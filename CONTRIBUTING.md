# Contributing to DS Toolkit

Thanks for contributing. Please read this before opening a PR. **If you are using
Claude Code or another AI assistant to make changes, point it at this file first** —
most rejected PRs fail the feature-wiring checklist below, not code review.

## The one rule that trips people up

Dropping a class file in `features/` does **not** make a feature work. The class is
never instantiated unless it is registered. A PR that adds only the feature file is
incomplete and will not be merged as-is.

## Adding a feature (complete checklist)

A feature is a class in `features/class-ds-<name>.php` with this exact shape:

```php
class DS_<Name> {
    private $settings;
    public function __construct( $settings = array() ) { $this->settings = $settings; }
    public function init() {
        // add_action / add_shortcode / etc.
    }
}
```

To make it actually load, **all** of these are required in the same PR:

1. **Register it.** Add one entry to `$features[]` in
   `includes/class-ds-toolkit.php` (key = settings flag, value = file + class).
2. **Give it a default.** Add the settings key to `get_defaults()` in the same
   file. New optional features default to `0` (off) unless they are a no-op
   compat patch.
3. **Make it toggleable.** Add a toggle card to
   `admin/views/page-settings.php` and initialize the matching `$<key>` variable
   in `DS_Toolkit_Admin::render_page()` (`admin/class-ds-toolkit-admin.php`).
   Without this the feature can never be turned on.
4. **If it creates DB rows or cron events:** extend `uninstall.php` to remove
   them, and add schema install to `DS_Toolkit::activate()`.

A feature missing steps 1-3 is dead code. The PR checklist enforces this.

## Code conventions

- Guard every PHP file with `if ( ! defined( 'ABSPATH' ) ) exit;`.
- Escape all output (`esc_html`, `esc_url`, `esc_attr`, `wp_kses_*`).
- All settings live in the single `ds_toolkit_settings` option array. Do not add
  new `wp_options` rows.
- End files with a single trailing newline, no trailing whitespace.

## Branch, version, and release workflow

- Branch off `main` with a prefix: `feat/`, `fix/`, `perf/`, `refactor/`,
  `docs/`, or `chore/`. Do not push from your fork's `main`.
- Add a `CHANGELOG.md` entry under a new version heading.
- Bump `DS_TOOLKIT_VERSION` in **both** places in `ds-toolkit.php` (the header
  comment and the `define()`), as the **final commit** on your branch.
- Open the PR into `main`. Maintainers squash-merge; publishing a GitHub Release
  builds and ships the update to live sites via the auto-updater.

## Architecture in one paragraph

`ds-toolkit.php` boots `DS_Toolkit` (`includes/class-ds-toolkit.php`), which holds
the feature registry, the defaults, and conditional instantiation: each feature
class is loaded and `init()`-ed only if its settings key is truthy. The admin
settings page and MCP endpoint are always loaded but restricted by email domain.
That is the whole model — features are opt-in, registry-driven, and single-option.
