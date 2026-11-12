<?php
/**
 * Global CSS tab — CSS variable overrides + utility class reference.
 * Base CSS is managed in includes/defaults/global-css.css (plugin file).
 * Overrides are stored in wp_options and survive plugin updates.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- Info card -->
<div class="dst-card dst-card--lead">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-editor-code"></span></div>
        <div class="dst-card-info">
            <strong>Global CSS — Plugin Managed</strong>
            <span>Base styles live in <code>includes/defaults/global-css.css</code> and update with the plugin. Use <strong id="dst-t-global_css_enabled">CSS Overrides</strong> below to change variable values per-site — overrides are stored in the database and survive every plugin update.</span>
        </div>
        <div class="dst-toggle">
            <form method="post" action="options.php" class="dst-form-inline">
                <?php settings_fields( 'ds_toolkit_options' ); ?>
                <input type="hidden" name="ds_toolkit_settings[global_css_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-global_css_enabled" id="global_css_enabled" name="ds_toolkit_settings[global_css_enabled]" value="1" <?php checked( $global_css_enabled ); ?> onchange="this.form.submit()">
                <label for="global_css_enabled" aria-hidden="true"></label>
            </form>
        </div>
    </div>
</div>

<!-- CSS Variable Overrides -->
<p class="dst-section-title dst-mt-20">CSS Variable Overrides <span class="dst-title-note">— stored in database · survives plugin updates</span></p>

<form method="post" action="options.php">
    <?php settings_fields( 'ds_toolkit_options' ); ?>
    <input type="hidden" name="ds_toolkit_settings[global_css_enabled]" value="<?php echo $global_css_enabled ? '1' : '0'; ?>">

    <div class="dst-card dst-card--note">
        <p class="dst-note-lead">Override any <code>--dst-*</code> variable below. Only add what you want to change — defaults apply for everything else.</p>
        <pre class="dst-note-pre">:root {
  --dst-height-tall:   80vh;   /* was 65vh */
  --dst-border-radius: 16px;   /* was 10px */
}</pre>
        <p class="dst-note-foot"><strong>Available variables:</strong> <code>--dst-height-tall</code> · <code>--dst-height-tall-tablet</code> · <code>--dst-height-medium</code> · <code>--dst-height-medium-tablet</code> · <code>--dst-height-small</code> · <code>--dst-height-avatar</code> · <code>--dst-small-icon</code> · <code>--dst-sponsor-height</code> · <code>--dst-border-radius</code> · <code>--dst-zindex-medium</code> · <code>--dst-zindex-high</code> · <code>--dst-sticky-logo-width</code> · <code>--dst-sticky-logo-height</code> · <code>--dst-sticky-logo-margin</code> · <code>--dst-h2-home</code> · <code>--dst-h2-home-mobile</code> · <code>--dst-grid-gap</code> · <code>--dst-grid-gap-tablet</code> · <code>--dst-grid-item-padding</code></p>
    </div>

    <div class="dst-code-editor-wrap">
        <textarea id="global_css_overrides" name="ds_toolkit_settings[global_css_overrides]" rows="12" placeholder=":root {&#10;  --dst-height-tall: 80vh;&#10;}"><?php echo esc_textarea( $global_css_overrides ); ?></textarea>
    </div>

    <div class="dst-footer">
        <?php submit_button( 'Save Overrides', 'primary', 'submit', false ); ?>
        <span class="dst-footer-meta">
            <a href="https://github.com/Design-Shop-LeagueApps-Department/ds-toolkit" target="_blank" rel="noopener">GitHub</a>
            &nbsp;&middot;&nbsp; By LeagueApps Design Shop
        </span>
    </div>
</form>

<details class="dst-group dst-ref">
    <summary class="dst-group-head">
        <span class="dst-group-title"><span class="dashicons dashicons-book" aria-hidden="true"></span> Reference: utility classes</span>
        <span class="dst-group-count">Class list and the full stylesheet</span>
    </summary>
    <div class="dst-group-body">
        <p class="dst-ref-pointer">Guides for the modules and the builder are on <a href="<?php echo esc_url( DS_Toolkit_Admin::HQ_DOCS_URL ); ?>" target="_blank" rel="noopener">Design Shop HQ</a>. This list is the reference for what the plugin itself ships.</p>

<!-- Image Heights -->
<p class="dst-section-title dst-mt-28">Image Heights</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>.height-tall</strong><span>Images/carousels to <code>--dst-height-tall</code> (default <code>65vh</code>) — full-bleed hero banners.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>.height-medium</strong><span>Images/carousels to <code>--dst-height-medium</code> (default <code>45vh</code>) — mid-size feature sections.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>.height-small</strong><span>Images/carousels to <code>--dst-height-small</code> (default <code>35vh</code>) — compact image rows.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>.height-avatar</strong><span>Images to <code>--dst-height-avatar</code> (default <code>350px</code>), top-center crop — headshot/profile photos.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>.small-icon</strong><span>Limits images to <code>--dst-small-icon</code> (default <code>55px</code>) — small logos or inline icons.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>.clip-photo</strong><span>Applies a hexagonal clip-path to images — geometric photo cutout effect.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>.add-white-bg</strong><span>Adds white background, border-radius, and padding to images — clean logo display on dark rows.</span></div>
    </div>
</div>

<!-- Text Colors -->
<p class="dst-section-title dst-mt-20">Text Colors</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-editor-textcolor"></span></div>
        <div class="dst-card-info"><strong>.color-white</strong><span>Forces text to <code>--fl-global-white</code>.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-editor-textcolor"></span></div>
        <div class="dst-card-info"><strong>.color-primary</strong><span>Forces text to <code>--fl-global-primary</code>.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-editor-textcolor"></span></div>
        <div class="dst-card-info"><strong>.color-accent</strong><span>Forces text to <code>--fl-global-accent</code>.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-editor-textcolor"></span></div>
        <div class="dst-card-info"><strong>.color-dark</strong><span>Forces text to <code>--fl-global-dark-background</code>.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-editor-textcolor"></span></div>
        <div class="dst-card-info"><strong>.quote</strong><span>Bold (<code>900</code> weight) primary-color text — pull quotes or highlighted statements.</span></div>
    </div>
</div>

<!-- Outline Text -->
<p class="dst-section-title dst-mt-20">Outline Text</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-text"></span></div>
        <div class="dst-card-info"><strong>.outline-white-text</strong><span>Transparent fill with <code>2px</code> white stroke — outlined headline on dark backgrounds.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-text"></span></div>
        <div class="dst-card-info"><strong>.outline-primary-text</strong><span>Transparent fill with <code>2px</code> primary color stroke.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-text"></span></div>
        <div class="dst-card-info"><strong>.outline-accent-text</strong><span>Transparent fill with <code>2px</code> accent color stroke.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-text"></span></div>
        <div class="dst-card-info"><strong>.h2-home</strong><span>Sets h2 to <code>--dst-h2-home</code> (default <code>76px</code>) desktop / <code>--dst-h2-home-mobile</code> (default <code>46px</code>) mobile.</span></div>
    </div>
</div>

<!-- Layout Utilities -->
<p class="dst-section-title dst-mt-20">Layout Utilities</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <div class="dst-card-info"><strong>.overflow-hidden</strong><span>Clips overflowing content on the row and its direct child — useful for image zoom hover effects.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <div class="dst-card-info"><strong>.border-radius</strong><span>Applies <code>--dst-border-radius</code> (default <code>10px</code>) to the element and all children.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <div class="dst-card-info"><strong>.zindex-medium</strong><span>Sets <code>position: relative; z-index: --dst-zindex-medium</code> (default <code>100</code>).</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <div class="dst-card-info"><strong>.zindex-high</strong><span>Sets <code>position: relative; z-index: --dst-zindex-high</code> (default <code>1000</code>).</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <div class="dst-card-info"><strong>.grid-list</strong><span>Converts a UABB Info List into a 3-column card grid (2 tablet, 1 mobile). Gap: <code>--dst-grid-gap</code>. Item padding: <code>--dst-grid-item-padding</code>.</span></div>
    </div>
</div>

<!-- Blend Modes -->
<p class="dst-section-title dst-mt-20">Blend Modes</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-appearance"></span></div>
        <div class="dst-card-info"><strong>.text-mix-blend-difference</strong><span>Applies <code>mix-blend-mode: difference</code> — inverts colors against the background.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-appearance"></span></div>
        <div class="dst-card-info"><strong>.mix-blend-multiply</strong><span>Applies <code>mix-blend-mode: multiply</code> on pseudo-elements — blends image overlays with background.</span></div>
    </div>
</div>

<!-- Menu -->
<p class="dst-section-title dst-mt-20">Menu</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
        <div class="dst-card-info"><strong>.menu-justify</strong><span>Flexes the primary menu to the right with full-width stretch.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
        <div class="dst-card-info"><strong>.justify-menu</strong><span>Distributes menu items evenly across full width with equal spacing and borders.</span></div>
    </div>
</div>

<!-- Sticky Header -->
<p class="dst-section-title dst-mt-20">Sticky Header</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-appearance"></span></div>
        <div class="dst-card-info"><strong>.sticky-row</strong><span>Makes a BB row stick to the top on scroll. Logo shrinks to <code>--dst-sticky-logo-width</code> × <code>--dst-sticky-logo-height</code> (defaults <code>80×60px</code>). Requires DS Toolkit Global JS.</span></div>
    </div>
</div>

<!-- Carousels -->
<p class="dst-section-title dst-mt-20">Carousels</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-images-alt2"></span></div>
        <div class="dst-card-info"><strong>.sponsor-carousel</strong><span>Styles UABB Image Carousel as white-background sponsor logos — height controlled by <code>--dst-sponsor-height</code> (default <code>90px</code>).</span></div>
    </div>
</div>

<!-- CTA / Infobox -->
<p class="dst-section-title dst-mt-20">CTA / Infobox</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-megaphone"></span></div>
        <div class="dst-card-info"><strong>.cta-tile</strong><span>Rounds UABB Info Box 2 corners and adds a white gradient fade at the bottom.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-megaphone"></span></div>
        <div class="dst-card-info"><strong>.border-cta</strong><span>Adds a <code>1px</code> accent-color border around UABB Info Box 2.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-megaphone"></span></div>
        <div class="dst-card-info"><strong>.info-box-cta</strong><span>On hover, title and body text switch to white.</span></div>
    </div>
</div>

<!-- Animation -->
<p class="dst-section-title dst-mt-20">Animation</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-arrow-down"></span></div>
        <div class="dst-card-info"><strong>.v-line-container + .v-line</strong><span>Animated vertical scroll-down indicator. Place <code>div.v-line-container > div.v-line</code> inside a BB HTML module on a hero row.</span></div>
    </div>
</div>

<!-- Social -->
<p class="dst-section-title dst-mt-20">Social Icons</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-share"></span></div>
        <div class="dst-card-info"><strong>.top-social</strong><span>Flexes a UABB Info List into a horizontal right-aligned social icon row.</span></div>
    </div>
</div>

<!-- Full CSS source -->
<p class="dst-section-title dst-mt-28">Full CSS Source <span class="dst-title-note">— read only · edit in <code>includes/defaults/global-css.css</code></span></p>
<div class="dst-code-editor-wrap">
    <textarea readonly rows="40" class="dst-readonly-code"><?php echo esc_textarea( file_get_contents( DS_TOOLKIT_PATH . 'includes/defaults/global-css.css' ) ); ?></textarea>
</div>

    </div>
</details>

