/**
 * Page Banner Image button in the Beaver Builder top bar (DS_Page_Banner).
 * A small panel under the button shows the current banner image, opens the media
 * library to choose one, or removes it. Saves over AJAX, then repaints the banner in
 * the builder preview, because the banner belongs to the Themer layout and the builder
 * only re-renders the page's own content.
 *
 * Beaver Builder 2.10+ runs the page (and this script) in a preview iframe while the
 * top bar lives in the outer window, so everything the panel draws goes into the
 * BUTTON's window: its document, its stylesheet and its wp.media.
 */
( function ( $ ) {
	'use strict';
	var D = window.dsBannerImageData;
	if ( ! D ) { return; }
	var T = D.i18n, state = D.state, pop = null, btn = null, frame = null, busy = false, uiDoc = document, uiWin = window, focalTimer = null;

	function esc( s ) { return String( s ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } ); }

	function render( status, isError ) {
		if ( ! pop ) { return; }
		var f = state.focal || [ 50, 50 ];
		// With a photo: the whole photo as a focal-point picker (the banner crops it; the point stays in view).
		var preview = state.thumb && ! state.hasVideo
			? '<div class="ds-bi-fstage" title="' + esc( T.focalHint ) + '"><img src="' + esc( state.full || state.thumb ) + '" alt="" draggable="false"><span class="ds-bi-dot" style="left:' + f[0] + '%;top:' + f[1] + '%"></span></div>'
			: ( state.thumb ? '<img src="' + esc( state.thumb ) + '" alt="">' : '<span class="ds-bi-empty">' + esc( T.none ) + '</span>' );
		var focal = state.thumb && ! state.hasVideo
			? '<div class="ds-bi-focal"><div class="ds-bi-frow"><span class="ds-bi-flbl">' + esc( T.focal ) + '</span><button type="button" class="ds-bi-centre">' + esc( T.centre ) + '</button></div>' +
				'<label class="ds-bi-range"><span>' + esc( T.horiz ) + ' <output data-f="0">' + f[0] + '%</output></span><input type="range" min="0" max="100" step="1" value="' + f[0] + '" data-f="0"></label>' +
				'<label class="ds-bi-range"><span>' + esc( T.vert ) + ' <output data-f="1">' + f[1] + '%</output></span><input type="range" min="0" max="100" step="1" value="' + f[1] + '" data-f="1"></label></div>'
			: '';
		pop.html(
			'<div class="ds-bi-head"><strong>' + esc( T.title ) + '</strong>' +
				'<button type="button" class="ds-bi-close" aria-label="Close">&times;</button></div>' +
			'<div class="ds-bi-preview' + ( state.thumb ? '' : ' is-empty' ) + ( state.thumb && ! state.hasVideo ? ' is-focal' : '' ) + '">' + preview + '</div>' + focal +
			( state.hasVideo ? '<p class="ds-bi-note">' + esc( T.video ) + '</p>' : '' ) +
			'<div class="ds-bi-actions">' +
				'<button type="button" class="ds-bi-choose">' + esc( state.id ? T.change : T.choose ) + '</button>' +
				( state.id ? '<button type="button" class="ds-bi-remove">' + esc( T.remove ) + '</button>' : '' ) +
			'</div>' +
			'<p class="ds-bi-status' + ( isError ? ' is-error' : '' ) + '" role="status">' + esc( status || T.live ) + '</p>'
		);
		pop.toggleClass( 'is-busy', busy );
	}

	function place() {
		if ( ! pop || ! btn ) { return; }
		var r = btn.getBoundingClientRect(), w = pop.outerWidth();
		var left = Math.max( 12, Math.min( r.right - w, uiWin.innerWidth - w - 12 ) );
		pop.css( { top: Math.round( r.bottom + 8 ), left: Math.round( left ) } );
	}

	/** The panel's stylesheet, in the button's document (it is only enqueued in the preview). */
	function ensureCss() {
		if ( uiDoc.getElementById( 'ds-bi-css' ) ) { return; }
		var src = $( 'link[href*="builder-banner-image.css"]' ).attr( 'href' );
		if ( ! src ) { return; }
		var l = uiDoc.createElement( 'link' ); l.id = 'ds-bi-css'; l.rel = 'stylesheet'; l.href = src;
		uiDoc.head.appendChild( l );
	}

	function open( el ) {
		btn = el; uiDoc = el.ownerDocument; uiWin = uiDoc.defaultView || window;
		ensureCss();
		pop = $( '<div class="ds-bi-pop" role="dialog"></div>', uiDoc ).attr( 'aria-label', T.title ).appendTo( uiDoc.body );
		render(); place();
		$( btn ).addClass( 'ds-bi-active' ).attr( 'aria-expanded', 'true' );
		pop.on( 'input', '.ds-bi-range input', function () { var f = ( state.focal || [ 50, 50 ] ).slice(); f[ +this.getAttribute( 'data-f' ) ] = +this.value; setFocal( f ); } )
			.on( 'click', '.ds-bi-centre', function () { setFocal( [ 50, 50 ] ); } )
			.on( 'pointerdown', '.ds-bi-fstage', function ( e ) {
				var stage = this, img = stage.querySelector( 'img' );
				var at = function ( ev ) { var r = img.getBoundingClientRect(); setFocal( [ Math.round( Math.max( 0, Math.min( 1, ( ev.clientX - r.left ) / r.width ) ) * 100 ), Math.round( Math.max( 0, Math.min( 1, ( ev.clientY - r.top ) / r.height ) ) * 100 ) ] ); };
				e.preventDefault(); at( e );
				var move = function ( ev ) { at( ev ); }, up = function () { uiDoc.removeEventListener( 'pointermove', move ); uiDoc.removeEventListener( 'pointerup', up ); };
				uiDoc.addEventListener( 'pointermove', move ); uiDoc.addEventListener( 'pointerup', up );
			} )
			.on( 'click', '.ds-bi-close', close )
			.on( 'click', '.ds-bi-choose', choose )
			.on( 'click', '.ds-bi-remove', function () { save( 0 ); } );
		$( uiDoc ).on( 'mousedown.dsbi', function ( e ) {
			if ( pop && ! pop[0].contains( e.target ) && ! btn.contains( e.target ) && ! $( e.target ).closest( '.media-modal' ).length ) { close(); }
		} ).on( 'keydown.dsbi', function ( e ) { if ( 'Escape' === e.key && ! $( uiDoc ).find( '.media-modal:visible' ).length ) { close(); } } );
		$( uiWin ).on( 'resize.dsbi', place );
		pop.find( '.ds-bi-choose' ).trigger( 'focus' );
	}

	function close() {
		if ( pop ) { pop.remove(); pop = null; }
		if ( btn ) { $( btn ).removeClass( 'ds-bi-active' ).attr( 'aria-expanded', 'false' ).trigger( 'focus' ); }
		$( uiDoc ).off( '.dsbi' ); $( uiWin ).off( '.dsbi' );
	}

	function choose() {
		var media = ( uiWin.wp && uiWin.wp.media ) ? uiWin.wp : window.wp; // the library opens over the whole builder
		if ( ! media || ! media.media ) { render( T.failed, true ); return; }
		if ( ! frame ) {
			frame = media.media( { title: T.title, library: { type: 'image' }, button: { text: T.use }, multiple: false } );
			frame.on( 'open', function () {
				frame.content.mode( 'browse' ); // the library, not the upload tab (the library is still loading when it opens)
				var sel = frame.state().get( 'selection' );
				sel.reset( state.id ? [ media.media.attachment( state.id ) ] : [] );
			} );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first();
				if ( a ) { save( a.get( 'id' ) ); }
			} );
		}
		frame.open();
	}

	/** Move the point: panel, banner preview right away, saved a moment after the last change. */
	function setFocal( f ) {
		state.focal = f;
		if ( pop ) {
			var dot = pop[0].querySelector( '.ds-bi-dot' ); if ( dot ) { dot.style.left = f[0] + '%'; dot.style.top = f[1] + '%'; }
			pop.find( '.ds-bi-range input' ).each( function () { var i = +this.getAttribute( 'data-f' ); if ( +this.value !== f[ i ] ) { this.value = f[ i ]; } } );
			pop.find( 'output[data-f]' ).each( function () { this.textContent = f[ +this.getAttribute( 'data-f' ) ] + '%'; } );
		}
		repaint();
		clearTimeout( focalTimer );
		focalTimer = setTimeout( saveFocal, 450 );
	}
	function saveFocal() {
		var f = state.focal || [ 50, 50 ], st = pop && pop[0].querySelector( '.ds-bi-status' );
		$.post( D.ajax, { action: D.action, nonce: D.nonce, post_id: D.postId, focal_x: f[0], focal_y: f[1] } )
			.done( function ( r ) { if ( st ) { st.classList.toggle( 'is-error', ! ( r && r.success ) ); st.textContent = r && r.success ? T.posSaved : T.failed; } } )
			.fail( function () { if ( st ) { st.classList.add( 'is-error' ); st.textContent = T.failed; } } );
	}

	function save( id ) {
		if ( busy ) { return; }
		busy = true; render( T.saving );
		$.post( D.ajax, { action: D.action, nonce: D.nonce, post_id: D.postId, attachment_id: id } )
			.done( function ( r ) {
				if ( ! r || ! r.success ) { busy = false; render( T.failed, true ); return; }
				state = r.data; busy = false;
				render( id ? T.saved : T.removed );
				repaint();
			} )
			.fail( function () { busy = false; render( T.failed, true ); } );
	}

	/** Every document that can hold the banner: this one and the builder's same-origin preview iframe(s). */
	function docs() {
		var out = [ uiDoc ];
		Array.prototype.forEach.call( uiDoc.querySelectorAll( 'iframe' ), function ( f ) {
			try { if ( f.contentDocument && out.indexOf( f.contentDocument ) < 0 ) { out.push( f.contentDocument ); } } catch ( e ) {}
		} );
		if ( out.indexOf( document ) < 0 ) { out.push( document ); }
		return out;
	}

	function repaint() {
		var pos = ( state.focal || [ 50, 50 ] ).map( function ( v ) { return v + '%'; } ).join( ' ' );
		if ( state.hasVideo ) { // the video shows instead of any image; the point frames it
			docs().forEach( function ( doc ) { Array.prototype.forEach.call( doc.querySelectorAll( '.ds-hero--banner .ds-hero-video' ), function ( v ) { v.style.objectPosition = pos; } ); } );
			return;
		}
		docs().forEach( function ( doc ) {
			Array.prototype.forEach.call( doc.querySelectorAll( '.ds-hero--banner' ), function ( banner ) {
				var bg = banner.querySelector( ':scope > .ds-hero-bg' );
				if ( state.url ) {
					if ( ! bg ) { bg = doc.createElement( 'div' ); bg.className = 'ds-hero-bg'; banner.insertBefore( bg, banner.firstChild ); }
					bg.style.backgroundImage = 'url("' + state.url.replace( /"/g, '%22' ) + '")';
					bg.style.backgroundPosition = pos;
					banner.classList.remove( 'ds-banner--no-bg' ); banner.classList.add( 'ds-banner--has-bg' );
				} else {
					if ( bg ) { bg.parentNode.removeChild( bg ); }
					banner.classList.remove( 'ds-banner--has-bg' ); banner.classList.add( 'ds-banner--no-bg' );
				}
				// Theme Setting can hide the title on photo banners.
				Array.prototype.forEach.call( banner.querySelectorAll( '.ds-hero-title, .ds-hero-sub, .ds-banner-eyebrow' ), function ( t ) {
					if ( state.url && state.hideTitle ) { t.setAttribute( 'data-ds-bi-hidden', '1' ); t.style.display = 'none'; }
					else if ( t.getAttribute( 'data-ds-bi-hidden' ) ) { t.removeAttribute( 'data-ds-bi-hidden' ); t.style.display = ''; }
				} );
			} );
		} );
	}

	window.dsBannerImage = { toggle: function ( el ) { if ( pop ) { close(); } else { open( el ); } } };
} )( jQuery );
