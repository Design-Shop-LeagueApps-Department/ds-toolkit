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

/* ---- Style 3: peek slider — centred active slide, neighbours peeking at the edges.
   Horizontal track (never a fade). Infinite looping clones the head and tail slide and
   snaps silently on transitionend, so the wrap never shows a jump. Vanilla, idempotent. ---- */
(function () {
	function num(v, d) { v = parseFloat(v); return isNaN(v) ? d : v; }

	function initPeek(root) {
		if (root.dsPeekInit) return;
		var vp    = root.querySelector('.ds-peek-viewport');
		var track = root.querySelector('.ds-peek-track');
		if (!vp || !track) { return; }        // not a style-3 hero
		var n = track.children.length;
		if (!n) { return; }
		root.dsPeekInit = true;

		var dots   = [].slice.call(root.querySelectorAll('.ds-peek-dot'));
		var play   = root.querySelector('.ds-peek-play');
		var live   = root.querySelector('.ds-peek-live');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var ds     = track.dataset;

		var loop     = ds.loop === 'yes' && n > 1;
		var autoplay = ds.autoplay === 'yes' && !reduce && n > 1;
		var interval = Math.max(1500, num(ds.interval, 7) * 1000);
		var pause    = ds.pause === 'yes';
		var resume   = ds.resume !== 'no';
		var drag     = ds.drag !== 'no' && n > 1;
		var speed    = Math.max(100, num(ds.speed, 600));
		var ease     = 'cubic-bezier(.22,.61,.36,1)';

		// Loop clones: the last slide before the first, the first after the last. They are
		// decorative duplicates, so they leave the a11y tree and the tab order.
		var offset = 0;
		if (loop) {
			var head = track.children[0].cloneNode(true);
			var tail = track.children[n - 1].cloneNode(true);
			[ head, tail ].forEach(function (c) {
				c.classList.add('ds-peek-clone');
				c.setAttribute('aria-hidden', 'true');
				c.removeAttribute('role');
				c.querySelectorAll('a,button,input,select,textarea,[tabindex]').forEach(function (f) {
					f.setAttribute('tabindex', '-1');
				});
			});
			track.insertBefore(tail, track.children[0]);
			track.appendChild(head);
			offset = 1;
		}

		// `active` is the logical slide index; while looping it sits transiently at
		// -1 or n (a clone) until transitionend snaps it back.
		var active = 0, timer = null, hovered = false, stopped = false;

		function cell() { return track.children[offset]; }
		function step() {
			var c   = cell();
			var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || '0') || 0;
			return c ? c.getBoundingClientRect().width + gap : 0;
		}
		function centreOffset() {
			var c = cell();
			return (vp.getBoundingClientRect().width - (c ? c.getBoundingClientRect().width : 0)) / 2;
		}
		function current() { return ((active % n) + n) % n; }

		function setX(animate) {
			var x = centreOffset() - (active + offset) * step();
			track.style.transition = (animate && !reduce) ? ('transform ' + speed + 'ms ' + ease) : 'none';
			track.style.transform  = 'translate3d(' + x + 'px,0,0)';
		}
		function syncDots() {
			var cur = current();
			for (var i = 0; i < dots.length; i++) {
				dots[i].classList.toggle('is-active', i === cur);
				dots[i].setAttribute('aria-current', i === cur ? 'true' : 'false');
			}
			for (var c = 0; c < track.children.length; c++) {
				track.children[c].classList.toggle('is-active', c === cur + offset);
			}
		}

		// Landed on a clone → re-seat on the matching real slide with no animation.
		track.addEventListener('transitionend', function (e) {
			if (e.target !== track || e.propertyName !== 'transform' || !loop) return;
			if (active < 0)      { active = n - 1; setX(false); }
			else if (active > n - 1) { active = 0; setX(false); }
		});

		function go(to)  { active = to; setX(true); syncDots(); }
		function next()  { if (!loop && active >= n - 1) return; go(active + 1); }
		function prev()  { if (!loop && active <= 0)     return; go(active - 1); }

		function stop()  { if (timer) { clearInterval(timer); timer = null; } syncPlay(); }
		function start() {
			if (!autoplay || stopped || hovered || document.hidden) { syncPlay(); return; }
			if (timer) clearInterval(timer);
			timer = setInterval(next, interval);
			syncPlay();
		}
		function syncPlay() {
			if (!play) return;
			play.classList.toggle('is-paused', !timer);
			play.setAttribute('aria-label', timer ? (play.dataset.pause || 'Pause') : (play.dataset.play || 'Play'));
		}
		// A manual move either restarts the timer or retires autoplay, per the setting.
		// It announces the new position; autoplay stays silent, so the
		// slider never talks over a screen reader on its own.
		function nudged() {
			if (resume) { start(); } else { stopped = true; stop(); }
			if (live) { live.textContent = (current() + 1) + ' / ' + n; }
		}

		root.querySelectorAll('.ds-peek-nav--next').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); next(); nudged(); }); });
		root.querySelectorAll('.ds-peek-nav--prev').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); prev(); nudged(); }); });
		dots.forEach(function (d, idx) { d.addEventListener('click', function (e) { e.preventDefault(); go(idx); nudged(); }); });

		if (play) {
			play.addEventListener('click', function (e) {
				e.preventDefault();
				if (timer) { stopped = true; stop(); }
				else       { stopped = false; start(); }
			});
		}

		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight')     { e.preventDefault(); next(); nudged(); }
			else if (e.key === 'ArrowLeft') { e.preventDefault(); prev(); nudged(); }
		});

		if (pause) {
			root.addEventListener('mouseenter', function () { hovered = true;  stop(); });
			root.addEventListener('mouseleave', function () { hovered = false; start(); });
		}
		// Keyboard focus pauses too, so a tabbing visitor is never moved mid-read.
		root.addEventListener('focusin',  function () { hovered = true; stop(); });
		root.addEventListener('focusout', function () {
			if (!root.contains(document.activeElement)) { hovered = false; start(); }
		});
		document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else { start(); } });

		// Drag / swipe. Unlike the flick-detect on the other carousels the track follows
		// the pointer, which is what a peek slider needs to read as direct manipulation.
		// The axis lock hands a vertical gesture straight back to the page.
		if (drag) {
			var x0 = 0, y0 = 0, baseX = 0, dx = 0, dragging = false, axis = null, pid = null;

			function release(delta) {
				if (!dragging) return;
				dragging = false; axis = null;
				track.classList.remove('is-dragging');
				if (pid !== null) { try { vp.releasePointerCapture(pid); } catch (err) {} pid = null; }
				var threshold = Math.min(120, step() * 0.18);
				if (delta <= -threshold)     { next(); }
				else if (delta >= threshold) { prev(); }
				else                         { setX(true); }
				nudged();
			}

			vp.addEventListener('pointerdown', function (e) {
				if (e.button) return;
				dragging = true; axis = null; dx = 0; pid = e.pointerId;
				x0 = e.clientX; y0 = e.clientY;
				baseX = centreOffset() - (active + offset) * step();
				stop();
			});
			vp.addEventListener('pointermove', function (e) {
				if (!dragging) return;
				var mx = e.clientX - x0, my = e.clientY - y0;
				if (axis === null) {
					if (Math.abs(mx) < 6 && Math.abs(my) < 6) return;
					axis = Math.abs(mx) > Math.abs(my) ? 'x' : 'y';
					if (axis === 'y') { release(0); return; }
					track.classList.add('is-dragging');
					try { vp.setPointerCapture(e.pointerId); } catch (err) {}
				}
				if (axis !== 'x') return;
				dx = mx;
				track.style.transition = 'none';
				track.style.transform  = 'translate3d(' + (baseX + mx) + 'px,0,0)';
			});
			window.addEventListener('pointerup',     function () { release(dx); });
			window.addEventListener('pointercancel', function () { release(0); });
			// A drag that finishes on a link must not also follow it.
			vp.addEventListener('click', function (e) {
				if (Math.abs(dx) > 5) { e.preventDefault(); e.stopPropagation(); dx = 0; }
			}, true);
			vp.addEventListener('dragstart', function (e) { e.preventDefault(); });
		}

		function relayout() { setX(false); syncDots(); }

		var rt = null;
		window.addEventListener('resize', function () {
			if (rt) clearTimeout(rt);
			rt = setTimeout(relayout, 150);
		});
		// The builder preview resizes the container without a window resize event.
		if (window.ResizeObserver) { new ResizeObserver(relayout).observe(vp); }
		if (document.fonts && document.fonts.ready) { document.fonts.ready.then(relayout).catch(function () {}); }

		setX(false);
		syncDots();
		start();
	}

	function boot(root) {
		(root || document).querySelectorAll('.ds-peek').forEach(initPeek);
	}

	if (document.readyState !== 'loading') boot();
	else document.addEventListener('DOMContentLoaded', function () { boot(); });

	if (window.jQuery) {
		jQuery(document).on('fl-builder.layout-rendered', function () { boot(); });
	}
})();
