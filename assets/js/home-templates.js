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
			if (b.hasAttribute('data-reapply') && !window.confirm('"' + title + '" is already this site\'s look. Apply it again (for example after editing the template)? The current version is kept for Revert.')) { return; }
			if (!b.hasAttribute('data-reapply') && !window.confirm('Apply "' + title + '"?\n\nThe home page, header, footer and design options change to this template\'s. The hero keeps its heading, text, buttons and photos. Everything now is kept, so Revert brings it all back.')) { return; }
			busy(true); say('Applying "' + title + '"…', 'busy');
			post(cfg.apply, { template: id }).then(function (d) { box.innerHTML = d.html; say('"' + title + '" is now this site\'s look.', 'ok'); showHome(); })
				.catch(function (err) { busy(false); say(err.message, 'error'); });
			return;
		}
		if (b.hasAttribute('data-dsht-revert')) {
			if (!window.confirm('Put the previous home page, header, footer and design back?')) { return; }
			busy(true); say('Reverting…', 'busy');
			post(cfg.revert).then(function (d) { box.innerHTML = d.html; say('The previous site look is back.', 'ok'); showHome(); })
				.catch(function (err) { busy(false); say(err.message, 'error'); });
			return;
		}
		if (b.hasAttribute('data-dsht-save')) {
			if (!window.confirm('Save the current site into "' + title + '"?\n\nIts home page, header, footer and design options are replaced with what this site has now.')) { return; }
			busy(true); say('Saving the site into "' + title + '"…', 'busy');
			post(cfg.save, { template: id }).then(function (d) { box.innerHTML = d.html; say('"' + title + '" now holds this site\'s home page, header, footer and design.', 'ok'); })
				.catch(function (err) { busy(false); say(err.message, 'error'); });
			return;
		}
		if (b.hasAttribute('data-dsht-save-new')) {
			var name = window.prompt('Name for the new template (for example "Home 3 · Split hero"):', '');
			if (!name || !name.trim()) { return; }
			busy(true); say('Saving the site as "' + name.trim() + '"…', 'busy');
			post(cfg.save, { name: name.trim() }).then(function (d) { box.innerHTML = d.html; say('"' + name.trim() + '" saved: this site\'s home page, header, footer and design. Give it a featured image in the Templates list for its card.', 'ok'); })
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
