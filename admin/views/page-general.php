<?php
/**
 * General tab content (blueprint generation 6+).
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 * Variables available: $footer_copyright_text, $copyright_shortcode_enabled
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<form method="post" action="options.php">
    <?php settings_fields( 'ds_toolkit_options' ); ?>

    <p class="dst-section-title">General Theme Settings</p>

    <!-- Footer copyright -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-editor-textcolor"></span></div>
            <div class="dst-card-info">
                <strong>[ds_copyright] Shortcode</strong>
                <span>Outputs the footer copyright line below anywhere you place <code>[ds_copyright]</code>. The token <code>{year}</code> is replaced with the current year automatically.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[copyright_shortcode_enabled]" value="0">
                <input type="checkbox" id="copyright_shortcode_enabled" name="ds_toolkit_settings[copyright_shortcode_enabled]" value="1" <?php checked( $copyright_shortcode_enabled ); ?>>
                <label for="copyright_shortcode_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-site"></span></div>
            <div class="dst-card-info">
                <strong>Footer Copyright Text</strong>
                <span>Use <code>{year}</code> for the current year. Basic inline tags (<code>a</code>, <code>strong</code>, <code>em</code>, <code>span</code>, <code>br</code>) are allowed.</span>
            </div>
            <div class="dst-field-inline">
                <input type="text" class="regular-text" name="ds_toolkit_settings[footer_copyright_text]" value="<?php echo esc_attr( $footer_copyright_text ); ?>" placeholder="&copy; {year} LeagueApps Design Shop">
            </div>
        </div>

        <div class="dst-card-row dst-shortcode-docs">
            <div class="dst-card-icon"><span class="dashicons dashicons-info-outline"></span></div>
            <div class="dst-card-info">
                <strong>How to use it</strong>
                <p>Place the shortcode in your footer:</p>
                <code>[ds_copyright]</code>
                <p>Preview: <?php echo wp_kses_post( str_replace( '{year}', date( 'Y' ), $footer_copyright_text ?: '&copy; {year} LeagueApps Design Shop' ) ); ?></p>
            </div>
        </div>
    </div>

    <div class="dst-footer">
        <?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
        <span class="dst-footer-meta">
            <a href="https://github.com/Design-Shop-LeagueApps-Department/ds-toolkit" target="_blank" rel="noopener">GitHub</a>
            &nbsp;&middot;&nbsp; By Alipio Gabriel
        </span>
    </div>

</form>
