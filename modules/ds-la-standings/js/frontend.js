/* LeagueApps Standings (ds-toolkit): division tabs and the division dropdown. One init per block; safe to call twice.
   The table is printed by the server, so without this script the first division still shows. */
(function () {
	function show(root, panelId) {
		root.querySelectorAll('.dsst-panel').forEach(function (p) { p.classList.toggle('is-active', p.id === panelId); });
		root.querySelectorAll('.dsst-tab').forEach(function (t) {
			var on = t.getAttribute('aria-controls') === panelId;
			t.setAttribute('aria-selected', on ? 'true' : 'false');
			t.setAttribute('tabindex', on ? '0' : '-1');
		});
		var sel = root.querySelector('.dsst-select');
		if (sel) { sel.setAttribute('aria-controls', panelId); }
	}
	function init(root) {
		if (!root || root.dsstReady) { return; }
		root.dsstReady = true;
		var tabs = [].slice.call(root.querySelectorAll('.dsst-tab'));
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { show(root, t.getAttribute('aria-controls')); });
			t.addEventListener('keydown', function (e) {
				var n = null;
				if (e.key === 'ArrowRight') { n = (i + 1) % tabs.length; }
				if (e.key === 'ArrowLeft') { n = (i - 1 + tabs.length) % tabs.length; }
				if (e.key === 'Home') { n = 0; }
				if (e.key === 'End') { n = tabs.length - 1; }
				if (n === null) { return; }
				e.preventDefault();
				tabs[n].focus();
				show(root, tabs[n].getAttribute('aria-controls'));
			});
		});
		var sel = root.querySelector('.dsst-select');
		if (sel) {
			sel.value = (root.querySelector('.dsst-panel.is-active') || {}).id || sel.value;
			sel.addEventListener('change', function () { show(root, sel.value); });
		}
	}
	function boot() { document.querySelectorAll('[data-dsst]').forEach(init); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	// The builder re-renders a block after a settings change.
	if (window.jQuery) { window.jQuery(document).on('fl-builder.layout-rendered fl-builder.preview-rendered', boot); }
	window.DSStandings = { init: init, boot: boot };
})();
