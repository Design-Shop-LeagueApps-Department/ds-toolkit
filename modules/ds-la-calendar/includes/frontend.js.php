<?php
/** @var string $id */
defined( 'ABSPATH' ) || exit;
?>
(function(){
	function go(){ if ( window.DSLACalendar ) { document.querySelectorAll('.fl-node-<?php echo esc_js( $id ); ?> .dslc').forEach( window.DSLACalendar.init ); } }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', go ); } else { go(); }
})();
