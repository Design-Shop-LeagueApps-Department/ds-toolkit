/**
 * DS Toolkit — Theme Setting page (features/class-ds-theme-setting.php).
 *
 * Plain JS (jQuery only for wp.media). Pieces:
 *   Palette   the global colours in the Colors section (names -> CSS variables)
 *   Color     colour fields: one shared popover + one Pickr instance for the page
 *   Fonts     the font combobox; the catalog loads after the page has rendered
 *   State     unsaved-change tracking (page, rail sections, heading levels)
 *   Preview   live preview iframe fed with CSS generated from the unsaved form
 *   Save      AJAX save to the same admin-post handler (plain POST still works)
 */
/* global dsTs, Pickr, wp, jQuery */
(function () {
	'use strict';

	var cfg = window.dsTs || {};
	var root = document.getElementById('dsts');
	var form = document.getElementById('dsts-form');
	if (!root || !form) { return; }

	var $ = function (sel, el) { return (el || document).querySelector(sel); };
	var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };
	var debounce = function (fn, ms) { var t; return function () { var a = arguments, s = this; clearTimeout(t); t = setTimeout(function () { fn.apply(s, a); }, ms); }; };
	var store = {
		get: function (k, d) { try { var v = localStorage.getItem('dsts:' + k); return v === null ? d : v; } catch (e) { return d; } },
		set: function (k, v) { try { localStorage.setItem('dsts:' + k, v); } catch (e) {} }
	};
	/** Tell the page a value changed (dirty tracking + preview), the same way a user edit would. */
	var changed = function (el) { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); };

	/* ================================================================ Palette */

	var Palette = {
		prefix: cfg.prefixKey || 'fl-global',
		wrap: $('#dsts-pal'),
		/** Beaver Builder's label_to_key(). */
		slug: function (label) {
			return String(label || '').trim().toLowerCase().replace(/[_ ]/g, '-').replace(/[^A-Za-z0-9-]/g, '');
		},
		prefixKey: function (p) { var k = Palette.slug(p); return k || 'fl-global'; },
		varFor: function (name) { var s = Palette.slug(name); return s ? 'var(--' + Palette.prefix + '-' + s + ')' : ''; },
		rows: function () { return Palette.wrap ? $$('.dsts-pal-row', Palette.wrap) : []; },
		/** Current palette as [{row, name, value, varref}] (reads the form, so unsaved edits count). */
		list: function () {
			return Palette.rows().map(function (row) {
				var name = $('.dsts-pal-name', row).value.trim();
				return { row: row, name: name, value: $('.dsts-color-input', row).value.trim(), varref: Palette.varFor(name) };
			});
		},
		valid: function () { return Palette.list().filter(function (g) { return g.name && g.value && g.varref; }); },
		find: function (varref) {
			var hit = null;
			Palette.valid().forEach(function (g) { if (!hit && g.varref === varref) { hit = g; } });
			return hit;
		},
		/** Every colour field outside the palette (the ones that can link to a global). */
		consumers: function () { return $$('.dsts-color-input').filter(function (i) { return !i.closest('.dsts-pal'); }); },
		refs: function (varref) { return Palette.consumers().filter(function (i) { return i.value.trim() === varref; }); },
		renderStrip: function () {
			var strip = $('#dsts-pal-strip'); if (!strip) { return; }
			strip.innerHTML = '';
			Palette.valid().forEach(function (g) { var i = document.createElement('i'); i.style.background = Color.css(g.value); i.title = g.name; strip.appendChild(i); });
		},
		renderVar: function (row) {
			var code = $('.dsts-pal-var', row); var name = $('.dsts-pal-name', row).value.trim();
			var v = Palette.slug(name); var orig = row.getAttribute('data-orig-name') || '';
			code.textContent = v ? '--' + Palette.prefix + '-' + v : ' ';
			var renamed = orig && Palette.slug(orig) !== v;
			code.classList.toggle('is-warn', !!renamed);
			code.title = renamed ? 'Renamed from “' + orig + '”. Settings on this page follow the rename; modules that reference var(--' + Palette.prefix + '-' + Palette.slug(orig) + ') by name will not.' : '';
		},
		/** Refresh everything that shows a palette colour. */
		refresh: function () { Palette.renderStrip(); Color.renderAll(); Popover.refreshGlobals(); },
		init: function () {
			if (!Palette.wrap) { return; }
			Palette.rows().forEach(function (row) { row.setAttribute('data-var', Palette.varFor($('.dsts-pal-name', row).value)); });
			Palette.renderStrip();

			// Rename: fields that linked to the old variable follow it to the new name.
			Palette.wrap.addEventListener('input', function (e) {
				if (!e.target.classList.contains('dsts-pal-name')) { return; }
				var row = e.target.closest('.dsts-pal-row');
				var before = row.getAttribute('data-var') || ''; var after = Palette.varFor(e.target.value);
				if (before && after && before !== after) {
					Palette.refs(before).forEach(function (i) { i.value = after; });
				}
				row.setAttribute('data-var', after);
				row.classList.remove('is-invalid');
				Palette.renderVar(row); Palette.refresh();
			});
			// A palette colour changed: every linked field repaints (and a flagged row may now be valid).
			Palette.wrap.addEventListener('dsts:color', function (e) {
				var row = e.target.closest && e.target.closest('.dsts-pal-row');
				if (row && $('.dsts-pal-name', row).value.trim()) { row.classList.remove('is-invalid'); }
				Palette.refresh();
			});

			$('#dsts-pal-add').addEventListener('click', function () {
				var tpl = $('#dsts-pal-tpl'); var row = tpl.content.firstElementChild.cloneNode(true);
				Palette.wrap.appendChild(row); Color.render($('.dsts-color', row));
				$('.dsts-pal-name', row).focus(); changed($('.dsts-pal-name', row));
			});

			Palette.wrap.addEventListener('click', function (e) {
				var btn = e.target.closest('.dsts-pal-remove'); if (!btn) { return; }
				var row = btn.closest('.dsts-pal-row'); var name = $('.dsts-pal-name', row).value.trim();
				var varref = Palette.varFor(name); var used = varref ? Palette.refs(varref).length : 0;
				var msg = 'Remove “' + (name || 'this colour') + '” from the palette?';
				if (used) { msg += '\n\n' + used + ' setting' + (used === 1 ? '' : 's') + ' on this page use' + (used === 1 ? 's' : '') + ' it and will fall back to their default until you pick another colour.'; }
				if (row.getAttribute('data-orig-name')) { msg += '\n\nBeaver Builder modules connected to it will lose the colour too.'; }
				if (!window.confirm(msg)) { return; }
				row.remove(); Palette.refresh(); changed(Palette.wrap);
			});

			// Prefix: rename every variable reference on the page.
			var pre = $('.dsts-prefix');
			if (pre) {
				pre.addEventListener('input', function () {
					var next = Palette.prefixKey(pre.value); if (next === Palette.prefix) { return; }
					var from = 'var(--' + Palette.prefix + '-'; var to = 'var(--' + next + '-';
					Palette.consumers().forEach(function (i) { if (i.value.indexOf(from) === 0) { i.value = to + i.value.slice(from.length); } });
					Palette.prefix = next;
					Palette.rows().forEach(function (row) { row.setAttribute('data-var', Palette.varFor($('.dsts-pal-name', row).value)); Palette.renderVar(row); });
					Palette.refresh();
				});
			}

			// Drag to reorder (the handle starts the drag; the order is the palette order in BB).
			var dragging = null;
			Palette.wrap.addEventListener('pointerdown', function (e) {
				var h = e.target.closest('.dsts-pal-handle'); if (!h) { return; }
				dragging = h.closest('.dsts-pal-row'); dragging.classList.add('is-dragging'); e.preventDefault();
				h.setPointerCapture && h.setPointerCapture(e.pointerId);
			});
			Palette.wrap.addEventListener('pointermove', function (e) {
				if (!dragging) { return; }
				var over = document.elementFromPoint(e.clientX, e.clientY); var row = over && over.closest('.dsts-pal-row');
				if (!row || row === dragging || row.parentNode !== Palette.wrap) { return; }
				var r = row.getBoundingClientRect();
				Palette.wrap.insertBefore(dragging, e.clientY < r.top + r.height / 2 ? row : row.nextSibling);
			});
			var drop = function () { if (!dragging) { return; } dragging.classList.remove('is-dragging'); dragging = null; Palette.renderStrip(); Popover.refreshGlobals(); changed(Palette.wrap); };
			Palette.wrap.addEventListener('pointerup', drop); Palette.wrap.addEventListener('pointercancel', drop);
		}
	};

	/* ================================================================== Color */

	var Color = {
		/** A stored value as a CSS colour (var() resolved through the palette, bare hex fixed). */
		css: function (v, depth) {
			v = String(v || '').trim(); if (!v) { return ''; }
			var m = /^var\(\s*(--[A-Za-z0-9_-]+)\s*\)$/.exec(v);
			if (m) { if ((depth || 0) > 3) { return ''; } var g = Palette.find('var(' + m[1] + ')'); return g ? Color.css(g.value, (depth || 0) + 1) : ''; }
			if (/^[0-9a-f]{3,8}$/i.test(v)) { return '#' + v.toLowerCase(); }
			return v;
		},
		isVar: function (v) { return /^var\(\s*--[A-Za-z0-9_-]+\s*\)$/.test(String(v || '').trim()); },
		/** Parse user input: hex (with or without #), rgb()/rgba(), var(--...), or a palette colour name. '' = default. */
		parse: function (txt, allowGlobals) {
			var v = String(txt || '').trim(); if (!v) { return ''; }
			if (/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(v)) { return '#' + v.replace(/^#/, '').toLowerCase(); }
			if (/^rgba?\(\s*[\d.,\s%]+\)$/i.test(v)) { return v.replace(/\s+/g, ' '); }
			if (allowGlobals) {
				if (Color.isVar(v)) { return v.replace(/\s+/g, ''); }
				var hit = null; Palette.valid().forEach(function (g) { if (!hit && g.name.toLowerCase() === v.toLowerCase()) { hit = g.varref; } });
				if (hit) { return hit; }
			}
			return null;
		},
		render: function (wrap) {
			if (!wrap) { return; }
			var input = $('.dsts-color-input', wrap); var v = input.value.trim();
			var fill = $('.dsts-color-fill', wrap); var text = $('.dsts-color-text', wrap); var btn = $('.dsts-color-btn', wrap);
			var tag = $('.dsts-color-tag', wrap); var state; var label;
			if (!v) { state = 'default'; label = 'Default'; }
			else if (Color.isVar(v)) { var g = Palette.find(v.replace(/\s+/g, '')); state = g ? 'global' : 'missing'; label = g ? g.name : v + ' (missing)'; }
			else { state = 'custom'; label = Color.css(v); }
			fill.style.background = Color.css(v);
			text.textContent = label;
			wrap.className = wrap.className.replace(/\bis-(default|global|custom|missing)\b/g, '').trim() + ' is-' + state;
			if (state === 'global') { if (!tag) { tag = document.createElement('span'); tag.className = 'dsts-color-tag'; tag.textContent = 'Global'; btn.appendChild(tag); } }
			else if (tag) { tag.remove(); }
			btn.setAttribute('aria-label', 'Colour: ' + label + '. Change colour');
		},
		renderAll: function () { $$('.dsts-color').forEach(Color.render); },
		/** Set a field's value and let everything downstream know. */
		set: function (wrap, v) {
			var input = $('.dsts-color-input', wrap);
			if (input.value === v) { return; }
			input.value = v; Color.render(wrap);
			wrap.dispatchEvent(new CustomEvent('dsts:color', { bubbles: true, detail: { value: v } }));
			changed(input);
		},
		init: function () {
			Color.renderAll();
			document.addEventListener('click', function (e) {
				var btn = e.target.closest('.dsts-color-btn'); if (!btn || !root.contains(btn)) { return; }
				e.preventDefault(); var wrap = btn.closest('.dsts-color');
				if (Popover.active === wrap) { Popover.close(); } else { Popover.open(wrap); }
			});
		}
	};

	/* ======================================================= Colour popover */

	var Popover = {
		el: null, pickr: null, active: null, input: null, grid: null, globalsBox: null, suppress: false,
		build: function () {
			var el = document.createElement('div');
			el.className = 'dsts-pop'; el.hidden = true; el.setAttribute('role', 'dialog'); el.setAttribute('aria-label', 'Choose colour');
			el.innerHTML = '<div class="dsts-pop-head"><input type="text" class="dsts-pop-input" spellcheck="false" aria-label="Colour value" placeholder="#hex, rgba() or global"><button type="button" class="dsts-pop-default" title="Use the theme default (no colour)">Default</button></div>' +
				'<div class="dsts-pop-picker"><div class="dsts-pop-anchor"></div></div>' +
				'<div class="dsts-pop-globals"><div class="dsts-pop-globals-h">Global colours</div><div class="dsts-pop-grid"></div></div>';
			document.body.appendChild(el);
			Popover.el = el; Popover.input = $('.dsts-pop-input', el); Popover.grid = $('.dsts-pop-grid', el); Popover.globalsBox = $('.dsts-pop-globals', el);

			if (typeof Pickr !== 'undefined') {
				Popover.pickr = Pickr.create({
					el: $('.dsts-pop-anchor', el), theme: 'nano', inline: true, showAlways: true, default: '#ffffff',
					defaultRepresentation: 'HEXA', autoReposition: false, closeWithKey: false,
					components: { preview: false, opacity: true, hue: true, interaction: { hex: false, rgba: false, input: false, clear: false, save: false } }
				});
				Popover.pickr.on('change', function (color) {
					if (Popover.suppress || !Popover.active || !color) { return; }
					var v = color.toHEXA().toString().toLowerCase();
					if (v.length === 9 && v.slice(7) === 'ff') { v = v.slice(0, 7); } // opaque -> #rrggbb
					Popover.input.value = v; Popover.input.classList.remove('is-invalid');
					Color.set(Popover.active, v); Popover.markActive();
				});
			}

			Popover.input.addEventListener('input', function () {
				if (!Popover.active) { return; }
				var v = Color.parse(Popover.input.value, Popover.active.getAttribute('data-globals') === '1');
				Popover.input.classList.toggle('is-invalid', v === null && Popover.input.value.trim() !== '');
				if (v === null) { return; }
				Color.set(Popover.active, v); Popover.syncPicker(v); Popover.markActive();
			});
			Popover.input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); Popover.close(true); } });
			$('.dsts-pop-default', el).addEventListener('click', function () {
				if (!Popover.active) { return; }
				Color.set(Popover.active, ''); Popover.input.value = ''; Popover.markActive(); Popover.close(true);
			});
			Popover.grid.addEventListener('click', function (e) {
				var b = e.target.closest('.dsts-pop-g'); if (!b || !Popover.active) { return; }
				var v = b.getAttribute('data-var');
				Color.set(Popover.active, v); Popover.input.value = v; Popover.syncPicker(v); Popover.markActive(); Popover.close(true);
			});

			document.addEventListener('mousedown', function (e) {
				if (!Popover.active || Popover.el.contains(e.target) || Popover.active.contains(e.target)) { return; }
				Popover.close();
			}, true);
			document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && Popover.active) { e.preventDefault(); Popover.close(true); } });
			window.addEventListener('resize', function () { Popover.place(); });
			var ctl = $('#dsts-controls'); if (ctl) { ctl.addEventListener('scroll', function () { Popover.place(); }, { passive: true }); }
		},
		syncPicker: function (v) {
			if (!Popover.pickr) { return; }
			var c = Color.css(v) || '#ffffff';
			Popover.suppress = true;
			try { Popover.pickr.setColor(c, true); } catch (err) {}
			setTimeout(function () { Popover.suppress = false; }, 0);
		},
		refreshGlobals: function () {
			if (!Popover.el || !Popover.active) { return; }
			var show = Popover.active.getAttribute('data-globals') === '1';
			Popover.globalsBox.hidden = !show; if (!show) { return; }
			var list = Palette.valid(); Popover.grid.innerHTML = '';
			if (!list.length) { Popover.grid.innerHTML = '<p class="dsts-pop-empty">No global colours yet. Add them in Colors.</p>'; return; }
			list.forEach(function (g) {
				var b = document.createElement('button'); b.type = 'button'; b.className = 'dsts-pop-g'; b.setAttribute('data-var', g.varref); b.title = g.name + ' — ' + g.varref;
				var i = document.createElement('i'); i.style.background = Color.css(g.value);
				var s = document.createElement('span'); s.textContent = g.name;
				b.appendChild(i); b.appendChild(s); Popover.grid.appendChild(b);
			});
			Popover.markActive();
		},
		markActive: function () {
			if (!Popover.active) { return; }
			var v = $('.dsts-color-input', Popover.active).value.trim();
			$$('.dsts-pop-g', Popover.grid).forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-var') === v); });
			$('.dsts-pop-default', Popover.el).classList.toggle('is-active', v === '');
		},
		open: function (wrap) {
			if (!Popover.el) { Popover.build(); }
			if (Popover.active) { Popover.active.classList.remove('is-open'); }
			Popover.active = wrap; wrap.classList.add('is-open');
			var v = $('.dsts-color-input', wrap).value.trim();
			Popover.input.value = v; Popover.input.classList.remove('is-invalid');
			Popover.refreshGlobals(); Popover.syncPicker(v);
			Popover.el.hidden = false; Popover.place();
		},
		close: function (refocus) {
			if (!Popover.active) { return; }
			var wrap = Popover.active; wrap.classList.remove('is-open'); Popover.active = null; Popover.el.hidden = true;
			if (refocus) { var b = $('.dsts-color-btn', wrap); if (b) { b.focus(); } }
		},
		place: function () {
			if (!Popover.active || !Popover.el || Popover.el.hidden) { return; }
			var r = $('.dsts-color-btn', Popover.active).getBoundingClientRect();
			var pw = Popover.el.offsetWidth, ph = Popover.el.offsetHeight, vw = window.innerWidth, vh = window.innerHeight;
			if (r.bottom < 40 || r.top > vh - 10) { Popover.close(); return; } // scrolled out of view
			var top = r.bottom + 6; if (top + ph > vh - 8 && r.top - ph - 6 > 40) { top = r.top - ph - 6; }
			var left = Math.min(Math.max(8, r.left), vw - pw - 8);
			Popover.el.style.top = Math.max(40, top) + 'px'; Popover.el.style.left = left + 'px';
		}
	};

	/* ================================================================== Fonts */

	var Fonts = {
		data: null, promise: null, list: null, active: null, items: [], hl: -1, before: '',
		load: function () {
			if (Fonts.promise) { return Fonts.promise; }
			var cached = null; try { cached = JSON.parse(sessionStorage.getItem('dsts:fonts') || 'null'); } catch (e) {}
			if (cached && cached.google) { Fonts.data = cached; Fonts.promise = Promise.resolve(cached); Fonts.applyWeights(); return Fonts.promise; }
			var body = new FormData(); body.append('action', cfg.fontsAction); body.append('nonce', cfg.nonce);
			Fonts.promise = fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					Fonts.data = (j && j.success) ? j.data : { system: {}, google: {} };
					try { sessionStorage.setItem('dsts:fonts', JSON.stringify(Fonts.data)); } catch (e) {}
					Fonts.applyWeights(); return Fonts.data;
				})
				.catch(function () { Fonts.data = { system: {}, google: {} }; return Fonts.data; });
			return Fonts.promise;
		},
		known: function (name) {
			if (!name || name === 'Default') { return true; }
			if (!Fonts.data) { return true; } // not loaded yet: never reject
			return Object.prototype.hasOwnProperty.call(Fonts.data.system, name) || Object.prototype.hasOwnProperty.call(Fonts.data.google, name);
		},
		weightsOf: function (name) {
			if (!Fonts.data || !name || name === 'Default') { return null; }
			var w = Fonts.data.google[name] || Fonts.data.system[name];
			if (!w) { return null; }
			var out = String(w).split(',').filter(function (x) { return /^\d00$/.test(x); });
			return out.length ? out : null;
		},
		/** Limit each Weight dropdown to what its font actually ships (the stored value always stays listed). */
		applyWeights: function () {
			$$('.dsts-typo').forEach(function (block) {
				var fam = $('.dsts-font-input', block); var sel = $('.dsts-weight', block);
				if (!fam || !sel) { return; }
				var ok = Fonts.weightsOf(fam.value.trim());
				$$('option', sel).forEach(function (o) {
					o.hidden = !!(ok && o.value !== '' && ok.indexOf(o.value) === -1 && o.value !== sel.value);
				});
			});
		},
		ensureList: function () {
			if (Fonts.list) { return; }
			Fonts.list = document.createElement('div'); Fonts.list.className = 'dsts-list'; Fonts.list.hidden = true; Fonts.list.setAttribute('role', 'listbox');
			document.body.appendChild(Fonts.list);
			Fonts.list.addEventListener('mousedown', function (e) { e.preventDefault(); });
			Fonts.list.addEventListener('click', function (e) { var i = e.target.closest('.dsts-list-i'); if (i) { Fonts.pick(i.getAttribute('data-name')); } });
		},
		render: function (q) {
			var inp = Fonts.active; if (!inp) { return; }
			q = (q || '').trim().toLowerCase(); var cur = inp.value.trim();
			var html = ''; Fonts.items = []; var cap = 120; var shown = 0; var total = 0;
			var add = function (name) { Fonts.items.push(name); var idx = Fonts.items.length - 1; html += '<div class="dsts-list-i' + (name === cur ? ' is-cur' : '') + '" role="option" data-i="' + idx + '" data-name="' + name.replace(/"/g, '&quot;') + '">' + name.replace(/</g, '&lt;') + '</div>'; };
			if (!q || 'default'.indexOf(q) !== -1) { add('Default'); }
			if (!q && cur && cur !== 'Default') { html += '<div class="dsts-list-h">Current</div>'; add(cur); }
			var groups = [['Custom & system', Fonts.data ? Object.keys(Fonts.data.system) : []], ['Google', Fonts.data ? Object.keys(Fonts.data.google) : []]];
			groups.forEach(function (g) {
				var names = g[1].filter(function (n) { return !q || n.toLowerCase().indexOf(q) !== -1; });
				total += names.length; if (!names.length) { return; }
				html += '<div class="dsts-list-h">' + g[0] + '</div>';
				names.slice(0, Math.max(0, cap - shown)).forEach(function (n) { if (!q && n === cur) { return; } add(n); shown++; });
			});
			if (total > shown) { html += '<div class="dsts-list-more">' + (total - shown) + ' more — keep typing to narrow down</div>'; }
			if (!Fonts.data) { html += '<div class="dsts-list-more">Loading fonts…</div>'; }
			if (Fonts.items.length === 0 && Fonts.data) { html += '<div class="dsts-list-more">No font matches “' + q.replace(/</g, '&lt;') + '”</div>'; }
			Fonts.list.innerHTML = html;
			Fonts.hl = Math.max(0, Fonts.items.indexOf(cur)); Fonts.highlight(false);
		},
		highlight: function (scroll) {
			$$('.dsts-list-i', Fonts.list).forEach(function (el) { el.classList.toggle('is-hl', +el.getAttribute('data-i') === Fonts.hl); });
			var hl = $('.dsts-list-i.is-hl', Fonts.list); if (hl && scroll !== false) { hl.scrollIntoView({ block: 'nearest' }); }
		},
		place: function () {
			if (!Fonts.active || Fonts.list.hidden) { return; }
			var r = Fonts.active.getBoundingClientRect(); var vh = window.innerHeight;
			Fonts.list.style.left = r.left + 'px'; Fonts.list.style.width = Math.max(r.width, 230) + 'px';
			var below = vh - r.bottom - 12; var h = Math.min(300, Math.max(160, below));
			if (below < 160 && r.top > below) { Fonts.list.style.top = ''; Fonts.list.style.bottom = (vh - r.top + 4) + 'px'; Fonts.list.style.maxHeight = Math.min(300, r.top - 50) + 'px'; }
			else { Fonts.list.style.bottom = ''; Fonts.list.style.top = (r.bottom + 4) + 'px'; Fonts.list.style.maxHeight = h + 'px'; }
		},
		open: function (inp) {
			Fonts.ensureList(); Fonts.active = inp; Fonts.before = inp.value;
			inp.setAttribute('aria-expanded', 'true'); inp.classList.remove('is-default');
			Fonts.list.hidden = false; Fonts.render(''); Fonts.place(); inp.select();
			if (!Fonts.data) { Fonts.load().then(function () { if (Fonts.active === inp) { Fonts.render(inp.value === Fonts.before ? '' : inp.value); } }); }
		},
		close: function (restore) {
			var inp = Fonts.active; if (!inp) { return; }
			if (restore || !Fonts.known(inp.value.trim()) || !inp.value.trim()) { inp.value = Fonts.before || 'Default'; }
			inp.setAttribute('aria-expanded', 'false'); inp.classList.toggle('is-default', inp.value === 'Default');
			Fonts.list.hidden = true; Fonts.active = null;
			if (inp.value !== Fonts.before) { changed(inp); Fonts.applyWeights(); }
		},
		pick: function (name) { if (!Fonts.active) { return; } Fonts.active.value = name; Fonts.close(false); },
		init: function () {
			$$('.dsts-font-input').forEach(function (inp) { inp.classList.toggle('is-default', inp.value === 'Default'); });
			root.addEventListener('focusin', function (e) { if (e.target.classList.contains('dsts-font-input') && Fonts.active !== e.target) { Fonts.open(e.target); } });
			root.addEventListener('mousedown', function (e) { if (e.target.classList.contains('dsts-font-input') && Fonts.active === e.target && Fonts.list.hidden) { Fonts.open(e.target); } });
			root.addEventListener('focusout', function (e) { if (e.target === Fonts.active) { setTimeout(function () { if (Fonts.active === e.target) { Fonts.close(false); } }, 120); } });
			root.addEventListener('input', function (e) {
				if (e.target !== Fonts.active) { return; }
				e.stopPropagation(); // not a real change until a font is picked
				Fonts.render(e.target.value); Fonts.place();
			}, true);
			root.addEventListener('keydown', function (e) {
				if (e.target !== Fonts.active) { return; }
				if (e.key === 'ArrowDown') { e.preventDefault(); Fonts.hl = Math.min(Fonts.items.length - 1, Fonts.hl + 1); Fonts.highlight(); }
				else if (e.key === 'ArrowUp') { e.preventDefault(); Fonts.hl = Math.max(0, Fonts.hl - 1); Fonts.highlight(); }
				else if (e.key === 'Enter') { e.preventDefault(); if (Fonts.items[Fonts.hl]) { Fonts.pick(Fonts.items[Fonts.hl]); } }
				else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); Fonts.close(true); }
				else if (e.key === 'Tab') { Fonts.close(false); }
			});
			window.addEventListener('resize', function () { Fonts.place(); });
			var ctl = $('#dsts-controls'); if (ctl) { ctl.addEventListener('scroll', function () { if (Fonts.active) { Fonts.place(); } }, { passive: true }); }
			// Prefetch once the page is idle so the first open is instant.
			(window.requestIdleCallback || function (f) { return setTimeout(f, 800); })(function () { Fonts.load(); });
		}
	};

	/* ================================================================== State */

	var State = {
		initial: '', sections: {}, dirty: false, saving: false, leaving: false,
		sig: function (scope) {
			var parts = [];
			$$('input[name], select[name], textarea[name]', scope).forEach(function (el) {
				if (el.disabled || el.name === '_wpnonce' || el.name === '_wp_http_referer') { return; }
				if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) { return; }
				parts.push(el.name + '=' + el.value);
			});
			return parts.join('\u0001');
		},
		init: function () {
			State.initial = State.sig(form);
			$$('.dsts-section').forEach(function (s) { State.sections[s.getAttribute('data-section')] = State.sig(s); });
			State.check();
		},
		check: function () {
			var dirty = State.sig(form) !== State.initial;
			$$('.dsts-section').forEach(function (s) {
				var key = s.getAttribute('data-section'); var btn = $('.dsts-rail-btn[data-section="' + key + '"]');
				if (btn) { btn.classList.toggle('is-dirty', State.sig(s) !== State.sections[key]); }
			});
			Levels.mark();
			if (State.saving) { return; }
			State.dirty = dirty;
			// Keep a save error on screen until the rows it points at are fixed.
			if (Status.current === 'error' && $('.dsts-pal-row.is-invalid')) { Status.set('error', $('#dsts-status').textContent); return; }
			if (dirty) { Status.set('dirty'); } else if (Status.current !== 'saved') { Status.set('clean'); }
		}
	};

	var Status = {
		current: 'clean', timer: null,
		set: function (s, msg) {
			Status.current = s; var el = $('#dsts-status'); var save = $('#dsts-save'); var discard = $('#dsts-discard');
			root.classList.toggle('is-dirty', s === 'dirty' || (s === 'error' && State.dirty));
			root.classList.toggle('is-saving', s === 'saving'); root.classList.toggle('is-saved', s === 'saved'); root.classList.toggle('is-error', s === 'error');
			var text = { clean: 'All changes saved', dirty: 'Unsaved changes', saving: 'Saving…', saved: 'Saved', error: msg || 'Could not save' }[s];
			el.textContent = text;
			save.disabled = !(s === 'dirty' || (s === 'error' && State.dirty));
			save.textContent = s === 'saving' ? 'Saving…' : 'Save changes';
			discard.hidden = !(s === 'dirty' || (s === 'error' && State.dirty));
			clearTimeout(Status.timer);
			if (s === 'saved') { Status.timer = setTimeout(function () { if (Status.current === 'saved') { Status.set('clean'); } }, 2600); }
		}
	};

	/* ============================================================ Navigation */

	var Nav = {
		keys: [],
		show: function (key, focus) {
			if (Nav.keys.indexOf(key) === -1) { key = Nav.keys[0]; }
			$$('.dsts-section').forEach(function (s) { s.hidden = s.getAttribute('data-section') !== key; });
			$$('.dsts-rail-btn').forEach(function (b) { b.setAttribute('aria-current', b.getAttribute('data-section') === key ? 'true' : 'false'); });
			store.set('section', key);
			if (window.history && history.replaceState) { history.replaceState(null, '', '#' + key); }
			var ctl = $('#dsts-controls'); if (ctl) { ctl.scrollTop = 0; }
			Popover.close(); if (Fonts.active) { Fonts.close(true); }
			if (key === 'code') { Code.init(); }
			if (Preview.suggestFor) { Preview.suggestFor(key); }
			if (focus) { var h = $('#dsts-sec-' + key + ' h2'); if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); } }
		},
		init: function () {
			Nav.keys = $$('.dsts-section').map(function (s) { return s.getAttribute('data-section'); });
			$$('.dsts-rail-btn').forEach(function (b) { b.addEventListener('click', function () { Nav.show(b.getAttribute('data-section'), true); }); });
			var hash = (location.hash || '').replace('#', '');
			Nav.show(Nav.keys.indexOf(hash) !== -1 ? hash : store.get('section', Nav.keys[0]));

			// Typography sub-tabs.
			$$('.dsts-tab').forEach(function (t) {
				t.addEventListener('click', function () {
					var key = t.getAttribute('data-tab'); var sec = t.closest('.dsts-section');
					$$('.dsts-tab', sec).forEach(function (x) { x.classList.toggle('is-active', x === t); x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
					$$('.dsts-tabpanel', sec).forEach(function (p) { p.hidden = p.getAttribute('data-tab') !== key; });
					store.set('typoTab', key);
				});
			});
			var tt = store.get('typoTab', ''); var tbtn = tt && $('.dsts-tab[data-tab="' + tt + '"]'); if (tbtn) { tbtn.click(); }
		}
	};

	var Levels = {
		init: function () {
			$$('.dsts-level').forEach(function (b) {
				b.addEventListener('click', function () {
					var lv = b.getAttribute('data-level');
					$$('.dsts-level').forEach(function (x) { x.classList.toggle('is-active', x === b); });
					$$('.dsts-levelpanel').forEach(function (p) { p.hidden = p.getAttribute('data-level') !== lv; });
				});
			});
			Levels.mark();
		},
		/** A dot on each heading level that carries its own values. */
		mark: function () {
			$$('.dsts-levelpanel').forEach(function (p) {
				var lv = p.getAttribute('data-level'); var has = false;
				$$('input[name], select[name]', p).forEach(function (el) {
					if (has) { return; }
					if (el.type === 'radio') { has = el.checked && el.value !== ''; return; }
					if (el.classList.contains('dsts-font-input')) { has = el.value !== 'Default' && el.value !== ''; return; }
					if (el.classList.contains('dsts-nu-unit')) { return; }
					has = el.value.trim() !== '';
				});
				var b = $('.dsts-level[data-level="' + lv + '"]'); if (b) { b.classList.toggle('has-override', has); }
			});
		}
	};

	/* ================================================================ Preview */

	var Preview = {
		iframe: $('#dsts-iframe'), stage: $('#dsts-stage'), frame: $('#dsts-frame'),
		ready: false, last: null, ctrl: null, device: 'desktop', live: false, seq: 0,
		widths: { desktop: 1440, tablet: 820, mobile: 390 }, userPicked: false,
		state: function (txt, kind) { var el = $('#dsts-preview-state'); if (!el) { return; } el.textContent = txt || ''; el.className = 'dsts-preview-state' + (kind ? ' is-' + kind : ''); },
		fit: function () {
			if (!Preview.stage || !Preview.frame) { return; }
			var sw = Preview.stage.clientWidth, sh = Preview.stage.clientHeight; if (!sw || !sh) { return; }
			var framed = Preview.device !== 'desktop';
			var w = Preview.device === 'desktop' ? Math.max(Preview.widths.desktop, sw) : Preview.widths[Preview.device];
			var pad = framed ? 32 : 0;
			var k = Math.min(1, (sw - pad) / w);
			var h = (sh - (framed ? 32 : 0)) / k;
			Preview.stage.classList.toggle('is-framed', framed);
			Preview.frame.style.width = w + 'px'; Preview.frame.style.height = h + 'px';
			Preview.frame.style.transform = 'scale(' + k + ')';
			Preview.frame.style.left = Math.max(0, (sw - w * k) / 2) + 'px';
		},
		load: function (url) {
			if (!Preview.iframe) { return; }
			Preview.ready = false; Preview.state('Loading…', 'busy'); Preview.iframe.src = url;
			var open = $('#dsts-preview-open'); if (open) { open.href = url.replace(/[?&]ds_ts_preview=1/, '').replace(/[?&]_dsnonce=[^&]*/, '').replace(/\?&/, '?').replace(/\?$/, ''); }
		},
		post: function (d) {
			if (!Preview.ready || !d || !Preview.iframe.contentWindow) { return; }
			Preview.iframe.contentWindow.postMessage({ type: 'dsts:css', css: d.css, ds: d.ds, customCss: d.customCss, fonts: d.fonts }, cfg.previewOrigin || '*');
		},
		/** Backgrounds and the page banner live on inner pages: show one there (unless a page was picked by hand). */
		suggestFor: function (section) {
			if (!Preview.select || Preview.userPicked || !Preview.pages) { return; }
			var cur = Preview.pages[+Preview.select.value]; if (!cur) { return; }
			var inner = section === 'backgrounds' || section === 'banner';
			var isHome = +Preview.select.value === 0;
			if (inner && isHome) {
				var pick = 1; Preview.pages.forEach(function (p, i) { if (i && /about/i.test(p.title) && pick === 1) { pick = i; } });
				if (Preview.pages[pick]) { Preview.select.value = String(pick); Preview.load(Preview.pages[pick].url); }
			}
		},
		request: function () {
			if (!Preview.iframe) { return; }
			Preview.live = true;
			if (Preview.ctrl) { Preview.ctrl.abort(); }
			Preview.ctrl = window.AbortController ? new AbortController() : null;
			var fd = new FormData(form); fd.set('action', cfg.previewAction); fd.set('nonce', cfg.nonce);
			var seq = ++Preview.seq;
			Preview.state('Updating…', 'busy');
			fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin', signal: Preview.ctrl ? Preview.ctrl.signal : undefined })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (seq !== Preview.seq) { return; }
					if (!j || !j.success) { throw new Error('preview'); }
					Preview.last = j.data; Preview.post(j.data); Preview.state(Preview.ready ? 'Live preview' : 'Loading…', Preview.ready ? '' : 'busy');
				})
				.catch(function (err) { if (err && err.name === 'AbortError') { return; } if (seq === Preview.seq) { Preview.state('Preview unavailable', 'error'); } });
		},
		init: function () {
			if (!Preview.iframe) { return; }
			var sel = $('#dsts-preview-page'); var pages = cfg.pages || [];
			pages.forEach(function (p, i) { var o = document.createElement('option'); o.value = String(i); o.textContent = p.title; sel.appendChild(o); });
			var saved = store.get('previewPage', ''); var idx = 0;
			pages.forEach(function (p, i) { if (String(p.id) === saved) { idx = i; } });
			// Opening straight onto Backgrounds / Page Banner: start on an inner page, where those show.
			var sec = store.get('section', '');
			if (!saved && idx === 0 && (sec === 'backgrounds' || sec === 'banner') && pages.length > 1) {
				idx = 1; pages.forEach(function (p, i) { if (i && /about/i.test(p.title) && idx === 1) { idx = i; } });
			}
			sel.value = String(idx);
			sel.addEventListener('change', function () { var p = pages[+sel.value]; if (p) { Preview.userPicked = true; store.set('previewPage', String(p.id)); Preview.load(p.url); } });
			Preview.pages = pages; Preview.select = sel;

			Preview.device = store.get('device', 'desktop');
			$$('.dsts-devices button').forEach(function (b) {
				b.setAttribute('aria-pressed', b.getAttribute('data-device') === Preview.device ? 'true' : 'false');
				b.addEventListener('click', function () {
					Preview.device = b.getAttribute('data-device'); store.set('device', Preview.device);
					$$('.dsts-devices button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
					Preview.fit();
				});
			});

			window.addEventListener('message', function (e) {
				if (e.source !== Preview.iframe.contentWindow) { return; }
				var d = e.data || {};
				if (d.type === 'dsts:ready') {
					Preview.ready = true; Preview.state('Live preview');
					if (Preview.live && Preview.last) { Preview.post(Preview.last); }
				}
			});
			window.addEventListener('resize', debounce(Preview.fit, 60));
			if (window.ResizeObserver) { new ResizeObserver(debounce(Preview.fit, 30)).observe(Preview.stage); }
			Preview.fit();

			// Hide / show the preview.
			var hide = $('#dsts-preview-hide'), toggle = $('#dsts-preview-toggle');
			var narrow = function () { return window.matchMedia('(max-width: 1100px)').matches; };
			if (store.get('preview', 'on') === 'off') { root.classList.add('no-preview'); }
			if (hide) { hide.addEventListener('click', function () { if (narrow()) { root.classList.remove('show-preview'); } else { root.classList.add('no-preview'); store.set('preview', 'off'); } }); }
			if (toggle) {
				toggle.addEventListener('click', function () {
					if (narrow()) { root.classList.toggle('show-preview'); } else { root.classList.remove('no-preview'); store.set('preview', 'on'); }
					if (!Preview.iframe.src || Preview.iframe.src === 'about:blank') { var p0 = pages[+sel.value]; if (p0) { Preview.load(p0.url); } }
					setTimeout(Preview.fit, 30);
				});
			}

			// Load the page after the admin screen itself is up, and only when the preview is visible.
			var start = function () { if (!root.classList.contains('no-preview') && !narrow()) { var p = pages[idx]; if (p) { Preview.load(p.url); } } };
			if (document.readyState === 'complete') { setTimeout(start, 50); } else { window.addEventListener('load', function () { setTimeout(start, 50); }); }
		}
	};

	/* =================================================================== Save */

	var Save = {
		/** Blank palette rows are dropped; half-filled ones block the save (the server would drop them and shift every uid). */
		validate: function () {
			var bad = null;
			Palette.rows().forEach(function (row) {
				var n = $('.dsts-pal-name', row).value.trim(); var v = $('.dsts-color-input', row).value.trim();
				if (!n && !v) { row.remove(); return; }
				var ok = !!(n && v && Palette.slug(n));
				row.classList.toggle('is-invalid', !ok); if (!ok && !bad) { bad = row; }
			});
			var seen = {}, dup = null;
			Palette.valid().forEach(function (g) { if (seen[g.varref] && !dup) { dup = g.row; } seen[g.varref] = true; });
			if (dup) { dup.classList.add('is-invalid'); bad = bad || dup; }
			if (bad) {
				Nav.show('colors'); bad.scrollIntoView({ block: 'center' }); var n2 = $('.dsts-pal-name', bad); if (n2) { n2.focus(); }
				Status.set('error', dup === bad ? 'Two colours share a name' : 'Each colour needs a name and a value');
				return false;
			}
			return true;
		},
		run: function () {
			if (State.saving) { return; }
			if (Fonts.active) { Fonts.close(false); }
			Code.sync();
			if (!Save.validate()) { return; }
			State.saving = true; Status.set('saving');
			var fd = new FormData(form); fd.set('ds_ajax', '1');
			fetch(form.getAttribute('action'), { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.text().then(function (t) { return { status: r.status, text: t }; }); })
				.then(function (res) {
					var j = null; try { j = JSON.parse(res.text); } catch (e) {}
					if (!j) { throw new Error(res.status === 403 || /expired|nonce|Are you sure/i.test(res.text) ? 'Session expired: reload the page' : 'Unexpected response (' + res.status + ')'); }
					if (!j.success) { throw new Error(typeof j.data === 'string' ? j.data : 'Could not save'); }
					// Keep the palette in step with what BB stored (a new colour gets its uid on save).
					var rows = Palette.valid().map(function (g) { return g.row; });
					var colors = j.data.colors || [];
					if (colors.length !== rows.length) { State.leaving = true; location.reload(); return; }
					colors.forEach(function (c, i) {
						var uid = $('input[name="color_uid[]"]', rows[i]); if (uid) { uid.value = c.uid || ''; }
						rows[i].setAttribute('data-orig-name', c.label || ''); Palette.renderVar(rows[i]);
					});
					if (j.data.prefixKey) { Palette.prefix = j.data.prefixKey; }
					State.saving = false; State.init(); Status.set('saved');
				})
				.catch(function (err) { State.saving = false; State.dirty = true; Status.set('error', err.message || 'Could not save'); });
		},
		init: function () {
			form.addEventListener('submit', function (e) { e.preventDefault(); if (State.dirty) { Save.run(); } });
			document.addEventListener('keydown', function (e) {
				if ((e.metaKey || e.ctrlKey) && !e.altKey && (e.key === 's' || e.key === 'S')) { e.preventDefault(); if (State.dirty) { Save.run(); } }
			});
			$('#dsts-discard').addEventListener('click', function () {
				if (!window.confirm('Discard all unsaved changes?')) { return; }
				State.leaving = true; location.reload();
			});
			window.addEventListener('beforeunload', function (e) { if (State.dirty && !State.leaving) { e.preventDefault(); e.returnValue = ''; } });
		}
	};

	/* ================================================================== Media */

	var Media = {
		frame: null, target: null,
		pick: function (tile) {
			if (!window.wp || !wp.media) { return; }
			Media.target = tile;
			if (!Media.frame) {
				Media.frame = wp.media({ title: 'Select image', multiple: false, library: { type: 'image' }, button: { text: 'Use this image' } });
				Media.frame.on('select', function () {
					var a = Media.frame.state().get('selection').first().toJSON(); var t = Media.target; if (!t) { return; }
					var thumb = (a.sizes && (a.sizes.medium || a.sizes.large)) ? (a.sizes.medium || a.sizes.large).url : a.url;
					Media.set(t, a.url, a.id, thumb);
				});
			}
			Media.frame.options.title = tile.getAttribute('data-title') || 'Select image';
			Media.frame.open();
		},
		set: function (tile, url, id, thumb) {
			var u = $('.dsts-img-url', tile), i = $('.dsts-img-id', tile), btn = $('.dsts-img-thumb', tile);
			u.value = url || ''; if (i) { i.value = id || ''; }
			var img = $('img', btn);
			if (url) { if (!img) { img = document.createElement('img'); img.alt = ''; btn.insertBefore(img, btn.firstChild); } img.src = thumb || url; }
			else if (img) { img.remove(); }
			tile.classList.toggle('has-img', !!url);
			$('.dsts-img-remove', tile).hidden = !url; $('.dsts-img-select', tile).textContent = url ? 'Replace' : 'Select';
			var row = tile.closest('.dsts-field'); var opts = row && row.nextElementSibling;
			if (opts && opts.classList.contains('dsts-bg-opts')) { opts.hidden = !url; }
			changed(u);
		},
		init: function () {
			root.addEventListener('click', function (e) {
				var tile = e.target.closest('.dsts-img'); if (!tile) { return; }
				if (e.target.closest('.dsts-img-select, .dsts-img-thumb')) { e.preventDefault(); Media.pick(tile); }
				else if (e.target.closest('.dsts-img-remove')) { e.preventDefault(); Media.set(tile, '', '', ''); }
			});
		}
	};

	/* ================================================================== Small */

	var Controls = {
		init: function () {
			// Linked quads: typing in one sets all four.
			root.addEventListener('click', function (e) {
				var b = e.target.closest('.dsts-quad-link'); if (!b) { return; }
				var q = b.closest('.dsts-quad'); var on = b.getAttribute('aria-pressed') !== 'true';
				b.setAttribute('aria-pressed', on ? 'true' : 'false'); q.classList.toggle('is-linked', on);
				if (on) { var first = $('input', q); $$('input', q).forEach(function (i) { if (i.value !== first.value) { i.value = first.value; changed(i); } }); }
			});
			root.addEventListener('input', function (e) {
				var q = e.target.closest && e.target.closest('.dsts-quad.is-linked'); if (!q || e.target.type !== 'number' || Controls.syncing) { return; }
				Controls.syncing = true; $$('input', q).forEach(function (i) { if (i !== e.target) { i.value = e.target.value; } }); Controls.syncing = false;
			});

			// Button shape cards + their demo colours.
			root.addEventListener('change', function (e) {
				if (e.target.name === 'button_style') { $$('.dsts-shape').forEach(function (s) { s.classList.toggle('is-active', $('input', s).checked); }); }
			});
			var shapes = $('.dsts-shapes');
			var paintShapes = function () {
				if (!shapes) { return; }
				var bg = $('input[data-role="btn-bg"]'), fg = $('input[data-role="btn-fg"]');
				shapes.style.setProperty('--dsts-btn-bg', Color.css(bg && bg.value) || '#1cb0f6');
				shapes.style.setProperty('--dsts-btn-fg', Color.css(fg && fg.value) || '#ffffff');
				var r = $('input[name="border[button][radius_top_left]"]');
				shapes.style.setProperty('--dsts-demo-radius', (r && r.value !== '' ? r.value : 4) + 'px');
			};
			root.addEventListener('dsts:color', paintShapes); root.addEventListener('input', debounce(paintShapes, 50)); paintShapes();

			// Range + number pairs, with small demos.
			$$('.dsts-range').forEach(function (w) {
				var range = $('input[type="range"]', w), num = $('.dsts-num', w);
				var demo = function () {
					if (w.getAttribute('data-demo') === 'radius') { w.style.setProperty('--dsts-demo-r', (num.value || 0) + 'px'); }
					if (w.getAttribute('data-demo') === 'outline') {
						w.style.setProperty('--dsts-demo-ow', (num.value || 2) + 'px');
						var oc = $('input[name="general[outline_color]"]'); w.style.setProperty('--dsts-demo-oc', Color.css(oc && oc.value) || '#1d2327');
					}
				};
				range.addEventListener('input', function () { num.value = range.value; demo(); changed(num); });
				num.addEventListener('input', function () { range.value = num.value; demo(); });
				root.addEventListener('dsts:color', demo); demo();
			});

			// Reset a typography block to the theme default.
			root.addEventListener('click', function (e) {
				var b = e.target.closest('.dsts-typo-reset'); if (!b) { return; }
				var block = b.closest('.dsts-typo');
				$$('.dsts-font-input', block).forEach(function (i) { i.value = 'Default'; i.classList.add('is-default'); });
				$$('select', block).forEach(function (s) { s.selectedIndex = 0; });
				$$('input[type="number"]', block).forEach(function (i) { i.value = ''; });
				$$('input[type="radio"]', block).forEach(function (r) { r.checked = r.value === ''; });
				$$('.dsts-color', block).forEach(function (c) { $('.dsts-color-input', c).value = ''; Color.render(c); });
				Fonts.applyWeights(); changed(block.querySelector('input,select'));
			});

		}
	};

	/* =================================================================== Code */

	var Code = {
		done: false, editors: [],
		/** Load WordPress' code editor (CodeMirror + linters) the first time Custom Code opens. */
		assets: function () {
			var c = cfg.codeEditor || {};
			if (window.wp && wp.codeEditor && window.CodeMirror) { return Promise.resolve(); }
			(c.styles || []).forEach(function (href) { var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; document.head.appendChild(l); });
			return (c.scripts || []).reduce(function (chain, src) {
				return chain.then(function () {
					return new Promise(function (res, rej) { var s = document.createElement('script'); s.src = src; s.async = false; s.onload = res; s.onerror = rej; document.body.appendChild(s); });
				});
			}, Promise.resolve());
		},
		init: function () {
			if (Code.done) { Code.editors.forEach(function (ed) { ed.codemirror.refresh(); }); return; }
			Code.done = true;
			if (!cfg.codeEditor) { return; } // syntax highlighting turned off: plain textareas
			Code.assets().then(function () {
				if (!window.wp || !wp.codeEditor) { return; }
				[['dsts-css-code', cfg.codeEditor.css], ['dsts-js-code', cfg.codeEditor.js]].forEach(function (pair) {
					var ta = document.getElementById(pair[0]); if (!ta || !pair[1]) { return; }
					var ed = wp.codeEditor.initialize(ta, pair[1]);
					ed.codemirror.on('change', debounce(function (cm) { cm.save(); changed(ta); }, 150));
					Code.editors.push(ed);
				});
			}).catch(function () { /* keep the plain textareas */ });
		},
		sync: function () { Code.editors.forEach(function (ed) { ed.codemirror.save(); }); }
	};

	/* ================================================================== Boot */

	Palette.init();
	Color.init();
	Fonts.init();
	Nav.init();
	Levels.init();
	Media.init();
	Controls.init();
	Preview.init();
	Save.init();
	State.init();
	if (/[?&]updated=1/.test(location.search)) { Status.set('saved'); }

	var onEdit = debounce(function () { State.check(); }, 80);
	var onPreview = debounce(function () { Preview.request(); }, 260);
	form.addEventListener('input', function () { onEdit(); onPreview(); });
	form.addEventListener('change', function () { onEdit(); onPreview(); });
})();
