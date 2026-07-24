/* LeagueApps CTA — Motion Cards (Style 5) logo entrance trigger. Vanilla, idempotent. */
(function () {
	function boot(root) {
		(root || document).querySelectorAll('.ds-mcards').forEach(function (sec) {
			if (sec.dsMcInit) { return; }
			sec.dsMcInit = true;
			var logos = [].slice.call(sec.querySelectorAll('.ds-mcard-logo'));
			if (!logos.length) { return; }
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (reduce || !('IntersectionObserver' in window)) {
				logos.forEach(function (l) { l.classList.add('is-in'); });
				return;
			}
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) {
					if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
				});
			}, { threshold: 0.25 });
			logos.forEach(function (l) { io.observe(l); });
		});
	}
	if ('loading' !== document.readyState) { boot(); } else { document.addEventListener('DOMContentLoaded', function () { boot(); }); }
	if ('undefined' !== typeof jQuery) { jQuery(document).on('fl-builder.layout-rendered', function () { boot(); }); }
})();
