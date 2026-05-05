<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DS_Global_JS {

    public function init() {
        add_action( 'wp_footer', array( $this, 'output_js' ), 99 );
    }

    public function output_js() {
        // Static-cache so multiple wp_footer calls in the same request don't re-read from disk.
        static $js = null;
        if ( $js === null ) {
            $js = (string) file_get_contents( DS_TOOLKIT_PATH . 'includes/defaults/global-js.js' );
        }
        echo '<script id="ds-toolkit-global-js">' . "\n" . $js . "\n" . '</script>' . "\n";
    }
}
