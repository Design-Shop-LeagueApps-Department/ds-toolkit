/**
 * Content Router — builder-UI helper.
 * Adds an "Edit template" button inside each Route popup that opens the
 * currently-selected saved template in a NEW TAB (launches Beaver Builder).
 */
( function ( $ ) {

	function templateEditUrl( id ) {
		return window.location.origin + '/wp-admin/post.php?post=' + parseInt( id, 10 ) + '&action=edit';
	}

	// Delegated click — works no matter when the Route popup is rendered.
	$( document ).on( 'click', '.ds-cr-edit-tpl', function ( e ) {
		e.preventDefault();
		var $scope = $( this ).closest( '.fl-builder-settings' );
		var id = $scope.find( 'select[name="cr_template"]' ).val();
		if ( ! id || '0' === String( id ) ) {
			window.alert( 'Pick a saved template first, then click Edit.' );
			return;
		}
		window.open( templateEditUrl( id ), '_blank', 'noopener' );
	} );

	// Enable/disable + label the button as the select changes.
	$( document ).on( 'change keyup', 'select[name="cr_template"]', function () {
		var id = $( this ).val();
		var $btn = $( this ).closest( '.fl-builder-settings' ).find( '.ds-cr-edit-tpl' );
		if ( ! $btn.length ) { return; }
		$btn.prop( 'disabled', ! id || '0' === String( id ) );
	} );

} )( jQuery );
