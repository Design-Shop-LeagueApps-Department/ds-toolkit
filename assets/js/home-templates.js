/**
 * Theme Setting > Home page: preview, apply and revert home page templates.
 *
 * Preview loads the front page in the Theme Setting preview with ?ds_home_tpl=<id>, which
 * DS_Home_Templates renders with that template's layout for that request only. Apply and
 * Revert post to admin-ajax and swap in the section's new HTML. The section has no form
 * fields, so it never marks the Theme Setting form as changed.
 */
/* global dsHomeTpl */
(function () {
	'use strict';

	var cfg = window.dsHomeTpl || {};
	var box = document.getElementById('dsht');
	if (!box) { return; }

	function api() { return window.dsTsApi || null; }
	function withTpl(url, id) { return url + (url.indexOf('?') === -1 ? '?' : '&') + encodeURIComponent(cfg.query) + '=' + encodeURIComponent(id); }
	function say(msg, kind) {
		var n = box.querySelector('.dsht-msg');
		if (!n) { n = document.createElement('p'); n.className = 'dsht-msg'; n.setAttribute('role', 'status'); box.insertBefore(n, box.firstChild); }
		n.textContent = msg; n.setAttribute('data-kind', kind || '');
	}
	function post(action, data) {
		var body = new FormData();
		body.append('action', action); body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { throw new Error('Unexpected response (' + r.status + ')'); }); })
			.then(function (j) { if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || 'Something went wrong.'); } return j.data || {}; });
	}
	function busy(on) { Array.prototype.forEach.call(box.querySelectorAll('button'), function (b) { if (on) { b.dataset.was = b.disabled ? '1' : ''; b.disabled = true; } else { b.disabled = b.dataset.was === '1'; } }); }
	function previewing(id) {
		Array.prototype.forEach.call(box.querySelectorAll('.dsht-card'), function (c) { c.classList.toggle('is-previewing', id !== null && c.getAttribute('data-id') === String(id)); });
	}
	function showHome() { var a = api(); if (a && a.homeUrl) { a.load(a.homeUrl); } previewing(null); }

	box.addEventListener('click', function (e) {
		var b = e.target.closest('button'); if (!b || !box.contains(b)) { return; }
		var card = b.closest('.dsht-card'), id = card ? card.getAttribute('data-id') : '';
		var title = card ? (card.querySelector('.dsht-title') || {}).textContent : '';

		if (b.hasAttribute('data-dsht-preview')) {
			var a = api(); if (!a || !a.homeUrl) { say('The preview is not available on this page.', 'error'); return; }
			a.load(withTpl(a.homeUrl, id)); previewing(id);
			say('Previewing "' + title + '" on the home page. Nothing is saved until you Apply.');
			return;
		}
		if (b.hasAttribute('data-dsht-apply')) {
			if (!window.confirm('Replace the home page layout with "' + title + '"?\n\nThe hero keeps its heading, text, buttons and photos. The current layout is kept, so you can revert.')) { return; }
			busy(true); say('Applying "' + title + '"…', 'busy');
			post(cfg.apply, { template: id }).then(function (d) { box.innerHTML = d.html; say('"' + title + '" is now the home page layout.', 'ok'); showHome(); })
				.catch(function (err) { busy(false); say(err.message, 'error'); });
			return;
		}
		if (b.hasAttribute('data-dsht-revert')) {
			if (!window.confirm('Put the previous home page layout back?')) { return; }
			busy(true); say('Reverting…', 'busy');
			post(cfg.revert).then(function (d) { box.innerHTML = d.html; say('The previous home page is back.', 'ok'); showHome(); })
				.catch(function (err) { busy(false); say(err.message, 'error'); });
			return;
		}
		if (b.hasAttribute('data-dsht-launch')) {
			if (!window.confirm('Mark this site as launched? The home page picker disappears from Theme Setting for good (the templates stay, hidden from the partner).')) { return; }
			busy(true);
			post(cfg.launch).then(function () {
				var btn = document.querySelector('.dsts-rail-btn[data-section="home"]'); if (btn) { btn.remove(); }
				var sec = document.getElementById('dsts-sec-home'); if (sec) { sec.remove(); }
				var first = document.querySelector('.dsts-rail-btn'); if (first) { first.click(); }
			}).catch(function (err) { busy(false); say(err.message, 'error'); });
		}
	});
}());
