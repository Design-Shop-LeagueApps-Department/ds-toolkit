# Changelog

All notable changes to DS Toolkit are documented here.

---

## [1.2.4] - 2026-05-05
### Added
- **Features tab toggle for the UABB Advanced Posts featured-image loop fix.** The patch was registered as a feature in 1.2.0 but never had a UI row, so it couldn't be inspected or controlled. Now visible alongside the other feature toggles, with a description that explains it works on any post type (Posts, Staff, Events, Teams, etc.) and that the hooks only fire when UABB Advanced Posts is rendering — safe to leave on regardless of whether UABB is installed.

### Changed
- **Default for `uabb_post_loop_fix_enabled` restored to `1`** (was `0` in 1.2.2/1.2.3). Unlike Global CSS / Global JS / MCP toggles, this is a compat patch — it only acts when UABB fires its per-post hooks, so defaulting on doesn't change behavior on sites that don't use UABB. Sites that hit the v1.2.1 fatal and got the option rebuilt from 1.2.2's defaults can now flip it back on from the Features tab without DB editing.

## [1.2.3] - 2026-05-05
### Performance
- **`[child_pages]` shortcode** — capped `posts_per_page` at 50 (was unbounded `-1`) and added a new `limit` shortcode attribute (max 200). Each child renders a Beaver Builder saved layout, so on parent pages with many children the old query could exhaust memory.
- **`[getsubmenu]` shortcode** — capped `posts_per_page` at 100 (was unbounded `-1`); replaced deprecated `get_page_by_title()` (removed-in-WP-6.2 noise) with a `WP_Query` title lookup; switched the children loop to read titles off the post objects instead of re-fetching each via `get_the_title($id)`.
- **Global CSS / Global JS** — static-cache the contents of `includes/defaults/global-css.css` and `global-js.js` per-request so multiple `wp_head`/`wp_footer` calls don't re-read from disk.
- **ACF CSS Vars** — cache the rendered `<style>` block in object cache (group `ds_toolkit`, 1 hour TTL); auto-busts on `acf/save_post` so options-page edits flush immediately.
- **MCP `bb_list_layout_templates`** — capped `posts_per_page` at 200 (was unbounded `-1`); skip post meta/term cache priming since only `ID` and `post_title` are read.
- **MCP `delete_transients`** — also flushes the persistent object cache when one is in use, so transients held in Redis/Memcached actually get cleared (the SQL `DELETE` only ever touched `wp_options`). Response now includes `object_cache_flushed`.
- **University Logo Finder import** — replaced the `meta_query LIKE '%filename%'` lookup with a direct `$wpdb->get_var` query using an anchored trailing match (`%/<filename>`). Avoids the WP_Query → wp_posts JOIN on every "is this logo already imported?" check (one per logo per import batch).
- **Plugin bootstrap** — `DS_MCP_Server` is now loaded lazily on `rest_api_init` instead of being `require_once`'d on every request. Frontend and wp-admin page loads no longer parse the 2.8k-line MCP file. Also deduplicated a redundant `get_option('ds_toolkit_settings')` in `run()`.

### Fixed
- **`[current_year]` shortcode** — uses `wp_date('Y')` instead of `date('Y')` so the year reflects the site's configured timezone instead of the server's.

## [1.2.2] - 2026-05-05
### Fixed
- **Fatal error** "Cannot access offset of type string on string" in `class-ds-toolkit.php` when saving the MCP tab with every checkbox unchecked. The empty POST caused WP to write the option as an empty string; `maybe_set_defaults()` then tried to index into a string and white-screened the site. Now both `maybe_set_defaults()` and `run()` coerce a non-array option back to `array()`, so a corrupted value self-heals on the next request.
- **Toggles wouldn't turn off** on the Features and MCP tabs. Unchecked checkboxes don't POST anything, so the missing key was refilled from defaults on the next load and the toggle silently re-enabled itself. Each toggle now has a hidden `value="0"` input so the off state is explicitly submitted (mirrors the trick already used on Global CSS / Global JS).
- **Saving one tab wiped the others.** `register_setting()` had no sanitize callback, so each form's POST replaced the entire option array. Backported the merge sanitize callback (LA branch `c087a19`) so each tab only updates the keys it owns.

### Changed
- **Fresh-install defaults**: only **LeagueApps Custom Login** and **Hide Beaver Builder Cloud Icon for Non-LeagueApps Users** are on by default. Global CSS, Global JS, ACF CSS Vars, `[getsubmenu]`, `[current_year]`, Forminator `{email_partner}`, `[child_pages]`, the UABB post-loop fix, and every MCP tool group now default to **off**. Existing installs keep their current settings — only newly seeded keys pick up the new defaults.

## [1.2.1] - 2026-05-02
### Fixed
- UABB post loop fix — patch now tracks flag ownership so it only resets `$in_post_grid_loop` if it was the one that set it. Prevents conflict if UABB ships their own fix that sets the flag at the loop level.

## [1.2.0] - 2026-05-02
### Fixed
- UABB Advanced Posts module — all posts showing the same featured image when using a Custom Post Layout with `[fl_builder_insert_layout]`. Beaver Builder's field connection cache was using an identical key for every post in the loop because `FLThemeBuilderFieldConnections::$in_post_grid_loop` was never set. The patch toggles that flag around each post render via the existing `uabb_blog_posts_before_post` / `uabb_blog_posts_after_post` hooks.

## [1.1.0] - 2026-04-08
### Fixed
- `.top-social > div` — added `float: right !important` to right-align the social icon container

## [1.0.1] - 2026-04-04
### Added
- Added header-top zindex for header style 5 permanent fix

## [1.0.0] - 2026-04-04
### Added
- University Logo Finder now shows 40 logos at a time with a **Load More** button — avoids rendering all 365 logos at once
- Acronym / keyword search in Logo Finder — auto-generates initials from every team name (e.g. "AUE" for American University Eagles) and includes a manual map of well-known acronyms (OSU, PSU, MSU, FSU, UNC, KU, etc.); search supports multiple space-separated tokens so "AU American" or "OSU Ohio" both work

## [0.9.15] - 2026-03-28
### Changed
- Disabled `.top-social` flex layout overrides in `global-css.css` — rules commented out to stop interfering with site-specific social icon layouts

## [0.9.14] - 2026-03-28
### Added
- CSS custom properties (`--dst-*`) system in `global-css.css` — all hardcoded values (heights, grid gaps, z-indexes, border radius, sticky logo dimensions, etc.) are now CSS variables, letting site CSS override any value without touching the plugin file
- **CSS Variable Overrides** field on the Global CSS tab — CSS entered here is stored in `wp_options` and injected as a second `<style>` block after the plugin CSS, so overrides survive every plugin update
### Changed
- Global CSS is now fully plugin-managed — the admin textarea is replaced with a utility class reference grouped by category; full CSS source shown read-only at the bottom
- Global JS is now fully plugin-managed — the CodeMirror editor is replaced with a feature documentation page (Sticky Header, Clickable Columns, Equal Heights, Button Normaliser); full JS source shown read-only at the bottom
- Both `class-ds-global-css.php` and `class-ds-global-js.php` now always read their respective plugin files at runtime — changes to plugin files take effect on next page load with no admin action needed

## [0.9.13] - 2026-03-28
### Added
- `bulk_create_posts` and `bulk_update_posts` MCP tools — accept an array of post objects in a single call, reducing context usage for CSV import workflows
- `DS_TOOLKIT_FORCE_BETA` constant — allows beta channel on WP Engine / production environments when explicitly needed
### Changed
- Beta update channel is now fully automatic — no wp-config.php constants needed. Local environments (`.local`, `localhost`, `127.x`, `192.168.x`, or non-production `WP_ENVIRONMENT_TYPE`) receive beta updates automatically; live/WP Engine sites receive stable only
- Global CSS tab replaced with a utility class reference — lists all available classes with descriptions grouped by category; full CSS source shown read-only at the bottom
- Updater reverted to 12-hour cache — clicking "Check for Updates" clears cache and fetches from GitHub immediately
- Available Tools sections on MCP tab are now collapsible accordions, collapsed by default
### Fixed
- `thumbnail_id` support added to `create_post` and `update_post` MCP tools
- PHP warning "Undefined array key tag_name" in updater when old cache format was present

## [0.9.13-beta.5] - 2026-03-28
### Changed
- Beta update channel is now fully automatic — no wp-config.php constants needed. Local environments (`.local`, `localhost`, `127.x`, `192.168.x`, or `WP_ENVIRONMENT_TYPE` set to non-production) receive beta updates automatically; live/WP Engine sites receive stable only

## [0.9.13-beta.4] - 2026-03-28
### Added
- `DS_TOOLKIT_FORCE_BETA` constant — allows beta update channel on WP Engine / production environments. Add both `define( 'DS_TOOLKIT_UPDATE_CHANNEL', 'beta' )` and `define( 'DS_TOOLKIT_FORCE_BETA', true )` to wp-config.php to opt a live site into beta updates (e.g. dslaunchpad4 staging)

## [0.9.13-beta.3] - 2026-03-28
### Changed
- Updater reverted to 12-hour cache — clicking "Check for Updates" clears the cache and fetches from GitHub immediately; no background polling on every page load

## [0.9.13-beta.2] - 2026-03-28
### Changed
- Global CSS tab replaced with a utility class reference — lists all available classes with descriptions grouped by category; full CSS source shown read-only at the bottom; CSS is now managed directly in `includes/defaults/global-css.css`

## [0.9.13-beta.1] - 2026-03-28
### Added
- bump the css class height-avatar under global css to 350

## [0.9.12] - 2026-03-28
### Fixed
- Beta channel now automatically disabled on production environments (`WP_ENVIRONMENT_TYPE=production`) — pushing a local wp-config.php to WP Engine no longer accidentally activates beta updates
- Beta version comparison no longer offers same-base beta as an upgrade over its stable release

## [0.9.11] - 2026-03-28
### Added
- `bulk_create_posts` MCP tool — create multiple posts/pages/CPT entries in one call; ideal for CSV imports
- `bulk_update_posts` MCP tool — update multiple posts in one call
### Changed
- Plugin updater re-checks GitHub every 60 seconds automatically — no manual click required
### Fixed
- Updater "Undefined array key tag_name" warning from stale ETag-format transient

## [0.9.11-beta.3] - 2026-03-28
### Fixed
- Updater no longer throws "Undefined array key tag_name" warning — discards old ETag-format transient left over from previous updater version

## [0.9.11-beta.2] - 2026-03-27
### Changed
- Plugin updater now re-checks GitHub every 60 seconds automatically — new releases appear within one minute with no manual "Check for Updates" click required

## [0.9.11-beta.1] - 2026-03-27
### Added
- `bulk_create_posts` MCP tool — create multiple posts/pages/CPT entries in one call (ideal for CSV imports); returns per-item results with IDs and errors
- `bulk_update_posts` MCP tool — update multiple posts in one call; each item needs an id plus the fields to change

## [0.9.10] - 2026-03-27
### Added
- `list_media`, `get_media` MCP tools — search and inspect Media Library attachments (title, URL, MIME type, dimensions, all registered image sizes)
- `thumbnail_id` support in `create_post` and `update_post` — set or remove the featured image
- `thumbnail_id` returned in `get_post` and `list_posts` responses
- Available Tools accordion redesigned — collapsed by default, styled card-row headers with chevron

## [0.9.10-beta.8] - 2026-03-27
### Changed
- Available Tools accordion redesigned — sections are now styled card-row headers (white background, border, chevron arrow) and collapsed by default; click to expand

## [0.9.10-beta.7] - 2026-03-27
### Added
- `list_media`, `get_media` MCP tools — search and inspect Media Library attachments (title, URL, MIME type, dimensions, all registered image sizes)
- `thumbnail_id` support in `create_post` and `update_post` — pass a Media Library attachment ID to set the featured image, or 0 to remove it
- `thumbnail_id` returned in `get_post` and `list_posts` responses

## [0.9.10-beta.6] - 2026-03-27
### Added
- Available Tools sections are now collapsible — click any section title to expand/collapse, keeping the MCP page compact

## [0.9.10-beta.5] - 2026-03-27
### Fixed
- OS radio buttons in MCP config generator rendered as stretched ovals — excluded radio/checkbox inputs from the `.dst-field-inline` width rule

## [0.9.10-beta.4] - 2026-03-27
### Fixed
- Update badge now detects new releases instantly on every admin page load using GitHub's ETag conditional requests — `304 Not Modified` responses are free (don't count against rate limits) so no polling cost when nothing has changed; a `200` response means a new release is available and the badge appears immediately

## [0.9.10-beta.3] - 2026-03-27
### Added
- OS toggle in config generator (Mac/Linux vs Windows) — Windows generates `npx.cmd` as the command and shows the correct config file path (`%APPDATA%\Claude\claude_desktop_config.json`)
- Setup Instructions step 2 now calls out that Windows users must run `npm install -g mcp-remote` before connecting

## [0.9.10-beta.2] - 2026-03-27
### Fixed
- Plugin update badge and nag now appear automatically on the Plugins page without requiring a manual "Check for Updates" click — hooks `site_transient_update_plugins` (read filter) so WP-Cron is no longer required
### Changed
- Extracted version comparison into `is_newer_version()` private method — eliminates duplication between write and read filter paths

## [0.9.10-beta.1] - 2026-03-27
### Added
- Node.js install requirement added to MCP Setup Instructions (step 1) with link to nodejs.org/en/download
- Generated Claude config now uses site-specific server key `ds-toolkit-{site-slug}` (e.g. `ds-toolkit-my-site`) instead of the generic `ds-toolkit` — prevents conflicts when managing multiple sites

## [0.9.9.2] - 2026-03-26
### Added
- `list_menus`, `get_menu`, `set_menu_items`, `assign_menu_to_location` MCP tools — full menu management
- `flush_rewrite_rules`, `flush_cache`, `delete_transients`, `search_replace` MCP tools — maintenance operations
- `get_option`, `update_option` MCP tools — read/write wp_options (leagueapps-gated)
- `list_users`, `get_user`, `regenerate_thumbnails` MCP tools — user lookup and media
- `create_post`/`update_post` now accept `post_parent`, `slug`, `menu_order`, `page_template`, `post_author`, `comment_status`
- `get_post`/`list_posts` return `slug`, `post_parent`, `menu_order`; `list_posts` accepts `post_parent`, `orderby`, `order` filters
- Four new MCP access-control groups: Menus, Maintenance, Options, Users & Media
### Fixed
- Beta updater now correctly detects beta.N → beta.N+1 upgrades

## [0.9.9.2-beta.4] - 2026-03-26
### Fixed
- Beta updater now correctly detects beta.N → beta.N+1 upgrades (same base version, higher beta number) — PHP's `version_compare` is unreliable for this case

## [0.9.9.2-beta.3] - 2026-03-26
### Added
- `list_menus`, `get_menu`, `set_menu_items`, `assign_menu_to_location` MCP tools — full menu management (list, inspect, rebuild structure, assign to location)
- `flush_rewrite_rules`, `flush_cache`, `delete_transients`, `search_replace` MCP tools — maintenance operations; `search_replace` requires @leagueapps.com + `confirm: true`
- `get_option`, `update_option` MCP tools — read/write wp_options; restricted to @leagueapps.com
- `list_users`, `get_user` MCP tools — user lookup with role/search filters
- `regenerate_thumbnails` MCP tool — regenerate image sizes for Media Library attachments
- Four new MCP access-control groups: **Menus**, **Maintenance**, **Options**, **Users & Media** (all enabled by default)
### Changed
- `create_post`, `update_post` — now accept `post_parent`, `slug`, `menu_order`, `page_template`, `post_author`, `comment_status`
- `get_post` — now returns `slug`, `post_parent`, `menu_order`, `page_template`, `comment_status`, `author_id`
- `list_posts` — now returns `slug`, `post_parent`, `menu_order`; accepts `post_parent`, `orderby`, `order` filters

## [0.9.9.2-beta.2] - 2026-03-26
### Added
- `get_partner_settings` MCP tool — read all ACF Partner Settings (logo, email, phone, address, Facebook, Instagram, X, YouTube, LinkedIn, TikTok, LeagueApps)
- `update_partner_settings` MCP tool — update any partner fields by name; URL fields sanitized, logo accepts Media Library attachment ID

## [0.9.9.2-beta.1] - 2026-03-26
### Changed
- Updated MCP example prompts to showcase current feature set — content editing, taxonomy terms, BB layout switching, global colors, CSS editing, ACF field groups, ACF post types, and settings

## [0.9.9.1] - 2026-03-26
### Fixed
- WordPress "View version details" changelog tab now shows the full CHANGELOG.md history instead of only the latest GitHub release body

## [0.9.9] - 2026-03-26
### Added
- `DS_TOOLKIT_ADMIN_DOMAIN` constant — single place to configure the email domain gate; overridable via `wp-config.php`
- `bb_list_layout_templates` / `bb_apply_layout_template` MCP tools — switch Header Main, Footer Main or the front page to any DS Launchpad layout template
- ACF Field Group MCP tools: `acf_list_field_groups`, `acf_get_field_group`, `acf_create_field_group`, `acf_update_field_group`, `acf_delete_field_group`
- ACF Options Page MCP tools: `acf_list_options_pages`, `acf_create_options_page`, `acf_delete_options_page` (ACF Pro 6.2+)
- ACF Post Type & Taxonomy MCP tools: full CRUD via ACF Pro API
- LeagueApps email gate on all destructive/schema MCP tools

### Fixed
- `$is_beta` undefined variable in updater — beta-vs-stable comparison logic was silently never executing
- Extracted `leagueapps_gate()` helper — eliminates duplicated auth check blocks across toolkit settings, BB colors, and ACF schema tools

## [0.9.9-beta.4] - 2026-03-26
### Added
- `bb_list_layout_templates` MCP tool — list available DS Launchpad header/footer/home templates (Header Style 1–5, Footer Style 1–3, Home Page Layout 1–6)
- `bb_apply_layout_template` MCP tool — replace "Header Main" or "Footer Main" Themer layouts, or the site front page, with a DS Launchpad template; requires `confirm: true` as a destructive-action safeguard

## [0.9.9-beta.3] - 2026-03-26
### Added
- `acf_list_field_groups`, `acf_get_field_group`, `acf_create_field_group`, `acf_update_field_group`, `acf_delete_field_group` MCP tools — full CRUD for ACF Pro field groups (create with fields inline)
- `acf_list_options_pages`, `acf_create_options_page`, `acf_delete_options_page` MCP tools — manage ACF Pro options pages (requires ACF Pro 6.2+)

## [0.9.9-beta.2] - 2026-03-26
### Added
- `acf_list_post_types`, `acf_create_post_type`, `acf_update_post_type`, `acf_delete_post_type` MCP tools — full CRUD for ACF Pro post types
- `acf_list_taxonomies`, `acf_create_taxonomy`, `acf_update_taxonomy`, `acf_delete_taxonomy` MCP tools — full CRUD for ACF Pro taxonomies
- New **ACF Schema** toggle in MCP access controls (marked destructive, @leagueapps.com only)
### Security
- All destructive/schema tools (ACF Schema, BB colors, Toolkit Settings) now require `@leagueapps.com` email in addition to `manage_options`

## [0.9.9-beta.1] - 2026-03-26
### Added
- `get_bb_global_colors` MCP tool — read all Beaver Builder Global Style colors as a label → hex map
- `update_bb_global_colors` MCP tool — update named BB colors by label, flushes BB CSS cache automatically
- New **Beaver Builder** toggle in MCP access controls

## [0.9.8] - 2026-03-26
### Added
- `set_post_terms` MCP tool — assign or replace taxonomy terms on any post without editing content
- `terms` parameter on `create_post` and `update_post` — assign taxonomy terms in the same call as creating/updating a post
- `get_post` now returns current assigned terms grouped by taxonomy

## [0.9.7] - 2026-03-26
### Added
- Beta update channel — set `define( 'DS_TOOLKIT_UPDATE_CHANNEL', 'beta' )` in `wp-config.php` to receive pre-releases
- MCP per-group access controls — enable/disable Claude's access to Posts & Pages, CPTs, Taxonomies, ACF Fields, and Toolkit Settings
- New MCP tools: `list_post_types`, `list_taxonomies`, `list_terms`, `get_term`, `create_term`, `update_term`, `delete_term`, `get_post_fields`, `update_post_fields`
- ACF-aware field tools — uses `get_fields()` / `update_field()` when ACF active, falls back to `get_post_meta()`
### Changed
- All 365 university team logos converted from PNG to WebP (60% smaller)
### Fixed
- MCP config generator outputs correct `mcp-remote` stdio format for Claude Desktop
- MCP tab detects `WP_ENVIRONMENT_TYPE=local` and switches to `http://` URL for LocalWP
- Add `--allow-http` flag automatically when MCP URL is non-HTTPS
- "Check for Updates (beta channel)" button now correctly clears beta release cache and forces immediate update check

## [0.9.7-beta.1] - 2026-03-26
### Fixed
- "Check for Updates (beta channel)" button now correctly clears the beta release cache
- Force synchronous `wp_update_plugins()` before redirect so update appears immediately on plugins.php

## [0.9.6-beta.1] - 2026-03-26
### Changed
- Convert all 365 university team logos from PNG to WebP (60% size reduction, 16.4MB → 6.6MB)

## [0.9.5-beta.1] - 2026-03-25
### Added
- Beta update channel — set `define( 'DS_TOOLKIT_UPDATE_CHANNEL', 'beta' )` in `wp-config.php` to receive pre-releases
- "Check for Updates (beta channel)" label shown in plugin action links when on beta channel
- Separate transient cache per channel (`ds_toolkit_latest_release` vs `ds_toolkit_latest_release_beta`)

## [0.9.4] - 2026-03-24
### Added
- MCP per-group access controls — enable/disable Claude's access to Posts & Pages, CPTs, Taxonomies, ACF Fields, and Toolkit Settings independently from the MCP tab
- New MCP tools: `list_post_types`, `list_taxonomies`, `list_terms`, `get_term`, `create_term`, `update_term`, `delete_term`, `get_post_fields`, `update_post_fields`
- ACF-aware field tools — uses `get_fields()` / `update_field()` when ACF is active, falls back to `get_post_meta()`

## [0.9.3] - 2026-03-23
### Fixed
- Add `--allow-http` flag to mcp-remote args when MCP URL is `http://` (required for LocalWP environments)

## [0.9.2] - 2026-03-23
### Fixed
- MCP tab now detects `WP_ENVIRONMENT_TYPE=local` and switches URL to `http://` to avoid LocalWP SSL certificate issues with Node.js

## [0.9.1] - 2026-03-22
### Fixed
- Claude Desktop config generator now outputs correct `command/args` format using `mcp-remote` stdio proxy instead of unsupported `type: http` format

## [0.9.0] - 2026-03-22
### Added
- DS Toolkit MCP server — exposes WordPress as a self-contained MCP endpoint at `/wp-json/ds-toolkit/v1/mcp`
- MCP tab in admin settings with setup instructions, config generator, and available tools reference
- MCP tools: `list_posts`, `get_post`, `create_post`, `update_post`, `delete_post`
- Authentication via WordPress Application Passwords (WP 5.6+)

## [0.8.2] - 2026-03-15
### Changed
- Restrict DS Toolkit admin menu to `@leagueapps.com` email addresses only

## [0.8.1] - 2026-03-14
### Added
- `[child_pages]` shortcode — renders child pages as cards with configurable columns (desktop/tablet/mobile)

## [0.8.0] - 2026-03-10
### Added
- Global CSS tab — inject custom CSS sitewide with CodeMirror editor
- Global JS tab — inject custom JavaScript sitewide with CodeMirror editor

## [0.7.2] - 2026-03-05
### Changed
- Merged University Team Logo Finder into DS Toolkit as a dedicated tab (removed as standalone plugin)

## [0.7.1] - 2026-03-04
### Added
- All 365 university team logos
### Fixed
- Logo finder UI improvements

## [0.7.0] - 2026-03-03
### Added
- University Team Logo Finder tab — browse and import team logos into WP Media Library

## [0.6.6] - 2026-02-28
### Fixed
- `[getsubmenu]` shortcode — match original implementation behavior

## [0.6.5] - 2026-02-27
### Added
- `[getsubmenu]` shortcode — render submenus by location
- `[current_year]` shortcode
- Forminator Email Partner variable support

## [0.6.2] - 2026-02-20
### Fixed
- Feature defaults for existing installs (options not overwritten on update)

## [0.6.1] - 2026-02-18
### Added
- ACF Theme Options support in CSS Variables feature

## [0.6.0] - 2026-02-15
### Added
- Hide Beaver Builder Assistant feature

## [0.5.9] - 2026-02-10
### Changed
- Refactored to WordPress plugin standards and scalable class structure

## [0.5.8] - 2026-02-08
### Fixed
- Folder rename for fresh installs via Upload Plugin

## [0.5.7] - 2026-02-06
### Fixed
- Login logo only shown when Custom Login Branding is enabled

## [0.5.6] - 2026-02-04
### Fixed
- Updater folder detection and source renaming

## [0.5.5] - 2026-02-02
### Added
- Custom login logo picker via Media Library

## [0.5.4] - 2026-01-30
### Fixed
- Plugin folder naming via GitHub Actions zip

## [0.5.3] - 2026-01-28
### Changed
- Redesigned settings page UI

## [0.5.2] - 2026-01-26
### Added
- "Check for Updates" link on plugins page

## [0.5.1] - 2026-01-24
### Changed
- Moved DS Toolkit menu under Settings

## [0.4.2] - 2026-01-20
### Changed
- Enable login branding by default on activation

## [0.4.1] - 2026-01-18
### Changed
- Replaced PUC library with native WordPress updater (no external dependency)

## [0.4.0] - 2026-01-15
### Changed
- Stable release — removed auto-updater temporarily

## [0.2.1] - 2025-12-10
### Fixed
- Clean up `shop_name` key on uninstall

## [0.2.0] - 2025-12-05
### Added
- Login branding toggle in admin settings
- Custom login logo support
