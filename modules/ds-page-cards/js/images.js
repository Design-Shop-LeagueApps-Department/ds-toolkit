/**
 * Page Cards > Page Images (DS_Page_Cards): the child pages the module shows, each with its featured image and
 * Change / Remove. A change saves to that page at once and refreshes the builder preview, so a partner never leaves
 * Beaver Builder to set a card image.
 */
( function ( $ ) {
	'use strict';
	var C = window.DSPageCardsImages;
	if ( ! C ) { return; }
	var frame = null, target = null;

	function esc( s ) { return String( s ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } ); }
	function post( action, data ) {
		return $.post( C.ajax, $.extend( { action: action, nonce: C.nonce, post_id: C.postId }, data ) ).then( function ( r ) {
			if ( r && r.success ) { return r.data; }
			return $.Deferred().reject( new Error( ( r && r.data && r.data.message ) || 'Something went wrong.' ) ).promise();
		}, function () { return $.Deferred().reject( new Error( 'The server did not answer. Try again.' ) ).promise(); } );
	}

	function Panel( form, el ) {
		this.form = form; this.$el = $( el ); var self = this, t = null;
		form.on( 'change input', 'select[name="list_source"], select[name="source"], input[name="parent_page"], input[name="limit"], select[name="order_by"], select[name="order"]', function () {
			clearTimeout( t ); t = setTimeout( function () { self.load(); }, 400 );
		} );
		form.on( 'change', 'select[name="show_image"], select[name="card_source"]', function () { if ( self.pages ) { self.render(); } } );
		this.$el.on( 'click', '[data-act]', function ( e ) {
			e.preventDefault();
			var id = +$( this ).closest( '.ds-pci-row' ).attr( 'data-id' ), act = this.getAttribute( 'data-act' );
			if ( 'change' === act ) { self.pick( id ); } else if ( 'remove' === act ) { self.set( id, 0 ); }
		} );
		this.load();
	}
	Panel.prototype.val = function ( n ) { var f = this.form.find( '[name="' + n + '"]' ).first(); return f.length ? String( f.val() || '' ) : ''; };
	Panel.prototype.msg = function ( t ) { this.$el.html( '<p class="ds-pci-msg">' + esc( t ) + '</p>' ); };
	Panel.prototype.load = function () {
		var self = this, q = {};
		[ 'list_source', 'source', 'limit', 'order_by', 'order' ].forEach( function ( n ) { q[ n ] = self.val( n ); } );
		q.parent_page = ( self.val( 'parent_page' ).match( /\d+/ ) || [ '' ] )[0];
		if ( 'manual' === q.list_source ) { return; }
		this.msg( 'Loading pages…' );
		post( 'ds_pc_images', { q: q } ).then( function ( d ) { self.pages = d.pages; self.render(); }, function ( e ) { self.msg( e.message ); } );
	};
	Panel.prototype.rowHtml = function ( p ) {
		return '<li class="ds-pci-row" data-id="' + p.id + '">' +
			'<span class="ds-pci-thumb' + ( p.thumb ? '' : ' is-empty' ) + '"' + ( p.thumb ? ' style="background-image:url(&quot;' + esc( p.thumb ) + '&quot;)"' : '' ) + '>' + ( p.thumb ? '' : 'No image' ) + '</span>' +
			'<span class="ds-pci-main"><span class="ds-pci-title">' + esc( p.title ) + '</span>' +
			( p.canEdit
				? '<span class="ds-pci-acts"><button type="button" class="ds-pci-btn" data-act="change">' + ( p.img ? 'Change image' : 'Add image' ) + '</button>' + ( p.img ? '<button type="button" class="ds-pci-link" data-act="remove">Remove</button>' : '' ) + '</span>'
				: '<span class="ds-pci-note">You cannot edit this page.</span>' ) +
			'<span class="ds-pci-status" role="status"></span></span></li>';
	};
	Panel.prototype.render = function () {
		if ( ! this.pages || ! this.pages.length ) { this.msg( 'This page has no published child pages yet.' ); return; }
		var off = 'template' !== this.val( 'card_source' ) && 'yes' !== this.val( 'show_image' );
		this.$el.html( ( off ? '<p class="ds-pci-note ds-pci-off">These cards hide images. Turn on Show Image (What Each Card Shows) to show them.</p>' : '' ) + '<ul class="ds-pci-list">' + this.pages.map( this.rowHtml ).join( '' ) + '</ul>' );
	};
	Panel.prototype.pick = function ( id ) {
		var self = this, page = ( this.pages || [] ).filter( function ( p ) { return p.id === id; } )[0];
		if ( ! window.wp || ! wp.media ) { window.alert( 'The Media Library is not available here.' ); return; }
		target = { panel: this, id: id };
		if ( ! frame ) {
			frame = wp.media( { title: 'Card image', library: { type: 'image' }, button: { text: 'Use this image' }, multiple: false } );
			frame.on( 'open', function () { frame.content.mode( 'browse' ); var sel = frame.state().get( 'selection' ), p = target && ( target.panel.pages || [] ).filter( function ( x ) { return x.id === target.id; } )[0]; sel.reset( p && p.img ? [ wp.media.attachment( p.img ) ] : [] ); } );
			frame.on( 'select', function () { var a = frame.state().get( 'selection' ).first(); if ( a && target ) { target.panel.set( target.id, a.get( 'id' ) ); } } );
		}
		frame.options.title = 'Card image: ' + ( page ? page.title : '' );
		frame.open();
	};
	Panel.prototype.set = function ( id, img ) {
		var self = this, $row = this.$el.find( '.ds-pci-row[data-id="' + id + '"]' );
		$row.addClass( 'is-busy' ).find( '.ds-pci-status' ).text( 'Saving…' );
		post( 'ds_pc_set_image', { page_id: id, attachment_id: img } ).then( function ( p ) {
			self.pages = self.pages.map( function ( x ) { return x.id === id ? p : x; } );
			var $new = $( self.rowHtml( p ) ); $row.replaceWith( $new ); $new.find( '.ds-pci-status' ).text( img ? 'Saved to the page' : 'Image removed' );
			// Show the new card image in the builder right away (the image is not a module setting, so nothing else would).
			if ( window.FLBuilder && FLBuilder.preview && 'function' === typeof FLBuilder.preview.preview ) { FLBuilder.preview.preview(); }
		}, function ( e ) { $row.removeClass( 'is-busy' ).find( '.ds-pci-status' ).addClass( 'is-error' ).text( e.message ); } );
	};

	function boot() {
		$( '.fl-builder-settings:visible' ).each( function () {
			var form = $( this );
			form.find( '[data-ds-page-images]' ).each( function () { if ( ! this.dsPci ) { this.dsPci = new Panel( form, this ); } } );
		} );
	}
	( function hook() {
		if ( ! window.FLBuilder || 'function' !== typeof window.FLBuilder.addHook ) { setTimeout( hook, 150 ); return; }
		window.FLBuilder.addHook( 'settings-form-init', function () { [ 0, 120, 400 ].forEach( function ( t ) { setTimeout( boot, t ); } ); } );
	} )();
} )( jQuery );
