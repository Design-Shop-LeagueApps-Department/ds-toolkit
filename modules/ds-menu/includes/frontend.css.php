<?php
/**
 * LeagueApps Menu — dynamic CSS from settings. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";
$open = "$node .ds-menu-wrap.ds-menu-open"; // overlay (open-state) scope prefix, used throughout the responsive block
$col = array( 'DS_Module_UI', 'color' );

$text       = $col( $settings->text_color ?? '' );
$hover      = $col( $settings->hover_color ?? '' );
$spacing    = isset( $settings->item_spacing ) && '' !== $settings->item_spacing ? (int) $settings->item_spacing : 20;
$barpv      = isset( $settings->bar_pad_v ) && '' !== $settings->bar_pad_v ? (int) $settings->bar_pad_v : 10; // top-level link vertical padding (compact item height)
$barph      = isset( $settings->bar_pad_h ) && '' !== $settings->bar_pad_h ? (int) $settings->bar_pad_h : 0; // top-level link horizontal padding (independent of the gap; blank = 0)
$hweight    = isset( $settings->hover_weight ) && '' !== $settings->hover_weight ? (int) $settings->hover_weight : 0; // bolder weight on hover / current
$dbg        = $col( $settings->dropdown_bg ?? 'var(--fl-global-white)' );
$dtext      = $col( $settings->dropdown_text ?? '' );
$dhover     = $col( $settings->dropdown_text_hover ?? '' );
$toggle_c   = $col( $settings->toggle_color ?? '' );
$obg        = $col( $settings->overlay_bg ?? 'var(--fl-global-dark-background)' );
$otext      = $col( $settings->overlay_text ?? 'var(--fl-global-white)' );
$osub       = $col( $settings->overlay_subtext ?? '' ) ?: $otext; // overlay submenu link colour (falls back to the menu item colour)
$otexthov   = $col( $settings->overlay_text_hover ?? '' );    // overlay menu item hover / active colour
$oitembg    = $col( $settings->overlay_item_bg ?? '' );       // overlay menu item row background
$osubhov    = $col( $settings->overlay_subtext_hover ?? '' ); // overlay submenu link hover colour
$osubbg     = $col( $settings->overlay_sub_bg ?? '' );        // overlay submenu link background
$dgap       = isset( $settings->dropdown_gap ) && '' !== $settings->dropdown_gap ? (int) $settings->dropdown_gap : 0; // desktop bar -> dropdown gap
$bp         = isset( $settings->breakpoint ) && '' !== $settings->breakpoint ? (int) $settings->breakpoint : 1000;
$align      = $settings->alignment ?? 'right';
$justify    = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'justify' => 'space-between' )[ $align ] ?? 'flex-end';

// Hamburger button (responsive). Defaults: transparent, borderless.
$tbg   = $col( $settings->toggle_bg ?? '' ) ?: 'transparent';
$tbc   = $col( $settings->toggle_border_color ?? '' );
$tbw   = isset( $settings->toggle_border_width ) && '' !== $settings->toggle_border_width ? (int) $settings->toggle_border_width : 0;
$trad  = isset( $settings->toggle_radius ) && '' !== $settings->toggle_radius ? (int) $settings->toggle_radius : 0;
$isz   = isset( $settings->toggle_icon_size ) && '' !== $settings->toggle_icon_size ? (int) $settings->toggle_icon_size : 26;
$ih    = max( 12, (int) round( $isz * 0.66 ) ); // bar-box height
$mid   = (int) round( $ih / 2 ) - 1;            // centre line for the X
$tbord = ( $tbw > 0 && $tbc ) ? "{$tbw}px solid {$tbc}" : '0';
?>
<?php /* Item Spacing is a real GAP between items (independent of the link's own padding). */ ?>
<?php echo $node; ?> .ds-menu { justify-content: <?php echo $justify; ?>; column-gap: <?php echo $spacing; ?>px; }
<?php
/* ---- Item divider: a small vertical rule between top-level items (desktop bar only).
        Rendered as a real flex item so it centres for every alignment incl. Justify. ---- */
$bds = $settings->bar_divider_style ?? 'none';
if ( in_array( $bds, array( 'solid', 'dashed', 'dotted' ), true ) ) {
	$bdc = $col( $settings->bar_divider_color ?? '' ) ?: 'var(--fl-global-line-color)';
	$bdh = isset( $settings->bar_divider_height ) && '' !== $settings->bar_divider_height ? (int) $settings->bar_divider_height : 18;
	$bdw = isset( $settings->bar_divider_width ) && '' !== $settings->bar_divider_width ? (int) $settings->bar_divider_width : 1;
	echo "$node .ds-menu > .ds-menu-sep { list-style: none; display: flex; align-items: center; }\n";
	echo "$node .ds-menu > .ds-menu-sep > span { display: block; width: 0; height: {$bdh}px; border-left: {$bdw}px {$bds} {$bdc}; }\n";
	echo "@media (max-width: {$bp}px) { $node .ds-menu-sep { display: none !important; } }\n";
}
?>
<?php echo $node; ?> .ds-menu > .ds-menu-item > a { padding: <?php echo $barpv; ?>px <?php echo $barph; ?>px; <?php if ( $text ) { echo 'color:' . $text . ';'; } ?> }
<?php if ( $hover ) : ?>
<?php echo $node; ?> .ds-menu > .ds-menu-item > a:hover,
<?php echo $node; ?> .ds-menu > .ds-menu-item:hover > a,
<?php echo $node; ?> .ds-menu > .ds-menu-item.current-menu-item > a { color: <?php echo $hover; ?>; }
<?php endif; ?>
<?php
/* Hover font weight: bolder top-level label on hover / current page. The weight is set on
   the label SPAN only (not the indicator), and a hidden bold copy (span::after, fed by the
   link's data-title) pre-reserves the wider width so the bar never reflows / shifts. */
if ( $hweight ) {
	$sp  = "$node .ds-menu > .ds-menu-item:not(.is-button) > a > span";
	$sph = "$node .ds-menu > .ds-menu-item:not(.is-button) > a:hover > span, $node .ds-menu > .ds-menu-item:not(.is-button):hover > a > span, $node .ds-menu > .ds-menu-item:not(.is-button).current-menu-item > a > span";
	echo "$sp::after { content: attr(data-title); display: block; height: 0; overflow: hidden; visibility: hidden; pointer-events: none; font-weight: {$hweight}; }\n";
	echo "$sph { font-weight: {$hweight}; }\n";
}
?>

<?php /* Desktop: the menu ITEM fills the full header-bar height (so its whole column is
         hoverable and the dropdown drops from the bar's bottom edge), but the LINK inside
         stays COMPACT — natural height set by its own vertical padding, vertically centred.
         This keeps the items tight (controlled by the Item Vertical Padding setting) instead
         of a tall full-height hit-area, while the dropdown still aligns to the bar bottom.
         Scoped to desktop so the mobile overlay is untouched; the CTA button is centred. */ ?>
@media (min-width: <?php echo $bp + 1; ?>px) {
	<?php echo $node; ?> { flex: 1 1 auto; }
	<?php echo $node; ?> > .fl-module-content { height: 100%; }
	<?php echo $node; ?> .ds-menu-wrap { height: 100%; }
	<?php echo $node; ?> .ds-menu { height: 100%; align-items: stretch; }
	<?php echo $node; ?> .ds-menu > .ds-menu-item { display: flex; align-items: center; }
	<?php echo $node; ?> .ds-menu > .ds-menu-item > a { height: auto; }
	<?php echo $node; ?> .ds-menu > .ds-menu-item.is-button { align-self: center; }
}

<?php
/* ===== Add-ons: bar hover animation, pill item backgrounds, dropdown hover highlight.
        All desktop-scoped (the mobile overlay below the breakpoint uses its own selectors). ===== */
$hanim  = $settings->hover_anim ?? 'none';
$hacol  = $col( $settings->hover_anim_color ?? '' ) ?: ( $hover ?: 'currentColor' );
$hasize = isset( $settings->hover_anim_size ) && '' !== $settings->hover_anim_size ? (int) $settings->hover_anim_size : 2;

$pill      = ( ( $settings->pill_enabled ?? 'no' ) === 'yes' );
$pill_bg   = $col( $settings->pill_bg ?? '' );
$pill_txt  = $col( $settings->pill_text ?? '' ) ?: ( $hover ?: '' );
$pill_bga  = $col( $settings->pill_bg_active ?? '' ) ?: $pill_bg;
$pill_txta = $col( $settings->pill_text_active ?? '' ) ?: $pill_txt;
$pill_rad  = isset( $settings->pill_radius ) && '' !== $settings->pill_radius ? (int) $settings->pill_radius : 999;
$pill_pv   = isset( $settings->pill_pad_v ) && '' !== $settings->pill_pad_v ? (int) $settings->pill_pad_v : 8;
$pill_ph   = isset( $settings->pill_pad_h ) && '' !== $settings->pill_pad_h ? (int) $settings->pill_pad_h : 16;

$dhbg  = $col( $settings->dropdown_hover_bg ?? '' );
$dhrad = isset( $settings->dropdown_hover_radius ) && '' !== $settings->dropdown_hover_radius ? (int) $settings->dropdown_hover_radius : null;

/* The CTA button (last item) is excluded from the bar hover animation — an underline /
   raise / grow on a button looks wrong; it keeps its own button hover treatment. */
$ta  = "$node .ds-menu > .ds-menu-item:not(.is-button) > a";                                   // top-level link (not the CTA button)
$tah = "$ta:hover, $node .ds-menu > .ds-menu-item:not(.is-button):hover > a, $node .ds-menu > .ds-menu-item:not(.is-button).current-menu-item > a"; // hover / active link
?>
@media (min-width: <?php echo $bp + 1; ?>px) {
<?php
// --- Top-level hover animation ---
if ( in_array( $hanim, array( 'underline', 'underline_center', 'underline_fade' ), true ) ) {
	$origin = ( 'underline_center' === $hanim ) ? 'center' : 'left center';
	echo "\t$ta { position: relative; }\n";
	if ( 'underline_fade' === $hanim ) {
		echo "\t$ta::after { content:''; position:absolute; left:0; right:0; bottom:2px; height:{$hasize}px; background:{$hacol}; opacity:0; transition:opacity .25s ease; pointer-events:none; }\n";
		echo "\t" . str_replace( ', ', "::after, ", $tah ) . "::after { opacity:1; }\n";
	} else {
		echo "\t$ta::after { content:''; position:absolute; left:0; right:0; bottom:2px; height:{$hasize}px; background:{$hacol}; transform:scaleX(0); transform-origin:{$origin}; transition:transform .25s ease; pointer-events:none; }\n";
		echo "\t" . str_replace( ', ', "::after, ", $tah ) . "::after { transform:scaleX(1); }\n";
	}
} elseif ( 'raise' === $hanim || 'grow' === $hanim ) {
	$tf = ( 'grow' === $hanim ) ? 'scale(1.08)' : 'translateY(-2px)';
	echo "\t$ta > span { transition: transform .2s ease; }\n";
	echo "\t" . str_replace( ', ', " > span, ", $tah ) . " > span { transform: {$tf}; }\n";
}

// --- Menu-bar pill item backgrounds ---
if ( $pill ) {
	$pi = "$node .ds-menu > .ds-menu-item:not(.is-button)";
	$pa = "$pi > a";
	echo "\t$pi { align-items: center; }\n";
	echo "\t$pa { height: auto; padding: {$pill_pv}px {$pill_ph}px; border-radius: {$pill_rad}px; background: transparent; transition: background-color .2s ease, color .15s ease; }\n";
	$decl = ( $pill_bg ? "background:{$pill_bg};" : '' ) . ( $pill_txt ? "color:{$pill_txt};" : '' );
	if ( $decl ) { echo "\t$pi:hover > a, $pa:hover { {$decl} }\n"; }
	$decla = ( $pill_bga ? "background:{$pill_bga};" : '' ) . ( $pill_txta ? "color:{$pill_txta};" : '' );
	if ( $decla ) { echo "\t$pi.current-menu-item > a { {$decla} }\n"; }
}

// --- Dropdown / mega item hover highlight ---
if ( $dhbg ) {
	$rad = ( null !== $dhrad ) ? " border-radius:{$dhrad}px;" : '';
	echo "\t$node .ds-submenu a, $node .ds-mega a { transition: background-color .15s ease, color .15s ease;{$rad} }\n";
	echo "\t$node .ds-submenu a:hover, $node .ds-mega a:hover { background: {$dhbg}; }\n";
}
?>
}

<?php /* Dropdown + mega panel surface */ ?>
<?php echo $node; ?> .ds-submenu, <?php echo $node; ?> .ds-mega { background: <?php echo $dbg ?: 'var(--fl-global-white)'; ?>; }
<?php if ( $dtext ) : ?><?php echo $node; ?> .ds-submenu a, <?php echo $node; ?> .ds-mega a { color: <?php echo $dtext; ?>; }<?php endif; ?>
<?php if ( $dhover ) : ?><?php echo $node; ?> .ds-submenu a:hover, <?php echo $node; ?> .ds-mega a:hover { color: <?php echo $dhover; ?>; }<?php endif; ?>
<?php
/* Dropdown / mega panel corner radius (blank keeps the css/frontend.css default). */
$dradius = isset( $settings->dropdown_radius ) && '' !== $settings->dropdown_radius ? (int) $settings->dropdown_radius : null;
if ( null !== $dradius ) { echo "$node .ds-submenu, $node .ds-mega { border-radius: {$dradius}px; }\n"; }

/* ---- Mega panel: width + alignment (desktop only; the mobile overlay block below
        forces full-width stacked panels, so these never affect mobile). ---- */
$mw    = $settings->mega_width ?? 'auto';
$mal   = $settings->mega_align ?? 'left';
$mmax  = ( isset( $settings->mega_max_width ) && '' !== $settings->mega_max_width ) ? (int) $settings->mega_max_width : 0;
$mItem = "$node .ds-menu > .ds-menu-item.is-mega";
$mPan  = "$mItem > .ds-mega";
echo "@media (min-width:" . ( $bp + 1 ) . "px){\n";
if ( 'bar' === $mw ) {
	// Span the menu bar: the item goes static so the panel anchors to .ds-menu-wrap.
	echo "\t$mItem { position: static; }\n";
	echo "\t$mPan { left: 0; right: 0; width: auto; min-width: 0; max-width: none; }\n";
} elseif ( 'viewport' === $mw ) {
	echo "\t$mItem { position: static; }\n";
	echo "\t$mPan { left: 50%; right: auto; width: 100vw; max-width: 100vw; min-width: 0; margin-left: -50vw; }\n";
} else { // auto (sized to columns), aligned relative to the item
	if ( 'center' === $mal ) {
		// Override both rest + open transforms so the translateY reveal is preserved.
		echo "\t$mPan { left: 50%; right: auto; transform: translate(-50%, 6px); }\n";
		echo "\t$mItem:hover > .ds-mega, $mItem:focus-within > .ds-mega { transform: translate(-50%, 0); }\n";
	} elseif ( 'right' === $mal ) {
		echo "\t$mPan { left: auto; right: 0; }\n";
	}
	if ( $mmax ) { echo "\t$mPan { max-width: {$mmax}px; }\n"; }
}
echo "}\n";

/* ---- Mega column heading style (the bold link atop each mega column) ----
   Selector includes .ds-mega-col so these out-rank the generic ".ds-mega a"
   submenu-link color/typography rules (which carry a type selector). */
$mhSel  = "$node .ds-mega-col .ds-mega-head";
$mh_col = $col( $settings->mega_head_color ?? '' );
$mh_hov = $col( $settings->mega_head_hover_color ?? '' );
if ( '' !== $mh_col ) { echo "$mhSel { color: {$mh_col}; }\n"; }
if ( '' !== $mh_hov ) { echo "$mhSel:hover, $mhSel:focus { color: {$mh_hov}; }\n"; }
$mh_div = $settings->mega_head_divider ?? 'none';
if ( in_array( $mh_div, array( 'solid', 'dashed', 'dotted' ), true ) ) {
	$mh_dc = $col( $settings->mega_head_divider_color ?? '' );
	if ( '' === $mh_dc ) { $mh_dc = 'rgba(0,0,0,.14)'; }
	echo "$mhSel { border-bottom: 1px {$mh_div} {$mh_dc}; padding-bottom: 6px; margin-bottom: 4px; }\n";
}

/* Mega sub-item indent: left padding for the links listed under a column heading
   (e.g. teams under a "U16" heading). 3-class selector beats the ".ds-mega a" rule. */
$mh_ind = ( isset( $settings->mega_sub_indent ) && '' !== $settings->mega_sub_indent ) ? (int) $settings->mega_sub_indent : null;
if ( null !== $mh_ind ) {
	echo "$node .ds-mega-col .ds-submenu a { padding-left: {$mh_ind}px; }\n";
}
?>

<?php /* Desktop: gap between the menu bar and the dropdown / mega panel. The ::before is a
         transparent bridge over the gap so hovering across it doesn't drop the panel. */ ?>
<?php if ( $dgap > 0 ) : ?>
<?php echo $node; ?> .ds-menu > .ds-menu-item > .ds-submenu,
<?php echo $node; ?> .ds-menu > .ds-menu-item > .ds-mega { margin-top: <?php echo $dgap; ?>px; }
<?php echo $node; ?> .ds-menu > .ds-menu-item > .ds-submenu::before,
<?php echo $node; ?> .ds-menu > .ds-menu-item > .ds-mega::before { content: ''; position: absolute; left: 0; right: 0; top: -<?php echo $dgap; ?>px; height: <?php echo $dgap; ?>px; }
<?php endif; ?>

<?php
/* Dropdown / mega item link padding (desktop). Blank = theme default; the mobile overlay
   keeps its own padding via higher-specificity rules, so this only affects desktop. */
$dpv = isset( $settings->dropdown_pad_v ) && '' !== $settings->dropdown_pad_v ? (int) $settings->dropdown_pad_v : null;
$dph = isset( $settings->dropdown_pad_h ) && '' !== $settings->dropdown_pad_h ? (int) $settings->dropdown_pad_h : null;
if ( null !== $dpv || null !== $dph ) {
	$decl = '';
	if ( null !== $dpv ) { $decl .= "padding-top:{$dpv}px;padding-bottom:{$dpv}px;"; }
	if ( null !== $dph ) { $decl .= "padding-left:{$dph}px;padding-right:{$dph}px;"; }
	echo "$node .ds-submenu .ds-menu-item > a { {$decl} }\n";
}
?>

<?php
/* Dropdown / submenu divider (between consecutive items). */
$dd_style = $settings->dropdown_divider_style ?? 'none';
if ( in_array( $dd_style, array( 'solid', 'dashed', 'dotted' ), true ) ) {
	$dd_color = $col( $settings->dropdown_divider_color ?? '' ) ?: '#eeeeee';
	$dd_width = isset( $settings->dropdown_divider_width ) && '' !== $settings->dropdown_divider_width ? (int) $settings->dropdown_divider_width : 1;
	echo "$node .ds-submenu > .ds-menu-item + .ds-menu-item { border-top: {$dd_width}px {$dd_style} {$dd_color}; }\n";
}
?>

<?php
/* CTA button (last top-level item). The border radius always syncs from the site's
   global Button (Theme Setting -> Elements -> Button) unless overridden; in "global"
   mode the colors + typography sync too. */
$cta_global = false;
if ( ( $settings->last_item_button ?? 'no' ) === 'yes' ) {
	$cta       = "$node .ds-menu > .ds-menu-item.is-button > a";
	$cta_hover = "$node .ds-menu > .ds-menu-item.is-button:hover > a, $node .ds-menu > .ds-menu-item.is-button > a:hover";
	$bpad      = isset( $settings->button_padding ) && '' !== $settings->button_padding ? (int) $settings->button_padding : 24;

	// Global Button settings, read once. Border-radius is the button border compound (TL TR BR BL).
	$gs          = class_exists( 'FLBuilderGlobalStyles' ) ? FLBuilderGlobalStyles::get_settings( false ) : null;
	$gbtn_radius = '';
	if ( $gs ) {
		$bb = isset( $gs->button_border ) ? (array) $gs->button_border : array();
		$r  = isset( $bb['radius'] ) ? (array) $bb['radius'] : array();
		$tl = $r['top_left'] ?? ''; $tr = $r['top_right'] ?? ''; $bl = $r['bottom_left'] ?? ''; $brr = $r['bottom_right'] ?? '';
		if ( '' !== ( $tl . $tr . $bl . $brr ) ) {
			$u = function( $v ) { return ( '' === $v ? '0' : (int) $v ) . 'px'; };
			$gbtn_radius = $u( $tl ) . ' ' . $u( $tr ) . ' ' . $u( $brr ) . ' ' . $u( $bl );
		}
	}

	if ( $gs && ( $settings->button_global ?? 'yes' ) === 'yes' ) {
		$cta_global = true;
		// FULL theme Button sync (bg, text, hover, border + radius + shadow, typography).
		DS_Module_UI::global_button_css( $cta, $cta_hover );
		echo "$cta { padding:0.6em {$bpad}px; }\n";
	} else {
		$bbg    = $col( $settings->button_bg ?? '' ) ?: 'var(--fl-global-button)';
		$btext  = $col( $settings->button_text ?? '' ) ?: 'var(--fl-global-white)';
		$bbgh   = $col( $settings->button_bg_hover ?? '' ) ?: $bbg;
		$btexth = $col( $settings->button_text_hover ?? '' ) ?: $btext;
		// Blank radius syncs from the global Button radius; set a value to override
		// (final fallback var(--ds-radius) only if the global Button has no radius set).
		if ( isset( $settings->button_radius ) && '' !== $settings->button_radius ) {
			$brad_css = (int) $settings->button_radius . 'px';
		} else {
			$brad_css = $gbtn_radius ?: 'var(--ds-radius)';
		}
		echo "$cta { background: {$bbg}; color: {$btext}; border-radius: {$brad_css}; padding: 0.6em {$bpad}px; }\n";
		echo "$cta_hover { background: {$bbgh}; color: {$btexth}; }\n";
	}
}
?>

<?php
/* Typography (responsive-aware): bar items, submenu/mega labels, CTA button. */
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::typography_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'typography',
		'selector'     => "$node .ds-menu > .ds-menu-item > a",
	) );
	FLBuilderCSS::typography_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'dropdown_typography',
		'selector'     => "$node .ds-submenu a, $node .ds-mega a",
	) );
	FLBuilderCSS::typography_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'mega_head_typography',
		'selector'     => "$node .ds-mega-col .ds-mega-head",
	) );
	if ( empty( $cta_global ) ) {
		FLBuilderCSS::typography_field_rule( array(
			'settings'     => $settings,
			'setting_name' => 'button_typography',
			'selector'     => "$node .ds-menu > .ds-menu-item.is-button > a",
		) );
	}
	// Hamburger label (only visible once the toggle shows on mobile).
	FLBuilderCSS::typography_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'toggle_label_typography',
		'selector'     => "$node .ds-menu-toggle-label",
	) );
}
?>

/* ===== Responsive: full-screen hamburger overlay at/below <?php echo $bp; ?>px ===== */
@media (max-width: <?php echo $bp; ?>px) {
	<?php echo $node; ?> .ds-menu { display: none; }
	<?php echo $node; ?> .ds-sub-ind { display: none; }

	/* Hamburger button. The `.ds-menu-wrap` scope out-weighs a theme's `header button`
	   rule, and bg/border/color are written with !important so theme button styling
	   can't leak in (the bars + label pick the colour up via currentColor/inherit). */
	<?php echo $node; ?> .ds-menu-wrap .ds-menu-toggle { display: flex; margin-left: auto; <?php if ( $toggle_c ) { echo 'color:' . $toggle_c . ' !important;'; } ?> background: <?php echo $tbg; ?> !important; border: <?php echo $tbord; ?> !important; border-radius: <?php echo $trad; ?>px; box-shadow: none !important; padding: 6px 10px; }
	<?php /* EVERY descendant must inherit (not just label + bar spans): BB's global
	   button styles paint `button *` — including the intermediate .ds-bars div —
	   and `inherit` only reads the DIRECT parent, so skipping one element in the
	   chain reintroduces the theme colour (measured live: spans went white via
	   .ds-bars). !important also beats site-level workaround CSS left over from
	   before this option worked (`.ds-bars span{color:black!important}`). inherit
	   tracks the toggle, so the open-overlay X keeps following Overlay text. */ ?>
	<?php if ( $toggle_c ) { ?><?php echo $node; ?> .ds-menu-wrap .ds-menu-toggle * { color: inherit !important; }<?php } ?>
	<?php echo $node; ?> .ds-bars { width: <?php echo $isz; ?>px; height: <?php echo $ih; ?>px; }
	<?php echo $node; ?> .ds-bars span:nth-child(1) { top: 0; }
	<?php echo $node; ?> .ds-bars span:nth-child(2) { top: <?php echo $mid; ?>px; }
	<?php echo $node; ?> .ds-bars span:nth-child(3) { top: <?php echo $ih - 2; ?>px; }
	<?php echo $open; ?> .ds-bars span:nth-child(1),
	<?php echo $open; ?> .ds-bars span:nth-child(3) { top: <?php echo $mid; ?>px; }

	<?php echo $open; ?> .ds-menu {
		display: flex; flex-direction: column; flex-wrap: nowrap; align-items: stretch; gap: 0;
		position: fixed; inset: 0; z-index: 100000; overflow-y: auto;
		padding: 76px 22px 40px; background: <?php echo $obg ?: 'var(--fl-global-dark-background)'; ?>;
		justify-content: flex-start;
	}
	/* Pin the open hamburger to the viewport corner so it can never overlap the items
	   (it sits in the header flow otherwise, which is what caused the overlap). */
	<?php echo $open; ?> .ds-menu-toggle { position: fixed; top: 16px; right: 18px; margin: 0; z-index: 100001; color: <?php echo $otext; ?> !important; background: transparent !important; border-color: <?php echo $otext; ?>; }

	<?php
	/* Overlay item divider. */
	$md_style = $settings->mobile_divider_style ?? 'solid';
	if ( in_array( $md_style, array( 'solid', 'dashed', 'dotted' ), true ) ) {
		$md_color  = $col( $settings->mobile_divider_color ?? '' ) ?: 'rgba(255,255,255,.12)';
		$md_width  = isset( $settings->mobile_divider_width ) && '' !== $settings->mobile_divider_width ? (int) $settings->mobile_divider_width : 1;
		$md_border = "{$md_width}px {$md_style} {$md_color}";
	} else {
		$md_border = '0';
	}
	?>
	<?php if ( ( $settings->mobile_menu_style ?? 'drill' ) !== 'overlay' ) : ?>
	<?php
	/* Drill-down drawer (default mobile style). The legacy overlay panel is hidden;
	   the drawer inherits ALL the module's Mobile Overlay settings as custom props,
	   so nothing is hard-coded — colours, dividers, and the CTA follow the fields. */
	$dr_mbg    = $col( $settings->button_mobile_bg ?? '' ) ?: $otext;
	$dr_mtext  = $col( $settings->button_mobile_text ?? '' ) ?: ( $obg ?: 'var(--fl-global-dark-background)' );
	$dr_mbgh   = $col( $settings->button_mobile_bg_hover ?? '' ) ?: $dr_mbg;
	$dr_mtexth = $col( $settings->button_mobile_text_hover ?? '' ) ?: $dr_mtext;
	?>
	<?php echo $open; ?> .ds-menu { display: none !important; }
	<?php echo $node; ?> .ds-drill {
		--ds-drill-bg: <?php echo $obg ?: 'var(--fl-global-dark-background)'; ?>;
		--ds-drill-text: <?php echo $otext; ?>;
		--ds-drill-sub: <?php echo $osub; ?>;
		--ds-drill-accent: <?php echo $otexthov ?: 'var(--fl-global-accent)'; ?>;
		--ds-drill-cta-bg: <?php echo $dr_mbg; ?>;
		--ds-drill-cta-text: <?php echo $dr_mtext; ?>;
		--ds-drill-cta-bg-hover: <?php echo $dr_mbgh; ?>;
		<?php if ( $oitembg ) : ?>--ds-drill-row-bg: <?php echo $oitembg; ?>;<?php endif; ?>
	}
	<?php echo $node; ?> .ds-drill-cta:hover { color: <?php echo $dr_mtexth; ?> !important; }
	<?php $dlh = ( isset( $settings->drill_logo_height ) && '' !== $settings->drill_logo_height ) ? (int) $settings->drill_logo_height : 30; ?>
	<?php echo $node; ?> .ds-drill-brand img { height: <?php echo $dlh; ?>px; }
	<?php echo $node; ?> .ds-drill .ds-drill-row, <?php echo $node; ?> .ds-drill .ds-drill-back, <?php echo $node; ?> .ds-drill .ds-drill-overview { border-bottom: <?php echo $md_border; ?> !important; }
	<?php if ( $oitembg ) : ?>
	<?php /* Mirror the overlay's persistent top-level item background. */ ?>
	<?php echo $node; ?> .ds-drill .ds-drill-panel.is-root .ds-drill-row:not(.ds-drill-cta) { background: <?php echo $oitembg; ?> !important; }
	<?php endif; ?>
	<?php endif; ?>
	/* Overlay items: stacked, large, full width */
	<?php echo $open; ?> .ds-menu-item { position: static; width: 100%; border-bottom: <?php echo $md_border; ?>; }
	<?php echo $open; ?> .ds-menu-item > a { padding: 14px 4px; color: <?php echo $otext; ?>; flex: 1; }
	<?php /* 20px default for TOP-LEVEL items only (mobile_typography overrides). Scoping
	         to direct children keeps it off submenu links so their font size stays controllable. */ ?>
	<?php echo $open; ?> .ds-menu > .ds-menu-item > a { font-size: 20px; }
	<?php echo $open; ?> .ds-menu-item { display: flex; flex-wrap: wrap; align-items: center; }

	<?php if ( ( $settings->last_item_button ?? 'no' ) === 'yes' ) : ?>
	<?php
	// Overlay CTA colours. Default is INVERTED (overlay text-colour fill, overlay-bg text)
	// so a green-on-green pill never vanishes; each is overridable per the mobile fields.
	$mbg    = $col( $settings->button_mobile_bg ?? '' ) ?: $otext;
	$mtext  = $col( $settings->button_mobile_text ?? '' ) ?: ( $obg ?: 'var(--fl-global-dark-background)' );
	$mbgh   = $col( $settings->button_mobile_bg_hover ?? '' ) ?: $mbg;
	$mtexth = $col( $settings->button_mobile_text_hover ?? '' ) ?: $mtext;
	$mfull  = ( $settings->button_full_mobile ?? 'yes' ) === 'yes';
	?>
	<?php echo $open; ?> .ds-menu-item.is-button { border-bottom: 0; margin: 18px 0 0; }
	<?php echo $open; ?> .ds-menu-item.is-button > a { background: <?php echo $mbg; ?>; color: <?php echo $mtext; ?>; <?php echo $mfull ? 'flex: 1 1 100%; justify-content: center;' : 'flex: 0 0 auto;'; ?> }
	<?php echo $open; ?> .ds-menu-item.is-button:hover > a,
	<?php echo $open; ?> .ds-menu-item.is-button > a:hover { background: <?php echo $mbgh; ?>; color: <?php echo $mtexth; ?>; }
	<?php endif; ?>

	/* Submenus + mega: smooth height-animated accordion. Kept rendered (not display:none)
	   and collapsed via height:0 so JS can animate height -> scrollHeight for a smooth open. */
	<?php echo $open; ?> .ds-submenu,
	<?php echo $open; ?> .ds-mega {
		position: static; opacity: 1; visibility: visible; transform: none; box-shadow: none;
		display: block; grid-template-columns: 1fr; width: 100%; min-width: 0; background: transparent;
		height: 0; overflow: hidden; margin-top: 0; padding: 0 0 0 14px;
		transition: height .3s ease;
	}
	<?php echo $open; ?> .ds-submenu::before,
	<?php echo $open; ?> .ds-mega::before { display: none; }
	<?php echo $open; ?> .ds-menu-item.ds-open > .ds-submenu,
	<?php echo $open; ?> .ds-menu-item.ds-open > .ds-mega { padding-bottom: 8px; }
	<?php /* The mega's column sublists are part of the mega itself, not separate accordions. */ ?>
	<?php echo $open; ?> .ds-mega .ds-submenu { height: auto; overflow: visible; padding: 0; transition: none; }
	<?php echo $open; ?> .ds-mega-col { width: 100%; margin-bottom: 4px; }
	<?php echo $open; ?> .ds-submenu a,
	<?php echo $open; ?> .ds-mega a { color: <?php echo $osub; ?>; opacity: .92; padding: 9px 4px; }
	<?php echo $open; ?> .ds-sub-toggle { display: block; color: <?php echo $otext; ?>; flex: 0 0 auto; }

	<?php /* Optional per-item backgrounds + hover/active colours in the overlay. */ ?>
	<?php if ( $oitembg ) : ?><?php echo $open; ?> .ds-menu > .ds-menu-item:not(.is-button) { background: <?php echo $oitembg; ?>; }
	<?php endif; ?>
	<?php if ( $otexthov ) : ?><?php echo $open; ?> .ds-menu > .ds-menu-item > a:hover,
	<?php echo $open; ?> .ds-menu > .ds-menu-item.current-menu-item > a { color: <?php echo $otexthov; ?>; }
	<?php endif; ?>
	<?php if ( $osubbg ) : ?><?php echo $open; ?> .ds-submenu a,
	<?php echo $open; ?> .ds-mega a { background: <?php echo $osubbg; ?>; }
	<?php endif; ?>
	<?php if ( $osubhov ) : ?><?php echo $open; ?> .ds-submenu a:hover,
	<?php echo $open; ?> .ds-mega a:hover,
	<?php echo $open; ?> .ds-submenu .current-menu-item > a { color: <?php echo $osubhov; ?>; }
	<?php endif; ?>
}

<?php
/*
 * Mobile overlay label typography. The selector is scoped to the OPEN overlay,
 * which only happens on mobile, so it is safe to emit outside the media query
 * (typography_field_rule manages its own responsive breakpoints). Emitted last
 * so it overrides the 20px default set above.
 */
if ( class_exists( 'FLBuilderCSS' ) ) {
	// Top-level labels: overlay rows AND the drill drawer's root-panel rows.
	FLBuilderCSS::typography_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'mobile_typography',
		'selector'     => "$open .ds-menu > .ds-menu-item > a, $node .ds-drill-panel.is-root",
	) );
	// Mobile submenu / mega labels. Overlay-scoped selector (specificity beats the
	// desktop dropdown_typography), so it wins inside the overlay; if left empty the
	// desktop "Submenu Label Typography" carries through as the fallback. Also
	// covers the drill drawer's deeper-panel rows.
	FLBuilderCSS::typography_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'mobile_subtypography',
		'selector'     => "$open .ds-submenu a, $open .ds-mega a, $node .ds-drill-panel:not(.is-root)",
	) );
}
?>
