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
	/* Ticker: like the Marquee block, two identical groups scroll by -50%. When one group is narrower than the bar a gap
	   would scroll through, so both are topped up with copies; the loop time follows the group width at the chosen speed. */
	function fill(root) {
		var viewport = root.querySelector('.dsst-tk-viewport');
		var track = root.querySelector('.dsst-tk-track');
		if (!viewport || !track) { return; }
		var groups = track.querySelectorAll('.dsst-tk-group');
		if (groups.length < 2) { return; }
		if (!track.dsstBase) { track.dsstBase = groups[0].innerHTML; }
		var avail = viewport.clientWidth;
		if (!avail) { return; }
		groups.forEach(function (g) { g.innerHTML = track.dsstBase; });
		var base = groups[0].scrollWidth;
		if (!base) { return; }
		var reps = Math.max(1, Math.ceil(avail / base));
		if (reps > 1) {
			var html = '';
			for (var i = 0; i < reps; i++) { html += track.dsstBase; }
			groups.forEach(function (g) { g.innerHTML = html; });
		}
		var speed = parseInt(root.getAttribute('data-speed'), 10) || 50;
		root.style.setProperty('--dsst-tk-dur', Math.max(8, groups[0].scrollWidth / speed).toFixed(1) + 's');
	}
	function ticker(root) {
		fill(root);
		if (root.dsstTicker) { return; }
		root.dsstTicker = true;
		var btn = root.querySelector('.dsst-tk-pause');
		if (btn) {
			btn.addEventListener('click', function () {
				var paused = root.classList.toggle('is-paused');
				btn.setAttribute('aria-pressed', paused ? 'true' : 'false');
				btn.setAttribute('aria-label', paused ? 'Play the standings ticker' : 'Pause the standings ticker');
			});
		}
	}
	function boot() {
		document.querySelectorAll('[data-dsst]').forEach(init);
		document.querySelectorAll('[data-dsst-ticker]').forEach(ticker);
	}
	var resizeT;
	window.addEventListener('resize', function () { clearTimeout(resizeT); resizeT = setTimeout(function () { document.querySelectorAll('[data-dsst-ticker]').forEach(fill); }, 200); });
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	// The builder re-renders a block after a settings change.
	if (window.jQuery) { window.jQuery(document).on('fl-builder.layout-rendered fl-builder.preview-rendered', boot); }
	window.DSStandings = { init: init, boot: boot };
})();
