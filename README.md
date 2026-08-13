# DS Toolkit

A professional WordPress plugin for Design Shop (LeagueApps) builds. Bundles all recurring custom features — shortcodes, admin tools, branding, CSS/JS management, and a built-in MCP server so Claude can communicate directly with WordPress — into a single, self-updating plugin.

Visible only to `DS_TOOLKIT_ADMIN_DOMAIN` users in WP Admin (default: `@leagueapps.com`).

## Author
Alipio Gabriel

---

## Requirements
- WordPress 5.8+
- PHP 7.4+
- HTTPS (live sites) — or `define('WP_ENVIRONMENT_TYPE', 'local')` for LocalWP
- Node.js / npx — required on developer machines for the Claude Desktop MCP connection

## Installing on a new site

Upload the release zip, then **load the Plugins page before clicking Activate.**

Activating straight from the upload screen can fail with:

> The plugin does not have a valid header.

That is a WordPress race, not a broken package. WordPress calls `get_plugins()` early in
the install request and caches the result for that request; if the snapshot was taken
before the new folder landed, the activation later in the *same* request consults the
stale list, does not find `ds-toolkit/ds-toolkit.php`, and reports a missing header. The
files are complete and correct — the next request rescans and activation succeeds.

Nothing in the plugin can prevent it: `validate_plugin()` runs before any plugin code
loads, so there is no hook available at that point. Seen on Flywheel (bluespringsbaseball,
2026-08-13), where the plugin folder, permissions, header and byte count were all verified
correct while the error was showing.

If it persists *after* a Plugins-page visit, then it is a real problem — check for a folder
not named `ds-toolkit`, a truncated upload, or permissions stopping PHP reading the header.

---

## Plugin Structure
```
ds-toolkit/
├── ds-toolkit.php                          — Entry point, constants, bootstrap
├── uninstall.php                           — Cleanup on deletion
├── CHANGELOG.md                            — Full version history
├── includes/
│   ├── class-ds-toolkit.php                — Core loader, feature registry, defaults
│   ├── class-ds-toolkit-updater.php        — GitHub Releases auto-updater
│   └── defaults/
│       ├── global-css.css                  — Default LaunchPad 4 CSS baseline
│       └── global-js.js                    — Default SiteUI JS (sticky header, etc.)
├── admin/
│   ├── class-ds-toolkit-admin.php          — Admin menu, tabs, settings registration
│   ├── class-ds-logo-finder.php            — University logo finder AJAX + assets
│   ├── class-ds-mcp-server.php             — MCP JSON-RPC server (REST endpoint)
│   └── views/
│       ├── page-settings.php               — Features tab
│       ├── page-logo-finder.php            — University Logo Finder tab
│       ├── page-global-css.php             — Global CSS tab
│       ├── page-global-js.php              — Global JS tab
│       └── page-mcp.php                    — MCP tab (config generator + tools reference)
├── features/
│   ├── class-ds-login-branding.php         — Custom WP login page branding
│   ├── class-ds-hide-fl-assistant.php      — Hide BB FL Assistant for non-LeagueApps users
│   ├── class-ds-acf-css-vars.php           — ACF options fields → CSS custom properties
│   ├── class-ds-getsubmenu.php             — [getsubmenu] shortcode
│   ├── class-ds-current-year.php           — [current_year] shortcode
│   ├── class-ds-forminator-email-partner.php — Forminator {email_partner} variable
│   ├── class-ds-global-css.php             — Injects Global CSS into <head>
│   ├── class-ds-global-js.php              — Injects Global JS before </body>
│   └── class-ds-child-pages.php            — [child_pages] + [get_parent_page_title] shortcodes
└── assets/
    ├── css/
    │   ├── admin.css                        — All admin UI styles
    │   ├── logo-finder.css                  — Logo finder grid + import bar
    │   ├── child-pages.css                  — Responsive card grid
    │   └── login.css                        — Custom login page styles
    ├── js/
    │   ├── admin.js                         — Admin toggles, media picker, mapping table
    │   └── logo-finder.js                   — Live search, multi-select, AJAX import
    └── images/
        ├── cropped-LA-circle-logo-1.png     — Default login logo
        └── team_logos/                      — 365 university team logo WebPs
```

---

## Configuration

### Admin Domain Gate
The DS Toolkit admin menu and all destructive MCP tools are restricted to users whose email matches `DS_TOOLKIT_ADMIN_DOMAIN`. Override in `wp-config.php` if needed:

```php
define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@yourdomain.com' );
```

Default: `@leagueapps.com`

### Beta Update Channel
Beta updates are enabled automatically on local environments — no configuration needed.

| Environment | Channel |
|---|---|
| `*.local`, `localhost`, `127.x`, `192.168.x` | Beta (automatic) |
| `WP_ENVIRONMENT_TYPE = local / development / staging` | Beta (automatic) |
| Live domains / WP Engine (`WP_ENVIRONMENT_TYPE = production`) | Stable only |

---

## Features

### LeagueApps Custom Login Branding
Replaces the default WordPress login page with LeagueApps / Design Shop branding.
- Custom logo (default: LeagueApps circle logo, or pick any image from Media Library)
- "Powered by LeagueApps Design Shop" tagline
- Logo links to home URL; "Need help? Visit Design Shop Academy" link below the form
- Toggle and logo picker under **Settings → DS Toolkit → Features**

### Hide Beaver Builder FL Assistant
Hides the Beaver Builder Cloud / FL Assistant toolbar button for anyone not logged in with a `DS_TOOLKIT_ADMIN_DOMAIN` email — keeps the admin bar clean for clients.

### ACF Theme Options → CSS Variables
Maps ACF options page fields to CSS custom properties output in `:root` on every front-end page.
- Add/remove mappings via the dynamic table in the Features tab
- Optional fallback value per mapping
- Default mapping: `header_scrolled_bar_color` → `--header-scrolled-bar-color`

### [getsubmenu] Shortcode
Outputs a navigation list of child pages or sub-menu items.
```
[getsubmenu listfrom="About Us" mode="pages"]
[getsubmenu listfrom="Main Menu" mode="menus"]
```
- `mode="pages"` — lists published child pages
- `mode="menus"` — lists direct children of a nav menu item

### [current_year] Shortcode
Outputs the current 4-digit year. Auto-updates — no maintenance needed.
```
© [current_year] LeagueApps
```

### Forminator {email_partner} Variable
Injects a custom `{email_partner}` merge tag into Forminator forms, resolved from the ACF `partner_email` options field. Configurable fallback email in the Features tab.

### Global CSS
Site-wide CSS injected into `<head>` at priority 99. Full LaunchPad 4 baseline pre-loaded on activation. All values use `--dst-*` CSS custom properties so any value can be overridden without editing the plugin file.

- **Plugin-managed** — CSS is maintained in `includes/defaults/global-css.css`; updates take effect on next page load automatically
- **CSS Variable Overrides** — enter custom CSS in **Settings → DS Toolkit → Global CSS** to override any `--dst-*` variable or add site-specific rules; overrides are stored in the database and survive every plugin update
- The tab shows a grouped utility class reference with descriptions; full CSS source is shown read-only at the bottom

### Global JS
Site-wide JavaScript injected before `</body>` at priority 99.

- **Plugin-managed** — JS is maintained in `includes/defaults/global-js.js`; updates take effect on next page load automatically
- Features: sticky header (`.sticky-row`), clickable columns (`.column-link`), equal heights (`same_height_*`), button normaliser (`.uabb-button → .fl-button`)
- The tab shows feature documentation with usage examples; full JS source is shown read-only at the bottom; call `window.equalizeHeightsRefresh()` from custom JS to re-run equalisation after dynamic content loads

### [child_pages] Shortcode
Renders child pages of the current page as a responsive card grid using a Beaver Builder saved layout template.
```
[child_pages]
[child_pages template="56369" columns="4" columns_tablet="2" columns_mobile="1"]
```

### University Logo Finder
Browse and import 365 university team logos (WebP) directly into the WordPress Media Library. Live search, multi-select, AJAX import with duplicate detection.

---

## MCP Server (Claude ↔ WordPress)

DS Toolkit registers a [Model Context Protocol](https://modelcontextprotocol.io) endpoint so Claude can read and edit WordPress content without any additional plugins.

**Endpoint:** `https://yoursite.com/wp-json/ds-toolkit/v1/mcp`
**Auth:** WordPress Application Passwords (WP 5.6+ built-in)
**Protocol:** MCP JSON-RPC 2.0 (`2024-11-05`)

Each tool group can be independently enabled/disabled from **Settings → DS Toolkit → MCP**.

### Available Tools

#### Posts & Pages / Custom Post Types
| Tool | Description |
|---|---|
| `list_posts` | List posts, pages, or CPT entries with filters (type, status, search) |
| `get_post` | Get full content + assigned taxonomy terms by post ID |
| `create_post` | Create a post/page/CPT entry; optionally assign taxonomy terms and featured image inline |
| `update_post` | Update title, content, excerpt, status, taxonomy terms, or featured image (`thumbnail_id`) |
| `delete_post` | Trash or permanently delete a post |
| `list_post_types` | Discover all registered public post types |
| `bulk_create_posts` | Create multiple posts in a single call — accepts an array of post objects |
| `bulk_update_posts` | Update multiple posts in a single call — accepts an array of post objects with `id` |

#### Taxonomies
| Tool | Description |
|---|---|
| `list_taxonomies` | List all public taxonomies and their associated post types |
| `list_terms` | List terms in a taxonomy with search and pagination |
| `get_term` | Get a single term by ID |
| `create_term` | Create a new term in a taxonomy |
| `update_term` | Update a term's name, slug, or description |
| `delete_term` | Delete a term |
| `set_post_terms` | Assign or replace taxonomy terms on any post |

#### ACF / Custom Fields
| Tool | Description |
|---|---|
| `get_post_fields` | Read all ACF (or custom) meta fields for a post |
| `update_post_fields` | Write ACF (or custom) meta fields for a post |

#### Toolkit Settings — `manage_options` + `DS_TOOLKIT_ADMIN_DOMAIN`
| Tool | Description |
|---|---|
| `get_toolkit_settings` | Read all DS Toolkit feature settings |
| `update_toolkit_settings` | Change feature toggles, CSS, JS, columns, etc. |

#### Menus
| Tool | Description |
|---|---|
| `list_menus` | List all nav menus with their IDs, slugs, item counts, and assigned theme locations |
| `get_menu` | Get a menu and its full item list (title, URL, parent, order, object type) |
| `set_menu_items` | Replace all items in a menu with a new structure. Requires `confirm: true` |
| `assign_menu_to_location` | Assign a menu to a registered theme location |

#### Maintenance
| Tool | Description |
|---|---|
| `flush_rewrite_rules` | Flush WordPress rewrite rules — fixes 404s after adding CPTs |
| `flush_cache` | Flush the WordPress object cache |
| `delete_transients` | Delete all transients from the options table |
| `search_replace` | Search and replace text in the database. Requires `confirm: true` + `DS_TOOLKIT_ADMIN_DOMAIN` |

#### Options — `manage_options` + `DS_TOOLKIT_ADMIN_DOMAIN`
| Tool | Description |
|---|---|
| `get_option` | Read a WordPress option by key |
| `update_option` | Update a WordPress option by key |

#### Users & Media
| Tool | Description |
|---|---|
| `list_users` | List users filtered by role, search, with pagination |
| `get_user` | Get a user's profile by ID or email |
| `list_media` | List Media Library items with filters (type, search, pagination) |
| `get_media` | Get a single media item's details, URLs, and metadata by ID |
| `regenerate_thumbnails` | Regenerate image thumbnail sizes for Media Library images |

#### Beaver Builder — `manage_options`
| Tool | Description |
|---|---|
| `get_bb_global_colors` | Read all BB Global Style colors as a label → hex map |
| `update_bb_global_colors` | Update named BB colors by label; flushes BB CSS cache automatically |
| `bb_list_layout_templates` | List DS Launchpad layout templates (Header Style 1–5, Footer Style 1–3, Home Page Layout 1–6) |
| `bb_apply_layout_template` | Replace Header Main, Footer Main (Themer layouts), or the site front page with a DS Launchpad template. Requires `confirm: true` |

#### ACF Schema — `manage_options` + `DS_TOOLKIT_ADMIN_DOMAIN` + ACF Pro required
| Tool | Description |
|---|---|
| `acf_list_post_types` | List all ACF-managed post types |
| `acf_create_post_type` | Create a new ACF post type |
| `acf_update_post_type` | Update an existing ACF post type |
| `acf_delete_post_type` | Permanently delete an ACF post type |
| `acf_list_taxonomies` | List all ACF-managed taxonomies |
| `acf_create_taxonomy` | Create a new ACF taxonomy |
| `acf_update_taxonomy` | Update an existing ACF taxonomy |
| `acf_delete_taxonomy` | Permanently delete an ACF taxonomy |
| `acf_list_field_groups` | List all ACF field groups |
| `acf_get_field_group` | Get a field group and all its fields |
| `acf_create_field_group` | Create a field group with optional inline field definitions |
| `acf_update_field_group` | Update a field group's title, location, position, or active state |
| `acf_delete_field_group` | Permanently delete a field group and all its fields |
| `acf_list_options_pages` | List all ACF Pro options pages (ACF Pro 6.2+) |
| `acf_create_options_page` | Create a new ACF Pro options page |
| `acf_delete_options_page` | Permanently delete an ACF Pro options page |

### Claude Desktop Setup

```json
{
  "mcpServers": {
    "ds-toolkit": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://yoursite.com/wp-json/ds-toolkit/v1/mcp",
        "--header",
        "Authorization:Basic <base64 of username:app_password>"
      ]
    }
  }
}
```

Use the **MCP tab** in Settings → DS Toolkit for a guided setup with an auto-generated config snippet.

---

## Auto-Updates via GitHub
Checks GitHub Releases every 12 hours via native WordPress update hooks. Update prompt appears in **WP Admin → Plugins** like any other plugin — no Composer, no external libraries.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.
