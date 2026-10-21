<?php
/** @var string $id */
defined( 'ABSPATH' ) || exit;
?>
(function(){
	function go(){ if ( window.DSLASchedule ) { document.querySelectorAll('.fl-node-<?php echo esc_js( $id ); ?> .dss').forEach( window.DSLASchedule.init ); } }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', go ); } else { go(); }
})();
