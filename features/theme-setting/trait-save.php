<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme Setting: saving the form (permission, nonce, parsing typography and borders, cleaning colours).
 *
 * One part of DS_Theme_Setting, which was a single 1,620-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the feature (same visibility, same
 * $this). The hooks, the front-end CSS output and the button shapes stay in class-ds-theme-setting.php.
 */
trait DS_Theme_Setting_Save {

    /* ----------------------------------------------------------------- Save */

    public function save() {
        if ( ! $this->allowed() ) {
            wp_die( 'Not allowed.' );
        }
        check_admin_referer( self::SAVE_ACTION );
        $ajax = ! empty( $_POST['ds_ajax'] );
        if ( ! $this->available() ) {
            if ( $ajax ) { wp_send_json_error( 'Beaver Builder Global Styles are not available.' ); }
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            exit;
        }
        $post = wp_unslash( $_POST );

        FLBuilderGlobalStyles::save_settings( (object) $this->bb_settings_from_request( $post ) );

        // General -> Beaver Builder Theme customizer theme_mods (backgrounds, banner, details, custom code).
        $mods = $this->general_mods_from_request( isset( $post['general'] ) ? $post['general'] : array() );
        if ( $mods ) {
            foreach ( $mods as $key => $value ) {
                set_theme_mod( $key, $value );
            }
            if ( class_exists( 'FLCustomizer' ) && method_exists( 'FLCustomizer', 'refresh_css' ) ) {
                FLCustomizer::refresh_css();
            }
        }

        // Favicon -> WordPress Site Icon (Settings > General), stored as the attachment id.
        if ( isset( $post['favicon'] ) && is_array( $post['favicon'] ) ) {
            $fav_id = isset( $post['favicon']['id'] ) ? (int) $post['favicon']['id'] : 0;
            if ( $fav_id > 0 ) {
                update_option( 'site_icon', $fav_id );
            } else {
                delete_option( 'site_icon' );
            }
        }

        // Social Sharing Card -> options + Yoast default OG image (handled by DS_Social_Card).
        if ( isset( $post['social_card'] ) && is_array( $post['social_card'] ) && class_exists( 'DS_Social_Card' ) ) {
            $sc_id  = isset( $post['social_card']['id'] ) ? (int) $post['social_card']['id'] : 0;
            $sc_url = isset( $post['social_card']['url'] ) ? esc_url_raw( $post['social_card']['url'] ) : '';
            DS_Social_Card::set_card( $sc_id, $sc_url );
        }

        // Button shape -> standalone option, emitted on the front end.
        $bs = $this->button_style_from_request( $post );
        if ( null !== $bs ) {
            if ( '' === $bs ) {
                delete_option( self::BUTTON_STYLE_OPTION );
            } else {
                update_option( self::BUTTON_STYLE_OPTION, $bs );
            }
        }

        if ( $ajax ) {
            // The stored palette with BB's uids (a new colour gets its uid on save), so
            // the page can keep saving without creating a second copy of it.
            $this->globals_cache = null;
            $stored = array();
            foreach ( (array) ( FLBuilderGlobalStyles::get_settings( false )->colors ?? array() ) as $c ) {
                $c        = (array) $c;
                $stored[] = array( 'label' => (string) ( $c['label'] ?? '' ), 'color' => (string) ( $c['color'] ?? '' ), 'uid' => (string) ( $c['uid'] ?? '' ) );
            }
            wp_send_json_success( array(
                'colors'    => $stored,
                'globals'   => $this->globals_data()['list'],
                'prefixKey' => $this->prefix_key(),
            ) );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function parse_typography( $existing, $p ) {
        $t = array();

        $fam = isset( $p['font_family'] ) ? sanitize_text_field( $p['font_family'] ) : '';
        if ( '' !== $fam && 'Default' !== $fam ) { $t['font_family'] = $fam; }

        if ( isset( $p['font_weight'] ) && '' !== $p['font_weight'] ) {
            $t['font_weight'] = preg_replace( '/[^0-9a-z]/', '', $p['font_weight'] );
        }
        $sz = isset( $p['font_size_length'] ) ? $this->num( $p['font_size_length'] ) : '';
        if ( '' !== $sz ) { $t['font_size'] = array( 'length' => $sz, 'unit' => isset( $p['font_size_unit'] ) ? sanitize_text_field( $p['font_size_unit'] ) : 'px' ); }
        $lh = isset( $p['line_height_length'] ) ? $this->num( $p['line_height_length'] ) : '';
        if ( '' !== $lh ) { $t['line_height'] = array( 'length' => $lh, 'unit' => isset( $p['line_height_unit'] ) ? sanitize_text_field( $p['line_height_unit'] ) : '' ); }
        $ls = isset( $p['letter_spacing_length'] ) ? $this->num( $p['letter_spacing_length'] ) : '';
        if ( '' !== $ls ) { $t['letter_spacing'] = array( 'length' => $ls, 'unit' => isset( $p['letter_spacing_unit'] ) ? sanitize_text_field( $p['letter_spacing_unit'] ) : 'px' ); }
        foreach ( array( 'text_align', 'text_transform', 'text_decoration', 'font_style', 'font_variant' ) as $k ) {
            if ( isset( $p[ $k ] ) && '' !== $p[ $k ] ) { $t[ $k ] = sanitize_text_field( $p[ $k ] ); }
        }
        $shadow_color = isset( $p['shadow_color'] ) ? $this->sanitize_color( $p['shadow_color'] ) : '';
        if ( '' !== $shadow_color ) {
            $t['text_shadow'] = array(
                'color'      => $shadow_color,
                'horizontal' => isset( $p['shadow_x'] ) ? $this->num( $p['shadow_x'] ) : '',
                'vertical'   => isset( $p['shadow_y'] ) ? $this->num( $p['shadow_y'] ) : '',
                'blur'       => isset( $p['shadow_blur'] ) ? $this->num( $p['shadow_blur'] ) : '',
            );
        }

        // Nothing meaningful set -> store empty so BB's font enqueuer skips it
        // entirely (it reads ['font_family'] without an isset check on non-empty
        // typography, which is what produced the front-end warnings).
        if ( empty( $t ) ) { return ''; }
        // A non-empty typography object must always carry font_family.
        if ( ! isset( $t['font_family'] ) ) { $t['font_family'] = 'Default'; }
        return $t;
    }

    private function parse_border( $existing, $p ) {
        $b = ( is_array( $existing ) || is_object( $existing ) ) ? (array) $existing : array();
        if ( isset( $p['style'] ) ) { $b['style'] = sanitize_text_field( $p['style'] ); }
        if ( isset( $p['color'] ) ) { $b['color'] = $this->sanitize_color( $p['color'] ); }
        $b['width']  = array(
            'top'    => $this->num( $p['width_top'] ?? '' ),
            'right'  => $this->num( $p['width_right'] ?? '' ),
            'bottom' => $this->num( $p['width_bottom'] ?? '' ),
            'left'   => $this->num( $p['width_left'] ?? '' ),
        );
        $b['radius'] = array(
            'top_left'     => $this->num( $p['radius_top_left'] ?? '' ),
            'top_right'    => $this->num( $p['radius_top_right'] ?? '' ),
            'bottom_left'  => $this->num( $p['radius_bottom_left'] ?? '' ),
            'bottom_right' => $this->num( $p['radius_bottom_right'] ?? '' ),
        );
        $b['shadow'] = array(
            'color'      => isset( $p['shadow_color'] ) ? $this->sanitize_color( $p['shadow_color'] ) : '',
            'horizontal' => $this->num( $p['shadow_x'] ?? '' ),
            'vertical'   => $this->num( $p['shadow_y'] ?? '' ),
            'blur'       => $this->num( $p['shadow_blur'] ?? '' ),
        );
        return $b;
    }

    private function num( $v ) {
        $v = trim( (string) $v );
        return ( '' === $v || ! is_numeric( $v ) ) ? '' : ( $v + 0 );
    }

    private function sanitize_color( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return ''; }
        // BB's picker stores hex WITHOUT a leading '#'; normalize to #hex.
        // 4- and 8-digit hex carry the alpha channel (#rgba / #rrggbbaa) —
        // rejecting them wiped any colour saved with opacity below 100% (GH #80).
        if ( preg_match( '/^#?([A-Fa-f0-9]{3,4}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value, $m ) ) { return '#' . strtolower( $m[1] ); }
        if ( preg_match( '/^rgba?\(\s*[\d.,\s%]+\)$/i', $value ) ) { return strtolower( $value ); }
        // Synced global-color reference, e.g. var(--fl-global-primary).
        if ( preg_match( '/^var\(\s*--[A-Za-z0-9_-]+\s*\)$/', $value ) ) { return $value; }
        return (string) sanitize_hex_color( $value );
    }
}
