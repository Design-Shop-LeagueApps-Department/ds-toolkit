/* global dstAdmin, wp, jQuery */
/**
 * Settings > DS Toolkit. Everything here is progressive: the page saves and reads correctly without it.
 *  - Features tab: a card's own settings fold away while its feature is off; the group counts, the "jump to"
 *    counts and the Show chips (All / On / Off / Changed) follow the switches; every card that differs from
 *    what is saved gets a "Changed, not saved" tag and a line in the "Changed since the last save" panel;
 *    groups remember open / closed; the find box and the chips filter the cards; the "all modules" switch;
 *    the save bar counts unsaved changes, offers Discard, and warns before leaving with any.
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
	var cards  = [].slice.call( form.querySelectorAll( '.dst-group .dst-card[data-dst-card]' ) );
	function each( list, fn ) { [].slice.call( list ).forEach( fn ); }
	function switchOf( card ) { return document.getElementById( card.getAttribute( 'data-dst-card' ) ); }
	function titleOf( card ) { var t = card.querySelector( '.dst-card-title strong' ); return t ? t.textContent.trim() : ''; }

	// ---------------------------------------------------------------- saved values
	// A setting's value as the form would post it. The "0" twin of a checkbox is skipped (the checkbox speaks
	// for both), except the login logo id, which is a real hidden setting.
	function counts( el ) {
		return el.name && 0 === el.name.indexOf( 'ds_toolkit_settings[' ) && ! ( 'hidden' === el.type && 'dst-logo-id' !== el.id );
	}
	function valueOf( el ) {
		if ( 'checkbox' === el.type ) { return el.checked ? '1' : '0'; }
		return el.value;
	}
	function snapshot() {
		var out = {};
		each( form.elements, function ( el ) {
			if ( ! counts( el ) ) { return; }
			if ( 'radio' === el.type ) { if ( el.checked ) { out[ el.name ] = el.value; } return; }
			out[ el.name ] = valueOf( el );
		} );
		return out;
	}
	var saved = snapshot();
	function fieldChanged( el ) {
		if ( ! counts( el ) ) { return false; }
		if ( 'radio' === el.type ) { return el.checked && saved[ el.name ] !== el.value; }
		return saved[ el.name ] !== valueOf( el );
	}
	function cardChanged( card ) {
		var hit = false;
		each( card.querySelectorAll( 'input, select, textarea' ), function ( el ) { if ( fieldChanged( el ) ) { hit = true; } } );
		if ( ! hit ) { // a mapping row removed leaves a saved name with no field
			var names = {};
			each( card.querySelectorAll( '[name]' ), function ( el ) { names[ el.name ] = 1; } );
			if ( 'acf_css_vars_enabled' === card.getAttribute( 'data-dst-card' ) ) {
				Object.keys( saved ).forEach( function ( k ) { if ( 0 === k.indexOf( 'ds_toolkit_settings[acf_css_vars_mappings]' ) && ! names[ k ] ) { hit = true; } } );
			}
		}
		return hit;
	}
	function changes() {
		var now = snapshot(), n = 0, k;
		for ( k in now ) { if ( now[ k ] !== saved[ k ] ) { n++; } }
		for ( k in saved ) { if ( ! ( k in now ) ) { n++; } }
		return n;
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

	// ---------------------------------------------------------------- the find box and the Show chips
	var input = document.getElementById( 'dst-filter' );
	var empty = document.getElementById( 'dst-filter-empty' );
	var chips = [].slice.call( form.querySelectorAll( '[data-dst-show]' ) );
	var mode  = 'all';
	var filtering = false;
	function applyFilter() {
		var q = input ? input.value.trim().toLowerCase() : '', any = false, narrowed = !! q || 'all' !== mode;
		filtering = true;
		cards.forEach( function ( card ) {
			var sw   = switchOf( card );
			var on   = sw ? sw.checked : true;
			var pass = 'all' === mode || ( 'on' === mode && on ) || ( 'off' === mode && ! on ) || ( 'changed' === mode && card.classList.contains( 'is-changed' ) );
			card.hidden = ! pass || ( !! q && -1 === card.textContent.toLowerCase().indexOf( q ) );
		} );
		groups.forEach( function ( g ) {
			var visible = [].slice.call( g.querySelectorAll( '.dst-card[data-dst-card]' ) ).some( function ( c ) { return ! c.hidden; } );
			g.hidden = ! visible;
			var allRow = g.querySelector( '.dst-modules-all' );
			if ( allRow ) { allRow.hidden = narrowed; } // "all modules" switches every block, so it shows only when every block does
			if ( visible ) { any = true; }
			g.open = narrowed ? visible : '1' === g.getAttribute( 'data-dst-open' );
		} );
		if ( empty ) { empty.hidden = any; }
		window.setTimeout( function () { filtering = false; }, 50 ); // the toggle events fire after this handler
	}
	if ( input ) { input.addEventListener( 'input', applyFilter ); }
	function setMode( m ) {
		mode = m;
		chips.forEach( function ( c ) {
			var active = c.getAttribute( 'data-dst-show' ) === m;
			c.classList.toggle( 'is-active', active );
			c.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
		applyFilter();
	}
	chips.forEach( function ( c ) { c.addEventListener( 'click', function () { setMode( c.getAttribute( 'data-dst-show' ) ); } ); } );
	var reset = document.getElementById( 'dst-filter-reset' );
	if ( reset ) {
		reset.addEventListener( 'click', function () {
			if ( input ) { input.value = ''; }
			setMode( 'all' );
		} );
	}

	// ---------------------------------------------------------------- counts, tags, the list of changes, the save bar
	var stateEl  = document.getElementById( 'dst-savebar-state' );
	var bar      = document.getElementById( 'dst-savebar' );
	var discard  = document.getElementById( 'dst-discard' );
	var list     = document.getElementById( 'dst-changed-list' );
	var none     = document.getElementById( 'dst-changed-none' );
	var onTotal  = document.getElementById( 'dst-on-total' );
	function setText( sel, text ) { var el = form.querySelector( sel ); if ( el ) { el.textContent = text; } }
	function refresh() {
		syncAll();
		var tally = { all: 0, on: 0, off: 0, changed: 0 }, changedCards = [];
		cards.forEach( function ( card ) {
			var sw      = switchOf( card );
			var on      = sw ? sw.checked : true;
			var changed = cardChanged( card );
			card.classList.toggle( 'is-off', ! on );
			card.classList.toggle( 'is-changed', changed );
			var tag = card.querySelector( '[data-dst-changed]' );
			if ( tag ) { tag.hidden = ! changed; }
			tally.all++;
			tally[ on ? 'on' : 'off' ]++;
			if ( changed ) {
				tally.changed++;
				changedCards.push( { card: card, text: sw && sw.checked !== ( '1' === saved[ sw.name ] ) ? ( on ? 'On' : 'Off' ) : 'Edited' } );
			}
		} );
		groups.forEach( function ( g ) {
			var switches = [].slice.call( g.querySelectorAll( '.dst-card[data-dst-card] > .dst-card-row > .dst-toggle input[type="checkbox"][name]' ) );
			var label    = switches.filter( function ( c ) { return c.checked; } ).length + ' of ' + switches.length + ' on';
			var count    = g.querySelector( '[data-dst-count]' );
			if ( count && switches.length ) { count.textContent = label; }
			setText( '[data-dst-jump-count="' + g.getAttribute( 'data-dst-group' ) + '"]', switches.filter( function ( c ) { return c.checked; } ).length + '/' + switches.length );
		} );
		Object.keys( tally ).forEach( function ( k ) { setText( '[data-dst-n="' + k + '"]', String( tally[ k ] ) ); } );
		if ( onTotal ) { onTotal.textContent = tally.on + ' of ' + tally.all; }

		if ( list && none ) {
			list.textContent = '';
			changedCards.forEach( function ( c ) {
				var li = document.createElement( 'li' );
				var a  = document.createElement( 'a' );
				var b  = document.createElement( 'strong' );
				a.href = '#' + ( switchOf( c.card ) ? switchOf( c.card ).id : '' );
				a.textContent = titleOf( c.card );
				a.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					var g = c.card.closest( 'details.dst-group' );
					if ( g ) { g.open = true; }
					c.card.scrollIntoView( { block: 'center' } );
					var sw = switchOf( c.card );
					if ( sw ) { sw.focus( { preventScroll: true } ); }
				} );
				b.textContent = c.text;
				li.appendChild( a );
				li.appendChild( b );
				list.appendChild( li );
			} );
			list.hidden = ! changedCards.length;
			none.hidden = !! changedCards.length;
		}

		var n = changes();
		if ( stateEl ) { stateEl.textContent = n ? n + ( 1 === n ? ' unsaved change' : ' unsaved changes' ) : 'No unsaved changes'; }
		if ( bar ) { bar.classList.toggle( 'is-dirty', n > 0 ); }
		if ( discard ) { discard.hidden = ! n; }
		if ( 'all' !== mode ) { applyFilter(); } // "Changed", "On" and "Off" follow the switches as they move
		return n;
	}
	var submitting = false;
	form.addEventListener( 'change', refresh );
	// Typing updates as it goes. A switch is left to its change event: its input event fires first, and a
	// refresh there would set the "all modules" switch back from the blocks before its own handler ran.
	form.addEventListener( 'input', function ( e ) {
		if ( 'checkbox' === e.target.type || 'radio' === e.target.type ) { return; }
		refresh();
	} );
	form.addEventListener( 'submit', function () { submitting = true; } );
	// A value the browser refuses (a number out of range, a broken address) stops the save. When that box sits
	// in a closed group, a closed Advanced fold or a card the filter hid, the browser cannot point at it and Save
	// looked dead (lvlelitetravel, 1.18.0). Open the way to the first one and show its message there.
	var revealing = false;
	form.addEventListener( 'invalid', function ( e ) {
		if ( revealing ) { return; }
		revealing = true;
		var el = e.target, p;
		for ( p = el.parentNode; p && p !== form; p = p.parentNode ) {
			if ( p.hidden ) { if ( input ) { input.value = ''; } setMode( 'all' ); break; }
		}
		for ( p = el.parentNode; p && p !== form; p = p.parentNode ) {
			if ( 'DETAILS' === p.tagName ) { p.open = true; }
		}
		window.setTimeout( function () {
			revealing = false;
			el.scrollIntoView( { block: 'center' } );
			el.focus( { preventScroll: true } );
			if ( el.reportValidity ) { el.reportValidity(); }
		}, 0 );
	}, true );
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( ! submitting && changes() > 0 ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );
	if ( discard ) {
		// Discard = load the saved page again (a mapping row added or removed cannot be undone field by field).
		discard.addEventListener( 'click', function () {
			submitting = true;
			window.location.href = window.location.href.split( '#' )[ 0 ];
		} );
	}

	// ---------------------------------------------------------------- groups remember open / closed
	function storeGet( key ) { try { return window.localStorage.getItem( key ); } catch ( e ) { return null; } }
	function storeSet( key, v ) { try { window.localStorage.setItem( key, v ); } catch ( e ) {} }
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

	// ---------------------------------------------------------------- jump to a group
	function openGroup( id ) {
		var g = id ? document.getElementById( id ) : null;
		if ( ! g || ! g.classList.contains( 'dst-group' ) ) { return false; }
		if ( g.hidden ) { // filtered away: show everything again first
			if ( input ) { input.value = ''; }
			setMode( 'all' );
		}
		g.open = true;
		return g;
	}
	each( form.querySelectorAll( '[data-dst-jump]' ), function ( a ) {
		var g = document.getElementById( 'dst-g-' + a.getAttribute( 'data-dst-jump' ) );
		if ( ! g ) { a.hidden = true; return; } // a group this site does not show
		a.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var target = openGroup( g.id );
			if ( target ) {
				target.scrollIntoView( { block: 'start' } );
				target.querySelector( 'summary' ).focus( { preventScroll: true } );
			}
		} );
	} );
	if ( window.location.hash && 0 === window.location.hash.indexOf( '#dst-g-' ) ) {
		var landing = openGroup( window.location.hash.slice( 1 ) ); // e.g. an old "General" tab bookmark
		if ( landing ) { landing.scrollIntoView( { block: 'start' } ); }
	}

	refresh();
}( jQuery ) );
