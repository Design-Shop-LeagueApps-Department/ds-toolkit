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

	/* ---------------- Drill-down drawer (Mobile Menu Style = drill) ----------------
	   Panels are built at open time from the RENDERED WP-menu markup, so the drawer
	   always mirrors Appearance → Menus (nothing duplicated server-side). One level
	   per panel; drilling slides the next level in from the right; back walks out;
	   parents with a real URL can get an optional "Overview" link row. */
	var CHEV_R = '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>';
	var CHEV_L = '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 6 9 12 15 18"/></svg>';

	// Data tree from the rendered menu. Mega panels flatten into drill levels:
	// each mega column heading becomes a drillable row over its column links.
	function drillTree( wrap ) {
		function walkList( ul ) {
			var out = [];
			if ( ! ul ) { return out; }
			for ( var i = 0; i < ul.children.length; i++ ) {
				var li = ul.children[ i ];
				if ( ! li.classList || ! li.classList.contains( 'ds-menu-item' ) ) { continue; }
				var a = li.querySelector( ':scope > a' );
				if ( ! a ) { continue; }
				var labelEl = a.querySelector( 'span' );
				var node = {
					label: ( labelEl ? labelEl.textContent : a.textContent ).trim(),
					url: a.getAttribute( 'href' ) || '',
					target: a.getAttribute( 'target' ) || '',
					isButton: li.classList.contains( 'is-button' ),
					children: []
				};
				var mega = null;
				for ( var j = 0; j < li.children.length; j++ ) {
					if ( li.children[ j ].classList && li.children[ j ].classList.contains( 'ds-mega' ) ) { mega = li.children[ j ]; break; }
				}
				if ( mega ) {
					mega.querySelectorAll( '.ds-mega-col' ).forEach( function ( col ) {
						var head  = col.querySelector( '.ds-mega-head a' ) || col.querySelector( '.ds-mega-head' );
						var colNode = {
							label: head ? head.textContent.trim() : '',
							url: ( head && head.getAttribute ) ? ( head.getAttribute( 'href' ) || '' ) : '',
							target: '', isButton: false,
							children: walkList( col.querySelector( '.ds-submenu' ) )
						};
						if ( colNode.label ) { node.children.push( colNode ); }
					} );
				} else {
					var sub = null;
					for ( var k = 0; k < li.children.length; k++ ) {
						if ( li.children[ k ].classList && li.children[ k ].classList.contains( 'ds-submenu' ) ) { sub = li.children[ k ]; break; }
					}
					node.children = walkList( sub );
				}
				out.push( node );
			}
			return out;
		}
		return walkList( wrap.querySelector( '.ds-menu' ) );
	}

	function makeDrill( wrap ) {
		var drawer = document.createElement( 'div' );
		drawer.className = 'ds-drill';
		wrap.appendChild( drawer );
		var panelsEl = document.createElement( 'div' );
		panelsEl.className = 'ds-drill-panels';
		drawer.appendChild( panelsEl );
		var stack = [];

		// Persistent brand bar above the panels (site/partner logo from data attrs).
		var brand = null;
		if ( wrap.dataset.drillLogo ) {
			brand = document.createElement( 'div' );
			brand.className = 'ds-drill-brand ds-drill-brand--' + ( 'left' === wrap.dataset.drillLogoAlign ? 'left' : 'right' );
			var bimg = document.createElement( 'img' );
			bimg.src = wrap.dataset.drillLogo;
			bimg.alt = '';
			brand.appendChild( bimg );
			drawer.appendChild( brand );
		}

		function row( node, isRoot ) {
			var hasKids = node.children.length > 0;
			var el = document.createElement( hasKids ? 'button' : 'a' );
			el.className = 'ds-drill-row' + ( node.isButton ? ' ds-drill-cta' : '' );
			if ( hasKids ) { el.type = 'button'; } else {
				el.href = node.url || '#';
				if ( node.target ) { el.target = node.target; el.rel = 'noopener'; }
			}
			var label = document.createElement( 'span' );
			label.className = 'ds-drill-label';
			label.textContent = node.label;
			el.appendChild( label );
			if ( hasKids ) {
				var chev = document.createElement( 'span' );
				chev.className = 'ds-drill-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.innerHTML = CHEV_R;
				el.appendChild( chev );
				el.addEventListener( 'click', function () { push( node ); } );
			}
			return el;
		}

		function buildPanel( node ) {
			var p = document.createElement( 'div' );
			p.className = 'ds-drill-panel' + ( node.root ? ' is-root' : '' );
			if ( ! node.root ) {
				var back = document.createElement( 'button' );
				back.type = 'button';
				back.className = 'ds-drill-back';
				back.innerHTML = CHEV_L;
				var bl = document.createElement( 'span' );
				bl.textContent = node.label;
				back.appendChild( bl );
				back.addEventListener( 'click', pop );
				p.appendChild( back );
				if ( 'hide' !== wrap.dataset.drillOverview && node.url && '#' !== node.url ) {
					var ov = document.createElement( 'a' );
					ov.className = 'ds-drill-overview';
					ov.href = node.url;
					var os = document.createElement( 'span' );
					os.textContent = 'Overview';
					var oh = document.createElement( 'small' );
					oh.textContent = node.label;
					ov.appendChild( os );
					ov.appendChild( oh );
					p.appendChild( ov );
				}
			}
			node.children.forEach( function ( c ) { p.appendChild( row( c, !! node.root ) ); } );
			return p;
		}

		function push( node ) {
			var prev = stack[ stack.length - 1 ];
			if ( prev ) { prev.style.transform = 'translateX(-100%)'; }
			var p = buildPanel( node );
			panelsEl.appendChild( p );
			stack.push( p );
			requestAnimationFrame( function () { p.style.transform = 'translateX(0)'; } );
		}

		function pop() {
			if ( stack.length < 2 ) { return; }
			var top = stack.pop();
			top.style.transform = 'translateX(100%)';
			setTimeout( function () { top.remove(); }, 320 );
			stack[ stack.length - 1 ].style.transform = 'translateX(0)';
		}

		return {
			openRoot: function () {
				panelsEl.innerHTML = '';
				stack = [];
				push( { root: true, label: '', url: '', isButton: false, children: drillTree( wrap ) } );
			},
			clear: function () { panelsEl.innerHTML = ''; stack = []; },
			brand: brand
		};
	}

	function init( wrap ) {
		if ( wrap.dsMenuInit ) { return; }
		wrap.dsMenuInit = true;

		var toggle  = wrap.querySelector( '.ds-menu-toggle' );
		var close   = wrap.querySelector( '.ds-menu-close' );
		var isDrill = wrap.classList.contains( 'ds-menu-wrap--drill' );
		var drill   = null;

		function open( state ) {
			wrap.classList.toggle( 'ds-menu-open', state );
			if ( toggle ) { toggle.setAttribute( 'aria-expanded', state ? 'true' : 'false' ); }
			document.body.classList.toggle( 'ds-menu-locked', state );
			if ( isDrill ) {
				if ( state ) {
					if ( ! drill ) { drill = makeDrill( wrap ); }
					drill.openRoot();
					// Right-aligned brand clears the pinned X + label, whose width
					// varies with the Hamburger Label — measure, don't guess.
					if ( drill.brand && toggle && -1 !== drill.brand.className.indexOf( 'ds-drill-brand--right' ) ) {
						requestAnimationFrame( function () {
							var t = toggle.getBoundingClientRect();
							drill.brand.style.paddingRight = Math.max( 76, Math.round( window.innerWidth - t.left + 14 ) ) + 'px';
						} );
					}
				} else if ( drill ) {
					setTimeout( drill.clear, 280 ); // after the fade-out
				}
				return;
			}
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

		if ( ! isDrill ) {
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
		}

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
