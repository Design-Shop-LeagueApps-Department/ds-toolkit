/* Leagueapps Marquee — seamless-loop top-up. Vanilla, idempotent.
 *
 * The PHP renders two identical item groups inside .ds-marquee-track and the CSS
 * animates the track by -50% (exactly one group width) for a gapless loop. When
 * the item set is narrower than the bar, one group is shorter than the viewport
 * and a blank gap would scroll through. This clones the original group content
 * (equally into BOTH groups, so the two halves stay identical) until each group
 * is at least as wide as the viewport. Re-runs on resize and after a Beaver
 * Builder partial refresh.
 */
(function () {
	function fill(marquee) {
		var viewport = marquee.querySelector('.ds-marquee-viewport');
		var track    = marquee.querySelector('.ds-marquee-track');
		if (!viewport || !track) return;

		var groups = track.querySelectorAll('.ds-marquee-group');
		if (groups.length < 2) return;

		// Cache the original (single-copy) markup of a group once.
		if (!track.dsBaseHTML) {
			track.dsBaseHTML = groups[0].innerHTML;
		}

		var avail = viewport.clientWidth;
		if (!avail) return; // hidden / not laid out yet

		// Measure one base copy by resetting groups to a single copy first.
		groups.forEach(function (g) { g.innerHTML = track.dsBaseHTML; });
		var baseWidth = groups[0].scrollWidth;
		if (!baseWidth) return;

		var reps = Math.max(1, Math.ceil(avail / baseWidth));
		if (reps > 1) {
			var html = '';
			for (var i = 0; i < reps; i++) { html += track.dsBaseHTML; }
			groups.forEach(function (g) { g.innerHTML = html; });
		}
	}

	function boot(root) {
		(root || document).querySelectorAll('.ds-marquee--style1').forEach(fill);
	}

	function init() {
		boot();

		var t;
		window.addEventListener('resize', function () {
			clearTimeout(t);
			t = setTimeout(function () { boot(); }, 200);
		});

		// Re-run after Beaver Builder partial refresh.
		if (window.jQuery) {
			jQuery(document).on('fl-builder.layout-rendered', function () { boot(); });
		}
	}

	if (document.readyState !== 'loading') init();
	else document.addEventListener('DOMContentLoaded', init);
})();
