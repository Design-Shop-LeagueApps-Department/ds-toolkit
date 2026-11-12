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
require_once __DIR__ . '/theme-setting/trait-data.php';
require_once __DIR__ . '/theme-setting/trait-preview.php';
require_once __DIR__ . '/theme-setting/trait-fields.php';
require_once __DIR__ . '/theme-setting/trait-page.php';
require_once __DIR__ . '/theme-setting/trait-save.php';

class DS_Theme_Setting {

    // The admin screen lives in features/theme-setting/, one trait per job. This file keeps the hooks, the CSS
    // the feature prints on the front end, and the button shapes.
    use DS_Theme_Setting_Data,
        DS_Theme_Setting_Preview,
        DS_Theme_Setting_Fields,
        DS_Theme_Setting_Page,
        DS_Theme_Setting_Save;

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
        // The BB theme compiles its skin CSS from the same fl-* mods this page saves, and
        // its LESS only reads #rgb / #rrggbb. Hand it a plain hex (see less_safe_theme_mods).
        add_filter( 'fl_theme_mods', array( $this, 'less_safe_theme_mods' ) );
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
.forminator-ui.forminator-custom-form button.forminator-button-submit{background:var(--fl-global-button-background,var(--fl-global-button));color:var(--fl-global-button-color,var(--fl-global-white));border:0;padding:14px 32px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;border-radius:6px;cursor:pointer;transition:background .2s ease,color .2s ease;}
.forminator-ui.forminator-custom-form button.forminator-button-submit:hover,
.forminator-ui.forminator-custom-form button.forminator-button-submit:focus{background:var(--fl-global-button-hover-background,var(--fl-global-accent));color:var(--fl-global-button-hover-color,var(--fl-global-white));}
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
            // GH #282. A fixed 14px lean, not a percentage, so a short REGISTER and a long
            // EXPLORE OUR TEAMS lean at the same angle (the reference is ~13px on a 54px
            // button). Both sides lean the same way; Angle slices opposite corners instead.
            'slant' => array(
                'label' => 'Slant',
                'desc'  => 'Parallel slanted sides for a sporty, dynamic look.',
                'clip'  => 'polygon(14px 0, 100% 0, calc(100% - 14px) 100%, 0 100%)',
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
        if ( ! self::can_access() ) {
            return;
        }
        $cap = DS_Toolkit::is_leagueapps_user() ? 'edit_posts' : 'edit_theme_options';
        add_menu_page( 'Theme Setting', 'Theme Setting', $cap, self::PAGE_SLUG, array( $this, 'render_page' ), 'dashicons-admin-customizer', '1.1' );
    }

    /**
     * Who may use the page, its preview and its endpoints: LeagueApps users who can edit
     * posts, and, when Settings > DS Toolkit > "Open Theme Setting to other users" is on,
     * anyone else who may change the site's design (edit_theme_options: Administrators and
     * the Partner role). Off by default, so the page stays LeagueApps-only.
     */
    public static function can_access() {
        if ( DS_Toolkit::is_leagueapps_user() ) {
            return current_user_can( 'edit_posts' );
        }
        $o = get_option( 'ds_toolkit_settings', array() );
        return is_array( $o ) && ! empty( $o['theme_setting_all_users'] ) && current_user_can( 'edit_theme_options' );
    }

    private function allowed() {
        return self::can_access();
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

}
