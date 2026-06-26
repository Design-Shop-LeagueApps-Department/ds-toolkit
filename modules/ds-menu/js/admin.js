/**
 * DS Menu — "Mega Items" pill picker (builder UI only).
 *
 * Draws a clickable / searchable list of the selected menu's top-level items
 * inside the module settings. Clicking a pill marks that item as a mega panel;
 * a small −/+ stepper sets its column count. The selection is written as JSON
 * into the hidden `mega_items` field, e.g. [{ "id": 123, "cols": 4 }, ...].
 *
 * Keeps everything inside Beaver Builder: no Appearance → Menus, no CSS classes.
 */
( function ( $ ) {
	'use strict';

	var CACHE = {}; // menuId -> [ { id, title, has_children } ]

	function cfg() {
		return ( typeof window.DSMenuPicker !== 'undefined' ) ? window.DSMenuPicker : null;
	}

	// Value format: a plain string of "id:cols" pairs joined by commas, e.g.
	// "56410:0,56415:4" (cols 0 = use the default). NOT JSON — Beaver's save path
	// json_decode_deep's JSON-looking values into arrays a text input can't restore.
	function readStore( $input ) {
		var v = ( $input.val() || '' ).trim();
		if ( ! v ) { return []; }
		if ( v.charAt( 0 ) === '[' ) { // legacy JSON value
			try {
				var a = JSON.parse( v );
				if ( Array.isArray( a ) ) {
					return a.map( function ( r ) { return { id: parseInt( r.id, 10 ), cols: parseInt( r.cols, 10 ) || 0 }; } )
						.filter( function ( r ) { return r.id; } );
				}
			} catch ( e ) {}
			return [];
		}
		return v.split( ',' ).map( function ( tok ) {
			var p = tok.split( ':' ); var id = parseInt( p[ 0 ], 10 );
			return id ? { id: id, cols: parseInt( p[ 1 ], 10 ) || 0 } : null;
		} ).filter( Boolean );
	}

	function writeStore( $input, rows ) {
		var str = rows.map( function ( r ) { return r.id + ':' + ( r.cols > 0 ? r.cols : 0 ); } ).join( ',' );
		$input.val( str );
		// Let Beaver Builder know the field changed (marks dirty + refreshes preview).
		$input.trigger( 'input' ).trigger( 'change' );
	}

	function findRow( rows, id ) {
		for ( var i = 0; i < rows.length; i++ ) {
			if ( parseInt( rows[ i ].id, 10 ) === parseInt( id, 10 ) ) { return rows[ i ]; }
		}
		return null;
	}

	function defaultCols( $form ) {
		var c = parseInt( $form.find( 'input[name="mega_columns"]' ).val(), 10 );
		return ( c && c > 0 ) ? c : 3;
	}

	function render( $form, items ) {
		var $picker = $form.find( '.ds-mega-picker' );
		var $pills  = $picker.find( '.ds-mega-picker-pills' );
		var $store  = $form.find( 'input[name="mega_items"]' );
		var rows    = readStore( $store );
		$pills.empty();
		$picker.removeAttr( 'data-loading' );

		if ( ! items || ! items.length ) {
			$pills.html( '<span class="ds-mega-picker-empty">' +
				'No top-level items found for this menu.</span>' );
			return;
		}

		items.forEach( function ( it ) {
			var row = findRow( rows, it.id );
			var on  = !! row;
			var $pill = $( '<button type="button" class="ds-mega-pill"></button>' )
				.attr( 'data-id', it.id )
				.attr( 'title', it.title );
			$pill.append( $( '<span class="ds-mega-pill-label"></span>' ).text( it.title ) );

			if ( ! it.has_children ) {
				$pill.addClass( 'is-disabled' ).attr( 'title', it.title + ' — no sub-items, cannot be a mega panel' );
			} else {
				if ( on ) { $pill.addClass( 'is-on' ); }
				var cols = ( row && row.cols > 0 ) ? row.cols : defaultCols( $form );
				var $cols = $( '<span class="ds-mega-pill-cols" aria-hidden="true"></span>' );
				$cols.append( '<button type="button" class="ds-mega-col-dn" tabindex="-1">−</button>' );
				$cols.append( $( '<span class="ds-mega-col-val"></span>' ).text( cols ) );
				$cols.append( '<button type="button" class="ds-mega-col-up" tabindex="-1">+</button>' );
				$pill.append( $cols );
			}
			$pills.append( $pill );
		} );
	}

	function fetchItems( $form, cb ) {
		var c = cfg();
		var menuId = $form.find( 'select[name="menu"]' ).val() || '';
		var $picker = $form.find( '.ds-mega-picker' );
		if ( ! c || ! menuId ) {
			$picker.removeAttr( 'data-loading' );
			$picker.find( '.ds-mega-picker-pills' ).html(
				'<span class="ds-mega-picker-empty">Select a WordPress menu first (General → Menu).</span>' );
			return;
		}
		if ( CACHE[ menuId ] ) { cb( CACHE[ menuId ] ); return; }
		$picker.attr( 'data-loading', '1' );
		$.post( c.ajaxurl, { action: 'ds_menu_top_items', nonce: c.nonce, menu: menuId } )
			.done( function ( res ) {
				var items = ( res && res.success && Array.isArray( res.data ) ) ? res.data : [];
				CACHE[ menuId ] = items;
				cb( items );
			} )
			.fail( function () { cb( [] ); } );
	}

	function bind( $form ) {
		var $picker = $form.find( '.ds-mega-picker' );
		if ( ! $picker.length || $picker.data( 'dsBound' ) ) { return; }
		$picker.data( 'dsBound', true );

		// Toggle a pill on/off.
		$picker.on( 'click', '.ds-mega-pill', function ( e ) {
			var $pill = $( this );
			if ( $pill.hasClass( 'is-disabled' ) ) { return; }
			// Stepper clicks are handled separately.
			if ( $( e.target ).closest( '.ds-mega-pill-cols' ).length ) { return; }
			var $store = $form.find( 'input[name="mega_items"]' );
			var rows = readStore( $store );
			var id = parseInt( $pill.attr( 'data-id' ), 10 );
			var existing = findRow( rows, id );
			if ( existing ) {
				rows = rows.filter( function ( r ) { return parseInt( r.id, 10 ) !== id; } );
				$pill.removeClass( 'is-on' );
			} else {
				rows.push( { id: id, cols: 0 } ); // 0 = use default columns
				$pill.addClass( 'is-on' );
				$pill.find( '.ds-mega-col-val' ).text( defaultCols( $form ) );
			}
			writeStore( $store, rows );
		} );

		// Column stepper.
		$picker.on( 'click', '.ds-mega-col-up, .ds-mega-col-dn', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var $pill = $( this ).closest( '.ds-mega-pill' );
			var $store = $form.find( 'input[name="mega_items"]' );
			var rows = readStore( $store );
			var id = parseInt( $pill.attr( 'data-id' ), 10 );
			var row = findRow( rows, id );
			if ( ! row ) { return; }
			var cur = ( row.cols > 0 ) ? row.cols : defaultCols( $form );
			cur += $( this ).hasClass( 'ds-mega-col-up' ) ? 1 : -1;
			cur = Math.max( 1, Math.min( 6, cur ) );
			row.cols = cur;
			$pill.find( '.ds-mega-col-val' ).text( cur );
			writeStore( $store, rows );
		} );

		// Search filter.
		$picker.on( 'input', '.ds-mega-picker-search', function () {
			var q = ( this.value || '' ).toLowerCase();
			$picker.find( '.ds-mega-pill' ).each( function () {
				var t = ( $( this ).attr( 'title' ) || '' ).toLowerCase();
				$( this ).toggle( t.indexOf( q ) !== -1 );
			} );
		} );

		// Re-fetch when the chosen WordPress menu changes.
		$form.on( 'change', 'select[name="menu"]', function () {
			fetchItems( $form, function ( items ) { render( $form, items ); } );
		} );
	}

	function init() {
		var $form = $( '.fl-builder-settings:visible' ).first();
		if ( ! $form.length ) { return; }
		if ( ! $form.find( '.ds-mega-picker' ).length ) { return; } // not the menu module
		bind( $form );
		fetchItems( $form, function ( items ) { render( $form, items ); } );
	}

	function boot() {
		if ( typeof window.FLBuilder === 'undefined' || ! window.FLBuilder || typeof window.FLBuilder.addHook !== 'function' ) {
			window.setTimeout( boot, 150 );
			return;
		}
		window.FLBuilder.addHook( 'settings-form-init', function () {
			[ 0, 120, 400 ].forEach( function ( d ) { window.setTimeout( init, d ); } );
		} );
	}

	boot();
} )( jQuery );
