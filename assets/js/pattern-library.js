/**
 * Pattern library on the Theme Setting page (DS_Pattern_Library).
 * Adds "Browse patterns" beside the Media Library button of every background image field, in both Theme Setting
 * markups (.dsts-img on the current page, .ds-ts-media on older toolkits), and fills the field exactly as picking an
 * image does, so unsaved-change tracking, the live preview and Save work unchanged.
 */
( function () {
	'use strict';
	var D = window.dsPatternLib;
	if ( ! D ) { return; }

	var CATS = [
		[ 'All', null ],
		[ 'Lines', [ 'lines', 'stripes', 'herringbone', 'zigzag', 'chevron', 'plaid' ] ],
		[ 'Geometric', [ 'triangles', 'squares', 'diamonds', 'rhombus', 'hexagon', 'geometric', 'cross', 'stars', 'polygons', 'cubes' ] ],
		[ 'Circles & dots', [ 'circles', 'dots', 'polka dots' ] ],
		[ 'Waves & curves', [ 'waves', 'curves', 'scales', 'clouds' ] ],
		[ 'Nature', [ 'leaves', 'flower', 'floral', 'fish', 'nature' ] ],
		[ 'Cultural', [ 'country', 'chinese pattern', 'japanese' ] ],
		[ 'Seasonal', [ 'christmas', 'holidays', 'santaclaus', 'halloween' ] ]
	];
	// Only background fields: never a logo, social card or favicon.
	var FIELD_RE = /(bg|background|nobg|pattern)[^\]]*_image\]$|\[(bg|content_bg|banner_nobg)_image\]$/i;
	var SKIP_RE = /logo|social|card|favicon|icon/i;

	var data = null, loading = null, ui = null, target = null;
	var st = { slug: '', color: '', opacity: 0.2, scale: 1, stroke: 1, cat: 'All', q: '' };

	function el( tag, attrs ) {
		var e = document.createElement( tag );
		for ( var k in ( attrs || {} ) ) { if ( 'class' === k ) { e.className = attrs[ k ]; } else { e.setAttribute( k, attrs[ k ] ); } }
		return e;
	}
	function num( n ) { return String( Math.round( n * 1000 ) / 1000 ); }

	/** Same tile as DS_Pattern_Library::svg() (the file the server writes). */
	function svg( p, color, opacity, scale, stroke ) {
		var w = p.w, h = p.h, paths = p.p;
		if ( p.v > 0 ) { h = h - p.v * ( p.p.length + 1 - 2 ); paths = [ p.p[0] ]; }
		var paint = 'fill' === p.m ? " stroke='none' fill='" + color + "'"
			: " stroke='" + color + "' fill='none'" + ( 'stroke-join' === p.m ? " stroke-linejoin='round' stroke-linecap='round'" : '' ) + " stroke-width='" + num( stroke ) + "'";
		var body = paths.map( function ( d ) { return d.trim().replace( /\s*\/>$/, paint + '/>' ); } ).join( '' );
		return "<svg xmlns='http://www.w3.org/2000/svg' width='" + num( w * scale ) + "' height='" + num( h * scale ) + "' viewBox='0 0 " + num( w ) + ' ' + num( h ) + "'><g opacity='" + num( opacity ) + "'>" + body + '</g></svg>';
	}
	function uri( s ) { return 'url("data:image/svg+xml,' + encodeURIComponent( s ).replace( /'/g, '%27' ) + '")'; }

	/** The field's own Background colour (resolving palette tokens), so the preview shows the real result. */
	function fieldBg( input ) {
		var name = input.getAttribute( 'name' ) || '';
		var c = document.querySelector( '[name="' + name.replace( /_image\]$/, '_color]' ) + '"]' );
		var v = c ? String( c.value || '' ).trim() : '';
		var m = v.match( /var\(\s*--fl-global-([a-z0-9-]+)/i );
		if ( m ) { var hit = D.palette.filter( function ( x ) { return ( x.alias || [ x.slug ] ).indexOf( m[1] ) > -1; } )[0]; v = hit ? hit.color : ''; }
		return /^#|^rgb/i.test( v ) ? v : '#ffffff';
	}

	function load() {
		if ( data ) { return Promise.resolve( data ); }
		if ( ! loading ) { loading = fetch( D.data, { credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( j ) { data = j; return j; } ); }
		return loading;
	}

	function current() { return data ? data.filter( function ( p ) { return p.s === st.slug; } )[0] : null; }

	function visible() {
		var cat = CATS.filter( function ( c ) { return c[0] === st.cat; } )[0];
		var q = st.q.toLowerCase();
		return data.filter( function ( p ) {
			if ( cat && cat[1] && ! p.g.some( function ( t ) { return cat[1].indexOf( t ) > -1; } ) ) { return false; }
			return ! q || p.t.toLowerCase().indexOf( q ) > -1 || p.g.join( ' ' ).indexOf( q ) > -1;
		} );
	}

	function build() {
		ui = el( 'div', { 'class': 'ds-pl', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Pattern library' } );
		ui.innerHTML =
			'<div class="ds-pl-box">' +
			'<div class="ds-pl-head"><h2>Pattern library</h2><input type="search" class="ds-pl-search" placeholder="Search patterns" aria-label="Search patterns"><button type="button" class="ds-pl-x" aria-label="Close">&times;</button></div>' +
			'<div class="ds-pl-cats" role="toolbar" aria-label="Categories"></div>' +
			'<div class="ds-pl-body"><div class="ds-pl-grid" role="listbox" aria-label="Patterns"></div>' +
			'<div class="ds-pl-side">' +
				'<div class="ds-pl-preview" aria-hidden="true"></div><p class="ds-pl-name"></p>' +
				'<div class="ds-pl-ctl"><span class="ds-pl-lbl">Colour</span><div class="ds-pl-sw"></div></div>' +
				'<label class="ds-pl-ctl"><span class="ds-pl-lbl">Opacity <output data-o="opacity"></output></span><input type="range" data-k="opacity" min="5" max="100" step="1"></label>' +
				'<label class="ds-pl-ctl"><span class="ds-pl-lbl">Size <output data-o="scale"></output></span><input type="range" data-k="scale" min="0.5" max="4" step="0.1"></label>' +
				'<label class="ds-pl-ctl ds-pl-stroke"><span class="ds-pl-lbl">Line weight <output data-o="stroke"></output></span><input type="range" data-k="stroke" min="0.25" max="6" step="0.25"></label>' +
			'</div></div>' +
			'<div class="ds-pl-foot"><span class="ds-pl-credit">Patterns by <a href="https://pattern.monster/" target="_blank" rel="noopener">pattern.monster</a> (MIT). Tiled over this section&#8217;s background colour.</span>' +
			'<span class="ds-pl-status" role="status"></span><button type="button" class="button ds-pl-cancel">Cancel</button><button type="button" class="button button-primary ds-pl-use" disabled>Use pattern</button></div>' +
			'</div>';
		document.body.appendChild( ui );

		var cats = ui.querySelector( '.ds-pl-cats' );
		CATS.forEach( function ( c ) { var b = el( 'button', { type: 'button', 'class': 'ds-pl-cat', 'data-cat': c[0], 'aria-pressed': 'false' } ); b.textContent = c[0]; cats.appendChild( b ); } );
		var sw = ui.querySelector( '.ds-pl-sw' );
		D.palette.forEach( function ( c ) { var b = el( 'button', { type: 'button', 'class': 'ds-pl-swatch', 'data-color': c.color, title: c.label, 'aria-label': c.label } ); b.style.background = c.color; sw.appendChild( b ); } );
		var custom = el( 'input', { type: 'color', 'class': 'ds-pl-custom', title: 'Custom colour', 'aria-label': 'Custom colour' } ); sw.appendChild( custom );

		ui.addEventListener( 'click', function ( e ) {
			var t = e.target;
			if ( t === ui || t.closest( '.ds-pl-x, .ds-pl-cancel' ) ) { close(); return; }
			var cat = t.closest( '.ds-pl-cat' ); if ( cat ) { st.cat = cat.getAttribute( 'data-cat' ); grid(); return; }
			var s = t.closest( '.ds-pl-swatch' ); if ( s ) { st.color = s.getAttribute( 'data-color' ); custom.value = st.color; refresh(); return; }
			var tile = t.closest( '.ds-pl-tile' ); if ( tile ) { st.slug = tile.getAttribute( 'data-slug' ); side(); mark(); return; }
			if ( t.closest( '.ds-pl-use' ) ) { use(); }
		} );
		ui.addEventListener( 'dblclick', function ( e ) { if ( e.target.closest( '.ds-pl-tile' ) ) { use(); } } );
		custom.addEventListener( 'input', function () { st.color = custom.value; refresh(); } );
		ui.querySelector( '.ds-pl-search' ).addEventListener( 'input', function ( e ) { st.q = e.target.value; grid(); } );
		var timer;
		ui.querySelectorAll( 'input[type=range]' ).forEach( function ( r ) {
			r.addEventListener( 'input', function () {
				var k = r.getAttribute( 'data-k' ); st[ k ] = 'opacity' === k ? r.value / 100 : parseFloat( r.value );
				outputs(); clearTimeout( timer ); timer = setTimeout( refresh, 60 );
			} );
		} );
		document.addEventListener( 'keydown', function ( e ) { if ( ui && ! ui.hidden && 'Escape' === e.key ) { close(); } } );
	}

	function outputs() {
		ui.querySelector( '[data-o=opacity]' ).textContent = Math.round( st.opacity * 100 ) + '%';
		ui.querySelector( '[data-o=scale]' ).textContent = st.scale.toFixed( 1 ) + '×';
		ui.querySelector( '[data-o=stroke]' ).textContent = st.stroke;
		ui.querySelector( '[data-k=opacity]' ).value = Math.round( st.opacity * 100 );
		ui.querySelector( '[data-k=scale]' ).value = st.scale;
		ui.querySelector( '[data-k=stroke]' ).value = st.stroke;
	}

	function grid() {
		ui.querySelectorAll( '.ds-pl-cat' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', b.getAttribute( 'data-cat' ) === st.cat ? 'true' : 'false' ); } );
		var g = ui.querySelector( '.ds-pl-grid' ), list = visible(), bg = st.bg;
		g.innerHTML = list.length ? '' : '<p class="ds-pl-none">No patterns match.</p>';
		var frag = document.createDocumentFragment();
		list.forEach( function ( p ) {
			var b = el( 'button', { type: 'button', 'class': 'ds-pl-tile', role: 'option', 'data-slug': p.s, title: p.t, 'aria-label': p.t } );
			b.style.backgroundColor = bg;
			b.style.backgroundImage = uri( svg( p, st.color, Math.max( st.opacity, 0.35 ), Math.min( st.scale, 1.5 ) * 0.6, Math.min( st.stroke, p.ms || 1 ) ) );
			frag.appendChild( b );
		} );
		g.appendChild( frag );
		mark();
	}
	function mark() { ui.querySelectorAll( '.ds-pl-tile' ).forEach( function ( b ) { var on = b.getAttribute( 'data-slug' ) === st.slug; b.classList.toggle( 'is-on', on ); b.setAttribute( 'aria-selected', on ? 'true' : 'false' ); } ); }

	function side() {
		var p = current(), prev = ui.querySelector( '.ds-pl-preview' );
		prev.style.backgroundColor = st.bg;
		prev.style.backgroundImage = p ? uri( svg( p, st.color, st.opacity, st.scale, Math.min( st.stroke, p.ms || 1 ) ) ) : 'none';
		ui.querySelector( '.ds-pl-name' ).textContent = p ? p.t : 'Pick a pattern';
		ui.querySelector( '.ds-pl-stroke' ).hidden = ! p || 'fill' === p.m;
		ui.querySelector( '.ds-pl-use' ).disabled = ! p;
		ui.querySelectorAll( '.ds-pl-swatch' ).forEach( function ( s ) { s.setAttribute( 'aria-pressed', s.getAttribute( 'data-color' ) === st.color ? 'true' : 'false' ); } );
	}
	function refresh() { grid(); side(); }

	/**
	 * The field already holds a library pattern: read its own file back (slug from the name, colour, opacity, size and
	 * line weight from the SVG this feature wrote) so the gallery reopens on it and a recolour is one click.
	 */
	function current_from( input ) {
		var url = input.value || '', m = url.match( /\/ds-patterns\/([a-z0-9-]+)-[0-9a-f]{8}\.svg$/ );
		if ( ! m ) { return Promise.resolve( false ); }
		return fetch( url, { credentials: 'same-origin' } ).then( function ( r ) { return r.ok ? r.text() : ''; } ).then( function ( t ) {
			if ( ! t ) { return false; }
			var col = t.match( /(?:stroke|fill)='(#[0-9a-f]{6})'/i ), op = t.match( /<g opacity='([\d.]+)'/ ), sw = t.match( /stroke-width='([\d.]+)'/ );
			var wv = t.match( /width='([\d.]+)' height='[\d.]+' viewBox='0 0 ([\d.]+) /i );
			st.slug = m[1];
			if ( col ) { st.color = col[1]; }
			if ( op ) { st.opacity = parseFloat( op[1] ); }
			if ( sw ) { st.stroke = parseFloat( sw[1] ); }
			if ( wv ) { st.scale = Math.round( parseFloat( wv[1] ) / parseFloat( wv[2] ) * 10 ) / 10; }
			return true;
		} ).catch( function () { return false; } );
	}

	function open( input ) {
		target = input;
		if ( ! ui ) { build(); }
		st.bg = fieldBg( input );
		if ( ! st.color ) { var pri = D.palette.filter( function ( c ) { return 'primary' === c.slug; } )[0]; st.color = pri ? pri.color : ( D.palette[0] ? D.palette[0].color : '#1d2327' ); }
		ui.querySelector( '.ds-pl-custom' ).value = st.color;
		ui.querySelector( '.ds-pl-status' ).textContent = '';
		ui.hidden = false; document.body.classList.add( 'ds-pl-open' );
		outputs();
		ui.querySelector( '.ds-pl-grid' ).innerHTML = '<p class="ds-pl-none">Loading patterns&#8230;</p>';
		st.slug = '';
		Promise.all( [ load(), current_from( input ) ] ).then( function ( r ) {
			ui.querySelector( '.ds-pl-custom' ).value = st.color; outputs(); refresh();
			var on = r[1] && ui.querySelector( '.ds-pl-tile.is-on' );
			if ( on ) { on.scrollIntoView( { block: 'center' } ); on.focus(); } else { ui.querySelector( '.ds-pl-search' ).focus(); }
		} )
			.catch( function () { ui.querySelector( '.ds-pl-grid' ).innerHTML = '<p class="ds-pl-none">Could not load the pattern library.</p>'; } );
	}
	function close() { if ( ui ) { ui.hidden = true; } document.body.classList.remove( 'ds-pl-open' ); if ( target ) { var b = target.__dsPlBtn; if ( b ) { b.focus(); } } }

	function use() {
		var p = current(); if ( ! p || ! target ) { return; }
		var btn = ui.querySelector( '.ds-pl-use' ), status = ui.querySelector( '.ds-pl-status' );
		btn.disabled = true; status.textContent = 'Saving pattern…';
		var body = new URLSearchParams( { action: D.action, nonce: D.nonce, slug: p.s, color: st.color, opacity: st.opacity, scale: st.scale, stroke: Math.min( st.stroke, p.ms || 1 ) } );
		fetch( D.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } ).then( function ( r ) {
			if ( ! r || ! r.success ) { throw new Error( 'save' ); }
			apply( target, r.data.url ); close();
		} ).catch( function ( err ) { if ( window.console ) { console.error( 'Pattern library:', err ); } status.textContent = 'Could not save the pattern. Try again.'; btn.disabled = false; } );
	}

	function fire( e ) { e.dispatchEvent( new Event( 'input', { bubbles: true } ) ); e.dispatchEvent( new Event( 'change', { bubbles: true } ) ); }
	/** A tiled pattern wants Repeat = Tile and Size = Auto; set them the way a click would. */
	function tile( input ) {
		[ [ '_repeat]', 'repeat' ], [ '_size]', 'auto' ] ].forEach( function ( pair ) {
			var name = ( input.getAttribute( 'name' ) || '' ).replace( /_image\]$/, pair[0] );
			var radio = document.querySelector( 'input[type=radio][name="' + name + '"][value="' + pair[1] + '"]' );
			if ( radio ) { if ( ! radio.checked ) { radio.checked = true; fire( radio ); } return; }
			var sel = document.querySelector( 'select[name="' + name + '"]' );
			if ( sel && sel.querySelector( 'option[value="' + pair[1] + '"]' ) && sel.value !== pair[1] ) { sel.value = pair[1]; fire( sel ); }
		} );
	}

	function apply( input, url ) {
		var wrap = input.closest( '.dsts-img' );
		if ( wrap ) { // current Theme Setting: mirror its Media.set()
			input.value = url;
			var id = wrap.querySelector( '.dsts-img-id' ); if ( id ) { id.value = ''; }
			var thumb = wrap.querySelector( '.dsts-img-thumb' ), img = thumb && thumb.querySelector( 'img' );
			if ( thumb && ! img ) { img = el( 'img', { alt: '' } ); thumb.insertBefore( img, thumb.firstChild ); }
			if ( img ) { img.src = url; }
			wrap.classList.add( 'has-img' );
			var rm = wrap.querySelector( '.dsts-img-remove' ); if ( rm ) { rm.hidden = false; }
			var sel = wrap.querySelector( '.dsts-img-select' ); if ( sel ) { sel.textContent = 'Replace'; }
			var row = wrap.closest( '.dsts-field' ), opts = row && row.nextElementSibling;
			if ( opts && opts.classList.contains( 'dsts-bg-opts' ) ) { opts.hidden = false; }
		} else { // older Theme Setting (.ds-ts-media)
			var box = input.closest( '.ds-ts-media' );
			input.value = url;
			var pv = box && box.querySelector( '.ds-ts-media-preview' ); if ( pv ) { pv.src = url; pv.style.display = 'inline-block'; }
			var r2 = box && box.querySelector( '.ds-ts-media-remove' ); if ( r2 ) { r2.style.display = 'inline-block'; }
			var i2 = box && box.querySelector( '.ds-ts-media-id' ); if ( i2 ) { i2.value = ''; }
		}
		fire( input );
		tile( input );
		tileThumb( input );
	}

	/** A pattern file is one small tile: show it tiled in the field's thumbnail instead of one stretched tile. */
	function tileThumb( input ) {
		var url = input.value || '', pat = /\/ds-patterns\/[^/]+\.svg$/.test( url );
		var wrap = input.closest( '.dsts-img' );
		if ( wrap ) {
			var btn = wrap.querySelector( '.dsts-img-thumb' ), img = btn && btn.querySelector( 'img' ); if ( ! btn ) { return; }
			btn.classList.toggle( 'ds-pl-thumb', pat );
			btn.style.backgroundImage = pat ? 'url("' + url + '")' : '';
			if ( img ) { img.style.visibility = pat ? 'hidden' : ''; }
			return;
		}
		var box = input.closest( '.ds-ts-media' ), pv = box && box.querySelector( '.ds-ts-media-preview' ); if ( ! pv ) { return; }
		var sw = box.querySelector( '.ds-pl-oldthumb' );
		if ( pat ) { if ( ! sw ) { sw = el( 'span', { 'class': 'ds-pl-oldthumb', 'aria-hidden': 'true' } ); pv.parentNode.insertBefore( sw, pv ); } sw.style.backgroundImage = 'url("' + url + '")'; pv.style.display = 'none'; }
		else if ( sw ) { sw.remove(); }
	}

	function addButtons() {
		document.querySelectorAll( 'input.dsts-img-url, input.ds-ts-media-url' ).forEach( function ( input ) {
			var name = input.getAttribute( 'name' ) || '';
			if ( input.__dsPlBtn || ! FIELD_RE.test( name ) || SKIP_RE.test( name ) ) { return; }
			var b = el( 'button', { type: 'button', 'class': 'button ds-pl-open-btn' } ); b.textContent = 'Browse patterns';
			b.addEventListener( 'click', function ( e ) { e.preventDefault(); open( input ); } );
			var actions = input.closest( '.dsts-img' ) ? input.closest( '.dsts-img' ).querySelector( '.dsts-img-actions' ) : null;
			var oldSel = input.closest( '.ds-ts-media' ) ? input.closest( '.ds-ts-media' ).querySelector( '.ds-ts-media-select' ) : null;
			if ( actions ) { actions.insertBefore( b, actions.querySelector( '.dsts-img-remove' ) ); }
			else if ( oldSel ) { oldSel.parentNode.insertBefore( b, oldSel.nextSibling ); }
			else { return; }
			input.__dsPlBtn = b;
			tileThumb( input );
			input.addEventListener( 'change', function () { tileThumb( input ); } ); // Replace / Remove through the Media Library
		} );
	}

	if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', addButtons ); } else { addButtons(); }
} )();
