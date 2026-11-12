<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme Setting: the live preview (the CSS answer for unsaved values and the bridge loaded in the preview frame).
 *
 * One part of DS_Theme_Setting, which was a single 1,620-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the feature (same visibility, same
 * $this). The hooks, the front-end CSS output and the button shapes stay in class-ds-theme-setting.php.
 */
trait DS_Theme_Setting_Preview {

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
}
