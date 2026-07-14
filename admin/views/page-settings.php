<?php
/**
 * Features tab content.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 * Variables available: $enabled, $logo_id, $logo_url, $default_url, $hide_fl_assistant,
 *                      $acf_css_vars_enabled, $acf_css_vars_mappings, $getsubmenu_enabled,
 *                      $current_year_enabled, $overlay_nav_enabled,
 *                      $forminator_email_partner_enabled,
 *                      $forminator_email_partner_fallback, $child_pages_enabled,
 *                      $child_pages_template_id, $child_pages_columns,
 *                      $child_pages_columns_tablet, $child_pages_columns_mobile,
 *                      $uabb_post_loop_fix_enabled, $blueprint_version,
 *                      $disable_comments_enabled, $theme_setting_enabled,
 *                      $admin_menu_tidy_enabled, $ds_menu_module_enabled,
 *                      $image_optimization_enabled
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

    <!-- User Roles (fleet-wide) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-groups"></span></div>
            <div class="dst-card-info">
                <strong>User Roles (Partner)</strong>
                <span>Registers a <strong>Partner</strong> role — everything an Administrator has except plugin management and the in-admin file editors — so partner accounts get safe admin access with one pick on the Users screen. Also hides the Plugins menu from non-LeagueApps users (menu label only; capabilities untouched). Auto-enabled on every install.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[user_roles_enabled]" value="0">
                <input type="checkbox" id="user_roles_enabled" name="ds_toolkit_settings[user_roles_enabled]" value="1" <?php checked( $user_roles_enabled ); ?>>
                <label for="user_roles_enabled"></label>
            </div>
        </div>
        <div class="dst-card-row" style="padding-top:0;">
            <div class="dst-card-icon" aria-hidden="true"></div>
            <div class="dst-card-info">
                <label for="partner_plugin_access" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="hidden" name="ds_toolkit_settings[partner_plugin_access]" value="0">
                    <input type="checkbox" id="partner_plugin_access" name="ds_toolkit_settings[partner_plugin_access]" value="1" <?php checked( $partner_plugin_access ); ?>>
                    <span><strong>Allow plugin access</strong> — re-enables plugin management for the Partner role and stops hiding the Plugins menu from non-LeagueApps users. Use when a dev decides a partner should manage plugins on this site.</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Design Academy dashboard panel (fleet-wide) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-welcome-learn-more"></span></div>
            <div class="dst-card-info">
                <strong>Design Academy Dashboard</strong>
                <span>Puts a <strong>Design Academy</strong> panel at the top of the WordPress dashboard: a pinned getting-started course plus the five newest tutorials from <a href="https://designacademy.leagueapps.com/" target="_blank" rel="noopener">designacademy.leagueapps.com</a> (cached 6 hours; falls back to the last good list if the academy is unreachable). Auto-enabled on every install.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[design_academy_enabled]" value="0">
                <input type="checkbox" id="design_academy_enabled" name="ds_toolkit_settings[design_academy_enabled]" value="1" <?php checked( $design_academy_enabled ); ?>>
                <label for="design_academy_enabled"></label>
            </div>
        </div>
        <div class="dst-card-row" style="padding-top:0;">
            <div class="dst-card-icon" aria-hidden="true"></div>
            <div class="dst-card-info" style="width:100%;">
                <strong>Pinned course</strong>
                <span>The "Start here" item at the top of the panel.</span>
                <input type="text" name="ds_toolkit_settings[academy_pinned_label]" value="<?php echo esc_attr( $academy_pinned_label ); ?>" placeholder="Pinned label" style="width:100%;margin-top:6px;">
                <input type="url" name="ds_toolkit_settings[academy_pinned_url]" value="<?php echo esc_attr( $academy_pinned_url ); ?>" placeholder="https://designacademy.leagueapps.com/…" style="width:100%;margin-top:6px;">
            </div>
        </div>
    </div>

    <!-- LeagueApps modules (Beaver Builder blocks) — opt-in on ANY blueprint, off by default below gen 6 -->
    <p class="dst-section-title">LeagueApps Modules (Beaver Builder blocks)</p>
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-grid-view"></span></div>
            <div class="dst-card-info">
                <strong>Enable all modules</strong>
                <span>Quick switch to turn every LeagueApps block on (or off) at once. Each block can still be toggled individually below. Remember to Save.</span>
            </div>
            <div class="dst-toggle">
                <input type="checkbox" id="dst-modules-all">
                <label for="dst-modules-all"></label>
            </div>
        </div>
        <?php foreach ( DS_Toolkit::module_features() as $mod_key => $mod ) : ?>
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-screenoptions"></span></div>
            <div class="dst-card-info">
                <strong><?php echo esc_html( $mod['label'] ); ?></strong>
                <span><?php echo esc_html( $mod['desc'] ); ?></span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[<?php echo esc_attr( $mod_key ); ?>]" value="0">
                <input type="checkbox" class="dst-module-toggle" id="<?php echo esc_attr( $mod_key ); ?>" name="ds_toolkit_settings[<?php echo esc_attr( $mod_key ); ?>]" value="1" <?php checked( ! empty( $ds_module_states[ $mod_key ] ) ); ?>>
                <label for="<?php echo esc_attr( $mod_key ); ?>"></label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
    ( function () {
        var all  = document.getElementById( 'dst-modules-all' );
        var mods = [].slice.call( document.querySelectorAll( '.dst-module-toggle' ) );
        if ( ! all || ! mods.length ) { return; }
        function sync() {
            var on = mods.filter( function ( c ) { return c.checked; } ).length;
            all.checked       = ( on === mods.length );
            all.indeterminate = ( on > 0 && on < mods.length );
        }
        all.addEventListener( 'change', function () {
            mods.forEach( function ( c ) { c.checked = all.checked; } );
            all.indeterminate = false;
        } );
        mods.forEach( function ( c ) { c.addEventListener( 'change', sync ); } );
        sync();
    } )();
    </script>

    <?php if ( $blueprint_version >= 6 ) : ?>
    <!-- Disable Comments (blueprint generation 6+) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-comments"></span></div>
            <div class="dst-card-info">
                <strong>Disable Comments</strong>
                <span>Turns WordPress comments off site-wide — closes comments and pings, hides existing comments, removes comment support from every post type, and cleans the admin (Comments menu, dashboard widget, admin-bar item). Replaces the standalone Disable Comments plugin. Auto-enabled on DSLP6 builds.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[disable_comments_enabled]" value="0">
                <input type="checkbox" id="disable_comments_enabled" name="ds_toolkit_settings[disable_comments_enabled]" value="1" <?php checked( $disable_comments_enabled ); ?>>
                <label for="disable_comments_enabled"></label>
            </div>
        </div>
    </div>

    <!-- Theme Setting page (blueprint generation 6+) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-admin-customizer"></span></div>
            <div class="dst-card-info">
                <strong>Theme Setting (LeagueApps only)</strong>
                <span>Registers the internal <strong>Theme Setting</strong> admin page directly below <strong>Partner Setting</strong>, visible only to LeagueApps email users. A synced surface for Beaver Builder &rsaquo; Global Styles (named global colors + Text/Heading/Link/Button defaults) reading/writing the same store, so edits here and in BB stay in sync. Auto-enabled on DSLP6 builds.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[theme_setting_enabled]" value="0">
                <input type="checkbox" id="theme_setting_enabled" name="ds_toolkit_settings[theme_setting_enabled]" value="1" <?php checked( $theme_setting_enabled ); ?>>
                <label for="theme_setting_enabled"></label>
            </div>
        </div>
    </div>

    <!-- Admin Menu Tidy (blueprint generation 6+) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
            <div class="dst-card-info">
                <strong>Admin Menu Tidy</strong>
                <span>Declutters the admin menu: moves Defender, Yoast, and Media Library Organizer under <strong>Tools</strong>. Hides ACF, Beaver Builder, and Appearance from non-LeagueApps users (left in place for LeagueApps). Capabilities are preserved, so it only relocates what a user can already access. Auto-enabled on DSLP6 builds.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[admin_menu_tidy_enabled]" value="0">
                <input type="checkbox" id="admin_menu_tidy_enabled" name="ds_toolkit_settings[admin_menu_tidy_enabled]" value="1" <?php checked( $admin_menu_tidy_enabled ); ?>>
                <label for="admin_menu_tidy_enabled"></label>
            </div>
        </div>
    </div>


    <!-- Image Optimization on Upload (blueprint generation 6+) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-images-alt2"></span></div>
            <div class="dst-card-info">
                <strong>Image Optimization on Upload</strong>
                <span>Downscales oversized images to a web-friendly <strong>2048px</strong> longest edge and re-encodes them as <strong>WebP</strong> (quality 82) the moment they're uploaded — before thumbnails are built, so every sub-size is WebP too. A partner dropping a 12 MB phone photo ends up with a lightweight WebP instead. Covers Media Library uploads and WP-CLI / sideload imports. Only touches JPEG/PNG (and oversized WebP); GIFs and SVGs pass through, and an already-small image that wouldn't benefit is left untouched. Auto-enabled on DSLP6 builds.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[image_optimization_enabled]" value="0">
                <input type="checkbox" id="image_optimization_enabled" name="ds_toolkit_settings[image_optimization_enabled]" value="1" <?php checked( $image_optimization_enabled ); ?>>
                <label for="image_optimization_enabled"></label>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- UABB Advanced Posts featured-image loop fix -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-format-image"></span></div>
            <div class="dst-card-info">
                <strong>UABB Advanced Posts — Featured Image Loop Fix</strong>
                <span>Patches the UABB Advanced Posts module bug where every post in a "Custom" layout shows the first post's featured image. Toggles Beaver Builder Theme Builder's <code>FLThemeBuilderFieldConnections::$in_post_grid_loop</code> flag around each post via UABB's <code>uabb_blog_posts_before_post</code> / <code>uabb_blog_posts_after_post</code> hooks. Works for any post type — Posts, Staff, Events, Teams, etc. Safe to leave on: the hooks only fire when UABB Advanced Posts is rendering, so sites without UABB are unaffected.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[uabb_post_loop_fix_enabled]" value="0">
                <input type="checkbox" id="uabb_post_loop_fix_enabled" name="ds_toolkit_settings[uabb_post_loop_fix_enabled]" value="1" <?php checked( $uabb_post_loop_fix_enabled ); ?>>
                <label for="uabb_post_loop_fix_enabled"></label>
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

    <!-- Overlay Nav Shortcodes -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
            <div class="dst-card-info">
                <strong>[ds_overlay_nav] / [ds_overlay_subs] Shortcodes</strong>
                <span>Renders a WordPress nav menu as a full-screen overlay: a numbered left panel and a matching sub-link right panel.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[overlay_nav_enabled]" value="0">
                <input type="checkbox" id="overlay_nav_enabled" name="ds_toolkit_settings[overlay_nav_enabled]" value="1" <?php checked( $overlay_nav_enabled ); ?>>
                <label for="overlay_nav_enabled"></label>
            </div>
        </div>

        <div class="dst-card-row dst-shortcode-docs">
            <div class="dst-card-icon"><span class="dashicons dashicons-info-outline"></span></div>
            <div class="dst-card-info">
                <strong>How to use it</strong>
                <p>Place <code>[ds_overlay_nav]</code> in the left panel to output top-level menu items, numbered automatically. Place <code>[ds_overlay_subs]</code> in the right panel, on the same page and after <code>[ds_overlay_nav]</code>, to output the child-link blocks for any items that have a submenu.</p>
                <code>[ds_overlay_nav menu="primary"]</code>
                <code>[ds_overlay_subs]</code>
                <p>The <code>menu</code> attribute accepts a menu name or slug (defaults to <code>primary</code>). Markup is unstyled by design — pair it with your theme's overlay-nav CSS/JS.</p>
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
