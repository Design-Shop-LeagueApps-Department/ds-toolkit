<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme Setting: the values behind the screen. Beaver Builder globals, the colour map, fonts, and turning a posted form into settings.
 *
 * One part of DS_Theme_Setting, which was a single 1,620-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the feature (same visibility, same
 * $this). The hooks, the front-end CSS output and the button shapes stay in class-ds-theme-setting.php.
 */
trait DS_Theme_Setting_Data {

    /* ------------------------------------------------------------ Shared data */

    /** The CSS-variable prefix exactly as Beaver Builder derives it (label_to_key, default fl-global). */
    private function prefix_key( $prefix = null ) {
        if ( null === $prefix ) {
            $s      = $this->available() ? FLBuilderGlobalStyles::get_settings( false ) : new stdClass();
            $prefix = isset( $s->prefix ) ? (string) $s->prefix : '';
        }
        $key = '' !== trim( $prefix ) ? $this->color_slug( $prefix ) : '';
        return '' !== $key ? $key : 'fl-global';
    }

    /**
     * Beaver Builder's label_to_key() for the CSS-variable slug (must match
     * how BB generates --<prefix>-<slug> for global colors).
     */
    private function color_slug( $label ) {
        if ( class_exists( 'FLBuilderGlobalStyles' ) && method_exists( 'FLBuilderGlobalStyles', 'label_to_key' ) ) {
            return FLBuilderGlobalStyles::label_to_key( (string) $label );
        }
        $label = str_replace( array( '_', ' ' ), '-', strtolower( trim( (string) $label ) ) );
        return preg_replace( '/[^A-Za-z0-9\-]/', '', $label );
    }

    /**
     * The global colors: the list the picker renders (name, colour, var(), uid),
     * plus var() -> name / colour maps for labelling and swatches. Reads the live
     * BB Global Styles, so it always matches the Colors section.
     */
    private $globals_cache = null;
    private function globals_data() {
        if ( null !== $this->globals_cache ) { return $this->globals_cache; }
        $list = array(); $byvar = array();
        if ( $this->available() ) {
            $s      = FLBuilderGlobalStyles::get_settings( false );
            $prefix = $this->prefix_key( isset( $s->prefix ) ? (string) $s->prefix : '' );
            foreach ( (array) ( $s->colors ?? array() ) as $c ) {
                $c     = (array) $c;
                $label = isset( $c['label'] ) ? trim( $c['label'] ) : '';
                $color = isset( $c['color'] ) ? trim( $c['color'] ) : '';
                if ( '' === $label || '' === $color ) { continue; }
                $hex   = preg_match( '/^[0-9a-fA-F]{3,8}$/', $color ) ? '#' . $color : $color;
                $var   = 'var(--' . $prefix . '-' . $this->color_slug( $label ) . ')';
                $list[]        = array( 'name' => $label, 'color' => $hex, 'var' => $var, 'uid' => isset( $c['uid'] ) ? (string) $c['uid'] : '' );
                $byvar[ $var ] = array( 'name' => $label, 'color' => $hex );
            }
        }
        $this->globals_cache = array( 'list' => $list, 'byvar' => $byvar );
        return $this->globals_cache;
    }

    /**
     * fl_theme_mods filter. The BB theme compiles uploads/bb-theme/skin-*.css from its fl-*
     * mods with LESS, which only reads #rgb / #rrggbb: it puts '#' in front of anything else
     * ("#var(--fl-global-...)"), the compile fails, no skin is written, and BB theme 1.7.20+
     * keeps linking the deleted file, so every page loses the theme CSS (a builder page's
     * content box stops being full width). This page saves richer values on purpose (a
     * synced var(--fl-global-*) and colours with opacity, GH #80), so the theme is given a
     * plain hex here while the stored mod, which the page and its CSS read, keeps what was
     * picked. Found on ethosvolleybal 2026-09-29. fl-*-bg-image URLs go through less_url()
     * for the same reason.
     */
    public function less_safe_theme_mods( $mods ) {
        if ( ! is_array( $mods ) ) { return $mods; }
        $globals = null;
        foreach ( $mods as $key => $value ) {
            if ( ! is_string( $value ) || 0 !== strpos( (string) $key, 'fl-' ) || in_array( $key, array( 'fl-css-code', 'fl-js-code' ), true ) ) { continue; }
            if ( '-bg-image' === substr( (string) $key, -9 ) ) {
                $mods[ $key ] = self::less_url( $value );
                continue;
            }
            if ( ! preg_match( '/^\s*(?:var\(|rgba?\(|#[0-9a-f]{4}\s*$|#[0-9a-f]{8}\s*$)/i', $value ) ) { continue; }
            if ( null === $globals ) { $globals = $this->global_color_map(); }
            $mods[ $key ] = self::less_hex( $value, $globals );
        }
        return $mods;
    }

    /**
     * var(--prefix-slug) => the colour it holds, read fresh on every call: save() writes the
     * palette and then rebuilds the skin in the same request, so a cached map would be stale.
     */
    private function global_color_map() {
        $map = array();
        if ( ! $this->available() ) { return $map; }
        $s      = FLBuilderGlobalStyles::get_settings( false );
        $prefix = $this->prefix_key( isset( $s->prefix ) ? (string) $s->prefix : '' );
        foreach ( (array) ( $s->colors ?? array() ) as $c ) {
            $c     = (array) $c;
            $label = isset( $c['label'] ) ? trim( $c['label'] ) : '';
            $color = isset( $c['color'] ) ? trim( $c['color'] ) : '';
            if ( '' !== $label && '' !== $color ) {
                $map[ 'var(--' . $prefix . '-' . $this->color_slug( $label ) . ')' ] = $color;
            }
        }
        return $map;
    }

    /**
     * A background image URL as the BB theme's LESS can read it. The theme writes
     * url(<value>) without quotes, so a ( ) ' or " in the URL fails the whole skin compile
     * (tested on 1.7.20: "photo (1).png", "o'brien.png"), and a space compiles into CSS that
     * browsers drop. Percent-encoding them points at the same file.
     */
    public static function less_url( $url ) {
        return str_replace( array( '(', ')', "'", '"', ' ' ), array( '%28', '%29', '%27', '%22', '%20' ), trim( (string) $url ) );
    }

    /**
     * A stored colour as the BB theme's LESS can read it: '#rrggbb' or '#rgb'. A synced
     * var() resolves through $globals; opacity is dropped (the skin has no alpha), and a
     * fully transparent colour or one that cannot be read gives '' so the theme falls
     * back to its own default.
     */
    public static function less_hex( $value, $globals = array(), $depth = 0 ) {
        $v = trim( (string) $value );
        if ( preg_match( '/^var\(\s*(--[A-Za-z0-9_-]+)\s*\)$/', $v, $m ) ) {
            $key = 'var(' . $m[1] . ')';
            return ( $depth < 3 && isset( $globals[ $key ] ) ) ? self::less_hex( $globals[ $key ], $globals, $depth + 1 ) : '';
        }
        if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $v, $m ) ) { return '#' . strtolower( $m[1] ); }
        if ( preg_match( '/^#?([0-9a-f]{3})([0-9a-f])$/i', $v, $m ) ) { return '0' === $m[2] ? '' : '#' . strtolower( $m[1] ); }
        if ( preg_match( '/^#?([0-9a-f]{6})([0-9a-f]{2})$/i', $v, $m ) ) { return '00' === $m[2] ? '' : '#' . strtolower( $m[1] ); }
        if ( preg_match( '/^rgba?\(\s*([\d.]+%?)[\s,]+([\d.]+%?)[\s,]+([\d.]+%?)(?:\s*[,\/]\s*([\d.]+%?))?\s*\)$/i', $v, $m ) ) {
            if ( isset( $m[4] ) && '' !== $m[4] && 0.0 === (float) $m[4] ) { return ''; }
            $hex = '#';
            foreach ( array( $m[1], $m[2], $m[3] ) as $c ) {
                $n    = '%' === substr( $c, -1 ) ? (float) $c * 2.55 : (float) $c;
                $hex .= sprintf( '%02x', max( 0, min( 255, (int) round( $n ) ) ) );
            }
            return $hex;
        }
        return '';
    }

    /** How a stored colour value presents: swatch fill, label and state (default / global / custom / missing). */
    private function color_info( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return array( 'state' => 'default', 'fill' => '', 'text' => 'Default' );
        }
        if ( preg_match( '/^var\(\s*(--[A-Za-z0-9_-]+)\s*\)$/', $value, $m ) ) {
            $g   = $this->globals_data();
            $key = 'var(' . $m[1] . ')';
            if ( isset( $g['byvar'][ $key ] ) ) {
                return array( 'state' => 'global', 'fill' => $g['byvar'][ $key ]['color'], 'text' => $g['byvar'][ $key ]['name'] );
            }
            return array( 'state' => 'missing', 'fill' => '', 'text' => $value );
        }
        $fill = preg_match( '/^#?[0-9a-fA-F]{3,8}$/', $value ) ? '#' . ltrim( strtolower( $value ), '#' ) : $value;
        return array( 'state' => 'custom', 'fill' => $fill, 'text' => $fill );
    }

    /** System+Typekit and Google font names with their weights, for the font picker (loaded after the page renders). */
    private function font_catalog() {
        $out = array( 'system' => array(), 'google' => array() );
        if ( ! class_exists( 'FLFontFamilies' ) ) { return $out; }
        $system = apply_filters( 'fl_theme_system_fonts', FLFontFamilies::get_system() );
        foreach ( (array) $system as $name => $data ) {
            if ( 'Default' === $name ) { continue; }
            $w = isset( $data['weights'] ) ? (array) $data['weights'] : array();
            $out['system'][ $name ] = implode( ',', $w );
        }
        foreach ( (array) FLFontFamilies::get_google() as $name => $w ) {
            $out['google'][ $name ] = implode( ',', (array) $w );
        }
        return $out;
    }

    public function ajax_fonts() {
        check_ajax_referer( self::PREVIEW_NONCE, 'nonce' );
        if ( ! $this->allowed() ) { wp_send_json_error( 'forbidden', 403 ); }
        wp_send_json_success( $this->font_catalog() );
    }

    /* ------------------------------------------- Request -> settings (shared) */

    /**
     * The Beaver Builder Global Styles values carried by a submitted form, exactly
     * as save() has always written them. Shared by save() and the live preview so
     * what is previewed is what gets stored.
     */
    private function bb_settings_from_request( $post ) {
        $old = FLBuilderGlobalStyles::get_settings( false );
        $new = array();

        // Named colors: only rebuilt when the repeater was actually submitted, so a
        // stray save can never wipe the global colors.
        if ( isset( $post['color_label'] ) ) {
            $labels = (array) $post['color_label'];
            $cols   = isset( $post['color_color'] ) ? (array) $post['color_color'] : array();
            $uids   = isset( $post['color_uid'] ) ? (array) $post['color_uid'] : array();
            $colors = array();
            foreach ( $labels as $i => $label ) {
                $label = sanitize_text_field( $label );
                $color = $this->sanitize_color( isset( $cols[ $i ] ) ? $cols[ $i ] : '' );
                if ( '' !== $label && '' !== $color ) {
                    $entry = array( 'label' => $label, 'color' => $color );
                    // Preserve the existing BB uid. Global-color CONNECTIONS reference
                    // colors BY uid; a missing uid gets a fresh random one from BB and
                    // every connection to that colour silently reverts.
                    $uid = isset( $uids[ $i ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $uids[ $i ] ) : '';
                    if ( '' !== $uid ) { $entry['uid'] = $uid; }
                    $colors[] = $entry;
                }
            }
            $new['colors'] = $colors;
        }
        if ( isset( $post['prefix'] ) ) {
            $new['prefix'] = sanitize_text_field( $post['prefix'] );
        }

        // Element colors.
        $posted_colors = isset( $post['color'] ) && is_array( $post['color'] ) ? $post['color'] : array();
        foreach ( $this->color_keys() as $k ) {
            if ( isset( $posted_colors[ $k ] ) ) {
                $new[ $k ] = $this->sanitize_color( $posted_colors[ $k ] );
            }
        }

        // Typography groups.
        $posted_typo = isset( $post['typo'] ) && is_array( $post['typo'] ) ? $post['typo'] : array();
        foreach ( $this->typo_groups() as $grp => $setting_key ) {
            $existing            = isset( $old->{$setting_key} ) ? $old->{$setting_key} : array();
            $new[ $setting_key ] = $this->parse_typography( $existing, isset( $posted_typo[ $grp ] ) ? $posted_typo[ $grp ] : array() );
        }

        // Button border.
        $posted_border        = isset( $post['border']['button'] ) && is_array( $post['border']['button'] ) ? $post['border']['button'] : array();
        $new['button_border'] = $this->parse_border( isset( $old->button_border ) ? $old->button_border : array(), $posted_border );

        return $new;
    }

    /**
     * The theme_mods carried by the General fields (page / content / banner
     * backgrounds, banner titles, corner radius, outline text, custom code),
     * sanitised exactly as save() has always done. Empty when not submitted.
     */
    private function general_mods_from_request( $g ) {
        if ( ! is_array( $g ) || ! $g ) { return array(); }
        $repeat_ok = array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' );
        $size_ok   = array( 'auto', 'cover', 'contain' );
        $attach_ok = array( 'scroll', 'fixed' );
        $pos_ok    = array( 'left top', 'left center', 'left bottom', 'center top', 'center center', 'center bottom', 'right top', 'right center', 'right bottom' );
        $blend_ok  = array_keys( $this->blend_modes() );
        $pick      = function( $key, $ok, $default ) use ( $g ) { return in_array( $g[ $key ] ?? '', $ok, true ) ? $g[ $key ] : $default; };
        $m = array();
        // Base Page Background (the page band) -> the Base Container row, fl-body-bg-* mods.
        $m['fl-body-bg-color']      = $this->sanitize_color( $g['bg_color'] ?? '' );
        $m['fl-body-bg-image']      = esc_url_raw( $g['bg_image'] ?? '' );
        $m['fl-body-bg-repeat']     = $pick( 'bg_repeat', $repeat_ok, 'no-repeat' );
        $m['fl-body-bg-position']   = $pick( 'bg_position', $pos_ok, 'center top' );
        $m['fl-body-bg-size']       = $pick( 'bg_size', $size_ok, 'auto' );
        $m['fl-body-bg-attachment'] = $pick( 'bg_attachment', $attach_ok, 'scroll' );
        $m['fl-body-bg-blend']      = $pick( 'bg_blend', $blend_ok, 'normal' );
        $m['fl-body-bg-overlay']    = $this->sanitize_color( $g['bg_overlay'] ?? '' );
        // Base Page Content Background (the inner content box) -> the content COLUMN, fl-content-bg-* mods.
        $m['fl-content-bg-color']      = $this->sanitize_color( $g['content_bg_color'] ?? '' );
        $m['fl-content-bg-image']      = esc_url_raw( $g['content_bg_image'] ?? '' );
        $m['fl-content-bg-repeat']     = $pick( 'content_bg_repeat', $repeat_ok, 'no-repeat' );
        $m['fl-content-bg-position']   = $pick( 'content_bg_position', $pos_ok, 'center top' );
        $m['fl-content-bg-size']       = $pick( 'content_bg_size', $size_ok, 'auto' );
        $m['fl-content-bg-attachment'] = $pick( 'content_bg_attachment', $attach_ok, 'scroll' );
        $m['fl-content-bg-blend']      = $pick( 'content_bg_blend', $blend_ok, 'normal' );
        $m['fl-content-bg-overlay']    = $this->sanitize_color( $g['content_bg_overlay'] ?? '' );
        // Page Banner (no-image) default background -> ds-banner-nobg-* mods.
        $m['ds-banner-nobg-color']      = $this->sanitize_color( $g['banner_nobg_color'] ?? '' );
        $m['ds-banner-nobg-image']      = esc_url_raw( $g['banner_nobg_image'] ?? '' );
        $m['ds-banner-nobg-repeat']     = $pick( 'banner_nobg_repeat', $repeat_ok, 'repeat' );
        $m['ds-banner-nobg-size']       = $pick( 'banner_nobg_size', $size_ok, 'auto' );
        $m['ds-banner-nobg-blend']      = $pick( 'banner_nobg_blend', $blend_ok, 'normal' );
        // Default 'center center' matches the value that used to be hardcoded.
        $m['ds-banner-nobg-position']   = $pick( 'banner_nobg_position', $pos_ok, 'center center' );
        $m['ds-banner-nobg-attachment'] = $pick( 'banner_nobg_attachment', $attach_ok, 'scroll' );
        $m['ds-banner-nobg-overlay']    = $this->sanitize_color( $g['banner_nobg_overlay'] ?? '' );
        if ( isset( $g['banner_featured_types_set'] ) ) {
            // The form offers public types with featured images; a stored type it does not list
            // (fl-builder-template) is kept rather than dropped on every save.
            $offered = self::featured_type_choices();
            $kept    = array_diff( self::banner_featured_types(), $offered );
            $picked  = array_intersect( array_map( 'sanitize_key', (array) ( $g['banner_featured_types'] ?? array() ) ), $offered );
            $m['ds-banner-featured-types'] = array_values( array_unique( array_merge( $picked, $kept ) ) );
        }
        $m['ds-banner-photo-title']       = ( $g['banner_photo_title'] ?? 'show' ) === 'hide' ? 'hide' : 'show';
        $m['ds-banner-photo-title-color'] = $this->sanitize_color( $g['banner_photo_title_color'] ?? '' );
        $m['ds-banner-nobg-title-color']  = $this->sanitize_color( $g['banner_nobg_title_color'] ?? '' );
        $m['ds-corner-radius']            = isset( $g['corner_radius'] ) && '' !== $g['corner_radius'] ? max( 0, min( 60, (int) $g['corner_radius'] ) ) : 8;
        $m['ds-outline-color']            = $this->sanitize_color( $g['outline_color'] ?? '' );
        $m['ds-outline-width']            = isset( $g['outline_width'] ) && '' !== $g['outline_width'] ? max( 1, min( 8, (int) $g['outline_width'] ) ) : 2;
        // Custom code: stored raw like the BB theme's Code section, so only a user WordPress
        // trusts with raw HTML may change it (a Contributor with a LeagueApps email may use the
        // rest of this page, not add site-wide script). Otherwise the saved code is kept.
        if ( current_user_can( 'unfiltered_html' ) ) {
            $m['fl-css-code'] = isset( $g['css_code'] ) ? trim( $g['css_code'] ) : '';
            $m['fl-js-code']  = isset( $g['js_code'] ) ? trim( $g['js_code'] ) : '';
        }
        return $m;
    }

    private function button_style_from_request( $post ) {
        if ( ! isset( $post['button_style'] ) ) { return null; }
        $bs = sanitize_key( $post['button_style'] );
        return array_key_exists( $bs, self::button_styles() ) ? $bs : '';
    }
}
