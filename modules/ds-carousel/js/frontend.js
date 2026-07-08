/* Leagueapps Image Carousel — stacked-deck behaviour. Vanilla, idempotent. */
(function () {
	function num(v, d) { v = parseFloat(v); return isNaN(v) ? d : v; }

	function initCarousel(root) {
		if (root.dsCarInit) return;
		var deck = root.querySelector('.ds-carousel-deck');
		if (!deck) { root.dsCarInit = true; return; }
		var slides = [].slice.call(deck.querySelectorAll('.ds-carousel-slide'));
		var n = slides.length;
		if (!n) { root.dsCarInit = true; return; }
		root.dsCarInit = true;

		var dots   = [].slice.call(root.querySelectorAll('.ds-carousel-dot'));
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var ds     = deck.dataset;

		var visible  = Math.max(1, parseInt(ds.visible || '2', 10));
		var offx     = num(ds.offx, 40);
		var offy     = num(ds.offy, 0);
		var scale    = num(ds.scale, 8) / 100;
		var rot      = num(ds.rot, 0);
		var dir      = ds.dir || 'right';
		var loop     = ds.loop === 'yes';
		var autoplay = ds.autoplay === 'yes' && !reduce;
		var interval = Math.max(1500, num(ds.interval, 5) * 1000);
		var pause    = ds.pause === 'yes';
		var drag     = ds.drag !== 'no';

		var active = 0, timer = null;
		var sign = dir === 'left' ? -1 : 1;

		function layout() {
			for (var i = 0; i < n; i++) {
				var s   = slides[i];
				var rel = (i - active + n) % n;       // 0 = front, 1.. = behind
				var shown = rel <= visible;
				var tx = (dir === 'center' ? 0 : sign * rel * offx);
				var ty = rel * offy;
				var sc = Math.max(0.4, 1 - rel * scale);
				var rt = (dir === 'center' ? 0 : sign * rel * rot);
				s.style.transform    = 'translateX(calc(-50% + ' + tx + 'px)) translateY(' + ty + 'px) scale(' + sc + ') rotate(' + rt + 'deg)';
				s.style.opacity      = shown ? String(1 - rel * 0.18) : '0';
				s.style.zIndex       = String(100 - rel);
				s.style.pointerEvents = shown ? 'auto' : 'none';
				s.classList.toggle('is-front', rel === 0);
			}
			for (var d = 0; d < dots.length; d++) { dots[d].classList.toggle('is-active', d === active); }
		}

		function go(to)  { active = ((to % n) + n) % n; layout(); }
		function next()  { if (!loop && active >= n - 1) return; go(active + 1); }
		function prev()  { if (!loop && active <= 0) return; go(active - 1); }
		function stop()  { if (timer) { clearInterval(timer); timer = null; } }
		function start() { if (!autoplay || n < 2) return; stop(); timer = setInterval(next, interval); }

		root.querySelectorAll('.ds-carousel-nav--next').forEach(function (b) {
			b.addEventListener('click', function (e) { e.preventDefault(); next(); start(); });
		});
		root.querySelectorAll('.ds-carousel-nav--prev').forEach(function (b) {
			b.addEventListener('click', function (e) { e.preventDefault(); prev(); start(); });
		});
		dots.forEach(function (d, idx) {
			d.addEventListener('click', function (e) { e.preventDefault(); go(idx); start(); });
		});

		// Click a card behind to bring it forward. A front card with a link follows
		// its link; a front card with no link advances.
		slides.forEach(function (s, i) {
			s.addEventListener('click', function (e) {
				if (!s.classList.contains('is-front')) { e.preventDefault(); go(i); start(); }
				else if (!s.getAttribute('href'))      { e.preventDefault(); next(); start(); }
			});
		});

		if (pause) {
			root.addEventListener('mouseenter', stop);
			root.addEventListener('mouseleave', start);
		}

		if (drag) {
			var x0 = null, dragging = false;
			deck.addEventListener('pointerdown', function (e) { x0 = e.clientX; dragging = true; stop(); });
			window.addEventListener('pointerup', function (e) {
				if (!dragging) return;
				dragging = false;
				var dx = e.clientX - x0; x0 = null;
				if (Math.abs(dx) > 40) { (dx < 0 ? next : prev)(); }
				start();
			});
		}

		layout();
		start();
	}

	/* ---- Style 2: Sponsors / logos — multi-up track with seamless infinite loop ---- */
	function initLogos(root) {
		if (root.dsLogoInit) return;
		var track = root.querySelector('.ds-logos-track');
		var vp    = root.querySelector('.ds-logos-viewport');
		if (!track || !vp) return;            // not a style-2 carousel
		root.dsLogoInit = true;

		var originals = [].slice.call(track.children);
		var n = originals.length;
		if (!n) return;

		var dots   = [].slice.call(root.querySelectorAll('.ds-carousel-dot'));
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var ds     = track.dataset;
		var loop   = ds.loop === 'yes';
		var autoplay = ds.autoplay === 'yes' && !reduce;
		var interval = Math.max(1500, num(ds.interval, 4) * 1000);
		var pause  = ds.pause === 'yes';
		var drag   = ds.drag !== 'no';
		var speed  = Math.max(100, num(ds.speed, 600));
		var ease   = 'cubic-bezier(.22,.61,.36,1)';

		var cloned = false;
		// Clone the full set once for a seamless wrap (works for any per-view count).
		if (loop) {
			originals.forEach(function (el) { track.appendChild(el.cloneNode(true)); });
			cloned = true;
		}

		var active = 0, timer = null;

		function perView() {
			var first = track.children[0];
			if (!first) return 1;
			var iw = first.getBoundingClientRect().width;
			var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || '0') || 0;
			var vw = vp.getBoundingClientRect().width;
			if (iw <= 0) return 1;
			return Math.max(1, Math.round((vw + gap) / (iw + gap)));
		}
		function step() {
			var first = track.children[0];
			var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || '0') || 0;
			return first ? first.getBoundingClientRect().width + gap : 0;
		}
		function maxIndex() { return Math.max(0, n - perView()); }

		function setX(animate) {
			track.style.transition = animate ? ('transform ' + speed + 'ms ' + ease) : 'none';
			track.style.transform  = 'translateX(' + (-active * step()) + 'px)';
		}
		function syncDots() {
			var cur = ((active % n) + n) % n;
			for (var i = 0; i < dots.length; i++) { dots[i].classList.toggle('is-active', i === cur); }
		}
		function refreshStatic() {
			// All logos fit → no scrolling: centre + hide nav.
			root.classList.toggle('is-static', n <= perView());
		}

		function go(to) {
			if (loop) {
				active = to;
				setX(true);
				if (active >= n) {                       // entered the clone block → snap back invisibly
					window.setTimeout(function () { active -= n; setX(false); syncDots(); }, speed);
				}
			} else {
				active = Math.max(0, Math.min(maxIndex(), to));
				setX(true);
			}
			syncDots();
		}
		function next() { go(active + 1); }
		function prev() {
			if (loop) {
				if (active <= 0) {
					// Jump to the identical clone block (no anim), then animate one step left.
					active = n; setX(false);
					window.requestAnimationFrame(function () {
						window.requestAnimationFrame(function () { go(n - 1); });
					});
				} else {
					go(active - 1);
				}
			} else {
				if (active <= 0) return;
				go(active - 1);
			}
		}
		function stop()  { if (timer) { clearInterval(timer); timer = null; } }
		function start() { if (!autoplay || n <= perView()) return; stop(); timer = setInterval(next, interval); }

		root.querySelectorAll('.ds-carousel-nav--next').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); next(); start(); }); });
		root.querySelectorAll('.ds-carousel-nav--prev').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); prev(); start(); }); });
		dots.forEach(function (d, idx) { d.addEventListener('click', function (e) { e.preventDefault(); go(idx); start(); }); });

		if (pause) {
			root.addEventListener('mouseenter', stop);
			root.addEventListener('mouseleave', start);
		}
		if (drag) {
			var x0 = null, dragging = false;
			track.addEventListener('pointerdown', function (e) { x0 = e.clientX; dragging = true; stop(); });
			window.addEventListener('pointerup', function (e) {
				if (!dragging) return; dragging = false;
				var dx = e.clientX - x0; x0 = null;
				if (Math.abs(dx) > 40) { (dx < 0 ? next : prev)(); }
				start();
			});
		}

		var rt = null;
		window.addEventListener('resize', function () {
			if (rt) clearTimeout(rt);
			rt = setTimeout(function () {
				refreshStatic();
				if (!loop) active = Math.min(active, maxIndex());
				setX(false);
				syncDots();
			}, 150);
		});

		refreshStatic();
		setX(false);
		syncDots();
		start();
	}

	function boot(root) {
		(root || document).querySelectorAll('.ds-carousel').forEach(function (el) {
			initCarousel(el);
			initLogos(el);
		});
	}

	if (document.readyState !== 'loading') boot();
	else document.addEventListener('DOMContentLoaded', function () { boot(); });

	if (window.jQuery) {
		jQuery(document).on('fl-builder.layout-rendered', function () { boot(); });
	}
})();

/* ---- Style 3: reels strip (inline video + arrow scroll). Idempotent. ---- */
(function () {
	function initReels(root) {
		if (root.dsReelInit) return;
		var track = root.querySelector('.ds-reels-track');
		if (!track) { return; }
		root.dsReelInit = true;
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		[].slice.call(root.querySelectorAll('.ds-reel-card.has-video')).forEach(function (card) {
			var btn = card.querySelector('.ds-reel-play');
			function play() {
				if (card.dsPlaying) return;
				card.dsPlaying = true;
				// pause any other playing reel in this module
				[].slice.call(root.querySelectorAll('video.ds-reel-video')).forEach(function (o) { o.pause(); });
				var v = document.createElement('video');
				v.className = 'ds-reel-video';
				v.src = card.getAttribute('data-video') || '';
				v.controls = true; v.autoplay = true; v.playsInline = true;
				v.setAttribute('playsinline', '');
				card.appendChild(v);
				if (btn) btn.style.display = 'none';
				v.addEventListener('ended', function () { v.remove(); if (btn) btn.style.display = ''; card.dsPlaying = false; });
			}
			card.addEventListener('click', function (e) { e.preventDefault(); play(); });
		});

		function step(dir) {
			var card = track.querySelector('.ds-reel-card');
			var gap = parseFloat(getComputedStyle(track).gap) || 24;
			var w = card ? card.getBoundingClientRect().width + gap : 320;
			track.scrollBy({ left: dir * w, behavior: reduce ? 'auto' : 'smooth' });
		}
		[].slice.call(root.querySelectorAll('.ds-reel-nav--prev')).forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); step(-1); }); });
		[].slice.call(root.querySelectorAll('.ds-reel-nav--next')).forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); step(1); }); });
	}

	function bootReels(root) {
		(root || document).querySelectorAll('.ds-carousel--style3').forEach(initReels);
	}
	if (document.readyState !== 'loading') bootReels();
	else document.addEventListener('DOMContentLoaded', function () { bootReels(); });
	if (window.jQuery) {
		jQuery(document).on('fl-builder.layout-rendered', function () { bootReels(); });
	}
})();
