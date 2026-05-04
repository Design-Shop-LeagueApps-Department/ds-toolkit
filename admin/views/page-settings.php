<?php
/**
 * Features tab content.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 * Variables available: $enabled, $logo_id, $logo_url, $default_url, $hide_fl_assistant,
 *                      $acf_css_vars_enabled, $acf_css_vars_mappings, $getsubmenu_enabled,
 *                      $current_year_enabled, $forminator_email_partner_enabled,
 *                      $forminator_email_partner_fallback, $child_pages_enabled,
 *                      $child_pages_template_id, $child_pages_columns,
 *                      $child_pages_columns_tablet, $child_pages_columns_mobile
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<form method="post" action="options.php">
    <?php settings_fields( 'ds_toolkit_options' ); ?>

    <p class="dst-section-title">Features</p>
    <div class="dst-card">

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-appearance"></span></div>
            <div class="dst-card-info">
                <strong>LeagueApps Custom Login</strong>
                <span>Custom logo, "Powered by LeagueApps Design Shop" branding, and support link on the WP login page.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[enable_login_branding]" value="0">
                <input type="checkbox" id="enable_login_branding" name="ds_toolkit_settings[enable_login_branding]" value="1" <?php checked( $enabled ); ?>>
                <label for="enable_login_branding"></label>
            </div>
        </div>

        <div class="dst-card-row" id="dst-logo-row" <?php echo $enabled ? '' : 'style="display:none"'; ?>>
            <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
            <div class="dst-logo-label">
                <strong>Login Logo</strong>
                <span>Replaces the default LeagueApps logo on the login page.</span>
            </div>
            <div class="dst-logo-picker">
                <div class="dst-logo-preview">
                    <img id="dst-logo-img" src="<?php echo esc_url( $logo_url ?: $default_url ); ?>" alt="Login logo">
                </div>
                <div class="dst-logo-actions">
                    <input type="hidden" id="dst-logo-id" name="ds_toolkit_settings[login_logo_id]" value="<?php echo esc_attr( $logo_id ); ?>">
                    <button type="button" class="button" id="dst-logo-select">Select Logo</button>
                    <button type="button" class="button" id="dst-logo-remove" <?php echo $logo_id ? '' : 'style="display:none"'; ?>>Use Default</button>
                </div>
            </div>
        </div>

    </div>

    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-cloud"></span></div>
            <div class="dst-card-info">
                <strong>Hide Beaver Builder Cloud Icon for Non-LeagueApps Users</strong>
                <span>Hides the FL Assistant cloud button in the Beaver Builder toolbar for all users except @leagueapps.com accounts.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[hide_fl_assistant]" value="0">
                <input type="checkbox" id="hide_fl_assistant" name="ds_toolkit_settings[hide_fl_assistant]" value="1" <?php checked( $hide_fl_assistant ); ?>>
                <label for="hide_fl_assistant"></label>
            </div>
        </div>
    </div>

    <!-- ACF CSS Variables -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-editor-code"></span></div>
            <div class="dst-card-info">
                <strong>ACF Theme Options → CSS Variables</strong>
                <span>Map ACF option fields to CSS custom properties output in <code>:root</code> on every page.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[acf_css_vars_enabled]" value="0">
                <input type="checkbox" id="acf_css_vars_enabled" name="ds_toolkit_settings[acf_css_vars_enabled]" value="1" <?php checked( $acf_css_vars_enabled ); ?>>
                <label for="acf_css_vars_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row dst-mapping-wrap" id="dst-acf-mappings-row" <?php echo $acf_css_vars_enabled ? '' : 'style="display:none"'; ?>>
            <div class="dst-mapping-container">
                <table class="dst-mapping-table" id="dst-mappings-table">
                    <thead>
                        <tr>
                            <th>ACF Field Name</th>
                            <th>CSS Variable</th>
                            <th>Fallback Value</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="dst-mappings-tbody">
                        <?php foreach ( $acf_css_vars_mappings as $i => $mapping ) : ?>
                        <tr class="dst-mapping-row">
                            <td><input type="text" class="regular-text" name="ds_toolkit_settings[acf_css_vars_mappings][<?php echo $i; ?>][acf_field]" value="<?php echo esc_attr( $mapping['acf_field'] ?? '' ); ?>" placeholder="acf_field_name"></td>
                            <td><input type="text" class="regular-text" name="ds_toolkit_settings[acf_css_vars_mappings][<?php echo $i; ?>][css_var]" value="<?php echo esc_attr( $mapping['css_var'] ?? '' ); ?>" placeholder="--css-variable-name"></td>
                            <td><input type="text" class="regular-text" name="ds_toolkit_settings[acf_css_vars_mappings][<?php echo $i; ?>][fallback]" value="<?php echo esc_attr( $mapping['fallback'] ?? '' ); ?>" placeholder="optional"></td>
                            <td><button type="button" class="button dst-remove-mapping" title="Remove">&#x2715;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="button dst-add-mapping" id="dst-add-mapping" data-count="<?php echo count( $acf_css_vars_mappings ); ?>">+ Add Mapping</button>
            </div>
        </div>
    </div>

    <!-- Get Sub Menu Shortcode -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-menu-alt"></span></div>
            <div class="dst-card-info">
                <strong>[getsubmenu] Shortcode</strong>
                <span>Adds a shortcode that displays the child pages of any page as a navigation list.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[getsubmenu_enabled]" value="0">
                <input type="checkbox" id="getsubmenu_enabled" name="ds_toolkit_settings[getsubmenu_enabled]" value="1" <?php checked( $getsubmenu_enabled ); ?>>
                <label for="getsubmenu_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row dst-shortcode-docs">
            <div class="dst-card-icon"><span class="dashicons dashicons-info-outline"></span></div>
            <div class="dst-card-info">
                <strong>How to use it</strong>
                <p>Use <code>listfrom</code> to name the parent page or menu item, and <code>mode</code> to choose where to look.</p>
                <p><strong>Mode: pages</strong> — lists the child pages of a parent page. You can use the page title, slug, or ID:</p>
                <code>[getsubmenu listfrom="Programs" mode="pages"]</code>
                <p><strong>Mode: menus</strong> — finds a nav menu item by title and lists its direct children from that menu:</p>
                <code>[getsubmenu listfrom="Programs" mode="menus"]</code>
                <p>The output is a <code>&lt;div class="submenu-text"&gt;</code> with links separated by line breaks, ready to style with CSS.</p>
            </div>
        </div>
    </div>

    <!-- Current Year Shortcode -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
            <div class="dst-card-info">
                <strong>[current_year] Shortcode</strong>
                <span>Outputs the current year automatically. Great for copyright notices in footers.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[current_year_enabled]" value="0">
                <input type="checkbox" id="current_year_enabled" name="ds_toolkit_settings[current_year_enabled]" value="1" <?php checked( $current_year_enabled ); ?>>
                <label for="current_year_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row dst-shortcode-docs">
            <div class="dst-card-icon"><span class="dashicons dashicons-info-outline"></span></div>
            <div class="dst-card-info">
                <strong>How to use it</strong>
                <p>Place this shortcode anywhere you want the year to appear and update itself automatically each year — no manual edits needed.</p>
                <code>[current_year]</code>
                <p>Example: &copy; <?php echo date( 'Y' ); ?> LeagueApps Design Shop — type it as: <code>&amp;copy; [current_year] LeagueApps Design Shop</code></p>
            </div>
        </div>
    </div>

    <!-- Forminator Email Partner Variable -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-email-alt"></span></div>
            <div class="dst-card-info">
                <strong>Forminator {email_partner} Variable</strong>
                <span>Adds a custom <code>{email_partner}</code> variable to Forminator forms, pulled from the ACF options field <code>partner_email</code>.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[forminator_email_partner_enabled]" value="0">
                <input type="checkbox" id="forminator_email_partner_enabled" name="ds_toolkit_settings[forminator_email_partner_enabled]" value="1" <?php checked( $forminator_email_partner_enabled ); ?>>
                <label for="forminator_email_partner_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row dst-shortcode-docs" id="dst-forminator-partner-row" <?php echo $forminator_email_partner_enabled ? '' : 'style="display:none"'; ?>>
            <div class="dst-card-icon"><span class="dashicons dashicons-info-outline"></span></div>
            <div class="dst-card-info">
                <strong>How to use it</strong>
                <p>In any Forminator form notification, use <code>{email_partner}</code> as a recipient or in the email body. It will be replaced with the email address stored in <strong>ACF Theme Options → partner_email</strong>.</p>
                <p>Example — set the "Send To" field in a Forminator notification to:</p>
                <code>{email_partner}</code>
                <p><strong>Fallback email</strong> — used when <code>partner_email</code> is empty in ACF:</p>
                <input type="email" class="regular-text" name="ds_toolkit_settings[forminator_email_partner_fallback]" value="<?php echo esc_attr( $forminator_email_partner_fallback ); ?>" placeholder="designshop@leagueapps.com">
            </div>
        </div>
    </div>

    <!-- Child Pages Cards -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-grid-view"></span></div>
            <div class="dst-card-info">
                <strong>[child_pages] — List Pages Cards</strong>
                <span>Outputs a responsive grid of child page cards using a Beaver Builder template. Also registers <code>[get_parent_page_title]</code> for use inside the card template.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[child_pages_enabled]" value="0">
                <input type="checkbox" id="child_pages_enabled" name="ds_toolkit_settings[child_pages_enabled]" value="1" <?php checked( $child_pages_enabled ); ?>>
                <label for="child_pages_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row dst-shortcode-docs">
            <div class="dst-card-icon"><span class="dashicons dashicons-info-outline"></span></div>
            <div class="dst-card-info">
                <strong>How to use it</strong>
                <p>Place <code>[child_pages]</code> on any parent page. It will automatically find and display all its child pages using the BB template below.</p>
                <p>Each card in your Beaver Builder template can use <code>[get_parent_page_title]</code> to output the parent page's title (e.g. "Programs").</p>
                <code>[child_pages]</code>
                <p>Override any setting per shortcode:</p>
                <code>[child_pages template="56369" columns="4" columns_tablet="2" columns_mobile="1"]</code>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
            <div class="dst-card-info">
                <strong>Default BB Template ID</strong>
                <span>The Beaver Builder saved layout ID used as the card template. Find it in the URL when editing the template in BB.</span>
            </div>
            <div class="dst-field-inline">
                <input type="number" class="small-text" name="ds_toolkit_settings[child_pages_template_id]" value="<?php echo esc_attr( $child_pages_template_id ); ?>" min="1" placeholder="56369">
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-smartphone"></span></div>
            <div class="dst-card-info">
                <strong>Default Columns</strong>
                <span>Number of columns on each screen size. Can be overridden per shortcode.</span>
            </div>
            <div class="dst-columns-inputs">
                <label>
                    <span class="dst-col-label">Desktop</span>
                    <input type="number" class="small-text" name="ds_toolkit_settings[child_pages_columns]" value="<?php echo esc_attr( $child_pages_columns ); ?>" min="1" max="6">
                </label>
                <label>
                    <span class="dst-col-label">Tablet</span>
                    <input type="number" class="small-text" name="ds_toolkit_settings[child_pages_columns_tablet]" value="<?php echo esc_attr( $child_pages_columns_tablet ); ?>" min="1" max="4">
                </label>
                <label>
                    <span class="dst-col-label">Mobile</span>
                    <input type="number" class="small-text" name="ds_toolkit_settings[child_pages_columns_mobile]" value="<?php echo esc_attr( $child_pages_columns_mobile ); ?>" min="1" max="2">
                </label>
            </div>
        </div>
    </div>

    <div class="dst-footer">
        <?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
        <span class="dst-footer-meta">
            <a href="https://github.com/agabriel1590/ds-toolkit" target="_blank" rel="noopener">GitHub</a>
            &nbsp;&middot;&nbsp; By Alipio Gabriel
        </span>
    </div>

</form>
