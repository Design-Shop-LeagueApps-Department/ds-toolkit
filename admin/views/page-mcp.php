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
        <span class="dst-mcp-note" style="width:100%;margin-top:6px;">&#9432; LocalWP detected — using <strong>http://</strong> to avoid Node.js SSL issues with local certificates. Application Passwords work because <code>WP_ENVIRONMENT_TYPE=local</code> is set in wp-config.php.</span>
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

    <p class="dst-section-title" style="margin-top:24px;">Claude Access Controls</p>
    <div class="dst-card">

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-page"></span></div>
            <div class="dst-card-info">
                <strong>Posts &amp; Pages</strong>
                <span>Allow Claude to list, read, create, edit, and delete standard WordPress posts and pages.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_posts_pages_enabled]" value="0">
                <input type="checkbox" id="mcp_posts_pages_enabled" name="ds_toolkit_settings[mcp_posts_pages_enabled]" value="1" <?php checked( $mcp_posts_pages_enabled ); ?>>
                <label for="mcp_posts_pages_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-database"></span></div>
            <div class="dst-card-info">
                <strong>Custom Post Types</strong>
                <span>Allow Claude to access CPTs — Events, Athletes, Staff, Teams, and any future custom post types registered on this site.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_cpt_enabled]" value="0">
                <input type="checkbox" id="mcp_cpt_enabled" name="ds_toolkit_settings[mcp_cpt_enabled]" value="1" <?php checked( $mcp_cpt_enabled ); ?>>
                <label for="mcp_cpt_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-tag"></span></div>
            <div class="dst-card-info">
                <strong>Taxonomies</strong>
                <span>Allow Claude to list, create, edit, and delete taxonomy terms — Athlete Categories, Staff Categories, Team Categories, and any other taxonomies.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_taxonomies_enabled]" value="0">
                <input type="checkbox" id="mcp_taxonomies_enabled" name="ds_toolkit_settings[mcp_taxonomies_enabled]" value="1" <?php checked( $mcp_taxonomies_enabled ); ?>>
                <label for="mcp_taxonomies_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-forms"></span></div>
            <div class="dst-card-info">
                <strong>ACF / Custom Fields</strong>
                <span>Allow Claude to read and update ACF field values on posts and CPT entries. Uses ACF's <code>get_fields()</code> / <code>update_field()</code> if ACF is active.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_acf_enabled]" value="0">
                <input type="checkbox" id="mcp_acf_enabled" name="ds_toolkit_settings[mcp_acf_enabled]" value="1" <?php checked( $mcp_acf_enabled ); ?>>
                <label for="mcp_acf_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-settings"></span></div>
            <div class="dst-card-info">
                <strong>DS Toolkit Settings</strong>
                <span>Allow Claude to read and update DS Toolkit feature settings — toggles, column counts, Global CSS/JS, template IDs, etc.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_toolkit_settings_enabled]" value="0">
                <input type="checkbox" id="mcp_toolkit_settings_enabled" name="ds_toolkit_settings[mcp_toolkit_settings_enabled]" value="1" <?php checked( $mcp_toolkit_settings_enabled ); ?>>
                <label for="mcp_toolkit_settings_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-art"></span></div>
            <div class="dst-card-info">
                <strong>Beaver Builder</strong>
                <span>Allow Claude to read and update Beaver Builder Global Style colors — Primary, Accent, Headings, Body, and all other named colors.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_bb_enabled]" value="0">
                <input type="checkbox" id="mcp_bb_enabled" name="ds_toolkit_settings[mcp_bb_enabled]" value="1" <?php checked( $mcp_bb_enabled ); ?>>
                <label for="mcp_bb_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-networking"></span></div>
            <div class="dst-card-info">
                <strong>ACF Schema <span style="color:#b32d2e;font-size:11px;font-weight:600;background:#fce8e8;padding:1px 6px;border-radius:3px;margin-left:4px;">&#9888; Destructive</span></strong>
                <span>Allow Claude to create, update, and delete ACF Post Types and Taxonomies. <strong>Restricted to @leagueapps.com accounts only</strong> — even if enabled, non-LeagueApps users cannot use these tools.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_acf_schema_enabled]" value="0">
                <input type="checkbox" id="mcp_acf_schema_enabled" name="ds_toolkit_settings[mcp_acf_schema_enabled]" value="1" <?php checked( $mcp_acf_schema_enabled ); ?>>
                <label for="mcp_acf_schema_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
            <div class="dst-card-info">
                <strong>Menus</strong>
                <span>Allow Claude to list menus, read menu structure, replace all menu items, and assign menus to theme locations.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_menus_enabled]" value="0">
                <input type="checkbox" id="mcp_menus_enabled" name="ds_toolkit_settings[mcp_menus_enabled]" value="1" <?php checked( $mcp_menus_enabled ); ?>>
                <label for="mcp_menus_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-tools"></span></div>
            <div class="dst-card-info">
                <strong>Maintenance</strong>
                <span>Allow Claude to flush rewrite rules, flush object cache, delete transients, and run search &amp; replace on the database. <strong>search_replace restricted to @leagueapps.com accounts.</strong></span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_maintenance_enabled]" value="0">
                <input type="checkbox" id="mcp_maintenance_enabled" name="ds_toolkit_settings[mcp_maintenance_enabled]" value="1" <?php checked( $mcp_maintenance_enabled ); ?>>
                <label for="mcp_maintenance_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-database"></span></div>
            <div class="dst-card-info">
                <strong>Options <span style="color:#b32d2e;font-size:11px;font-weight:600;background:#fce8e8;padding:1px 6px;border-radius:3px;margin-left:4px;">&#9888; Destructive</span></strong>
                <span>Allow Claude to read and write WordPress options (wp_options). <strong>Restricted to @leagueapps.com accounts only.</strong></span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_options_enabled]" value="0">
                <input type="checkbox" id="mcp_options_enabled" name="ds_toolkit_settings[mcp_options_enabled]" value="1" <?php checked( $mcp_options_enabled ); ?>>
                <label for="mcp_options_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
            <div class="dst-card-info">
                <strong>Users &amp; Media</strong>
                <span>Allow Claude to list and read WordPress users and regenerate image thumbnails.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[mcp_users_enabled]" value="0">
                <input type="checkbox" id="mcp_users_enabled" name="ds_toolkit_settings[mcp_users_enabled]" value="1" <?php checked( $mcp_users_enabled ); ?>>
                <label for="mcp_users_enabled"></label>
            </div>
        </div>

    </div>

    <?php submit_button( 'Save Access Controls' ); ?>
</form>

<!-- What is this -->
<p class="dst-section-title" style="margin-top:24px;">What is this?</p>
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
<p class="dst-section-title" style="margin-top:24px;">Setup Instructions</p>
<div class="dst-card">

    <div class="dst-card-row">
        <div class="dst-card-icon dst-step-num">1</div>
        <div class="dst-card-info">
            <strong>Install Node.js <span style="font-weight:400;color:#888;">(required on all platforms)</span></strong>
            <span>Download and install the <strong>LTS version</strong> from <a href="https://nodejs.org/en/download" target="_blank" rel="noopener"><strong>nodejs.org/en/download</strong></a>. After install, open a terminal and run <code>node -v</code> to confirm it works.</span>
        </div>
    </div>

    <div class="dst-card-row">
        <div class="dst-card-icon dst-step-num">2</div>
        <div class="dst-card-info">
            <strong>Install the mcp-remote package <span style="font-weight:400;color:#888;">(Windows only)</span></strong>
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

<!-- Config generator -->
<p class="dst-section-title" style="margin-top:24px;">Generate Claude Config</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-desktop"></span></div>
        <div class="dst-card-info">
            <strong>Operating System</strong>
            <span>Select the OS of the computer running Claude</span>
        </div>
        <div class="dst-field-inline" style="min-width:220px;">
            <label style="display:block;margin-bottom:6px;"><input type="radio" name="dst-mcp-os" value="mac" checked> Mac / Linux</label>
            <label style="display:block;"><input type="radio" name="dst-mcp-os" value="windows"> Windows</label>
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
        <div class="dst-card-info">
            <strong>WordPress Username</strong>
            <span>Your WP admin username (the one you log in with)</span>
        </div>
        <div class="dst-field-inline" style="min-width:220px;">
            <input type="text" id="dst-mcp-username" class="regular-text" placeholder="your_username" style="width:100%;">
        </div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-lock"></span></div>
        <div class="dst-card-info">
            <strong>Application Password</strong>
            <span>The password from Step 3 — spaces are fine</span>
        </div>
        <div class="dst-field-inline" style="min-width:220px;">
            <input type="text" id="dst-mcp-apppass" class="regular-text" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" style="width:100%;">
        </div>
    </div>
    <div class="dst-card-row" style="justify-content:flex-end;">
        <button type="button" class="button button-primary" id="dst-mcp-generate">Generate Config</button>
    </div>

    <div id="dst-mcp-output" style="display:none;">

        <div class="dst-card-row dst-mcp-config-block">
            <div style="width:100%;">
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
            <div style="width:100%;">
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

<!-- Available tools -->
<p class="dst-section-title" style="margin-top:24px;">Available Tools</p>

<p class="dst-section-title dst-section-subtitle">Posts &amp; Pages</p>
<div class="dst-card" style="<?php echo $mcp_posts_pages_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
        <div class="dst-card-info"><strong>list_posts</strong><span>List posts or pages. Filter by status or keyword.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-visibility"></span></div>
        <div class="dst-card-info"><strong>get_post</strong><span>Get full content of a post by ID — title, content, excerpt, status, author.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <div class="dst-card-info"><strong>create_post</strong><span>Create a new post or page with title, content, status.</span></div>
        <span class="dst-mcp-cap">publish_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-edit"></span></div>
        <div class="dst-card-info"><strong>update_post</strong><span>Edit title, content, excerpt, or status of an existing post.</span></div>
        <span class="dst-mcp-cap">edit_post</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-trash"></span></div>
        <div class="dst-card-info"><strong>delete_post</strong><span>Trash or permanently delete a post.</span></div>
        <span class="dst-mcp-cap">delete_post</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-upload"></span></div>
        <div class="dst-card-info"><strong>bulk_create_posts</strong><span>Create multiple posts in one call — ideal for CSV imports. Pass an array of post objects; returns per-item results with IDs.</span></div>
        <span class="dst-mcp-cap">publish_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-update"></span></div>
        <div class="dst-card-info"><strong>bulk_update_posts</strong><span>Update multiple posts in one call. Each item needs an id plus the fields to change; returns per-item results.</span></div>
        <span class="dst-mcp-cap">edit_post</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Custom Post Types</p>
<div class="dst-card" style="<?php echo $mcp_cpt_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-database"></span></div>
        <div class="dst-card-info"><strong>list_post_types</strong><span>Discover all registered CPTs — Events, Athletes, Staff, Teams, and any future types.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
        <div class="dst-card-info"><strong>list_posts</strong><span>Same tool as above — pass <code>post_type: "athletes"</code> (or any CPT slug) to query CPT entries.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <div class="dst-card-info"><strong>create_post / update_post / delete_post</strong><span>Same tools as above — pass the CPT slug as <code>post_type</code> to create/edit/delete CPT entries.</span></div>
        <span class="dst-mcp-cap">publish_posts</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Taxonomies</p>
<div class="dst-card" style="<?php echo $mcp_taxonomies_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-tag"></span></div>
        <div class="dst-card-info"><strong>list_taxonomies</strong><span>List all public taxonomies and which post types they belong to.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
        <div class="dst-card-info"><strong>list_terms</strong><span>List terms in a taxonomy (e.g. athlete-category). Supports search and pagination.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-visibility"></span></div>
        <div class="dst-card-info"><strong>get_term</strong><span>Get a single term by ID or slug.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <div class="dst-card-info"><strong>create_term</strong><span>Add a new term to a taxonomy with optional slug, description, and parent.</span></div>
        <span class="dst-mcp-cap">manage_categories</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-edit"></span></div>
        <div class="dst-card-info"><strong>update_term</strong><span>Edit a term's name, slug, or description.</span></div>
        <span class="dst-mcp-cap">manage_categories</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-trash"></span></div>
        <div class="dst-card-info"><strong>delete_term</strong><span>Remove a term from a taxonomy.</span></div>
        <span class="dst-mcp-cap">manage_categories</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">ACF / Custom Fields</p>
<div class="dst-card" style="<?php echo $mcp_acf_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-forms"></span></div>
        <div class="dst-card-info"><strong>get_post_fields</strong><span>Read all ACF fields on a post or CPT entry. Falls back to post meta if ACF is not active.</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-edit"></span></div>
        <div class="dst-card-info"><strong>update_post_fields</strong><span>Update ACF field values by passing a <code>field_name → value</code> map. Uses <code>update_field()</code> if ACF active.</span></div>
        <span class="dst-mcp-cap">edit_post</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Partner Settings</p>
<div class="dst-card" style="<?php echo $mcp_acf_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-site"></span></div>
        <div class="dst-card-info"><strong>get_partner_settings</strong><span>Read all ACF Partner Settings — logo, email, phone, address, and social links (Facebook, Instagram, X, YouTube, LinkedIn, TikTok, LeagueApps).</span></div>
        <span class="dst-mcp-cap">edit_posts</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-edit"></span></div>
        <div class="dst-card-info"><strong>update_partner_settings</strong><span>Update any partner fields by name. URL fields expect full URLs. <code>partner_logo</code> expects a Media Library attachment ID.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">DS Toolkit Settings</p>
<div class="dst-card" style="<?php echo $mcp_toolkit_settings_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-settings"></span></div>
        <div class="dst-card-info"><strong>get_toolkit_settings</strong><span>Read all DS Toolkit settings — feature toggles, column counts, template IDs, etc.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-hammer"></span></div>
        <div class="dst-card-info"><strong>update_toolkit_settings</strong><span>Update feature toggles, Global CSS/JS, column counts, and other settings.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">ACF Schema <span style="color:#b32d2e;font-size:11px;">&#9888; @leagueapps.com only</span></p>
<div class="dst-card" style="<?php echo $mcp_acf_schema_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
        <div class="dst-card-info"><strong>acf_list_post_types</strong><span>List all ACF-managed post types — returns key, slug, labels, and settings.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <div class="dst-card-info"><strong>acf_create_post_type</strong><span>Create a new CPT via ACF — slug, labels, supports, taxonomies, visibility.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-edit"></span></div>
        <div class="dst-card-info"><strong>acf_update_post_type</strong><span>Update an existing ACF post type by key. Only provided fields are changed.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-trash"></span></div>
        <div class="dst-card-info"><strong>acf_delete_post_type</strong><span>Permanently delete an ACF post type by key. Irreversible.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
        <div class="dst-card-info"><strong>acf_list_taxonomies</strong><span>List all ACF-managed taxonomies — returns key, slug, labels, and associated post types.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <div class="dst-card-info"><strong>acf_create_taxonomy</strong><span>Create a new taxonomy via ACF — slug, labels, hierarchical, associated post types.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-edit"></span></div>
        <div class="dst-card-info"><strong>acf_update_taxonomy</strong><span>Update an existing ACF taxonomy by key. Only provided fields are changed.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-trash"></span></div>
        <div class="dst-card-info"><strong>acf_delete_taxonomy</strong><span>Permanently delete an ACF taxonomy by key. Irreversible.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">ACF Field Groups <span style="color:#b32d2e;font-size:11px;">&#9888; @leagueapps.com only</span></p>
<div class="dst-card" style="<?php echo $mcp_acf_schema_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
        <div class="dst-card-info"><strong>acf_list_field_groups</strong><span>List all ACF field groups — returns key, title, active status, and location rules.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-search"></span></div>
        <div class="dst-card-info"><strong>acf_get_field_group</strong><span>Get a single field group by key, including all its fields (key, label, name, type).</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <div class="dst-card-info"><strong>acf_create_field_group</strong><span>Create a new ACF field group with optional location rules and fields in a single call.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-edit"></span></div>
        <div class="dst-card-info"><strong>acf_update_field_group</strong><span>Update an existing field group — title, location, position, label placement, or active state.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-trash"></span></div>
        <div class="dst-card-info"><strong>acf_delete_field_group</strong><span>Permanently delete a field group and all its fields. Irreversible.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">ACF Options Pages <span style="color:#b32d2e;font-size:11px;">&#9888; @leagueapps.com only &bull; ACF Pro 6.2+</span></p>
<div class="dst-card" style="<?php echo $mcp_acf_schema_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
        <div class="dst-card-info"><strong>acf_list_options_pages</strong><span>List all ACF Pro options pages — returns key, title, menu_slug, and parent_slug.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <div class="dst-card-info"><strong>acf_create_options_page</strong><span>Create a new ACF Pro options page with title, menu slug, parent, capability, and optional redirect.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-trash"></span></div>
        <div class="dst-card-info"><strong>acf_delete_options_page</strong><span>Permanently delete an ACF Pro options page. Irreversible.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Beaver Builder</p>
<div class="dst-card" style="<?php echo $mcp_bb_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-art"></span></div>
        <div class="dst-card-info"><strong>get_bb_global_colors</strong><span>Read all BB Global Style colors — returns a label → hex map (e.g. Primary, Accent, Headings).</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-color-picker"></span></div>
        <div class="dst-card-info"><strong>update_bb_global_colors</strong><span>Update named BB Global Style colors by label. Flushes BB CSS cache automatically so changes are live immediately.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <div class="dst-card-info"><strong>bb_list_layout_templates</strong><span>List available DS Launchpad layout templates — Header Style 1–5, Footer Style 1–3, Home Page Layout 1–6. Filter by type (header/footer/home).</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-image-rotate"></span></div>
        <div class="dst-card-info"><strong>bb_apply_layout_template</strong><span>Replace "Header Main" or "Footer Main" (Themer layouts) or the site front page with a DS Launchpad template. Requires <code>confirm: true</code> — replaces current content.</span></div>
        <span class="dst-mcp-cap">manage_options</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Menus</p>
<div class="dst-card" style="<?php echo $mcp_menus_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
        <div class="dst-card-info"><strong>list_menus</strong><span>List all nav menus with their IDs, slugs, item counts, and assigned theme locations.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
        <div class="dst-card-info"><strong>get_menu</strong><span>Get the full item list for a menu — title, URL, parent, order, and object type for each item.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
        <div class="dst-card-info"><strong>set_menu_items</strong><span>Replace all items in a menu with a new structure. Supports nested items via <code>parent_index</code>. Requires <code>confirm: true</code>.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
        <div class="dst-card-info"><strong>assign_menu_to_location</strong><span>Assign a menu to a registered theme location (e.g. primary-menu, header-menu).</span></div>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Maintenance</p>
<div class="dst-card" style="<?php echo $mcp_maintenance_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-tools"></span></div>
        <div class="dst-card-info"><strong>flush_rewrite_rules</strong><span>Flush WordPress rewrite rules — fixes 404s after adding CPTs or changing permalink structure.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-tools"></span></div>
        <div class="dst-card-info"><strong>flush_cache</strong><span>Flush the WordPress object cache.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-tools"></span></div>
        <div class="dst-card-info"><strong>delete_transients</strong><span>Delete all transients from the options table.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-tools"></span></div>
        <div class="dst-card-info"><strong>search_replace</strong><span>Search and replace text in the database (posts, postmeta, options). Requires <code>confirm: true</code>.</span></div>
        <span class="dst-mcp-cap"><?php echo esc_html( DS_TOOLKIT_ADMIN_DOMAIN ); ?> only</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Options <span style="color:#b32d2e;font-size:11px;">&#9888; @leagueapps.com only</span></p>
<div class="dst-card" style="<?php echo $mcp_options_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-database"></span></div>
        <div class="dst-card-info"><strong>get_option</strong><span>Read any WordPress option by key (e.g. blogname, siteurl, blogdescription).</span></div>
        <span class="dst-mcp-cap"><?php echo esc_html( DS_TOOLKIT_ADMIN_DOMAIN ); ?> only</span>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-database"></span></div>
        <div class="dst-card-info"><strong>update_option</strong><span>Update any WordPress option by key and value.</span></div>
        <span class="dst-mcp-cap"><?php echo esc_html( DS_TOOLKIT_ADMIN_DOMAIN ); ?> only</span>
    </div>
</div>

<p class="dst-section-title dst-section-subtitle">Users &amp; Media</p>
<div class="dst-card" style="<?php echo $mcp_users_enabled ? '' : 'opacity:.5;'; ?>">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
        <div class="dst-card-info"><strong>list_users</strong><span>List users filtered by role, search keyword, with pagination.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
        <div class="dst-card-info"><strong>get_user</strong><span>Get a user's profile by ID or email — name, roles, capabilities, registration date.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-search"></span></div>
        <div class="dst-card-info"><strong>list_media</strong><span>Search and list Media Library files. Filter by keyword, MIME type (image, video, audio, application/pdf), or the post they're attached to.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
        <div class="dst-card-info"><strong>get_media</strong><span>Get full details for a single attachment — URL, alt text, caption, dimensions, file size, and all registered image sizes.</span></div>
    </div>
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-image-rotate"></span></div>
        <div class="dst-card-info"><strong>regenerate_thumbnails</strong><span>Regenerate image thumbnail sizes for Media Library images. Optionally pass specific attachment IDs.</span></div>
    </div>
</div>

<!-- Example prompts -->
<p class="dst-section-title" style="margin-top:24px;">Example Claude Prompts</p>
<div class="dst-card">
    <div class="dst-card-row dst-shortcode-docs">
        <div class="dst-card-icon"><span class="dashicons dashicons-format-chat"></span></div>
        <div class="dst-card-info">
            <strong>Content</strong>
            <p>List posts and edit one</p>
            <code>List all published pages on this site, then update the title of the About page to "About Us"</code>
            <p>Assign taxonomy terms</p>
            <code>Assign the "Basketball" and "Spring 2025" terms to post ID 88</code>

            <strong style="margin-top:12px;display:block;">Design</strong>
            <p>Switch the site header</p>
            <code>Change the site header to Header Style 4</code>
            <p>Switch the homepage layout</p>
            <code>Apply Home Page Layout 3 to the front page</code>
            <p>Update brand colors</p>
            <code>Change the Primary BB global color to #e63946 and Accent to #457b9d</code>
            <p>Edit global CSS</p>
            <code>Show me the Global CSS, then add a rule that makes all h2 headings color #333</code>

            <strong style="margin-top:12px;display:block;">ACF Schema</strong>
            <p>Explore field groups</p>
            <code>List all ACF field groups and show me the fields inside the "Event Details" group</code>
            <p>Create a field group</p>
            <code>Create an ACF field group called "Athlete Profile" on the athletes post type with fields: bio (textarea), position (text), jersey_number (number)</code>
            <p>Manage post types</p>
            <code>List all ACF post types and show me the settings for the "athletes" one</code>

            <strong style="margin-top:12px;display:block;">Menus</strong>
            <p>Rebuild a menu</p>
            <code>Replace the Primary Menu with these items: Home (page ID 2), About (page ID 14), Programs (custom link /programs/), Contact (page ID 22)</code>
            <p>Assign a menu</p>
            <code>Assign the "Main Menu" to the primary-menu location</code>

            <strong style="margin-top:12px;display:block;">Maintenance</strong>
            <p>Flush everything</p>
            <code>Flush the rewrite rules, object cache, and delete all transients</code>
            <p>Fix a URL after migration</p>
            <code>Search and replace "https://old-domain.com" with "https://new-domain.com" in the database</code>

            <strong style="margin-top:12px;display:block;">Settings</strong>
            <p>Toggle a feature</p>
            <code>Disable the [current_year] shortcode in DS Toolkit settings</code>
            <p>Read a WP option</p>
            <code>What is the current site title and tagline?</code>
        </div>
    </div>
</div>

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
