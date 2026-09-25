/**
 * LeagueApps Table: the spreadsheet editor inside the Beaver Builder settings panel.
 *
 * Writes the table into the hidden `table_data` field as "dst1:" + base64(JSON)
 * (Beaver Builder json-decodes JSON-looking strings on save, so plain JSON would
 * not survive; see includes/class-ds-table-data.php). The value is written on every
 * keystroke so a quick Save never loses typing; the preview refresh is debounced.
 *
 * Sources (the "Rows come from" select):
 *   manual  an editable grid: add / move / delete rows and columns, paste cells
 *           from Excel or Google Sheets, import a CSV (AJAX upload + parse), undo.
 *   file    a CSV in the Media Library, previewed read-only; "Upload new version"
 *           replaces the file in place so every table synced to it updates.
 *   url     a Google Sheet or CSV link, fetched over AJAX; "Refresh now".
 * Column options (alignment, one line, hide on phones) are editable in every mode.
 */
/* global DSTable, wp, jQuery */
(function ($) {
	'use strict';

	var CFG = window.DSTable || {};
	var RENDER_CAP = 400;   // rows drawn in the panel; the full table is always saved
	var PREVIEW_CAP = 60;   // rows drawn for a synced file / link

	/* ------------------------------------------------------------ helpers */

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function b64enc(str) {
		if (window.TextEncoder) { var bytes = new TextEncoder().encode(str), bin = ''; for (var i = 0; i < bytes.length; i += 0x8000) { bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000)); } return btoa(bin); }
		return btoa(unescape(encodeURIComponent(str)));
	}
	function b64dec(b64) {
		var bin = atob(b64);
		if (window.TextDecoder) { var bytes = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) { bytes[i] = bin.charCodeAt(i); } return new TextDecoder().decode(bytes); }
		return decodeURIComponent(escape(bin));
	}
	function blankCol() { return { label: '', align: '', nowrap: false, hide: false }; }
	function normalize(t) {
		t = t && typeof t === 'object' ? t : {};
		var cols = Array.isArray(t.cols) ? t.cols.map(function (c) {
			c = c && typeof c === 'object' ? c : { label: c };
			return { label: String(c.label == null ? '' : c.label), align: ['left', 'center', 'right'].indexOf(c.align) !== -1 ? c.align : '', nowrap: !!c.nowrap, hide: !!c.hide };
		}) : [];
		var rows = Array.isArray(t.rows) ? t.rows.map(function (r) { return (Array.isArray(r) ? r : []).map(function (v) { return v == null ? '' : String(v); }); }) : [];
		var w = cols.length; rows.forEach(function (r) { w = Math.max(w, r.length); });
		while (cols.length < w) { cols.push(blankCol()); }
		rows.forEach(function (r) { while (r.length < w) { r.push(''); } });
		return { cols: cols, rows: rows };
	}
	function decode(v) {
		v = (v || '').trim();
		if (!v) { return { cols: [], rows: [] }; }
		try {
			if (v.indexOf('dst1:') === 0) { return normalize(JSON.parse(b64dec(v.slice(5)))); }
			return normalize(JSON.parse(v));
		} catch (e) { return { cols: [], rows: [] }; }
	}
	function encode(t) { return 'dst1:' + b64enc(JSON.stringify({ cols: t.cols, rows: t.rows, t: t.t || 0 })); }

	/** Split pasted spreadsheet text (tabs) or CSV text into rows of cells, quotes respected. */
	function splitGrid(text, delim) {
		text = String(text).replace(/\r\n?/g, '\n');
		if (text.slice(-1) === '\n') { text = text.slice(0, -1); }
		var rows = [], row = [], cell = '', q = false;
		for (var i = 0; i < text.length; i++) {
			var ch = text[i];
			if (q) {
				if (ch === '"') { if (text[i + 1] === '"') { cell += '"'; i++; } else { q = false; } }
				else { cell += ch; }
			} else if (ch === '"' && cell === '') { q = true; }
			else if (ch === delim) { row.push(cell); cell = ''; }
			else if (ch === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; }
			else { cell += ch; }
		}
		row.push(cell); rows.push(row);
		return rows;
	}
	function toCSV(t) {
		var line = function (cells) { return cells.map(function (v) { v = String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(','); };
		var out = [line(t.cols.map(function (c) { return c.label; }))];
		t.rows.forEach(function (r) { out.push(line(r)); });
		return out.join('\r\n') + '\r\n';
	}
	function ajax(action, data, onProgress) {
		return new Promise(function (resolve, reject) {
			var fd = data instanceof FormData ? data : new FormData();
			if (!(data instanceof FormData)) { Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); }); }
			fd.append('action', action); fd.append('nonce', CFG.nonce || '');
			var xhr = new XMLHttpRequest();
			xhr.open('POST', CFG.ajaxurl);
			xhr.withCredentials = true;
			if (onProgress && xhr.upload) { xhr.upload.onprogress = function (e) { if (e.lengthComputable) { onProgress(e.loaded / e.total); } }; }
			xhr.onload = function () {
				var j = null; try { j = JSON.parse(xhr.responseText); } catch (e) {}
				if (j && j.success) { resolve(j.data); }
				else { reject(new Error((j && j.data && j.data.message) || (xhr.status === 403 ? 'Your session expired. Save your work and reload the builder.' : 'The server did not answer (' + xhr.status + ').'))); }
			};
			xhr.onerror = function () { reject(new Error('Network error. Check your connection and try again.')); };
			xhr.send(fd);
		});
	}

	/* ------------------------------------------------------------- editor */

	function Editor($form, el) {
		this.$form = $form;
		this.el = el;
		this.store = $form.find('input[name="table_data"]');
		this.csvId = $form.find('input[name="csv_id"]');
		this.source = $form.find('select[name="source"]');
		this.csvHeader = $form.find('select[name="csv_header"]');
		this.csvUrl = $form.find('input[name="csv_url"]');
		this.state = decode(this.store.val());
		this.undoStack = [];
		this.synced = null;        // { rows, name, error } for file / link sources
		this.focus = null;         // { r, c } of the focused cell
		this.refreshTimer = null;
		this.build();
		this.bind();
		this.render();
		this.loadSynced();
	}

	Editor.prototype.mode = function () { return this.source.val() || 'manual'; };

	/** Persist now (so Save never misses a keystroke) and refresh the builder preview shortly after. */
	Editor.prototype.commit = function (immediatePreview) {
		this.store.val(encode(this.state));
		var self = this;
		clearTimeout(this.refreshTimer);
		// Beaver Builder refreshes the preview of a text field on keyup, not on change.
		this.refreshTimer = setTimeout(function () { self.store.trigger('keyup').trigger('change'); }, immediatePreview ? 0 : 450);
		this.updateCounts();
	};

	Editor.prototype.snapshot = function () {
		this.undoStack.push(JSON.stringify(this.state));
		if (this.undoStack.length > 40) { this.undoStack.shift(); }
		this.$undo.prop('disabled', false);
	};
	Editor.prototype.undo = function () {
		var s = this.undoStack.pop(); if (!s) { return; }
		this.state = normalize(JSON.parse(s));
		this.$undo.prop('disabled', !this.undoStack.length);
		this.render(); this.commit(true); this.say('Undone.');
	};

	Editor.prototype.build = function () {
		var h = '';
		h += '<div class="ds-te" data-mode="manual">';
		h += '<div class="ds-te-bar">';
		h += '<span class="ds-te-grp ds-te-only-manual"><button type="button" class="ds-te-btn" data-act="add-row" title="Add a row at the end">+ Row</button><button type="button" class="ds-te-btn" data-act="add-col" title="Add a column at the end">+ Column</button><button type="button" class="ds-te-btn ds-te-icon" data-act="undo" title="Undo the last row or column change" disabled>&#8630;</button></span>';
		h += '<span class="ds-te-grp ds-te-only-manual"><button type="button" class="ds-te-btn" data-act="import">Import CSV</button><button type="button" class="ds-te-btn" data-act="export">Export CSV</button></span>';
		h += '<span class="ds-te-grp ds-te-only-file"><button type="button" class="ds-te-btn ds-te-primary" data-act="upload">Upload CSV</button><button type="button" class="ds-te-btn" data-act="library">Media Library</button><button type="button" class="ds-te-btn" data-act="replace" title="Replace this file with a newer export. Every table synced to it updates.">Upload new version</button></span>';
		h += '<span class="ds-te-grp ds-te-only-url"><button type="button" class="ds-te-btn ds-te-primary" data-act="refresh">Refresh now</button></span>';
		h += '<span class="ds-te-grp ds-te-only-synced"><button type="button" class="ds-te-btn" data-act="to-editor" title="Stop syncing and edit these rows by hand">Copy into editor</button></span>';
		h += '<button type="button" class="ds-te-btn ds-te-icon ds-te-expand" data-act="expand" title="Open a larger editor">&#x2922;</button>';
		h += '</div>';
		h += '<div class="ds-te-status" role="status" aria-live="polite"></div>';
		h += '<div class="ds-te-progress" hidden><span></span></div>';
		h += '<div class="ds-te-drop"><div class="ds-te-scroll"><table class="ds-te-grid"><thead></thead><tbody></tbody></table></div>';
		h += '<div class="ds-te-empty" hidden></div>';
		h += '<div class="ds-te-dropmsg">Drop the CSV file to import it</div></div>';
		h += '<button type="button" class="ds-te-addrow ds-te-only-manual" data-act="add-row">+ Add row</button>';
		h += '<p class="ds-te-hint ds-te-only-manual">Tip: paste cells straight from Excel or Google Sheets. Enter moves down, Shift+Enter starts a new line in a cell.</p>';
		h += '<p class="ds-te-hint ds-te-only-synced">Rows come from the source and are read-only here. The &#9662; on a column still sets its alignment, wrapping and phone visibility.</p>';
		h += '<input type="file" class="ds-te-file" accept=".csv,.tsv,.txt,text/csv,text/plain" hidden>';
		h += '<div class="ds-te-menu" hidden role="menu"></div>';
		h += '<div class="ds-te-dialog" hidden role="dialog" aria-modal="false"></div>';
		h += '</div>';
		this.el.innerHTML = h;
		this.$root = $(this.el).find('.ds-te');
		this.$thead = this.$root.find('thead');
		this.$tbody = this.$root.find('tbody');
		this.$status = this.$root.find('.ds-te-status');
		this.$menu = this.$root.find('.ds-te-menu');
		this.$dialog = this.$root.find('.ds-te-dialog');
		this.$undo = this.$root.find('[data-act="undo"]');
		this.$file = this.$root.find('.ds-te-file');
		this.$empty = this.$root.find('.ds-te-empty');
		this.$progress = this.$root.find('.ds-te-progress');
		if (!CFG.canUpload) { this.$root.find('[data-act="upload"],[data-act="replace"]').prop('disabled', true).attr('title', 'Your account cannot upload files.'); }
	};

	Editor.prototype.say = function (msg, kind) {
		this.$status.text(msg || '').attr('data-kind', kind || '');
		var self = this; clearTimeout(this.sayTimer);
		if (msg && kind !== 'error' && kind !== 'sticky') { this.sayTimer = setTimeout(function () { self.updateCounts(); }, 3500); }
	};

	Editor.prototype.updateCounts = function () {
		if (this.$status.attr('data-kind') === 'error' || this.$status.attr('data-kind') === 'sticky') { return; }
		var m = this.mode();
		if (m === 'manual') {
			var n = this.state.rows.length, c = this.state.cols.length;
			this.$status.text(n ? (n + ' row' + (n === 1 ? '' : 's') + ' · ' + c + ' column' + (c === 1 ? '' : 's') + (n > RENDER_CAP ? ' · showing the first ' + RENDER_CAP + ' here' : '')) : '').attr('data-kind', '');
		} else if (this.synced && !this.synced.error) {
			var s = this.synced.rows.length - (this.headerOn() ? 1 : 0);
			this.$status.text((this.synced.name ? this.synced.name + ' · ' : '') + Math.max(0, s) + ' rows synced' + (this.synced.stale ? ' (last copy that loaded)' : '')).attr('data-kind', '');
		}
	};

	Editor.prototype.headerOn = function () { return this.csvHeader.val() !== 'no'; };

	/* ---------------------------------------------------------- rendering */

	Editor.prototype.render = function () {
		var m = this.mode();
		this.$root.attr('data-mode', m).toggleClass('is-synced', m !== 'manual');
		this.closeMenu();
		if (m === 'manual') { this.renderManual(); } else { this.renderSynced(); }
		this.updateCounts();
	};

	Editor.prototype.colHead = function (c, i, editable) {
		var col = this.state.cols[i] || blankCol();
		var flags = (col.align ? ' is-' + col.align : '') + (col.nowrap ? ' is-nowrap' : '') + (col.hide ? ' is-hidden-sm' : '');
		var label = editable
			? '<textarea rows="1" class="ds-te-head" data-c="' + i + '" placeholder="Column ' + (i + 1) + '" aria-label="Column ' + (i + 1) + ' heading">' + esc(c) + '</textarea>'
			: '<span class="ds-te-head-ro">' + (esc(c) || '<em>Column ' + (i + 1) + '</em>') + '</span>';
		return '<th class="ds-te-colh' + flags + '" data-c="' + i + '">' + label + '<button type="button" class="ds-te-colmenu" data-c="' + i + '" title="Column options" aria-haspopup="menu">&#9662;</button>' + (col.hide ? '<span class="ds-te-badge" title="Hidden on phones">&#128241;&#8416;</span>' : '') + '</th>';
	};

	Editor.prototype.renderManual = function () {
		var t = this.state, self = this;
		if (!t.cols.length) { t.cols = [blankCol(), blankCol(), blankCol()]; t.rows = [['', '', '']]; this.store.val(encode(t)); }
		var hh = '<tr><th class="ds-te-corner" aria-hidden="true"></th>';
		t.cols.forEach(function (c, i) { hh += self.colHead(c.label, i, true); });
		hh += '</tr>';
		this.$thead.html(hh);
		var bh = '';
		var n = Math.min(t.rows.length, RENDER_CAP);
		for (var r = 0; r < n; r++) {
			bh += '<tr data-r="' + r + '"><th class="ds-te-rowh"><button type="button" class="ds-te-rowmenu" data-r="' + r + '" title="Row options" aria-haspopup="menu">' + (r + 1) + '</button></th>';
			for (var c = 0; c < t.cols.length; c++) {
				var col = t.cols[c];
				bh += '<td class="' + (col.align ? 'is-' + col.align : '') + (col.hide ? ' is-hidden-sm' : '') + '"><textarea rows="1" class="ds-te-cell" data-r="' + r + '" data-c="' + c + '" aria-label="Row ' + (r + 1) + ', ' + esc(col.label || 'column ' + (c + 1)) + '">' + esc(t.rows[r][c]) + '</textarea></td>';
			}
			bh += '</tr>';
		}
		this.$tbody.html(bh);
		this.$empty.prop('hidden', true);
		this.growAll();
		if (t.rows.length > RENDER_CAP) {
			this.$empty.prop('hidden', false).html('Showing the first ' + RENDER_CAP + ' of ' + t.rows.length + ' rows here; every row is saved. For bulk edits, Export CSV, change it in a spreadsheet, then Import CSV.');
		}
	};

	Editor.prototype.renderSynced = function () {
		var self = this, s = this.synced, m = this.mode();
		this.$thead.html(''); this.$tbody.html('');
		if (!s) {
			var msg = m === 'file'
				? (this.csvId.val() ? 'Loading the CSV file…' : 'Upload a CSV, drop one here, or choose one from the Media Library. The table stays in sync with that file.')
				: (this.csvUrl.val() ? 'Loading the link…' : 'Paste a Google Sheet or CSV link in the field above. The sheet must be viewable by anyone with the link.');
			this.$empty.prop('hidden', false).text(msg);
			return;
		}
		if (s.error && !s.rows.length) { this.$empty.prop('hidden', false).text(s.error); return; }
		var rows = s.rows.slice();
		var head = this.headerOn() && rows.length ? rows.shift() : [];
		var w = 0; rows.concat([head]).forEach(function (r) { w = Math.max(w, r.length); });
		while (this.state.cols.length < w) { this.state.cols.push(blankCol()); }
		var hh = '<tr><th class="ds-te-corner" aria-hidden="true"></th>';
		for (var c = 0; c < w; c++) { hh += this.colHead(head[c] || '', c, false); }
		this.$thead.html(hh + '</tr>');
		var bh = '';
		rows.slice(0, PREVIEW_CAP).forEach(function (r, ri) {
			bh += '<tr><th class="ds-te-rowh"><span class="ds-te-rownum">' + (ri + 1) + '</span></th>';
			for (var c = 0; c < w; c++) { var col = self.state.cols[c] || blankCol(); bh += '<td class="ds-te-ro' + (col.align ? ' is-' + col.align : '') + (col.hide ? ' is-hidden-sm' : '') + '">' + esc(r[c] || '') + '</td>'; }
			bh += '</tr>';
		});
		this.$tbody.html(bh);
		this.$empty.prop('hidden', rows.length <= PREVIEW_CAP).text(rows.length > PREVIEW_CAP ? 'Showing the first ' + PREVIEW_CAP + ' of ' + rows.length + ' rows. Visitors see them all.' : '');
		if (s.error) { this.say(s.error + ' Showing the last copy that loaded.', 'error'); }
	};

	Editor.prototype.grow = function (ta) { ta.style.height = 'auto'; ta.style.height = Math.min(Math.max(ta.scrollHeight, 32), 160) + 'px'; };

	/** Fit every cell to its wrapped text in two layout passes (one write, one read), not one per cell. */
	Editor.prototype.growAll = function () {
		var tas = this.$root.find('textarea.ds-te-cell, textarea.ds-te-head').get();
		if (!tas.length || !this.el.offsetWidth) { return; }
		tas.forEach(function (t) { t.style.height = 'auto'; });
		var hs = tas.map(function (t) { return t.scrollHeight; });
		tas.forEach(function (t, i) { t.style.height = Math.min(Math.max(hs[i], 32), 160) + 'px'; });
	};

	/* -------------------------------------------------------- structure */

	Editor.prototype.addRow = function (at) {
		this.snapshot();
		var row = this.state.cols.map(function () { return ''; });
		if (at == null) { this.state.rows.push(row); } else { this.state.rows.splice(at, 0, row); }
		this.render(); this.commit();
		var r = at == null ? this.state.rows.length - 1 : at;
		this.focusCell(r, 0);
	};
	Editor.prototype.addCol = function (at) {
		if (this.state.cols.length >= (CFG.maxCols || 50)) { this.say('A table can have up to ' + (CFG.maxCols || 50) + ' columns.', 'error'); return; }
		this.snapshot();
		if (at == null) { at = this.state.cols.length; }
		this.state.cols.splice(at, 0, blankCol());
		this.state.rows.forEach(function (r) { r.splice(at, 0, ''); });
		this.render(); this.commit();
		var th = this.$thead.find('textarea[data-c="' + at + '"]')[0]; if (th) { th.focus(); }
	};
	Editor.prototype.delRow = function (r) {
		this.snapshot(); this.state.rows.splice(r, 1);
		if (!this.state.rows.length) { this.state.rows.push(this.state.cols.map(function () { return ''; })); }
		this.render(); this.commit(); this.say('Row deleted. Undo with ↶.');
	};
	Editor.prototype.delCol = function (c) {
		if (this.state.cols.length <= 1) { this.say('A table needs at least one column.', 'error'); return; }
		this.snapshot(); this.state.cols.splice(c, 1); this.state.rows.forEach(function (r) { r.splice(c, 1); });
		this.render(); this.commit(); this.say('Column deleted. Undo with ↶.');
	};
	Editor.prototype.moveRow = function (r, d) {
		var to = r + d; if (to < 0 || to >= this.state.rows.length) { return; }
		this.snapshot(); var x = this.state.rows.splice(r, 1)[0]; this.state.rows.splice(to, 0, x);
		this.render(); this.commit(); this.focusCell(to, (this.focus && this.focus.c) || 0);
	};
	Editor.prototype.moveCol = function (c, d) {
		var to = c + d; if (to < 0 || to >= this.state.cols.length) { return; }
		this.snapshot();
		var mv = function (a) { var x = a.splice(c, 1)[0]; a.splice(to, 0, x); };
		mv(this.state.cols); this.state.rows.forEach(mv);
		this.render(); this.commit();
	};

	Editor.prototype.focusCell = function (r, c) {
		var ta = this.$tbody.find('textarea[data-r="' + r + '"][data-c="' + c + '"]')[0];
		if (ta) { ta.focus(); var v = ta.value.length; try { ta.setSelectionRange(v, v); } catch (e) {} }
	};

	/** Spread pasted cells from (r, c), growing the table as needed. */
	Editor.prototype.pasteGrid = function (r, c, grid, noSnapshot) {
		var needCols = c + Math.max.apply(null, grid.map(function (x) { return x.length; }));
		if (needCols > (CFG.maxCols || 50)) { this.say('That paste is wider than ' + (CFG.maxCols || 50) + ' columns.', 'error'); return; }
		if (!noSnapshot) { this.snapshot(); }
		var t = this.state;
		while (t.cols.length < needCols) { t.cols.push(blankCol()); t.rows.forEach(function (row) { row.push(''); }); }
		grid.forEach(function (cells, i) {
			var rr = r + i;
			while (t.rows.length <= rr) { t.rows.push(t.cols.map(function () { return ''; })); }
			cells.forEach(function (v, j) { t.rows[rr][c + j] = v; });
		});
		this.render(); this.commit(true);
		this.say('Pasted ' + grid.length + ' row' + (grid.length === 1 ? '' : 's') + '.');
	};

	/* -------------------------------------------------------------- menus */

	Editor.prototype.openMenu = function (btn, items) {
		var self = this;
		var h = items.map(function (it) {
			if (it === '-') { return '<div class="ds-te-sep"></div>'; }
			return '<button type="button" role="menuitem" class="ds-te-mi' + (it.on ? ' is-on' : '') + (it.danger ? ' is-danger' : '') + '" data-k="' + it.k + '"' + (it.disabled ? ' disabled' : '') + '>' + esc(it.label) + '</button>';
		}).join('');
		this.$menu.html(h).prop('hidden', false);
		var br = btn.getBoundingClientRect(), rr = this.el.getBoundingClientRect(), mw = this.$menu.outerWidth();
		var left = Math.min(br.left - rr.left, rr.width - mw - 4);
		this.$menu.css({ top: (br.bottom - rr.top + 4) + 'px', left: Math.max(0, left) + 'px' });
		this.menuItems = items;
		this.$menu.off('click').on('click', '.ds-te-mi', function () {
			var k = $(this).attr('data-k');
			items.forEach(function (it) { if (it.k === k && it.run) { it.run(); } });
			self.closeMenu();
		});
		this.$menu.find('.ds-te-mi:not([disabled])').first().trigger('focus');
	};
	Editor.prototype.closeMenu = function () { if (this.$menu) { this.$menu.prop('hidden', true).empty(); } };

	Editor.prototype.colMenu = function (btn, c) {
		var self = this, col = this.state.cols[c] || blankCol(), manual = this.mode() === 'manual';
		var setOpt = function (k, v) { self.snapshot(); while (self.state.cols.length <= c) { self.state.cols.push(blankCol()); } self.state.cols[c][k] = v; self.render(); self.commit(true); };
		var items = [
			{ k: 'al', label: 'Align left', on: !col.align || col.align === 'left', run: function () { setOpt('align', ''); } },
			{ k: 'ac', label: 'Align centre', on: col.align === 'center', run: function () { setOpt('align', 'center'); } },
			{ k: 'ar', label: 'Align right', on: col.align === 'right', run: function () { setOpt('align', 'right'); } },
			'-',
			{ k: 'nw', label: 'Keep on one line', on: col.nowrap, run: function () { setOpt('nowrap', !col.nowrap); } },
			{ k: 'hd', label: 'Hide on phones', on: col.hide, run: function () { setOpt('hide', !col.hide); } }
		];
		if (manual) {
			items = items.concat(['-',
				{ k: 'il', label: 'Insert column left', run: function () { self.addCol(c); } },
				{ k: 'ir', label: 'Insert column right', run: function () { self.addCol(c + 1); } },
				{ k: 'ml', label: 'Move left', disabled: c === 0, run: function () { self.moveCol(c, -1); } },
				{ k: 'mr', label: 'Move right', disabled: c >= this.state.cols.length - 1, run: function () { self.moveCol(c, 1); } },
				'-',
				{ k: 'dc', label: 'Delete column', danger: true, run: function () { self.delCol(c); } }
			]);
		}
		this.openMenu(btn, items);
	};

	Editor.prototype.rowMenu = function (btn, r) {
		var self = this;
		this.openMenu(btn, [
			{ k: 'ia', label: 'Insert row above', run: function () { self.addRow(r); } },
			{ k: 'ib', label: 'Insert row below', run: function () { self.addRow(r + 1); } },
			{ k: 'du', label: 'Duplicate row', run: function () { self.snapshot(); self.state.rows.splice(r + 1, 0, self.state.rows[r].slice()); self.render(); self.commit(); } },
			{ k: 'mu', label: 'Move up', disabled: r === 0, run: function () { self.moveRow(r, -1); } },
			{ k: 'md', label: 'Move down', disabled: r >= self.state.rows.length - 1, run: function () { self.moveRow(r, 1); } },
			'-',
			{ k: 'dr', label: 'Delete row', danger: true, run: function () { self.delRow(r); } }
		]);
	};

	/* ------------------------------------------------------------ dialogs */

	Editor.prototype.dialog = function (html, buttons) {
		var self = this;
		this.$dialog.html('<div class="ds-te-dialog-in">' + html + '<div class="ds-te-dialog-btns">' + buttons.map(function (b, i) {
			return '<button type="button" class="ds-te-btn' + (b.primary ? ' ds-te-primary' : '') + '" data-i="' + i + '">' + esc(b.label) + '</button>';
		}).join('') + '</div></div>').prop('hidden', false);
		this.$dialog.off('click').on('click', 'button[data-i]', function () {
			var b = buttons[+$(this).attr('data-i')];
			self.$dialog.prop('hidden', true).empty();
			if (b && b.run) { b.run(); }
		});
		this.$dialog.find('.ds-te-primary').first().trigger('focus');
	};

	/** After a CSV is read in the editor: replace, append, or keep the table synced to the file. */
	Editor.prototype.offerImport = function (data, file) {
		var self = this, rows = data.rows || [];
		var w = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 0);
		var has = this.state.rows.some(function (r) { return r.join('') !== ''; });
		var warn = (data.warnings || []).length ? '<p class="ds-te-warn">' + data.warnings.map(esc).join('<br>') + '</p>' : '';
		var apply = function (mode) {
			var header = self.$dialog.data('header') !== false;
			var rr = rows.slice();
			var head = header ? rr.shift() : null;
			self.snapshot();
			if (mode === 'replace' || !has) {
				self.state = normalize({ cols: (head || new Array(w).fill('')).map(function (l) { return { label: l }; }), rows: rr });
			} else {
				var t = self.state;
				while (t.cols.length < w) { t.cols.push(blankCol()); t.rows.forEach(function (r) { r.push(''); }); }
				rr.forEach(function (r) { var nr = t.cols.map(function (_, i) { return r[i] || ''; }); t.rows.push(nr); });
			}
			self.render(); self.commit(true);
			self.say('Imported ' + rr.length + ' rows from ' + (data.name || 'the file') + '.');
		};
		var hdr = '<label class="ds-te-check"><input type="checkbox" checked> First row is the column headings</label>';
		var btns = [];
		if (has) {
			btns.push({ label: 'Replace the table', primary: true, run: function () { apply('replace'); } });
			btns.push({ label: 'Add below', run: function () { apply('append'); } });
		} else {
			btns.push({ label: 'Import', primary: true, run: function () { apply('replace'); } });
		}
		if (file && CFG.canUpload) {
			// Only now is the file stored (in the Media Library), because the table reads it from there.
			btns.push({ label: 'Keep synced to this file', run: function () {
				self.csvHeader.val(self.$dialog.data('header') === false ? 'no' : 'yes');
				self.csvId.val(''); // no stale load of an earlier file can land after this upload
				self.source.val('file').trigger('change');
				self.upload(file);
			} });
		}
		btns.push({ label: 'Cancel' });
		this.dialog('<p><strong>' + esc(data.name || 'CSV file') + '</strong>: ' + rows.length + ' rows × ' + w + ' columns.</p>' + hdr + warn, btns);
		this.$dialog.data('header', true);
		this.$dialog.find('input[type="checkbox"]').on('change', function () { self.$dialog.data('header', this.checked); });
	};

	/* ------------------------------------------------------- CSV + sources */

	Editor.prototype.upload = function (file, replaceId) {
		var self = this;
		if (!file) { return; }
		if (!/\.(csv|tsv|txt)$/i.test(file.name)) { this.say('Choose a .csv file (in Excel or Google Sheets: File > Download > CSV).', 'error'); return; }
		if (CFG.maxBytes && file.size > CFG.maxBytes) { this.say('The file is larger than ' + Math.round(CFG.maxBytes / 1048576) + ' MB.', 'error'); return; }
		// Importing into the table only reads the file; the server keeps nothing.
		var readOnly = !replaceId && this.mode() === 'manual';
		var fd = new FormData(); fd.append('file', file, file.name);
		if (replaceId) { fd.append('replace_id', replaceId); }
		if (readOnly) { fd.append('store', '0'); }
		this.$progress.prop('hidden', false).find('span').css('width', '0%');
		this.say((replaceId ? 'Replacing ' : readOnly ? 'Reading ' : 'Uploading ') + file.name + '…', 'sticky');
		ajax('ds_table_upload', fd, function (p) { self.$progress.find('span').css('width', Math.round(p * 100) + '%'); })
			.then(function (data) {
				self.$progress.prop('hidden', true);
				self.$status.attr('data-kind', '');
				if (readOnly) { self.offerImport(data, file); return; }
				self.csvId.val(String(data.id));
				self.synced = { rows: data.rows, name: data.name, error: '', warnings: data.warnings };
				self.render(); self.state.t = Date.now(); self.commit(true); // new data: refresh the preview now
				self.say((replaceId ? 'New version of ' : 'Synced to ') + data.name + ': ' + Math.max(0, data.rows.length - (self.headerOn() ? 1 : 0)) + ' rows.' + ((data.warnings || []).length ? ' ' + data.warnings.join(' ') : ''));
			})
			.catch(function (err) { self.$progress.prop('hidden', true); self.$status.attr('data-kind', ''); self.say(err.message, 'error'); });
	};

	Editor.prototype.loadSynced = function (force) {
		var self = this, m = this.mode();
		if (m === 'file') {
			var id = parseInt(this.csvId.val(), 10);
			if (!id) { this.synced = null; this.render(); return; }
			ajax('ds_table_parse', { id: id }).then(function (d) { self.synced = { rows: d.rows, name: d.name, error: '' }; self.render(); })
				.catch(function (e) { self.synced = { rows: [], error: e.message }; self.render(); });
		} else if (m === 'url') {
			var url = (this.csvUrl.val() || '').trim();
			if (!url) { this.synced = null; this.render(); return; }
			this.say(force ? 'Fetching the latest copy…' : 'Loading the link…', 'sticky');
			ajax('ds_table_fetch', { url: url, force: force ? 1 : 0 }).then(function (d) {
				self.$status.attr('data-kind', '');
				self.synced = { rows: d.rows, name: '', error: d.error || '', stale: d.stale };
				self.render();
				if (force) { self.state.t = Date.now(); self.commit(true); self.say('Up to date: ' + Math.max(0, d.rows.length - (self.headerOn() ? 1 : 0)) + ' rows.'); }
			}).catch(function (e) { self.$status.attr('data-kind', ''); self.synced = { rows: [], error: e.message }; self.render(); });
		} else {
			this.synced = null; this.render();
		}
	};

	Editor.prototype.library = function () {
		var self = this;
		if (!window.wp || !wp.media) { this.say('The Media Library is not available here.', 'error'); return; }
		var frame = wp.media({ title: 'Choose a CSV file', button: { text: 'Use this file' }, multiple: false, library: { type: ['text/csv', 'text/plain', 'text/tab-separated-values'] } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			if (!/\.(csv|tsv|txt)$/i.test(a.filename || a.url || '')) { self.say('That file is not a CSV.', 'error'); return; }
			self.csvId.val(String(a.id)).trigger('change');
			self.synced = null; self.render(); self.loadSynced(); self.state.t = Date.now(); self.commit(true);
		});
		frame.open();
	};

	Editor.prototype.toEditor = function () {
		var self = this, s = this.synced;
		if (!s || !s.rows.length) { this.say('There are no synced rows to copy yet.', 'error'); return; }
		this.dialog('<p>Copy these ' + Math.max(0, s.rows.length - (this.headerOn() ? 1 : 0)) + ' rows into the editor? The table stops syncing and you edit it by hand from then on.</p>', [
			{ label: 'Copy and edit', primary: true, run: function () {
				self.snapshot();
				var rr = s.rows.slice(), head = self.headerOn() ? rr.shift() : [];
				var opts = self.state.cols;
				var t = normalize({ cols: (head.length ? head : new Array(rr[0] ? rr[0].length : 0).fill('')).map(function (l, i) { var o = opts[i] || blankCol(); return { label: l, align: o.align, nowrap: o.nowrap, hide: o.hide }; }), rows: rr });
				self.state = t; self.source.val('manual').trigger('change'); self.render(); self.commit(true);
				self.say('Copied. The table is now edited here.');
			} },
			{ label: 'Cancel' }
		]);
	};

	Editor.prototype.exportCSV = function () {
		var blob = new Blob(['﻿' + toCSV(this.state)], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob); a.download = 'table.csv';
		document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
	};

	/* ------------------------------------------------------------- events */

	Editor.prototype.bind = function () {
		var self = this, $r = this.$root;

		$r.on('click', '[data-act]', function (e) {
			e.preventDefault();
			var act = $(this).attr('data-act');
			if (act === 'add-row') { self.addRow(); }
			else if (act === 'add-col') { self.addCol(); }
			else if (act === 'undo') { self.undo(); }
			else if (act === 'import' || act === 'upload') { self.$file.data('replace', 0).val('').trigger('click'); }
			else if (act === 'replace') {
				var id = parseInt(self.csvId.val(), 10);
				if (!id) { self.say('Upload or choose a CSV first.', 'error'); return; }
				self.$file.data('replace', id).val('').trigger('click');
			}
			else if (act === 'library') { self.library(); }
			else if (act === 'refresh') { self.loadSynced(true); }
			else if (act === 'to-editor') { self.toEditor(); }
			else if (act === 'export') { self.exportCSV(); }
			else if (act === 'expand') { self.expand(!$r.hasClass('is-expanded')); }
		});

		this.$file.on('change', function () { var f = this.files && this.files[0]; self.upload(f, $(this).data('replace') || 0); });

		// Cells and headings: persist on every keystroke.
		$r.on('input', 'textarea.ds-te-cell', function () {
			var r = +this.getAttribute('data-r'), c = +this.getAttribute('data-c');
			if (self.state.rows[r]) { self.state.rows[r][c] = this.value; self.commit(); }
			self.grow(this);
		});
		$r.on('input', 'textarea.ds-te-head', function () {
			var c = +this.getAttribute('data-c');
			if (self.state.cols[c]) { self.state.cols[c].label = this.value.replace(/\n/g, ' '); self.commit(); }
		});
		$r.on('focus', 'textarea.ds-te-cell', function () { self.focus = { r: +this.getAttribute('data-r'), c: +this.getAttribute('data-c') }; });

		$r.on('keydown', 'textarea.ds-te-cell, textarea.ds-te-head', function (e) {
			var isHead = this.classList.contains('ds-te-head');
			var r = isHead ? -1 : +this.getAttribute('data-r'), c = +this.getAttribute('data-c');
			if (e.key === 'Enter' && !e.shiftKey && !e.altKey) {
				e.preventDefault();
				if (r + 1 >= self.state.rows.length) { self.addRow(); self.focusCell(self.state.rows.length - 1, c); }
				else { self.focusCell(r + 1, c); }
			} else if (e.key === 'ArrowDown' && (isHead || this.selectionEnd === this.value.length)) {
				if (r + 1 < Math.min(self.state.rows.length, RENDER_CAP)) { e.preventDefault(); self.focusCell(r + 1, c); }
			} else if (e.key === 'ArrowUp' && this.selectionStart === 0 && !isHead) {
				e.preventDefault();
				if (r > 0) { self.focusCell(r - 1, c); } else { var th = self.$thead.find('textarea[data-c="' + c + '"]')[0]; if (th) { th.focus(); } }
			}
		});

		$r.on('paste', 'textarea.ds-te-cell, textarea.ds-te-head', function (e) {
			var text = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
			if (!text || (text.indexOf('\t') === -1 && text.replace(/\r?\n$/, '').indexOf('\n') === -1)) { return; } // a plain value: let the browser paste it
			var delim = text.indexOf('\t') !== -1 ? '\t' : ',';
			var grid = splitGrid(text, delim);
			e.preventDefault();
			if (this.classList.contains('ds-te-head')) {
				// Pasting a block into the headings: first line = headings, the rest = rows.
				var c0 = +this.getAttribute('data-c'), head = grid.shift();
				self.snapshot();
				while (self.state.cols.length < c0 + head.length) { self.state.cols.push(blankCol()); self.state.rows.forEach(function (row) { row.push(''); }); }
				head.forEach(function (v, j) { self.state.cols[c0 + j].label = v; });
				if (grid.length) { self.pasteGrid(0, c0, grid, true); } else { self.render(); self.commit(true); }
				return;
			}
			self.pasteGrid(+this.getAttribute('data-r'), +this.getAttribute('data-c'), grid);
		});

		$r.on('click', '.ds-te-colmenu', function (e) { e.preventDefault(); e.stopPropagation(); self.colMenu(this, +this.getAttribute('data-c')); });
		$r.on('click', '.ds-te-rowmenu', function (e) { e.preventDefault(); e.stopPropagation(); self.rowMenu(this, +this.getAttribute('data-r')); });
		$r.on('keydown', function (e) {
			if (e.key === 'Escape') {
				if (!self.$menu.prop('hidden')) { self.closeMenu(); e.stopPropagation(); }
				else if (!self.$dialog.prop('hidden')) { self.$dialog.prop('hidden', true).empty(); e.stopPropagation(); }
				else if ($r.hasClass('is-expanded')) { self.expand(false); e.stopPropagation(); }
			}
		});

		// Drop a CSV anywhere on the grid.
		var drop = $r.find('.ds-te-drop')[0], depth = 0;
		drop.addEventListener('dragenter', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1) { depth++; $r.addClass('is-dragging'); e.preventDefault(); } });
		drop.addEventListener('dragover', function (e) { if ($r.hasClass('is-dragging')) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
		drop.addEventListener('dragleave', function () { if (--depth <= 0) { depth = 0; $r.removeClass('is-dragging'); } });
		drop.addEventListener('drop', function (e) {
			if (!$r.hasClass('is-dragging')) { return; }
			e.preventDefault(); depth = 0; $r.removeClass('is-dragging');
			var f = e.dataTransfer.files && e.dataTransfer.files[0];
			if (!CFG.canUpload && self.mode() !== 'manual') { self.say('Your account cannot upload files.', 'error'); return; }
			self.upload(f, self.mode() === 'file' && parseInt(self.csvId.val(), 10) && window.confirm('Replace the synced file with this one? Every table synced to it will update.') ? parseInt(self.csvId.val(), 10) : 0);
		});

		// Re-fit wrapped cells when the panel is resized or the editor goes full screen.
		if (window.ResizeObserver) {
			var rt = null, lastW = 0;
			new ResizeObserver(function (entries) {
				var w = Math.round(entries[0].contentRect.width); if (w === lastW) { return; } lastW = w;
				clearTimeout(rt); rt = setTimeout(function () { if (self.mode() === 'manual') { self.growAll(); } }, 80);
			}).observe(this.el);
		}

		// Settings the editor depends on.
		this.source.on('change.dste', function () { self.synced = null; self.render(); self.loadSynced(); });
		this.csvHeader.on('change.dste', function () { self.render(); });
		var urlTimer = null;
		this.csvUrl.on('input.dste change.dste', function () { clearTimeout(urlTimer); urlTimer = setTimeout(function () { if (self.mode() === 'url') { self.synced = null; self.loadSynced(); } }, 700); });
	};

	Editor.prototype.expand = function (on) {
		var $r = this.$root, self = this;
		if (on) {
			this.$placeholder = $('<div class="ds-te-placeholder">The table editor is open full screen.</div>').insertBefore($r);
			$r.addClass('is-expanded').appendTo(document.body);
			$r.find('.ds-te-expand').html('Done').attr('title', 'Back to the settings panel').trigger('focus');
			$('body').addClass('ds-te-open');
			// Moving the editor drops focus to <body>, so listen at the document, first: Escape
			// closes the full-screen editor (or its open menu) and never reaches Beaver Builder,
			// which would otherwise treat it as Cancel on the whole settings panel.
			this.escHandler = function (e) {
				if (e.key !== 'Escape') { return; }
				e.preventDefault(); e.stopPropagation();
				if (!self.$menu.prop('hidden')) { self.closeMenu(); }
				else if (!self.$dialog.prop('hidden')) { self.$dialog.prop('hidden', true).empty(); }
				else { self.expand(false); }
			};
			document.addEventListener('keydown', this.escHandler, true);
		} else {
			if (this.escHandler) { document.removeEventListener('keydown', this.escHandler, true); this.escHandler = null; }
			if (this.$placeholder) { $r.removeClass('is-expanded').insertAfter(this.$placeholder); this.$placeholder.remove(); this.$placeholder = null; }
			$r.find('.ds-te-expand').html('&#x2922;').attr('title', 'Open a larger editor');
			$('body').removeClass('ds-te-open');
			if (this.mode() === 'manual') { this.growAll(); }
			$r.find('.ds-te-expand').trigger('focus');
		}
		this.closeMenu();
	};

	/* --------------------------------------------------------------- boot */

	function init() {
		$('.fl-builder-settings:visible').each(function () {
			var $form = $(this);
			var el = $form.find('[data-ds-table-editor]')[0];
			if (!el || el.dsTableEditor) { return; }
			el.dsTableEditor = new Editor($form, el);
		});
	}

	function boot() {
		if (!window.FLBuilder || typeof window.FLBuilder.addHook !== 'function') { setTimeout(boot, 150); return; }
		window.FLBuilder.addHook('settings-form-init', function () { [0, 120, 400].forEach(function (d) { setTimeout(init, d); }); });
		// One listener for every editor: a click outside an open row / column menu closes it.
		$(document).on('mousedown', function (e) { if (!$(e.target).closest('.ds-te-menu, .ds-te-colmenu, .ds-te-rowmenu').length) { $('.ds-te-menu').prop('hidden', true).empty(); } });
		// Save / Cancel close the panel: collapse a full-screen editor first.
		$(document).on('mousedown', '.fl-builder-settings-save, .fl-builder-settings-cancel', function () {
			$('[data-ds-table-editor]').each(function () { if (this.dsTableEditor && this.dsTableEditor.$root.hasClass('is-expanded')) { this.dsTableEditor.expand(false); } });
			$('body').removeClass('ds-te-open');
		});
	}
	boot();
}(jQuery));
