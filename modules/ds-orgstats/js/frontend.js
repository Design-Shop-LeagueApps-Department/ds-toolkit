/* Leagueapps Org Stats — count-up on scroll. Vanilla, idempotent.
 *
 * Numbers are rendered final by PHP (so content is correct with JS off / in
 * full-page screenshots). When the band has .ds-orgstats--count and the visitor
 * hasn't asked for reduced motion, each [data-count] figure resets to 0 and
 * eases up to its target the first time it scrolls into view. Re-runs after a
 * Beaver Builder partial refresh.
 */
(function () {
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function fmt(n) { return n >= 1000 ? n.toLocaleString('en-US') : String(n); }

	function animate(num, dur) {
		var valEl  = num.querySelector('.ds-orgstats-value');
		var target = parseInt(num.getAttribute('data-count'), 10);
		if (!valEl || isNaN(target)) return;
		var start = null;
		function step(ts) {
			if (start === null) start = ts;
			var p = Math.min(1, (ts - start) / dur);
			// easeOutCubic
			var eased = 1 - Math.pow(1 - p, 3);
			valEl.textContent = fmt(Math.round(eased * target));
			if (p < 1) requestAnimationFrame(step);
			else valEl.textContent = fmt(target);
		}
		requestAnimationFrame(step);
	}

	function setup(band) {
		if (band.dsOrgstatsInit) return;
		band.dsOrgstatsInit = true;

		var nums = band.querySelectorAll('.ds-orgstats-num[data-count]');
		if (!nums.length) return;

		// Count-up disabled, reduced motion, or no observer -> leave final values.
		if (reduce || band.className.indexOf('ds-orgstats--count') === -1 || !('IntersectionObserver' in window)) {
			return;
		}

		var durVar = parseInt(getComputedStyle(band).getPropertyValue('--ds-orgstats-dur'), 10);
		var dur    = isNaN(durVar) ? 1600 : durVar;

		nums.forEach(function (num) {
			var valEl = num.querySelector('.ds-orgstats-value');
			if (valEl) valEl.textContent = '0';
		});

		var io = new IntersectionObserver(function (entries, obs) {
			entries.forEach(function (e) {
				if (e.isIntersecting) { animate(e.target, dur); obs.unobserve(e.target); }
			});
		}, { threshold: 0.4 });

		nums.forEach(function (num) { io.observe(num); });
	}

	function boot(root) {
		(root || document).querySelectorAll('.ds-orgstats--style1').forEach(setup);
	}

	function init() {
		boot();
		if (window.jQuery) {
			jQuery(document).on('fl-builder.layout-rendered', function () { boot(); });
		}
	}

	if (document.readyState !== 'loading') init();
	else document.addEventListener('DOMContentLoaded', init);
})();
