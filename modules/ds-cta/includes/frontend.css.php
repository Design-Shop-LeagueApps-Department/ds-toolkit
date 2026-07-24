<?php
/**
 * Leagueapps CTA — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node  = ".fl-node-$id";
$style = isset( $settings->cta_style ) ? preg_replace( '/[^a-z0-9_]/', '', $settings->cta_style ) : 'style1';

$col = array( 'DS_Module_UI', 'color' );
$u = array( 'DS_Module_UI', 'u' );
$mix = function( $color, $op ) use ( $col ) {
	$c = $col( $color );
	if ( '' === $c ) { return ''; }
	$op = max( 0, min( 100, (int) $op ) );
	return "color-mix(in srgb, {$c} {$op}%, transparent)";
};

$g   = FLBuilderModel::get_global_settings();
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();

// ---- Section background ----
$sbg = $col( $settings->section_bg ?? '' );
if ( '' !== $sbg ) { echo "$node .ds-cta { background: {$sbg}; }\n"; }

// ---- Content width (boxed / full / custom) so the CTA can fill a full-width container ----
$cw = $settings->content_width ?? 'boxed';
if ( 'full' === $cw ) {
	echo "$node .ds-cta-wrap { max-width: none; margin: 0; }\n";
} elseif ( 'custom' === $cw ) {
	$cmw = $u( $settings->content_max_width ?? '', 1280 );
	echo "$node .ds-cta-wrap { max-width: {$cmw}px; margin: 0 auto; }\n";
}

// ---- Grid (responsive) ----
$cols = $u( $settings->columns ?? '', 4 );
$gap  = $u( $settings->gap ?? '', 20 );
echo "$node .ds-cta-grid { grid-template-columns: repeat({$cols}, 1fr); gap: {$gap}px; }\n";
// Always emit node-scoped responsive rules so they beat the desktop base rule
// (a node-scoped base would otherwise out-specify the static media queries and
// keep N columns on phones). Defaults: tablet = min(cols,2), mobile = 1.
$colsM = $u( $settings->columns_medium ?? '', min( $cols, 2 ) );
$gapM  = $u( $settings->gap_medium ?? '', $gap );
echo "@media (max-width:{$bpm}px){ $node .ds-cta-grid { grid-template-columns: repeat({$colsM},1fr); gap: {$gapM}px; } }\n";
$colsR = $u( $settings->columns_responsive ?? '', 1 );
$gapR  = $u( $settings->gap_responsive ?? '', max( 12, $gap ) );
echo "@media (max-width:{$bpr}px){ $node .ds-cta-grid { grid-template-columns: repeat({$colsR},1fr); gap: {$gapR}px; } }\n";

// ---- Header colours (shared) ----
$pairs = array(
	'.ds-cta-heading'    => $settings->header_color ?? '',
	'.ds-cta-accent'     => $settings->header_accent_color ?? '',
	'.ds-cta-head-label' => $settings->header_label_color ?? '',
);
foreach ( $pairs as $sel => $v ) { $c = $col( $v ); if ( '' !== $c ) { echo "$node $sel { color: {$c}; }\n"; } }

// Shared overlay values + accent / title colours.
$ocolor = $settings->overlay_color ?? 'var(--fl-global-dark-background)';
$otop   = $mix( $ocolor, $settings->overlay_top ?? 10 );
$obot   = $mix( $ocolor, $settings->overlay_bottom ?? 92 );
$accent = $col( $settings->accent_color ?? '' );
$tcolor = $col( $settings->title_color ?? '' );

// Shared hover-overlay (colour + blend) — used by Style 1 (cards) and Style 2 (tiles).
$ohmix   = $mix( $settings->overlay_hover_color ?? '', $settings->overlay_hover_opacity ?? 70 );
$ohblend = preg_replace( '/[^a-z-]/', '', (string) ( $settings->overlay_hover_blend ?? 'normal' ) );
if ( ! in_array( $ohblend, array( 'normal', 'multiply', 'screen', 'overlay', 'soft-light', 'hard-light', 'color', 'hue', 'saturation', 'luminosity', 'difference', 'exclusion', 'darken', 'lighten', 'color-dodge', 'color-burn' ), true ) ) { $ohblend = 'normal'; }

if ( 'style3' === $style ) {
	// ---- Style 3: bento grid ----
	$cols3 = $u( $settings->columns ?? '', 4 );
	$gap3  = $u( $settings->gap ?? '', 16 );
	$rh    = $u( $settings->row_height ?? '', 220 );
	echo "$node .ds-cta-bento { grid-template-columns: repeat({$cols3}, 1fr); gap: {$gap3}px; grid-auto-rows: {$rh}px; }\n";
	// Responsive: honour the Columns / Gap / Row Height tablet + mobile values; blank
	// keeps the original defaults (tablet 2-up with auto rows, mobile 1-up). Span resets
	// use !important to beat the per-cell inline spans.
	$cols3m = ( '' !== ( $settings->columns_medium ?? '' ) ) ? max( 1, (int) $settings->columns_medium ) : 2;
	$cols3r = ( '' !== ( $settings->columns_responsive ?? '' ) ) ? max( 1, (int) $settings->columns_responsive ) : 1;
	$gap3m  = ( '' !== ( $settings->gap_medium ?? '' ) ) ? ' gap: ' . (int) $settings->gap_medium . 'px;' : '';
	$gap3r  = ( '' !== ( $settings->gap_responsive ?? '' ) ) ? ' gap: ' . (int) $settings->gap_responsive . 'px;' : '';
	$rhm    = ( '' !== ( $settings->row_height_medium ?? '' ) ) ? ( (int) $settings->row_height_medium ) . 'px' : 'auto';
	$rhr    = ( '' !== ( $settings->row_height_responsive ?? '' ) ) ? ' grid-auto-rows: ' . (int) $settings->row_height_responsive . 'px;' : '';
	echo "@media (max-width:{$bpm}px){ $node .ds-cta-bento { grid-template-columns: repeat({$cols3m},1fr); grid-auto-rows: {$rhm};{$gap3m} } $node .ds-cta-bento-cell { grid-column: span 1 !important; grid-row: span 1 !important; } $node .ds-cta-bento-img { min-height: {$rh}px; } }\n";
	echo "@media (max-width:{$bpr}px){ $node .ds-cta-bento { grid-template-columns: repeat({$cols3r},1fr);{$gap3r}{$rhr} } }\n";
	$cellbg = $col( $settings->cell_bg ?? '' );
	if ( '' !== $cellbg ) { echo "$node .ds-cta-bento-text { background: {$cellbg}; }\n"; }
	$imgbg = $col( $settings->img_cell_bg ?? '' );
	if ( '' !== $imgbg ) { echo "$node .ds-cta-bento-img { background: {$imgbg}; }\n"; }
	if ( $tcolor ) { echo "$node .ds-cta-bento-title { color: {$tcolor}; }\n"; }

	// Cell corner radius (image + text cells).
	$brad = ( isset( $settings->bento_radius ) && '' !== $settings->bento_radius ) ? ( (int) $settings->bento_radius ) . 'px' : 'var(--ds-radius)';
	echo "$node .ds-cta-bento-cell { border-radius: {$brad}; }\n";

	// Cell border (blank/0 width = none).
	$bsh = array( 'soft' => '0 4px 14px rgba(0,0,0,.10)', 'medium' => '0 8px 24px rgba(0,0,0,.16)', 'strong' => '0 16px 40px rgba(0,0,0,.24)' )[ $settings->bento_shadow ?? '' ] ?? '';
	if ( '' !== $bsh ) { echo "$node .ds-cta-bento-cell { box-shadow: {$bsh}; }\n"; }
	$bbw = ( isset( $settings->bento_border_width ) && '' !== $settings->bento_border_width ) ? (int) $settings->bento_border_width : 0;
	if ( $bbw > 0 ) {
		$bbc = $col( $settings->bento_border_color ?? '' ) ?: 'var(--fl-global-line-color)';
		echo "$node .ds-cta-bento-cell { border: {$bbw}px solid {$bbc}; }\n";
	}

	// Cell eyebrow colour (blank = static defaults: accent on text cells, white pill on image cells).
	$beye = $col( $settings->bento_eyebrow_color ?? '' );
	if ( '' !== $beye ) { echo "$node .ds-cta-bento-eyebrow { color: {$beye}; }\n"; }
	if ( class_exists( 'FLBuilderCSS' ) ) {
		FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'bento_eyebrow_typography', 'selector' => "$node .ds-cta-bento-eyebrow" ) );
	}

	// Bento button: respect the Theme Setting global Button by default.
	$btn_global = ( $settings->btn_global ?? 'yes' ) === 'yes';
	$gs = class_exists( 'FLBuilderGlobalStyles' ) ? FLBuilderGlobalStyles::get_settings( false ) : null;
	$gradius = '';
	if ( $gs ) {
		$bb = isset( $gs->button_border ) ? (array) $gs->button_border : array();
		$r  = isset( $bb['radius'] ) ? (array) $bb['radius'] : array();
		$tl = $r['top_left'] ?? ''; $tr = $r['top_right'] ?? ''; $bl = $r['bottom_left'] ?? ''; $brr = $r['bottom_right'] ?? '';
		if ( '' !== ( $tl . $tr . $bl . $brr ) ) { $ru = function( $v ) { return ( '' === $v ? '0' : (int) $v ) . 'px'; }; $gradius = $ru( $tl ) . ' ' . $ru( $tr ) . ' ' . $ru( $brr ) . ' ' . $ru( $bl ); }
	}
	if ( $btn_global && $gs ) {
		// FULL theme Button sync (bg, text, hover, border + radius + shadow, typography).
		DS_Module_UI::global_button_css( "$node .ds-cta-bento-btn" );
	} elseif ( $accent ) {
		echo "$node .ds-cta-bento-btn { background: {$accent} !important; color: var(--fl-global-white) !important; }\n";
	}

	$typo = array( 'heading_typography' => '.ds-cta-heading', 'title_typography' => '.ds-cta-bento-title', 'eyebrow_typography' => '.ds-cta-bento-desc' );
} elseif ( 'style2' === $style ) {
	// ---- Style 2: bordered tiles ----
	$mh  = $u( $settings->min_height ?? '', 430 );
	echo "$node .ds-cta-tile { min-height: {$mh}px; }\n";
	if ( '' !== ( $settings->min_height_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-cta-tile { min-height: " . (int) $settings->min_height_medium . "px; } }\n"; }
	if ( '' !== ( $settings->min_height_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-cta-tile { min-height: " . (int) $settings->min_height_responsive . "px; } }\n"; }

	$rad = ( isset( $settings->card_radius ) && '' !== $settings->card_radius ) ? ( (int) $settings->card_radius ) . 'px' : 'var(--ds-radius)';
	$bw  = $u( $settings->border_width ?? '', 3 );
	$bc  = $col( $settings->border_color ?? 'var(--fl-global-dark-background)' );
	$bch = $col( $settings->border_hover_color ?? 'var(--fl-global-accent)' );
	echo "$node .ds-cta-tile { border-radius: {$rad}; border-style: solid; border-width: {$bw}px;" . ( $bc ? " border-color: {$bc};" : '' ) . " }\n";
	if ( $bch ) { echo "$node .ds-cta-tile:hover { border-color: {$bch}; }\n"; }

	if ( $obot ) {
		$omid = $mix( $ocolor, $settings->overlay_top ?? 10 );
		echo "$node .ds-cta-tile-overlay { background: linear-gradient(to top, {$obot} 8%, {$omid} 60%, transparent 100%); }\n";
	}
	// Hover overlay colour + blend.
	if ( '' !== $ohmix )         { echo "$node .ds-cta-tile:hover .ds-cta-tile-overlay { background: {$ohmix}; }\n"; }
	if ( 'normal' !== $ohblend ) { echo "$node .ds-cta-tile:hover .ds-cta-tile-overlay { mix-blend-mode: {$ohblend}; }\n"; }
	if ( $accent ) { echo "$node .ds-cta-tile-num { color: {$accent}; }\n"; }
	if ( $tcolor ) { echo "$node .ds-cta-tile-title { color: {$tcolor}; }\n"; }

	$typo = array( 'heading_typography' => '.ds-cta-heading', 'title_typography' => '.ds-cta-tile-title', 'eyebrow_typography' => '.ds-cta-tile-num' );
} elseif ( 'style4' === $style ) {
	// ---- Style 4: big hero contact CTA ----

	// Background image: position, size, repeat, attachment.
	$pos_allow = array( 'left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom', 'top', 'center', 'bottom' );
	$bgpos     = in_array( $settings->hero_bg_position ?? 'center center', $pos_allow, true ) ? ( $settings->hero_bg_position ?? 'center center' ) : 'center center';

	$size_sel = $settings->hero_bg_size ?? 'cover';
	if ( 'custom' === $size_sel ) {
		$bgsize = trim( (string) ( $settings->hero_bg_size_custom ?? '' ) );
		if ( '' === $bgsize || ! preg_match( '#^[0-9a-z%\.\s]+$#i', $bgsize ) ) { $bgsize = 'auto'; }
	} else {
		$bgsize = in_array( $size_sel, array( 'cover', 'contain', 'auto' ), true ) ? $size_sel : 'cover';
	}

	$bgrep = in_array( $settings->hero_bg_repeat ?? 'no-repeat', array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), true ) ? ( $settings->hero_bg_repeat ?? 'no-repeat' ) : 'no-repeat';
	$bgatt = in_array( $settings->hero_bg_attachment ?? 'scroll', array( 'scroll', 'fixed' ), true ) ? ( $settings->hero_bg_attachment ?? 'scroll' ) : 'scroll';

	echo "$node .ds-cta-hero-bg { background-position: {$bgpos}; background-size: {$bgsize}; background-repeat: {$bgrep}; background-attachment: {$bgatt}; }\n";

	// Min height (responsive). Scope to the section element itself.
	$mh = $u( $settings->hero_min_height ?? '', 520 );
	echo "$node.ds-cta--style4 { min-height: {$mh}px; }\n";
	if ( '' !== ( $settings->hero_min_height_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node.ds-cta--style4 { min-height: " . (int) $settings->hero_min_height_medium . "px; } }\n"; }
	if ( '' !== ( $settings->hero_min_height_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node.ds-cta--style4 { min-height: " . (int) $settings->hero_min_height_responsive . "px; } }\n"; }

	// Overlay: solid tint / linear gradient / radial gradient / none.
	$otype = in_array( $settings->hero_overlay_type ?? 'solid', array( 'solid', 'linear', 'radial', 'none' ), true ) ? ( $settings->hero_overlay_type ?? 'solid' ) : 'solid';
	$oc1   = $mix( $settings->hero_overlay_color ?? 'var(--fl-global-dark-background)', $settings->hero_overlay_opacity ?? 82 );
	$oc2   = $mix( $settings->hero_overlay_color2 ?? 'var(--fl-global-dark-background)', $settings->hero_overlay_opacity2 ?? 20 );
	if ( 'none' === $otype ) {
		echo "$node .ds-cta-hero-overlay { background: transparent; }\n";
	} elseif ( 'linear' === $otype && '' !== $oc1 && '' !== $oc2 ) {
		$ang = (int) ( $settings->hero_overlay_angle ?? 180 );
		echo "$node .ds-cta-hero-overlay { background: linear-gradient({$ang}deg, {$oc1} 0%, {$oc2} 100%); }\n";
	} elseif ( 'radial' === $otype && '' !== $oc1 && '' !== $oc2 ) {
		$rpos_allow = array( 'center', 'top', 'bottom', 'left', 'right', 'left top', 'right top', 'left bottom', 'right bottom' );
		$rpos       = in_array( $settings->hero_overlay_radial ?? 'center', $rpos_allow, true ) ? ( $settings->hero_overlay_radial ?? 'center' ) : 'center';
		echo "$node .ds-cta-hero-overlay { background: radial-gradient(circle at {$rpos}, {$oc1} 0%, {$oc2} 100%); }\n";
	} elseif ( '' !== $oc1 ) {
		echo "$node .ds-cta-hero-overlay { background: {$oc1}; }\n";
	}
	// Blend the overlay colour INTO the image (duotone / colour-shift). The section
	// is isolated (css/frontend.css) so the blend only mixes with the hero image,
	// not the page behind it; the content sits above the overlay so text is unaffected.
	if ( 'none' !== $otype ) {
		$blend_allow = array( 'normal', 'multiply', 'screen', 'overlay', 'soft-light', 'hard-light', 'color', 'hue', 'saturation', 'luminosity', 'difference', 'exclusion', 'darken', 'lighten', 'color-dodge', 'color-burn' );
		$blend       = in_array( $settings->hero_overlay_blend ?? 'normal', $blend_allow, true ) ? ( $settings->hero_overlay_blend ?? 'normal' ) : 'normal';
		if ( 'normal' !== $blend ) { echo "$node .ds-cta-hero-overlay { mix-blend-mode: {$blend}; }\n"; }
	}

	// Box: border, background tint, radius, max-width, top bar.
	$box_on = ( $settings->hero_box ?? 'yes' ) === 'yes';
	$hac    = $col( $settings->hero_accent_color ?? '' );
	if ( $box_on ) {
		$bbw  = $u( $settings->box_border_width ?? '', 1 );
		$bbc  = $col( $settings->box_border_color ?? '' );
		$bbg  = $col( $settings->box_bg ?? '' );
		$brad = ( isset( $settings->box_radius ) && '' !== $settings->box_radius ) ? ( (int) $settings->box_radius ) . 'px' : 'var(--ds-radius)';
		$p    = "border-width:{$bbw}px;border-style:solid;";
		if ( '' !== $bbc ) { $p .= "border-color:{$bbc};"; }
		if ( '' !== $bbg ) { $p .= "background:{$bbg};"; }
		$p .= "border-radius:{$brad};";
		echo "$node .ds-cta-hero-inner { {$p} }\n";
		if ( ( $settings->box_top_bar ?? 'yes' ) === 'yes' ) {
			$tbh = $u( $settings->box_top_bar_height ?? '', 4 );
			$tbc = $col( $settings->box_top_bar_color ?? '' );
			if ( '' === $tbc ) { $tbc = $hac; }
			echo "$node .ds-cta-hero-bar { height:{$tbh}px;" . ( '' !== $tbc ? "background:{$tbc};" : '' ) . " }\n";
		}
	}
	$bmw = $u( $settings->box_max_width ?? '', 860 );
	echo "$node .ds-cta-hero-inner { max-width: {$bmw}px; }\n";

	// Colours.
	$hhc = $col( $settings->hero_heading_color ?? '' );
	$hha = $col( $settings->hero_heading_accent_color ?? '' );
	$hcc = $col( $settings->hero_contact_color ?? '' );
	$hci = $col( $settings->hero_contact_icon_color ?? '' );
	if ( '' !== $hac ) { echo "$node .ds-cta-hero-eyebrow { color: {$hac}; }\n"; }
	if ( '' !== $hhc ) { echo "$node .ds-cta-hero-heading { color: {$hhc}; }\n"; }
	if ( '' !== $hha ) { echo "$node .ds-cta-hero-heading .ds-cta-accent { color: {$hha}; }\n"; }
	// Contact text: !important so anchor items (email/phone) beat the theme's link colour.
	if ( '' !== $hcc ) { echo "$node .ds-cta-hero-citem, $node a.ds-cta-hero-citem { color: {$hcc} !important; }\n"; }
	if ( '' !== $hci ) { echo "$node .ds-cta-hero-citem svg { color: {$hci}; }\n"; }

	// Button: Theme Setting global (default) / accent / custom.
	$mode = $settings->hero_btn_global ?? 'global';
	$gs   = class_exists( 'FLBuilderGlobalStyles' ) ? FLBuilderGlobalStyles::get_settings( false ) : null;
	if ( 'global' === $mode && $gs ) {
		// FULL theme Button sync (bg, text, hover, border + radius + shadow, typography).
		DS_Module_UI::global_button_css( "$node .ds-cta-hero-btn" );
	} elseif ( 'custom' === $mode ) {
		$cbg = $col( $settings->hero_btn_bg ?? '' ); $ctx = $col( $settings->hero_btn_text_color ?? '' );
		$cbgh = $col( $settings->hero_btn_hover_bg ?? '' ) ?: $cbg; $ctxh = $col( $settings->hero_btn_hover_text ?? '' ) ?: $ctx;
		$p = '';
		if ( $cbg ) { $p .= "background:{$cbg} !important;"; }
		if ( $ctx ) { $p .= "color:{$ctx} !important;"; }
		if ( '' !== $p ) { echo "$node .ds-cta-hero-btn { {$p} }\n"; }
		if ( $cbgh || $ctxh ) { echo "$node .ds-cta-hero-btn:hover { " . ( $cbgh ? "background:{$cbgh} !important;" : '' ) . ( $ctxh ? "color:{$ctxh} !important;" : '' ) . "filter:none; }\n"; }
	} elseif ( '' !== $hac ) {
		echo "$node .ds-cta-hero-btn { background:{$hac} !important; color:var(--fl-global-white) !important; }\n";
	}

	// Social: size, gap, colours (responsive size/gap).
	$ssz  = $u( $settings->social_icon_size ?? '', 20 );
	$sgap = $u( $settings->social_gap ?? '', 14 );
	echo "$node .ds-cta-hero-social { gap: {$sgap}px; }\n";
	echo "$node .ds-cta-hero-social-link svg { width: {$ssz}px; height: {$ssz}px; }\n";
	if ( '' !== ( $settings->social_icon_size_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-cta-hero-social-link svg { width: " . (int) $settings->social_icon_size_medium . "px; height: " . (int) $settings->social_icon_size_medium . "px; } }\n"; }
	if ( '' !== ( $settings->social_gap_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-cta-hero-social { gap: " . (int) $settings->social_gap_medium . "px; } }\n"; }
	$sclr  = $col( $settings->social_color ?? '' );
	$sclrh = $col( $settings->social_hover_color ?? '' );
	if ( '' !== $sclr )  { echo "$node .ds-cta-hero-social-link { color: {$sclr} !important; }\n"; }
	if ( '' !== $sclrh ) { echo "$node .ds-cta-hero-social-link:hover { color: {$sclrh} !important; }\n"; }

	// Box padding (deferred dimension).
	if ( class_exists( 'FLBuilderCSS' ) ) {
		FLBuilderCSS::dimension_field_rule( array(
			'settings'     => $settings,
			'setting_name' => 'box_padding',
			'selector'     => "$node .ds-cta-hero-inner",
			'unit'         => 'px',
			'props'        => array( 'padding-top' => 'box_padding_top', 'padding-right' => 'box_padding_right', 'padding-bottom' => 'box_padding_bottom', 'padding-left' => 'box_padding_left' ),
		) );
	}

	$typo = array( 'hero_eyebrow_typography' => '.ds-cta-hero-eyebrow', 'hero_heading_typography' => '.ds-cta-hero-heading', 'hero_contact_typography' => '.ds-cta-hero-citem', 'hero_btn_typography' => '.ds-cta-hero-btn' );
} elseif ( 'style5' === $style ) {
	// ---- Style 5: Motion Cards (GH #56) — everything flows through custom props
	//      on the grid so the static classes stay generic. ----
	$cols = max( 1, (int) ( $settings->mc_cols ?? 3 ) );
	$gap  = $u( $settings->mc_gap ?? '', 24 );
	echo "$node .ds-mcard-grid{grid-template-columns:repeat({$cols},1fr);gap:{$gap}px;}\n";
	echo "@media(max-width:1024px){ $node .ds-mcard-grid{grid-template-columns:repeat(" . min( $cols, 2 ) . ",1fr);} }\n";
	echo "@media(max-width:600px){ $node .ds-mcard-grid{grid-template-columns:1fr;} }\n";

	$ratio = preg_replace( '#[^0-9/ ]#', '', (string) ( $settings->mc_ratio ?? '4 / 5' ) ); if ( '' === trim( $ratio ) ) { $ratio = '4 / 5'; }
	$rad   = $u( $settings->mc_radius ?? '', 14 );
	$lift  = $u( $settings->mc_lift ?? '', 6 );
	$spd   = max( 50, $u( $settings->mc_speed ?? '', 250 ) );

	$sh_map = array( 'none' => 'none', 'soft' => '0 10px 26px -16px rgba(10,30,60,.35)', 'medium' => '0 16px 34px -18px rgba(10,30,60,.45)', 'strong' => '0 22px 44px -18px rgba(10,30,60,.55)' );
	$shh_map = array( 'none' => '0 14px 30px -18px rgba(10,30,60,.35)', 'soft' => '0 24px 48px -20px rgba(10,30,60,.5)', 'medium' => '0 30px 56px -20px rgba(10,30,60,.55)', 'strong' => '0 36px 64px -20px rgba(10,30,60,.62)' );
	$sh = $settings->mc_shadow ?? 'soft'; if ( ! isset( $sh_map[ $sh ] ) ) { $sh = 'soft'; }

	// Default background effect.
	$amt = max( 0, min( 100, $u( $settings->mcfx_amount ?? '', 60 ) ) );
	$fx  = 'none'; $tint = 'transparent'; $blend = 'normal';
	switch ( $settings->mcfx_base ?? 'none' ) {
		case 'blur':       $fx = 'blur(' . min( 40, max( 1, $amt ) ) . 'px)'; break;
		case 'brightness': $fx = 'brightness(' . $amt . '%)'; break;
		case 'grayscale':  $fx = 'grayscale(1)'; break;
		case 'opacity':    $fx = 'opacity(' . $amt . '%)'; break;
		case 'overlay':
			$tint  = $col( $settings->mcfx_overlay ?? '' ) ?: 'var(--fl-global-primary)';
			$blend = in_array( $settings->mcfx_blend ?? 'multiply', array( 'normal', 'multiply', 'screen', 'overlay', 'soft-light', 'hard-light', 'darken', 'lighten' ), true ) ? ( $settings->mcfx_blend ?? 'multiply' ) : 'multiply';
			break;
	}
	$zoom = 1 + max( 1, min( 25, (int) ( $settings->mcfx_zoom ?? 6 ) ) ) / 100;

	// Logo overlay.
	$lsz  = $u( $settings->mcl_size ?? '', 84 );
	$lpad = $u( $settings->mcl_pad ?? '', 10 );
	$lrad = $u( $settings->mcl_radius ?? '', 999 );
	$lbg  = $col( $settings->mcl_bg ?? '' ) ?: 'transparent';
	$lsh  = $settings->mcl_shadow ?? 'soft'; if ( ! isset( $sh_map[ $lsh ] ) ) { $lsh = 'soft'; }
	$ldur = max( 50, $u( $settings->mcl_dur ?? '', 600 ) );
	$ldel = max( 0, $u( $settings->mcl_delay ?? '', 150 ) );
	$ease_allow = array( 'ease', 'ease-out', 'ease-in-out', 'cubic-bezier(.34,1.56,.64,1)' );
	$lease = in_array( $settings->mcl_ease ?? 'ease-out', $ease_allow, true ) ? ( $settings->mcl_ease ?? 'ease-out' ) : 'ease-out';

	echo "$node .ds-mcard-grid{--mc-ratio:{$ratio};--mc-radius:{$rad}px;--mc-lift:{$lift}px;--mc-speed:{$spd}ms;--mc-shadow:{$sh_map[$sh]};--mc-shadow-hover:{$shh_map[$sh]};--mc-fx:{$fx};--mc-tint:{$tint};--mc-blend:{$blend};--mc-zoom:{$zoom};--mcl-size:{$lsz}px;--mcl-pad:{$lpad}px;--mcl-radius:{$lrad}px;--mcl-bg:{$lbg};--mcl-shadow:{$sh_map[$lsh]};--mcl-dur:{$ldur}ms;--mcl-delay:{$ldel}ms;--mcl-ease:{$lease};}\n";

	$typo = array( 'heading_typography' => '.ds-cta-heading', 'title_typography' => '.ds-mcard-title', 'eyebrow_typography' => '.ds-mcard-eyebrow' );
} else {
	// ---- Style 1: clip cards ----
	$ratio = trim( (string) ( $settings->ratio ?? '3/4' ) );
	if ( '' !== $ratio && preg_match( '#^[0-9]+\s*/\s*[0-9]+$#', $ratio ) ) { echo "$node .ds-cta-card { aspect-ratio: {$ratio}; }\n"; }
	$tl = $u( $settings->clip_tl ?? '', 18 );
	$br = $u( $settings->clip_br ?? '', 26 );
	echo "$node .ds-cta-card { clip-path: polygon({$tl}px 0, 100% 0, 100% calc(100% - {$br}px), calc(100% - {$br}px) 100%, 0 100%, 0 {$tl}px); }\n";
	if ( ( $settings->stagger ?? 'yes' ) === 'yes' ) {
		$soff = $u( $settings->stagger_offset ?? '', 40 );
		echo "@media (min-width:" . ( $bpm + 1 ) . "px){ $node .ds-cta--stagger .ds-cta-card:nth-child(even){ margin-top: {$soff}px; } }\n";
	}
	$cbg = $col( $settings->card_bg ?? '' );
	if ( '' !== $cbg ) { echo "$node .ds-cta-card { background-color: {$cbg}; }\n"; }
	if ( $otop && $obot ) { echo "$node .ds-cta-card-overlay { background: linear-gradient(180deg, {$otop} 30%, {$obot} 100%); }\n"; }
	// Hover overlay colour + blend.
	if ( '' !== $ohmix )         { echo "$node .ds-cta-card:hover .ds-cta-card-overlay { background: {$ohmix}; }\n"; }
	if ( 'normal' !== $ohblend ) { echo "$node .ds-cta-card:hover .ds-cta-card-overlay { mix-blend-mode: {$ohblend}; }\n"; }
	if ( $accent ) {
		echo "$node .ds-cta-card-eyebrow { color: {$accent}; }\n";
		echo "$node .ds-cta-card-chevron { color: {$accent}; }\n";
		echo "$node .ds-cta-card-bar { background: {$accent}; }\n";
	}
	if ( $tcolor ) { echo "$node .ds-cta-card-title { color: {$tcolor}; }\n"; }

	$typo = array( 'heading_typography' => '.ds-cta-heading', 'title_typography' => '.ds-cta-card-title', 'eyebrow_typography' => '.ds-cta-card-eyebrow' );
}

// ---- Spacing: padding on the wrap, margin on the section (deferred) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-cta-wrap",
		'unit'         => 'px',
		'props'        => array( 'padding-top' => 'padding_top', 'padding-right' => 'padding_right', 'padding-bottom' => 'padding_bottom', 'padding-left' => 'padding_left' ),
	) );
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-cta",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

// ---- Typography (deferred; flushed by FLBuilderCSS::render()) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{$key} ) ) {
			FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $key, 'selector' => "$node $sel" ) );
		}
	}
}

/* ---- Outline text ({outline}...{/outline}) per-module override: blank = Theme Setting. ---- */
$oc_ov = DS_Module_UI::color( $settings->outline_color ?? '' );
if ( '' !== $oc_ov ) { echo "$node { --ds-outline-c: {$oc_ov}; }\n"; }
if ( isset( $settings->outline_width ) && '' !== $settings->outline_width ) { echo "$node { --ds-outline-w: " . max( 1, (int) $settings->outline_width ) . "px; }\n"; }

/* ---- Card shadow (all styles): soft / medium / strong. ---- */
$csh = array( 'soft' => '0 4px 14px rgba(0,0,0,.10)', 'medium' => '0 8px 24px rgba(0,0,0,.16)', 'strong' => '0 16px 40px rgba(0,0,0,.24)' )[ $settings->cta_shadow ?? '' ] ?? '';
if ( '' !== $csh ) { echo "$node .ds-cta-card, $node .ds-cta-tile, $node .ds-cta-bento-cell, $node .ds-cta-hero-box { box-shadow: {$csh}; }\n"; }
