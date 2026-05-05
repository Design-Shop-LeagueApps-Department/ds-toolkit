<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DS_ACF_CSS_Vars {

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    const CACHE_KEY   = 'acf_css_vars_style';
    const CACHE_GROUP = 'ds_toolkit';

    public function init() {
        add_action( 'wp_head', array( $this, 'output_css_vars' ), 1 );
        // Bust the cached <style> block whenever the ACF options page is saved.
        add_action( 'acf/save_post', array( __CLASS__, 'flush_cache' ), 20 );
    }

    public static function flush_cache() {
        wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
    }

    public function output_css_vars() {
        if ( ! function_exists( 'get_field' ) ) {
            return;
        }

        $cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );
        if ( false !== $cached ) {
            if ( $cached !== '' ) {
                echo $cached;
            }
            return;
        }

        $mappings = ! empty( $this->settings['acf_css_vars_mappings'] )
            ? $this->settings['acf_css_vars_mappings']
            : array();

        if ( empty( $mappings ) ) {
            wp_cache_set( self::CACHE_KEY, '', self::CACHE_GROUP, HOUR_IN_SECONDS );
            return;
        }

        $css = '';

        foreach ( $mappings as $mapping ) {
            $acf_field = ! empty( $mapping['acf_field'] ) ? sanitize_key( $mapping['acf_field'] ) : '';
            $css_var   = ! empty( $mapping['css_var'] )   ? sanitize_text_field( $mapping['css_var'] ) : '';
            $fallback  = ! empty( $mapping['fallback'] )  ? sanitize_text_field( $mapping['fallback'] ) : '';

            if ( ! $acf_field || ! $css_var ) {
                continue;
            }

            $value = get_field( $acf_field, 'option' );

            if ( empty( $value ) && $fallback ) {
                $value = $fallback;
            }

            if ( ! $value ) {
                continue;
            }

            $css .= esc_attr( $css_var ) . ':' . esc_attr( $value ) . ';';
        }

        $output = $css ? '<style id="dst-acf-css-vars">:root{' . $css . '}</style>' . "\n" : '';
        wp_cache_set( self::CACHE_KEY, $output, self::CACHE_GROUP, HOUR_IN_SECONDS );

        if ( $output !== '' ) {
            echo $output;
        }
    }
}
