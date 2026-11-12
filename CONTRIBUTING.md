# Contributing to DS Toolkit

Thanks for contributing. Please read this before opening a PR. **If you are using
Claude Code or another AI assistant to make changes, point it at this file first**:
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

To make it actually load, add **one entry** to `DS_Features::layout()` in
`includes/class-ds-features.php`, in the group where its card belongs:

```php
'my_feature_enabled' => self::feature( 'my-feature', 'DS_My_Feature', array(
    'default' => 0, 'label' => 'My Feature', 'icon' => 'admin-generic', 'scope' => 'fleet',
    'summary' => 'One sentence an editor can read.',
    'more'    => 'The detail, shown behind "More".',
    'fields'  => array( 'my_feature_limit' => array( 'type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100, 'label' => 'Limit' ) ),
) ),
```

That entry is the loader registration, the default, the validation on save and the settings card. There is
no second place to edit: the settings page is drawn from the schema (`admin/class-ds-settings-cards.php`).
The file's header lists every key an entry can carry and every field type.

1. **New optional features default to `0`** (off) unless they are a no-op compatibility patch.
2. **Run `php tests/features-schema-test.php`.** It pins the registry and the defaults to the last release
   (`tests/fixtures/features-1.17.4.json`). A new feature is a deliberate change: add it to the fixture and
   say why in its `_changes` list. It also fails on a summary that runs long.
3. **If it stores anything outside `ds_toolkit_settings`, or schedules a job:** add the option, transient
   prefix or cron hook to `includes/class-ds-lifecycle.php`, in the same change. Deactivation and uninstall
   work from those lists, and `tests/lifecycle-test.php` fails on a scheduled job the file does not know.
4. **A lock or a fresh read of an option** comes from `DS_Lock` (`includes/class-ds-lock.php`), not a new
   hand-written one.
5. **A host name, the staff email domain, the repository or "is this a development site"** comes from
   `DS_Env` (`includes/class-ds-env.php`). `tests/env-test.php` fails on a second copy.
6. **A Beaver Builder block** follows `docs/BUILDER-FORMS.md` (tab names, labels, which control for which
   need), and every `<button>` it prints carries `fl-content-ui-button`.

A feature class with no schema entry is dead code.

## Tests

`tests/run.sh` lints every PHP file and runs every `tests/*-test.php` (a test passes when it exits 0).
Tests that need a running WordPress say `@requires wordpress` in their header and run with
`tests/run.sh --wp "<wp-cli command for a test site>"`. A change in behaviour comes with a test that fails
without it.

## Code conventions

- Guard every PHP file with `if ( ! defined( 'ABSPATH' ) ) exit;`.
- Escape all output (`esc_html`, `esc_url`, `esc_attr`, `wp_kses_*`).
- Settings live in the single `ds_toolkit_settings` option array. State that is not a setting (a cache, a
  lock, a run record) gets its own option, not autoloaded, and is listed in `includes/class-ds-lifecycle.php`.
- No inline `style=` attributes in the admin views: add a class to `assets/css/admin.css`
  (`tests/admin-views-test.php` fails on a new one).
- End files with a single trailing newline, no trailing whitespace.

## Branch, version, and release workflow

- Branch off `main` with a prefix: `feat/`, `fix/`, `perf/`, `refactor/`,
  `docs/`, or `chore/`. Do not push from your fork's `main`.
- Add a `CHANGELOG.md` entry under a new version heading.
- Bump `DS_TOOLKIT_VERSION` in **both** places in `ds-toolkit.php` (the header
  comment and the `define()`), as the **final commit** on your branch.
- In that same final commit, regenerate `includes/release-hashes.txt`
  (`designshophq/tools/release/release-hashes.sh <plugin-dir>`). Every site checks
  its own toolkit folder against it; `sign-release.sh` refuses a release whose list
  does not match its PHP files.
- Open the PR into `main`. Maintainers squash-merge; publishing a GitHub Release
  builds the zip. Sites are offered the update only once the release is signed
  (`designshophq/tools/release/sign-release.sh <version>`): since 1.11.0 every install
  path checks the signature and refuses an unsigned or altered zip.

### Version numbers: MAJOR.MINOR.PATCH (from 1.11.0)

- **PATCH** (1.11.0 to 1.11.1): bug and security fixes, wording, docs. No new setting,
  module or option. Saved pages look and behave the same, apart from the bug.
- **MINOR** (1.11.4 to 1.12.0, PATCH back to 0): something new that changes nothing
  already on a page: a module, a feature switch, a setting, a preset, a new default
  for newly added blocks, a tightened permission that only refuses what should never
  have been allowed. A release that mixes fixes and new things is MINOR.
- **MAJOR** (1.x to 2.0.0): a change existing sites have to act on: a module removed
  while pages still use it, a setting renamed or removed, saved data reshaped, a higher
  minimum PHP or WordPress.
- Pre-releases are `1.12.0-rc.1`: unsigned, offered only to local and staging sites
  (the beta channel), never to production.
- Before 1.11.0 every release raised the third number, new modules included (1.10.41
  added two). Read old version numbers with that in mind.

## Architecture in one paragraph

`ds-toolkit.php` loads `DS_Env` (names and the development-site rule), the feature schema `DS_Features`
and `DS_Toolkit` (`includes/class-ds-toolkit.php`). `DS_Toolkit::run()` starts Bot Shield first, then walks
`DS_Features::registry()` in order: a feature class is loaded and `init()`-ed only if its settings key is
on and the site's blueprint generation allows it. The settings page (drawn from the same schema) and the
MCP endpoint are always loaded and are limited to Design Shop staff: an administrator whose email ends in
the staff domain. Features are opt-in, schema-driven, and share one settings option.
