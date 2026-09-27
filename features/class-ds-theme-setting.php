<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme Setting (LeagueApps-internal, blueprint generation 6+).
 *
 * A LeagueApps-only admin surface for Beaver Builder's Global Styles, mounted
 * directly below "Partner Setting". Reads and writes the SAME store BB's own
 * Global Styles panel uses (option `_fl_builder_styles` via
 * FLBuilderGlobalStyles), so edits here and in BB stay in sync both ways. BB
 * renders the CSS itself.
 *
 * The page is a Customizer-style editor: sections on the left (Colors,
 * Typography, Buttons, Backgrounds, Page Banner, Details, Site Icon & Sharing,
 * Custom Code) and a live preview of a real page of the site on the right. The
 * preview shows unsaved values: Beaver Builder generates its global CSS from the
 * submitted form (ajax_preview_css) and the page swaps it into the preview
 * iframe (maybe_start_preview). Saving is an AJAX post to the same admin-post
 * handler, which still works as a plain form post without JavaScript (the root
 * carries .dsts-nojs, showing every section with Save enabled, until the script boots).
 * UI: assets/css/theme-setting.css + assets/js/theme-setting.js.
 */
class DS_Theme_Setting {

    const PAGE_SLUG     = 'ds-theme-setting';
    const SAVE_ACTION   = 'ds_theme_setting_save';
    const PREVIEW_AJAX  = 'ds_ts_preview_css';
    const FONTS_AJAX    = 'ds_ts_fonts';
    const PREVIEW_QUERY = 'ds_ts_preview';
    const PREVIEW_NONCE = 'ds_ts_preview';

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'register_menu' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
            add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
            add_action( 'wp_ajax_' . self::PREVIEW_AJAX, array( $this, 'ajax_preview_css' ) );
            add_action( 'wp_ajax_' . self::FONTS_AJAX, array( $this, 'ajax_fonts' ) );
        } elseif ( isset( $_GET[ self::PREVIEW_QUERY ] ) ) {
            // The live-preview iframe on the Theme Setting page (checked for user + nonce there).
            add_action( 'wp', array( $this, 'maybe_start_preview' ), 0 );
        }
        // Button shape is part of Theme Setting (not BB global styles), so it's
        // emitted here on the front end rather than through DS_Global_CSS (which
        // is an independently toggled feature and may be off).
        add_action( 'wp_head', array( $this, 'output_button_style_css' ), 99 );
        // In the base themer template: Base Page Background paints the Base Container
        // ROW (the page band), Base Page Content Background paints the content COLUMN
        // inside it (the inner box). Two settings, applied site-wide on every single.
        add_action( 'wp_head', array( $this, 'output_page_bg_css' ), 99 );
        add_action( 'wp_head', array( $this, 'output_content_bg_css' ), 99 );
        add_action( 'wp_head', array( $this, 'output_banner_nobg_css' ), 99 );
        // Global corner radius -> --ds-radius (the in-house card surfaces inherit it).
        add_action( 'wp_head', array( $this, 'output_corner_radius_css' ), 99 );
        // Style Forminator forms to match the theme (fields + submit button). Late
        // (priority 100) so it wins over Forminator's own enqueued CSS.
        add_action( 'wp_head', array( $this, 'output_form_style_css' ), 100 );
        // UABB Info List icons: default Primary / Accent alternation (dev colour overrides).
        add_action( 'wp_head', array( $this, 'output_uabb_infolist_css' ), 100 );
    }

    /**
     * Front-end <style> that makes Forminator forms match the site design: clean
     * fields with an accent focus ring, and a submit button that follows the global
     * Button colours / typography (the Button Shape is applied separately by
     * button_style_css(), which now includes the submit button in its selector).
     */
    public function output_form_style_css() {
        $css = <<<'CSS'
.forminator-ui.forminator-custom-form .forminator-field{margin-bottom:18px;}
.forminator-ui.forminator-custom-form label.forminator-label{display:block;margin-bottom:7px;font-weight:600;font-size:.82rem;letter-spacing:.03em;color:var(--fl-global-headings);}
.forminator-ui.forminator-custom-form input.forminator-input,
.forminator-ui.forminator-custom-form textarea.forminator-textarea,
.forminator-ui.forminator-custom-form select.forminator-select{width:100%;padding:13px 16px;border:1px solid rgba(0,0,0,.14);border-radius:6px;background:#fff;color:var(--fl-global-body);font-size:1rem;line-height:1.45;box-shadow:none;transition:border-color .15s ease,box-shadow .15s ease;}
.forminator-ui.forminator-custom-form textarea.forminator-textarea{min-height:130px;resize:vertical;}
.forminator-ui.forminator-custom-form input.forminator-input:focus,
.forminator-ui.forminator-custom-form textarea.forminator-textarea:focus,
.forminator-ui.forminator-custom-form select.forminator-select:focus{border-color:var(--fl-global-accent);box-shadow:0 0 0 3px rgba(0,0,0,.06);outline:none;}
.forminator-ui.forminator-custom-form input::placeholder,
.forminator-ui.forminator-custom-form textarea::placeholder{color:rgba(0,0,0,.42);}
.forminator-ui.forminator-custom-form button.forminator-button-submit{background:var(--fl-global-button);color:var(--fl-global-white);border:0;padding:14px 32px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;border-radius:6px;cursor:pointer;transition:background .2s ease,color .2s ease;}
.forminator-ui.forminator-custom-form button.forminator-button-submit:hover,
.forminator-ui.forminator-custom-form button.forminator-button-submit:focus{background:var(--fl-global-accent);color:var(--fl-global-white);}
CSS;
        echo '<style id="ds-form-style">' . $css . '</style>' . "\n";
    }

    /**
     * UABB Info List icons default to alternating Primary (odd rows) / Accent (even rows).
     * A dev-set per-item Icon Colour is emitted by UABB at higher specificity and overrides
     * this; :where() keeps these defaults low-specificity so the dev's choice always wins.
     */
    public function output_uabb_infolist_css() {
        $css = '.uabb-info-list :where(.uabb-info-list-wrapper > li) .uabb-icon i,'
             . '.uabb-info-list :where(.uabb-info-list-wrapper > li) .uabb-icon svg{color:var(--fl-global-primary);fill:var(--fl-global-primary);}'
             . '.uabb-info-list :where(.uabb-info-list-wrapper > li:nth-child(even)) .uabb-icon i,'
             . '.uabb-info-list :where(.uabb-info-list-wrapper > li:nth-child(even)) .uabb-icon svg{color:var(--fl-global-accent);fill:var(--fl-global-accent);}';
        echo '<style id="ds-uabb-infolist-icons">' . $css . '</style>' . "\n";
    }

    /**
     * Build a CSS background block from a set of fl-*-bg-* theme mods ($prefix is
     * "fl-body-bg" or "fl-content-bg"): colour + optional pattern image (with
     * repeat / position / size / attachment) + optional blend mode. Empty if nothing
     * is set, so the caller can skip emitting a rule.
     */
    private function bg_decl( $prefix ) {
        $color = (string) get_theme_mod( $prefix . '-color', '' );
        $image = (string) get_theme_mod( $prefix . '-image', '' );
        $blend = (string) get_theme_mod( $prefix . '-blend', 'normal' );
        $decl  = '';
        if ( '' !== $color ) { $decl .= 'background-color:' . $color . ';'; }
        if ( '' !== $image ) {
            $repeat = (string) get_theme_mod( $prefix . '-repeat', 'no-repeat' );
            $pos    = (string) get_theme_mod( $prefix . '-position', 'center top' );
            $size   = (string) get_theme_mod( $prefix . '-size', 'auto' );
            $attach = (string) get_theme_mod( $prefix . '-attachment', 'scroll' );
            $decl  .= 'background-image:' . self::overlay_layer( $prefix ) . 'url(' . esc_url( $image ) . ');background-repeat:' . $repeat . ';background-position:' . $pos . ';background-size:' . $size . ';background-attachment:' . $attach . ';';
        }
        if ( '' !== $blend && 'normal' !== $blend ) { $decl .= 'background-blend-mode:' . $blend . ';'; }
        return $decl;
    }

    /**
     * A tint stacked over the background image, as a flat gradient in the same
     * `background-image` shorthand. Done this way so no surface needs an extra
     * element or pseudo-element — these three all paint elements the base Themer
     * template owns, and adding markup to them would ripple through every build.
     *
     * Only meaningful WITH an image: over a plain colour a tint is just a
     * different colour, so it is skipped and the Background Colour field is the
     * right control. Returns '' when nothing is set, which leaves the emitted CSS
     * byte-identical to before.
     */
    private static function overlay_layer( $prefix ) {
        $c = trim( (string) get_theme_mod( $prefix . '-overlay', '' ) );
        if ( '' === $c ) { return ''; }
        return 'linear-gradient(' . $c . ',' . $c . '),';
    }

    /**
     * Guards for `background-attachment: fixed`, which is what "parallax" is.
     *
     * iOS Safari does not support it: the browser falls back to scroll but keeps
     * sizing the image against the viewport, so a `cover` background renders
     * zoomed and cropped. Most partner traffic is iPhone, so exposing Fixed
     * without this makes the setting actively worse than Scroll on the devices
     * that matter most. Coarse pointers get scroll, and so does anyone who has
     * asked for reduced motion — a background that slides under the content is
     * exactly the kind of movement that request is about.
     */
    private static function parallax_guard_css( $prefix, $selector ) {
        if ( 'fixed' !== (string) get_theme_mod( $prefix . '-attachment', 'scroll' ) ) { return ''; }
        return '@media (hover:none) and (pointer:coarse){' . $selector . '{background-attachment:scroll;}}'
             . '@media (prefers-reduced-motion:reduce){' . $selector . '{background-attachment:scroll;}}';
    }

    /**
     * Paints the Base Container ROW (class .ds-base-page-bg in the base themer
     * template) with the "Base Page Background" theme setting: the page band that
     * sits behind the content column on every single page.
     */
    public function output_page_bg_css() {
        $decl = $this->bg_decl( 'fl-body-bg' );
        if ( '' === $decl ) { return; }
        $sel = '.ds-base-page-bg > .fl-row-content-wrap';
        echo '<style id="ds-base-page-bg-css">' . $sel . '{' . $decl . '}' . self::parallax_guard_css( 'fl-body-bg', $sel ) . '</style>' . "\n";
    }

    /**
     * Paints the content COLUMN (class .ds-base-content-bg, the column inside the
     * Base Container row that holds the body) with the "Base Page Content Background"
     * theme setting: the inner content box. Colour + optional pattern + blend mode.
     */
    public function output_content_bg_css() {
        $decl = $this->bg_decl( 'fl-content-bg' );
        if ( '' === $decl ) { return; }
        $sel = '.ds-base-content-bg > .fl-col-content';
        echo '<style id="ds-base-content-bg-css">' . $sel . '{' . $decl . '}' . self::parallax_guard_css( 'fl-content-bg', $sel ) . '</style>' . "\n";
    }

    /**
     * Site-wide default background for the Page Banner (Leagueapps Hero Banner)
     * when a page has NO banner photo / video. The hero module can override per page.
     */
    /**
     * Post types allowed to use their Featured Image as the page-banner
     * background (the Hero Banner Style 2 fallback). Types NOT in the list
     * always get the text-only banner over the "No Image" background below.
     * Default (never saved yet): every eligible type EXCEPT staff — portrait
     * headshots crop badly in a wide banner.
     */
    /** Site-wide default: hide the auto title/subtitle on banners that HAVE a photo/video. */
    public static function banner_photo_title_hidden() {
        return 'hide' === get_theme_mod( 'ds-banner-photo-title', 'show' );
    }

    /** Post types the Featured image as banner card offers: public, with featured images. */
    public static function featured_type_choices() {
        $out = array();
        foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
            if ( 'attachment' !== $pt->name && post_type_supports( $pt->name, 'thumbnail' ) ) { $out[] = $pt->name; }
        }
        return $out;
    }

    public static function banner_featured_types() {
        $mod = get_theme_mod( 'ds-banner-featured-types', null );
        if ( is_array( $mod ) ) {
            return array_map( 'sanitize_key', $mod );
        }
        $out = array();
        foreach ( get_post_types( array( 'public' => true ), 'names' ) as $pt ) {
            if ( 'attachment' === $pt || 'staff' === $pt ) { continue; }
            if ( post_type_supports( $pt, 'thumbnail' ) ) { $out[] = $pt; }
        }
        return $out;
    }

    /** True when $post_type may use its Featured Image as the banner background. */
    public static function banner_featured_allowed( $post_type ) {
        return in_array( (string) $post_type, self::banner_featured_types(), true );
    }

    public function output_banner_nobg_css() {
        $color = (string) get_theme_mod( 'ds-banner-nobg-color', 'var(--fl-global-light-background)' );
        $image = (string) get_theme_mod( 'ds-banner-nobg-image', '' );
        $blend = (string) get_theme_mod( 'ds-banner-nobg-blend', 'normal' );
        $decl  = '';
        if ( '' !== $color ) { $decl .= 'background-color:' . $color . ';'; }
        if ( '' !== $image ) {
            $repeat = (string) get_theme_mod( 'ds-banner-nobg-repeat', 'repeat' );
            $size   = (string) get_theme_mod( 'ds-banner-nobg-size', 'auto' );
            // Position was hardcoded to `center`; the setting defaults to the
            // equivalent `center center`, so an existing banner does not move.
            $pos    = (string) get_theme_mod( 'ds-banner-nobg-position', 'center center' );
            $attach = (string) get_theme_mod( 'ds-banner-nobg-attachment', 'scroll' );
            $decl  .= 'background-image:' . self::overlay_layer( 'ds-banner-nobg' ) . 'url(' . esc_url( $image ) . ');background-repeat:' . $repeat . ';background-position:' . $pos . ';background-size:' . $size . ';background-attachment:' . $attach . ';';
        }
        if ( '' !== $blend && 'normal' !== $blend ) { $decl .= 'background-blend-mode:' . $blend . ';'; }
        if ( '' === $decl ) { return; }
        echo '<style id="ds-banner-nobg-css">.ds-banner--no-bg{' . $decl . '}' . self::parallax_guard_css( 'ds-banner-nobg', '.ds-banner--no-bg' ) . '</style>' . "\n";
        $o_color = (string) get_theme_mod( 'ds-outline-color', '' );
        $o_width = max( 1, (int) get_theme_mod( 'ds-outline-width', 2 ) );
        echo '<style id="ds-outline-text-css">.ds-outline-text{color:transparent;-webkit-text-stroke:var(--ds-outline-w,' . $o_width . 'px) var(--ds-outline-c,' . ( '' !== $o_color ? esc_html( $o_color ) : 'currentColor' ) . ');}</style>' . "\n";
        $pt_color = (string) get_theme_mod( 'ds-banner-photo-title-color', '' );
        if ( '' !== $pt_color ) {
            echo '<style id="ds-banner-photo-title-css">.ds-banner--has-bg .ds-hero-title{color:' . esc_html( $pt_color ) . ';}</style>' . "\n";
        }
        $nt_color = (string) get_theme_mod( 'ds-banner-nobg-title-color', '' );
        if ( '' !== $nt_color ) {
            echo '<style id="ds-banner-nobg-title-css">.ds-banner--no-bg .ds-hero-title{color:' . esc_html( $nt_color ) . ';}</style>' . "\n";
        }
    }

    /**
     * Global corner radius. Emits --ds-radius, which the in-house LeagueApps card
     * surfaces use as their default border-radius (so radius is controlled in one
     * place; a module can still override per instance).
     */
    public function output_corner_radius_css() {
        $r = get_theme_mod( 'ds-corner-radius', '8' );
        if ( '' === $r ) { $r = '8'; }
        echo '<style id="ds-corner-radius-css">:root{--ds-radius:' . (int) $r . 'px;}</style>' . "\n";
    }

    /** Front-end <style> for the chosen button shape (Elements -> Button -> Button Shape). */
    public function output_button_style_css() {
        $css = self::button_style_css( get_option( self::BUTTON_STYLE_OPTION, '' ) );
        if ( $css ) {
            echo '<style id="ds-toolkit-button-style">' . $css . '</style>' . "\n";
        }
    }

    private function available() {
        return class_exists( 'FLBuilderGlobalStyles' );
    }

    /** Typography groups: POST key => settings key. */
    private function typo_groups() {
        return array(
            'text'   => 'text_typography',
            'h'      => 'h_typography',
            'h1'     => 'h1_typography',
            'h2'     => 'h2_typography',
            'h3'     => 'h3_typography',
            'h4'     => 'h4_typography',
            'h5'     => 'h5_typography',
            'h6'     => 'h6_typography',
            'link'   => 'link_typography',
            'button' => 'button_typography',
        );
    }

    private function color_keys() {
        return array(
            'text_color', 'h_color', 'h1_color', 'h2_color', 'h3_color', 'h4_color', 'h5_color', 'h6_color',
            'link_color', 'link_hover_color',
            'button_color', 'button_hover_color', 'button_background', 'button_hover_background', 'button_border_hover_color',
        );
    }

    const BUTTON_STYLE_OPTION = 'ds_button_style';

    /**
     * Button shape presets. The `clip` value is the literal CSS clip-path; it is
     * the SINGLE source of truth shared by the admin preview (render_button_style)
     * and the front-end emitter (button_style_css), so what's previewed is exactly
     * what renders. '' (Default) keeps the theme's normal rounded button.
     */
    public static function button_styles() {
        return array(
            ''      => array(
                'label' => 'Default',
                'desc'  => 'Standard button (uses the global corner radius).',
                'clip'  => '',
            ),
            'angle' => array(
                'label' => 'Angle',
                'desc'  => 'Opposite corners sliced for a slanted, dynamic look.',
                'clip'  => 'polygon(16px 0, 100% 0, 100% calc(100% - 16px), calc(100% - 16px) 100%, 0 100%, 0 16px)',
            ),
            'clip'  => array(
                'label' => 'Clip',
                'desc'  => 'All four corners beveled for a chamfered edge.',
                'clip'  => 'polygon(10px 0, calc(100% - 10px) 0, 100% 10px, 100% calc(100% - 10px), calc(100% - 10px) 100%, 10px 100%, 0 calc(100% - 10px), 0 10px)',
            ),
        );
    }

    /** Front-end CSS for a chosen button shape. Default emits a clip-path RESET:
     *  several blueprint modules ship a decorative clip-path in their static CSS
     *  (e.g. the Post Loop "See all" parallelogram), and without this reset the
     *  Default shape's "standard button with the global radius" never actually
     *  showed — the static clip stayed and the button ignored the Theme Setting. */
    public static function button_style_css( $style ) {
        $styles = self::button_styles();
        if ( ! isset( $styles[ $style ] ) || '' === $styles[ $style ]['clip'] ) {
            $sel = self::button_shape_selectors();
            return "{$sel}{-webkit-clip-path:none;clip-path:none;}";
        }
        $clip = $styles[ $style ]['clip'];
        // Filled site-button surfaces: standard BB/UABB buttons (.fl-button — UABB
        // buttons get it via Global JS), the nav CTA (DS Menu is-button), and the
        // hero primary CTA. Outline/ghost buttons are left alone — a clip-path
        // would slice their visible border.
        $sel = self::button_shape_selectors( ':not(.ds-no-clip)' );
        return "{$sel}{overflow:hidden;-webkit-clip-path:{$clip};clip-path:{$clip};border-radius:0 !important;}";
    }

    /** The button surfaces the Button Shape system owns (shared by shape + Default reset). */
    private static function button_shape_selectors( $suffix = '' ) {
        $list = array( '.fl-button', 'a.fl-button', '.ds-menu-item.is-button', '.ds-hero-btn--primary', '.ds-cta-bento-btn', '.ds-cta-hero-btn', '.ds-news-seeall', '.forminator-custom-form button.forminator-button-submit', '.dst-card-btn--button', '.ds-program-btn', '.ds-tourn-btn' );
        return implode( $suffix . ', ', $list ) . $suffix;
    }

    /* ----------------------------------------------------------------- Menu */

    public function register_menu() {
        if ( ! DS_Toolkit::is_leagueapps_user() ) {
            return;
        }
        add_menu_page( 'Theme Setting', 'Theme Setting', 'edit_posts', self::PAGE_SLUG, array( $this, 'render_page' ), 'dashicons-admin-customizer', '1.1' );
    }

    /** Only LeagueApps users who can edit posts may use the page, its preview and its endpoints. */
    private function allowed() {
        return current_user_can( 'edit_posts' ) && DS_Toolkit::is_leagueapps_user();
    }

    private static function asset_ver( $rel ) {
        $t = @filemtime( DS_TOOLKIT_PATH . $rel ); // cache-bust on every edit, not only on release
        return $t ? DS_TOOLKIT_VERSION . '.' . $t : DS_TOOLKIT_VERSION;
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style( 'ds-toolkit-admin', DS_TOOLKIT_URL . 'assets/css/admin.css', array(), DS_TOOLKIT_VERSION );
        wp_enqueue_media(); // image pickers (backgrounds, favicon, social card)

        // One shared colour picker (Pickr) instance for the whole page, created the
        // first time a swatch is opened. The BB module picker is a React control
        // bound to the builder app, so it cannot run on a standalone admin page.
        wp_enqueue_style( 'pickr-nano', DS_TOOLKIT_URL . 'assets/vendor/pickr/nano.min.css', array(), '1.9.1' );
        wp_enqueue_script( 'pickr', DS_TOOLKIT_URL . 'assets/vendor/pickr/pickr.min.js', array(), '1.9.1', true );

        // WordPress' own CodeMirror for the Custom CSS / JS fields, loaded only when
        // the Custom Code section is first opened: CodeMirror + CSSLint + Esprima are
        // about 1.3 MB of JavaScript that no other section needs.
        $code_editor = $this->code_editor_config();

        wp_enqueue_style( 'ds-theme-setting', DS_TOOLKIT_URL . 'assets/css/theme-setting.css', array( 'pickr-nano' ), self::asset_ver( 'assets/css/theme-setting.css' ) );
        wp_enqueue_script( 'ds-theme-setting', DS_TOOLKIT_URL . 'assets/js/theme-setting.js', array( 'jquery', 'pickr' ), self::asset_ver( 'assets/js/theme-setting.js' ), true );

        $nonce = wp_create_nonce( self::PREVIEW_NONCE );
        wp_localize_script( 'ds-theme-setting', 'dsTs', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'previewAction' => self::PREVIEW_AJAX,
            'fontsAction'   => self::FONTS_AJAX,
            'nonce'         => $nonce,
            'prefixKey'     => $this->prefix_key(),
            'globals'       => $this->globals_data()['list'],
            'pages'         => $this->preview_pages( $nonce ),
            'previewOrigin' => $this->origin_of( home_url( '/' ) ),
            'codeEditor'    => $code_editor,
        ) );
    }

    /**
     * Settings + asset URLs for lazy-loading WordPress' code editor (what
     * wp_enqueue_code_editor() would enqueue up front). False when the user
     * turned syntax highlighting off in their profile.
     */
    private function code_editor_config() {
        if ( ! function_exists( 'wp_get_code_editor_settings' ) || 'false' === wp_get_current_user()->syntax_highlighting ) {
            return false;
        }
        $url = function( $handle, $reg ) {
            $o = isset( $reg->registered[ $handle ] ) ? $reg->registered[ $handle ] : null;
            if ( ! $o || ! $o->src ) { return ''; }
            $src = preg_match( '#^(https?:)?//#', $o->src ) ? $o->src : $reg->base_url . $o->src;
            return add_query_arg( 'ver', $o->ver ? $o->ver : get_bloginfo( 'version' ), $src );
        };
        $js  = wp_scripts();
        $css = wp_styles();
        return array(
            'css'     => wp_get_code_editor_settings( array( 'type' => 'text/css' ) ),
            'js'      => wp_get_code_editor_settings( array( 'type' => 'application/javascript' ) ),
            'scripts' => array_values( array_filter( array( $url( 'wp-codemirror', $js ), $url( 'csslint', $js ), $url( 'esprima', $js ), $url( 'jshint', $js ), $url( 'code-editor', $js ) ) ) ),
            'styles'  => array_values( array_filter( array( $url( 'wp-codemirror', $css ), $url( 'code-editor', $css ) ) ) ),
        );
    }

    /** Pages offered in the preview selector: the front page first, then published pages. */
    private function preview_pages( $nonce ) {
        $out   = array();
        $front = ( 'page' === get_option( 'show_on_front' ) ) ? (int) get_option( 'page_on_front' ) : 0;
        $out[] = array( 'id' => $front, 'title' => 'Home', 'url' => $this->preview_url( $front ? get_permalink( $front ) : home_url( '/' ), $nonce ) );
        $pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 80, 'post_status' => 'publish' ) );
        foreach ( (array) $pages as $pg ) {
            if ( (int) $pg->ID === $front ) { continue; }
            $title = trim( wp_strip_all_tags( get_the_title( $pg ) ) );
            if ( $pg->post_parent ) { $title = '— ' . $title; }
            $out[] = array( 'id' => (int) $pg->ID, 'title' => '' !== $title ? $title : '(no title)', 'url' => $this->preview_url( get_permalink( $pg ), $nonce ) );
        }
        return $out;
    }

    private function preview_url( $url, $nonce ) {
        $url = set_url_scheme( $url, is_ssl() ? 'https' : 'http' ); // same scheme as the admin, or the iframe is blocked as mixed content
        return add_query_arg( array( self::PREVIEW_QUERY => 1, '_dsnonce' => $nonce ), $url );
    }

    private function origin_of( $url ) {
        $url = set_url_scheme( $url, is_ssl() ? 'https' : 'http' );
        $p   = wp_parse_url( $url );
        return $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
    }

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

    /* --------------------------------------------------------- Live preview */

    /**
     * Preview CSS for the submitted (unsaved) form: Beaver Builder's own global
     * styles CSS generated from those values, plus this class's front-end CSS
     * (backgrounds, banner, radius, outline text, button shape) rendered with the
     * submitted theme settings, plus the Custom CSS. Nothing is stored.
     */
    public function ajax_preview_css() {
        check_ajax_referer( self::PREVIEW_NONCE, 'nonce' );
        if ( ! $this->allowed() || ! $this->available() ) { wp_send_json_error( 'forbidden', 403 ); }
        $post = wp_unslash( $_POST );

        $settings = (object) array_merge( (array) FLBuilderGlobalStyles::get_settings( false ), $this->bb_settings_from_request( $post ) );
        if ( class_exists( 'FLThemeBuilderFieldConnections' ) ) {
            $settings = FLThemeBuilderFieldConnections::connect_settings( $settings );
        }
        $bb_css = FLBuilderGlobalStyles::generate_css( $settings );

        $mods = $this->general_mods_from_request( isset( $post['general'] ) ? $post['general'] : array() );
        foreach ( $mods as $key => $value ) {
            add_filter( 'theme_mod_' . $key, function() use ( $value ) { return $value; }, 999 );
        }
        $bs = $this->button_style_from_request( $post );
        if ( null !== $bs ) {
            add_filter( 'pre_option_' . self::BUTTON_STYLE_OPTION, function() use ( $bs ) { return $bs; }, 999 );
        }

        wp_send_json_success( array(
            'css'       => $bb_css,
            'ds'        => $this->ds_front_css(),
            'customCss' => isset( $mods['fl-css-code'] ) ? $mods['fl-css-code'] : (string) get_theme_mod( 'fl-css-code', '' ),
            'fonts'     => $this->google_fonts_in( $settings ),
        ) );
    }

    /** This class's own front-end CSS as one string (same emitters the site uses). */
    private function ds_front_css() {
        ob_start();
        $this->output_page_bg_css();
        $this->output_content_bg_css();
        $this->output_banner_nobg_css();
        $this->output_corner_radius_css();
        $this->output_button_style_css();
        return trim( preg_replace( '#</?style[^>]*>#i', "\n", (string) ob_get_clean() ) );
    }

    /** Google font families (with weights) the settings use, so the preview can load unsaved choices. */
    private function google_fonts_in( $settings ) {
        if ( ! class_exists( 'FLFontFamilies' ) ) { return array(); }
        $google = FLFontFamilies::get_google();
        $fonts  = array();
        foreach ( $this->typo_groups() as $key ) {
            $t   = isset( $settings->{$key} ) ? (array) $settings->{$key} : array();
            $fam = isset( $t['font_family'] ) ? (string) $t['font_family'] : '';
            if ( '' === $fam || ! isset( $google[ $fam ] ) ) { continue; }
            $w = isset( $t['font_weight'] ) && '' !== $t['font_weight'] ? (string) $t['font_weight'] : '400';
            $fonts[ $fam ][ $w ] = true;
        }
        $out = array();
        foreach ( $fonts as $fam => $weights ) {
            $out[] = $fam . ':' . implode( ',', array_keys( $weights ) );
        }
        return $out;
    }

    /**
     * Preview mode for the iframe on this page: a real front-end page, with the
     * saved global styles held in two replaceable <style> blocks so the admin page
     * can swap in the unsaved CSS. Beaver Builder renders the layout CSS inline for
     * this request only (its cache files are only written in file mode, so nothing
     * visitors get is touched) and without the saved global styles, which live in
     * the replaceable block instead: clearing a value in the admin then really
     * clears it in the preview.
     */
    public function maybe_start_preview() {
        if ( ! $this->allowed() || ! $this->available() ) { return; }
        if ( ! wp_verify_nonce( isset( $_GET['_dsnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_dsnonce'] ) ) : '', self::PREVIEW_NONCE ) ) { return; }

        show_admin_bar( false );
        // Never cached: the preview carries unsaved styles (plugin page caches honour this constant).
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        nocache_headers();
        add_filter( 'fl_builder_render_assets_inline', '__return_true', 999 );
        remove_filter( 'fl_builder_global_css_string', 'FLBuilderGlobalStyles::inject_global_css_string', 10 );

        // Saved global styles, printed before the layout CSS so the cascade matches the live site.
        add_action( 'wp_enqueue_scripts', function() {
            wp_register_style( 'ds-ts-live', false, array(), null );
            wp_enqueue_style( 'ds-ts-live' );
            wp_add_inline_style( 'ds-ts-live', FLBuilderGlobalStyles::generate_css() );
        }, 1 );

        // This class's front-end CSS in one replaceable block, at the same position.
        foreach ( array( 'output_button_style_css', 'output_page_bg_css', 'output_content_bg_css', 'output_banner_nobg_css', 'output_corner_radius_css' ) as $m ) {
            remove_action( 'wp_head', array( $this, $m ), 99 );
        }
        add_action( 'wp_head', function() {
            echo '<style id="ds-ts-live-ds">' . $this->ds_front_css() . '</style>' . "\n";
        }, 99 );
        add_action( 'wp_footer', array( $this, 'preview_bridge' ), 999 );
    }

    /** Receives preview CSS from the Theme Setting page and keeps navigation inside preview mode. */
    public function preview_bridge() {
        $keep = array( self::PREVIEW_QUERY => 1, '_dsnonce' => isset( $_GET['_dsnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_dsnonce'] ) ) : '' );
        ?>
<script id="ds-ts-preview-bridge">
(function(){
  var KEEP = <?php echo wp_json_encode( $keep ); ?>;
  var loaded = {};
  function styleEl(id){ var el = document.getElementById(id); if (!el) { el = document.createElement('style'); el.id = id; document.head.appendChild(el); } return el; }
  function loadFonts(list){
    (list || []).forEach(function(f){
      if (loaded[f]) return; loaded[f] = 1;
      var parts = f.split(':'), fam = parts[0], w = (parts[1] || '400');
      var l = document.createElement('link'); l.rel = 'stylesheet';
      l.href = 'https://fonts.googleapis.com/css?family=' + encodeURIComponent(fam) + ':' + w + '&display=swap';
      document.head.appendChild(l);
    });
  }
  window.addEventListener('message', function(e){
    if (e.source !== window.parent || e.origin !== location.origin) return;
    var d = e.data || {};
    if (d.type !== 'dsts:css') return;
    if (typeof d.css === 'string') styleEl('ds-ts-live-inline-css').textContent = d.css;
    if (typeof d.ds === 'string') styleEl('ds-ts-live-ds').textContent = d.ds;
    if (typeof d.customCss === 'string') styleEl('fl-theme-custom-css').textContent = d.customCss;
    loadFonts(d.fonts);
  });
  // Stay in preview mode when a link inside the preview is clicked; never leave the site.
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.getAttribute('href').charAt(0) === '#') return;
    e.preventDefault();
    var u; try { u = new URL(a.href, location.href); } catch (err) { return; }
    if (u.origin !== location.origin || /\/wp-(admin|login)/.test(u.pathname)) return;
    Object.keys(KEEP).forEach(function(k){ u.searchParams.set(k, KEEP[k]); });
    location.href = u.toString();
  }, true);
  document.addEventListener('submit', function(e){ e.preventDefault(); }, true);
  if (window.parent !== window) window.parent.postMessage({ type: 'dsts:ready', url: location.href, title: document.title }, location.origin);
})();
</script>
        <?php
    }

    /* -------------------------------------------------------- Field helpers */

    private $fid = 0;
    private function fid() { return 'dsts-f' . ( ++$this->fid ); }

    private function label_html( $label, $for = '', $hint = '' ) {
        if ( '' === $label ) { return ''; }
        $h = '<div class="dsts-field-label"><label' . ( $for ? ' for="' . esc_attr( $for ) . '"' : '' ) . '>' . esc_html( $label ) . '</label>';
        if ( $hint ) { $h .= '<span class="dsts-hint" tabindex="0" data-tip="' . esc_attr( $hint ) . '"><span class="dashicons dashicons-info-outline"></span></span>'; }
        return $h . '</div>';
    }

    /** A labelled row: label on the left, control on the right. */
    private function row( $label, $control_html, $args = array() ) {
        $cls = 'dsts-field' . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' );
        echo '<div class="' . esc_attr( $cls ) . '"' . ( ! empty( $args['attrs'] ) ? ' ' . $args['attrs'] : '' ) . '>' . $this->label_html( $label, $args['for'] ?? '', $args['hint'] ?? '' ) . '<div class="dsts-field-control">' . $control_html . '</div>';
        if ( ! empty( $args['help'] ) ) { echo '<p class="dsts-help">' . wp_kses_post( $args['help'] ) . '</p>'; }
        echo '</div>';
    }

    /** A compact labelled cell for grids (label above the control). */
    private function cell( $label, $control_html, $args = array() ) {
        return '<div class="dsts-cell' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '"><span class="dsts-cell-label">' . esc_html( $label ) . '</span>' . $control_html . '</div>';
    }

    private function card_open( $title, $desc = '', $args = array() ) {
        echo '<div class="dsts-card' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '"' . ( ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '' ) . '>';
        if ( $title ) {
            echo '<div class="dsts-card-head"><h3>' . esc_html( $title ) . '</h3>' . ( ! empty( $args['aside'] ) ? $args['aside'] : '' ) . '</div>';
        }
        if ( $desc ) { echo '<p class="dsts-card-desc">' . wp_kses_post( $desc ) . '</p>'; }
    }
    private function card_close() { echo '</div>'; }

    private function select_html( $name, $val, $choices, $args = array() ) {
        $id  = $args['id'] ?? '';
        $out = '<select name="' . esc_attr( $name ) . '"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ( ! empty( $args['class'] ) ? ' class="' . esc_attr( $args['class'] ) . '"' : '' ) . '>';
        $found = false;
        foreach ( $choices as $v => $l ) {
            $sel    = ( (string) $val === (string) $v );
            $found  = $found || $sel;
            $out   .= '<option value="' . esc_attr( $v ) . '"' . ( $sel ? ' selected' : '' ) . '>' . esc_html( $l ) . '</option>';
        }
        // Never silently change a stored value the list does not know.
        if ( ! $found && '' !== (string) $val ) {
            $out .= '<option value="' . esc_attr( $val ) . '" selected>' . esc_html( $val ) . '</option>';
        }
        return $out . '</select>';
    }

    /** Segmented control: native radios styled as a button group (posts like a select). */
    private function seg_html( $name, $val, $choices, $args = array() ) {
        $out = '<div class="dsts-seg' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '" role="radiogroup">';
        $found = false;
        foreach ( $choices as $v => $c ) {
            $c      = is_array( $c ) ? $c : array( 'label' => $c );
            $sel    = ( (string) $val === (string) $v );
            $found  = $found || $sel;
            $inner  = ! empty( $c['icon'] ) ? '<span class="dashicons ' . esc_attr( $c['icon'] ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $c['label'] ) . '</span>' : esc_html( $c['label'] );
            $out   .= '<label title="' . esc_attr( $c['title'] ?? $c['label'] ) . '"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . ( $sel ? ' checked' : '' ) . '><span>' . $inner . '</span></label>';
        }
        if ( ! $found && '' !== (string) $val ) {
            $out .= '<label title="' . esc_attr( $val ) . '"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" checked><span>' . esc_html( $val ) . '</span></label>';
        }
        return $out . '</div>';
    }

    private function num_html( $name, $val, $args = array() ) {
        return '<input type="number" class="dsts-num" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"'
            . ( isset( $args['step'] ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '' )
            . ( isset( $args['min'] ) ? ' min="' . esc_attr( $args['min'] ) . '"' : '' )
            . ( isset( $args['max'] ) ? ' max="' . esc_attr( $args['max'] ) . '"' : '' )
            . ( isset( $args['placeholder'] ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '' )
            . ( isset( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '' )
            . ( isset( $args['title'] ) ? ' title="' . esc_attr( $args['title'] ) . '" aria-label="' . esc_attr( $args['title'] ) . '"' : '' ) . '>';
    }

    /** Number + unit in one control. */
    private function num_unit_html( $len_name, $len, $unit_name, $unit, $units, $args = array() ) {
        return '<div class="dsts-nu">' . $this->num_html( $len_name, $len, $args ) . $this->select_html( $unit_name, $unit, $units, array( 'class' => 'dsts-nu-unit' ) ) . '</div>';
    }

    private function color_html( $name, $value, $args = array() ) {
        $value   = trim( (string) $value );
        $info    = $this->color_info( $value );
        $globals = ! isset( $args['globals'] ) || $args['globals'];
        $fill    = $info['fill'] ? ' style="background:' . esc_attr( $info['fill'] ) . '"' : '';
        return '<div class="dsts-color is-' . esc_attr( $info['state'] ) . '" data-globals="' . ( $globals ? '1' : '0' ) . '">'
            . '<button type="button" class="dsts-color-btn"><span class="dsts-color-chip"><span class="dsts-color-fill"' . $fill . '></span></span>'
            . '<span class="dsts-color-text">' . esc_html( $info['text'] ) . '</span>'
            . ( 'global' === $info['state'] ? '<span class="dsts-color-tag">Global</span>' : '' ) . '</button>'
            . '<input type="hidden" class="dsts-color-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( ! empty( $args['input_class'] ) ? ' data-role="' . esc_attr( $args['input_class'] ) . '"' : '' ) . '>'
            . '</div>';
    }

    private function color_row( $label, $name, $value, $args = array() ) {
        $this->row( $label, $this->color_html( $name, $value, $args ), array( 'class' => 'dsts-field--color', 'hint' => $args['hint'] ?? '', 'help' => $args['help'] ?? '' ) );
    }

    private function font_html( $name, $value ) {
        $value = '' !== trim( (string) $value ) ? $value : 'Default';
        return '<div class="dsts-font" data-value="' . esc_attr( $value ) . '">'
            . '<input type="text" class="dsts-font-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false" aria-autocomplete="list">'
            . '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></div>';
    }

    /** Four linked numeric inputs (border width, radius), with a link toggle that sets all four together. */
    private function quad_html( $base, $keys, $vals, $args = array() ) {
        $vals   = (array) $vals;
        $values = array();
        foreach ( $keys as $k => $l ) { $values[] = (string) ( $vals[ $k ] ?? '' ); }
        $linked = count( array_unique( $values ) ) === 1;
        $out    = '<div class="dsts-quad' . ( $linked ? ' is-linked' : '' ) . '">';
        foreach ( $keys as $k => $l ) {
            $out .= '<label class="dsts-quad-i"><input type="number" class="dsts-num" name="' . esc_attr( $base . '[' . ( $args['field'] ?? '' ) . $k . ']' ) . '" value="' . esc_attr( $vals[ $k ] ?? '' ) . '" min="0"><span>' . esc_html( $l ) . '</span></label>';
        }
        return $out . '<button type="button" class="dsts-quad-link" aria-pressed="' . ( $linked ? 'true' : 'false' ) . '" title="Link values"><span class="dashicons dashicons-admin-links"></span></button></div>';
    }

    /** Image picker tile (URL, and optionally the attachment id). */
    private function image_html( $url_name, $url, $id_name = '', $id = 0, $args = array() ) {
        $url  = (string) $url;
        $cls  = 'dsts-img' . ( $url ? ' has-img' : '' ) . ( ! empty( $args['shape'] ) ? ' dsts-img--' . $args['shape'] : '' );
        $out  = '<div class="' . esc_attr( $cls ) . '"' . ( ! empty( $args['title'] ) ? ' data-title="' . esc_attr( $args['title'] ) . '"' : '' ) . '>';
        $out .= '<button type="button" class="dsts-img-thumb" aria-label="Select image">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '' ) . '<span class="dsts-img-empty"><span class="dashicons dashicons-format-image"></span>Select image</span></button>';
        $out .= '<div class="dsts-img-actions"><button type="button" class="button dsts-img-select">' . ( $url ? 'Replace' : 'Select' ) . '</button><button type="button" class="button-link dsts-img-remove"' . ( $url ? '' : ' hidden' ) . '>Remove</button></div>';
        $out .= '<input type="hidden" class="dsts-img-url" name="' . esc_attr( $url_name ) . '" value="' . esc_attr( $url ) . '">';
        if ( $id_name ) { $out .= '<input type="hidden" class="dsts-img-id" name="' . esc_attr( $id_name ) . '" value="' . esc_attr( $id ) . '">'; }
        return $out . '</div>';
    }

    private function position_html( $name, $val ) {
        $out = '<div class="dsts-pos" role="radiogroup">';
        foreach ( array( 'top', 'center', 'bottom' ) as $y ) {
            foreach ( array( 'left', 'center', 'right' ) as $x ) {
                $v    = $x . ' ' . $y;
                $out .= '<label title="' . esc_attr( ucwords( $v ) ) . '"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . checked( $val, $v, false ) . '><span></span></label>';
            }
        }
        return $out . '</div>';
    }

    private function blend_modes() {
        return array( 'normal' => 'Normal', 'multiply' => 'Multiply', 'screen' => 'Screen', 'overlay' => 'Overlay', 'darken' => 'Darken', 'lighten' => 'Lighten', 'color-dodge' => 'Color Dodge', 'color-burn' => 'Color Burn', 'hard-light' => 'Hard Light', 'soft-light' => 'Soft Light', 'difference' => 'Difference', 'exclusion' => 'Exclusion', 'hue' => 'Hue', 'saturation' => 'Saturation', 'color' => 'Color', 'luminosity' => 'Luminosity' );
    }

    /** Colour + pattern/image background group; the image options only show once an image is set. */
    private function background_group( $key, $mods, $defaults ) {
        $mod = function( $k, $d = '' ) { $v = get_theme_mod( $k, $d ); return ( false === $v ) ? $d : $v; };
        $n   = function( $f ) use ( $key ) { return 'general[' . $key . '_' . $f . ']'; };
        $img = $mod( $mods . '-image' );

        $this->color_row( 'Background colour', $n( 'color' ), $mod( $mods . '-color', $defaults['color'] ?? '' ) );
        $this->row( 'Pattern / image', $this->image_html( $n( 'image' ), $img, '', 0, array( 'title' => 'Select background image' ) ), array( 'class' => 'dsts-field--image' ) );

        echo '<div class="dsts-bg-opts"' . ( $img ? '' : ' hidden' ) . '>';
        $this->row( 'Repeat', $this->seg_html( $n( 'repeat' ), $mod( $mods . '-repeat', $defaults['repeat'] ), array( 'no-repeat' => 'None', 'repeat' => 'Tile', 'repeat-x' => array( 'label' => 'X', 'title' => 'Tile horizontally' ), 'repeat-y' => array( 'label' => 'Y', 'title' => 'Tile vertically' ) ) ) );
        $this->row( 'Size', $this->seg_html( $n( 'size' ), $mod( $mods . '-size', 'auto' ), array( 'auto' => 'Auto', 'cover' => 'Cover', 'contain' => 'Contain' ) ) );
        $this->row( 'Position', $this->position_html( $n( 'position' ), $mod( $mods . '-position', $defaults['position'] ) ) );
        $this->row( 'Attachment', $this->seg_html( $n( 'attachment' ), $mod( $mods . '-attachment', 'scroll' ), array( 'scroll' => 'Scroll', 'fixed' => array( 'label' => 'Fixed', 'title' => 'Parallax: the image holds still while the page scrolls' ) ) ), array( 'hint' => 'Fixed is the parallax effect. It switches back to Scroll on touch devices (iOS renders it zoomed) and for visitors who ask for reduced motion.' ) );
        $this->row( 'Blend mode', $this->select_html( $n( 'blend' ), $mod( $mods . '-blend', 'normal' ), $this->blend_modes() ) );
        $this->color_row( 'Image overlay', $n( 'overlay' ), $mod( $mods . '-overlay' ), array( 'hint' => 'Tints the image. Use a colour with transparency: a solid colour hides the image entirely.' ) );
        echo '</div>';
    }

    /** Typography controls for one group (Font, size, spacing, style, shadow). */
    private function typo_block( $group, $typo ) {
        $n       = "typo[$group]";
        $weights = array( '' => 'Default', '100' => '100 Thin', '200' => '200 Extra Light', '300' => '300 Light', '400' => '400 Regular', '500' => '500 Medium', '600' => '600 Semi Bold', '700' => '700 Bold', '800' => '800 Extra Bold', '900' => '900 Black' );
        $sh      = (array) $this->tv( $typo, 'text_shadow' );

        echo '<div class="dsts-typo" data-group="' . esc_attr( $group ) . '">';
        echo '<div class="dsts-grid dsts-grid--font">';
        echo $this->cell( 'Font', $this->font_html( "{$n}[font_family]", $this->tv( $typo, 'font_family' ) ) );
        echo $this->cell( 'Weight', $this->select_html( "{$n}[font_weight]", $this->tv( $typo, 'font_weight' ), $weights, array( 'class' => 'dsts-weight' ) ) );
        echo '</div><div class="dsts-grid dsts-grid--3">';
        echo $this->cell( 'Size', $this->num_unit_html( "{$n}[font_size_length]", $this->tv( $typo, 'font_size', 'length' ), "{$n}[font_size_unit]", $this->tv( $typo, 'font_size', 'unit' ) ?: 'px', $this->units(), array( 'placeholder' => '—', 'step' => 'any', 'title' => 'Font size' ) ) );
        echo $this->cell( 'Line height', $this->num_unit_html( "{$n}[line_height_length]", $this->tv( $typo, 'line_height', 'length' ), "{$n}[line_height_unit]", $this->tv( $typo, 'line_height', 'unit' ), array( '' => '—', 'em' => 'em', 'px' => 'px', '%' => '%' ), array( 'placeholder' => '—', 'step' => 'any', 'title' => 'Line height' ) ) );
        echo $this->cell( 'Letter spacing', $this->num_unit_html( "{$n}[letter_spacing_length]", $this->tv( $typo, 'letter_spacing', 'length' ), "{$n}[letter_spacing_unit]", $this->tv( $typo, 'letter_spacing', 'unit' ) ?: 'px', array( 'px' => 'px', 'em' => 'em' ), array( 'placeholder' => '—', 'step' => 'any', 'title' => 'Letter spacing' ) ) );
        echo '</div><div class="dsts-grid dsts-grid--2">';
        echo $this->cell( 'Case', $this->seg_html( "{$n}[text_transform]", $this->tv( $typo, 'text_transform' ), array( '' => array( 'label' => 'Auto', 'title' => 'Default' ), 'none' => array( 'label' => 'Ab', 'title' => 'Normal' ), 'capitalize' => array( 'label' => 'Aa', 'title' => 'Capitalize' ), 'uppercase' => array( 'label' => 'AB', 'title' => 'UPPERCASE' ), 'lowercase' => array( 'label' => 'ab', 'title' => 'lowercase' ) ), array( 'class' => 'dsts-seg--sm' ) ) );
        echo $this->cell( 'Align', $this->seg_html( "{$n}[text_align]", $this->tv( $typo, 'text_align' ), array( '' => array( 'label' => 'Auto', 'title' => 'Default' ), 'left' => array( 'label' => 'Left', 'icon' => 'dashicons-editor-alignleft' ), 'center' => array( 'label' => 'Center', 'icon' => 'dashicons-editor-aligncenter' ), 'right' => array( 'label' => 'Right', 'icon' => 'dashicons-editor-alignright' ) ), array( 'class' => 'dsts-seg--sm' ) ) );
        echo '</div>';

        $has_more = '' !== (string) $this->tv( $typo, 'font_style' ) || '' !== (string) $this->tv( $typo, 'text_decoration' ) || '' !== (string) $this->tv( $typo, 'font_variant' ) || ! empty( $sh['color'] );
        echo '<details class="dsts-more"' . ( $has_more ? ' open' : '' ) . '><summary>Style, decoration &amp; shadow</summary>';
        echo '<div class="dsts-grid dsts-grid--3">';
        echo $this->cell( 'Style', $this->select_html( "{$n}[font_style]", $this->tv( $typo, 'font_style' ), array( '' => 'Default', 'normal' => 'Normal', 'italic' => 'Italic' ) ) );
        echo $this->cell( 'Decoration', $this->select_html( "{$n}[text_decoration]", $this->tv( $typo, 'text_decoration' ), array( '' => 'Default', 'none' => 'None', 'underline' => 'Underline', 'line-through' => 'Line Through', 'overline' => 'Overline' ) ) );
        echo $this->cell( 'Variant', $this->select_html( "{$n}[font_variant]", $this->tv( $typo, 'font_variant' ), array( '' => 'Default', 'normal' => 'Normal', 'small-caps' => 'Small Caps' ) ) );
        echo '</div><div class="dsts-grid dsts-grid--shadow">';
        echo $this->cell( 'Text shadow', $this->color_html( $n . '[shadow_color]', $sh['color'] ?? '' ) );
        echo $this->cell( 'X', $this->num_html( "{$n}[shadow_x]", $sh['horizontal'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow X' ) ) );
        echo $this->cell( 'Y', $this->num_html( "{$n}[shadow_y]", $sh['vertical'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow Y' ) ) );
        echo $this->cell( 'Blur', $this->num_html( "{$n}[shadow_blur]", $sh['blur'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow blur' ) ) );
        echo '</div></details>';
        echo '<div class="dsts-typo-foot"><button type="button" class="button-link dsts-typo-reset">Reset to theme default</button></div>';
        echo '</div>';
    }

    private function units() {
        return array( 'px' => 'px', 'em' => 'em', 'rem' => 'rem', '%' => '%', 'vw' => 'vw' );
    }

    /** Read a typography sub-value from an object/array. */
    private function tv( $typo, $key, $sub = null ) {
        $t = (array) $typo;
        if ( ! isset( $t[ $key ] ) ) { return ''; }
        if ( null === $sub ) { return $t[ $key ]; }
        $v = (array) $t[ $key ];
        return isset( $v[ $sub ] ) ? $v[ $sub ] : '';
    }

    /** Button-shape selector: radio cards each showing a live preview of its clip-path. */
    private function button_shape_html( $current, $prev_bg, $prev_fg ) {
        $prev_bg = $prev_bg ?: '#1cb0f6';
        $prev_fg = $prev_fg ?: '#ffffff';
        $out = '<div class="dsts-shapes" style="--dsts-btn-bg:' . esc_attr( $prev_bg ) . ';--dsts-btn-fg:' . esc_attr( $prev_fg ) . '">';
        foreach ( self::button_styles() as $key => $st ) {
            $checked = ( (string) $current === (string) $key );
            $clip    = '' !== $st['clip'] ? '-webkit-clip-path:' . $st['clip'] . ';clip-path:' . $st['clip'] . ';border-radius:0;' : '';
            $out    .= '<label class="dsts-shape' . ( $checked ? ' is-active' : '' ) . '">'
                . '<input type="radio" name="button_style" value="' . esc_attr( $key ) . '"' . checked( $checked, true, false ) . '>'
                . '<span class="dsts-shape-demo"><span class="dsts-shape-btn" style="' . esc_attr( $clip ) . '">Register</span></span>'
                . '<span class="dsts-shape-name">' . esc_html( $st['label'] ) . '</span>'
                . '<span class="dsts-shape-desc">' . esc_html( $st['desc'] ) . '</span></label>';
        }
        return $out . '</div>';
    }

    /* ----------------------------------------------------------------- Page */

    public function render_page() {
        if ( ! $this->allowed() ) {
            return;
        }
        if ( ! $this->available() ) {
            echo '<div class="wrap"><h1>Theme Setting</h1><p>Beaver Builder Global Styles are not available on this site.</p></div>';
            return;
        }

        $s      = FLBuilderGlobalStyles::get_settings( false );
        $colors = ! empty( $s->colors ) ? array_filter( (array) $s->colors, function( $c ) { $c = (array) $c; return ! empty( $c['label'] ) || ! empty( $c['color'] ); } ) : array();
        $prefix = isset( $s->prefix ) ? $s->prefix : '';
        $col    = function( $k ) use ( $s ) { return isset( $s->{$k} ) ? $s->{$k} : ''; };
        $tp     = function( $k ) use ( $s ) { return isset( $s->{$k} ) ? $s->{$k} : array(); };
        $mod    = function( $k, $d = '' ) { $v = get_theme_mod( $k, $d ); return ( false === $v ) ? $d : $v; };

        $sections = array(
            'colors'      => array( 'Colors', 'dashicons-art' ),
            'typography'  => array( 'Typography', 'dashicons-editor-textcolor' ),
            'buttons'     => array( 'Buttons', 'dashicons-button' ),
            'backgrounds' => array( 'Backgrounds', 'dashicons-format-image' ),
            'banner'      => array( 'Page Banner', 'dashicons-cover-image' ),
            'details'     => array( 'Details', 'dashicons-image-filter' ),
            'identity'    => array( 'Site Identity', 'dashicons-share' ),
            'code'        => array( 'Custom Code', 'dashicons-editor-code' ),
        );
        ?>
        <div class="wrap dsts dsts-nojs" id="dsts">
        <form id="dsts-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
            <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
            <?php wp_nonce_field( self::SAVE_ACTION ); ?>

            <header class="dsts-bar">
                <div class="dsts-bar-title">
                    <span class="dsts-bar-icon dashicons dashicons-admin-customizer" aria-hidden="true"></span>
                    <div><h1>Theme Setting</h1><p>Synced with Beaver Builder &rsaquo; Global Styles</p></div>
                </div>
                <div class="dsts-bar-actions">
                    <span class="dsts-status" id="dsts-status" role="status" aria-live="polite"><?php echo isset( $_GET['updated'] ) ? 'Saved' : 'All changes saved'; ?></span>
                    <button type="button" class="button dsts-preview-toggle" id="dsts-preview-toggle"><span class="dashicons dashicons-visibility" style="margin:4px 4px 0 0;font-size:16px"></span>Preview</button>
                    <button type="button" class="button dsts-discard" id="dsts-discard" hidden>Discard</button>
                    <button type="submit" class="button button-primary dsts-save" id="dsts-save" title="Save (Ctrl/Cmd + S)">Save changes</button>
                </div>
            </header>
            <hr class="wp-header-end">

            <div class="dsts-app">
                <nav class="dsts-rail" aria-label="Theme Setting sections">
                    <?php foreach ( $sections as $key => $sec ) : ?>
                        <button type="button" class="dsts-rail-btn" data-section="<?php echo esc_attr( $key ); ?>" aria-controls="dsts-sec-<?php echo esc_attr( $key ); ?>">
                            <span class="dashicons <?php echo esc_attr( $sec[1] ); ?>" aria-hidden="true"></span><span class="dsts-rail-label"><?php echo esc_html( $sec[0] ); ?></span><span class="dsts-dot" aria-hidden="true"></span>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <div class="dsts-controls" id="dsts-controls">

                <!-- COLORS -->
                <section class="dsts-section" id="dsts-sec-colors" data-section="colors">
                    <div class="dsts-sec-head"><h2>Colors</h2><p>The brand palette. Each colour becomes a <code>--<?php echo esc_html( $this->prefix_key() ); ?>-*</code> variable and a swatch in every Beaver Builder colour picker.</p></div>
                    <?php $this->card_open( 'Global colours', '', array( 'aside' => '<span class="dsts-pal-strip" id="dsts-pal-strip" aria-hidden="true"></span>' ) ); ?>
                        <div class="dsts-pal" id="dsts-pal">
                            <?php foreach ( $colors as $c ) : $c = (array) $c; $this->palette_row( $c['label'] ?? '', $c['color'] ?? '', $c['uid'] ?? '' ); endforeach; ?>
                        </div>
                        <template id="dsts-pal-tpl"><?php $this->palette_row( '', '', '' ); ?></template>
                        <button type="button" class="button dsts-pal-add" id="dsts-pal-add"><span class="dashicons dashicons-plus-alt2"></span> Add colour</button>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( '', '', array( 'class' => 'dsts-card--flat' ) ); ?>
                        <details class="dsts-more dsts-more--card"><summary>Variable prefix</summary>
                            <?php $this->row( 'Prefix', '<input type="text" class="regular-text dsts-prefix" name="prefix" value="' . esc_attr( $prefix ) . '" placeholder="fl-global" spellcheck="false">', array( 'help' => 'Changing it renames every colour variable. Settings on this page follow automatically; modules that reference a variable by name (for example <code>var(--fl-global-accent)</code>) will not.' ) ); ?>
                        </details>
                    <?php $this->card_close(); ?>
                </section>

                <!-- TYPOGRAPHY -->
                <section class="dsts-section" id="dsts-sec-typography" data-section="typography" hidden>
                    <div class="dsts-sec-head"><h2>Typography</h2><p>Body text, headings and links. Empty fields use the theme default.</p></div>
                    <div class="dsts-tabs" role="tablist">
                        <button type="button" class="dsts-tab is-active" data-tab="text" role="tab">Body text</button>
                        <button type="button" class="dsts-tab" data-tab="headings" role="tab">Headings</button>
                        <button type="button" class="dsts-tab" data-tab="links" role="tab">Links</button>
                    </div>
                    <div class="dsts-tabpanel" data-tab="text">
                        <?php $this->card_open( 'Body text' ); ?>
                            <?php $this->color_row( 'Colour', 'color[text_color]', $col( 'text_color' ) ); ?>
                            <?php $this->typo_block( 'text', $tp( 'text_typography' ) ); ?>
                        <?php $this->card_close(); ?>
                    </div>
                    <div class="dsts-tabpanel" data-tab="headings" hidden>
                        <?php $this->card_open( 'Headings' ); ?>
                            <div class="dsts-levels" role="tablist">
                                <?php foreach ( array( 'h' => 'All', 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ) as $lv => $lbl ) : ?>
                                    <button type="button" class="dsts-level<?php echo 'h' === $lv ? ' is-active' : ''; ?>" data-level="<?php echo esc_attr( $lv ); ?>"><?php echo esc_html( $lbl ); ?><span class="dsts-dot" aria-hidden="true"></span></button>
                                <?php endforeach; ?>
                            </div>
                            <?php foreach ( array( 'h', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $lv ) : ?>
                                <div class="dsts-levelpanel" data-level="<?php echo esc_attr( $lv ); ?>"<?php echo 'h' === $lv ? '' : ' hidden'; ?>>
                                    <p class="dsts-note"><?php echo 'h' === $lv ? 'Applies to every heading level. H1&ndash;H6 can override it.' : 'Overrides &ldquo;All&rdquo; for ' . esc_html( strtoupper( $lv ) ) . ' only. Leave empty to inherit.'; ?></p>
                                    <?php $this->color_row( 'Colour', 'color[' . $lv . '_color]', $col( $lv . '_color' ) ); ?>
                                    <?php $this->typo_block( $lv, $tp( $lv . '_typography' ) ); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php $this->card_close(); ?>
                    </div>
                    <div class="dsts-tabpanel" data-tab="links" hidden>
                        <?php $this->card_open( 'Links' ); ?>
                            <div class="dsts-grid dsts-grid--2 dsts-grid--colors">
                                <?php echo $this->cell( 'Colour', $this->color_html( 'color[link_color]', $col( 'link_color' ) ) ); ?>
                                <?php echo $this->cell( 'Hover colour', $this->color_html( 'color[link_hover_color]', $col( 'link_hover_color' ) ) ); ?>
                            </div>
                            <?php $this->typo_block( 'link', $tp( 'link_typography' ) ); ?>
                        <?php $this->card_close(); ?>
                    </div>
                </section>

                <!-- BUTTONS -->
                <section class="dsts-section" id="dsts-sec-buttons" data-section="buttons" hidden>
                    <div class="dsts-sec-head"><h2>Buttons</h2><p>Every standard button on the site: Beaver Builder buttons, the menu CTA, hero and card buttons, form submit buttons.</p></div>
                    <?php $this->card_open( 'Shape' ); ?>
                        <?php echo $this->button_shape_html( get_option( self::BUTTON_STYLE_OPTION, '' ), $this->color_info( $col( 'button_background' ) )['fill'], $this->color_info( $col( 'button_color' ) )['fill'] ); ?>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'Colours' ); ?>
                        <div class="dsts-grid dsts-grid--2 dsts-grid--colors">
                            <?php echo $this->cell( 'Background', $this->color_html( 'color[button_background]', $col( 'button_background' ), array( 'input_class' => 'btn-bg' ) ) ); ?>
                            <?php echo $this->cell( 'Hover background', $this->color_html( 'color[button_hover_background]', $col( 'button_hover_background' ) ) ); ?>
                            <?php echo $this->cell( 'Text', $this->color_html( 'color[button_color]', $col( 'button_color' ), array( 'input_class' => 'btn-fg' ) ) ); ?>
                            <?php echo $this->cell( 'Hover text', $this->color_html( 'color[button_hover_color]', $col( 'button_hover_color' ) ) ); ?>
                        </div>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'Typography' ); ?>
                        <?php $this->typo_block( 'button', $tp( 'button_typography' ) ); ?>
                    <?php $this->card_close(); ?>
                    <?php
                    $b  = (array) ( isset( $s->button_border ) ? $s->button_border : array() );
                    $bw = (array) ( $b['width'] ?? array() );
                    $br = (array) ( $b['radius'] ?? array() );
                    $bs = (array) ( $b['shadow'] ?? array() );
                    $this->card_open( 'Border & shadow' );
                        $this->row( 'Style', $this->select_html( 'border[button][style]', $b['style'] ?? '', array( '' => 'Default', 'none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double' ) ) );
                        $this->row( 'Width', $this->quad_html( 'border[button]', array( 'top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left' ), $bw, array( 'field' => 'width_' ) ) . '<span class="dsts-unit">px</span>' );
                        echo '<div class="dsts-grid dsts-grid--2 dsts-grid--colors">';
                        echo $this->cell( 'Border colour', $this->color_html( 'border[button][color]', $b['color'] ?? '' ) );
                        echo $this->cell( 'Border hover colour', $this->color_html( 'color[button_border_hover_color]', $col( 'button_border_hover_color' ) ) );
                        echo '</div>';
                        $this->row( 'Corner radius', $this->quad_html( 'border[button]', array( 'top_left' => 'TL', 'top_right' => 'TR', 'bottom_right' => 'BR', 'bottom_left' => 'BL' ), $br, array( 'field' => 'radius_' ) ) . '<span class="dsts-unit">px</span>', array( 'hint' => 'Angle and Clip shapes replace the radius with their own corners.' ) );
                        echo '<div class="dsts-grid dsts-grid--shadow">';
                        echo $this->cell( 'Shadow', $this->color_html( 'border[button][shadow_color]', $bs['color'] ?? '' ) );
                        echo $this->cell( 'X', $this->num_html( 'border[button][shadow_x]', $bs['horizontal'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow X' ) ) );
                        echo $this->cell( 'Y', $this->num_html( 'border[button][shadow_y]', $bs['vertical'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow Y' ) ) );
                        echo $this->cell( 'Blur', $this->num_html( 'border[button][shadow_blur]', $bs['blur'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow blur' ) ) );
                        echo '</div>';
                    $this->card_close();
                    ?>
                </section>

                <!-- BACKGROUNDS -->
                <section class="dsts-section" id="dsts-sec-backgrounds" data-section="backgrounds" hidden>
                    <div class="dsts-sec-head"><h2>Backgrounds</h2><p>The base themer template on every single page: the page band and the content box inside it.</p></div>
                    <?php $this->card_open( 'Page background', 'Paints the <strong>Base Container</strong> row: the band behind the content.' ); ?>
                        <?php $this->background_group( 'bg', 'fl-body-bg', array( 'repeat' => 'no-repeat', 'position' => 'center top' ) ); ?>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'Content background', 'Paints the <strong>content column</strong> inside that row: the box that holds the page body.' ); ?>
                        <?php $this->background_group( 'content_bg', 'fl-content-bg', array( 'repeat' => 'no-repeat', 'position' => 'center top' ) ); ?>
                    <?php $this->card_close(); ?>
                </section>

                <!-- PAGE BANNER -->
                <section class="dsts-section" id="dsts-sec-banner" data-section="banner" hidden>
                    <div class="dsts-sec-head"><h2>Page Banner</h2><p>Defaults for the Leagueapps Hero Banner at the top of each page. The module can still override them per page.</p></div>
                    <?php $this->card_open( 'Featured image as banner', 'Content types that may use their <strong>Featured Image</strong> as the banner photo. Unchecked types always get the text-only banner. Recommended off for <strong>Staff</strong>: portrait headshots crop badly in a wide banner.' ); ?>
                        <div class="dsts-chips"><input type="hidden" name="general[banner_featured_types_set]" value="1">
                        <?php
                        $ft_allowed = self::banner_featured_types();
                        foreach ( self::featured_type_choices() as $ft_name ) {
                            $ft_pt = get_post_type_object( $ft_name );
                            echo '<label class="dsts-chip"><input type="checkbox" name="general[banner_featured_types][]" value="' . esc_attr( $ft_pt->name ) . '"' . checked( in_array( $ft_pt->name, $ft_allowed, true ), true, false ) . '><span>' . esc_html( $ft_pt->labels->name ) . '</span></label>';
                        }
                        ?>
                        </div>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'Banner title' ); ?>
                        <?php $this->row( 'Title on photo banners', $this->seg_html( 'general[banner_photo_title]', $mod( 'ds-banner-photo-title', 'show' ), array( 'show' => 'Show', 'hide' => array( 'label' => 'Hide', 'title' => 'Image-only banner' ) ) ) ); ?>
                        <?php $this->color_row( 'Title colour on a photo', 'general[banner_photo_title_color]', $mod( 'ds-banner-photo-title-color', '' ), array( 'hint' => 'Default is the light title.' ) ); ?>
                        <?php $this->color_row( 'Title colour, no image', 'general[banner_nobg_title_color]', $mod( 'ds-banner-nobg-title-color', '' ) ); ?>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'Background when there is no image', 'Used on pages with no banner photo or video. A small tileable pattern suits Repeat &rarr; Tile.' ); ?>
                        <?php $this->background_group( 'banner_nobg', 'ds-banner-nobg', array( 'color' => 'var(--fl-global-light-background)', 'repeat' => 'repeat', 'position' => 'center center' ) ); ?>
                    <?php $this->card_close(); ?>
                </section>

                <!-- DETAILS -->
                <section class="dsts-section" id="dsts-sec-details" data-section="details" hidden>
                    <div class="dsts-sec-head"><h2>Details</h2><p>Small site-wide defaults the LeagueApps modules inherit.</p></div>
                    <?php $this->card_open( 'Corner radius', 'One radius for the LeagueApps card surfaces (cards, image tiles). A module with its own Corner Radius set keeps it.' ); ?>
                        <?php $cr = $mod( 'ds-corner-radius', '8' ); ?>
                        <div class="dsts-range-label">Radius</div>
                        <div class="dsts-range" data-demo="radius">
                            <input type="range" min="0" max="60" step="1" value="<?php echo esc_attr( $cr ); ?>" aria-label="Corner radius">
                            <span class="dsts-range-num"><?php echo $this->num_html( 'general[corner_radius]', $cr, array( 'min' => 0, 'max' => 60, 'title' => 'Corner radius in px' ) ); ?><span class="dsts-unit">px</span></span>
                            <span class="dsts-radius-demo" aria-hidden="true"></span>
                        </div>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'Outline text', 'Wrap a word in <code>{outline}&hellip;{/outline}</code> in any LeagueApps module heading and it renders hollow with this stroke. Each module can override it.' ); ?>
                        <?php $this->color_row( 'Outline colour', 'general[outline_color]', $mod( 'ds-outline-color', '' ), array( 'hint' => 'Default uses the text’s own colour as the stroke.' ) ); ?>
                        <?php $ow = $mod( 'ds-outline-width', '2' ); ?>
                        <div class="dsts-range-label">Stroke width</div>
                        <div class="dsts-range" data-demo="outline">
                            <input type="range" min="1" max="8" step="1" value="<?php echo esc_attr( $ow ); ?>" aria-label="Outline width">
                            <span class="dsts-range-num"><?php echo $this->num_html( 'general[outline_width]', $ow, array( 'min' => 1, 'max' => 8, 'title' => 'Outline width in px' ) ); ?><span class="dsts-unit">px</span></span>
                            <span class="dsts-outline-demo" aria-hidden="true">Outline</span>
                        </div>
                    <?php $this->card_close(); ?>
                </section>

                <!-- SITE ICON & SHARING -->
                <section class="dsts-section" id="dsts-sec-identity" data-section="identity" hidden>
                    <div class="dsts-sec-head"><h2>Site Identity</h2><p>How the site shows up in browser tabs and when a page is shared.</p></div>
                    <?php
                    $fav_id  = (int) get_option( 'site_icon', 0 );
                    $fav_url = $fav_id ? (string) wp_get_attachment_image_url( $fav_id, 'full' ) : '';
                    $this->card_open( 'Site icon', 'The favicon in browser tabs and bookmarks. Synced with <strong>Settings &rsaquo; General &rsaquo; Site Icon</strong>. Square, at least 512&times;512.' );
                        echo $this->image_html( 'favicon[url]', $fav_url, 'favicon[id]', $fav_id, array( 'shape' => 'icon', 'title' => 'Select site icon' ) );
                    $this->card_close();

                    $card_id  = (int) get_option( 'ds_social_card_id', 0 );
                    $card_url = (string) get_option( 'ds_social_card_url', '' );
                    if ( $card_id && '' === $card_url ) { $card_url = (string) wp_get_attachment_url( $card_id ); }
                    $this->card_open( 'Social sharing card', 'Shown when a page without its own image is shared (Open Graph / Twitter card). Also the Beaver Builder field <code>Site &rsaquo; Social Sharing Card</code>. 1200&times;630 or larger at that ratio.' );
                        echo $this->image_html( 'social_card[url]', $card_url, 'social_card[id]', $card_id, array( 'shape' => 'card', 'title' => 'Select social sharing card' ) );
                    $this->card_close();
                    ?>
                </section>

                <!-- CUSTOM CODE -->
                <section class="dsts-section" id="dsts-sec-code" data-section="code" hidden>
                    <div class="dsts-sec-head"><h2>Custom Code</h2><p>Site-wide code for this partner. CSS previews live; JavaScript runs only on the saved site.</p></div>
                    <?php $this->card_open( 'CSS', 'Output in the <code>&lt;head&gt;</code>. No <code>&lt;style&gt;</code> tags needed.' ); ?>
                        <textarea class="dsts-code" id="dsts-css-code" name="general[css_code]" spellcheck="false" placeholder="/* Custom CSS */"><?php echo esc_textarea( $mod( 'fl-css-code' ) ); ?></textarea>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'JavaScript', 'Output before <code>&lt;/body&gt;</code>. No <code>&lt;script&gt;</code> tags needed.' ); ?>
                        <textarea class="dsts-code" id="dsts-js-code" name="general[js_code]" spellcheck="false" placeholder="// Custom JavaScript"><?php echo esc_textarea( $mod( 'fl-js-code' ) ); ?></textarea>
                    <?php $this->card_close(); ?>
                </section>

                </div><!-- .dsts-controls -->

                <div class="dsts-preview" id="dsts-preview">
                    <div class="dsts-preview-bar">
                        <label class="screen-reader-text" for="dsts-preview-page">Preview page</label>
                        <select id="dsts-preview-page" class="dsts-preview-page"></select>
                        <div class="dsts-devices" role="group" aria-label="Preview size">
                            <button type="button" data-device="desktop" aria-pressed="true" title="Desktop"><span class="dashicons dashicons-desktop"></span></button>
                            <button type="button" data-device="tablet" aria-pressed="false" title="Tablet"><span class="dashicons dashicons-tablet"></span></button>
                            <button type="button" data-device="mobile" aria-pressed="false" title="Mobile"><span class="dashicons dashicons-smartphone"></span></button>
                        </div>
                        <span class="dsts-preview-state" id="dsts-preview-state"></span>
                        <a class="dsts-preview-open" id="dsts-preview-open" href="#" target="_blank" rel="noopener" title="Open the saved page in a new tab"><span class="dashicons dashicons-external"></span></a>
                        <button type="button" class="dsts-preview-hide" id="dsts-preview-hide" title="Hide preview"><span class="dashicons dashicons-hidden"></span><span class="screen-reader-text">Hide preview</span></button>
                    </div>
                    <div class="dsts-stage" id="dsts-stage">
                        <div class="dsts-frame" id="dsts-frame"><iframe id="dsts-iframe" title="Live preview" loading="eager"></iframe></div>
                        <div class="dsts-stage-note">Preview shows unsaved changes. Nothing on the live site changes until you save.</div>
                    </div>
                </div>
            </div><!-- .dsts-app -->
        </form>
        </div>
        <?php
    }

    /** One row of the global palette (also rendered into the "add colour" template). */
    private function palette_row( $label, $color, $uid ) {
        $prefix = $this->prefix_key();
        $slug   = '' !== trim( (string) $label ) ? $this->color_slug( $label ) : '';
        echo '<div class="dsts-pal-row" data-orig-name="' . esc_attr( $label ) . '">';
        echo '<span class="dsts-pal-handle" title="Drag to reorder" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>';
        echo $this->color_html( 'color_color[]', $color, array( 'globals' => false ) );
        echo '<div class="dsts-pal-meta"><input type="text" class="dsts-pal-name" name="color_label[]" value="' . esc_attr( $label ) . '" placeholder="Colour name" spellcheck="false">';
        echo '<code class="dsts-pal-var">' . ( $slug ? '--' . esc_html( $prefix . '-' . $slug ) : '&nbsp;' ) . '</code></div>';
        echo '<input type="hidden" name="color_uid[]" value="' . esc_attr( $uid ) . '">';
        echo '<button type="button" class="dsts-pal-remove" title="Remove colour"><span class="dashicons dashicons-trash"></span><span class="screen-reader-text">Remove colour</span></button>';
        echo '</div>';
    }

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
