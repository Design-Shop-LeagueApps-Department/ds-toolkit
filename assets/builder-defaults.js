/**
 * DS Toolkit — Builder UX default.
 *
 * Beaver Builder re-selects whatever settings tab was last used when you open a
 * node's settings. That means after editing a Style tab once, every module you
 * open lands on Style. A partner editing a module should always land on the
 * first (Content) tab, never Style. This forces the first tab active whenever a
 * settings form opens. Builder UI only.
 */
( function () {
	function ready() {
		return typeof window.FLBuilder !== 'undefined'
			&& window.FLBuilder
			&& typeof window.FLBuilder.addHook === 'function'
			&& typeof window.jQuery !== 'undefined';
	}

	function focusFirstTab() {
		var $ = window.jQuery;
		// The settings form BB just opened (module / row / column).
		var $form = $( '.fl-builder-settings:visible' ).first();
		if ( ! $form.length ) { return; }

		var $tabs = $form.find( '.fl-builder-settings-tabs a' );
		if ( $tabs.length < 2 ) { return; } // single-tab forms: nothing to do.

		var $first = $tabs.first();
		if ( ! $first.hasClass( 'fl-active' ) ) {
			// Click drives BB's own tab handler (shows the panel, fixes overflow).
			$first.trigger( 'click' );
		}
	}

	function boot() {
		if ( ! ready() ) { window.setTimeout( boot, 150 ); return; }
		window.FLBuilder.addHook( 'settings-form-init', function () {
			// Re-assert across a few frames so we win whenever BB restores the
			// last-used tab (it may set it a tick or two after init fires).
			[ 0, 60, 200 ].forEach( function ( d ) { window.setTimeout( focusFirstTab, d ); } );
		} );
	}

	boot();
} )();
