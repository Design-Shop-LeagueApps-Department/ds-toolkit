/**
 * LeagueApps Table: search, column sorting, pagination, stripes.
 *
 * The rows are already in the HTML; this only hides, shows and reorders them, so
 * the table works with JS off or still loading. Idempotent and re-bound after the
 * Beaver Builder preview re-renders a node.
 *
 * Row attributes: data-i original index, data-s<N> the numeric sort key of a number,
 * date or time column N. Search and text columns read the row's own text.
 */
(function () {
	'use strict';

	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function lower(s) { return (s || '').replace(/\s+/g, ' ').trim().toLowerCase(); }

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ds-table]'), function (wrap) {
			if (wrap.dsTableInit) { return; }
			wrap.dsTableInit = true;

			var tbody    = wrap.querySelector('.ds-table-t tbody');
			var rows     = Array.prototype.slice.call(wrap.querySelectorAll('.ds-table-row'));
			var search   = wrap.querySelector('[data-ds-table-search]');
			var sortSel  = wrap.querySelector('[data-ds-table-sortsel]');
			var sortBtns = wrap.querySelectorAll('.ds-table-sortbtn');
			var countEl  = wrap.querySelector('[data-ds-table-count]');
			var noneEl   = wrap.querySelector('[data-ds-table-none]');
			var pagerEl  = wrap.querySelector('[data-ds-table-pager]');
			var pageSize = parseInt(wrap.getAttribute('data-page-size'), 10) || 0;
			var one      = wrap.getAttribute('data-one') || 'row';
			var many     = wrap.getAttribute('data-many') || 'rows';
			var prevTxt  = wrap.getAttribute('data-prev') || 'Previous';
			var nextTxt  = wrap.getAttribute('data-next') || 'Next';
			if (!tbody || !rows.length) { return; }

			// Search text and text-column sort keys, read once from the cells (the server no
			// longer repeats every cell's words in data attributes).
			rows.forEach(function (r) {
				r.dsHay = lower(r.textContent);
				r.dsKeys = Array.prototype.map.call(r.children, function (c) { return lower(c.textContent); });
			});

			var state = { key: '', dir: 'asc', page: 1 };
			var types = {};
			Array.prototype.forEach.call(sortBtns, function (b) { types[b.getAttribute('data-sort')] = b.getAttribute('data-type') || 'text'; });

			function matches(row, q) {
				if (!q) { return true; }
				var hay = row.dsHay || '';
				return q.split(/\s+/).every(function (w) { return hay.indexOf(w) !== -1; });
			}

			function sorted(list) {
				var byIndex = function (a, b) { return (+a.getAttribute('data-i')) - (+b.getAttribute('data-i')); };
				if (state.key === '') { return list.slice().sort(byIndex); }
				var attr = 'data-s' + state.key, num = types[state.key] === 'num', dir = state.dir === 'desc' ? -1 : 1;
				var key = num
					? function (r) { return r.getAttribute(attr) || ''; }
					: function (r) { return r.dsKeys[+state.key] || ''; };
				return list.slice().sort(function (a, b) {
					var av = key(a), bv = key(b), c;
					if (av === '' || bv === '') { c = av === bv ? 0 : (av === '' ? 1 : -1); return c || byIndex(a, b); } // blanks always last
					if (num) { c = parseFloat(av) - parseFloat(bv); }
					else { c = av.localeCompare(bv, undefined, { numeric: true, sensitivity: 'base' }); }
					return (c || byIndex(a, b)) * dir;
				});
			}

			function pager(total) {
				if (!pagerEl) { return; }
				if (!pageSize || total <= pageSize) { pagerEl.hidden = true; pagerEl.innerHTML = ''; return; }
				var pages = Math.ceil(total / pageSize);
				if (state.page > pages) { state.page = pages; }
				var btn = function (label, page, cls, disabled, current) {
					return '<button type="button" class="ds-table-page' + (cls ? ' ' + cls : '') + '" data-page="' + page + '"' + (disabled ? ' disabled' : '') + (current ? ' aria-current="page"' : '') + '>' + esc(label) + '</button>';
				};
				var html = btn(prevTxt, state.page - 1, 'ds-table-page--prev', state.page === 1);
				var win = [];
				for (var p = 1; p <= pages; p++) {
					if (p === 1 || p === pages || Math.abs(p - state.page) <= 1) { win.push(p); }
					else if (win[win.length - 1] !== '…') { win.push('…'); }
				}
				win.forEach(function (p) { html += p === '…' ? '<span class="ds-table-page-gap" aria-hidden="true">…</span>' : btn(String(p), p, p === state.page ? 'is-current' : '', false, p === state.page); });
				html += btn(nextTxt, state.page + 1, 'ds-table-page--next', state.page === pages);
				var from = (state.page - 1) * pageSize + 1, to = Math.min(total, state.page * pageSize);
				html += '<span class="ds-table-page-range">' + from + '–' + to + ' / ' + total + '</span>';
				pagerEl.innerHTML = html;
				pagerEl.hidden = false;
			}

			function apply(reset) {
				var q = search ? search.value.trim().toLowerCase() : '';
				if (reset) { state.page = 1; }
				sorted(rows).forEach(function (r) { tbody.appendChild(r); });
				var visible = sorted(rows.filter(function (r) { return matches(r, q); }));
				var total = visible.length;
				var from = pageSize ? (state.page - 1) * pageSize : 0, to = pageSize ? from + pageSize : Infinity;
				var onPage = {}, matched = {};
				visible.forEach(function (r, i) { var k = r.getAttribute('data-i'); matched[k] = true; onPage[k] = i >= from && i < to; });
				var n = 0;
				sorted(rows).forEach(function (r) {
					var k = r.getAttribute('data-i');
					r.classList.toggle('is-hidden', !matched[k]);
					r.classList.toggle('is-paged', !!matched[k] && !onPage[k]);
					var shown = matched[k] && onPage[k];
					r.classList.toggle('is-alt', !!shown && (n % 2 === 1));
					if (shown) { n++; }
				});
				if (countEl) { countEl.textContent = total + ' ' + (total === 1 ? one : many); }
				if (noneEl) { noneEl.hidden = total !== 0; }
				pager(total);
				Array.prototype.forEach.call(sortBtns, function (b) {
					var is = b.getAttribute('data-sort') === state.key;
					b.parentNode.setAttribute('aria-sort', is ? (state.dir === 'desc' ? 'descending' : 'ascending') : 'none');
					b.classList.toggle('is-asc', is && state.dir === 'asc');
					b.classList.toggle('is-desc', is && state.dir === 'desc');
				});
				if (sortSel) { sortSel.value = state.key !== '' ? state.key + ':' + state.dir : ''; }
			}

			function setSort(key, dir) { state.key = key; state.dir = dir || 'asc'; apply(true); }

			Array.prototype.forEach.call(sortBtns, function (b) {
				b.addEventListener('click', function () {
					var k = b.getAttribute('data-sort');
					if (state.key !== k) { setSort(k, 'asc'); }
					else if (state.dir === 'asc') { setSort(k, 'desc'); }
					else { setSort('', 'asc'); }
				});
			});
			if (sortSel) {
				sortSel.addEventListener('change', function () { var v = sortSel.value.split(':'); setSort(v[0] || '', v[1] || 'asc'); });
			}
			if (search) {
				var t = null;
				search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { apply(true); }, 120); });
				search.addEventListener('search', function () { apply(true); });
			}
			if (pagerEl) {
				pagerEl.addEventListener('click', function (e) {
					var b = e.target.closest ? e.target.closest('[data-page]') : null;
					if (!b || b.disabled) { return; }
					state.page = parseInt(b.getAttribute('data-page'), 10) || 1;
					apply(false);
					if (wrap.getBoundingClientRect().top < 0) { wrap.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
				});
			}
			apply(true);
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	if (window.jQuery && !window.dsTableBound) { window.dsTableBound = true; window.jQuery(document).on('fl-builder.layout-rendered', boot); }
}());
