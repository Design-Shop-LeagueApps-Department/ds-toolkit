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
 * Mirrors the BB Global Styles UI: an "Elements" tab (Text / Heading with
 * All + H1–H6 sub-tabs / Link / Button) with the full typography control set
 * (Font, Style & Spacing, Text Shadow) and button Border, plus a "Colors" tab
 * (named global colors + Add button + prefix). Visible only to LeagueApps users.
 */
class DS_Theme_Setting {

    const PAGE_SLUG   = 'ds-theme-setting';
    const SAVE_ACTION = 'ds_theme_setting_save';

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'register_menu' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
            add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
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
            $decl  .= 'background-image:url(' . esc_url( $image ) . ');background-repeat:' . $repeat . ';background-position:' . $pos . ';background-size:' . $size . ';background-attachment:' . $attach . ';';
        }
        if ( '' !== $blend && 'normal' !== $blend ) { $decl .= 'background-blend-mode:' . $blend . ';'; }
        return $decl;
    }

    /**
     * Paints the Base Container ROW (class .ds-base-page-bg in the base themer
     * template) with the "Base Page Background" theme setting: the page band that
     * sits behind the content column on every single page.
     */
    public function output_page_bg_css() {
        $decl = $this->bg_decl( 'fl-body-bg' );
        if ( '' === $decl ) { return; }
        echo '<style id="ds-base-page-bg-css">.ds-base-page-bg > .fl-row-content-wrap{' . $decl . '}</style>' . "\n";
    }

    /**
     * Paints the content COLUMN (class .ds-base-content-bg, the column inside the
     * Base Container row that holds the body) with the "Base Page Content Background"
     * theme setting: the inner content box. Colour + optional pattern + blend mode.
     */
    public function output_content_bg_css() {
        $decl = $this->bg_decl( 'fl-content-bg' );
        if ( '' === $decl ) { return; }
        echo '<style id="ds-base-content-bg-css">.ds-base-content-bg > .fl-col-content{' . $decl . '}</style>' . "\n";
    }

    /**
     * Site-wide default background for the Page Banner (Leagueapps Hero Banner)
     * when a page has NO banner photo / video. The hero module can override per page.
     */
    public function output_banner_nobg_css() {
        $color = (string) get_theme_mod( 'ds-banner-nobg-color', 'var(--fl-global-light-background)' );
        $image = (string) get_theme_mod( 'ds-banner-nobg-image', '' );
        $blend = (string) get_theme_mod( 'ds-banner-nobg-blend', 'normal' );
        $decl  = '';
        if ( '' !== $color ) { $decl .= 'background-color:' . $color . ';'; }
        if ( '' !== $image ) {
            $repeat = (string) get_theme_mod( 'ds-banner-nobg-repeat', 'repeat' );
            $size   = (string) get_theme_mod( 'ds-banner-nobg-size', 'auto' );
            $decl  .= 'background-image:url(' . esc_url( $image ) . ');background-repeat:' . $repeat . ';background-position:center;background-size:' . $size . ';';
        }
        if ( '' !== $blend && 'normal' !== $blend ) { $decl .= 'background-blend-mode:' . $blend . ';'; }
        if ( '' === $decl ) { return; }
        echo '<style id="ds-banner-nobg-css">.ds-banner--no-bg{' . $decl . '}</style>' . "\n";
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

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style( 'ds-toolkit-admin', DS_TOOLKIT_URL . 'assets/css/admin.css', array(), DS_TOOLKIT_VERSION );
        wp_enqueue_media(); // for the General tab background-image picker

        // Modern, self-contained colour picker (Pickr). The polished BB module
        // picker is a React control bound to the builder app, so it can't run on
        // a standalone admin page; we bundle Pickr instead and feed it the BB
        // global colours. Picking a named global stores a synced var(--prefix-slug)
        // reference (kept in sync with the Colors tab), exactly like before.
        $g       = $this->globals_data();
        $globals = array();
        foreach ( $g['list'] as $c ) {
            $uid       = $c['uid'];
            $globals[] = array(
                'name'   => $c['name'],
                'hex'    => isset( $g['hex'][ $uid ] ) ? $g['hex'][ $uid ] : $c['color'],
                'varref' => isset( $g['var'][ $uid ] ) ? $g['var'][ $uid ] : '',
            );
        }

        wp_enqueue_style( 'pickr-nano', DS_TOOLKIT_URL . 'assets/vendor/pickr/nano.min.css', array(), '1.9.1' );
        wp_enqueue_script( 'pickr', DS_TOOLKIT_URL . 'assets/vendor/pickr/pickr.min.js', array( 'jquery' ), '1.9.1', true );

        wp_add_inline_style( 'pickr-nano', '
.ds-color{display:inline-flex;align-items:center;gap:9px;}
.ds-color .pcr-button{width:44px;height:30px;border-radius:5px;border:1px solid #8c8f94;box-shadow:none;}
.ds-color .pcr-button::after,.ds-color .pcr-button::before{border-radius:5px;}
.ds-color-text{font-size:12px;color:#50575e;font-family:Menlo,Monaco,Consolas,monospace;}
.pcr-app{z-index:100100;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.22);}
.pcr-app .ds-pcr-globals{border-top:1px solid #e2e4e7;margin-top:12px;padding-top:6px;max-height:172px;overflow:auto;}
.pcr-app .ds-pcr-globals-h{font-size:11px;font-weight:700;color:#646970;padding:2px 4px 6px;text-transform:uppercase;letter-spacing:.04em;}
.pcr-app .ds-pcr-global{display:flex;align-items:center;gap:9px;width:100%;background:none;border:0;padding:6px;cursor:pointer;border-radius:5px;font-size:13px;color:#1e1e1e;text-align:left;box-shadow:none;}
.pcr-app .ds-pcr-global:hover{background:rgba(0,0,0,.06);}
.pcr-app .ds-pcr-sw{width:20px;height:20px;border-radius:4px;border:1px solid rgba(0,0,0,.2);flex:0 0 auto;}
' );

        wp_add_inline_script( 'pickr', 'window.dsTsGlobals=' . wp_json_encode( $globals ) . ';', 'before' );
        $anchor = 'pickr';

        $js = <<<'JS'
jQuery(function($){
  var GLOBALS = window.dsTsGlobals || [];
  // Resolve a stored value (var(--..)/hex/rgb) to a hex Pickr can display.
  function dsResolve(v){
    v=(''+(v||'')).trim(); if(!v) return null;
    if(/^var\(/.test(v)){ for(var i=0;i<GLOBALS.length;i++){ if(GLOBALS[i].varref===v) return GLOBALS[i].hex; } return null; }
    if(/^#?[0-9a-fA-F]{3,6}$/.test(v)) return '#'+v.replace(/^#/,'');
    return v;
  }
  function dsInitColor(wrap){
    if(wrap.dsInit) return; wrap.dsInit=true;
    var input=wrap.querySelector('.ds-color-input');
    var text=wrap.querySelector('.ds-color-text');
    var btn=wrap.querySelector('.ds-color-btn');
    if(!input||!btn||typeof Pickr==='undefined') return;
    var disp=dsResolve(input.value)||'rgba(0,0,0,0)';
    var ready=false;
    var pickr=Pickr.create({el:btn,theme:'nano',default:disp,defaultRepresentation:'HEX',position:'bottom-start',
      components:{preview:true,opacity:true,hue:true,interaction:{hex:true,rgba:true,input:true,clear:true,save:false}}});
    function commit(v){ input.value=v; if(text){ text.textContent=(v||'None'); } }
    pickr.on('init',function(){
      var app=pickr.getRoot().app;
      if(GLOBALS.length){
        var sec=document.createElement('div'); sec.className='ds-pcr-globals';
        var h=document.createElement('div'); h.className='ds-pcr-globals-h'; h.textContent='Global Colors'; sec.appendChild(h);
        GLOBALS.forEach(function(gc){
          var b=document.createElement('button'); b.type='button'; b.className='ds-pcr-global'; b.title=gc.name;
          var sw=document.createElement('span'); sw.className='ds-pcr-sw'; sw.style.background=gc.hex;
          var nm=document.createElement('span'); nm.textContent=gc.name;
          b.appendChild(sw); b.appendChild(nm);
          b.addEventListener('click',function(e){ e.preventDefault(); commit(gc.varref); pickr.setColor(gc.hex,true); pickr.hide(); });
          sec.appendChild(b);
        });
        app.appendChild(sec);
      }
      ready=true;
    });
    pickr.on('change',function(c){ if(!ready) return; commit(c?c.toHEXA().toString():''); });
    pickr.on('clear',function(){ if(!ready) return; commit(''); });
  }
  function dsInitColors(scope){ (scope||document).querySelectorAll('.ds-color').forEach(dsInitColor); }
  dsInitColors(document);

  $(".ds-ts-tab").on("click",function(e){e.preventDefault();var t=$(this).data("tab");
    $(this).closest(".ds-ts-tabs").find(".ds-ts-tab").removeClass("is-active");$(this).addClass("is-active");
    $("#ds-ts-panel-"+t).siblings(".ds-ts-panel").hide();$("#ds-ts-panel-"+t).show();
    try{ localStorage.setItem("dsTsTab", t); }catch(e){} });
  // Restore the last active tab after a save->reload so edits stay visible.
  try{ var savedTab=localStorage.getItem("dsTsTab");
    if(savedTab && $('.ds-ts-tab[data-tab="'+savedTab+'"]').length){ $('.ds-ts-tab[data-tab="'+savedTab+'"]').trigger("click"); } }catch(e){}
  $(".ds-ts-subtab").on("click",function(e){e.preventDefault();var t=$(this).data("sub");
    var $wrap=$(this).closest(".ds-ts-subtabs-wrap");
    $wrap.find(".ds-ts-subtab").removeClass("is-active");$(this).addClass("is-active");
    $wrap.find(".ds-ts-subpanel").hide();$wrap.find("#ds-ts-sub-"+t).show();});
  $(document).on("click",".ds-ts-acc-head",function(e){e.stopPropagation();$(this).closest(".ds-ts-acc").toggleClass("is-open");});
  $("#ds-ts-add-color").on("click",function(e){e.preventDefault();
    var html='<div class="ds-ts-color-row"><input type="hidden" name="color_uid[]" value=""><input type="text" class="regular-text" name="color_label[]" placeholder="Name"> '+
      '<span class="ds-color" data-value=""><span class="ds-color-btn"></span><input type="hidden" class="ds-color-input" name="color_color[]" value=""><span class="ds-color-text">None</span></span> '+
      '<button type="button" class="button ds-ts-color-remove" title="Remove">&times;</button></div>';
    var $row=$(html);$("#ds-ts-colors").append($row);dsInitColors($row[0]);});
  $(document).on("click",".ds-ts-color-remove",function(){$(this).closest(".ds-ts-color-row").remove();});
  // Button-shape radio cards: reflect the selection on the card frame.
  $(document).on("change",'input[name="button_style"]',function(){
    $(".ds-ts-btnstyle").removeClass("is-active");
    $(this).closest(".ds-ts-btnstyle").addClass("is-active");});
  // Background-image / favicon media picker (General tab)
  $(document).on("click",".ds-ts-media-select",function(e){e.preventDefault();var $w=$(this).closest(".ds-ts-media");
    var f=wp.media({title:"Select Image",multiple:false,library:{type:"image"}});
    f.on("select",function(){var a=f.state().get("selection").first().toJSON();var u=(a.sizes&&a.sizes.large)?a.sizes.large.url:a.url;
      $w.find(".ds-ts-media-url").val(a.url);$w.find(".ds-ts-media-id").val(a.id);
      $w.find(".ds-ts-media-preview").attr("src",u).show();$w.find(".ds-ts-media-remove").show();});f.open();});
  $(document).on("click",".ds-ts-media-remove",function(e){e.preventDefault();var $w=$(this).closest(".ds-ts-media");
    $w.find(".ds-ts-media-url").val("");$w.find(".ds-ts-media-id").val("");$w.find(".ds-ts-media-preview").attr("src","").hide();$(this).hide();});
});
JS;
        wp_add_inline_script( $anchor, $js );
    }

    /* ------------------------------------------------------------ Renderers */

    private $font_name_cache = null;

    /** System+Typekit and Google font NAME lists, fetched once per request. */
    private function font_names() {
        if ( null === $this->font_name_cache ) {
            if ( ! class_exists( 'FLFontFamilies' ) ) {
                $this->font_name_cache = array( 'system' => array(), 'google' => array() );
            } else {
                $system = apply_filters( 'fl_theme_system_fonts', FLFontFamilies::get_system() );
                $this->font_name_cache = array(
                    'system' => array_values( array_diff( array_keys( $system ), array( 'Default' ) ) ),
                    'google' => array_keys( FLFontFamilies::get_google() ),
                );
            }
        }
        return $this->font_name_cache;
    }

    private function font_options( $selected ) {
        $names = $this->font_names();
        $html  = '<option value="Default"' . selected( $selected, 'Default', false ) . '>Default</option>';
        $html .= '<optgroup label="System / Custom">';
        foreach ( $names['system'] as $name ) {
            $html .= '<option value="' . esc_attr( $name ) . '"' . selected( $selected, $name, false ) . '>' . esc_html( $name ) . '</option>';
        }
        $html .= '</optgroup><optgroup label="Google">';
        foreach ( $names['google'] as $name ) {
            $html .= '<option value="' . esc_attr( $name ) . '"' . selected( $selected, $name, false ) . '>' . esc_html( $name ) . '</option>';
        }
        return $html . '</optgroup>';
    }

    private function sel( $name, $val, $choices ) {
        $out = '<select name="' . esc_attr( $name ) . '">';
        foreach ( $choices as $v => $l ) {
            $out .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $val, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
        }
        return $out . '</select>';
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

    private function field_open( $label ) {
        echo '<div class="ds-ts-field"><label>' . esc_html( $label ) . '</label><div class="ds-ts-input">';
    }
    private function field_close() { echo '</div></div>'; }

    /**
     * Beaver Builder's label_to_key() for the CSS-variable slug (must match
     * how BB generates --<prefix>-<slug> for global colors).
     */
    private function color_slug( $label ) {
        $label = str_replace( array( '_', ' ' ), '-', strtolower( trim( (string) $label ) ) );
        return preg_replace( '/[^A-Za-z0-9\-]/', '', $label );
    }

    /**
     * The global colors prepared for the picker: the named list it renders, plus
     * uid->var() (the synced reference stored when one is picked) and uid->hex
     * (for swatch preview) maps. Reads the live BB Global Styles, so it stays in
     * sync with the Colors tab.
     */
    private $globals_cache = null;
    private function globals_data() {
        if ( null !== $this->globals_cache ) { return $this->globals_cache; }
        $list = array(); $varmap = array(); $hexmap = array(); $byslug = array();
        if ( $this->available() ) {
            $s      = FLBuilderGlobalStyles::get_settings( false );
            $prefix = ! empty( $s->prefix ) ? preg_replace( '/[^a-z0-9_-]/i', '', $s->prefix ) : 'fl-global';
            foreach ( (array) ( $s->colors ?? array() ) as $c ) {
                $c     = (array) $c;
                $label = isset( $c['label'] ) ? trim( $c['label'] ) : '';
                $color = isset( $c['color'] ) ? trim( $c['color'] ) : '';
                $uid   = isset( $c['uid'] ) ? $c['uid'] : '';
                if ( '' === $label || '' === $color || '' === $uid ) { continue; }
                $hex  = preg_match( '/^[0-9a-fA-F]{3,6}$/', $color ) ? '#' . $color : $color;
                $slug = $this->color_slug( $label );
                $var  = 'var(--' . $prefix . '-' . $slug . ')';
                $list[]            = array( 'color' => $hex, 'name' => $label, 'uid' => $uid );
                $varmap[ $uid ]    = $var;
                $hexmap[ $uid ]    = $hex;
                $byslug[ '--' . $prefix . '-' . $slug ] = $hex;
            }
        }
        $this->globals_cache = array( 'list' => $list, 'var' => $varmap, 'hex' => $hexmap, 'byslug' => $byslug );
        return $this->globals_cache;
    }

    /** Resolve a stored value to a CSS color for the swatch preview (var() -> referenced hex). */
    private function swatch_color( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return ''; }
        if ( preg_match( '/^var\(\s*(--[A-Za-z0-9_-]+)\s*\)$/', $value, $m ) ) {
            $g = $this->globals_data();
            return isset( $g['byslug'][ $m[1] ] ) ? $g['byslug'][ $m[1] ] : '';
        }
        if ( preg_match( '/^#?[0-9a-fA-F]{3,6}$/', $value ) ) { return '#' . ltrim( $value, '#' ); }
        return $value; // rgb()/rgba()
    }

    /**
     * A colour-picker field rendered with Pickr (see enqueue_assets). Markup:
     * a `.ds-color-btn` placeholder Pickr turns into the swatch button, a hidden
     * input that submits the value, and a `.ds-color-text` label showing it.
     *
     * The stored value is written verbatim (hex `#rrggbb`, `rgb()/rgba()`, or a
     * synced global reference `var(--prefix-slug)`); Pickr resolves var()/hex for
     * display via dsResolve, and `sanitize_color()` normalises on save.
     */
    private function bb_color( $name, $value ) {
        $value    = trim( (string) $value );
        $swatch   = $this->swatch_color( $value ); // resolves var()->hex for the preview
        $btnStyle = $swatch ? ' style="background:' . esc_attr( $swatch ) . '"' : '';
        $label    = '' !== $value ? $value : 'None';
        return '<span class="ds-color" data-value="' . esc_attr( $value ) . '">'
            . '<span class="ds-color-btn"' . $btnStyle . '></span>'
            . '<input type="hidden" class="ds-color-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">'
            . '<span class="ds-color-text">' . esc_html( $label ) . '</span>'
            . '</span>';
    }

    private function color_field( $label, $name, $value ) {
        $this->field_open( $label );
        echo $this->bb_color( $name, $value );
        $this->field_close();
    }

    private function acc( $title, $open = false ) {
        echo '<div class="ds-ts-acc' . ( $open ? ' is-open' : '' ) . '"><div class="ds-ts-acc-head"><span class="dashicons dashicons-arrow-right-alt2"></span> ' . esc_html( $title ) . '</div><div class="ds-ts-acc-body">';
    }
    private function acc_end() { echo '</div></div>'; }

    /** Full typography control set for one group (Font / Style & Spacing / Text Shadow). */
    private function render_typography( $group, $typo ) {
        $weights = array( '' => 'Default', '100' => '100', '200' => '200', '300' => '300', '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800', '900' => '900' );
        $lh_units = array( '' => '—', 'em' => 'em', 'px' => 'px', '%' => '%' );
        $aligns  = array( '' => 'Default', 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' );
        $trans   = array( '' => 'Default', 'none' => 'Normal', 'capitalize' => 'Capitalize', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase' );
        $decos   = array( '' => 'Default', 'none' => 'None', 'underline' => 'Underline', 'line-through' => 'Line Through', 'overline' => 'Overline' );
        $styles  = array( '' => 'Default', 'normal' => 'Normal', 'italic' => 'Italic' );
        $variant = array( '' => 'Default', 'normal' => 'Normal', 'small-caps' => 'Small Caps' );
        $n = "typo[$group]";

        echo '<div class="ds-ts-typo">';
        $this->acc( 'Font', false );
            $this->field_open( 'Family' ); echo '<select name="' . esc_attr( $n ) . '[font_family]">' . $this->font_options( $this->tv( $typo, 'font_family' ) ?: 'Default' ) . '</select>'; $this->field_close();
            $this->field_open( 'Weight' ); echo $this->sel( "{$n}[font_weight]", $this->tv( $typo, 'font_weight' ), $weights ); $this->field_close();
            $this->field_open( 'Size' );
                echo '<input type="number" class="ds-ts-num" name="' . esc_attr( $n ) . '[font_size_length]" value="' . esc_attr( $this->tv( $typo, 'font_size', 'length' ) ) . '"> ';
                echo $this->sel( "{$n}[font_size_unit]", $this->tv( $typo, 'font_size', 'unit' ) ?: 'px', $this->units() );
            $this->field_close();
            $this->field_open( 'Line Height' );
                echo '<input type="number" step="0.01" class="ds-ts-num" name="' . esc_attr( $n ) . '[line_height_length]" value="' . esc_attr( $this->tv( $typo, 'line_height', 'length' ) ) . '"> ';
                echo $this->sel( "{$n}[line_height_unit]", $this->tv( $typo, 'line_height', 'unit' ), $lh_units );
            $this->field_close();
            $this->field_open( 'Align' ); echo $this->sel( "{$n}[text_align]", $this->tv( $typo, 'text_align' ), $aligns ); $this->field_close();
        $this->acc_end();

        $this->acc( 'Style & Spacing', false );
            $this->field_open( 'Spacing' );
                echo '<input type="number" step="0.1" class="ds-ts-num" name="' . esc_attr( $n ) . '[letter_spacing_length]" value="' . esc_attr( $this->tv( $typo, 'letter_spacing', 'length' ) ) . '"> ';
                echo $this->sel( "{$n}[letter_spacing_unit]", $this->tv( $typo, 'letter_spacing', 'unit' ) ?: 'px', array( 'px' => 'px', 'em' => 'em' ) );
            $this->field_close();
            $this->field_open( 'Transform' ); echo $this->sel( "{$n}[text_transform]", $this->tv( $typo, 'text_transform' ), $trans ); $this->field_close();
            $this->field_open( 'Decoration' ); echo $this->sel( "{$n}[text_decoration]", $this->tv( $typo, 'text_decoration' ), $decos ); $this->field_close();
            $this->field_open( 'Style' ); echo $this->sel( "{$n}[font_style]", $this->tv( $typo, 'font_style' ), $styles ); $this->field_close();
            $this->field_open( 'Variant' ); echo $this->sel( "{$n}[font_variant]", $this->tv( $typo, 'font_variant' ), $variant ); $this->field_close();
        $this->acc_end();

        $sh = (array) $this->tv( $typo, 'text_shadow' );
        $this->acc( 'Text Shadow', false );
            $this->field_open( 'Color' ); echo $this->bb_color( $n . '[shadow_color]', $sh['color'] ?? '' ); $this->field_close();
            $this->field_open( 'X' ); echo '<input type="number" class="ds-ts-num" name="' . esc_attr( $n ) . '[shadow_x]" value="' . esc_attr( $sh['horizontal'] ?? '' ) . '">'; $this->field_close();
            $this->field_open( 'Y' ); echo '<input type="number" class="ds-ts-num" name="' . esc_attr( $n ) . '[shadow_y]" value="' . esc_attr( $sh['vertical'] ?? '' ) . '">'; $this->field_close();
            $this->field_open( 'Blur' ); echo '<input type="number" class="ds-ts-num" name="' . esc_attr( $n ) . '[shadow_blur]" value="' . esc_attr( $sh['blur'] ?? '' ) . '">'; $this->field_close();
        $this->acc_end();
        echo '</div>';
    }

    private function render_border( $group, $border ) {
        $b = (array) $border;
        $styles = array( '' => 'Default', 'none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double' );
        $n = "border[$group]";
        $w = (array) ( $b['width'] ?? array() );
        $r = (array) ( $b['radius'] ?? array() );
        $sh = (array) ( $b['shadow'] ?? array() );

        $this->acc( 'General', false );
            $this->field_open( 'Style' ); echo $this->sel( "{$n}[style]", $b['style'] ?? '', $styles ); $this->field_close();
            $this->field_open( 'Width (px)' );
                foreach ( array( 'top' => 'T', 'right' => 'R', 'bottom' => 'B', 'left' => 'L' ) as $side => $lbl ) {
                    echo '<input type="number" class="ds-ts-num ds-ts-quad" title="' . esc_attr( $lbl ) . '" name="' . esc_attr( $n ) . '[width_' . $side . ']" value="' . esc_attr( $w[ $side ] ?? '' ) . '" placeholder="' . esc_attr( $lbl ) . '"> ';
                }
            $this->field_close();
            $this->color_field( 'Color', "{$n}[color]", $b['color'] ?? '' );
        $this->acc_end();

        $this->acc( 'Radius & Shadow', false );
            $this->field_open( 'Radius (px)' );
                foreach ( array( 'top_left' => 'TL', 'top_right' => 'TR', 'bottom_left' => 'BL', 'bottom_right' => 'BR' ) as $k => $lbl ) {
                    echo '<input type="number" class="ds-ts-num ds-ts-quad" name="' . esc_attr( $n ) . '[radius_' . $k . ']" value="' . esc_attr( $r[ $k ] ?? '' ) . '" placeholder="' . esc_attr( $lbl ) . '"> ';
                }
            $this->field_close();
            $this->field_open( 'Shadow Color' ); echo $this->bb_color( $n . '[shadow_color]', $sh['color'] ?? '' ); $this->field_close();
            $this->field_open( 'Shadow X/Y/Blur' );
                echo '<input type="number" class="ds-ts-num ds-ts-quad" name="' . esc_attr( $n ) . '[shadow_x]" value="' . esc_attr( $sh['horizontal'] ?? '' ) . '" placeholder="X"> ';
                echo '<input type="number" class="ds-ts-num ds-ts-quad" name="' . esc_attr( $n ) . '[shadow_y]" value="' . esc_attr( $sh['vertical'] ?? '' ) . '" placeholder="Y"> ';
                echo '<input type="number" class="ds-ts-num ds-ts-quad" name="' . esc_attr( $n ) . '[shadow_blur]" value="' . esc_attr( $sh['blur'] ?? '' ) . '" placeholder="Blur"> ';
            $this->field_close();
        $this->acc_end();
    }

    /** Button-shape selector: radio cards each showing a live preview of its clip-path. */
    private function render_button_style( $current, $prev_bg, $prev_fg ) {
        $prev_bg = $prev_bg ?: '#1cb0f6';
        $prev_fg = $prev_fg ?: '#0b1b2b';
        echo '<div class="ds-ts-field ds-ts-btnstyle-field"><label>Button Shape</label><div class="ds-ts-input"><div class="ds-ts-btnstyles">';
        foreach ( self::button_styles() as $key => $st ) {
            $checked = ( (string) $current === (string) $key );
            $clip    = '' !== $st['clip']
                ? 'overflow:hidden;-webkit-clip-path:' . $st['clip'] . ';clip-path:' . $st['clip'] . ';border-radius:0;'
                : 'border-radius:4px;';
            $btn_style = 'background:' . $prev_bg . ';color:' . $prev_fg . ';' . $clip;
            echo '<label class="ds-ts-btnstyle' . ( $checked ? ' is-active' : '' ) . '">';
            echo '<input type="radio" name="button_style" value="' . esc_attr( $key ) . '"' . checked( $checked, true, false ) . '>';
            echo '<span class="ds-ts-btnstyle-preview"><span class="ds-ts-btnstyle-btn" style="' . esc_attr( $btn_style ) . '">Register Now</span></span>';
            echo '<span class="ds-ts-btnstyle-name">' . esc_html( $st['label'] ) . '</span>';
            echo '<span class="ds-ts-btnstyle-desc">' . esc_html( $st['desc'] ) . '</span>';
            echo '</label>';
        }
        echo '</div></div></div>';
    }

    public function render_page() {
        if ( ! current_user_can( 'edit_posts' ) || ! DS_Toolkit::is_leagueapps_user() ) {
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
        // General tab reads the BB Theme customizer theme_mods.
        $mod    = function( $k, $d = '' ) { $v = get_theme_mod( $k, $d ); return ( false === $v ) ? $d : $v; };
        ?>
        <div class="wrap dst-wrap">
            <div class="dst-header">
                <div class="dst-header-icon"><span class="dashicons dashicons-admin-customizer"></span></div>
                <div class="dst-header-text">
                    <h1>Theme Setting</h1>
                    <p>LeagueApps-internal global styling. Synced with Beaver Builder &rsaquo; Global Styles.</p>
                </div>
            </div>
            <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Global styles saved.</p></div><?php endif; ?>

            <?php
            // Define the global-color CSS variables on this admin page so that
            // var(--prefix-slug) swatch previews resolve (BB only emits these on
            // the front end). Without this the picker paints synced swatches grey.
            $gv = $this->globals_data();
            if ( ! empty( $gv['byslug'] ) ) {
                echo '<style id="ds-ts-global-vars">:root{';
                foreach ( $gv['byslug'] as $varname => $hex ) { echo esc_html( $varname ) . ':' . esc_html( $hex ) . ';'; }
                echo '}</style>';
            }
            ?>

            <style>
                /* Vertical left-rail tab layout */
                .ds-ts-layout{display:flex;align-items:flex-start;gap:0;margin:16px 0 0;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;background:#fff}
                .ds-ts-tabs{display:flex;flex-direction:column;flex:0 0 200px;background:#f6f7f7;border-right:1px solid #dcdcde;padding:8px 0}
                .ds-ts-tab{display:flex;align-items:center;gap:10px;padding:12px 18px;cursor:pointer;border-left:3px solid transparent;background:transparent;font-weight:600;color:#50575e;text-decoration:none;font-size:14px}
                .ds-ts-tab .dashicons{font-size:18px;width:18px;height:18px;color:#787c82}
                .ds-ts-tab:hover{background:#f0f0f1;color:#1d2327}
                .ds-ts-tab.is-active{background:#fff;border-left-color:#2271b1;color:#2271b1}
                .ds-ts-tab.is-active .dashicons{color:#2271b1}
                .ds-ts-panels{flex:1;min-width:0;align-self:stretch}
                .ds-ts-panel{background:#fff;padding:0}
                @media (max-width:782px){.ds-ts-layout{flex-direction:column}.ds-ts-tabs{flex-basis:auto;flex-direction:row;border-right:0;border-bottom:1px solid #dcdcde}.ds-ts-tab{border-left:0;border-bottom:3px solid transparent}.ds-ts-tab.is-active{border-left:0;border-bottom-color:#2271b1}}
                .ds-ts-acc{border-bottom:1px solid #f0f0f1}
                .ds-ts-acc-head{display:flex;align-items:center;gap:8px;padding:13px 18px;cursor:pointer;font-weight:600;font-size:14px;user-select:none}
                .ds-ts-acc-head .dashicons{transition:transform .15s}
                .ds-ts-acc.is-open>.ds-ts-acc-head .dashicons{transform:rotate(90deg)}
                .ds-ts-acc-body{display:none;padding:6px 18px 16px 40px}
                .ds-ts-acc.is-open>.ds-ts-acc-body{display:block}
                .ds-ts-typo .ds-ts-acc-body{padding-left:24px}
                .ds-ts-field{display:flex;align-items:center;gap:12px;padding:7px 0}
                .ds-ts-field>label{flex:0 0 150px;font-weight:600;font-size:13px;color:#1d2327}
                .ds-ts-field .ds-ts-input{flex:1;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
                .ds-ts-num{width:90px}.ds-ts-quad{width:64px}
                .ds-ts-field select{min-width:120px;max-width:280px}
                .ds-ts-subtabs{display:flex;gap:2px;background:#f0f0f1;border-radius:6px;padding:4px;margin:8px 0 14px;flex-wrap:wrap}
                .ds-ts-subtab{flex:1;text-align:center;padding:8px 10px;border-radius:4px;cursor:pointer;text-decoration:none;color:#50575e;font-weight:600;font-size:13px}
                .ds-ts-subtab.is-active{background:#fff;color:#2271b1;box-shadow:0 1px 2px rgba(0,0,0,.08)}
                /* Button shape selector */
                .ds-ts-btnstyle-field{align-items:flex-start}
                .ds-ts-btnstyles{display:flex;gap:14px;flex-wrap:wrap;width:100%}
                .ds-ts-btnstyle{position:relative;flex:1 1 150px;max-width:210px;border:2px solid #dcdcde;border-radius:8px;padding:14px 12px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:7px;text-align:center;background:#fff;transition:border-color .15s,box-shadow .15s}
                .ds-ts-btnstyle:hover{border-color:#2271b1}
                .ds-ts-btnstyle.is-active{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
                .ds-ts-btnstyle input{position:absolute;opacity:0;pointer-events:none}
                .ds-ts-btnstyle-preview{height:50px;display:flex;align-items:center;justify-content:center;width:100%}
                .ds-ts-btnstyle-btn{display:inline-block;padding:13px 22px;font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;line-height:1;white-space:nowrap}
                .ds-ts-btnstyle-name{font-weight:700;font-size:13px;color:#1d2327}
                .ds-ts-btnstyle-desc{font-size:11px;color:#646970;line-height:1.35}
                .ds-ts-color-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
                #ds-ts-add-color{margin-top:6px}
                .ds-ts-help{color:#646970;font-size:12px;margin:2px 0 14px 0}
                .ds-ts-code{width:100%;min-height:200px;font-family:Menlo,Monaco,Consolas,monospace;font-size:13px;line-height:1.5;tab-size:2;background:#1d2327;color:#e2e4e7;border:1px solid #1d2327;border-radius:4px;padding:12px;white-space:pre}
                .ds-ts-media .ds-ts-input{flex-wrap:wrap}
                /* Beaver Builder color field (swatch + hex), scoped to this page so the picker overlay is untouched */
                .dst-wrap .fl-color-picker{display:inline-flex;align-items:center;border:1px solid #8c8f94;border-radius:4px;overflow:hidden;background:#fff;height:32px}
                .dst-wrap .fl-color-picker-color{width:42px;align-self:stretch;cursor:pointer;flex:0 0 auto;border-right:1px solid #dcdcde;
                  background-image:linear-gradient(45deg,#e0e0e0 25%,transparent 25%),linear-gradient(-45deg,#e0e0e0 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e0e0e0 75%),linear-gradient(-45deg,transparent 75%,#e0e0e0 75%);background-size:10px 10px;background-position:0 0,0 5px,5px -5px,-5px 0}
                .dst-wrap .fl-color-picker-value{border:0!important;box-shadow:none!important;outline:0!important;width:130px;height:30px;padding:0 8px;margin:0;background:transparent}
            </style>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
                <?php wp_nonce_field( self::SAVE_ACTION ); ?>

                <div class="ds-ts-layout">
                <div class="ds-ts-tabs">
                    <a href="#" class="ds-ts-tab is-active" data-tab="general"><span class="dashicons dashicons-admin-settings"></span> General</a>
                    <a href="#" class="ds-ts-tab" data-tab="elements"><span class="dashicons dashicons-editor-textcolor"></span> Elements</a>
                    <a href="#" class="ds-ts-tab" data-tab="colors"><span class="dashicons dashicons-art"></span> Colors</a>
                </div>
                <div class="ds-ts-panels">

                <!-- GENERAL TAB (first) -->
                <div class="ds-ts-panel" id="ds-ts-panel-general">
                    <?php
                    $repeat = array( 'no-repeat' => 'No Repeat', 'repeat' => 'Tile', 'repeat-x' => 'Tile Horizontally', 'repeat-y' => 'Tile Vertically' );
                    $pos    = array( 'left top' => 'Left Top', 'left center' => 'Left Center', 'left bottom' => 'Left Bottom', 'center top' => 'Center Top', 'center center' => 'Center Center', 'center bottom' => 'Center Bottom', 'right top' => 'Right Top', 'right center' => 'Right Center', 'right bottom' => 'Right Bottom' );
                    $size   = array( 'auto' => 'Auto', 'cover' => 'Cover', 'contain' => 'Contain' );
                    $attach = array( 'scroll' => 'Scroll', 'fixed' => 'Fixed' );
                    $blend  = array( 'normal' => 'Normal', 'multiply' => 'Multiply', 'screen' => 'Screen', 'overlay' => 'Overlay', 'darken' => 'Darken', 'lighten' => 'Lighten', 'color-dodge' => 'Color Dodge', 'color-burn' => 'Color Burn', 'hard-light' => 'Hard Light', 'soft-light' => 'Soft Light', 'difference' => 'Difference', 'exclusion' => 'Exclusion', 'hue' => 'Hue', 'saturation' => 'Saturation', 'color' => 'Color', 'luminosity' => 'Luminosity' );
                    $bg_img = $mod( 'fl-body-bg-image' );

                    $this->acc( 'Base Page Background', true );
                        $this->color_field( 'Background Color', 'general[bg_color]', $mod( 'fl-body-bg-color' ) );
                        ?>
                        <div class="ds-ts-field ds-ts-media"><label>Background Image</label><div class="ds-ts-input">
                            <input type="hidden" class="ds-ts-media-url" name="general[bg_image]" value="<?php echo esc_attr( $bg_img ); ?>">
                            <img class="ds-ts-media-preview" src="<?php echo esc_url( $bg_img ); ?>" alt="" style="max-width:120px;height:auto;border:1px solid #dcdcde;border-radius:4px;display:<?php echo $bg_img ? 'inline-block' : 'none'; ?>">
                            <button type="button" class="button ds-ts-media-select">Select Image</button>
                            <button type="button" class="button ds-ts-media-remove" style="display:<?php echo $bg_img ? 'inline-block' : 'none'; ?>">Remove</button>
                        </div></div>
                        <?php
                        $this->field_open( 'Repeat' ); echo $this->sel( 'general[bg_repeat]', $mod( 'fl-body-bg-repeat', 'no-repeat' ), $repeat ); $this->field_close();
                        $this->field_open( 'Position' ); echo $this->sel( 'general[bg_position]', $mod( 'fl-body-bg-position', 'center top' ), $pos ); $this->field_close();
                        $this->field_open( 'Size' ); echo $this->sel( 'general[bg_size]', $mod( 'fl-body-bg-size', 'auto' ), $size ); $this->field_close();
                        $this->field_open( 'Attachment' ); echo $this->sel( 'general[bg_attachment]', $mod( 'fl-body-bg-attachment', 'scroll' ), $attach ); $this->field_close();
                        $this->field_open( 'Blend Mode' ); echo $this->sel( 'general[bg_blend]', $mod( 'fl-body-bg-blend', 'normal' ), $blend ); $this->field_close();
                        echo '<p class="ds-ts-help">Paints the <strong>Base Container</strong> row of the base themer template (the page band behind the content on every single page). Add a Background Image as a pattern and a Blend Mode to mix it with the Background Color.</p>';
                    $this->acc_end();

                    $cbg_img = $mod( 'fl-content-bg-image' );
                    $this->acc( 'Base Page Content Background', false );
                        $this->color_field( 'Background Color', 'general[content_bg_color]', $mod( 'fl-content-bg-color' ) );
                        ?>
                        <div class="ds-ts-field ds-ts-media"><label>Background Image</label><div class="ds-ts-input">
                            <input type="hidden" class="ds-ts-media-url" name="general[content_bg_image]" value="<?php echo esc_attr( $cbg_img ); ?>">
                            <img class="ds-ts-media-preview" src="<?php echo esc_url( $cbg_img ); ?>" alt="" style="max-width:120px;height:auto;border:1px solid #dcdcde;border-radius:4px;display:<?php echo $cbg_img ? 'inline-block' : 'none'; ?>">
                            <button type="button" class="button ds-ts-media-select">Select Image</button>
                            <button type="button" class="button ds-ts-media-remove" style="display:<?php echo $cbg_img ? 'inline-block' : 'none'; ?>">Remove</button>
                        </div></div>
                        <?php
                        $this->field_open( 'Repeat' ); echo $this->sel( 'general[content_bg_repeat]', $mod( 'fl-content-bg-repeat', 'no-repeat' ), $repeat ); $this->field_close();
                        $this->field_open( 'Position' ); echo $this->sel( 'general[content_bg_position]', $mod( 'fl-content-bg-position', 'center top' ), $pos ); $this->field_close();
                        $this->field_open( 'Size' ); echo $this->sel( 'general[content_bg_size]', $mod( 'fl-content-bg-size', 'auto' ), $size ); $this->field_close();
                        $this->field_open( 'Attachment' ); echo $this->sel( 'general[content_bg_attachment]', $mod( 'fl-content-bg-attachment', 'scroll' ), $attach ); $this->field_close();
                        $this->field_open( 'Blend Mode' ); echo $this->sel( 'general[content_bg_blend]', $mod( 'fl-content-bg-blend', 'normal' ), $blend ); $this->field_close();
                        echo '<p class="ds-ts-help">Paints the <strong>content column</strong> inside the Base Container row (the inner box that holds the body on every single page). Use a Background Image as a pattern and Blend Mode to mix it with the Background Color.</p>';
                    $this->acc_end();

                    $bnbg_img = $mod( 'ds-banner-nobg-image' );
                    $this->acc( 'Page Banner Background (No Image)', false );
                        $this->color_field( 'Background Color', 'general[banner_nobg_color]', $mod( 'ds-banner-nobg-color', 'var(--fl-global-light-background)' ) );
                        ?>
                        <div class="ds-ts-field ds-ts-media"><label>Pattern / Image</label><div class="ds-ts-input">
                            <input type="hidden" class="ds-ts-media-url" name="general[banner_nobg_image]" value="<?php echo esc_attr( $bnbg_img ); ?>">
                            <img class="ds-ts-media-preview" src="<?php echo esc_url( $bnbg_img ); ?>" alt="" style="max-width:120px;height:auto;border:1px solid #dcdcde;border-radius:4px;display:<?php echo $bnbg_img ? 'inline-block' : 'none'; ?>">
                            <button type="button" class="button ds-ts-media-select">Select Image</button>
                            <button type="button" class="button ds-ts-media-remove" style="display:<?php echo $bnbg_img ? 'inline-block' : 'none'; ?>">Remove</button>
                        </div></div>
                        <?php
                        $this->field_open( 'Repeat' ); echo $this->sel( 'general[banner_nobg_repeat]', $mod( 'ds-banner-nobg-repeat', 'repeat' ), $repeat ); $this->field_close();
                        $this->field_open( 'Size' ); echo $this->sel( 'general[banner_nobg_size]', $mod( 'ds-banner-nobg-size', 'auto' ), $size ); $this->field_close();
                        $this->field_open( 'Blend Mode' ); echo $this->sel( 'general[banner_nobg_blend]', $mod( 'ds-banner-nobg-blend', 'normal' ), $blend ); $this->field_close();
                        echo '<p class="ds-ts-help">Default background for the <strong>Page Banner</strong> (Leagueapps Hero Banner) on pages with <strong>no banner photo or video</strong>. A small tileable pattern suits Repeat = Tile. The hero module can still override this per page.</p>';
                    $this->acc_end();

                    $this->acc( 'Corner Radius', false );
                    	$this->field_open( 'Corner Radius' );
                    	echo '<input type="number" min="0" max="60" name="general[corner_radius]" value="' . esc_attr( $mod( 'ds-corner-radius', '8' ) ) . '" class="small-text" style="width:90px"> <span style="opacity:.6">px</span>';
                    	$this->field_close();
                    	echo '<p class="ds-ts-help">One global corner radius for the LeagueApps card surfaces (cards, image tiles). Modules with their Corner Radius left blank inherit this; set a value on a module to override it there.</p>';
                    $this->acc_end();

                    $card_id  = (int) get_option( 'ds_social_card_id', 0 );
                    $card_url = (string) get_option( 'ds_social_card_url', '' );
                    if ( $card_id && '' === $card_url ) { $card_url = (string) wp_get_attachment_url( $card_id ); }
                    $this->acc( 'Social Sharing', false );
                        echo '<p class="ds-ts-help">Default image used when the site is shared on social media (Open Graph / Twitter card) for any page without its own image. Also available in Beaver Builder as the dynamic field <code>Site &rsaquo; Social Sharing Card</code>. Recommended size: 1200&times;630 (or larger at the same ratio).</p>';
                        ?>
                        <div class="ds-ts-field ds-ts-media"><label>Social Card</label><div class="ds-ts-input">
                            <input type="hidden" class="ds-ts-media-id" name="social_card[id]" value="<?php echo esc_attr( $card_id ); ?>">
                            <input type="hidden" class="ds-ts-media-url" name="social_card[url]" value="<?php echo esc_attr( $card_url ); ?>">
                            <img class="ds-ts-media-preview" src="<?php echo esc_url( $card_url ); ?>" alt="" style="max-width:240px;height:auto;border:1px solid #dcdcde;border-radius:4px;display:<?php echo $card_url ? 'inline-block' : 'none'; ?>">
                            <button type="button" class="button ds-ts-media-select">Select Image</button>
                            <button type="button" class="button ds-ts-media-remove" style="display:<?php echo $card_url ? 'inline-block' : 'none'; ?>">Remove</button>
                        </div></div>
                        <?php
                    $this->acc_end();

                    $fav_id  = (int) get_option( 'site_icon', 0 );
                    $fav_url = $fav_id ? (string) wp_get_attachment_image_url( $fav_id, 'full' ) : '';
                    $this->acc( 'Favicon', false );
                        echo '<p class="ds-ts-help">The site icon / favicon shown in browser tabs and bookmarks. Synced with WordPress <strong>Settings &rsaquo; General &rsaquo; Site Icon</strong> (the <code>site_icon</code> option). Should be square, at least 512&times;512.</p>';
                        ?>
                        <div class="ds-ts-field ds-ts-media"><label>Site Icon</label><div class="ds-ts-input">
                            <input type="hidden" class="ds-ts-media-id" name="favicon[id]" value="<?php echo esc_attr( $fav_id ); ?>">
                            <input type="hidden" class="ds-ts-media-url" name="favicon[url]" value="<?php echo esc_attr( $fav_url ); ?>">
                            <img class="ds-ts-media-preview" src="<?php echo esc_url( $fav_url ); ?>" alt="" style="max-width:64px;height:auto;border:1px solid #dcdcde;border-radius:8px;display:<?php echo $fav_url ? 'inline-block' : 'none'; ?>">
                            <button type="button" class="button ds-ts-media-select">Select Image</button>
                            <button type="button" class="button ds-ts-media-remove" style="display:<?php echo $fav_url ? 'inline-block' : 'none'; ?>">Remove</button>
                        </div></div>
                        <?php
                    $this->acc_end();

                    $this->acc( 'Custom CSS', false );
                        echo '<p class="ds-ts-help">Output site-wide in the <code>&lt;head&gt;</code>. No <code>&lt;style&gt;</code> tags needed.</p>';
                        echo '<textarea class="ds-ts-code" name="general[css_code]" spellcheck="false" placeholder="/* Custom CSS */">' . esc_textarea( $mod( 'fl-css-code' ) ) . '</textarea>';
                    $this->acc_end();

                    $this->acc( 'Custom JavaScript', false );
                        echo '<p class="ds-ts-help">Output site-wide before <code>&lt;/body&gt;</code>. No <code>&lt;script&gt;</code> tags needed.</p>';
                        echo '<textarea class="ds-ts-code" name="general[js_code]" spellcheck="false" placeholder="// Custom JavaScript">' . esc_textarea( $mod( 'fl-js-code' ) ) . '</textarea>';
                    $this->acc_end();
                    ?>
                </div>

                <!-- ELEMENTS TAB -->
                <div class="ds-ts-panel" id="ds-ts-panel-elements" style="display:none">
                    <?php
                    // Text
                    $this->acc( 'Text', true );
                        $this->color_field( 'Color', 'color[text_color]', $col( 'text_color' ) );
                        $this->render_typography( 'text', $tp( 'text_typography' ) );
                    $this->acc_end();

                    // Heading with All / H1-H6 sub-tabs
                    $this->acc( 'Heading', false );
                        echo '<div class="ds-ts-subtabs-wrap">';
                        echo '<div class="ds-ts-subtabs">';
                        $htabs = array( 'all' => 'All', 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' );
                        foreach ( $htabs as $k => $lbl ) {
                            echo '<a href="#" class="ds-ts-subtab' . ( 'all' === $k ? ' is-active' : '' ) . '" data-sub="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</a>';
                        }
                        echo '</div>';
                        foreach ( $htabs as $k => $lbl ) {
                            $grp = ( 'all' === $k ) ? 'h' : $k;
                            $ckey = ( 'all' === $k ) ? 'h_color' : $k . '_color';
                            echo '<div class="ds-ts-subpanel" id="ds-ts-sub-' . esc_attr( $k ) . '"' . ( 'all' === $k ? '' : ' style="display:none"' ) . '>';
                            $this->color_field( 'Color', "color[$ckey]", $col( $ckey ) );
                            $this->render_typography( $grp, $tp( $grp . '_typography' ) );
                            echo '</div>';
                        }
                        echo '</div>';
                    $this->acc_end();

                    // Link
                    $this->acc( 'Link', false );
                        $this->color_field( 'Color', 'color[link_color]', $col( 'link_color' ) );
                        $this->color_field( 'Hover Color', 'color[link_hover_color]', $col( 'link_hover_color' ) );
                        $this->render_typography( 'link', $tp( 'link_typography' ) );
                    $this->acc_end();

                    // Button
                    $this->acc( 'Button', false );
                        $this->render_button_style(
                            get_option( self::BUTTON_STYLE_OPTION, '' ),
                            $this->swatch_color( $col( 'button_background' ) ),
                            $this->swatch_color( $col( 'button_color' ) )
                        );
                        $this->color_field( 'Color', 'color[button_color]', $col( 'button_color' ) );
                        $this->color_field( 'Hover Color', 'color[button_hover_color]', $col( 'button_hover_color' ) );
                        $this->color_field( 'Background', 'color[button_background]', $col( 'button_background' ) );
                        $this->color_field( 'Hover Background', 'color[button_hover_background]', $col( 'button_hover_background' ) );
                        $this->render_typography( 'button', $tp( 'button_typography' ) );
                        $this->render_border( 'button', isset( $s->button_border ) ? $s->button_border : array() );
                        $this->color_field( 'Border Hover Color', 'color[button_border_hover_color]', $col( 'button_border_hover_color' ) );
                    $this->acc_end();
                    ?>
                </div>

                <!-- COLORS TAB -->
                <div class="ds-ts-panel" id="ds-ts-panel-colors" style="display:none">
                    <?php $this->acc( 'Global Colors', true ); ?>
                        <p class="ds-ts-help">Named brand colors. Each becomes a <code>--&lt;prefix&gt;-*</code> variable and a swatch in every Beaver Builder color picker.</p>
                        <div id="ds-ts-colors">
                            <?php foreach ( $colors as $c ) : $c = (array) $c; ?>
                            <div class="ds-ts-color-row">
                                <input type="hidden" name="color_uid[]" value="<?php echo esc_attr( $c['uid'] ?? '' ); ?>">
                                <input type="text" class="regular-text" name="color_label[]" placeholder="Name" value="<?php echo esc_attr( $c['label'] ?? '' ); ?>">
                                <?php echo $this->bb_color( 'color_color[]', $c['color'] ?? '' ); ?>
                                <button type="button" class="button ds-ts-color-remove" title="Remove">&times;</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" id="ds-ts-add-color">+ Add Global Color</button>
                    <?php $this->acc_end(); ?>
                    <?php $this->acc( 'Prefix', false ); ?>
                        <div class="ds-ts-field"><label>Variable Prefix</label><div class="ds-ts-input"><input type="text" class="regular-text" name="prefix" value="<?php echo esc_attr( $prefix ); ?>" placeholder="fl-global"></div></div>
                    <?php $this->acc_end(); ?>
                </div>

                </div><!-- .ds-ts-panels -->
                </div><!-- .ds-ts-layout -->

                <div class="dst-footer"><?php submit_button( 'Save Global Styles', 'primary', 'submit', false ); ?></div>
            </form>
        </div>
        <?php
    }

    /* ----------------------------------------------------------------- Save */

    public function save() {
        if ( ! current_user_can( 'edit_posts' ) || ! DS_Toolkit::is_leagueapps_user() ) {
            wp_die( 'Not allowed.' );
        }
        check_admin_referer( self::SAVE_ACTION );
        if ( ! $this->available() ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            exit;
        }

        $old = FLBuilderGlobalStyles::get_settings( false );
        $new = array();

        // Named colors — only rebuilt when the repeater was actually submitted, so a
        // stray save (e.g. saving just the General tab) can never wipe the global colors.
        if ( isset( $_POST['color_label'] ) ) {
            $labels = (array) wp_unslash( $_POST['color_label'] );
            $cols   = isset( $_POST['color_color'] ) ? (array) wp_unslash( $_POST['color_color'] ) : array();
            $uids   = isset( $_POST['color_uid'] ) ? (array) wp_unslash( $_POST['color_uid'] ) : array();
            $colors = array();
            foreach ( $labels as $i => $label ) {
                $label = sanitize_text_field( $label );
                $color = $this->sanitize_color( isset( $cols[ $i ] ) ? $cols[ $i ] : '' );
                if ( '' !== $label && '' !== $color ) {
                    $entry = array( 'label' => $label, 'color' => $color );
                    // Preserve the existing BB uid. Global-color CONNECTIONS (e.g. the
                    // header's "Primary") reference colors BY uid; BB assigns a fresh
                    // random uid to any color missing one, so rebuilding without the uid
                    // orphaned every connection and it silently reverted to grey.
                    $uid = isset( $uids[ $i ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $uids[ $i ] ) : '';
                    if ( '' !== $uid ) { $entry['uid'] = $uid; }
                    $colors[] = $entry;
                }
            }
            $new['colors'] = $colors;
        }
        if ( isset( $_POST['prefix'] ) ) {
            $new['prefix'] = sanitize_text_field( wp_unslash( $_POST['prefix'] ) );
        }

        // Element colors.
        $posted_colors = isset( $_POST['color'] ) && is_array( $_POST['color'] ) ? wp_unslash( $_POST['color'] ) : array();
        foreach ( $this->color_keys() as $k ) {
            if ( isset( $posted_colors[ $k ] ) ) {
                $new[ $k ] = $this->sanitize_color( $posted_colors[ $k ] );
            }
        }

        // Typography groups.
        $posted_typo = isset( $_POST['typo'] ) && is_array( $_POST['typo'] ) ? wp_unslash( $_POST['typo'] ) : array();
        foreach ( $this->typo_groups() as $grp => $setting_key ) {
            $existing            = isset( $old->{$setting_key} ) ? $old->{$setting_key} : array();
            $new[ $setting_key ] = $this->parse_typography( $existing, isset( $posted_typo[ $grp ] ) ? $posted_typo[ $grp ] : array() );
        }

        // Button border.
        $posted_border       = isset( $_POST['border']['button'] ) && is_array( $_POST['border']['button'] ) ? wp_unslash( $_POST['border']['button'] ) : array();
        $new['button_border'] = $this->parse_border( isset( $old->button_border ) ? $old->button_border : array(), $posted_border );

        FLBuilderGlobalStyles::save_settings( (object) $new );

        // General tab -> Beaver Builder Theme customizer theme_mods (page background + custom code).
        $g = isset( $_POST['general'] ) && is_array( $_POST['general'] ) ? wp_unslash( $_POST['general'] ) : array();
        if ( $g ) {
            $repeat_ok = array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' );
            $size_ok   = array( 'auto', 'cover', 'contain' );
            $attach_ok = array( 'scroll', 'fixed' );
            $pos_ok    = array( 'left top', 'left center', 'left bottom', 'center top', 'center center', 'center bottom', 'right top', 'right center', 'right bottom' );
            $blend_ok  = array( 'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity' );
            // Base Page Background (the page band) -> the Base Container row, fl-body-bg-* mods.
            set_theme_mod( 'fl-body-bg-color', $this->sanitize_color( $g['bg_color'] ?? '' ) );
            set_theme_mod( 'fl-body-bg-image', esc_url_raw( $g['bg_image'] ?? '' ) );
            set_theme_mod( 'fl-body-bg-repeat', in_array( $g['bg_repeat'] ?? '', $repeat_ok, true ) ? $g['bg_repeat'] : 'no-repeat' );
            set_theme_mod( 'fl-body-bg-position', in_array( $g['bg_position'] ?? '', $pos_ok, true ) ? $g['bg_position'] : 'center top' );
            set_theme_mod( 'fl-body-bg-size', in_array( $g['bg_size'] ?? '', $size_ok, true ) ? $g['bg_size'] : 'auto' );
            set_theme_mod( 'fl-body-bg-attachment', in_array( $g['bg_attachment'] ?? '', $attach_ok, true ) ? $g['bg_attachment'] : 'scroll' );
            set_theme_mod( 'fl-body-bg-blend', in_array( $g['bg_blend'] ?? '', $blend_ok, true ) ? $g['bg_blend'] : 'normal' );
            // Base Page Content Background (the inner content box) -> the content COLUMN, fl-content-bg-* mods.
            set_theme_mod( 'fl-content-bg-color', $this->sanitize_color( $g['content_bg_color'] ?? '' ) );
            set_theme_mod( 'fl-content-bg-image', esc_url_raw( $g['content_bg_image'] ?? '' ) );
            set_theme_mod( 'fl-content-bg-repeat', in_array( $g['content_bg_repeat'] ?? '', $repeat_ok, true ) ? $g['content_bg_repeat'] : 'no-repeat' );
            set_theme_mod( 'fl-content-bg-position', in_array( $g['content_bg_position'] ?? '', $pos_ok, true ) ? $g['content_bg_position'] : 'center top' );
            set_theme_mod( 'fl-content-bg-size', in_array( $g['content_bg_size'] ?? '', $size_ok, true ) ? $g['content_bg_size'] : 'auto' );
            set_theme_mod( 'fl-content-bg-attachment', in_array( $g['content_bg_attachment'] ?? '', $attach_ok, true ) ? $g['content_bg_attachment'] : 'scroll' );
            set_theme_mod( 'fl-content-bg-blend', in_array( $g['content_bg_blend'] ?? '', $blend_ok, true ) ? $g['content_bg_blend'] : 'normal' );
            // Page Banner (no-image) default background -> ds-banner-nobg-* mods.
            set_theme_mod( 'ds-banner-nobg-color', $this->sanitize_color( $g['banner_nobg_color'] ?? '' ) );
            set_theme_mod( 'ds-banner-nobg-image', esc_url_raw( $g['banner_nobg_image'] ?? '' ) );
            set_theme_mod( 'ds-banner-nobg-repeat', in_array( $g['banner_nobg_repeat'] ?? '', $repeat_ok, true ) ? $g['banner_nobg_repeat'] : 'repeat' );
            set_theme_mod( 'ds-banner-nobg-size', in_array( $g['banner_nobg_size'] ?? '', $size_ok, true ) ? $g['banner_nobg_size'] : 'auto' );
            set_theme_mod( 'ds-banner-nobg-blend', in_array( $g['banner_nobg_blend'] ?? '', $blend_ok, true ) ? $g['banner_nobg_blend'] : 'normal' );
            set_theme_mod( 'ds-corner-radius', isset( $g['corner_radius'] ) && '' !== $g['corner_radius'] ? max( 0, min( 60, (int) $g['corner_radius'] ) ) : 8 );
            // Custom code: LeagueApps-only surface; stored raw like the BB theme's Code section.
            set_theme_mod( 'fl-css-code', isset( $g['css_code'] ) ? trim( $g['css_code'] ) : '' );
            set_theme_mod( 'fl-js-code', isset( $g['js_code'] ) ? trim( $g['js_code'] ) : '' );
            if ( class_exists( 'FLCustomizer' ) && method_exists( 'FLCustomizer', 'refresh_css' ) ) {
                FLCustomizer::refresh_css();
            }
        }

        // Favicon -> WordPress Site Icon (Settings > General). Stored as the
        // attachment ID in the core `site_icon` option so it syncs everywhere.
        if ( isset( $_POST['favicon'] ) && is_array( $_POST['favicon'] ) ) {
            $fav_id = isset( $_POST['favicon']['id'] ) ? (int) $_POST['favicon']['id'] : 0;
            if ( $fav_id > 0 ) {
                update_option( 'site_icon', $fav_id );
            } else {
                delete_option( 'site_icon' );
            }
        }

        // Social Sharing Card -> options + Yoast default OG image (handled by DS_Social_Card).
        if ( isset( $_POST['social_card'] ) && is_array( $_POST['social_card'] ) && class_exists( 'DS_Social_Card' ) ) {
            $sc       = wp_unslash( $_POST['social_card'] );
            $sc_id    = isset( $sc['id'] ) ? (int) $sc['id'] : 0;
            $sc_url   = isset( $sc['url'] ) ? esc_url_raw( $sc['url'] ) : '';
            DS_Social_Card::set_card( $sc_id, $sc_url );
        }

        // Button shape -> standalone option, emitted on the front end by DS_Global_CSS.
        if ( isset( $_POST['button_style'] ) ) {
            $bs = sanitize_key( wp_unslash( $_POST['button_style'] ) );
            if ( ! array_key_exists( $bs, self::button_styles() ) ) { $bs = ''; }
            if ( '' === $bs ) {
                delete_option( self::BUTTON_STYLE_OPTION );
            } else {
                update_option( self::BUTTON_STYLE_OPTION, $bs );
            }
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
        if ( preg_match( '/^#?([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value, $m ) ) { return '#' . strtolower( $m[1] ); }
        if ( preg_match( '/^rgba?\(\s*[\d.,\s%]+\)$/', $value ) ) { return $value; }
        // Synced global-color reference, e.g. var(--fl-global-primary).
        if ( preg_match( '/^var\(\s*--[A-Za-z0-9_-]+\s*\)$/', $value ) ) { return $value; }
        return (string) sanitize_hex_color( $value );
    }
}
