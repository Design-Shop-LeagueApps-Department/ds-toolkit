/**
 * DS Toolkit: easier multi-select lists in the Beaver Builder settings panel (builder only, never on the site).
 *
 * Beaver Builder draws a multi-select field as the browser's own list box. In it a normal click picks that
 * option and DROPS every other one, and the only way to untick is Cmd-click (Ctrl-click on Windows), which
 * nobody knows: a partner could switch the LeagueApps Programs "Seasons" from one season to another but
 * never get back to "nothing picked" (ClickUp 868mkp2ky). For DS Toolkit modules only, this makes a plain
 * click tick or untick the option it lands on, shows the list's options without a tiny scroll box, and adds
 * "N picked · Clear" under it.
 *
 * It changes nothing that is saved: the <select multiple> stays the field, so Beaver Builder saves exactly
 * what it always did (an empty selection saves as "", which the modules read as "all").
 */
(function ($) {
	'use strict';

	var FORM = 'form.fl-builder-settings[data-type^="ds-"]';

	function pickedText(n) {
		return n === 0 ? 'Nothing picked (all)' : (n === 1 ? '1 picked' : n + ' picked');
	}

	function refresh(sel) {
		var n = $(sel).find('option:selected').filter(function () { return this.value !== ''; }).length;
		var bar = $(sel).next('.ds-ms-bar');
		bar.find('.ds-ms-count').text(pickedText(n));
		bar.find('.ds-ms-clear').prop('disabled', n === 0);
	}

	function changed(sel) {
		refresh(sel);
		$(sel).trigger('change'); // Beaver Builder's own listener: preview refresh and the saved value
	}

	function enhance(form) {
		$(form).find('select[multiple]').each(function () {
			var sel = this;
			if (sel.getAttribute('data-ds-ms')) { return; }
			sel.setAttribute('data-ds-ms', '1');

			// Show the options instead of a ~3-line scroll box (up to 8 lines, then it scrolls).
			var real = $(sel).find('option').filter(function () { return this.value !== ''; }).length;
			sel.size = Math.max(3, Math.min(8, real || 3));
			sel.style.height = 'auto';

			// A plain click ticks or unticks the option under it. Shift / Cmd / Ctrl keep the browser's own behaviour.
			$(sel).on('mousedown.dsms', 'option', function (e) {
				if (e.shiftKey || e.metaKey || e.ctrlKey || e.button !== 0 || this.disabled || this.value === '') { return; }
				e.preventDefault();
				var top = sel.scrollTop;
				this.selected = !this.selected;
				sel.focus();
				sel.scrollTop = top; // keep the list where it was
				changed(sel);
			});
			$(sel).on('change.dsms', function () { refresh(sel); });

			var bar = $('<div class="ds-ms-bar" aria-live="polite"><span class="ds-ms-count"></span>' +
				'<button type="button" class="ds-ms-clear">Clear</button>' +
				'<span class="ds-ms-hint">Click to tick or untick</span></div>');
			bar.find('.ds-ms-clear').on('click', function () {
				$(sel).find('option').prop('selected', false);
				changed(sel);
				sel.focus();
			});
			$(sel).after(bar);
			refresh(sel);
		});
	}

	function style() {
		if (document.getElementById('ds-ms-style')) { return; }
		$('<style id="ds-ms-style">' +
			FORM + ' select[multiple]{width:100%;max-width:100%;padding:4px 0;}' +
			FORM + ' select[multiple] option{padding:5px 10px;cursor:pointer;}' +
			// Ticked options in WordPress blue, even when the list is not focused (a grey bar reads as "hovered").
			FORM + ' select[multiple] option:checked{background:#2271b1 linear-gradient(0deg,#2271b1 0,#2271b1 100%);color:#fff;font-weight:600;}' +
			'.ds-ms-bar{display:flex;flex-wrap:wrap;align-items:center;gap:4px 12px;margin-top:6px;font-size:12px;line-height:1.4;color:#50575e;}' +
			'.ds-ms-bar .ds-ms-count{font-weight:600;color:#1d2327;}' +
			// Scoped under the form so Beaver Builder's own panel button styles cannot repaint the link.
			FORM + ' .ds-ms-bar .ds-ms-clear{display:inline;width:auto;height:auto;min-height:0;margin:0;padding:2px 0;border:0;border-radius:0;box-shadow:none;background:none;color:#2271b1;font:inherit;font-weight:600;line-height:1.4;text-decoration:underline;text-transform:none;cursor:pointer;}' +
			FORM + ' .ds-ms-bar .ds-ms-clear:hover,' + FORM + ' .ds-ms-bar .ds-ms-clear:focus{background:none;color:#135e96;}' +
			FORM + ' .ds-ms-bar .ds-ms-clear:focus-visible{outline:2px solid #2271b1;outline-offset:2px;border-radius:2px;}' +
			FORM + ' .ds-ms-bar .ds-ms-clear[disabled]{color:#8c8f94;text-decoration:none;cursor:default;}' +
			'.ds-ms-bar .ds-ms-hint{margin-left:auto;color:#646970;}' +
		'</style>').appendTo('head');
	}

	function run() {
		style();
		$(FORM).each(function () { enhance(this); });
	}

	$(function () {
		if (window.FLBuilder && typeof FLBuilder.addHook === 'function') {
			FLBuilder.addHook('settings-form-init', run);
		}
		// Belt and braces: a settings form opened by any other route gets the same treatment.
		if (window.MutationObserver) {
			var queued = false; // the builder changes the page constantly: look at most once a frame
			new MutationObserver(function () {
				if (queued) { return; }
				queued = true;
				window.requestAnimationFrame(function () {
					queued = false;
					if ($(FORM).find('select[multiple]:not([data-ds-ms])').length) { run(); }
				});
			}).observe(document.body, { childList: true, subtree: true });
		}
	});
})(jQuery);
