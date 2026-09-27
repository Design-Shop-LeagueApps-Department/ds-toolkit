/* LeagueApps Device: moves between items (slide or fade), plays the active video and pauses the rest, and gives
   visitors control: a pause/play button whenever anything moves (WCAG 2.2.2), dots, and swipe. Autoplay waits while
   the pointer or focus is inside, stops while the device is off screen, and never starts for reduced-motion visitors
   (their videos show the poster until they press play). Vanilla, idempotent, re-run after a builder refresh. */
(function () {
	function init(el) {
		if (el.dsDeviceInit) return;
		el.dsDeviceInit = true;
		var items = Array.prototype.slice.call(el.querySelectorAll('.ds-device-item'));
		if (!items.length) return;
		var dots = Array.prototype.slice.call(el.querySelectorAll('.ds-device-dot'));
		var toggle = el.querySelector('.ds-device-toggle');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var auto = el.getAttribute('data-autoplay') === '1';
		var interval = Math.max(2, parseInt(el.getAttribute('data-interval'), 10) || 4) * 1000;
		var i = 0, timer = null, userPaused = reduce, held = false, visible = true, endedFn = null;

		function vid(n) { return items[n] && items[n].querySelector('video'); }
		function running() { return !userPaused && !held && visible; }
		function clear() {
			if (timer) { clearTimeout(timer); timer = null; }
			var v = vid(i);
			if (v && endedFn) v.removeEventListener('ended', endedFn);
			endedFn = null;
		}
		function playActive() {
			var v = vid(i);
			if (!v) return;
			// Hover and focus only hold the timer; the video keeps playing unless paused or off screen.
			if (!userPaused && visible) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
			else v.pause();
		}
		function schedule() {
			clear();
			if (!auto || items.length < 2 || !running()) return;
			var v = vid(i);
			if (v && items[i].getAttribute('data-advance') === 'end') {
				endedFn = function () { go(i + 1); };
				v.addEventListener('ended', endedFn);
			} else {
				timer = setTimeout(function () { go(i + 1); }, interval);
			}
		}
		function go(n) {
			if (items.length < 2) return;
			clear();
			var prev = i, v = vid(prev);
			if (v) v.pause();
			i = (n + items.length) % items.length;
			items.forEach(function (it, k) {
				it.classList.toggle('is-active', k === i);
				it.classList.toggle('is-prev', k === prev && k !== i);
				if (k === i) it.removeAttribute('aria-hidden'); else it.setAttribute('aria-hidden', 'true');
			});
			dots.forEach(function (d, k) {
				d.classList.toggle('is-active', k === i);
				if (k === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
			});
			v = vid(i);
			if (v) { try { v.currentTime = 0; } catch (e) {} }
			playActive();
			schedule();
		}
		function setPaused(p) {
			userPaused = p;
			el.classList.toggle('is-paused', p);
			if (toggle) toggle.setAttribute('aria-label', p ? 'Play' : 'Pause');
			playActive();
			schedule();
		}

		if (reduce) { items.forEach(function (it) { var v = it.querySelector('video'); if (v) { v.removeAttribute('autoplay'); v.pause(); v.loop = true; } }); }
		el.classList.toggle('is-paused', userPaused);
		if (toggle) {
			toggle.setAttribute('aria-label', userPaused ? 'Play' : 'Pause');
			toggle.addEventListener('click', function (e) { e.preventDefault(); setPaused(!userPaused); });
		}
		dots.forEach(function (d, k) { d.addEventListener('click', function (e) { e.preventDefault(); go(k); }); });

		// Hold autoplay while the visitor is reading or using it.
		el.addEventListener('mouseenter', function () { held = true; schedule(); });
		el.addEventListener('mouseleave', function () { held = false; schedule(); });
		el.addEventListener('focusin', function () { held = true; schedule(); });
		el.addEventListener('focusout', function (e) { if (!el.contains(e.relatedTarget)) { held = false; schedule(); } });

		// Swipe between items.
		var sx = null, sy = null;
		var screen = el.querySelector('.ds-device-screen');
		if (screen && items.length > 1) {
			// A mouse drag would otherwise start the browser's own image drag and never deliver pointerup.
			screen.querySelectorAll('img').forEach(function (im) { im.draggable = false; });
			screen.addEventListener('pointercancel', function () { sx = null; });
			screen.addEventListener('pointerdown', function (e) { sx = e.clientX; sy = e.clientY; });
			screen.addEventListener('pointerup', function (e) {
				if (sx === null) return;
				var dx = e.clientX - sx, dy = e.clientY - sy; sx = null;
				if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) go(dx < 0 ? i + 1 : i - 1);
			});
		}

		// Off screen: stop the timer and the video; back on screen: carry on.
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) {
				visible = en[0].isIntersecting;
				playActive(); schedule();
			}, { threshold: 0.15 }).observe(el);
		}

		playActive();
		schedule();
	}

	function boot(root) { (root || document).querySelectorAll('.ds-device').forEach(init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { boot(); });
	else boot();
	if (window.jQuery) window.jQuery(document).on('fl-builder.layout-rendered', function () { boot(); });
})();
