<?php
/**
 * Features tab content.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 *
 * Cards are organised into collapsible GROUPS (<details>) so ~30 features stay
 * navigable: Security & Access, Partner Experience, Builder Modules,
 * Site & Content, Shortcodes & Dev Tools. Each summary shows an on/total count,
 * open/closed state persists per group in localStorage, and the filter box at
 * the top searches card titles/descriptions across all groups.
 *
 * Blueprint gating is PER CARD here (not one big wrapper) because gated cards
 * live in different groups.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ---- group counts (on / total), computed from the same vars the cards use ----
$dst_bp6 = ( $blueprint_version >= 6 );

$dst_grp_security = array( $hide_fl_assistant, $user_roles_enabled, $bot_shield_enabled, $origin_guard_enabled );
if ( $dst_bp6 ) {
	$dst_grp_security[] = $disable_comments_enabled;
	$dst_grp_security[] = $admin_menu_tidy_enabled;
}
$dst_grp_partner = array( $enabled, $design_academy_enabled );
$dst_grp_site    = $dst_bp6 ? array( $theme_setting_enabled, $image_optimization_enabled ) : array();
$dst_grp_site[]  = $nested_order_enabled;
$dst_grp_dev     = array( $uabb_post_loop_fix_enabled, $acf_css_vars_enabled, $getsubmenu_enabled, $current_year_enabled, $overlay_nav_enabled, $forminator_email_partner_enabled, $child_pages_enabled );

$dst_count = function ( $arr ) {
	return count( array_filter( $arr ) ) . ' of ' . count( $arr ) . ' on';
};
$dst_mod_on  = count( array_filter( (array) $ds_module_states ) );
$dst_mod_all = count( DS_Toolkit::module_features() );
?>
<form method="post" action="options.php">
    <?php settings_fields( 'ds_toolkit_options' ); ?>

    <div class="dst-filter-bar">
        <span class="dashicons dashicons-search" aria-hidden="true"></span>
        <input type="search" id="dst-filter" placeholder="Filter features… (e.g. turnstile, shortcode, hero)" autocomplete="off">
    </div>

    <!-- ============================== SECURITY & ACCESS ============================== -->
    <details class="dst-group" data-dst-group="security" open>
        <summary class="dst-group-head">
            <span class="dst-group-title"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span> Security &amp; Access</span>
            <span class="dst-group-count"><?php echo esc_html( $dst_count( $dst_grp_security ) ); ?></span>
        </summary>
        <div class="dst-group-body">

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
                <span>Registers a <strong>Partner</strong> role — everything an Administrator has except plugin management and the in-admin file editors — so partner accounts get safe admin access with one pick on the Users screen. Also hides the <strong>Plugins</strong>, <strong>Tools</strong>, and <strong>Settings</strong> menus from non-LeagueApps users (menu labels only; capabilities untouched). Auto-enabled on every install.</span>
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

    <?php if ( $dst_bp6 ) : ?>
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

    <!-- Admin Menu Tidy (blueprint generation 6+) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-menu"></span></div>
            <div class="dst-card-info">
                <strong>Admin Menu Tidy</strong>
                <span>Declutters the admin menu: moves Defender, Yoast, and Media Library Organizer under <strong>Tools</strong>. Hides ACF and Beaver Builder from non-LeagueApps users (left in place for LeagueApps; Appearance stays visible to everyone so partners can manage menus). Capabilities are preserved, so it only relocates what a user can already access. Auto-enabled on DSLP6 builds.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[admin_menu_tidy_enabled]" value="0">
                <input type="checkbox" id="admin_menu_tidy_enabled" name="ds_toolkit_settings[admin_menu_tidy_enabled]" value="1" <?php checked( $admin_menu_tidy_enabled ); ?>>
                <label for="admin_menu_tidy_enabled"></label>
            </div>
        </div>
    </div>
    <?php endif; ?>

        </div>
    <!-- Bot Shield (fleet-wide) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-shield"></span></div>
            <div class="dst-card-info">
                <strong>Bot Shield</strong>
                <span>Origin protection against bot floods that page caching and Defender miss (valid-URL request storms that exhaust PHP workers and cause 502s). Rejects abusive traffic in ~1ms before the page render: per-IP rate limit with a penalty box, an automatic "under attack" browser check when the whole site is being flooded, and a crawler blocklist. Logged-in users, WP Engine internals, and known search engines are never touched. Ships in Monitor mode (counts, blocks nothing) so a day of numbers can be read before flipping to Block. A Bot Shield box on the WP dashboard shows mode and today's counts.
                <?php if ( $bot_shield_stats ) : ?><br><strong>Today:</strong> <?php echo esc_html( $bot_shield_stats ); ?><?php endif; ?></span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[bot_shield_enabled]" value="0">
                <input type="checkbox" id="bot_shield_enabled" name="ds_toolkit_settings[bot_shield_enabled]" value="1" <?php checked( $bot_shield_enabled ); ?>>
                <label for="bot_shield_enabled"></label>
            </div>
        </div>
        <div class="dst-card-row" style="padding-top:0;">
            <div class="dst-card-icon" aria-hidden="true"></div>
            <div class="dst-card-info" style="width:100%;">
                <strong>Mode</strong>
                <span style="display:flex;gap:20px;align-items:center;margin-top:4px;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="radio" name="ds_toolkit_settings[bot_shield_mode]" value="monitor" <?php checked( 'monitor', $bot_shield_mode ); ?>>
                        Monitor (count only, block nothing)
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="radio" name="ds_toolkit_settings[bot_shield_mode]" value="block" <?php checked( 'block', $bot_shield_mode ); ?>>
                        Block (enforce)
                    </label>
                    <label>Pagination cap
                        <input type="number" min="5" name="ds_toolkit_settings[bot_shield_page_cap]" value="<?php echo esc_attr( $bot_shield_page_cap ); ?>" style="width:70px;">
                    </label>
                </span>
                <span style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-top:8px;">
                    <label>Per-IP limit/min
                        <input type="number" min="30" name="ds_toolkit_settings[bot_shield_ip_limit]" value="<?php echo esc_attr( $bot_shield_ip_limit ); ?>" style="width:80px;">
                    </label>
                    <label>Penalty (min)
                        <input type="number" min="1" name="ds_toolkit_settings[bot_shield_penalty_mins]" value="<?php echo esc_attr( $bot_shield_penalty_mins ); ?>" style="width:70px;">
                    </label>
                    <label>Site limit/10s
                        <input type="number" min="50" name="ds_toolkit_settings[bot_shield_global_limit]" value="<?php echo esc_attr( $bot_shield_global_limit ); ?>" style="width:80px;">
                    </label>
                    <label>Attack mode (min)
                        <input type="number" min="1" name="ds_toolkit_settings[bot_shield_attack_mins]" value="<?php echo esc_attr( $bot_shield_attack_mins ); ?>" style="width:70px;">
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="hidden" name="ds_toolkit_settings[bot_shield_challenge_enabled]" value="0">
                        <input type="checkbox" name="ds_toolkit_settings[bot_shield_challenge_enabled]" value="1" <?php checked( $bot_shield_challenge_enabled ); ?>>
                        Browser check when under attack
                    </label>
                </span>
                <strong style="display:block;margin-top:10px;">Blocked user agents</strong>
                <span>One per line, case-insensitive substring match, answered 403.</span>
                <textarea name="ds_toolkit_settings[bot_shield_ua_blocklist]" rows="4" style="width:100%;margin-top:6px;"><?php echo esc_textarea( $bot_shield_ua_blocklist ); ?></textarea>
                <strong style="display:block;margin-top:10px;">IP allowlist</strong>
                <span>Never limited or challenged. One per line: exact IP, CIDR (203.0.113.0/24), or prefix (203.0.113.).</span>
                <textarea name="ds_toolkit_settings[bot_shield_ip_allowlist]" rows="3" style="width:100%;margin-top:6px;"><?php echo esc_textarea( $bot_shield_ip_allowlist ); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Origin Guard (pre-plugin mu-plugin) -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-shield-alt"></span></div>
            <div class="dst-card-info">
                <strong>Origin Guard</strong>
                <span>The layer below Bot Shield. Installs a tiny must-use plugin that refuses the exact request shapes of a bot flood <em>before WordPress loads any plugins</em>, so each attack request costs ~20ms instead of a full render (a 50-100x capacity gain on the attacked endpoints). Rejects anonymous REST user-enumeration (<code>/wp-json/wp/v2/users</code>), credential-stuffing login POSTs that never rendered the login form, and XML-RPC. Logged-in users, authenticated API calls, and normal visitors are never touched; the XML-RPC rule is skipped automatically on sites running Jetpack or the WP mobile app. Built after WPE ticket #8578620 (the Aug 2026 fleet flood).
                <?php if ( $origin_guard_enabled && ! $origin_guard_installed ) : ?><br><strong style="color:#b32d2e;">Not installed:</strong> the mu-plugins directory could not be written. Check filesystem permissions.<?php elseif ( $origin_guard_installed ) : ?><br><strong style="color:#1a7f37;">Active</strong> — running as <code>mu-plugins/ds-origin-guard.php</code>.<?php endif; ?></span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[origin_guard_enabled]" value="0">
                <input type="checkbox" id="origin_guard_enabled" name="ds_toolkit_settings[origin_guard_enabled]" value="1" <?php checked( $origin_guard_enabled ); ?>>
                <label for="origin_guard_enabled"></label>
            </div>
        </div>
        <div class="dst-card-row" style="padding-top:0;">
            <div class="dst-card-icon" aria-hidden="true"></div>
            <div class="dst-card-info" style="width:100%;">
                <strong>Rules</strong>
                <span style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;margin-top:4px;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="hidden" name="ds_toolkit_settings[origin_guard_block_login]" value="0">
                        <input type="checkbox" name="ds_toolkit_settings[origin_guard_block_login]" value="1" <?php checked( $origin_guard_block_login ); ?>>
                        Block cold login POSTs
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="hidden" name="ds_toolkit_settings[origin_guard_block_xmlrpc]" value="0">
                        <input type="checkbox" name="ds_toolkit_settings[origin_guard_block_xmlrpc]" value="1" <?php checked( $origin_guard_block_xmlrpc ); ?>>
                        Block XML-RPC (auto-skipped if Jetpack is active)
                    </label>
                </span>
            </div>
        </div>
    </div>

    <!-- LeagueApps modules (Beaver Builder blocks) — opt-in on ANY blueprint, off by default below gen 6 -->
    <p class="dst-section-title">LeagueApps Modules (Beaver Builder blocks)</p>

    </details>

    <!-- ============================== PARTNER EXPERIENCE ============================== -->
    <details class="dst-group" data-dst-group="partner" open>
        <summary class="dst-group-head">
            <span class="dst-group-title"><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span> Partner Experience</span>
            <span class="dst-group-count"><?php echo esc_html( $dst_count( $dst_grp_partner ) ); ?></span>
        </summary>
        <div class="dst-group-body">

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

        </div>
    </details>

    <!-- ============================== BUILDER MODULES ============================== -->
    <details class="dst-group" data-dst-group="modules" open>
        <summary class="dst-group-head">
            <span class="dst-group-title"><span class="dashicons dashicons-grid-view" aria-hidden="true"></span> LeagueApps Modules (Beaver Builder blocks)</span>
            <span class="dst-group-count"><?php echo esc_html( $dst_mod_on . ' of ' . $dst_mod_all . ' on' ); ?></span>
        </summary>
        <div class="dst-group-body">

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

        </div>
    </details>

    <?php if ( $dst_bp6 ) : ?>
    <!-- ============================== SITE & CONTENT ============================== -->
    <details class="dst-group" data-dst-group="site" open>
        <summary class="dst-group-head">
            <span class="dst-group-title"><span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span> Site &amp; Content</span>
            <span class="dst-group-count"><?php echo esc_html( $dst_count( $dst_grp_site ) ); ?></span>
        </summary>
        <div class="dst-group-body">

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

        </div>

    <!-- Nested Pages order on the front end -->
    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-sort"></span></div>
            <div class="dst-card-info">
                <strong>Nested Pages Order on the Front End</strong>
                <span><strong>Off by default on existing sites</strong>, because switching it on changes the visible order of live archives. Makes the front end list entries in the order set by dragging them in <strong>Nested Pages</strong>: Nested Pages saves that order but never applies it to the public site, so archives otherwise fall back to newest-first and ignore it. Also makes a Post Loop set to &ldquo;Menu Order / Nested Pages position&rdquo; read top-to-bottom instead of reversed. Applies to the custom post types Nested Pages manages (Teams, Staff, Athletes, Events). Blog posts stay newest-first, and any layout or loop that sets its own order always wins.</span>
            </div>
            <div class="dst-toggle">
                <input type="hidden" name="ds_toolkit_settings[nested_order_enabled]" value="0">
                <input type="checkbox" id="nested_order_enabled" name="ds_toolkit_settings[nested_order_enabled]" value="1" <?php checked( $nested_order_enabled ); ?>>
                <label for="nested_order_enabled"></label>
            </div>
        </div>
    </div>
    </details>
    <?php endif; ?>

    <!-- ============================== SHORTCODES & DEV TOOLS ============================== -->
    <details class="dst-group" data-dst-group="dev">
        <summary class="dst-group-head">
            <span class="dst-group-title"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> Shortcodes &amp; Dev Tools</span>
            <span class="dst-group-count"><?php echo esc_html( $dst_count( $dst_grp_dev ) ); ?></span>
        </summary>
        <div class="dst-group-body">

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

        </div>
    </details>

    <div class="dst-footer">
        <?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
        <span class="dst-footer-meta">
            <a href="https://github.com/agabriel1590/ds-toolkit" target="_blank" rel="noopener">GitHub</a>
            &nbsp;&middot;&nbsp; By Alipio Gabriel
        </span>
    </div>

    <script>
    ( function () {
        // ---- remember each group's open/closed state --------------------------
        var groups = [].slice.call( document.querySelectorAll( 'details.dst-group' ) );
        groups.forEach( function ( g ) {
            var key = 'dst-group-' + g.getAttribute( 'data-dst-group' );
            try {
                var saved = window.localStorage.getItem( key );
                if ( 'closed' === saved ) { g.open = false; }
                if ( 'open' === saved )   { g.open = true;  }
            } catch ( e ) {}
            g.addEventListener( 'toggle', function () {
                try { window.localStorage.setItem( key, g.open ? 'open' : 'closed' ); } catch ( e ) {}
            } );
        } );

        // ---- filter box: search card titles + descriptions across groups ------
        var input = document.getElementById( 'dst-filter' );
        if ( ! input ) { return; }
        var cards = [].slice.call( document.querySelectorAll( '.dst-group .dst-card' ) );

        function apply() {
            var q = input.value.trim().toLowerCase();
            cards.forEach( function ( card ) {
                var hit = ! q || card.textContent.toLowerCase().indexOf( q ) !== -1;
                card.style.display = hit ? '' : 'none';
            } );
            groups.forEach( function ( g ) {
                var visible = [].slice.call( g.querySelectorAll( '.dst-card' ) ).some( function ( c ) {
                    return c.style.display !== 'none';
                } );
                g.style.display = visible ? '' : 'none';
                if ( q && visible ) { g.open = true; } // reveal matches while searching
            } );
            if ( ! q ) {
                // restore saved open/closed states after clearing the search
                groups.forEach( function ( g ) {
                    var key = 'dst-group-' + g.getAttribute( 'data-dst-group' );
                    try {
                        var saved = window.localStorage.getItem( key );
                        if ( 'closed' === saved ) { g.open = false; }
                    } catch ( e ) {}
                } );
            }
        }
        input.addEventListener( 'input', apply );
    } )();
    </script>

</form>
