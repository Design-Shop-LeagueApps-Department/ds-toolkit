/* LeagueApps Menu — hamburger overlay + smooth mobile submenu accordion */
(function () {
	// The direct child .ds-submenu / .ds-mega panel of a menu item (if any).
	function panelOf( li ) {
		for ( var i = 0; i < li.children.length; i++ ) {
			var c = li.children[ i ];
			if ( c.classList && ( c.classList.contains( 'ds-submenu' ) || c.classList.contains( 'ds-mega' ) ) ) { return c; }
		}
		return null;
	}

	// Animate an accordion panel open/closed by transitioning height (0 <-> content).
	function slide( el, open ) {
		if ( ! el ) { return; }
		if ( open ) {
			el.style.height = 'auto';
			var h = el.scrollHeight;
			el.style.height = '0px';
			void el.offsetHeight; // force reflow so the transition runs
			el.style.height = h + 'px';
			var done = function () { el.style.height = 'auto'; el.removeEventListener( 'transitionend', done ); };
			el.addEventListener( 'transitionend', done );
		} else {
			el.style.height = el.scrollHeight + 'px';
			void el.offsetHeight;
			el.style.height = '0px';
		}
	}

	function toggleItem( li, open ) {
		var willOpen = ( 'boolean' === typeof open ) ? open : ! li.classList.contains( 'ds-open' );
		li.classList.toggle( 'ds-open', willOpen );
		var t = li.querySelector( ':scope > .ds-sub-toggle' );
		if ( t ) { t.setAttribute( 'aria-expanded', willOpen ? 'true' : 'false' ); }
		slide( panelOf( li ), willOpen );
	}

	function init( wrap ) {
		if ( wrap.dsMenuInit ) { return; }
		wrap.dsMenuInit = true;

		var toggle = wrap.querySelector( '.ds-menu-toggle' );
		var close  = wrap.querySelector( '.ds-menu-close' );

		function open( state ) {
			wrap.classList.toggle( 'ds-menu-open', state );
			if ( toggle ) { toggle.setAttribute( 'aria-expanded', state ? 'true' : 'false' ); }
			document.body.classList.toggle( 'ds-menu-locked', state );
			if ( ! state ) {
				// Reset every expanded accordion + clear inline heights so it reopens clean.
				wrap.querySelectorAll( '.ds-menu-item.ds-open' ).forEach( function ( li ) {
					li.classList.remove( 'ds-open' );
					var t = li.querySelector( ':scope > .ds-sub-toggle' );
					if ( t ) { t.setAttribute( 'aria-expanded', 'false' ); }
				} );
				wrap.querySelectorAll( '.ds-submenu, .ds-mega' ).forEach( function ( p ) { p.style.height = ''; } );
			}
		}

		if ( toggle ) { toggle.addEventListener( 'click', function () { open( ! wrap.classList.contains( 'ds-menu-open' ) ); } ); }
		if ( close ) { close.addEventListener( 'click', function () { open( false ); } ); }

		// The +/- icon toggles its submenu.
		wrap.querySelectorAll( '.ds-sub-toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var li = btn.closest( '.ds-menu-item' );
				if ( li ) { toggleItem( li ); }
			} );
		} );

		// In the overlay, tapping ANYWHERE on a parent row (the whole line, not just
		// the icon) opens/closes its submenu instead of navigating. Leaf links and the
		// desktop bar are untouched — navigation still works when the overlay is closed.
		wrap.querySelectorAll( '.ds-menu-item.has-children > a' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				if ( ! wrap.classList.contains( 'ds-menu-open' ) ) { return; }
				var li = a.closest( '.ds-menu-item' );
				if ( ! li ) { return; }
				e.preventDefault();
				toggleItem( li );
			} );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && wrap.classList.contains( 'ds-menu-open' ) ) { open( false ); }
		} );
	}

	/* Edge flip: when a dropdown / nested flyout would overflow the viewport's
	   right edge (e.g. the last "More" item), flip it to open leftward instead
	   of overlapping or clipping. Desktop only — the overlay renders submenus
	   statically. Measured on hover (panels have layout while hidden). */
	function initFlip( wrap ) {
		if ( wrap.dsFlipInit ) { return; }
		wrap.dsFlipInit = true;
		wrap.addEventListener( 'mouseover', function ( e ) {
			if ( wrap.classList.contains( 'ds-menu-open' ) ) { return; } // mobile overlay
			var li = e.target && e.target.closest ? e.target.closest( '.ds-menu-item.has-children' ) : null;
			if ( ! li || ! wrap.contains( li ) ) { return; }
			var sub = null;
			for ( var i = 0; i < li.children.length; i++ ) {
				if ( li.children[ i ].classList && li.children[ i ].classList.contains( 'ds-submenu' ) ) { sub = li.children[ i ]; break; }
			}
			if ( ! sub ) { return; }
			li.classList.remove( 'ds-flip' );
			var r = sub.getBoundingClientRect();
			if ( r.width > 0 && r.right > window.innerWidth - 8 ) { li.classList.add( 'ds-flip' ); }
		} );
	}

	function boot() { document.querySelectorAll( '.ds-menu-wrap' ).forEach( function ( w ) { init( w ); initFlip( w ); } ); }

	if ( 'loading' !== document.readyState ) { boot(); } else { document.addEventListener( 'DOMContentLoaded', boot ); }
	// Re-init after a Beaver Builder partial refresh while editing.
	if ( 'undefined' !== typeof jQuery ) { jQuery( document ).on( 'fl-builder.layout-rendered', boot ); }
})();
