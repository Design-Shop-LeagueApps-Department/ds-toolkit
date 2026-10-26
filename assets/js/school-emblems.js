/* School Emblems picker (features/class-ds-school-emblems.php): the athlete's School Name box searches the Design
   Shop HQ library (name, short name, team or initials) and shows the logo that will be added on save.
   Matching and ranking are the same as HQ's /emblems/ page (mu-plugins/dshq-emblems/assets/emblems.js on HQ):
   change both together. All text goes in with textContent. */
(function () {
	'use strict';
	var cfg = window.dsSchoolEmblems;
	if (!cfg) { return; }
	var doc = null, loading = null, uid = 0;
	var WEAK = { and: 1, of: 1, the: 1, at: 1, in: 1, university: 1, college: 1, univ: 1, state: 1 };
	var TIER = { D1: 0, D2: 1, D3: 2, NAIA: 3, NJCAA: 4 };
	var DIV = { D1: 'NCAA D1', D2: 'NCAA D2', D3: 'NCAA D3' };

	function norm(s) {
		return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
			.replace(/['’`]/g, '').replace(/&/g, ' and ').replace(/\bst\.?\s/g, 'saint ').replace(/\bmt\.?\s/g, 'mount ')
			.replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim();
	}
	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (text != null) { e.textContent = text; }
		return e;
	}
	function load() {
		if (doc) { return Promise.resolve(doc); }
		if (!loading) {
			var body = new URLSearchParams({ action: cfg.action, _ajax_nonce: cfg.nonce });
			loading = fetch(cfg.ajax + '?' + body.toString(), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (!j || !j.success || !j.data || !j.data.schools) { throw new Error('index'); }
					doc = j.data.schools.map(function (s) {
						s._n = norm(s.n);
						s._k = ' ' + norm([s.n, s.s, s.k, s.st, s.c].concat(s.a || []).join(' '));
						s._xa = (s.a || []).concat([s.s]).filter(function (a) { return a && !/\s/.test(a.trim()); })
							.map(function (a) { return norm(a).replace(/ /g, ''); });
						s._x = (s.x || []).concat(s._xa);
						return s;
					});
					return doc;
				})
				.catch(function (e) { loading = null; throw e; });
		}
		return loading;
	}
	function search(q) {
		var phrase = norm(q), words = phrase.split(' ').filter(Boolean), bare = phrase.replace(/ /g, '');
		if (!phrase) { return []; }
		var strong = words.filter(function (w) { return w.length > 1 && !WEAK[w]; });
		var hits = doc.filter(function (s) {
			if (s._x.indexOf(bare) !== -1 || s._k.indexOf(' ' + phrase) !== -1) { return true; }
			if (!strong.length) { return false; }
			for (var i = 0; i < strong.length; i++) { if (s._k.indexOf(' ' + strong[i]) === -1) { return false; } }
			return true;
		});
		var rank = function (s) {
			if (s._xa.indexOf(bare) !== -1) { return 0; }
			if (s._x.indexOf(bare) !== -1) { return 1; }
			if (s._n.indexOf(phrase) === 0) { return 2; }
			return s._k.indexOf(' ' + phrase) !== -1 ? 3 : 4;
		};
		var tier = function (s) { return s.d in TIER ? TIER[s.d] : 5; };
		hits.sort(function (a, b) { return (rank(a) - rank(b)) || (tier(a) - tier(b)); });
		return hits.slice(0, 8);
	}
	function byId(id) {
		for (var i = 0; doc && i < doc.length; i++) { if (doc[i].id === id) { return doc[i]; } }
		return null;
	}
	function meta(s) { return [DIV[s.d] || s.d, s.st].filter(Boolean).join(' · '); }
	function logo(s, size) {
		var box = el('span', 'ds-school-logo' + (s.l ? ' is-dark' : ''));
		if (s.f) {
			var img = el('img');
			img.src = cfg.logos + encodeURIComponent(s.f);
			img.alt = '';
			img.width = size; img.height = size;
			img.loading = 'lazy';
			box.appendChild(img);
		}
		return box;
	}

	function setup(hidden) {
		var field = hidden.closest('.acf-field');
		var input = field && field.querySelector('.acf-input input[type="text"]');
		var chip = field && field.querySelector('.ds-school-chip');
		if (!input || !chip || input.dataset.dsSchool) { return; }
		input.dataset.dsSchool = '1';
		var ours = hidden.getAttribute('data-logo-ours') === '1';
		var id = 'ds-school-list-' + (++uid), list = el('ul', 'ds-school-list'), wrap = el('div', 'ds-school-pick');
		var live = el('div', 'screen-reader-text');
		var items = [], active = -1, picked = null, t;
		list.id = id;
		list.setAttribute('role', 'listbox');
		list.hidden = true;
		live.setAttribute('aria-live', 'polite');
		// Wrap ACF's own .acf-input-wrap, not the input: that div is overflow:hidden and clipped the whole list.
		var anchor = input.closest('.acf-input-wrap') || input;
		anchor.parentNode.insertBefore(wrap, anchor);
		wrap.appendChild(anchor);
		wrap.appendChild(list);
		wrap.appendChild(live);
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-controls', id);
		input.setAttribute('autocomplete', 'off');
		if (!input.placeholder) { input.placeholder = 'Type the school or its initials (UCLA, TAMU)'; }

		function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
		function mark(i) {
			active = i;
			items.forEach(function (li, j) { li.setAttribute('aria-selected', j === i ? 'true' : 'false'); });
			if (items[i]) {
				input.setAttribute('aria-activedescendant', items[i].id);
				// Scroll the list only: scrollIntoView() also scrolled the whole edit screen.
				var li = items[i];
				if (li.offsetTop < list.scrollTop) { list.scrollTop = li.offsetTop; }
				else if (li.offsetTop + li.offsetHeight > list.scrollTop + list.clientHeight) { list.scrollTop = li.offsetTop + li.offsetHeight - list.clientHeight; }
			}
		}
		function draw(hits) {
			list.textContent = '';
			items = [];
			if (!hits.length) {
				var none = el('li', 'ds-school-none', 'Not in the school list. Keep typing the name; you can upload the logo in School Logo.');
				none.setAttribute('aria-disabled', 'true');
				list.appendChild(none);
			}
			hits.forEach(function (s, i) {
				var li = el('li', 'ds-school-option'), text = el('span', 'ds-school-text');
				li.id = id + '-' + i;
				li.setAttribute('role', 'option');
				li.setAttribute('aria-selected', 'false');
				li.appendChild(logo(s, 32));
				text.appendChild(el('span', 'ds-school-name', s.n));
				text.appendChild(el('span', 'ds-school-meta', meta(s) + (s.f ? '' : ' · no logo yet')));
				li.appendChild(text);
				li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(s); });
				list.appendChild(li);
				items.push(li);
			});
			list.hidden = false;
			input.setAttribute('aria-expanded', 'true');
			live.textContent = hits.length ? hits.length + ' schools found. Use the arrow keys to choose.' : 'No school found.';
			active = -1;
		}
		function showChip() {
			chip.textContent = '';
			if (!picked) { chip.hidden = true; return; }
			var note = el('span', 'ds-school-note');
			if (!picked.f) {
				note.textContent = 'This school has no logo in the library yet. Upload one in School Logo.';
			} else if (!ours) {
				note.textContent = 'Your own School Logo is kept. Remove it, then Update, to use this logo instead.';
			} else {
				note.textContent = 'This logo goes in School Logo when you click Update.';
			}
			var clear = el('button', 'button-link ds-school-clear', 'Not this school');
			clear.type = 'button';
			clear.addEventListener('click', function () { picked = null; hidden.value = ''; showChip(); input.focus(); });
			if (picked.f) { chip.appendChild(logo(picked, 40)); }
			chip.appendChild(note);
			chip.appendChild(clear);
			chip.hidden = false;
		}
		function choose(s) {
			picked = s;
			input.value = s.n;
			hidden.value = s.id;
			close();
			showChip();
			live.textContent = s.n + ' chosen.';
		}
		function find() {
			if (!doc && input.value.trim()) { // typed before the list arrived: say so instead of showing nothing
				list.textContent = '';
				items = [];
				var wait = el('li', 'ds-school-none', 'Loading the school list…');
				wait.setAttribute('aria-disabled', 'true');
				list.appendChild(wait);
				list.hidden = false;
				input.setAttribute('aria-expanded', 'true');
			}
			load().then(function () {
				// Compare with the STORED pick too: the name can change before the list has loaded.
				var cur = picked || (hidden.value ? byId(hidden.value) : null);
				if (cur && input.value !== cur.n) { picked = null; hidden.value = ''; showChip(); }
				if (!input.value.trim()) { close(); return; }
				draw(search(input.value));
			}).catch(function () {
				live.textContent = 'The school list could not be loaded. Type the name; the logo can be added later.';
			});
		}

		// Start loading the list as soon as the screen is ready (it took ~8 s from page load on designshopdemo when it
		// waited for the first click), so it is there by the time a partner types.
		setTimeout(function () { load().catch(function () {}); }, 300);
		input.addEventListener('focus', function () { load().catch(function () {}); });
		input.addEventListener('input', function () { clearTimeout(t); t = setTimeout(find, 80); });
		input.addEventListener('blur', function () { setTimeout(close, 120); });
		input.addEventListener('keydown', function (e) {
			if (list.hidden || !items.length) { if (e.key === 'ArrowDown' && input.value.trim()) { find(); } return; }
			if (e.key === 'ArrowDown') { e.preventDefault(); mark(Math.min(items.length - 1, active + 1)); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); mark(Math.max(0, active - 1)); }
			else if (e.key === 'Enter' && active > -1) { e.preventDefault(); items[active].dispatchEvent(new MouseEvent('mousedown')); }
			else if (e.key === 'Escape') { e.preventDefault(); close(); }
		});
		if (hidden.value) {
			load().then(function () {
				var s = byId(hidden.value);
				if (s && input.value === s.n) { picked = s; showChip(); }
			}).catch(function () {});
		}
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('input.ds-school-id'), setup); }
	if (window.acf && window.acf.addAction) { window.acf.addAction('ready', boot); window.acf.addAction('append', boot); }
	else if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
	else { boot(); }
})();
