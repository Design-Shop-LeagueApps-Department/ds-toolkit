<?php
/** @var string $id */
defined( 'ABSPATH' ) || exit;
?>
(function(){
	function go(){ if ( window.DSLARoster ) { document.querySelectorAll('.fl-node-<?php echo esc_js( $id ); ?> .dsr').forEach( window.DSLARoster.init ); } }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', go ); } else { go(); }
})();
