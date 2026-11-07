/**
 * LeagueApps Programs: filters, keyword search, column sorting, pagination.
 *
 * Rows are already in the HTML; this only hides, shows and reorders them, so
 * the listing still works with JS off or still loading. Idempotent and
 * re-bindable so the Beaver Builder preview re-render does not double-bind.
 *
 * Row attributes:  data-f-<key> filter value (data-multi selects match a
 * comma list), data-s-<key> sort key (numeric when the header says
 * data-type="num"), data-search lowercase haystack, data-i original index.
 */
(function () {
	'use strict';

	/**
	 * A table wider than its box scrolls sideways inside it. macOS, iOS and iPadOS
	 * hide scrollbars, so the frame gets can-scroll-left / can-scroll-right (an edge
	 * shadow on the side that has more) only while that is true. While it overflows,
	 * the box is also a named, focusable region so a keyboard can scroll it.
	 * Re-measured on scroll and whenever the box or the table changes size: filters,
	 * paging, web fonts arriving, rotation, the phone card switch.
	 */
	function watchFrame(frame) {
		if (frame.dsProgramsFrame) { return; }
		var box = frame.querySelector('.ds-programs-scroll');
		var table = box ? box.querySelector('table') : null;
		if (!box || !table) { return; }
		frame.dsProgramsFrame = true;
		var wrap = frame.closest ? frame.closest('[data-ds-programs]') : null;
		var label = frame.getAttribute('data-label') || '';
		var raf = 0, lastW = -1, recheck = true;

		/*
		 * Cards wherever the table does not fit. Lay it out as a table, measure, and stack it
		 * (.is-stacked, see frontend.css.php) when it is wider than its box: a portrait tablet,
		 * many columns, long names, large text. All in one frame, so nothing flashes. Only a
		 * change of WIDTH (or the web fonts arriving) re-decides; a filter or a page change does
		 * not, so the layout never flips while a visitor is using it. On a phone the media query
		 * already shows cards, and that is left alone.
		 */
		function fits() { return box.scrollWidth <= box.clientWidth + 1; }
		function stack() {
			if (!wrap) { return; }
			wrap.classList.remove('is-stacked');
			wrap.classList.remove('is-tight');
			if (getComputedStyle(table).display !== 'table' || fits()) { return; }
			// First drop the wide-screen minimum column widths (Modern preset); stack only if it still does not fit.
			wrap.classList.add('is-tight');
			if (fits()) { return; }
			wrap.classList.remove('is-tight');
			wrap.classList.add('is-stacked');
		}

		function measure() {
			raf = 0;
			var w = frame.clientWidth;
			// A table showing that no longer fits (text zoom after load) stacks too. Filtering only ever
			// hides rows, and the first measure runs with every row shown, so this never fires mid-filter.
			var spill = wrap && !wrap.classList.contains('is-stacked') && getComputedStyle(table).display === 'table' && !fits();
			if (recheck || w !== lastW || spill) { recheck = false; lastW = w; stack(); }
			var max = box.scrollWidth - box.clientWidth;
			var over = max > 1 && getComputedStyle(box).overflowX !== 'visible';
			var x = Math.abs(box.scrollLeft); // negative in right-to-left pages
			frame.classList.toggle('is-scrollable', over);
			frame.classList.toggle('can-scroll-left', over && x > 1);
			frame.classList.toggle('can-scroll-right', over && x < max - 1);
			if (over) {
				box.setAttribute('tabindex', '0');
				box.setAttribute('role', 'region');
				if (label) { box.setAttribute('aria-label', label); }
			} else {
				box.removeAttribute('tabindex');
				box.removeAttribute('role');
				box.removeAttribute('aria-label');
			}
		}
		function update() {
			if (raf) { return; }
			raf = window.requestAnimationFrame ? window.requestAnimationFrame(measure) : setTimeout(measure, 16);
		}
		box.addEventListener('scroll', update, { passive: true });
		if (window.ResizeObserver) {
			var ro = new ResizeObserver(update);
			ro.observe(box);
			var table = box.querySelector('table');
			if (table) { ro.observe(table); }
		} else {
			window.addEventListener('resize', update);
		}
		measure();
		// Web fonts can widen the table after the first measure.
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(function () { recheck = true; update(); });
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ds-programs-frame]'), watchFrame);

		var wraps = document.querySelectorAll('[data-ds-programs]');

		Array.prototype.forEach.call(wraps, function (wrap) {
			if (wrap.dsProgramsInit) { return; }
			wrap.dsProgramsInit = true;

			var selects  = wrap.querySelectorAll('[data-ds-programs-filter]');
			var search   = wrap.querySelector('[data-ds-programs-search]');
			var sortSel  = wrap.querySelector('[data-ds-programs-sortsel]');
			var sortBtns = wrap.querySelectorAll('.ds-programs-sortbtn');
			var tbody    = wrap.querySelector('[data-ds-programs-list]') || wrap.querySelector('.ds-programs-table tbody');
			var rows     = Array.prototype.slice.call(wrap.querySelectorAll('.ds-programs-row'));
			var countEl  = wrap.querySelector('[data-ds-programs-count]');
			var noneEl   = wrap.querySelector('[data-ds-programs-none]');
			var clearEl  = wrap.querySelector('[data-ds-programs-clear]');
			var pagerEl  = wrap.querySelector('[data-ds-programs-pager]');
			var one      = wrap.getAttribute('data-one') || 'program';
			var many     = wrap.getAttribute('data-many') || 'programs';
			var pageSize = parseInt(wrap.getAttribute('data-page-size'), 10) || 0;
			var prevTxt  = wrap.getAttribute('data-prev') || 'Previous';
			var nextTxt  = wrap.getAttribute('data-next') || 'Next';

			if (!rows.length) { return; }

			var state = { sortKey: '', sortDir: 'asc', sortType: 'text', page: 1 };
			var types = {};
			Array.prototype.forEach.call(sortBtns, function (b) { types[b.getAttribute('data-sort')] = b.getAttribute('data-type') || 'text'; });
			if (sortSel) {
				Array.prototype.forEach.call(sortSel.options, function (o) {
					var k = o.value.split(':')[0];
					if (k && !types[k]) { types[k] = /^(dateRange|startDate|endDate|month|ageGroup|days|price|spots)$/.test(k) ? 'num' : 'text'; }
				});
			}

			function matches(row, active, q) {
				var ok = active.every(function (f) {
					var v = row.getAttribute(f.attr) || '';
					if (!f.multi) { return v === f.value; }
					return v.split(',').some(function (p) { return p.trim() === f.value; });
				});
				if (!ok) { return false; }
				if (!q) { return true; }
				var hay = row.getAttribute('data-search') || '';
				return q.split(/\s+/).every(function (w) { return hay.indexOf(w) !== -1; });
			}

			function comparator() {
				if (!state.sortKey) {
					return function (a, b) { return (+a.getAttribute('data-i')) - (+b.getAttribute('data-i')); };
				}
				var attr = 'data-s-' + state.sortKey.toLowerCase();
				var num  = state.sortType === 'num';
				var dir  = state.sortDir === 'desc' ? -1 : 1;
				return function (a, b) {
					var av = a.getAttribute(attr) || '', bv = b.getAttribute(attr) || '';
					var c;
					if (num) { c = (parseFloat(av) || 0) - (parseFloat(bv) || 0); }
					else { c = av < bv ? -1 : (av > bv ? 1 : 0); }
					if (c === 0) { return (+a.getAttribute('data-i')) - (+b.getAttribute('data-i')); }
					return c * dir;
				};
			}

			// A sub-program is never ordered against the whole table: it stays
			// directly under its main program, which is the row that carries the
			// group's place in the list. Sorting every row flat splits a group the
			// moment another program's value falls between two of its sub-programs,
			// and the stranded ones then read as belonging to the program above them.
			function sorted(list) {
				var cmp = comparator();
				var kids = {}, tops = [], present = {};
				list.forEach(function (r) { present[r.getAttribute('data-i')] = 1; });
				list.forEach(function (r) {
					var p = r.getAttribute('data-parent');
					if (p && present[p]) { (kids[p] = kids[p] || []).push(r); }
					else { tops.push(r); }
				});
				tops.sort(cmp);
				var out = [];
				tops.forEach(function (r) {
					out.push(r);
					var k = kids[r.getAttribute('data-i')];
					if (k) { k.sort(cmp); k.forEach(function (c) { out.push(c); }); }
				});
				return out;
			}

			function renderPager(total) {
				if (!pagerEl) { return; }
				if (!pageSize || total <= pageSize) { pagerEl.hidden = true; pagerEl.innerHTML = ''; return; }
				var pages = Math.ceil(total / pageSize);
				if (state.page > pages) { state.page = pages; }
				var html = '';
				// The Previous / Next labels come from the module settings: always inserted as text, never as HTML.
				var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
				var btn = function (label, page, cls, disabled, aria) {
					return '<button type="button" class="fl-content-ui-button ds-programs-page' + (cls ? ' ' + cls : '') + '" data-page="' + page + '"' + (disabled ? ' disabled' : '') + (aria ? ' aria-current="page"' : '') + '>' + esc(label) + '</button>';
				};
				html += btn(prevTxt, state.page - 1, 'ds-programs-page--prev', state.page === 1);
				var win = [];
				for (var p = 1; p <= pages; p++) {
					if (p === 1 || p === pages || Math.abs(p - state.page) <= 1) { win.push(p); }
					else if (win[win.length - 1] !== '…') { win.push('…'); }
				}
				win.forEach(function (p) {
					html += (p === '…') ? '<span class="ds-programs-page-gap" aria-hidden="true">…</span>' : btn(String(p), p, p === state.page ? 'is-current' : '', false, p === state.page);
				});
				html += btn(nextTxt, state.page + 1, 'ds-programs-page--next', state.page === pages);
				var from = (state.page - 1) * pageSize + 1, to = Math.min(total, state.page * pageSize);
				html += '<span class="ds-programs-page-range">' + from + '–' + to + ' / ' + total + '</span>';
				pagerEl.innerHTML = html;
				pagerEl.hidden = false;
			}

			function apply(opts) {
				opts = opts || {};
				var active = [];
				Array.prototype.forEach.call(selects, function (sel) {
					if (sel.value) {
						active.push({ attr: 'data-f-' + sel.getAttribute('data-ds-programs-filter').toLowerCase(), value: sel.value, multi: sel.getAttribute('data-multi') === '1' });
					}
				});
				var q = search ? search.value.trim().toLowerCase() : '';
				if (opts.reset) { state.page = 1; }

				// A sub-program nested under its main program (data-parent) is one
				// group with it: the group shows when the main program matches or any
				// sub-program does, and then the sub-programs that match (all of them
				// when the main program matched on its own).
				var own = {}, kids = {};
				rows.forEach(function (r) {
					var k = r.getAttribute('data-i'), p = r.getAttribute('data-parent');
					own[k] = matches(r, active, q);
					if (p) { (kids[p] = kids[p] || []).push(k); }
				});
				var vis = {};
				rows.forEach(function (r) {
					var k = r.getAttribute('data-i');
					if (!r.getAttribute('data-parent')) { vis[k] = own[k] || (kids[k] || []).some(function (c) { return own[c]; }); }
				});
				rows.forEach(function (r) {
					var k = r.getAttribute('data-i'), p = r.getAttribute('data-parent');
					if (p) { vis[k] = !!vis[p] && (own[k] || own[p]); }
				});
				var shown = rows.filter(function (r) { return vis[r.getAttribute('data-i')]; });
				var order = sorted(rows);
				if (tbody) { order.forEach(function (r) { tbody.appendChild(r); }); }

				var visible = sorted(shown);
				var total = visible.length;
				var from = pageSize ? (state.page - 1) * pageSize : 0;
				var to   = pageSize ? from + pageSize : Infinity;
				var idx = 0;
				var shownSet = {};
				visible.forEach(function (r) { shownSet[r.getAttribute('data-i')] = (idx >= from && idx < to); idx++; });
				rows.forEach(function (r) {
					var k = r.getAttribute('data-i');
					var isMatch = Object.prototype.hasOwnProperty.call(shownSet, k);
					r.classList.toggle('is-hidden', !isMatch);
					r.classList.toggle('is-paged', isMatch && !shownSet[k]);
				});

				// The count is programs, so nested sub-programs are not counted twice.
				var programs = visible.filter(function (r) { return !r.getAttribute('data-parent'); }).length;
				if (countEl) { countEl.textContent = programs + ' ' + (programs === 1 ? one : many); }
				if (noneEl)  { noneEl.hidden = total !== 0; }
				if (clearEl) { clearEl.hidden = active.length === 0 && !q; }
				renderPager(total);

				Array.prototype.forEach.call(sortBtns, function (b) {
					var th = b.parentNode;
					var is = b.getAttribute('data-sort') === state.sortKey;
					th.setAttribute('aria-sort', is ? (state.sortDir === 'desc' ? 'descending' : 'ascending') : 'none');
					b.classList.toggle('is-asc', is && state.sortDir === 'asc');
					b.classList.toggle('is-desc', is && state.sortDir === 'desc');
				});
				if (sortSel) { sortSel.value = state.sortKey ? state.sortKey + ':' + state.sortDir : ''; }
			}

			function setSort(key, dir) {
				state.sortKey = key || '';
				state.sortDir = dir || 'asc';
				state.sortType = types[key] || 'text';
				apply({ reset: true });
			}

			Array.prototype.forEach.call(selects, function (sel) { sel.addEventListener('change', function () { apply({ reset: true }); }); });

			if (search) {
				var t = null;
				search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { apply({ reset: true }); }, 150); });
				search.addEventListener('search', function () { apply({ reset: true }); });
			}

			Array.prototype.forEach.call(sortBtns, function (b) {
				b.addEventListener('click', function () {
					var key = b.getAttribute('data-sort');
					if (state.sortKey !== key) { setSort(key, 'asc'); }
					else if (state.sortDir === 'asc') { setSort(key, 'desc'); }
					else { setSort('', 'asc'); }
				});
			});

			if (sortSel) {
				sortSel.addEventListener('change', function () {
					var v = sortSel.value.split(':');
					setSort(v[0] || '', v[1] || 'asc');
				});
			}

			if (pagerEl) {
				pagerEl.addEventListener('click', function (e) {
					var b = e.target.closest ? e.target.closest('[data-page]') : null;
					if (!b || b.disabled) { return; }
					state.page = parseInt(b.getAttribute('data-page'), 10) || 1;
					apply();
					var top = wrap.getBoundingClientRect().top;
					if (top < 0) { wrap.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
				});
			}

			if (clearEl) {
				clearEl.addEventListener('click', function () {
					Array.prototype.forEach.call(selects, function (sel) { sel.value = ''; });
					if (search) { search.value = ''; }
					apply({ reset: true });
				});
			}

			apply({ reset: true });
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	// Beaver Builder re-renders the node while editing.
	if (window.jQuery) {
		window.jQuery(document).on('fl-builder.layout-rendered', boot);
	}
}());
