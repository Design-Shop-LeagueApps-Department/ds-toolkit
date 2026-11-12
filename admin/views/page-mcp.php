<?php
/**
 * MCP tab content.
 * Variables: $mcp_url, $wp_version_ok, $app_passwords_ok, $is_local,
 *            $mcp_posts_pages_enabled, $mcp_cpt_enabled, $mcp_taxonomies_enabled,
 *            $mcp_acf_enabled, $mcp_toolkit_settings_enabled, $mcp_bb_enabled, $mcp_acf_schema_enabled,
 *            $mcp_menus_enabled, $mcp_maintenance_enabled, $mcp_options_enabled, $mcp_users_enabled
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- Status bar -->
<div class="dst-mcp-status">
    <?php if ( $wp_version_ok && $app_passwords_ok ) : ?>
        <span class="dst-mcp-dot dst-mcp-dot--on"></span>
        <strong>MCP Endpoint Active<?php echo $is_local ? ' — Local' : ''; ?></strong>
        <code class="dst-mcp-endpoint"><?php echo esc_html( $mcp_url ); ?></code>
        <button type="button" class="button dst-copy-url-btn" data-url="<?php echo esc_attr( $mcp_url ); ?>">Copy URL</button>
        <?php if ( $is_local ) : ?>
        <span class="dst-mcp-note dst-mcp-note--full">&#9432; LocalWP detected — using <strong>http://</strong> to avoid Node.js SSL issues with local certificates. Application Passwords work because <code>WP_ENVIRONMENT_TYPE=local</code> is set in wp-config.php.</span>
        <?php endif; ?>
    <?php elseif ( ! $wp_version_ok ) : ?>
        <span class="dst-mcp-dot dst-mcp-dot--off"></span>
        <strong>Requires WordPress 5.6+</strong>
        <span class="dst-mcp-note">Application Passwords were added in WP 5.6. Please upgrade WordPress.</span>
    <?php else : ?>
        <span class="dst-mcp-dot dst-mcp-dot--off"></span>
        <strong>Application Passwords Disabled</strong>
        <span class="dst-mcp-note">Application Passwords are disabled on this site. Enable them to use MCP.</span>
    <?php endif; ?>
</div>

<!-- Claude Access Controls -->
<form method="post" action="options.php">
    <?php settings_fields( 'ds_toolkit_options' ); ?>

    <p class="dst-section-title dst-mt-24">Claude Access Controls</p>
    <div class="dst-card">

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-page"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_posts_pages_enabled">Posts &amp; Pages</strong>
                <span>Allow Claude to list, read, create, edit, and delete standard WordPress posts and pages.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_posts_pages_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_posts_pages_enabled" id="mcp_posts_pages_enabled" name="ds_toolkit_settings[mcp_posts_pages_enabled]" value="1" <?php checked( $mcp_posts_pages_enabled ); ?>>
                <label for="mcp_posts_pages_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-database"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_cpt_enabled">Custom Post Types</strong>
                <span>Allow Claude to access CPTs — Events, Athletes, Staff, Teams, and any future custom post types registered on this site.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_cpt_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_cpt_enabled" id="mcp_cpt_enabled" name="ds_toolkit_settings[mcp_cpt_enabled]" value="1" <?php checked( $mcp_cpt_enabled ); ?>>
                <label for="mcp_cpt_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-tag"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_taxonomies_enabled">Taxonomies</strong>
                <span>Allow Claude to list, create, edit, and delete taxonomy terms — Athlete Categories, Staff Categories, Team Categories, and any other taxonomies.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_taxonomies_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_taxonomies_enabled" id="mcp_taxonomies_enabled" name="ds_toolkit_settings[mcp_taxonomies_enabled]" value="1" <?php checked( $mcp_taxonomies_enabled ); ?>>
                <label for="mcp_taxonomies_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-forms"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_acf_enabled">ACF / Custom Fields</strong>
                <span>Allow Claude to read and update ACF field values on posts and CPT entries. Uses ACF's <code>get_fields()</code> / <code>update_field()</code> if ACF is active.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_acf_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_acf_enabled" id="mcp_acf_enabled" name="ds_toolkit_settings[mcp_acf_enabled]" value="1" <?php checked( $mcp_acf_enabled ); ?>>
                <label for="mcp_acf_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-settings"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_toolkit_settings_enabled">DS Toolkit Settings</strong>
                <span>Allow Claude to read and update DS Toolkit feature settings — toggles, column counts, Global CSS/JS, template IDs, etc.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_toolkit_settings_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_toolkit_settings_enabled" id="mcp_toolkit_settings_enabled" name="ds_toolkit_settings[mcp_toolkit_settings_enabled]" value="1" <?php checked( $mcp_toolkit_settings_enabled ); ?>>
                <label for="mcp_toolkit_settings_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-art"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_bb_enabled">Beaver Builder</strong>
                <span>Allow Claude to read and update Beaver Builder Global Style colors — Primary, Accent, Headings, Body, and all other named colors.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_bb_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_bb_enabled" id="mcp_bb_enabled" name="ds_toolkit_settings[mcp_bb_enabled]" value="1" <?php checked( $mcp_bb_enabled ); ?>>
                <label for="mcp_bb_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-networking"></span></div>
            <div class="dst-card-info">
                <strong>ACF Schema <span class="dst-badge-danger">&#9888; Destructive</span></strong>
                <span>Allow Claude to create, update, and delete ACF Post Types and Taxonomies. <strong id="dst-t-mcp_acf_schema_enabled">Restricted to @leagueapps.com accounts only</strong> — even if enabled, non-LeagueApps users cannot use these tools.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_acf_schema_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_acf_schema_enabled" id="mcp_acf_schema_enabled" name="ds_toolkit_settings[mcp_acf_schema_enabled]" value="1" <?php checked( $mcp_acf_schema_enabled ); ?>>
                <label for="mcp_acf_schema_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_menus_enabled">Menus</strong>
                <span>Allow Claude to list menus, read menu structure, replace all menu items, and assign menus to theme locations.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_menus_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_menus_enabled" id="mcp_menus_enabled" name="ds_toolkit_settings[mcp_menus_enabled]" value="1" <?php checked( $mcp_menus_enabled ); ?>>
                <label for="mcp_menus_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-tools"></span></div>
            <div class="dst-card-info">
                <strong>Maintenance</strong>
                <span>Allow Claude to flush rewrite rules, flush object cache, delete transients, and run search &amp; replace on the database. <strong id="dst-t-mcp_maintenance_enabled">search_replace restricted to @leagueapps.com accounts.</strong></span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_maintenance_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_maintenance_enabled" id="mcp_maintenance_enabled" name="ds_toolkit_settings[mcp_maintenance_enabled]" value="1" <?php checked( $mcp_maintenance_enabled ); ?>>
                <label for="mcp_maintenance_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-database"></span></div>
            <div class="dst-card-info">
                <strong>Options <span class="dst-badge-danger">&#9888; Destructive</span></strong>
                <span>Allow Claude to read and write WordPress options (wp_options). <strong id="dst-t-mcp_options_enabled">Restricted to @leagueapps.com accounts only.</strong></span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_options_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_options_enabled" id="mcp_options_enabled" name="ds_toolkit_settings[mcp_options_enabled]" value="1" <?php checked( $mcp_options_enabled ); ?>>
                <label for="mcp_options_enabled" aria-hidden="true"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
            <div class="dst-card-info">
                <strong id="dst-t-mcp_users_enabled">Users &amp; Media</strong>
                <span>Allow Claude to list and read WordPress users and regenerate image thumbnails.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_users_enabled]" value="0">
                <input type="checkbox" role="switch" aria-labelledby="dst-t-mcp_users_enabled" id="mcp_users_enabled" name="ds_toolkit_settings[mcp_users_enabled]" value="1" <?php checked( $mcp_users_enabled ); ?>>
                <label for="mcp_users_enabled" aria-hidden="true"></label>
            </div>
        </div>

    </div>

    <?php submit_button( 'Save Access Controls' ); ?>
</form>

<details class="dst-group dst-ref">
    <summary class="dst-group-head">
        <span class="dst-group-title"><span class="dashicons dashicons-backup" aria-hidden="true"></span> Changes made through MCP</span>
        <span class="dst-group-count"><?php echo esc_html( $mcp_log ? count( $mcp_log ) . ' most recent' : 'None yet' ); ?></span>
    </summary>
    <div class="dst-group-body">
        <?php if ( ! $mcp_log ) : ?>
        <p class="dst-ref-pointer">No tool that changes the site has been called yet. Each call of one is listed here: who, which tool, on what, and whether it went through. Values are never kept.</p>
        <?php else : ?>
        <div class="dst-card dst-mcp-log">
            <table class="widefat striped">
                <thead><tr><th scope="col">When</th><th scope="col">User</th><th scope="col">Tool</th><th scope="col">On</th><th scope="col">Result</th></tr></thead>
                <tbody>
                <?php foreach ( $mcp_log as $mcp_e ) :
                    $mcp_on = array();
                    foreach ( (array) ( $mcp_e['target'] ?? array() ) as $mcp_k => $mcp_v ) { $mcp_on[] = $mcp_k . ' ' . $mcp_v; }
                    ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) ( $mcp_e['t'] ?? 0 ) ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $mcp_e['user'] ?? '' ) ); ?></td>
                        <td><code><?php echo esc_html( (string) ( $mcp_e['tool'] ?? '' ) ); ?></code></td>
                        <td><?php echo esc_html( $mcp_on ? implode( ', ', $mcp_on ) : '-' ); ?></td>
                        <td><?php if ( ! empty( $mcp_e['ok'] ) ) : ?><span class="dst-ok">Done</span><?php else : ?><span class="dst-bad"><?php echo esc_html( 'Refused: ' . (string) ( $mcp_e['error'] ?? '' ) ); ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</details>

<details class="dst-group dst-ref">
    <summary class="dst-group-head">
        <span class="dst-group-title"><span class="dashicons dashicons-book" aria-hidden="true"></span> Setup guide</span>
        <span class="dst-group-count">What this is and the five setup steps</span>
    </summary>
    <div class="dst-group-body">

<!-- What is this -->
<p class="dst-section-title dst-mt-24">What is this?</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-rest-api"></span></div>
        <div class="dst-card-info">
            <strong>Claude ↔ WordPress — no extra plugins needed</strong>
            <span>DS Toolkit exposes a <a href="https://modelcontextprotocol.io" target="_blank" rel="noopener">Model Context Protocol</a> (MCP) endpoint. Connect Claude Desktop or Claude Code to this URL and Claude can read posts, edit pages, and change DS Toolkit settings directly — just by asking in conversation.</span>
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-shield"></span></div>
        <div class="dst-card-info">
            <strong>Secured by WordPress Application Passwords</strong>
            <span>Authentication uses <strong>Application Passwords</strong> (built into WP 5.6+) — a separate token that never exposes your login password and can be revoked at any time from your profile page.</span>
        </div>
    </div>
</div>

<!-- Setup steps -->
<p class="dst-section-title dst-mt-24">Setup Instructions</p>
<div class="dst-card">

    <div class="dst-card-row">
        <div class="dst-card-icon dst-step-num">1</div>
        <div class="dst-card-info">
            <strong>Install Node.js <span class="dst-title-aside">(required on all platforms)</span></strong>
            <span>Download and install the <strong>LTS version</strong> from <a href="https://nodejs.org/en/download" target="_blank" rel="noopener"><strong>nodejs.org/en/download</strong></a>. After install, open a terminal and run <code>node -v</code> to confirm it works.</span>
        </div>
    </div>

    <div class="dst-card-row">
        <div class="dst-card-icon dst-step-num">2</div>
        <div class="dst-card-info">
            <strong>Install the mcp-remote package <span class="dst-title-aside">(Windows only)</span></strong>
            <span>On <strong>Windows</strong>, Claude Desktop cannot resolve <code>npx</code> directly. Install mcp-remote globally so it's available as a <code>.cmd</code> file:<br>Open <strong>Command Prompt or PowerShell</strong> and run: <code>npm install -g mcp-remote</code></span>
        </div>
    </div>

    <div class="dst-card-row">
        <div class="dst-card-icon dst-step-num">3</div>
        <div class="dst-card-info">
            <strong>Create an Application Password</strong>
            <span>Go to <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>" target="_blank"><strong>WP Admin → Users → Your Profile</strong></a>, scroll to <strong>Application Passwords</strong>, enter a name (e.g. <em>Claude MCP</em>), click <em>Add New Application Password</em>, and copy the password. <strong>Save it — it won't be shown again.</strong></span>
        </div>
    </div>

    <div class="dst-card-row">
        <div class="dst-card-icon dst-step-num">4</div>
        <div class="dst-card-info">
            <strong>Generate your Claude config below</strong>
            <span>Select your operating system, enter your WordPress username and Application Password. The tool generates the exact config to paste into Claude Desktop, or the command for Claude Code.</span>
        </div>
    </div>

    <div class="dst-card-row">
        <div class="dst-card-icon dst-step-num">5</div>
        <div class="dst-card-info">
            <strong>Restart Claude and start talking to WordPress</strong>
            <span>After adding the config, restart Claude Desktop (or reload Claude Code). You'll see <em><?php echo esc_html( 'ds-toolkit-' . sanitize_title( get_bloginfo( 'name' ) ) ); ?></em> listed as an MCP server.</span>
        </div>
    </div>

</div>

    </div>
</details>

<!-- Config generator -->
<p class="dst-section-title dst-mt-24">Generate Claude Config</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-desktop"></span></div>
        <div class="dst-card-info">
            <strong>Operating System</strong>
            <span>Select the OS of the computer running Claude</span>
        </div>
        <div class="dst-field-inline dst-field-inline--wide">
            <label class="dst-radio"><input type="radio" name="dst-mcp-os" value="mac" checked> Mac / Linux</label>
            <label class="dst-radio"><input type="radio" name="dst-mcp-os" value="windows"> Windows</label>
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
        <div class="dst-card-info">
            <strong>WordPress Username</strong>
            <span>Your WP admin username (the one you log in with)</span>
        </div>
        <div class="dst-field-inline dst-field-inline--wide">
            <input type="text" id="dst-mcp-username" class="regular-text dst-w-full" placeholder="your_username">
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-lock"></span></div>
        <div class="dst-card-info">
            <strong>Application Password</strong>
            <span>The password from Step 3 — spaces are fine</span>
        </div>
        <div class="dst-field-inline dst-field-inline--wide">
            <input type="text" id="dst-mcp-apppass" class="regular-text dst-w-full" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx">
        </div>
    </div>
    <div class="dst-card-row dst-card-row--end">
        <button type="button" class="button button-primary" id="dst-mcp-generate">Generate Config</button>
    </div>

    <div id="dst-mcp-output" class="dst-is-hidden">

        <div class="dst-card-row dst-mcp-config-block">
            <div class="dst-w-full">
                <p class="dst-mcp-config-label">
                    <strong>Claude Desktop</strong>
                    <span id="dst-mcp-desktop-path">Add inside <code>mcpServers</code> in your Claude Desktop config file</span>
                </p>
                <div class="dst-mcp-textarea-wrap">
                    <textarea id="dst-mcp-config-desktop" class="dst-mcp-textarea" readonly rows="14"></textarea>
                    <button type="button" class="button dst-mcp-copy" data-target="dst-mcp-config-desktop">Copy</button>
                </div>
            </div>
        </div>

        <div class="dst-card-row dst-mcp-config-block">
            <div class="dst-w-full">
                <p class="dst-mcp-config-label">
                    <strong>Claude Code CLI</strong>
                    <span>Run this command in your terminal once to register the server</span>
                </p>
                <div class="dst-mcp-textarea-wrap">
                    <textarea id="dst-mcp-config-cli" class="dst-mcp-textarea" readonly rows="4"></textarea>
                    <button type="button" class="button dst-mcp-copy" data-target="dst-mcp-config-cli">Copy</button>
                </div>
            </div>
        </div>

    </div>
</div>

<details class="dst-group dst-ref">
    <summary class="dst-group-head">
        <span class="dst-group-title"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span> Tool reference</span>
        <span class="dst-group-count">Every tool, by group</span>
    </summary>
    <div class="dst-group-body">
        <p class="dst-ref-pointer">Guides for the modules and the builder are on <a href="<?php echo esc_url( DS_Toolkit_Admin::HQ_DOCS_URL ); ?>" target="_blank" rel="noopener">Design Shop HQ</a>. This list is the reference for what the plugin itself ships.</p>

<!-- Available tools: drawn from DS_MCP_Tools (group, capability, reads or writes) and the server's own descriptions -->
<p class="dst-section-title dst-mt-24">Available Tools</p>

<?php foreach ( DS_MCP_Tools::by_group() as $mcp_group => $mcp_names ) :
    list( $mcp_title, $mcp_staff ) = DS_MCP_Tools::GROUPS[ $mcp_group ];
    ?>
<p class="dst-section-title dst-section-subtitle"><?php echo esc_html( $mcp_title ); ?><?php if ( $mcp_staff ) : ?> <span class="dst-staff-note">&#9888; <?php echo esc_html( DS_Env::staff_domain() ); ?> only</span><?php endif; ?></p>
<div class="dst-card<?php echo ! empty( $opts[ $mcp_group ] ) ? '' : ' dst-is-dim'; ?>">
    <?php foreach ( $mcp_names as $mcp_name ) : $mcp_row = DS_MCP_Tools::get( $mcp_name ); ?>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-<?php echo $mcp_row['write'] ? 'edit' : 'visibility'; ?>" aria-hidden="true"></span></div>
        <div class="dst-card-info"><strong><?php echo esc_html( $mcp_name ); ?></strong><span><?php echo esc_html( isset( $mcp_tool_text[ $mcp_name ] ) ? $mcp_tool_text[ $mcp_name ] : '' ); ?></span></div>
        <span class="dst-mcp-cap"><?php echo $mcp_row['write'] ? 'writes' : 'reads'; ?><?php echo '' !== $mcp_row['cap'] ? ' &middot; ' . esc_html( $mcp_row['cap'] ) : ''; ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

    </div>
</details>

<details class="dst-group dst-ref">
    <summary class="dst-group-head">
        <span class="dst-group-title"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span> Example prompts</span>
        <span class="dst-group-count">Things to ask Claude</span>
    </summary>
    <div class="dst-group-body">

<!-- Example prompts -->
<p class="dst-section-title dst-mt-24">Example Claude Prompts</p>
<div class="dst-card">
    <div class="dst-card-row dst-shortcode-docs">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-chat"></span></div>
        <div class="dst-card-info">
            <strong>Content</strong>
            <p>List posts and edit one</p>
            <code>List all published pages on this site, then update the title of the About page to "About Us"</code>
            <p>Assign taxonomy terms</p>
            <code>Assign the "Basketball" and "Spring 2025" terms to post ID 88</code>

            <strong class="dst-prompt-group">Design</strong>
            <p>Switch the site header</p>
            <code>Change the site header to Header Style 4</code>
            <p>Switch the homepage layout</p>
            <code>Apply Home Page Layout 3 to the front page</code>
            <p>Update brand colors</p>
            <code>Change the Primary BB global color to #e63946 and Accent to #457b9d</code>
            <p>Edit global CSS</p>
            <code>Show me the Global CSS, then add a rule that makes all h2 headings color #333</code>

            <strong class="dst-prompt-group">ACF Schema</strong>
            <p>Explore field groups</p>
            <code>List all ACF field groups and show me the fields inside the "Event Details" group</code>
            <p>Create a field group</p>
            <code>Create an ACF field group called "Athlete Profile" on the athletes post type with fields: bio (textarea), position (text), jersey_number (number)</code>
            <p>Manage post types</p>
            <code>List all ACF post types and show me the settings for the "athletes" one</code>

            <strong class="dst-prompt-group">Menus</strong>
            <p>Rebuild a menu</p>
            <code>Replace the Primary Menu with these items: Home (page ID 2), About (page ID 14), Programs (custom link /programs/), Contact (page ID 22)</code>
            <p>Assign a menu</p>
            <code>Assign the "Main Menu" to the primary-menu location</code>

            <strong class="dst-prompt-group">Maintenance</strong>
            <p>Flush everything</p>
            <code>Flush the rewrite rules, object cache, and delete all transients</code>
            <p>Fix a URL after migration</p>
            <code>Search and replace "https://old-domain.com" with "https://new-domain.com" in the database</code>

            <strong class="dst-prompt-group">Settings</strong>
            <p>Toggle a feature</p>
            <code>Disable the [current_year] shortcode in DS Toolkit settings</code>
            <p>Read a WP option</p>
            <code>What is the current site title and tagline?</code>
        </div>
    </div>
</div>

    </div>
</details>

<script type="text/javascript">
(function ($) {
    'use strict';

    var mcpUrl       = <?php echo wp_json_encode( $mcp_url ); ?>;
    var mcpServerKey = <?php echo wp_json_encode( 'ds-toolkit-' . sanitize_title( get_bloginfo( 'name' ) ) ); ?>;

    // Copy endpoint URL button
    $('.dst-copy-url-btn').on('click', function () {
        copyText(mcpUrl, $(this));
    });

    // Generate config
    $('#dst-mcp-generate').on('click', function () {
        var username = $('#dst-mcp-username').val().trim();
        var apppass  = $('#dst-mcp-apppass').val().trim();
        var os       = $('input[name="dst-mcp-os"]:checked').val();

        if ( ! username || ! apppass ) {
            alert('Please enter both your WordPress username and Application Password.');
            return;
        }

        // Normalise app password: remove spaces (WP accepts both forms)
        var credentials = username + ':' + apppass.replace(/\s+/g, '');
        var encoded     = btoa( unescape( encodeURIComponent( credentials ) ) );
        var authHeader  = 'Basic ' + encoded;

        var isLocal = mcpUrl.indexOf('http://') === 0;
        var mcpArgs, command, desktopPath, cliCommand;

        if ( os === 'windows' ) {
            // On Windows, Claude Desktop cannot resolve plain `npx`.
            // mcp-remote must be installed globally (npm install -g mcp-remote)
            // which creates mcp-remote.cmd in the npm global bin directory.
            command = 'npx.cmd';
            mcpArgs = [ 'mcp-remote', mcpUrl ];
            if ( isLocal ) mcpArgs.push( '--allow-http' );
            mcpArgs.push( '--header', 'Authorization:' + authHeader );
            desktopPath = 'Add inside <code>mcpServers</code> in <code>%APPDATA%\\Claude\\claude_desktop_config.json</code>';
            cliCommand  =
                'claude mcp add ' + mcpServerKey + ' ^\n' +
                '  --transport http ^\n' +
                '  --header "Authorization: ' + authHeader + '" ^\n' +
                '  ' + mcpUrl;
        } else {
            command = 'npx';
            mcpArgs = [ 'mcp-remote', mcpUrl ];
            if ( isLocal ) mcpArgs.push( '--allow-http' );
            mcpArgs.push( '--header', 'Authorization:' + authHeader );
            desktopPath = 'Add inside <code>mcpServers</code> in <code>~/Library/Application Support/Claude/claude_desktop_config.json</code>';
            cliCommand  =
                'claude mcp add ' + mcpServerKey + ' \\\n' +
                '  --transport http \\\n' +
                '  --header "Authorization: ' + authHeader + '" \\\n' +
                '  ' + mcpUrl;
        }

        var mcpServers = {};
        mcpServers[ mcpServerKey ] = { "command": command, "args": mcpArgs };

        var desktopConfig = JSON.stringify({ "mcpServers": mcpServers }, null, 2);

        $('#dst-mcp-desktop-path').html( desktopPath );
        $('#dst-mcp-config-desktop').val( desktopConfig );
        $('#dst-mcp-config-cli').val( cliCommand );
        $('#dst-mcp-output').slideDown(200);
    });

    // Accordion tool sections — collapsed by default
    $('.dst-section-title.dst-section-subtitle').each(function () {
        var $title = $(this);
        var $card  = $title.next('.dst-card');
        if ( ! $card.length ) return;

        $card.hide();
        $title.append('<span class="dst-tools-chevron"></span>');

        $title.on('click', function () {
            if ( $title.hasClass('is-open') ) {
                $card.slideUp( 200 );
                $title.removeClass('is-open');
            } else {
                $card.slideDown( 200 );
                $title.addClass('is-open');
            }
        });
    });

    // Copy buttons
    $(document).on('click', '.dst-mcp-copy', function () {
        var targetId = $(this).data('target');
        var text     = $('#' + targetId).val();
        copyText(text, $(this));
    });

    function copyText(text, $btn) {
        var original = $btn.text();
        if ( navigator.clipboard ) {
            navigator.clipboard.writeText(text).then(function () {
                $btn.text('Copied!');
                setTimeout(function () { $btn.text(original); }, 1500);
            });
        } else {
            var el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            $btn.text('Copied!');
            setTimeout(function () { $btn.text(original); }, 1500);
        }
    }

}(jQuery));
</script>
