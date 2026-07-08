# Changelog

All notable changes to DS Toolkit are documented here.

---

## [1.9.21] - 2026-07-10
### Changed
- **Admin toolbar: content actions moved to the end.** Final order after the site name: **Theme Setting · DS Toolkit · (Yoast) · + New · Edit Page · Beaver Builder** — the editing trio grouped at the last part of the bar.
- **Toolbar customisations hard-locked to Launchpad 6.** All toolbar changes (quick links, declutter, reorder) were already blueprint-6 gated via the feature registry; the class now also refuses to run below blueprint 6 outright, so older installs can never receive them.

## [1.9.20] - 2026-07-10
### Changed
- **Description / body textareas are now WYSIWYG editors** across the LeagueApps modules (like the Program card in 1.9.12): CTA bento-cell Description + Header Description, Post Loop Sponsor Description, Hero Subtext + Banner Subtitle, and the Heading module Description. All render sanitised HTML (`wp_kses_post`, auto-paragraphs). **Heading fields intentionally stay plain textareas** — a visual editor injects `<p>` wrappers, which would corrupt the `<h1>/<h2>` markup; they keep their `{a}` / `{outline}` tokens and line-break support.

## [1.9.19] - 2026-07-10
### Added
- **Outline Text.** Wrap a word in `{outline}…{/outline}` in any LeagueApps module heading (Hero, Heading, CTA, Post Loop, Org Stats) and it renders **transparent with a stroked border** (`-webkit-text-stroke`). The site-wide default (stroke colour + width) lives in **Theme Setting → General → Outline Text** (blank colour = the text's own colour as the stroke); each module's Style tab gains **Outline Text Colour / Width** overrides (blank = theme default, via CSS variables).
- **CTA — Card Shadow.** New Style option (None / Soft / Medium / Strong) that shadows the cards / tiles / cells of whichever CTA style is selected.
- **Theme Setting — Title Colour (no image).** Companion to "Title Colour (on photo)": sets the banner title colour for pages whose banner has **no** photo/video. (Fleet report: the photo colour was working — verified in the served CSS — but the tested page had a no-image banner, which had no control until now.)

## [1.9.18] - 2026-07-09
### Changed
- **Theme Setting — accordion renamed "Page Banner Background (No Image)" → "Page Banner".** The section now covers all banner behaviour (Featured Image Banner per post type, Title on Photo Banners + colour, and the no-image background), so the old name no longer fit. The background controls sit under a "Background (when the banner has no image)" sub-heading; the Hero module's help text referencing the old name was updated too. Settings and stored values are unchanged.

## [1.9.17] - 2026-07-09
### Fixed
- **Menu — nested submenu overlap.** Two flyout bugs on deep dropdowns (e.g. a "More" item near the right edge): the nested flyout anchored to the **top of the whole panel** instead of its own row (submenu items were never positioned), and it always opened **rightward**, clipping off-screen / overlapping near the viewport edge. Flyouts now align to their row, and a lightweight edge check flips any dropdown or flyout to open **leftward** when it would overflow the right edge. Desktop only; the mobile overlay is untouched.

## [1.9.16] - 2026-07-09
### Added
- **Theme Setting — Title on Photo Banners.** In General → Page Banner Background (No Image): choose whether page banners that **have** a photo/video show the auto page title (**Show** / **Hide** for an image-only banner), plus an optional **Title Colour (on photo)** (blank = the default light title). Site-wide defaults; the Hero Banner module can still override both per page.

### Changed
- **Admin toolbar order.** The content actions now sit side by side: **+ New · Edit Page · Beaver Builder**, followed by **Theme Setting · DS Toolkit** (other nodes such as Yoast move after).

## [1.9.15] - 2026-07-09
### Changed
- **Admin toolbar decluttered.** The toolbar quick-links feature now also removes the **Customize** link (the fleet styles through Theme Setting / Beaver Builder, not the Customizer), the **updates counter**, and the **WP Engine Quick Links** menu. Capabilities are untouched — those screens stay reachable by URL; only the toolbar noise goes.

## [1.9.14] - 2026-07-09
### Added
- **Theme Setting — Featured Image Banner control.** New checkbox list inside **General → Page Banner Background (No Image)**: which content types may use their **Featured Image** as the banner photo on their single pages. Unchecked types always show the **text-only banner** over the No-Image background (colour / pattern) configured right below it. **Staff is off by default** — portrait headshots crop badly in a wide banner, so staff singles now show a clean heading banner automatically (no setting change needed). Existing behaviour for pages, posts, teams, etc. is unchanged.

## [1.9.13] - 2026-07-09
### Changed
- **Images/Videos Carousel — Auto Scroll is now a seamless infinite loop.** Instead of stepping card-by-card and visibly rewinding to the start, the Reels Strip now drifts continuously marquee-style: the card set is cloned and the scroll position wraps invisibly, so it loops forever in one direction. The interval setting is now **Speed** (seconds per card; lower = faster). Still pauses on hover / touch / focus and off-screen, arrows still work (drift pauses briefly after a manual step), and it stays off for reduced-motion visitors.

## [1.9.12] - 2026-07-09
### Fixed
- **Program cards — buttons now truly bottom-anchor.** The 1.9.10 `margin-top:auto` was correct but the button sits inside `.ds-program-body`, which only sized to its content, so there was no space to push into. The body now stretches to fill the card (`flex:1 1 auto`), and buttons line up across a Same Height row for real this time. Verified against the live markup on the fleet test site.

### Changed
- **Program card Description is now a WordPress WYSIWYG editor** (like the Beaver Builder Text Editor field) instead of a plain textarea, and it renders as real HTML (`wp_kses_post`, auto-paragraphs). Typed markup in existing descriptions (e.g. `<br>`, `<b>…</b>`) now renders instead of printing literally.

## [1.9.11] - 2026-07-09
### Added
- **Menu — Item Divider.** New Style → Menu Bar option: a small vertical divider between top-level items (Solid / Dashed / Dotted, with colour, height, and width controls; colour defaults to the global line colour). Rendered as a real flex item, so it centres correctly for **every alignment including Justify**. Desktop bar only (hidden in the mobile overlay) and never shown before the CTA button.

## [1.9.10] - 2026-07-09
### Fixed
- **Post Loop — Program card buttons now anchor to the card bottom.** With Same Height on, the Learn More / CTA button sat directly under the text, so buttons across a row didn't line up. The button now pins to the bottom of the card (`margin-top:auto` in the card's flex column), so all buttons align horizontally regardless of text length. No change for cards without Same Height.

## [1.9.9] - 2026-07-08
### Fixed
- **"Match site Button" now truly matches — everywhere, including borders and shadow.** Root cause found on a live fleet test (risevolleyball): with Button Shape = **Default**, the Theme Setting emitted no shape CSS at all, so the blueprint's decorative clip-path baked into module buttons (e.g. the Post Loop "See all" parallelogram) was never cleared — the button ignored the theme radius no matter what the sync emitted. Default now emits an explicit clip-path **reset** across all button surfaces. On top of that, every module's global-button mode previously synced only background / text / hover / radius / typography; a new shared `DS_Module_UI::global_button_css()` also syncs the **full border (style / width / colour), border hover colour, and box-shadow** from Theme Setting → Elements → Button. Rewired: **Hero Banner** primary (ghost keeps radius + typography), **Menu CTA (Last Item)**, **Post Loop** "See all" + **Tournament** + **Program** (theme mode), **Page Cards** button, **CTA** bento + hero buttons.

### Added
- **Images/Videos Carousel — Auto Scroll (Reels Strip).** The strip advances one card at a time on a timer (interval setting, default 4s) and loops back to the start. Pauses on hover / touch / focus, only runs while on screen, and stays off for reduced-motion visitors.

## [1.9.8] - 2026-07-07
### Changed
- **Renamed "Info List" → "Post Details"** and scoped it to its intended context: the module now only appears in the builder panel while editing a **Themer layout** (via the `fl_builder_register_module` filter). Its rows read ACF fields off the rendered post, which is meaningless on a normal page. Instances already placed on pages keep rendering — the gate only controls the panel listing. Slug (`ds-info-list`) and saved settings unchanged.

### Fixed
- **Post Details — editor memory error on normal pages.** A row's Value connected to the "Post Content" dynamic field (or a `[wpbb post:content]` shortcode) on a regular page rendered the page inside itself (infinite recursion → memory exhaustion in the builder). `render()` now has a re-entry guard, so the loop physically can't happen even on existing page instances.

## [1.9.7] - 2026-07-06
### Added
- **Admin toolbar quick links** (`ds_admin_bar_links_enabled`, blueprint 6+, auto-on): **Theme Setting** and, beside it, **DS Toolkit** in the WP admin bar (wp-admin and front end). LeagueApps-email users only, and each link also requires its page's capability (Theme Setting: `edit_posts` + the feature enabled; DS Toolkit: `manage_options`), so partners never see them and there are no dead links.

## [1.9.6] - 2026-07-06
### Added
- **Reels Strip — Media Library videos.** New **Video (Media Library)** field per slide (Beaver's native video picker) for uploading/picking MP4s; takes priority over the Video URL field (kept for externally-hosted files).
- **Reels Strip — Autoplay Videos.** Optional Instagram-style behaviour: videos autoplay muted + looped while on screen and pause off-screen (IntersectionObserver); click pauses/resumes. Reduced-motion visitors get click-to-play instead.
- **Reels Strip — Play Button styles.** Circle (default), **Icon only** (no circle, larger glyph with drop shadow), or **Hidden** (the whole card is the play target). Colour fields show per style; the button auto-hides in autoplay mode.

### Fixed
- **Post Loop — inline HTML now works in titles.** The section heading and every card title (featured / news grid / card grid / tournament / program / staff names + roles / team names / the `{title}` custom-loop token) now go through the shared safe inline-HTML whitelist, so `<span>` styling renders instead of printing literally. Also swept the last stragglers elsewhere: Page Cards titles, Team Detail coaches-section title, and Images/Videos Carousel captions.
- **Post Loop — Tournament button now follows Theme Setting → Elements → Button** (background, text, hover, border-radius, typography). It previously only borrowed the button colour with a hardcoded 6px radius.
- **Page Cards — the "Button (follows theme)" link style now actually follows the theme Button** (bg, text, hover, radius, typography); it previously had no sync behind the label.
- **Reels Strip — clicks on the native video controls** (pause / scrub / fullscreen) no longer re-trigger the card's play handler.

## [1.9.5] - 2026-07-05
### Added
- **Images/Videos Carousel — Style 3: Reels Strip.** A new multi-column style (modeled on an Instagram-reels strip): portrait cards side by side, each an **image or an MP4 video**. The slide form gains a **Video URL (MP4)** field — a video card shows a centred play button over its poster image and plays inline on click (playing one pauses the others); an image card stays a plain (optionally linked) card. Style → Reels Strip controls: responsive **Columns** (default 5 / 3 / 2) + **Gap**, **Card Aspect Ratio** (9:16 default, 3:4, 4:5, 1:1), **Corner Radius** (blank = Theme Setting radius), **Arrows** (steps one card; the strip always swipes/scrolls with snap), and **Play Button** background + icon colours. Honours `prefers-reduced-motion`; play button has a `:focus-visible` ring; JS is idempotent and re-inits on builder partial refresh; imageless slides fall back to the Social Card.

### Changed
- **Renamed "Image Carousel" → "Images/Videos Carousel"** (module name, description, and the Settings-page module list; the `ds-carousel` slug and existing instances are unchanged).

## [1.9.4] - 2026-07-05
### Added
- **Inline HTML in module title fields.** Editor-authored title / eyebrow / label fields across the LeagueApps modules (Hero heading, Heading title + subheading, CTA heading + card/tile/bento titles and eyebrows, Team Detail section titles, Org Stats eyebrow + labels, Marquee items + label, Info List text) now accept safe inline HTML — `span` (class/style), `strong`, `em`, `b`, `i`, `u`, `small`, `mark`, `br`, `sup`, `sub` — via a shared `DS_Module_UI::inline()` kses whitelist, so e.g. `Lorem <span style="color:#069e33">Ipsum</span>` renders instead of printing literally. `style` attributes are sanitised by WP's safecss filter; everything else is still escaped. WP post titles remain fully escaped.
- **Image Carousel — Height option.** New Sizing → **Height** select (Aspect ratio (default) / Small 300px / Medium 420px / Tall 560px / Custom with responsive per-breakpoint values): a fixed deck height that replaces the aspect ratio; slide images keep covering the card. The matching utility classes `height-small` / `height-medium` / `height-tall` (on the module, column, or row) now also work, emitted with enough specificity to beat the per-node aspect rule.
- **CTA (Bento) — Cell Shadow.** New Style → Bento **Cell Shadow** option (None / Soft / Medium / Strong) alongside the 1.9.3 border controls.

## [1.9.3] - 2026-07-04
### Added
- **CTA (Bento) — cell borders.** New **Cell Border Width** + **Cell Border Colour** options (Style → Bento), global-colour connected. Blank/0 = no border, so existing instances are unchanged.
- **CTA (Bento) — eyebrow style controls.** New **Eyebrow Colour** and **Eyebrow Typography** (responsive, live preview) in Style → Bento. Blank colour keeps the defaults (accent on text cells, white pill on image cells).

### Fixed
- **CTA (Bento) — the cell Eyebrow now actually renders.** The card form saved an Eyebrow for every bento cell but Style 3 never output it. Text cells now show it as a small accent label above the title; image cells show it as a rounded dark pill overlaid bottom-left.
- **CTA (Bento) — responsive Columns / Gap / Row Height now work.** The tablet and mobile values of the Columns and Gap fields were ignored (tablet was hardcoded to 2 columns, mobile to 1), and the tablet Row Height was emitted before the hardcoded `grid-auto-rows: auto` so it never applied. All three now honour their tablet/mobile values; blank still falls back to the original 2-up / 1-up defaults.

## [1.9.2] - 2026-07-02
### Added
- **LeagueApps Modules settings: per-module descriptions + an "Enable all" switch.** Each of the 14 module toggles now shows a short description of what the block does, and a top **Enable all modules** switch turns every block on or off at once (it checks/unchecks the individual toggles, which remain the source of truth and still save per-module).

## [1.9.1] - 2026-07-01
### Added
- **LeagueApps modules are now opt-in on any blueprint.** A new **LeagueApps Modules** section on Settings → DS Toolkit lists all 14 in-house Beaver Builder blocks (Hero, Menu, Post Loop, Page Cards, Image Carousel, Org Stats, Marquee, Info List, CTA, Heading, Divider, Team Detail, Content Router, Partner Social) with a per-module toggle, shown **regardless of blueprint**. Existing blueprint-5 sites can now enable individual blocks without a full blueprint bump; the toggles are **off by default** below blueprint 6, so auto-updates change nothing. The blueprint gate is bypassed for these module features only (they are additive builder blocks); the behavioral features (image optimization, disable comments, admin-menu tidy, theme setting) stay gated to blueprint 6. New builds (blueprint 6+) still get every module on by default.

## [1.9.0] - 2026-06-22
### Added
- **Leagueapps Divider** Beaver Builder module (`ds_divider_module_enabled`, blueprint 6+, auto-on) — new in-house module in `modules/ds-divider/`, registered under the "LeagueApps" category. Horizontal or vertical, with five effects: **Solid**, **Gradient Fade** (fades to transparent at the ends), **Running Light** (a loading-style highlight that sweeps along — down for vertical, across for horizontal), **Glow Pulse**, and **Marching Dashes**. Two global-colour-connected colours, thickness, length/height + alignment, rounded ends, spacing, and animation controls (speed, reverse direction, glow size, dash length/gap). Every animation honours `prefers-reduced-motion`.
- **Hero Banner — Multiple Backgrounds overlay.** The Overlay setting gains a **Multiple Backgrounds** option that reuses Beaver Builder's native layered-background control: stack colours, gradients, and images that composite into the hero overlay. Emission is scoped (via the field's preview `enabled` guard) so it only paints in that mode — switching overlay styles never leaves a stray layer.
- **Leagueapps Menu — Mega menu picker + design options.** A new in-builder **Mega Items** picker (search + click "pills") on the module settings chooses which top-level items open as mega panels, each with its own column count — no Appearance → Menus, no CSS classes. Adds **Panel Width** (auto / full menu width / full screen width), **Panel Alignment**, per-item **Columns**, **Mega column-heading** colour / hover / typography / underline, and a **Mega sub-item indent**.
- **Leagueapps Menu — Justify alignment.** A new **Justify (even spacing)** option in the Menu's Alignment control spreads the top-level items evenly across the full width of the menu bar (`justify-content: space-between`).

### Changed
- **Post Loop — manual-list cards moved to the Content tab; Query tab auto-hidden.** For the **Program Card** and **Sponsor Card** (manual lists), the card builder now lives on the **Content** tab (everything content in one place for partners), and the **Query tab is hidden entirely** for those card types since they don't run a query (via the card-layout select's `toggle` → `tabs`). Query-driven cards (News, Staff, Teams, Tournament, etc.) keep the Query tab unchanged.

### Fixed
- **Menu — mega-items selection now persists on reopen.** The mega-items picker stores its value as a plain `id:cols` string instead of JSON, so Beaver's save path (`json_decode_deep`) no longer turns it into an array a text field can't restore. Also removes a stray "Array to string conversion" notice on render.
- **Team Detail — no longer fatals when ACF is inactive.** The Single Team layout's `get_field()` calls are now guarded with `function_exists()` and fall back to `get_post_meta()`, matching every other module. Previously a gen-6 site with ACF deactivated would hit "Call to undefined function get_field()".

## [1.8.0] - 2026-06-21
### Added
- **Leagueapps Menu — bar hover animation, pill item backgrounds, dropdown hover highlight.** Three new options on the in-house `ds-menu` module, all global-colour-connected and off by default (existing instances unchanged):
  - **Hover Animation** (Style → Menu Bar): top-level links can reveal an underline (slide-in, grow-from-centre, or fade-in) or **Raise** / **Grow** the label on hover and for the current page. Underline gets its own colour (blank = the Text Hover colour) and thickness. Desktop-scoped; the mobile overlay is untouched.
  - **Menu Bar Item Background (Pill)** (new Style section): a rounded background behind top-level items on hover and for the current page, with separate hover vs active/current background + text colours, corner radius (999 = full stadium pill), and vertical/horizontal padding. The CTA button item is never given a pill.
  - **Dropdown / Mega Item Hover Background** (Style → Dropdown / Mega): a background highlight (with optional corner radius) behind a dropdown / mega link on hover.
  - **Hover Font Weight** (Style → Menu Bar): top-level links can go heavier (Medium → Black) on hover and for the current page. A hidden bold copy of each label pre-reserves the wider width so the bar never reflows / shifts when the weight changes.
  - The bar hover effects (animation + font weight) **exclude the CTA button** (last item) — a button keeps its own button hover treatment instead of getting an underline / raise / grow / weight change.
  - **Item Horizontal Padding** (Style → Item Size & Spacing): explicit left/right padding *inside* each top-level link, fully independent of the gap between items. **Item Spacing** is now a real flex `column-gap` between items (no longer implemented as link padding), so the two controls no longer fight each other — you can have wide gaps with zero link padding, or vice-versa.
  - **Tidier options UI** — the overloaded "Menu Bar" panel was split into three collapsible sections (**Menu Bar** colours + weight + typography, **Hover Effect** animation, **Item Size & Spacing**) and the pill panel renamed to **Item Background (Pill)**. Nothing removed; the form just reads cleaner.
- **Leagueapps Menu — compact bar items.** Top-level links are now **compact** (their height set by a new **Item Vertical Padding** control, vertically centred in the header) instead of stretching the full header height. The menu *item* still fills the bar so the dropdown keeps dropping cleanly from the bar's bottom edge, but the clickable link and the hover underline now sit tight to the label. Also trimmed the dropdown / submenu panel's own vertical padding (`8px` → `4px`) so the first item no longer has a doubled (panel + item) gap above it.
- **Leagueapps Image Carousel** Beaver Builder module (`ds_carousel_module_enabled`, blueprint 6+, auto-on) — a new in-house module in `modules/ds-carousel/` (no UABB dependency, fully SSH-editable), registered under the "LeagueApps" category. **Style 1 — Stacked Deck**: the active image sits in front and the upcoming images peek behind it (offset + scale + fade) for depth, with an optional offset **frame bracket** around the front card and a per-slide **caption pill** (editorial "LOCKED IN" reference look). Built on the same multi-style scaffold as the other modules (`styles()` registry + per-style `toggle` + `.ds-carousel--<style>` modifier). UX-first, built to the in-house quality bar:
  - **Slides** — a repeater of image + caption + link. A slide with no image falls back to the Theme Setting **Social Card**. The front slide follows its link; clicking a slide behind brings it forward.
  - **Behaviour** — Autoplay (+ interval, pause-on-hover), Loop, Drag/Swipe, and Transition Speed. Autoplay and transitions always respect `prefers-reduced-motion`. Vanilla, idempotent JS that re-inits on `fl-builder.layout-rendered`.
  - **Stack** — Direction (peek right / left / straight behind), Cards Behind (1–4), Horizontal/Vertical Offset, Scale Step, and Rotation Step.
  - **Sizing** — Card Width, Aspect Ratio (portrait / square / landscape presets), Corner Radius.
  - **Frame Bracket** — show/hide + offset, thickness, and (global-colour-aware) colour; the offset flips with a left-peek stack.
  - **Caption** — position (bottom left/center/right), pill background + text colour, radius, and responsive typography.
  - **Navigation** — Arrows + Dots (each toggleable), with an accent colour for the arrow hover and active dot. Nav is layered above the stacked cards so it stays clickable.
  - **Colours / Spacing** — optional section background; 4-side responsive padding + margin (default 0 / flush). All colour pickers are global-colour-connected and default to the site Global Colours.
- **Image Optimization on Upload** (`image_optimization_enabled`, blueprint 6+, auto-on) — a new feature in `features/class-ds-image-optimization.php` that stops oversized, heavy media at the door. Hooks `wp_handle_upload` (which fires for **both** Media Library uploads and sideload / WP-CLI imports), so the moment a JPEG/PNG comes in it's downscaled to a web-friendly **2048px** longest-edge bounding box (aspect preserved, never upscaled) and re-encoded as **WebP** at quality **82** — *before* WordPress generates sub-sizes, so every thumbnail is WebP too. A partner dropping a 12 MB phone photo ends up with a lightweight WebP and the heavy original is removed. Safe by design: only touches `image/jpeg` / `image/png` (and oversized `image/webp`, which it resizes in place) — GIFs (keep animation), SVGs, and every non-image upload pass straight through; it no-ops gracefully if the active image editor can't encode WebP or errors; and an already-small image that *didn't* need resizing is left untouched when the WebP wouldn't be smaller. Tunable via the `ds_image_optimization_max_dim`, `ds_image_optimization_quality`, and `ds_image_optimization_mime_types` filters. Toggleable from **Settings → DS Toolkit → Features**.
- **Leagueapps News — Style 2 (Card Grid).** A uniform grid of news cards (selectable from the News Style dropdown), modeled on the nhbfutbolclub news cards: each card is an image with a category **pill** badge overlaid, then date, title, and a "Read More →" link. Query-driven like Style 1 (reuses the Query tab). Self-contained, fully customizable **Cards** section (Style-2 only): responsive **Columns** + **Gap**, **Image Height**, **Corner Radius**, card background / border colour + width, **pill** background + text, title / date / Read More colours, Read More text, and a hover-lift toggle. Scoped under `.ds-news--style2`; responsive (4-up → 2-up → 1-up).
- **Hero Banner — Ken Burns effect.** New **Ken Burns Effect** toggle on the Background (image + slideshow). When on, the background image(s) slowly zoom; for a slideshow the zoom duration tracks the Slide Interval. Honours `prefers-reduced-motion` (no animation for those visitors). Scoped via a `.ds-hero--kenburns` modifier class.
- **Hero Banner — slideshow navigation.** New **Slide Navigation** control (shows for a 2+ image slideshow): **Progress Lines (cooldown)** — thin bars that fill over the Slide Interval as a countdown to the next slide (the default) — plus **Dots**, **Arrows**, **Lines + Arrows**, and **Dots + Arrows**. All indicators are clickable (jump to slide, restart autoplay); the active indicator and arrow hover use the accent colour. The cooldown fill freezes full for `prefers-reduced-motion` visitors. Driven by `js/frontend.js` (vanilla, re-inits on `fl-builder.layout-rendered`).
- **Org Stats — eyebrow rule options.** The pre-heading ("The Record" line) now has **Eyebrow Rule** (None / Leading line / Both sides) plus **Rule Length** and **Rule Thickness** controls (the rule renders via `::before`/`::after` and tracks the eyebrow colour).
- **Org Stats — Image Cards layout + richer background.** New **Stat Layout** control (Style tab): *Plain figures* (the original bordered grid, default) or **Image Cards** — each stat becomes a photo card with a dark gradient overlay and the figure laid over it (matches the volleyball-cards reference). Adds a per-stat **Card Image** plus card controls (responsive **Card Height**, **Corner Radius**, **Gap**, **Inner Padding**, **Content Position** bottom-left / bottom-center / center, and an **Overlay** colour + top/bottom opacity via `color-mix`). The section **Background** option also gains **Image Size**, **Image Position**, **Image Repeat**, **Image Attachment** (scroll / fixed parallax), and **Image Blur**.
- **News — Social Card image fallback.** A news post with no featured image now falls back to the Theme Setting **Social Card** image instead of a blank tile (applies to the featured card, Style 2 cards, and the `{image}`/`{thumb_url}` custom-card tokens).

### Changed
- **Renamed "Leagueapps News" → "Leagueapps Post Loop"** (module slug `ds-news` → `ds-post-loop`; option `ds_news_module_enabled` → `ds_post_loop_module_enabled`). Existing saved layouts were migrated (node type rewritten in `_fl_builder_data`/`_fl_builder_draft`). Restructured as **Content Type → Layout**: a top-level Content Type selector (News today; Staff / Team / Athletes presets scaffolded via `content_types()`) and a Layout selector beneath it. News layouts: **Featured + Grid**, **Card Grid**, and a universal **Custom Loop Layout** (always available for any content type) that loops the query and renders the editor's own HTML / shortcode once per post (tokens `{title}`/`{permalink}`/`{date}`/`{category}`/`{excerpt}`/`{image}`/`{id}`; shortcodes like `[fl_builder_insert_layout]` resolve against each post), with configurable responsive columns + gap. The Featured + Grid layout's old per-loop-card "Loop Card Layout" (built-in vs custom) option was removed from the Style tab — custom per-post markup now lives in the dedicated Custom Loop Layout. Internal `.ds-news-*` CSS classes and the `style1`/`style2` layout keys are kept for backward-compatibility.
- **All module colour defaults now come from the site Global Colours.** Every in-house module's colour fields default to a `var(--fl-global-*)` reference (dark-background / light-background / white / accent / primary / headings / body / line-color) instead of the reference designs' hardcoded hex, so a freshly dropped module matches the site's brand palette out of the box (still overridable per field, still global-colour-connected). Swept across Hero, CTA, News, Org Stats, and Marquee.
- **All module content defaults are now lorem ipsum / generic placeholders** (headings, eyebrows, buttons, stat labels, marquee items, card text) so new instances don't ship reference-site copy.

### Fixed
- **Image Carousel & Hero — nav arrows jumping on click.** A global theme rule (`button:focus, button:active { position: relative }`) outranked the single-class arrow rule, so clicking an arrow flipped it to `position:relative`, dropping it into flow and shifting the carousel/hero. The `.ds-carousel-nav` / `.ds-hero-nav` rules now re-assert `position:absolute` on `:focus`/`:active`.
- **Menu — full-height nav items (hover/click dead zones + dropdown position).** On desktop, each top-level menu item now fills the full height of the header bar instead of sitting at text height, vertically centred. Previously the strips of the bar above and below the text were dead zones (hover/click did nothing there) and the dropdown anchored to the short item, overlapping up into the bar. Now the entire bar is hoverable/clickable per item and the dropdown drops flush from the bar's bottom edge. Scoped to desktop (above the menu breakpoint) via `flex:1` on the module node, so horizontal alignment, the CTA button height, and the mobile overlay are all untouched.
### Added
- **Leagueapps Org Stats** Beaver Builder module (`ds_orgstats_module_enabled`, blueprint 6+, auto-on) — an in-house "by the numbers" stats band in `modules/ds-orgstats/` (no UABB dependency, fully SSH-editable), registered under the "LeagueApps" module category. Modeled on the nhbfutbolclub "The Record / Proof on the Pitch" section. **Style 1 — Record Band**: a full-bleed dark section over a faded background photo + gradient overlay, a centred eyebrow (with a leading rule) + heading (with an `{a}…{/a}` accent word), then a clean bordered grid of big figures — each an optional **prefix**, a **count-up number**, an optional accent **suffix** (e.g. `+`, `×`, `%`), and an uppercase label. Built on the same multi-style scaffold as ds-hero / ds-cta / ds-news (a `styles()` registry + per-style `toggle` sections in both tabs + a `.ds-orgstats--<style>` modifier class) so more styles can be added later. Options, built to the in-house quality bar:
  - **Stats** — a repeater of figures (prefix / number / suffix / label). A plain whole number (e.g. `75`, thousands separators allowed) **counts up from 0** when scrolled into view; non-numeric text (e.g. "SoCal NPL") renders as-is. The final value is rendered server-side so it's correct with JS off / in full-page screenshots; the count-up resets to 0 and eases up (easeOutCubic) only when motion is allowed.
  - **Header** — show/hide, eyebrow, and a heading (line breaks kept, `{a}` accent word).
  - **Grid** — responsive **Columns** (node-scoped overrides out-specify the static media queries; default 2-up on tablet/mobile), responsive item padding (vertical + horizontal), and a content max width.
  - **Background** — optional background image with an **opacity** control, behind a gradient **Overlay** (colour + top/bottom opacity via `color-mix`, so it works on hex *and* global `var()` colours).
  - **Dividers / Border** — clean grid lines drawn around and between the figures (container draws top/left, items draw right/bottom, so the rule grid stays correct for any column count); style (none/solid/dashed/dotted) + width + colour + opacity.
  - **Motion** — Count-Up toggle + duration (ms, passed to the JS via a CSS custom property); always off for `prefers-reduced-motion` visitors. Vanilla, idempotent JS that re-inits on `fl-builder.layout-rendered`.
  - **Colours** — section background, eyebrow (its leading rule tracks the colour), heading, accent (heading word + prefix/suffix), number, and label — every control a **global-colour-connected** picker; the eyebrow and accent fall back to the global Accent colour when left blank.
  - **Typography** — independent responsive controls for the eyebrow, heading, number, and label.
  - **Spacing** — inner padding (on the content wrap) and outer margin (on the section), both 4-side / responsive, default **0** (flush).

## [1.6.0] - 2026-06-21
### Added
- **Leagueapps News** Beaver Builder module (`ds_news_module_enabled`, blueprint 6+, auto-on) — an in-house news section in `modules/ds-news/` (no UABB dependency, fully SSH-editable), registered under the "LeagueApps" module category. Modeled on the threehl home-dev "OUR NEWS" block. **Style 1 — Featured + Grid**: a header row (a `{a}accent{/a}` heading + a "See all" button) over a CSS grid with one large **featured card** (background image, gradient overlay, badge, title, excerpt, "Read More →") spanning two rows on the left, and a grid of small **news cards** (category eyebrow, uppercase title, date, chevron) on the right. The newest/first post is the featured card; the rest fill the loop. Built on the same multi-style scaffold as ds-hero / ds-cta (a `styles()` registry + per-style `toggle` sections in both tabs + a `.ds-news--<style>` modifier class) so more styles can be added later. Options, built to the in-house quality bar:
  - **Query tab** — fully post-query-driven (no manual entry): **Post Type** (any public type), **Number of Posts**, **Order By** (date / modified / title / menu order / random) + **Order**, **Offset**, a PHP **Date Format**, and an optional **Taxonomy Filter** (taxonomy slug + comma-separated term slugs/IDs). The featured card's image / excerpt / category / date are pulled from the post automatically.
  - **Loop Card Layout** — each looped (non-featured) post renders with the **Built-in card** design OR a **Custom** layout where the editor supplies HTML and/or a shortcode (e.g. a saved BB layout: `[fl_builder_insert_layout id="123"]`). The custom template renders once per post with that post in scope (`setup_postdata`, so post-aware shortcodes resolve) and supports `{title} {permalink} {date} {category} {excerpt} {image} {id}` tokens. The featured card always uses the built-in design.
  - **Header** — show/hide, heading (with `{a}` accent word), and a show/hide **"See all" button** (text + link).
  - **Layout** — responsive **Gap**, **Featured Column Width** (CSS track, e.g. `1.4fr`), responsive **Featured Min Height** and **Card Min Height**, and a **Hover Lift** toggle.
  - **Featured Card** — fallback background, gradient **overlay** colour + top/bottom opacity (via `color-mix` so it works on hex and global `var()` colours alike), badge background/text, title colour, excerpt colour, editable **Read More** text + colour.
  - **Loop Cards** — background, border colour / hover-border colour / width, corner radius, category-eyebrow colour, title colour, date colour, chevron colour (built-in card style).
  - **Header & Section** — section background, heading text + accent colours, and a **Button Style** that **defaults to the global Theme Setting Button** (background/hover/radius/typography), with **Dark** (custom bg/text) and **Heading Accent** fallbacks.
  - **Colours** — every colour control is a **global-colour-connected** picker.
  - **Typography** — independent responsive controls for the heading, featured title, featured excerpt, badge, card title, card category, and card date.
  - **Spacing** — inner padding (on the content wrap) and outer margin (on the section), both 4-side / responsive, default **0** (flush).
  - **Responsive** — node-scoped grid overrides collapse the layout to featured-on-top + 2-up cards at the medium breakpoint, then a single column on mobile (the overrides are emitted node-scoped so they out-specify the desktop base rule).

## [1.5.0] - 2026-06-21
### Added
- **Leagueapps Marquee** Beaver Builder module (`ds_marquee_module_enabled`, blueprint 6+, auto-on) — an in-house scrolling-ticker module in `modules/ds-marquee/` (no UABB dependency, fully SSH-editable), registered under the "LeagueApps" module category. Modeled on the threehl home-dev broadcast ticker. **Style 1 — Scrolling Tape**: a slim full-width bar with a pinned accent **label** (with an optional **pulsing status dot**) on the left and an infinitely scrolling row of **items** separated by a configurable glyph (a diamond by default). Built on the same multi-style scaffold as ds-hero / ds-cta (a `styles()` registry + per-style `toggle` sections + a `.ds-marquee--<style>` modifier class) so more styles can be added. Options, built to the in-house quality bar:
  - **Items** repeater — each item is text with an **optional link** (linked items become hover-coloured anchors; the separator glyph is configurable or can be removed).
  - **Pinned Label** — show/hide, label text, and a show/hide **pulsing dot**; independent label background / text / dot colours and **label typography**.
  - **Motion** — **Scroll Duration** (seconds per loop), **Direction** (right→left or left→right), and **Pause on Hover**. The scroll always stops for visitors who prefer reduced motion (`prefers-reduced-motion`).
  - **Layout** — responsive **Bar Height**, **Item Spacing**, the **Separator** glyph + **Separator Size**, and the label side padding.
  - **Border** — style (none/solid/dashed/dotted), **Border Sides** (bottom / top / top & bottom), width (up to 12px) + colour + opacity (colour falls back to the global Accent when blank).
  - **Colours** — per-context controls (bar background, item text, item hover, separator, label background, label text, status dot), every one a **global-colour-connected** picker; the accent-ish fields (separator, label background, border) fall back to the global **Accent** colour when left blank.
  - **Typography** — independent responsive controls for the items and the label.
  - **Spacing** — outer margin (4-side, responsive), default 0 (flush).
  - **Seamless loop** — PHP renders two identical item groups for a gapless `translateX(-50%)` CSS loop; a small idempotent JS tops the groups up with extra clones when the item set is narrower than the bar (re-running on resize and after a Beaver Builder partial refresh) so no blank gap ever scrolls through.

## [1.4.0] - 2026-06-21
### Added
- **Leagueapps CTA — Style 3 (Bento Grid).** A third CTA style: a bento layout of mixed **image cells** and **text/CTA cells** with per-cell **column & row spans** (CSS grid, `grid-auto-flow:dense`). Cards gain Bento fields (Cell Type image/text, Column Span, Row Span, rich-text Description); text cells render Title + Description + an accent button, image cells render a full-bleed image (clickable, zoom on hover). Header supports a heading (with `{a}` accent) plus a description paragraph. New Style-3-only "Bento" section (row height, text-cell background); columns/gap come from the shared Grid section. Scoped under `.ds-cta--style3`; collapses to 2-up then full-width on smaller screens with **auto-height cells** so text cells are never clipped. Text-cell buttons **default to the global Theme Setting Button** (background/hover/radius/typography), with an Accent fallback. The cell Description uses a textarea (not an `editor`), and the module renders with full preview refresh (`partial_refresh` off) so changing a card's Cell Type re-renders the module correctly in the builder instead of blanking the preview until save.
- **Leagueapps CTA — Style 2 (Bordered Tiles).** A second CTA style (selectable from the CTA Style dropdown), modeled on the nhbfutbolclub "Programs" tiles: rounded cards with a dark border that turns the accent colour on hover, a bottom-to-top gradient, a numbered eyebrow (e.g. "01 / Join the Club"), a big title, and an "Express Interest →" text link (the card gains a **Link Text** field; Style 1 still uses a chevron). Image zooms on hover (toggle). The reference's green line above the eyebrow is intentionally omitted. New per-style "Tile" section (min-height, corner radius, border width + colour + hover colour, image-zoom); Style-1 layout fields (ratio, clip cuts, stagger) moved into a Style-1-only "Card Shape" section, while Grid / Overlay / Colours / Typography / Spacing / Header are shared across both styles. The dynamic CSS branches on the selected style and scopes everything under `.ds-cta--style2`.
- **Hero Banner & CTA spacing controls + compact defaults.** Both modules now expose **Padding** and **Margin** (4-side, responsive) in a Style-tab "Spacing" section, defaulting to **0** so the modules sit flush/compact by default (removed the previously hardcoded section padding). Hero **Content Width** now actually governs the text column (removed the separate hardcoded subtext `max-width` cap that made the control look broken).
- **Button Shape selector** (Theme Setting &rsaquo; Elements &rsaquo; Button, blueprint 6+, LeagueApps only). A radio-card control at the top of the Button section with a **live preview** of each shape rendered in the site's own button colours: **Default** (the theme's rounded button), **Angle** (opposite corners sliced for a slanted look), and **Clip** (all four corners beveled). The choice is stored in the standalone `ds_button_style` option and emitted on the front end via a `wp_head` `<style id="ds-toolkit-button-style">` from `DS_Theme_Setting` itself (not `DS_Global_CSS`, which is an independently-toggled feature). The shape applies to the filled site-button surfaces — standard BB/UABB buttons (`.fl-button`), the nav CTA (`.ds-menu-item.is-button`), the hero primary CTA (`.ds-hero-btn--primary`), the CTA bento button (`.ds-cta-bento-btn`), and the News "See all" button (`.ds-news-seeall`) — via `clip-path`; outline/ghost/text-link buttons are left untouched so their border isn't sliced. Every in-house LeagueApps module's filled button respects the shape by default and can opt OUT with a `ds-no-clip` class (`:not(.ds-no-clip)`). The clip-path definitions are a single source of truth (`DS_Theme_Setting::button_styles()`) shared by the admin preview and the front-end CSS, so what's previewed is exactly what renders. Replaces the old commented-out "ENABLE THIS FOR CLIP Button" block in `global-css.css` with a proper UI toggle. Default is **Default** (no CSS emitted), so existing sites are unchanged until a shape is chosen.

## [1.3.0] - 2026-06-18
### Added
- **Blueprint-generation gating.** Features can now declare a `'min_blueprint'` in the registry. A gated feature only loads, defaults on, and shows a toggle on sites stamped at that generation or higher, read from the new `DS_Toolkit::blueprint_version()` helper (the `ds_blueprint_version` DB option, or a `DS_BLUEPRINT_VERSION` wp-config constant override). The option travels when an install is cloned from the blueprint, so DSLP6 builds inherit it; legacy/unstamped sites (DSLP4/5, ad-hoc) report 0 and never see gated features — no toggle, no auto-enable, the class is never instantiated. The toolkit only ever reads the stamp, never writes it.
- **Disable Comments feature** (`disable_comments_enabled`, blueprint 6+, auto-on). Closes comments and pings site-wide, hides existing comments, strips comment/trackback support from every post type, and cleans the admin (Comments menu, dashboard widget, admin-bar node, discussion-screen redirect). Replaces the standalone Disable Comments plugin on DSLP6 builds; older sites keep their separate plugin and are untouched.
- **General tab + `[ds_copyright]` shortcode** (blueprint 6+). A native General theme-settings tab (shown only on blueprint 6+). First setting is the footer copyright text with a `{year}` token that auto-substitutes the current year, output anywhere via `[ds_copyright]`.
- **Theme Setting page** (blueprint 6+, LeagueApps only). A LeagueApps-only admin surface for **Beaver Builder Global Styles**, registered directly below "Partner Setting" and gated by `DS_Toolkit::is_leagueapps_user()`. It reads and writes the same store BB's own Global Styles panel uses (option `_fl_builder_styles` via `FLBuilderGlobalStyles::get_settings()`/`save_settings()`), so edits here and in BB stay **in sync both ways** with no extra mapping (BB renders the CSS). Mirrors the BB UI: an **Elements** tab (first) and a **Colors** tab. Elements covers **Text / Heading / Link / Button** with the full BB typography control set — Font (family/weight/size+unit/line-height+unit/align), Style & Spacing (letter-spacing/transform/decoration/style/variant) and Text Shadow (color/x/y/blur) — plus **Heading All + H1–H6 sub-tabs** (per-level color + typography) and **Button border** (style/width/color/radius/shadow + border hover color). Colors holds named global colors (Add button) + a variable prefix. All values are written in BB's exact object shapes (`{length,unit}` compounds, `text_shadow{color,horizontal,vertical,blur}`, border `{width,radius,shadow}`) so BB regenerates every `--<prefix>-*` variable. Font dropdowns use BB's full list (system + Google + Typekit via `fl_theme_system_fonts`). Collapsible sections, styled with the toolkit admin design system. No ACF dependency; no theme_mods/color-preset writes.
- **Beaver Builder's own color picker** on every Theme Setting color field (BB's `fl-color-picker.js` widget + `jquery-ui` slider/draggable/sortable + builder CSS bundle). The BB **Global Colors** are surfaced through the picker's named globals path: each shows its **label** (not hex) and selecting one stores a synced **`var(--<prefix>-<slug>)`** reference (change the global color and every field using it updates). Field markup carries the `.fl-field`/`.fl-field-connection` markers BB needs to show globals; the builder-only `FLThemeBuilderFieldConnections._connectField` is stubbed to write the var reference standalone; the global `:root` vars are printed on the page so swatches resolve; `fl-color-picker-alpha-enabled` keeps rgba alpha through reloads; hex is rendered without `#` (BB convention); and `var()` is allowed by the color sanitizer. Falls back to WP's Iris picker if BB isn't active. Active tab is persisted across save→reload (localStorage).
- **General tab** (blueprint 6+): Page Background (color, image, repeat, position, size, attachment), Custom CSS, and Custom JavaScript — written to the BB Theme `theme_mods` (`fl-body-bg-*`, `fl-css-code`, `fl-js-code`) and rendered by the theme, with `FLCustomizer::refresh_css()` on save.
- **Favicon (General tab)** — a Site Icon media picker that writes the core `site_icon` option, so it stays in sync with WordPress **Settings &rsaquo; General &rsaquo; Site Icon** (and the Customizer) and drives the browser-tab favicon. Empty clears it.
- **LeagueApps Menu** Beaver Builder module (`ds_menu_module_enabled`, blueprint 6+, auto-on) — an in-house nav module in `modules/ds-menu/` (no UABB dependency, fully SSH-editable). Renders a chosen WP menu as a horizontal bar with hover dropdowns; top-level items with children can open a **mega panel** whose links flow into a configurable **column count** (module default, overridable per item via a `ds-cols-N` menu CSS class, or `ds-no-mega` for a plain dropdown); collapses to a **full-screen hamburger overlay** with accordion sub-items below a configurable breakpoint. Registered under a "LeagueApps" module category. Options:
  - **Submenu indicator** — none / arrow icon (flips on hover) / custom text, shown next to items that have a submenu.
  - **CTA button** — style the last top-level item as a button so it pops. "Use Global Button Style" = **Yes** (default) makes it fully match the site Button (Theme Setting → Elements → Button: background, hover, border radius, typography). Set it to **No** for custom colors, and even then the **border radius stays in sync with the global Button whenever the radius field is left blank** (so a custom-colored CTA still tracks the site's button rounding); enter a value to override. In the mobile overlay the CTA can be **full width** and has its own background / text / hover colors (default = inverted so it never blends into the overlay).
  - **Hamburger button** — optional label text to the right of the icon (e.g. "Menu"); it **animates into an X** when the overlay opens, **pins to the viewport corner while open** so it never overlaps the items, and exposes background / border color+width / radius / icon-size controls plus full **label typography** (family, weight, size, line-height, letter-spacing, transform). A theme's `<button>` styling is hard-reset (and the open hamburger forced transparent) so it can't leak in — this is the green-pill bug. The mega panel also **flattens to a single column** in the overlay.
  - **Typography** — independent controls for the bar, the desktop submenu/mega labels, and the mobile overlay (separate **menu-label** and **submenu-label** typography). The mobile submenu/mega font size is now controllable; it was previously pinned to the 20px menu-item size because the overlay item rule also matched submenu links.
  - **Smooth mobile accordion** — expanding a submenu / mega in the overlay animates its height (~0.3s) instead of snapping open; closing animates back.
  - **Dividers** — style (solid/dashed/dotted/none) + color + width for both the dropdown/mega submenus and the mobile overlay.
  - **Mobile overlay colors** — separate **menu-item** and **submenu-item** controls: font color, hover/active color, and background (plus the overlay background). Submenu colors fall back to the menu-item color when unset.
  - **Whole-row tap (mobile)** — in the overlay, tapping anywhere on a parent row (not just the +/- icon) opens/closes its submenu; leaf links and the desktop bar still navigate normally.
  - **Desktop dropdown** — configurable **gap** between the menu bar and the dropdown/mega panel (with a transparent hover bridge so it stays open while crossing the gap), **item padding** (vertical + horizontal), and **panel corner radius** (overrides the built-in default).
- **Leagueapps CTA** Beaver Builder module (`ds_cta_module_enabled`, blueprint 6+, auto-on) — an in-house, **multi-style** call-to-action module in `modules/ds-cta/` (same Style-selector pattern as the Hero). **Style 1 — Clip Cards** is the "Explore" angled-corner card grid (modeled on the threehl design): a repeater of cards, each a background image with a dark gradient, an accent eyebrow, a big title, a chevron, an animated top bar, and a hover lift; an optional header row (heading with an `{a}…{/a}` accent word + a right-side label). Registered under the "LeagueApps" module category. Options: **Grid** (responsive columns + gap, card aspect-ratio, optional **stagger** with offset, section background); **Card Clip** (top-left + bottom-right cut sizes, the angled `clip-path`); **Overlay** (colour + top/bottom opacity via `color-mix`, works on hex *and* global colours); **Colours** (accent for eyebrow/chevron/bar, card title, card bg, header text/accent/label — all global-colour aware); **Typography** (header heading, card title, card eyebrow, responsive). Responsive by default (tablet 2-up, mobile 1-up). Adding a new style follows the documented `styles()` + `render_<key>()` + section-toggle + `.ds-cta--<key>` pattern.
- **Leagueapps Hero Banner** Beaver Builder module (`ds_hero_module_enabled`, blueprint 6+, auto-on) — an in-house full-bleed hero in `modules/ds-hero/` (the blueprint "Home Page Style 1" hero, modeled on the nhbfutbolclub design; no UABB dependency, fully SSH-editable). Content: eyebrow, headline (line breaks kept; wrap a word in `{a}…{/a}` to colour it with the accent), subtext, two CTA buttons, and a 3-slot stats/proof row (blank pairs hide). Registered under the "LeagueApps" module category. Options:
  - **Hero Style selector** — a multi-style module by design. A "Hero Style" dropdown (currently **Style 1 — Classic**, default) gates which option sections show, so each future style exposes only its own controls. Adding a style is a clear pattern: add it to `DS_Hero_Module::styles()`, add a `render_<key>()` method (reusing the shared bg/button/stats helpers), add its sections + list them under the selector's `toggle`, and scope any CSS under the auto-printed `.ds-hero--<key>` modifier class.
  - **Background** — Single Image, **Image Slideshow** (cross-fade with a configurable interval; vanilla JS, respects `prefers-reduced-motion`), or **Video** (autoplay/muted/loop MP4 with a poster). Robust to BB returning photo values as id, url, or array.
  - **Overlay** — None / Solid (colour + opacity) / Gradient (two colour stops each with opacity + angle), applied via `color-mix()` so opacity works on hex *and* global `var()` colours. Defaults to the blueprint's dark-green diagonal gradient.
  - **Buttons respect the site Button by default** — "Button Style" defaults to **Match site Button (Theme Setting)**: the primary button inherits the global Button background / hover / typography, and both buttons pick up its corner radius, so a hero CTA matches the rest of the site automatically. Switch to "Accent colour" for the standalone accent style. (Button text colour is forced so the theme's content-link colour can't make it unreadable.)
  - **Layout / Colours / Typography** — responsive min-height, content width, and alignment (left/center per breakpoint); per-element colours (eyebrow, headline, accent word, subtext, stat number/label, all global-colour aware) and responsive typography for the headline, subtext, and eyebrow.
- **Leagueapps Partner Social** Beaver Builder module (`ds_social_module_enabled`, blueprint 6+, auto-on) — an in-house social-icons module in `modules/ds-social/` (no icon-font or UABB dependency; brand marks are inline SVG, fully SSH-editable). URLs come straight from the ACF **Partner Settings** (`partner_fb` / `partner_instagram` / `partner_x` / `partner_youtube` / `partner_linkedin` / `partner_tiktok`): a network with no value is **silently skipped**, and the module renders **nothing on the front end when none are set** (a hint shows only inside the builder). Registered under the "LeagueApps" module category. Options:
  - **General** — open links in new/same tab; **responsive alignment** (left/center/right per breakpoint).
  - **Icons** — icon **size** and **gap** (both responsive), icon **color** + **hover color** (global-color aware via `connections`).
  - **Background tile** — toggle the tile behind each icon on/off; when on, its own **background color**, **hover background**, **padding** (responsive), and **corner radius**. Blank hover fields keep the base color.
  - Fully responsive: size / gap / padding / alignment each get tablet + mobile overrides emitted under BB's global breakpoints.
- **Social Sharing Card** (`ds_social_card_enabled`, blueprint 6+, auto-on). A single default share image, set in **Theme Setting &rsaquo; General &rsaquo; Social Sharing** (media picker storing `ds_social_card_id` / `ds_social_card_url`), that does three things:
  - **Site-wide sharing default.** Synced into Yoast's native default OG image (`wpseo_social.og_default_image[_id]`) so every page without its own image shares the card; Yoast handles og:/twitter: dimensions and fallbacks. Because a **static front page** is treated as a normal page (Yoast auto-derives its image from the first content image, the header logo, ahead of the default), the card is also written as the **front page's own** Yoast OG/Twitter image so "sharing the site" (the home page) shows the card. Without Yoast, the feature outputs the `og:image` / `twitter:image` (+ dimensions + `summary_large_image`) tags itself.
  - **Beaver Builder dynamic field.** Registers a `Site &rsaquo; Social Sharing Card` page-data property (type `photo`, honours a chosen image size) selectable in any module's dynamic/connection picker.
  - **Self-seeding default.** Ships the default image in `assets/img/social-card-default.jpg` and sideloads it into the media library on first run (admin-only, flag-guarded), so a fresh DSLP6 install has the card set with no manual step.
- **Admin Menu Tidy** (`admin_menu_tidy_enabled`, blueprint 6+, auto-on). Declutters wp-admin: relocates Defender, Yoast SEO, and Media Library Organizer under **Tools** (a label move — the page registration is untouched, capabilities preserved, full 4-element submenu entries so no "Undefined array key 3" warning). Hides ACF, Beaver Builder, and Appearance from non-LeagueApps users. Site Kit and WPMU DEV are intentionally left alone because relocating them isn't clean (Site Kit's custom menu vanishes; WPMU DEV's whitelabel-mangled page errors under a new parent).

### Fixed
- **Theme Setting color picker replaced with a clean modern picker (Pickr).** Beaver Builder's pickers don't work on a standalone admin page: the legacy iris picker laid out wrong (the globals list rendered as an absolute overlay covering the spectrum, the popup pinned to the viewport bottom, a stray alpha strip), and BB's polished newer picker is a React control bound to the builder app, not reusable outside it. The page now bundles **Pickr 1.9.1** (`assets/vendor/pickr/`, MIT, self-contained) themed to match: spectrum square + hue + opacity sliders + hex/RGBA input, with the BB **Global Colors** appended as a named list inside the popup. Picking a named global still stores the synced `var(--prefix-slug)` reference (resolved to its hex for display); custom picks store hex/rgba; `sanitize_color()` normalises on save. Verified logged-in with Playwright on both the Elements and Colors tabs (spectrum shows the real colour, 11 named globals selectable, no console errors).
- **Theme Setting no longer reverts global colors to grey.** Global-color *connections* (e.g. the header's "Primary") reference colors by BB `uid`, and `FLBuilderGlobalStyles::save_settings()` assigns a fresh random `uid` to any color missing one. The save rebuilt the colors array without the `uid`, so every save — including a page-background change on the General tab — regenerated all uids and orphaned every connection, which silently fell back to grey. The save now round-trips each color's `uid` (hidden field carried through), only rebuilds the colors array when the repeater was actually submitted, and only overwrites the prefix when posted. This also fixes the related symptom where changing the page background appeared to recolor `.fl-page-content` (its "Base Page Content BG Color" global was being orphaned the same way). Verified end-to-end: changing the page background and saving leaves all 11 global-color uids unchanged and the header's primary green intact.
- **LeagueApps Menu: global / preset colors are now selectable on the module's color fields.** Each color field was missing Beaver Builder's `'connections' => array( 'color' )` declaration — the attribute that wires BB's global-color picker into a module field (UABB sets it on every color field). Without it the picker never exposed the global colors, so they couldn't be selected in the builder. Added it to all 21 color fields, and made the module's dynamic CSS pass a `var(--…)` value through unchanged (the colour helper previously prepended `#`, turning a connected global into an invalid `#var(...)`).
- **Leagueapps Partner Social: module icon now shows in the content panel.** A bare `'icon' => 'social.svg'` is only resolved against Beaver Builder's *own* `img/svg/` bundle (ds-menu's `menu.svg` happens to exist there; `social.svg` does not), so it fell through to an invalid dashicon and rendered blank. Renamed the file to `modules/ds-social/icon.svg` and dropped the `icon` param so BB auto-loads the module's own `icon.svg`.
- **Leagueapps Partner Social: icon color setting now applies.** The dynamic CSS only set `color` on the `<a>` and relied on the icon's `fill: currentColor`. In a dark footer the theme's link-color rules override the `<a>` colour, dragging the icon fill with it, so the Icon Color control appeared to do nothing. It now also writes `fill` directly on the node-scoped `svg` (default + hover), which wins over the theme regardless. Works with both hex and connected global colors.
- **Theme Setting: global / preset colors are now selectable.** Beaver Builder scopes its color-picker swatch sizing — and the reveal of the presets/"Global Colors" area — to the in-builder UI, so on the standalone Theme Setting admin page the swatches rendered at 0×0 inside a collapsed container that was awkward to open: the global colors couldn't be seen or clicked. The page now force-opens that area and re-styles the swatches as a labelled, clickable list. Selecting one writes the synced `var(--<prefix>-<slug>)` reference and updates the field swatch (verified: clicking "Primary" sets `var(--fl-global-primary)` and shows green).

## [1.2.7] - 2026-05-15
### Fixed
- **Auto-updates no longer deactivate the plugin into a long folder name.** The updater (and its `upgrader_source_selection` → `fix_source_dir` filter) was only instantiated under `is_admin()`. WP Engine Smart Plugin Manager, wp-cron auto-updates, and `wp plugin update` all run in CLI/cron context where `is_admin()` is false, so when the updater fell back to GitHub's raw zipball (release JSON cached during the ~10s window before CI attaches `ds-toolkit.zip`) the extracted `owner-repo-hash` folder was never renamed back to `ds-toolkit/`. WordPress then couldn't resolve the `active_plugins` path and silently deactivated the plugin. The updater now also loads under `wp_doing_cron()` and `WP_CLI`, and `fix_source_dir` no longer bails when an upgrader omits `hook_extra['type']`.

### Changed
- **Updater repo pointer updated to `Design-Shop-LeagueApps-Department/ds-toolkit`.** The hardcoded `agabriel1590/ds-toolkit` only worked via GitHub's redirect; the release `zipball_url` already pointed at the new org, lengthening the fallback folder name. Pointing directly at the current org removes that fragility.

## [1.2.6] - 2026-05-15
### Added
- **`[ds_overlay_nav]` and `[ds_overlay_subs]` shortcodes** (contributed by @LouiePacheco, #7). Render a WordPress nav menu as a full-screen overlay: `[ds_overlay_nav menu="..."]` outputs auto-numbered top-level items, and `[ds_overlay_subs]` (same page, after the nav) outputs child-link blocks for items that have a submenu. Off by default; toggle on the Features tab. Markup is intentionally unstyled to pair with theme-side overlay CSS/JS. Maintainer note: the PR added the feature class only, so the registry entry, default key, and Features-tab toggle were wired up on merge.
- **`CONTRIBUTING.md` and a GitHub PR template.** Spell out the full feature-wiring checklist (registry entry + default + toggle) that the #7 PR missed, with an explicit instruction for AI assistants to read it before opening a PR. `main` had no contributor-facing guidance previously.

## [1.2.5] - 2026-05-05
### Fixed
- **UABB post-loop fix re-enabled on affected installs.** 1.2.4 restored the default for `uabb_post_loop_fix_enabled` to `1` and added a Features tab toggle, but `maybe_set_defaults()` only fills missing keys — so any site whose option was rebuilt by the 1.2.2 fatal-recovery path (or that fresh-installed 1.2.2/1.2.3) kept the value at `0` and had to enable the toggle by hand. Added a one-shot migration that flips the value to `1` once for affected installs and records `_dst_migrated_125_uabb_on` so the user can still toggle it off later without it bouncing back.

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
