/* LeagueApps CTA: Motion Cards card-wide logo trigger. Vanilla, idempotent. */
(function () {
	function boot(root) {
		(root || document).querySelectorAll('.ds-mcards').forEach(function (sec) {
			if (sec.dsMcInit) { return; }
			sec.dsMcInit = true;
			sec.querySelectorAll('.ds-mcard').forEach(function (card) {
				if (!card.querySelector('.ds-mcard-logo')) { return; }
				var enterEvent = ('onpointerenter' in window) ? 'pointerenter' : 'mouseenter';
				var leaveEvent = ('onpointerleave' in window) ? 'pointerleave' : 'mouseleave';

				function activate() {
					card.classList.remove('is-logo-active');
					void card.offsetWidth;
					card.classList.add('is-logo-active');
				}

				function deactivate() {
					card.classList.remove('is-logo-active');
				}

				card.addEventListener(enterEvent, activate);
				card.addEventListener(leaveEvent, deactivate);
				card.addEventListener('focus', activate);
				card.addEventListener('blur', deactivate);
			});
		});
	}
	if ('loading' !== document.readyState) { boot(); } else { document.addEventListener('DOMContentLoaded', function () { boot(); }); }
	if ('undefined' !== typeof jQuery) { jQuery(document).on('fl-builder.layout-rendered', function () { boot(); }); }
})();
