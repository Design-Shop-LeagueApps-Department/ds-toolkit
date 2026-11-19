# Site layouts (1.20.0)

A **site layout** is an extra style for a LeagueApps block that lives in the site's theme, not in the plugin. It is
how a build gets a one-off hero without hand-written hooks: drop files over SSH, pick the style in the builder, and
the partner still edits everything in the block's settings.

Today the **Hero Banner** (`ds-hero`) reads site layouts. The registry (`includes/class-ds-site-layouts.php`) is
block-agnostic, so Marquee, Post Loop and CTA can follow with one `hook_form()` line and a render branch each.

## Files

Put them in the child theme (a parent-theme copy is used when the child has none):

| File | Required | What it is |
|---|---|---|
| `ds-layouts/ds-hero/<slug>.php` | yes | The markup. `<slug>` = lowercase letters, numbers, dashes. |
| `ds-layouts/ds-hero/<slug>.json` | no | The layout's own settings, as Beaver Builder fields. |
| `ds-layouts/ds-hero/<slug>.css` | no | Loaded in the head, only on pages that use the layout. |
| `ds-layouts/ds-hero/<slug>.js` | no | Loaded in the footer, only on pages that use the layout. |

The style appears in **Hero Style** as **Site: \<Layout Name\>**. A worked example is in
`docs/site-layouts/ds-hero/` (Split Photo): copy its three files into the theme to try it.

## Header comment (read without running the file)

```php
/**
 * Layout Name: Split Photo
 * Description: Shown as help above the layout's own settings.
 * Uses: text, seo, buttons, bg, spacing      <- the Hero sections that stay visible (default: these five)
 * Hide: kenburns, slide_nav                   <- Hero fields hidden inside those sections
 */
```

Hero section keys you can list in **Uses**: `text`, `seo`, `buttons`, `bg`, `stats`, `layout`, `overlay`, `colors`,
`typography`, `buttons_style`, `effects`, `spacing`. Unknown names are ignored.

## The layout's own settings (`<slug>.json`)

```json
{ "sections": [ { "key": "split", "title": "Split Photo", "fields": {
    "frame": { "type": "select", "label": "Photo frame", "default": "card", "options": { "card": "Card", "square": "Square" } },
    "badge": { "type": "text", "label": "Badge text" }
} } ] }
```

Allowed field types: text, textarea, editor, select, photo, multiple-photos, video, color, unit, link, icon,
typography, dimension, align. Allowed keys: type, label, default, options, help, description, placeholder, multiple,
show_remove, show_reset, show_alpha, units, slider, responsive, rows, media_buttons, connections, show_target,
show_nofollow. Anything else is dropped. Values are saved as `sl_<slug>__<name>` so they never collide with the
Hero's own fields.

## Inside the template

| Variable | What |
|---|---|
| `$fields` | This layout's values by their JSON names, defaults filled in (`$fields['badge']`). |
| `$settings` | Every Hero setting (heading, buttons, background, colours...). |
| `$module` | The Hero Banner. `$module->part( $name )` returns a ready piece: `open` (the `<section>`, add classes as the 2nd argument), `close`, `background` (image / slideshow / video / mixed + Overlay), `slide_nav`, `eyebrow`, `heading` (with `{a}accent{/a}`), `text`, `buttons`, `stats`. `$module->photo_urls( $size )` = the Background photos as URLs. |
| `$layout` | The header data (label, description, uses, hide, file). |

Escape your own output as in any theme template (`esc_html`, `esc_url`, `esc_attr`, `wp_kses_post`).

## Safety

- Layout files come from the server only. Nothing is typed into the builder.
- If the file is missing (theme switched, renamed) or throws an error, the Hero renders **Style 1: Classic** instead,
  and an administrator sees an HTML comment naming the error. A page is never left blank or broken.
- Keep partner-editable content in fields (Hero or JSON). No HTML modules, no baked text in images.
