<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [ds_copyright] shortcode.
 *
 * Outputs the footer copyright line configured on the toolkit General tab
 * (`footer_copyright_text`). The literal token `{year}` is replaced with the
 * current year so the notice stays current without manual edits. Blueprint
 * generation 6+ only.
 *
 * Usage:  [ds_copyright]
 *         [ds_copyright text="© {year} Custom Org"]   (per-use override)
 */
class DS_Copyright {

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function init() {
        add_shortcode( 'ds_copyright', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'text' => '',
            ),
            $atts,
            'ds_copyright'
        );

        $text = $atts['text'] !== ''
            ? $atts['text']
            : ( isset( $this->settings['footer_copyright_text'] ) ? $this->settings['footer_copyright_text'] : '' );

        if ( '' === trim( (string) $text ) ) {
            return '';
        }

        $text = str_replace( '{year}', date( 'Y' ), $text );

        // Allow only the small set of inline tags a copyright line needs.
        return wp_kses(
            $text,
            array(
                'a'    => array( 'href' => array(), 'title' => array(), 'target' => array(), 'rel' => array() ),
                'span' => array( 'class' => array() ),
                'br'   => array(),
                'strong' => array(),
                'em'   => array(),
            )
        );
    }
}
