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
	var drillCount = 0;

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
		drawer.id = 'ds-drill-' + ( ++drillCount );
		drawer.setAttribute( 'aria-hidden', 'true' );
		drawer.setAttribute( 'inert', '' );
		wrap.appendChild( drawer );
		var panelsEl = document.createElement( 'div' );
		panelsEl.className = 'ds-drill-panels';
		drawer.appendChild( panelsEl );
		var stack = [];
		var panelCount = 0;
		var toggle = wrap.querySelector( '.ds-menu-toggle' );
		if ( toggle ) {
			toggle.setAttribute( 'aria-controls', drawer.id );
		}

		function normalizedUrl( value ) {
			if ( ! value || '#' === value ) { return ''; }
			try {
				var parsed = new URL( value, window.location.href );
				return parsed.origin + parsed.pathname.replace( /\/+$/, '' );
			} catch ( error ) {
				return value;
			}
		}

		function isDuplicateOverview( child, parent ) {
			return 'hide' === wrap.dataset.drillOverview
				&& 'overview' === child.label.trim().toLowerCase()
				&& normalizedUrl( child.url ) === normalizedUrl( parent.url );
		}

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
				el.setAttribute( 'aria-haspopup', 'true' );
				var chev = document.createElement( 'span' );
				chev.className = 'ds-drill-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.innerHTML = CHEV_R;
				el.appendChild( chev );
				el.addEventListener( 'click', function () { push( node, el, true ); } );
			}
			return el;
		}

		function buildPanel( node ) {
			var p = document.createElement( 'div' );
			p.className = 'ds-drill-panel' + ( node.root ? ' is-root' : '' );
			p.tabIndex = -1;
			p.setAttribute( 'role', 'group' );
			p.setAttribute( 'aria-hidden', 'false' );
			p.dataset.panelLabel = node.root ? 'Main menu' : node.label;
			if ( node.root ) {
				p.setAttribute( 'aria-label', 'Main menu' );
			}
			if ( ! node.root ) {
				var back = document.createElement( 'button' );
				back.type = 'button';
				back.className = 'ds-drill-back';
				back.innerHTML = CHEV_L;
				var destination = stack.length ? stack[ stack.length - 1 ].dataset.panelLabel : 'Main menu';
				back.setAttribute( 'aria-label', 'Back to ' + destination );
				var copy = document.createElement( 'span' );
				copy.className = 'ds-drill-back-copy';
				var cue = document.createElement( 'span' );
				cue.className = 'ds-drill-back-cue';
				cue.textContent = 'Back';
				var title = document.createElement( 'span' );
				title.className = 'ds-drill-title';
				title.id = drawer.id + '-title-' + ( ++panelCount );
				title.textContent = node.label;
				copy.appendChild( cue );
				copy.appendChild( title );
				back.appendChild( copy );
				back.addEventListener( 'click', pop );
				p.appendChild( back );
				p.setAttribute( 'aria-labelledby', title.id );
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
			node.children.forEach( function ( c ) {
				if ( ! isDuplicateOverview( c, node ) ) {
					p.appendChild( row( c, !! node.root ) );
				}
			} );
			return p;
		}

		function push( node, trigger, moveFocus ) {
			var prev = stack[ stack.length - 1 ];
			if ( prev ) {
				prev.style.transform = 'translateX(-100%)';
				prev.setAttribute( 'aria-hidden', 'true' );
				prev.setAttribute( 'inert', '' );
			}
			var p = buildPanel( node );
			p.dsTrigger = trigger || null;
			panelsEl.appendChild( p );
			stack.push( p );
			requestAnimationFrame( function () {
				p.style.transform = 'translateX(0)';
				if ( moveFocus ) {
					var focusTarget = p.querySelector( '.ds-drill-back' ) || p;
					focusTarget.focus( { preventScroll: true } );
				}
			} );
		}

		function pop() {
			if ( stack.length < 2 ) { return; }
			var top = stack.pop();
			var trigger = top.dsTrigger;
			top.style.transform = 'translateX(100%)';
			top.setAttribute( 'aria-hidden', 'true' );
			top.setAttribute( 'inert', '' );
			setTimeout( function () { top.remove(); }, 320 );
			var current = stack[ stack.length - 1 ];
			current.removeAttribute( 'inert' );
			current.setAttribute( 'aria-hidden', 'false' );
			current.style.transform = 'translateX(0)';
			if ( trigger ) {
				requestAnimationFrame( function () { trigger.focus( { preventScroll: true } ); } );
			}
		}

		return {
			openRoot: function () {
				panelsEl.innerHTML = '';
				stack = [];
				push( { root: true, label: '', url: '', isButton: false, children: drillTree( wrap ) }, toggle, false );
			},
			clear: function () { panelsEl.innerHTML = ''; stack = []; },
			setOpen: function ( state ) {
				drawer.setAttribute( 'aria-hidden', state ? 'false' : 'true' );
				if ( state ) { drawer.removeAttribute( 'inert' ); } else { drawer.setAttribute( 'inert', '' ); }
			},
			activePanel: function () { return stack[ stack.length - 1 ] || null; },
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
		var drillClearTimer = null;
		var originalRole = wrap.getAttribute( 'role' );
		var originalAriaModal = wrap.getAttribute( 'aria-modal' );
		var originalAriaLabel = wrap.getAttribute( 'aria-label' );
		var toggleLabel = toggle ? toggle.querySelector( '.ds-menu-toggle-label' ) : null;
		var toggleLabelText = toggleLabel ? toggleLabel.textContent : '';
		var toggleAriaLabel = toggle ? toggle.getAttribute( 'aria-label' ) : null;

		function restoreAttribute( name, value ) {
			if ( null === value ) { wrap.removeAttribute( name ); } else { wrap.setAttribute( name, value ); }
		}

		function open( state ) {
			var wasOpen = wrap.classList.contains( 'ds-menu-open' );
			wrap.classList.toggle( 'ds-menu-open', state );
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
				toggle.setAttribute( 'aria-label', state ? 'Close menu' : ( toggleAriaLabel || 'Toggle menu' ) );
			}
			if ( toggleLabel ) { toggleLabel.textContent = state ? 'Close' : toggleLabelText; }
			document.body.classList.toggle( 'ds-menu-locked', state );
			if ( isDrill ) {
				if ( state ) {
					if ( drillClearTimer ) {
						clearTimeout( drillClearTimer );
						drillClearTimer = null;
					}
					if ( ! drill ) { drill = makeDrill( wrap ); }
					drill.setOpen( true );
					wrap.setAttribute( 'role', 'dialog' );
					wrap.setAttribute( 'aria-modal', 'true' );
					wrap.setAttribute( 'aria-label', 'Site menu' );
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
					drill.setOpen( false );
					drillClearTimer = setTimeout( function () {
						drill.clear();
						drillClearTimer = null;
					}, 280 ); // after the fade-out
				}
				if ( ! state ) {
					restoreAttribute( 'role', originalRole );
					restoreAttribute( 'aria-modal', originalAriaModal );
					restoreAttribute( 'aria-label', originalAriaLabel );
					if ( wasOpen && toggle ) {
						requestAnimationFrame( function () { toggle.focus( { preventScroll: true } ); } );
					}
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
			if ( ! wrap.classList.contains( 'ds-menu-open' ) ) { return; }
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				open( false );
				return;
			}
			if ( 'Tab' === e.key && isDrill && drill ) {
				var panel = drill.activePanel();
				if ( ! panel ) { return; }
				var panelFocus = Array.prototype.slice.call( panel.querySelectorAll( 'a[href], button:not([disabled])' ) );
				var focusable = ( toggle ? [ toggle ] : [] ).concat( panelFocus );
				if ( ! focusable.length ) { return; }
				var first = focusable[0];
				var last = focusable[ focusable.length - 1 ];
				var active = document.activeElement;
				if ( e.shiftKey && ( active === first || active === panel || focusable.indexOf( active ) === -1 ) ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && active === last ) {
					e.preventDefault();
					first.focus();
				}
			}
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
