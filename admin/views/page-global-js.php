<?php
/**
 * Global JS tab: the on/off switch, then the reference and the read-only source behind a fold.
 * The JS is managed directly in includes/defaults/global-js.js (plugin file).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- Info card + toggle -->
<div class="dst-card dst-card--lead">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-editor-code"></span></div>
        <div class="dst-card-info">
            <strong id="dst-t-global_js_enabled">Global JavaScript — Plugin Managed</strong>
            <span>Site utilities injected before <code>&lt;/body&gt;</code> on every page. Managed in <code>includes/defaults/global-js.js</code> — updates automatically with the plugin. Toggle off to disable output entirely.</span>
        </div>
        <div class="dst-toggle">
            <form method="post" action="options.php" class="dst-form-inline">
                <?php settings_fields( 'ds_toolkit_options' ); ?>
                <input type="hidden" name="ds_toolkit_settings[global_js_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-global_js_enabled" id="global_js_enabled" name="ds_toolkit_settings[global_js_enabled]" value="1" <?php checked( $global_js_enabled ); ?> onchange="this.form.submit()">
                <label for="global_js_enabled" aria-hidden="true"></label>
            </form>
        </div>
    </div>
</div>

<details class="dst-group dst-ref">
    <summary class="dst-group-head">
        <span class="dst-group-title"><span class="dashicons dashicons-book" aria-hidden="true"></span> Reference: what the script does</span>
        <span class="dst-group-count">Four helpers and the full source</span>
    </summary>
    <div class="dst-group-body">
        <p class="dst-ref-pointer">Guides for the modules and the builder are on <a href="<?php echo esc_url( DS_Toolkit_Admin::HQ_DOCS_URL ); ?>" target="_blank" rel="noopener">Design Shop HQ</a>. This list is the reference for what the plugin itself ships.</p>

<!-- Sticky Header -->
<p class="dst-section-title dst-mt-20">Sticky Header</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-appearance"></span></div>
        <div class="dst-card-info">
            <strong>.sticky-row</strong>
            <span>Add <code>.sticky-row</code> to any BB row. The script watches scroll position and toggles <code>.scrolled</code> on the element — Global CSS then applies the sticky styles (background color, shrunken logo). No JS configuration needed.</span>
        </div>
    </div>
</div>

<!-- Clickable Columns -->
<p class="dst-section-title dst-mt-20">Clickable Columns</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <div class="dst-card-info">
            <strong>.column-link</strong>
            <span>Add <code>.column-link</code> to any BB column. The script finds the first anchor inside the column and makes the entire column clickable — cursor changes to pointer, click navigates to that URL. Disabled automatically inside the BB editor.</span>
        </div>
    </div>
</div>

<!-- Equal Heights -->
<p class="dst-section-title dst-mt-20">Equal Heights</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-align-center"></span></div>
        <div class="dst-card-info">
            <strong>same_height_{target}</strong>
            <span><strong>Local:</strong> Add <code>same_height_card</code> to a BB column wrapper. All <code>.card</code> elements inside that wrapper are equalised to the tallest one.</span>
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-align-center"></span></div>
        <div class="dst-card-info">
            <strong>same_height_{target}_{group}</strong>
            <span><strong>Global:</strong> Add <code>same_height_card_a</code> to multiple BB column wrappers. All <code>.card</code> elements across every wrapper with the same group are equalised together.</span>
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-align-center"></span></div>
        <div class="dst-card-info">
            <strong>same_height-group-{group}</strong>
            <span><strong>Direct:</strong> Add <code>same_height-group-a</code> directly to the elements you want equalised — no wrapper needed.</span>
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-info-outline"></span></div>
        <div class="dst-card-info">
            <strong>Manual trigger</strong>
            <span>Call <code>window.equalizeHeightsRefresh()</code> from any custom JS to re-run equalisation after dynamic content loads.</span>
        </div>
    </div>
</div>

<!-- Button Normaliser -->
<p class="dst-section-title dst-mt-20">Button Normaliser</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-button"></span></div>
        <div class="dst-card-info">
            <strong>.uabb-button → .fl-button</strong>
            <span>UABB buttons automatically receive the <code>.fl-button</code> class so they inherit all Beaver Builder button styles and Global CSS button overrides without extra configuration.</span>
        </div>
    </div>
</div>

<!-- Full JS source -->
<p class="dst-section-title dst-mt-28">Full JS Source <span class="dst-title-note">— read only · edit in <code>includes/defaults/global-js.js</code></span></p>
<div class="dst-code-editor-wrap">
    <textarea readonly rows="40" class="dst-readonly-code"><?php echo esc_textarea( file_get_contents( DS_TOOLKIT_PATH . 'includes/defaults/global-js.js' ) ); ?></textarea>
</div>

    </div>
</details>

