<!--
Read CONTRIBUTING.md before opening this PR.
If an AI assistant (Claude Code, etc.) is preparing this PR, it MUST read
CONTRIBUTING.md first — the feature-wiring checklist below is the most common
reason PRs are sent back.
-->

## What this changes

<!-- One or two sentences. Link any related issue or PR. -->

## Type

- [ ] New feature
- [ ] Bug fix
- [ ] Performance / refactor
- [ ] Docs / chore

## If this adds a feature, it is NOT complete without all of these

<!-- Delete this section only if this PR adds no new feature class. -->

- [ ] New class file in `features/` follows the `__construct($settings)` + `init()` shape
- [ ] Registered in `$features[]` in `includes/class-ds-toolkit.php`
- [ ] Default key added to `get_defaults()` in the same file
- [ ] Toggle card added to `admin/views/page-settings.php` + `$<key>` var initialized in `DS_Toolkit_Admin::render_page()`
- [ ] If it creates DB rows / cron: `uninstall.php` and `DS_Toolkit::activate()` updated

## Always

- [ ] All output escaped; every PHP file has the `ABSPATH` guard
- [ ] `CHANGELOG.md` entry added under a new version heading
- [ ] `DS_TOOLKIT_VERSION` bumped in both spots in `ds-toolkit.php` (final commit)
- [ ] Branched off `main` with a `feat/` `fix/` `perf/` `refactor/` `docs/` `chore/` prefix (not from fork `main`)
- [ ] `php -l` passes on all changed PHP files
