/**
 * LeagueApps Programs, filter bar.
 *
 * Rows are already in the HTML; this only shows and hides them, so the listing
 * still works with JS off or still loading. Idempotent and re-bindable so the
 * Beaver Builder preview re-render does not double-bind.
 *
 * A filter select carries data-ds-programs-filter="<key>"; each row carries
 * data-f-<key>="<value>". A select marked data-multi="1" (days) matches when
 * the row's comma-separated list CONTAINS the chosen value.
 */
(function () {
	'use strict';

	function boot() {
		var wraps = document.querySelectorAll('[data-ds-programs]');

		Array.prototype.forEach.call(wraps, function (wrap) {
			if (wrap.dsProgramsInit) { return; }
			wrap.dsProgramsInit = true;

			var selects = wrap.querySelectorAll('[data-ds-programs-filter]');
			var rows    = wrap.querySelectorAll('.ds-programs-row');
			var countEl = wrap.querySelector('[data-ds-programs-count]');
			var noneEl  = wrap.querySelector('[data-ds-programs-none]');
			var clearEl = wrap.querySelector('[data-ds-programs-clear]');
			var one     = wrap.getAttribute('data-one') || 'program';
			var many    = wrap.getAttribute('data-many') || 'programs';

			if (!rows.length) { return; }

			function apply() {
				var active = [];
				Array.prototype.forEach.call(selects, function (sel) {
					if (sel.value) {
						active.push({
							attr:  'data-f-' + sel.getAttribute('data-ds-programs-filter').toLowerCase(),
							value: sel.value,
							multi: sel.getAttribute('data-multi') === '1'
						});
					}
				});

				var shown = 0;
				Array.prototype.forEach.call(rows, function (row) {
					var ok = active.every(function (f) {
						var v = row.getAttribute(f.attr) || '';
						if (!f.multi) { return v === f.value; }
						return v.split(',').some(function (p) { return p.trim() === f.value; });
					});
					row.classList.toggle('is-hidden', !ok);
					if (ok) { shown++; }
				});

				if (countEl) { countEl.textContent = shown + ' ' + (shown === 1 ? one : many); }
				if (noneEl)  { noneEl.hidden = shown !== 0; }
				if (clearEl) { clearEl.hidden = active.length === 0; }
			}

			Array.prototype.forEach.call(selects, function (sel) { sel.addEventListener('change', apply); });

			if (clearEl) {
				clearEl.addEventListener('click', function () {
					Array.prototype.forEach.call(selects, function (sel) { sel.value = ''; });
					apply();
				});
			}

			apply();
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
