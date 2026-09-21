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

	function boot() {
		var wraps = document.querySelectorAll('[data-ds-programs]');

		Array.prototype.forEach.call(wraps, function (wrap) {
			if (wrap.dsProgramsInit) { return; }
			wrap.dsProgramsInit = true;

			var selects  = wrap.querySelectorAll('[data-ds-programs-filter]');
			var search   = wrap.querySelector('[data-ds-programs-search]');
			var sortSel  = wrap.querySelector('[data-ds-programs-sortsel]');
			var sortBtns = wrap.querySelectorAll('.ds-programs-sortbtn');
			var tbody    = wrap.querySelector('.ds-programs-table tbody');
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

			function sorted(list) {
				if (!state.sortKey) {
					return list.slice().sort(function (a, b) { return (+a.getAttribute('data-i')) - (+b.getAttribute('data-i')); });
				}
				var attr = 'data-s-' + state.sortKey.toLowerCase();
				var num  = state.sortType === 'num';
				var dir  = state.sortDir === 'desc' ? -1 : 1;
				return list.slice().sort(function (a, b) {
					var av = a.getAttribute(attr) || '', bv = b.getAttribute(attr) || '';
					var c;
					if (num) { c = (parseFloat(av) || 0) - (parseFloat(bv) || 0); }
					else { c = av < bv ? -1 : (av > bv ? 1 : 0); }
					if (c === 0) { c = (+a.getAttribute('data-i')) - (+b.getAttribute('data-i')); }
					return c * dir;
				});
			}

			function renderPager(total) {
				if (!pagerEl) { return; }
				if (!pageSize || total <= pageSize) { pagerEl.hidden = true; pagerEl.innerHTML = ''; return; }
				var pages = Math.ceil(total / pageSize);
				if (state.page > pages) { state.page = pages; }
				var html = '';
				var btn = function (label, page, cls, disabled, aria) {
					return '<button type="button" class="ds-programs-page' + (cls ? ' ' + cls : '') + '" data-page="' + page + '"' + (disabled ? ' disabled' : '') + (aria ? ' aria-current="page"' : '') + '>' + label + '</button>';
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

				var shown = rows.filter(function (r) { return matches(r, active, q); });
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

				if (countEl) { countEl.textContent = total + ' ' + (total === 1 ? one : many); }
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
