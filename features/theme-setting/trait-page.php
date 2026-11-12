<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme Setting: the screen itself.
 *
 * One part of DS_Theme_Setting, which was a single 1,620-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the feature (same visibility, same
 * $this). The hooks, the front-end CSS output and the button shapes stay in class-ds-theme-setting.php.
 */
trait DS_Theme_Setting_Page {

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
        // Other features add their own sections (Home page: DS_Home_Templates). Each one
        // renders its markup on ds_theme_setting_extra_sections, after the built-in sections.
        $sections = (array) apply_filters( 'ds_theme_setting_sections', $sections );
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
                    <?php $code_ro = ! current_user_can( 'unfiltered_html' ) ? ' readonly' : ''; ?>
                    <div class="dsts-sec-head"><h2>Custom Code</h2><p>Site-wide code for this partner. CSS previews live; JavaScript runs only on the saved site.<?php if ( $code_ro ) { echo ' <strong>Read-only for your account: saving custom code needs the unfiltered HTML permission.</strong>'; } ?></p></div>
                    <?php $this->card_open( 'CSS', 'Output in the <code>&lt;head&gt;</code>. No <code>&lt;style&gt;</code> tags needed.' ); ?>
                        <textarea class="dsts-code" id="dsts-css-code" name="general[css_code]" spellcheck="false"<?php echo $code_ro; ?> placeholder="/* Custom CSS */"><?php echo esc_textarea( $mod( 'fl-css-code' ) ); ?></textarea>
                    <?php $this->card_close(); ?>
                    <?php $this->card_open( 'JavaScript', 'Output before <code>&lt;/body&gt;</code>. No <code>&lt;script&gt;</code> tags needed.' ); ?>
                        <textarea class="dsts-code" id="dsts-js-code" name="general[js_code]" spellcheck="false"<?php echo $code_ro; ?> placeholder="// Custom JavaScript"><?php echo esc_textarea( $mod( 'fl-js-code' ) ); ?></textarea>
                    <?php $this->card_close(); ?>
                </section>

                <?php do_action( 'ds_theme_setting_extra_sections' ); ?>

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
}
