/* LeagueApps Standings (ds-toolkit): division tabs and the division dropdown. One init per block; safe to call twice.
   The table is printed by the server, so without this script the first division still shows. */
(function () {
	/* The page link: #standings=<league>/<division> (slugs of their names). Written when a visitor picks a division
	   or a league, read once on load, so a menu item or a shared link opens that league and division. */
	var HASH = 'standings=';
	function readHash() {
		var h = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
		if (h.indexOf(HASH) !== 0) { return null; }
		var parts = h.slice(HASH.length).split('/');
		return { league: parts[0] || '', div: parts[1] || '' };
	}
	function writeHash(root, divSlug) {
		var league = root.getAttribute('data-dsst-league-slug') || '';
		if (!league || !window.history || !window.history.replaceState) { return; }
		try {
			var u = new URL(window.location.href);
			u.hash = HASH + league + (divSlug ? '/' + divSlug : '');
			window.history.replaceState(window.history.state, '', u.toString());
		} catch (e) { /* an old browser keeps the address as it is */ }
	}
	function show(root, panelId, byVisitor) {
		var panel = null;
		root.querySelectorAll('.dsst-panel').forEach(function (p) {
			var on = p.id === panelId;
			p.classList.toggle('is-active', on);
			if (on) { panel = p; }
		});
		root.querySelectorAll('.dsst-tab').forEach(function (t) {
			var on = t.getAttribute('aria-controls') === panelId;
			t.setAttribute('aria-selected', on ? 'true' : 'false');
			t.setAttribute('tabindex', on ? '0' : '-1');
		});
		var sel = root.querySelector('.dsst-select');
		if (sel) {
			sel.setAttribute('aria-controls', panelId);
			sel.value = panelId;
		}
		if (byVisitor && panel) { writeHash(root, panel.getAttribute('data-slug')); }
	}
	/* A division by its slug (from the page link); false when this block has no such division. */
	function openDiv(root, slug) {
		var p = slug ? root.querySelector('.dsst-panel[data-slug="' + slug.replace(/[^a-z0-9_%-]/gi, '') + '"]') : null;
		if (!p) { return false; }
		show(root, p.id, false);
		return true;
	}
	/* "Auto": chips while they fit on one row, the Division dropdown when they would wrap (phones, long names).
	   Both are printed; this only picks one, so without the script the chips show. */
	function fit(root) {
		if (!root.classList.contains('dsst-mode-auto')) { return; }
		root.classList.remove('is-menu');
		var tabs = root.querySelectorAll('.dsst-tab');
		if (tabs.length > 1 && tabs[tabs.length - 1].offsetTop > tabs[0].offsetTop + 2) {
			root.classList.add('is-menu');
		}
	}
	/* Find a team (divisions over 15 teams): hides the rows that do not match; ranks are printed, so each team keeps its
	   real position. Matching is on the same normalised name the server writes in data-team. */
	function norm(v) { return String(v || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }
	function finder(root) {
		root.querySelectorAll('[data-dsst-find]').forEach(function (input) {
			if (input.dsstReady) { return; }
			input.dsstReady = true;
			var panel = input.closest('.dsst-panel');
			var none = panel && panel.querySelector('.dsst-nomatch');
			input.addEventListener('input', function () {
				var q = norm(input.value);
				var shown = 0;
				panel.querySelectorAll('.dsst-table tbody tr').forEach(function (tr) {
					var hit = !q || (tr.getAttribute('data-team') || '').indexOf(q) > -1;
					tr.hidden = !hit;
					if (hit) { shown++; }
				});
				if (none) { none.hidden = shown > 0; }
			});
		});
	}
	function init(root) {
		if (!root || root.dsstReady) { return; }
		root.dsstReady = true;
		var tabs = [].slice.call(root.querySelectorAll('.dsst-tab'));
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { show(root, t.getAttribute('aria-controls'), true); });
			t.addEventListener('keydown', function (e) {
				var n = null;
				if (e.key === 'ArrowRight') { n = (i + 1) % tabs.length; }
				if (e.key === 'ArrowLeft') { n = (i - 1 + tabs.length) % tabs.length; }
				if (e.key === 'Home') { n = 0; }
				if (e.key === 'End') { n = tabs.length - 1; }
				if (n === null) { return; }
				e.preventDefault();
				tabs[n].focus();
				show(root, tabs[n].getAttribute('aria-controls'), true);
			});
		});
		var sel = root.querySelector('.dsst-select');
		if (sel) {
			sel.value = (root.querySelector('.dsst-panel.is-active') || {}).id || sel.value;
			sel.addEventListener('change', function () { show(root, sel.value, true); });
		}
		fit(root);
		finder(root);
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
	/* Leader cards carousel: the header arrows step one view at a time, hide when every card fits, and a focused arrow
	   that reaches its end hands focus to the other one so the keyboard never drops to the page (as the Roster block). */
	function carousel(root) {
		var track = root.querySelector('[data-dsst-car]');
		var bar = root.querySelector('.dsst-car-nav');
		if (!track || !bar || track.dsstCar) { return; }
		track.dsstCar = true;
		var prev = bar.querySelector('.dsst-prev');
		var next = bar.querySelector('.dsst-next');
		var rtl = getComputedStyle(track).direction === 'rtl'; // scrollLeft runs from 0 to negative there
		function update() {
			var over = track.scrollWidth > track.clientWidth + 2;
			bar.hidden = !over;
			var pos = Math.abs(track.scrollLeft);
			var atStart = pos <= 2;
			var atEnd = pos + track.clientWidth >= track.scrollWidth - 2;
			if (atStart && document.activeElement === prev && !atEnd) { next.focus(); }
			if (atEnd && document.activeElement === next && !atStart) { prev.focus(); }
			prev.disabled = atStart;
			next.disabled = atEnd;
		}
		function step(dir) {
			var gap = parseFloat(getComputedStyle(track).columnGap) || 14;
			track.scrollBy({ left: (rtl ? -dir : dir) * (track.clientWidth + gap), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
		}
		prev.addEventListener('click', function () { step(-1); });
		next.addEventListener('click', function () { step(1); });
		track.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		update();
	}
	/* League dropdown: the picked league's block comes from the REST route at data-dsst-src and replaces this one
	   (same node, so its colours and settings carry over), the dropdown keeps focus, and the address gets
	   ?dsst_league= so a reload or a shared link opens that league. Without this script the dropdown is a GET form. */
	function league(root) {
		var form = root.querySelector('[data-dsst-league]');
		var src = root.getAttribute('data-dsst-src');
		if (!form || !src || form.dsstReady || !window.fetch) { return; }
		form.dsstReady = true;
		var sel = form.querySelector('select');
		var meta = form.querySelector('.dsst-league-meta');
		var was = sel.value;
		root.dsstLoad = function (key, divSlug) { sel.value = key; load(divSlug); };
		function load(divSlug) {
			var key = sel.value;
			if (key === was || root.classList.contains('is-loading')) { return; }
			root.classList.add('is-loading');
			root.setAttribute('aria-busy', 'true');
			fetch(src + (src.indexOf('?') > -1 ? '&' : '?') + 'league=' + encodeURIComponent(key), { headers: { Accept: 'application/json' } })
				.then(function (r) { if (!r.ok) { throw new Error(String(r.status)); } return r.json(); })
				.then(function (j) {
					var box = document.createElement('div');
					box.innerHTML = j && typeof j.html === 'string' ? j.html : '';
					var fresh = box.querySelector('.dsst');
					if (!fresh) { throw new Error('empty'); }
					root.parentNode.replaceChild(fresh, root);
					boot();
					var opened = typeof divSlug === 'string' && openDiv(fresh, divSlug);
					var next = fresh.querySelector('[data-dsst-league] select');
					if (next && typeof divSlug !== 'string') { next.focus({ preventScroll: true }); }
					var active = fresh.querySelector('.dsst-panel.is-active');
					writeHash(fresh, opened ? divSlug : (active ? active.getAttribute('data-slug') : ''));
					try {
						var u = new URL(window.location.href);
						u.searchParams.set('dsst_league', key);
						window.history.replaceState(window.history.state, '', u.toString());
					} catch (e) { /* an old browser keeps the address as it is */ }
				})
				.catch(function () {
					root.classList.remove('is-loading');
					root.removeAttribute('aria-busy');
					sel.value = was;
					if (meta) { meta.textContent = meta.getAttribute('data-failed') || 'That league could not load. Please try again.'; }
				});
		}
		sel.addEventListener('change', function () { load(); });
		form.addEventListener('submit', function (e) { e.preventDefault(); load(); });
	}
	/* Once per page load: open the league and division the page link names, in the first block that has them. */
	var linkDone = false;
	function fromLink() {
		if (linkDone) { return; }
		linkDone = true;
		var want = readHash();
		if (!want || !want.league) { return; }
		var roots = [].slice.call(document.querySelectorAll('[data-dsst]'));
		for (var i = 0; i < roots.length; i++) {
			if (roots[i].getAttribute('data-dsst-league-slug') === want.league) {
				openDiv(roots[i], want.div);
				return;
			}
		}
		for (var j = 0; j < roots.length; j++) {
			var opt = roots[j].querySelector('.dsst-league-select option[data-slug="' + want.league.replace(/[^a-z0-9_%-]/gi, '') + '"]');
			if (opt && roots[j].dsstLoad) {
				roots[j].dsstLoad(opt.value, want.div);
				return;
			}
		}
	}
	function boot() {
		document.querySelectorAll('[data-dsst]').forEach(init);
		document.querySelectorAll('[data-dsst]').forEach(league);
		document.querySelectorAll('[data-dsst-ticker]').forEach(ticker);
		document.querySelectorAll('.dsst-carousel').forEach(carousel);
		fromLink();
	}
	var resizeT;
	window.addEventListener('resize', function () {
		clearTimeout(resizeT);
		resizeT = setTimeout(function () {
			document.querySelectorAll('[data-dsst-ticker]').forEach(fill);
			document.querySelectorAll('.dsst-mode-auto').forEach(fit);
		}, 200);
	});
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	// The builder re-renders a block after a settings change.
	if (window.jQuery) { window.jQuery(document).on('fl-builder.layout-rendered fl-builder.preview-rendered', boot); }
	window.DSStandings = { init: init, boot: boot, fit: fit };
})();
