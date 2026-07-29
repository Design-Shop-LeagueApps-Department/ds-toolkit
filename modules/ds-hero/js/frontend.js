/* Leagueapps Hero Banner — slideshow cross-fade + nav (arrows/dots). Handles image
   AND video slides (Mixed Media): the active slide's video plays from the start,
   inactive videos pause, and a play-until-end video advances on its `ended` event
   (with the progress bar tracking the video length). Vanilla, idempotent. */
(function () {
	function initHero(hero) {
		if (hero.dsHeroInit) return;
		hero.dsHeroInit = true;
		var slides = hero.querySelectorAll('.ds-hero-slide');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		// Reduced motion = no auto-advance, so a play-until-end video would freeze
		// on its last frame; let every slide video loop in place instead.
		if (reduce) {
			hero.querySelectorAll('.ds-hero-slide video').forEach(function (v) { v.loop = true; });
		}
		if (slides.length < 2) return;

		var dots   = hero.querySelectorAll('.ds-hero-dot');
		var bars   = hero.querySelectorAll('.ds-hero-bar');
		var interval = parseInt(hero.getAttribute('data-interval'), 10);
		if (!interval || interval < 2) interval = 6;

		var i = 0, timer = null, endedVid = null, onEnded = null;

		function vid(idx) { return slides[idx].querySelector('video'); }

		// Restart the cooldown fill animation on the newly-active bar.
		// dur (seconds) overrides the CSS interval duration (video slides).
		function armBar(idx, dur) {
			if (!bars[idx]) return;
			var fill = bars[idx].querySelector('.ds-hero-bar-fill');
			if (!fill) return;
			fill.style.animation = 'none';
			void fill.offsetWidth; // reflow so the animation re-triggers
			fill.style.animation = '';
			fill.style.animationDuration = dur ? dur + 's' : '';
		}

		function clearPending() {
			if (timer) { clearTimeout(timer); timer = null; }
			if (endedVid && onEnded) { endedVid.removeEventListener('ended', onEnded); }
			endedVid = null; onEnded = null;
		}

		// Queue the advance to the next slide: a play-until-end video advances on
		// `ended`; everything else advances after the slide interval.
		function schedule() {
			clearPending();
			if (reduce) return;
			var v = vid(i);
			if (v && slides[i].getAttribute('data-advance') === 'end') {
				endedVid = v;
				onEnded = function () { show(i + 1); };
				v.addEventListener('ended', onEnded);
				if (v.duration && isFinite(v.duration)) {
					armBar(i, v.duration);
				} else {
					v.addEventListener('loadedmetadata', function lm() {
						v.removeEventListener('loadedmetadata', lm);
						if (endedVid === v && v.duration && isFinite(v.duration)) armBar(i, v.duration);
					});
				}
			} else {
				timer = setTimeout(function () { show(i + 1); }, interval * 1000);
			}
		}

		function show(n) {
			clearPending();
			var v = vid(i);
			if (v) v.pause();
			slides[i].classList.remove('is-active');
			if (dots[i]) dots[i].classList.remove('is-active');
			if (bars[i]) bars[i].classList.remove('is-active');
			i = (n + slides.length) % slides.length;
			slides[i].classList.add('is-active');
			if (dots[i]) dots[i].classList.add('is-active');
			if (bars[i]) { bars[i].classList.add('is-active'); armBar(i); }
			v = vid(i);
			if (v) {
				try { v.currentTime = 0; } catch (e) {}
				var p = v.play();
				if (p && p.catch) p.catch(function () {});
			}
			schedule();
		}

		hero.querySelectorAll('.ds-hero-nav--next').forEach(function (b) {
			b.addEventListener('click', function (e) { e.preventDefault(); show(i + 1); });
		});
		hero.querySelectorAll('.ds-hero-nav--prev').forEach(function (b) {
			b.addEventListener('click', function (e) { e.preventDefault(); show(i - 1); });
		});
		dots.forEach(function (d, idx) {
			d.addEventListener('click', function (e) { e.preventDefault(); show(idx); });
		});
		bars.forEach(function (b, idx) {
			b.addEventListener('click', function (e) { e.preventDefault(); show(idx); });
		});

		// Sync the first bar's cooldown with autoplay on load.
		if (!reduce) armBar(0);

		schedule();
	}

	// Parallax: photo banners with data-parallax move their background slower than
	// the page on scroll. Bound once globally; queries live elements each frame so
	// builder-rendered heroes are covered. Skipped for reduced-motion visitors.
	function initParallax() {
		if (window.__dsHeroPx) return;
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
		window.__dsHeroPx = true;
		var ticking = false;
		function update() {
			var els = document.querySelectorAll('.ds-hero[data-parallax] .ds-hero-bg');
			var vh = window.innerHeight || document.documentElement.clientHeight;
			els.forEach(function (bg) {
				var hero = bg.closest('.ds-hero');
				if (!hero) return;
				var r = hero.getBoundingClientRect();
				if (r.bottom < 0 || r.top > vh) return;
				var prog = (r.top + r.height / 2 - vh / 2) / (vh / 2 + r.height / 2);
				if (prog > 1) prog = 1; else if (prog < -1) prog = -1;
				var shift = -prog * (r.height * 0.12);
				bg.style.transform = 'translate3d(0,' + shift.toFixed(1) + 'px,0)';
			});
			ticking = false;
		}
		function onScroll() {
			if (!ticking) { ticking = true; (window.requestAnimationFrame || function (f) { f(); })(update); }
		}
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		update();
	}

	function boot(root) {
		(root || document).querySelectorAll('.ds-hero').forEach(initHero);
		initParallax();
	}

	if (document.readyState !== 'loading') boot();
	else document.addEventListener('DOMContentLoaded', function () { boot(); });

	if (window.jQuery) {
		jQuery(document).on('fl-builder.layout-rendered', function () { boot(); });
	}
})();
