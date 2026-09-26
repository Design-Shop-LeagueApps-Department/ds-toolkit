/**
 * Post Loop > Manage entries: edit the posts this loop shows from the settings panel.
 *
 * Lists every entry of the loop's post type (and taxonomy filter), shown or hidden, in the
 * loop's order. Each row opens a form built from the post type's ACF fields; entries can be
 * added, removed (to the Trash), hidden and dragged into order. Nothing is written here:
 * changes go into the hidden `pl_manage` setting ("dsm1:" + base64 JSON), the builder preview
 * shows them, and DS_Loop_Manager writes them to the posts when the page is published.
 */
/* global DSLoopManager, FLBuilder, wp, jQuery */
(function ($) {
	'use strict';

	var CFG = window.DSLoopManager || {};
	var PREFIX = 'dsm1:';

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function b64enc(str) { var bytes = new TextEncoder().encode(str), bin = ''; for (var i = 0; i < bytes.length; i += 0x8000) { bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000)); } return btoa(bin); }
	function b64dec(b64) { var bin = atob(b64), bytes = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) { bytes[i] = bin.charCodeAt(i); } return new TextDecoder().decode(bytes); }
	function decode(v) { v = (v || '').trim(); if (v.indexOf(PREFIX) !== 0) { return null; } try { return JSON.parse(b64dec(v.slice(PREFIX.length))); } catch (e) { return null; } }
	/** A saved change set reduced to the shape this panel writes (the server cleans it again on save). */
	function tidy(c) {
		var key = function (k) { return /^(?:[1-9]\d{0,9}|n[1-9]\d{0,5})$/.test(String(k)); };
		var out = { pt: String(c.pt || '').replace(/[^a-z0-9_-]/g, ''), t: +c.t || 0, order: null, items: {}, trash: [], defaults: {} };
		(Array.isArray(c.trash) ? c.trash : []).forEach(function (x) { x = parseInt(x, 10); if (x > 0 && out.trash.indexOf(x) === -1) { out.trash.push(x); } });
		if (Array.isArray(c.order)) { out.order = c.order.map(String).filter(key); }
		Object.keys(c.items && typeof c.items === 'object' ? c.items : {}).forEach(function (k) { if (key(k) && c.items[k] && typeof c.items[k] === 'object') { out.items[k] = c.items[k]; } });
		if (c.defaults && typeof c.defaults === 'object') { out.defaults = c.defaults; }
		return out;
	}
	/**
	 * HTML the simple box can hold: p, br, b/strong, i/em, lists, links. Returns null when the
	 * text has more than that (a table, headings, images): editing it here would lose it.
	 */
	var RTE_TAGS = { P: 1, BR: 1, B: 1, STRONG: 1, I: 1, EM: 1, UL: 1, OL: 1, LI: 1, A: 1 };
	function simpleHtml(html) {
		var doc = new DOMParser().parseFromString('<div>' + (html || '') + '</div>', 'text/html'), root = doc.body.firstChild, ok = true;
		(function walk(n) {
			Array.prototype.slice.call(n.childNodes).forEach(function (c) {
				if (c.nodeType === 3) { return; }
				if (c.nodeType !== 1) { c.parentNode.removeChild(c); return; }
				if (!RTE_TAGS[c.tagName]) {
					if (/^(SPAN|DIV|FONT|U)$/.test(c.tagName) && !c.attributes.length) { walk(c); while (c.firstChild) { c.parentNode.insertBefore(c.firstChild, c); } c.parentNode.removeChild(c); return; }
					ok = false; return;
				}
				Array.prototype.slice.call(c.attributes).forEach(function (a) {
					var keep = c.tagName === 'A' && (a.name === 'href' ? /^(https?:\/\/|mailto:|tel:|\/)/i.test(a.value.trim()) : a.name === 'target' || a.name === 'rel');
					if (!keep) { c.removeAttribute(a.name); }
				});
				walk(c);
			});
		}(root));
		return ok ? root.innerHTML : null;
	}
	function same(a, b) { return JSON.stringify(a == null ? '' : a) === JSON.stringify(b == null ? '' : b); }
	function post(action, data) {
		return new Promise(function (resolve, reject) {
			$.post(CFG.ajaxurl, $.extend({ action: action, nonce: CFG.nonce }, data)).done(function (r) {
				if (r && r.success) { resolve(r.data); } else { reject(new Error((r && r.data && r.data.message) || 'Something went wrong.')); }
			}).fail(function () { reject(new Error('The server did not answer. Try again.')); });
		});
	}
	function mediaFrame(title, cb) {
		if (!window.wp || !wp.media) { window.alert('The Media Library is not available here.'); return; }
		var f = wp.media({ title: title, button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
		f.on('open', function () { if (f.content && f.content.mode) { f.content.mode('browse'); } });
		f.on('select', function () { var a = f.state().get('selection').first().toJSON(); cb({ id: a.id, url: (a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url) || a.url }); });
		f.open();
	}

	/* ------------------------------------------------------------ Manager */

	function Manager(form, el) {
		this.form = form; this.$el = $(el);
		this.store = form.find('input[name="pl_manage"]');
		this.data = null; this.openKey = null; this.drag = null;
		this.ch = this.blank();
		var saved = decode(this.store.val());
		if (saved && saved.pt) { this.ch = $.extend(this.blank(), tidy(saved)); }
		this.bind();
		this.load();
	}

	Manager.prototype.blank = function () { return { pt: '', t: 0, order: null, items: {}, trash: [], defaults: {} }; };
	/** A row by its key, without building a selector from it. */
	Manager.prototype.row = function (k) { return this.$el.find('.ds-lm-row').filter(function () { return this.getAttribute('data-k') === String(k); }); };
	Manager.prototype.val = function (name) {
		var f = this.form.find('[name="' + name + '"]');
		if (!f.length) { return ''; }
		if (f.is('select[multiple]')) { return (f.val() || []).join(','); }
		return f.first().val() || '';
	};
	Manager.prototype.query = function () {
		var q = { filter_tax: this.val('filter_tax'), filter_terms: this.val('filter_terms'), order_by: this.val('order_by'), order: this.val('order') };
		if (q.filter_tax) {
			// Term pickers are BB "suggest" fields: the chosen IDs live in the hidden as_values_<name> (",14,15,").
			var k = 'flt_' + q.filter_tax.replace(/-/g, '_');
			var ids = this.val('as_values_' + k);
			if (!ids.replace(/,/g, '')) {
				// Right after the form opens BB has not filled that hidden input yet: use the picker's data-value,
				// then the settings the form was opened with.
				try { ids = JSON.parse(this.form.find('input[name="' + k + '"]').attr('data-value') || '[]').map(function (t) { return t.value; }).join(','); } catch (e) { ids = ''; }
				if (!ids) { var cfg = window.FLBuilderSettingsForms && FLBuilderSettingsForms.config && FLBuilderSettingsForms.config.settings; ids = cfg && cfg[k] ? String(cfg[k]) : ''; }
			}
			q[k] = String(ids).split(',').filter(Boolean).join(',');
		}
		// The loop's Include / Exclude pickers (suggest fields too), so the list matches what the loop can show.
		var pt = this.val('post_type').replace(/-/g, '_'), self = this;
		['inc_', 'exc_'].forEach(function (p) {
			var n = p + pt, v = self.val('as_values_' + n);
			if (!v.replace(/,/g, '')) { try { v = JSON.parse(self.form.find('input[name="' + n + '"]').attr('data-value') || '[]').map(function (t) { return t.value; }).join(','); } catch (e) { v = ''; } }
			v = String(v).split(',').filter(function (x) { return /^\d+$/.test(x); }).join(',');
			if (v) { q[n] = v; }
		});
		return q;
	};

	Manager.prototype.load = function () {
		var self = this, pt = this.val('post_type');
		if (this.val('source') === 'archive') { this.msg('Manage entries works when the loop shows a post type (Query > Source: Custom).'); return; }
		if (!pt) { this.msg('Choose a Post Type in the Query tab.'); return; }
		if (this.ch.pt && this.ch.pt !== pt) {
			if (this.count() && !window.confirm('You have unpublished changes to other entries. Switching the post type discards them. Continue?')) { return; }
			this.ch = this.blank(); this.save(true);
		}
		this.msg('Loading entries…');
		var q = this.query(); this.lastQ = JSON.stringify(q);
		// The query fields can still be settling when the form opens; if they change, load again.
		clearTimeout(this.rq); this.rq = setTimeout(function () { if (JSON.stringify(self.query()) !== self.lastQ) { self.load(); } }, 900);
		post('ds_loop_manage_list', { post_type: pt, q: q }).then(function (d) {
			self.data = d; self.ch.pt = pt; self.ch.defaults = d.defaults || {};
			self.base = {}; d.items.forEach(function (it) { self.base[String(it.id)] = it; });
			self.render();
		}).catch(function (e) { self.msg(e.message); });
	};

	Manager.prototype.msg = function (t) { this.$el.html('<p class="ds-lm-msg">' + esc(t) + '</p>'); };

	/** Keys in display order: the saved order, else the loop's, then new entries; trashed left out. */
	Manager.prototype.keys = function () {
		var self = this, keys = this.ch.order ? this.ch.order.slice() : this.data.items.map(function (it) { return String(it.id); });
		Object.keys(this.ch.items).forEach(function (k) { if (keys.indexOf(k) === -1 && (self.base[k] || self.ch.items[k]._new)) { keys.push(k); } });
		return keys.filter(function (k) { return self.ch.trash.indexOf(+k) === -1 && (self.base[k] || (self.ch.items[k] && self.ch.items[k]._new)); });
	};
	/** The entry as it will be after publish: stored values with the pending changes on top. */
	Manager.prototype.entry = function (k) {
		var b = this.base[k] || { id: 0, title: '', status: 'publish', thumb: { id: 0, url: '' }, content: '', fields: {}, terms: this.ch.defaults || {} };
		var c = this.ch.items[k] || {}, e = $.extend(true, {}, b);
		['title', 'status', 'thumb', 'content'].forEach(function (p) { if (c.hasOwnProperty(p)) { e[p] = c[p]; } });
		if (c.fields) { Object.keys(c.fields).forEach(function (n) { e.fields[n] = c.fields[n]; }); }
		if (c.terms) { Object.keys(c.terms).forEach(function (t) { e.terms[t] = c.terms[t]; }); }
		return e;
	};
	Manager.prototype.count = function () { return Object.keys(this.ch.items).length + this.ch.trash.length + (this.ch.order ? 1 : 0); };

	/** Record one change; a value set back to what is stored stops counting as a change. */
	Manager.prototype.set = function (k, path, value) {
		var c = this.ch.items[k] || (this.ch.items[k] = {}), b = this.base[k];
		if (path[0] === 'fields' || path[0] === 'terms') {
			c[path[0]] = c[path[0]] || {};
			if (b && same(b[path[0]][path[1]], value)) { delete c[path[0]][path[1]]; } else { c[path[0]][path[1]] = value; }
			if (!Object.keys(c[path[0]]).length) { delete c[path[0]]; }
		} else if (b && same(b[path[0]], value)) { delete c[path[0]]; } else { c[path[0]] = value; }
		if (b && !Object.keys(c).length) { delete this.ch.items[k]; }
		this.save();
		this.renderBar(k);
	};

	Manager.prototype.save = function (now) {
		var c = this.ch, has = this.count() > 0;
		// t marks this editing session, so the same edits made again later are a new change set
		// (the server applies each change set once).
		if (has && !c.t) { c.t = Date.now(); }
		if (!has) { c.t = 0; }
		this.store.val(has ? PREFIX + b64enc(JSON.stringify({ pt: c.pt, t: c.t, order: c.order, items: c.items, trash: c.trash, defaults: c.defaults })) : '');
		var self = this; clearTimeout(this.t);
		// Beaver Builder refreshes the preview of a text field on keyup, not on change.
		this.t = setTimeout(function () { self.store.trigger('keyup').trigger('change'); }, now ? 0 : 500);
		this.$el.find('.ds-lm-pending').text(has ? this.count() + ' change' + (this.count() === 1 ? '' : 's') + ' save when you publish' : 'No changes');
	};

	/* ------------------------------------------------------------- render */

	Manager.prototype.sub = function (e) {
		var f = (this.data.schema.fields || []).filter(function (x) { return x.type === 'text' || x.type === 'select'; })[0];
		return f ? String(e.fields[f.name] || '') : '';
	};

	Manager.prototype.render = function () {
		var d = this.data, s = d.schema, self = this, keys = this.keys();
		var h = '<div class="ds-lm-head"><strong>' + esc(s.label) + ' <span class="ds-lm-n">(' + keys.length + ')</span></strong>'
			+ (d.canCreate ? '<button type="button" class="ds-lm-btn ds-lm-btn--primary" data-act="add">+ Add ' + esc(s.singular.toLowerCase()) + '</button>' : '') + '</div>'
			+ '<p class="ds-lm-pending" aria-live="polite"></p>';
		if (!d.manual) { h += '<p class="ds-lm-note">This loop sorts by ' + esc(this.val('order_by') || 'date') + ', so dragging changes the list but not the page. <button type="button" class="ds-lm-link" data-act="manual">Sort the loop by this list</button></p>'; }
		h += '<ul class="ds-lm-list">' + keys.map(function (k) { return '<li class="ds-lm-row" data-k="' + esc(k) + '"></li>'; }).join('') + '</ul>';
		if (!keys.length) { h += '<p class="ds-lm-msg">No entries yet.</p>'; }
		if (this.ch.trash.length) {
			h += '<div class="ds-lm-trash"><p>Moving to the Trash when you publish:</p><ul>' + this.ch.trash.map(function (id) {
				var b = self.base[String(id)]; return '<li>' + esc(b ? b.title : '#' + id) + ' <button type="button" class="ds-lm-link" data-act="restore" data-id="' + esc(parseInt(id, 10) || 0) + '">Restore</button></li>';
			}).join('') + '</ul></div>';
		}
		this.$el.html(h);
		keys.forEach(function (k) { self.renderRow(k); });
		this.save(true);
	};

	/** Refresh the row's bar (name, photo, flags) and action buttons without touching the inputs being typed in. */
	Manager.prototype.renderBar = function (k) {
		var $li = this.row(k);
		if (!$li.length) { return; }
		var e = this.entry(k);
		$li.children('.ds-lm-bar').replaceWith(this.barHtml(k, e));
		$li.toggleClass('is-hidden', e.status !== 'publish');
		$li.find('.ds-lm-actions').replaceWith(this.actionsHtml(k, e));
	};

	Manager.prototype.barHtml = function (k, e) {
		var c = this.ch.items[k] || {}, open = this.openKey === k, isNew = !!c._new;
		var flags = (e.status !== 'publish' ? '<span class="ds-lm-flag">Hidden</span>' : '') + (isNew ? '<span class="ds-lm-flag ds-lm-flag--new">New</span>' : (Object.keys(c).length ? '<span class="ds-lm-flag ds-lm-flag--edit">Edited</span>' : ''));
		return '<div class="ds-lm-bar" draggable="true"><span class="ds-lm-grip" title="Drag to reorder" aria-hidden="true">&#8942;&#8942;</span>'
			+ '<span class="ds-lm-thumb">' + (e.thumb && e.thumb.url ? '<img src="' + esc(e.thumb.url) + '" alt="">' : '') + '</span>'
			+ '<span class="ds-lm-name">' + (esc(e.title) || '<em>Untitled</em>') + '<small>' + esc(this.sub(e)) + '</small></span>' + flags
			+ '<button type="button" class="ds-lm-btn" data-act="toggle" aria-expanded="' + open + '">' + (open ? 'Close' : 'Edit') + '</button></div>';
	};

	Manager.prototype.actionsHtml = function (k, e) {
		var c = this.ch.items[k] || {};
		return '<div class="ds-lm-actions"><button type="button" class="ds-lm-btn" data-act="up">Move up</button><button type="button" class="ds-lm-btn" data-act="down">Move down</button>'
			+ (!c._new && Object.keys(c).length ? '<button type="button" class="ds-lm-btn" data-act="revert">Undo changes</button>' : '')
			+ (e.edit ? '<a class="ds-lm-link" href="' + esc(e.edit) + '" target="_blank" rel="noopener">Open in dashboard</a>' : '')
			+ '<button type="button" class="ds-lm-btn ds-lm-btn--danger" data-act="remove">' + (c._new ? 'Discard' : 'Remove') + '</button></div>';
	};

	Manager.prototype.renderRow = function (k) {
		var $li = this.row(k);
		if (!$li.length) { return; }
		var e = this.entry(k), c = this.ch.items[k] || {}, open = this.openKey === k, isNew = !!c._new;
		$li.toggleClass('is-open', open).toggleClass('is-hidden', e.status !== 'publish').html(this.barHtml(k, e) + (open ? this.formHtml(k, e) : ''));
		if (open) { this.mountRte($li, k, e); }
	};

	Manager.prototype.formHtml = function (k, e) {
		var s = this.data.schema, h = '<div class="ds-lm-form">', self = this, c = this.ch.items[k] || {};
		h += '<label class="ds-lm-field"><span>Name</span><input type="text" data-f="title" value="' + esc(e.title) + '"></label>';
		if (s.thumbnail) { h += this.imageHtml('thumb', 'Photo', e.thumb); }
		if (s.editor) { h += '<div class="ds-lm-field"><span>Bio</span><div class="ds-lm-rte" data-rte="content"></div></div>'; }
		(s.fields || []).forEach(function (f) {
			var v = e.fields[f.name], id = 'f:' + f.name;
			if (f.type === 'unsupported') { h += '<p class="ds-lm-field ds-lm-na"><span>' + esc(f.label) + '</span>Edit this in the dashboard.</p>'; return; }
			if (f.type === 'image') { h += self.imageHtml(id, f.label, v || { id: 0, url: '' }); return; }
			if (f.type === 'wysiwyg') { h += '<div class="ds-lm-field"><span>' + esc(f.label) + '</span><div class="ds-lm-rte" data-rte="' + esc(id) + '"></div></div>'; return; }
			if (f.type === 'true_false') { h += '<label class="ds-lm-check"><input type="checkbox" data-f="' + esc(id) + '"' + (+v ? ' checked' : '') + '> ' + esc(f.label) + '</label>'; return; }
			var input;
			if (f.type === 'textarea') { input = '<textarea rows="3" data-f="' + esc(id) + '">' + esc(v) + '</textarea>'; }
			else if (f.type === 'select' || f.type === 'radio') {
				input = '<select data-f="' + esc(id) + '"><option value="">—</option>' + Object.keys(f.choices || {}).map(function (o) { return '<option value="' + esc(o) + '"' + (String(v) === o ? ' selected' : '') + '>' + esc(f.choices[o]) + '</option>'; }).join('') + '</select>';
			} else {
				var t = { email: 'email', url: 'url', oembed: 'url', number: 'number' }[f.type] || 'text';
				input = '<input type="' + t + '" data-f="' + esc(id) + '" value="' + esc(v) + '"' + (f.type === 'url' || f.type === 'oembed' ? ' placeholder="https://"' : '') + '>';
			}
			h += '<label class="ds-lm-field"><span>' + esc(f.label) + '</span>' + input + (f.help ? '<small>' + esc(f.help) + '</small>' : '') + '</label>';
		});
		(s.tax || []).forEach(function (t) {
			if (!t.terms.length) { return; }
			var on = (e.terms[t.name] || []).map(Number);
			h += '<fieldset class="ds-lm-field ds-lm-terms"><legend>' + esc(t.label) + '</legend>' + t.terms.map(function (x) { return '<label class="ds-lm-check"><input type="checkbox" data-tax="' + esc(t.name) + '" value="' + x.id + '"' + (on.indexOf(x.id) !== -1 ? ' checked' : '') + '> ' + esc(x.name) + '</label>'; }).join('') + '</fieldset>';
		});
		h += '<label class="ds-lm-check ds-lm-show"><input type="checkbox" data-f="status"' + (e.status === 'publish' ? ' checked' : '') + '> Show on the site</label>';
		return h + this.actionsHtml(k, e) + '</div>';
	};

	Manager.prototype.imageHtml = function (id, label, v) {
		v = v || { id: 0, url: '' };
		return '<div class="ds-lm-field ds-lm-image" data-img="' + esc(id) + '"><span>' + esc(label) + '</span><div class="ds-lm-image-row"><span class="ds-lm-thumb ds-lm-thumb--lg">' + (v.url ? '<img src="' + esc(v.url) + '" alt="">' : '') + '</span>'
			+ '<button type="button" class="ds-lm-btn" data-act="pick">' + (v.id ? 'Change' : 'Choose') + '</button>' + (v.id ? '<button type="button" class="ds-lm-link" data-act="unpick">Remove</button>' : '') + '</div></div>';
	};

	/** Simple rich-text box: bold, italic, link, list. Pastes as plain text; the server allows only those tags. */
	Manager.prototype.mountRte = function ($li, k, e) {
		var self = this;
		$li.find('[data-rte]').each(function () {
			var key = this.getAttribute('data-rte'), html = key === 'content' ? e.content : (e.fields[key.slice(2)] || '');
			var clean = simpleHtml(html);
			if (clean === null) {
				// Tables, headings, images: the simple box would flatten them. Leave the text alone.
				$(this).html('<p class="ds-lm-na">This text has formatting (a table, headings or images) that this box cannot keep. ' + (e.edit ? '<a class="ds-lm-link" href="' + esc(e.edit) + '" target="_blank" rel="noopener">Edit it in the dashboard</a>.' : 'Edit it in the dashboard.') + '</p>');
				return;
			}
			$(this).html('<div class="ds-lm-rte-bar" role="toolbar"><button type="button" data-cmd="bold" title="Bold"><b>B</b></button><button type="button" data-cmd="italic" title="Italic"><i>I</i></button><button type="button" data-cmd="createLink" title="Link">Link</button><button type="button" data-cmd="insertUnorderedList" title="Bulleted list">&bull; List</button></div><div class="ds-lm-rte-body" contenteditable="true" role="textbox" aria-multiline="true" data-f="' + esc(key) + '"></div>');
			var body = $(this).find('.ds-lm-rte-body');
			body.html(clean);
			var push = function () { self.setKey(k, key, body.html()); };
			body.on('focus', function () { try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (x) {} });
			body.on('input', function () { clearTimeout(self.rt); self.rt = setTimeout(push, 250); });
			body.on('paste', function (ev) { ev.preventDefault(); var t = (ev.originalEvent.clipboardData || window.clipboardData).getData('text/plain'); document.execCommand('insertText', false, t); });
			$(this).find('[data-cmd]').on('mousedown', function (ev) { ev.preventDefault(); }).on('click', function () {
				var cmd = this.getAttribute('data-cmd'), arg = null;
				body.trigger('focus');
				if (cmd === 'createLink') { arg = window.prompt('Link address (https://…)', 'https://'); if (!arg || !/^(https?:\/\/|mailto:|tel:|\/)/i.test(arg)) { return; } }
				document.execCommand(cmd, false, arg); push();
			});
		});
	};

	/** Route a form control's key ("title", "status", "content", "f:<acf>") to set(). */
	Manager.prototype.setKey = function (k, key, value) {
		if (key.indexOf('f:') === 0) { this.set(k, ['fields', key.slice(2)], value); } else { this.set(k, [key], value); }
	};

	/* ------------------------------------------------------------- events */

	Manager.prototype.move = function (k, to) {
		var keys = this.keys(), from = keys.indexOf(k);
		if (from === -1 || to < 0 || to >= keys.length || to === from) { return; }
		keys.splice(to, 0, keys.splice(from, 1)[0]);
		this.ch.order = keys;
		this.render();
		this.row(k).find('.ds-lm-bar [data-act="toggle"]').trigger('focus');
	};

	Manager.prototype.bind = function () {
		var self = this, $el = this.$el;
		$el.on('click', '[data-act]', function (ev) {
			var act = this.getAttribute('data-act'), $row = $(this).closest('.ds-lm-row'), k = $row.attr('data-k');
			ev.preventDefault();
			if (act === 'toggle') { self.openKey = self.openKey === k ? null : k; self.render(); }
			else if (act === 'add') {
				var n = 0; Object.keys(self.ch.items).forEach(function (x) { var m = /^n(\d+)$/.exec(x); if (m) { n = Math.max(n, +m[1]); } });
				k = 'n' + (n + 1);
				self.ch.items[k] = { _new: 1, title: '', status: 'publish', terms: $.extend(true, {}, self.ch.defaults || {}) };
				if (self.ch.order) { self.ch.order.push(k); }
				self.openKey = k; self.render();
				self.row(k).find('[data-f="title"]').trigger('focus');
			}
			else if (act === 'remove') {
				var c = self.ch.items[k];
				if (c && c._new) {
					delete self.ch.items[k];
					if (self.ch.order) { self.ch.order = self.ch.order.filter(function (x) { return x !== k; }); }
				} else {
					// Kept in the order (keys() skips trashed entries), so Restore puts it back in its place.
					delete self.ch.items[k]; self.ch.trash.push(+k);
				}
				self.openKey = null; self.render();
			}
			else if (act === 'restore') {
				var id = parseInt(this.getAttribute('data-id'), 10);
				self.ch.trash = self.ch.trash.filter(function (x) { return x !== id; });
				if (self.ch.order && self.ch.order.indexOf(String(id)) === -1) { self.ch.order.push(String(id)); }
				self.render();
			}
			else if (act === 'revert') { delete self.ch.items[k]; self.render(); }
			else if (act === 'up' || act === 'down') { self.move(k, self.keys().indexOf(k) + (act === 'up' ? -1 : 1)); }
			else if (act === 'manual') { self.form.find('[name="order_by"]').val('menu_order').trigger('change'); self.load(); }
			else if (act === 'pick' || act === 'unpick') {
				var key = $(this).closest('[data-img]').attr('data-img');
				var apply = function (v) { if (key === 'thumb') { self.set(k, ['thumb'], v); } else { self.set(k, ['fields', key.slice(2)], v); } self.renderRow(k); };
				if (act === 'unpick') { apply({ id: 0, url: '' }); } else { mediaFrame('Choose an image', apply); }
			}
		});
		$el.on('input change', '.ds-lm-form [data-f]:not([contenteditable])', function (ev) {
			var key = this.getAttribute('data-f'), k = $(this).closest('.ds-lm-row').attr('data-k');
			if (this.type === 'checkbox') { if (ev.type !== 'change') { return; } self.setKey(k, key, key === 'status' ? (this.checked ? 'publish' : 'draft') : (this.checked ? 1 : 0)); return; }
			var v = this.value, tk = k + '|' + key;
			self.its = self.its || {};
			clearTimeout(self.its[tk]); self.its[tk] = setTimeout(function () { self.setKey(k, key, v); }, ev.type === 'change' ? 0 : 300);
		});
		$el.on('change', '.ds-lm-form [data-tax]', function () {
			var k = $(this).closest('.ds-lm-row').attr('data-k'), tax = this.getAttribute('data-tax');
			var ids = $(this).closest('.ds-lm-terms').find('input:checked').filter(function () { return this.getAttribute('data-tax') === tax; }).map(function () { return +this.value; }).get();
			self.set(k, ['terms', tax], ids);
		});
		// Drag to reorder (the bar is the handle).
		$el.on('dragstart', '.ds-lm-bar', function (ev) { self.drag = $(this).closest('.ds-lm-row').attr('data-k'); ev.originalEvent.dataTransfer.effectAllowed = 'move'; ev.originalEvent.dataTransfer.setData('text/plain', self.drag); $(this).closest('.ds-lm-row').addClass('is-dragging'); });
		$el.on('dragover', '.ds-lm-row', function (ev) {
			if (!self.drag) { return; } ev.preventDefault();
			var r = this.getBoundingClientRect(), after = ev.originalEvent.clientY > r.top + r.height / 2;
			$el.find('.ds-lm-row').removeClass('drop-before drop-after'); $(this).addClass(after ? 'drop-after' : 'drop-before');
		});
		$el.on('drop', '.ds-lm-row', function (ev) {
			if (!self.drag) { return; } ev.preventDefault();
			var keys = self.keys(), from = keys.indexOf(self.drag), to = keys.indexOf(this.getAttribute('data-k'));
			if ($(this).hasClass('drop-after')) { to++; }
			if (from < to) { to--; }
			var k = self.drag; self.drag = null; self.move(k, to);
		});
		$el.on('dragend', '.ds-lm-bar', function () { self.drag = null; $el.find('.ds-lm-row').removeClass('drop-before drop-after is-dragging'); });
		// Reload when the query that decides WHICH entries changes.
		this.form.on('change.dslm', 'select[name="post_type"], [name="filter_tax"], [name^="flt_"], [name^="as_values_flt_"], [name="filter_terms"], [name="order_by"], [name="order"], [name="source"]', function () { clearTimeout(self.lt); self.lt = setTimeout(function () { self.load(); }, 300); });
	};

	/* ---------------------------------------------------------------- boot */

	function boot() {
		$('.fl-builder-settings:visible').each(function () {
			var form = $(this);
			form.find('[data-ds-loop-manager]').each(function () { if (!this.dsLm) { this.dsLm = new Manager(form, this); } });
		});
	}
	// FLBuilder is defined after this script runs; wait for it before registering the hook.
	function hook() {
		if (!window.FLBuilder || typeof window.FLBuilder.addHook !== 'function') { setTimeout(hook, 150); return; }
		window.FLBuilder.addHook('settings-form-init', function () { [0, 120, 400].forEach(function (t) { setTimeout(boot, t); }); });
	}
	hook();
})(jQuery);
