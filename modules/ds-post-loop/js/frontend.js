/* Leagueapps Post Loop — Carousel + Pagination display drivers. Vanilla, idempotent. */
(function () {
	function num(v, d) { v = parseFloat(v); return isNaN(v) ? d : v; }

	/* ---------------------------------------------------------------- Carousel */
	function initCarousel(root) {
		if (root.dsLoopInit) return;
		var track = root.querySelector('.ds-loopcar-track');
		var vp    = root.querySelector('.ds-loopcar-viewport');
		if (!track || !vp) return;
		root.dsLoopInit = true;

		var originals = [].slice.call(track.children);
		var n = originals.length;
		if (!n) return;

		var reduce   = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var ds       = track.dataset;
		var loop     = ds.loop === 'yes';
		var autoplay = ds.autoplay === 'yes' && !reduce;
		var interval = Math.max(1500, num(ds.interval, 5) * 1000);
		var pause    = ds.pause === 'yes';
		var drag     = ds.drag !== 'no';
		var speed    = Math.max(100, num(ds.speed, 500));
		var ease     = 'cubic-bezier(.22,.61,.36,1)';

		if (loop) { originals.forEach(function (el) { track.appendChild(el.cloneNode(true)); }); }

		var dots = root.querySelector('.ds-loopcar-dots');
		var dotEls = [];
		var active = 0, timer = null;

		function gap()  { return parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || '0') || 0; }
		function step() { var f = track.children[0]; return f ? f.getBoundingClientRect().width + gap() : 0; }
		function perView() {
			var f = track.children[0]; if (!f) return 1;
			var iw = f.getBoundingClientRect().width; if (iw <= 0) return 1;
			return Math.max(1, Math.round((vp.getBoundingClientRect().width + gap()) / (iw + gap())));
		}
		function maxIndex() { return Math.max(0, n - perView()); }

		function buildDots() {
			if (!dots) return;
			dots.innerHTML = '';
			dotEls = [];
			for (var i = 0; i < n; i++) {
				var b = document.createElement('button');
				b.type = 'button'; b.className = 'ds-loopcar-dot' + (i === 0 ? ' is-active' : '');
				b.setAttribute('aria-label', 'Go to item ' + (i + 1));
				(function (idx) { b.addEventListener('click', function () { go(idx); start(); }); })(i);
				dots.appendChild(b); dotEls.push(b);
			}
		}
		function syncDots() {
			var cur = ((active % n) + n) % n;
			for (var i = 0; i < dotEls.length; i++) { dotEls[i].classList.toggle('is-active', i === cur); }
		}
		function setX(animate) {
			track.style.transition = animate ? ('transform ' + speed + 'ms ' + ease) : 'none';
			track.style.transform  = 'translateX(' + (-active * step()) + 'px)';
		}
		function refreshStatic() { root.classList.toggle('is-static', n <= perView()); }

		function go(to) {
			if (loop) {
				active = to; setX(true);
				if (active >= n)      { window.setTimeout(function () { active -= n; setX(false); }, speed); }
				else if (active < 0)  { active += n; setX(false); }
			} else {
				active = Math.max(0, Math.min(maxIndex(), to)); setX(true);
			}
			syncDots();
		}
		function next() { go(active + 1); }
		function prev() { if (!loop && active <= 0) return; go(active - 1); }
		function stop()  { if (timer) { clearInterval(timer); timer = null; } }
		function start() { if (!autoplay || n <= perView()) return; stop(); timer = setInterval(next, interval); }

		root.querySelectorAll('.ds-loopcar-nav--next').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); next(); start(); }); });
		root.querySelectorAll('.ds-loopcar-nav--prev').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); prev(); start(); }); });

		if (pause) { root.addEventListener('mouseenter', stop); root.addEventListener('mouseleave', start); }
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

		buildDots(); refreshStatic(); setX(false); start();
		var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { refreshStatic(); setX(false); }, 150); });
	}

	/* -------------------------------------------------------------- Pagination */
	function initPagination(root) {
		if (root.dsPageInit) return;
		var grid = root.querySelector(':scope > div:not(.ds-looppage-nav)');
		var nav  = root.querySelector('.ds-looppage-nav');
		if (!grid || !nav) return;
		root.dsPageInit = true;

		var items = [].slice.call(grid.children);
		var n = items.length;
		var per = Math.max(1, parseInt(root.dataset.perpage || '6', 10));
		var type = root.dataset.pagtype || 'numbers';
		var pages = Math.ceil(n / per);
		if (pages <= 1) { return; }

		function showPage(p) {
			for (var i = 0; i < n; i++) { items[i].classList.toggle('ds-looppage-hidden', i < (p - 1) * per || i >= p * per); }
		}
		function showUpTo(p) {
			for (var i = 0; i < n; i++) { items[i].classList.toggle('ds-looppage-hidden', i >= p * per); }
		}

		if (type === 'loadmore') {
			var shown = 1;
			showUpTo(shown);
			var btn = document.createElement('button');
			btn.type = 'button'; btn.className = 'ds-looppage-loadmore'; btn.textContent = 'Load More';
			btn.addEventListener('click', function () { shown++; showUpTo(shown); if (shown >= pages) { btn.style.display = 'none'; } });
			nav.appendChild(btn);
		} else {
			showPage(1);
			var btns = [];
			for (var p = 1; p <= pages; p++) {
				var b = document.createElement('button');
				b.type = 'button'; b.className = 'ds-looppage-page' + (p === 1 ? ' is-active' : '');
				b.textContent = p; b.setAttribute('aria-label', 'Page ' + p);
				(function (pg, el) {
					el.addEventListener('click', function () {
						showPage(pg);
						for (var i = 0; i < btns.length; i++) { btns[i].classList.toggle('is-active', btns[i] === el); }
						root.scrollIntoView({ behavior: 'smooth', block: 'start' });
					});
				})(p, b);
				nav.appendChild(b); btns.push(b);
			}
		}
	}

	/* ------------------------------------------------- Tournament filter bar */
	function initTournFilter(root) {
		if (root.dsTournInit) return;
		var bar  = root.querySelector('.ds-tourn-filter');
		var grid = root.querySelector('.ds-tourn-grid');
		if (!bar || !grid) return;
		root.dsTournInit = true;

		var cards = [].slice.call(grid.querySelectorAll('.ds-tourn-card'));
		var tabs  = [].slice.call(bar.querySelectorAll('.ds-tourn-tab'));
		var sel   = bar.querySelector('.ds-tourn-state select');
		var inp   = bar.querySelector('.ds-tourn-search input');
		var count = bar.querySelector('.ds-tourn-count');
		var none  = root.querySelector('.ds-tourn-none');
		var facet = '', state = '', q = '';

		function apply() {
			var shown = 0;
			cards.forEach(function (c) {
				var ok = true;
				if (facet && (' ' + (c.dataset.tabs || '') + ' ').indexOf(' ' + facet + ' ') === -1) ok = false;
				if (ok && state && (c.dataset.state || '') !== state) ok = false;
				if (ok && q && (c.dataset.q || '').indexOf(q) === -1) ok = false;
				c.style.display = ok ? '' : 'none';
				if (ok) shown++;
			});
			if (count) count.textContent = shown + ' ' + (shown === 1 ? 'event' : 'events');
			if (none) none.hidden = shown !== 0;
		}

		tabs.forEach(function (t) {
			t.addEventListener('click', function () {
				tabs.forEach(function (x) { x.classList.remove('is-active'); });
				t.classList.add('is-active');
				facet = t.dataset.tab || '';
				apply();
			});
		});
		if (sel) sel.addEventListener('change', function () { state = sel.value; apply(); });
		if (inp) {
			var deb;
			inp.addEventListener('input', function () {
				clearTimeout(deb);
				deb = setTimeout(function () { q = inp.value.trim().toLowerCase(); apply(); }, 150);
			});
		}
		apply();
	}

	function boot(rt) {
		(rt || document).querySelectorAll('.ds-loopcar').forEach(initCarousel);
		(rt || document).querySelectorAll('.ds-looppage').forEach(initPagination);
		(rt || document).querySelectorAll('.ds-tourn--filter').forEach(initTournFilter);
	}
	if (document.readyState !== 'loading') boot();
	else document.addEventListener('DOMContentLoaded', function () { boot(); });
	if (window.jQuery) { jQuery(document).on('fl-builder.layout-rendered', function () { boot(); }); }
})();
