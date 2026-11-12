# Builder forms: one way to do each thing

The settings panel of every DS Toolkit block should read as one product. This is the house style for a
module's `FLBuilder::register_module()` form. New fields follow it; an existing field is brought in line when
its module is next edited. A saved setting key never changes for the sake of style: labels, order and control
type can move, keys cannot (hundreds of live pages hold them).

Counts below were measured on the 20 modules of 1.17.4.

## Tabs

| Rule | Why |
|---|---|
| The first tab holds what the block shows and is titled **Content**. A block that lists live data names the tab after the data instead (Programs, Calendar, Roster, Table). | 11 modules already say Content; "General" (4 modules) says nothing about what is inside. |
| The look goes in a tab titled **Style**. Type settings go in a **Typography** section of Style, not a tab of their own, unless the block has more than about ten of them. | Editors look for colour and spacing in the same place in every block. |
| Never point an editor to a tab that is hidden for the layout they chose. A hint names the tab and the section as they are spelled on screen. | Two Post Loop hints sent editors to a hidden Query tab (fixed in the 2026-11 audit). |

## Labels and help text

| Rule | Example |
|---|---|
| British spelling in labels, as the team writes: **Colour**, **Centre**. | 155 labels say Colour, 40 still say Color (CTA, Divider, Menu, Post Loop, Social). |
| Title Case for labels, sentence case for help and descriptions. | `Button Text`; "Blank hides the button." |
| Help says what happens, in the editor's words. No class names, hook names, ticket numbers or version numbers. | "Shown instead of the image when set." |
| A field that only applies to some layouts is hidden for the others (`toggle` on the layout field), not explained away in help. | |
| Empty-state messages (shown only in the builder) say where to add the content: tab, then section. | "No sponsors yet. Add them on the Content tab, under Sponsors (manual list)." |

## Controls

| Need | Control | Notes |
|---|---|---|
| On or off | `select` with `no` then `yes`, labels **No** and **Yes** | The existing blocks use a select for this (45 one-line Yes/No selects, no button groups). Default is the first option unless the feature should start on. Stored values are the strings `yes` and `no`. |
| Show or hide one part | `select` with `show` then `hide` | Same reason. |
| One of two to four short choices that are worth seeing side by side (alignment, a view) | `button-group` | Not for Yes/No. |
| Several of a few choices | `button-group` with `multi-select` | Stored as an array or a comma string: read it with a helper that accepts both. Never a `select` with `multi-select` (it draws a list box where a click replaces the selection). |
| A colour | `color` with `show_reset`, `show_alpha` when transparency makes sense, and `'connections' => array( 'color' )` | 288 of 299 colour fields already do. A blank colour means "from the preset or the theme": never save a default that pins it. |
| A size | `unit` with a `slider` and `'description' => 'px'` | Add `'responsive' => true` when the value should differ by screen size, and emit it through the shared responsive emitter so the site's own breakpoints (Large included) are used. |
| A list of items | `form` field with `multiple` and a `preview_text` | The sub-form is registered with `FLBuilder::register_settings_form()`. |
| A value read from the site (post types, LeagueApps sites, seasons) | `select` filled by a static helper | Wrap the lookup in `DS_Module_UI::form_shown()` so it runs only when the builder is open. |

## Defaults and presets

- A new option that changes how existing blocks look is a new preset or a new value, with the old one kept as
  what saved blocks hold. Prove it by rendering a saved block before and after: the CSS must be byte-identical.
- A preset is a base layer: a blank field takes the preset value, a set field wins.
- Colours default to the site's Global Styles (`var(--fl-global-...)`) or to blank, never to a hex that belongs
  to one brand.

## Buttons inside a block

Every `<button>` a module prints carries the class `fl-content-ui-button`. Beaver Builder's Global Styles
button rule skips that class; without it the site's global button colours paint the control and the block's
own Style fields stop working (found on the Programs block, 1.16.0).

## Checking a form change

1. Open the block in the builder on a test site: every tab opens, no field is missing, a hidden section stays
   hidden for the layouts that do not use it.
2. Save without changing anything and compare the saved settings before and after: nothing may change.
3. Render a page that already holds the block: the HTML and CSS must match the previous build unless the
   change is the point of the release.
