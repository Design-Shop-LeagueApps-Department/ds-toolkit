/* global dstAdmin, wp, jQuery */
/**
 * Settings > DS Toolkit. Everything here is progressive: the page saves and reads correctly without it.
 *  - Features tab: a card's own settings fold away while its feature is off, the group counts follow the
 *    switches, groups remember open/closed, the filter box searches every card, the "all modules" switch,
 *    and the save bar counts unsaved changes and warns before leaving with any.
 *  - The login logo picker and the ACF mapping rows.
 */
( function ( $ ) {
	'use strict';

	var form = document.getElementById( 'dst-features-form' );

	// ---------------------------------------------------------------- login logo picker
	var frame;
	$( '#dst-logo-select' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( frame ) {
			frame.open();
			return;
		}
		frame = wp.media( { title: 'Select Login Logo', button: { text: 'Use this logo' }, multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#dst-logo-id' ).val( attachment.id ).trigger( 'change' );
			$( '#dst-logo-img' ).attr( 'src', attachment.url );
			$( '#dst-logo-remove' ).prop( 'hidden', false );
		} );
		frame.open();
	} );
	$( '#dst-logo-remove' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#dst-logo-id' ).val( '' ).trigger( 'change' );
		$( '#dst-logo-img' ).attr( 'src', dstAdmin.defaultLogoUrl );
		$( this ).prop( 'hidden', true );
	} );

	// ---------------------------------------------------------------- ACF mapping rows
	function formChanged() {
		if ( form ) { form.dispatchEvent( new Event( 'change', { bubbles: true } ) ); }
	}
	$( '#dst-add-mapping' ).on( 'click', function () {
		var index = parseInt( $( this ).data( 'count' ), 10 ) || 0;
		var name  = 'ds_toolkit_settings[acf_css_vars_mappings][' + index + ']';
		var row   = '<tr class="dst-mapping-row">' +
			'<td><input type="text" class="regular-text" aria-label="ACF field name" name="' + name + '[acf_field]" placeholder="acf_field_name"></td>' +
			'<td><input type="text" class="regular-text" aria-label="CSS variable" name="' + name + '[css_var]" placeholder="--css-variable-name"></td>' +
			'<td><input type="text" class="regular-text" aria-label="Fallback value" name="' + name + '[fallback]" placeholder="optional"></td>' +
			'<td><button type="button" class="button dst-remove-mapping" aria-label="Remove this mapping">&#x2715;</button></td>' +
			'</tr>';
		$( '#dst-mappings-tbody' ).append( row );
		$( this ).data( 'count', index + 1 );
		$( '#dst-mappings-tbody tr:last input:first' ).trigger( 'focus' );
		formChanged();
	} );
	$( '#dst-mappings-tbody' ).on( 'click', '.dst-remove-mapping', function () {
		$( this ).closest( 'tr' ).remove();
		formChanged();
	} );

	if ( ! form ) {
		return; // another tab
	}

	var groups = [].slice.call( form.querySelectorAll( 'details.dst-group' ) );
	var cards  = [].slice.call( form.querySelectorAll( '.dst-group .dst-card' ) );

	// ---------------------------------------------------------------- card on/off + group counts
	function refreshCounts() {
		cards.forEach( function ( card ) {
			var key = card.getAttribute( 'data-dst-card' );
			var sw  = 'modules' === key ? null : document.getElementById( key );
			if ( sw ) {
				card.classList.toggle( 'is-off', ! sw.checked );
			}
		} );
		groups.forEach( function ( g ) {
			var switches = [].slice.call( g.querySelectorAll( '.dst-toggle input[type="checkbox"][name]' ) );
			var count    = g.querySelector( '[data-dst-count]' );
			if ( count && switches.length ) {
				count.textContent = switches.filter( function ( c ) { return c.checked; } ).length + ' of ' + switches.length + ' on';
			}
		} );
	}

	// ---------------------------------------------------------------- all modules
	var all  = document.getElementById( 'dst-modules-all' );
	var mods = [].slice.call( form.querySelectorAll( '.dst-module-toggle' ) );
	function syncAll() {
		if ( ! all || ! mods.length ) { return; }
		var on = mods.filter( function ( c ) { return c.checked; } ).length;
		all.checked       = ( on === mods.length );
		all.indeterminate = ( on > 0 && on < mods.length );
	}
	if ( all ) {
		all.addEventListener( 'change', function ( e ) {
			e.stopPropagation(); // handled here: set the blocks first, then refresh once
			mods.forEach( function ( c ) { c.checked = all.checked; } );
			all.indeterminate = false;
			refresh();
		} );
	}

	// ---------------------------------------------------------------- unsaved changes
	function snapshot() {
		var out = {};
		[].slice.call( form.elements ).forEach( function ( el ) {
			if ( ! el.name || 0 !== el.name.indexOf( 'ds_toolkit_settings[' ) ) { return; }
			if ( 'hidden' === el.type && 'dst-logo-id' !== el.id ) { return; } // the "0" twin of a checkbox
			if ( 'checkbox' === el.type ) { out[ el.name ] = el.checked ? '1' : '0'; return; }
			if ( 'radio' === el.type ) { if ( el.checked ) { out[ el.name ] = el.value; } return; }
			out[ el.name ] = el.value;
		} );
		return out;
	}
	var saved      = snapshot();
	var stateEl    = document.getElementById( 'dst-savebar-state' );
	var bar        = document.getElementById( 'dst-savebar' );
	var submitting = false;
	function changes() {
		var now = snapshot(), n = 0, k;
		for ( k in now ) { if ( now[ k ] !== saved[ k ] ) { n++; } }
		for ( k in saved ) { if ( ! ( k in now ) ) { n++; } }
		return n;
	}
	function refreshBar() {
		var n = changes();
		if ( stateEl ) { stateEl.textContent = n ? n + ( 1 === n ? ' unsaved change' : ' unsaved changes' ) : 'No unsaved changes'; }
		if ( bar ) { bar.classList.toggle( 'is-dirty', n > 0 ); }
		return n;
	}
	function refresh() {
		syncAll();
		refreshCounts();
		refreshBar();
	}
	form.addEventListener( 'change', refresh );
	form.addEventListener( 'input', refreshBar );
	form.addEventListener( 'submit', function () { submitting = true; } );
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( ! submitting && changes() > 0 ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );
	refresh();

	// ---------------------------------------------------------------- groups remember open / closed
	function storeGet( key ) { try { return window.localStorage.getItem( key ); } catch ( e ) { return null; } }
	function storeSet( key, v ) { try { window.localStorage.setItem( key, v ); } catch ( e ) {} }
	var filtering = false;
	groups.forEach( function ( g ) {
		var key  = 'dst-group-' + g.getAttribute( 'data-dst-group' );
		var kept = storeGet( key );
		if ( 'closed' === kept ) { g.open = false; }
		if ( 'open' === kept ) { g.open = true; }
		g.setAttribute( 'data-dst-open', g.open ? '1' : '0' ); // the state to go back to after a search
		g.addEventListener( 'toggle', function () {
			if ( filtering ) { return; } // a search opening a group is not the user's choice
			storeSet( key, g.open ? 'open' : 'closed' );
			g.setAttribute( 'data-dst-open', g.open ? '1' : '0' );
		} );
	} );

	// ---------------------------------------------------------------- filter
	var input = document.getElementById( 'dst-filter' );
	var empty = document.getElementById( 'dst-filter-empty' );
	if ( input ) {
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase(), any = false;
			filtering = true;
			cards.forEach( function ( card ) {
				// The blocks card filters row by row; every other card is one unit (its detail and settings count too).
				if ( 'modules' === card.getAttribute( 'data-dst-card' ) ) {
					var shown = 0;
					[].slice.call( card.querySelectorAll( '.dst-card-row--module' ) ).forEach( function ( row ) {
						var hit = ! q || -1 !== row.textContent.toLowerCase().indexOf( q );
						row.hidden = ! hit;
						if ( hit ) { shown++; }
					} );
					card.hidden = ! shown;
					return;
				}
				card.hidden = !! q && -1 === card.textContent.toLowerCase().indexOf( q );
			} );
			groups.forEach( function ( g ) {
				var visible = [].slice.call( g.querySelectorAll( '.dst-card' ) ).some( function ( c ) { return ! c.hidden; } );
				g.hidden = ! visible;
				if ( visible ) { any = true; }
				g.open = q ? visible : '1' === g.getAttribute( 'data-dst-open' );
			} );
			if ( empty ) { empty.hidden = any; }
			window.setTimeout( function () { filtering = false; }, 50 ); // the toggle events fire after this handler
		} );
	}
}( jQuery ) );
