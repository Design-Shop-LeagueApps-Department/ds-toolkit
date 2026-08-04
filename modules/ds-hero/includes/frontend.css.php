<?php
/**
 * Leagueapps Hero Banner — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";
// Selected hero style. The rules below are the shared/Style-1 base; scope any
// future style-specific overrides with: if ( 'style2' === $style ) { … } and the
// `.ds-hero--style2` modifier class.
$style = isset( $settings->hero_style ) ? preg_replace( '/[^a-z0-9_]/', '', $settings->hero_style ) : 'style1';

// ---- Content width (boxed / full / custom) so the hero can fill a full-width container ----
$cw = $settings->container_width ?? 'boxed';
if ( 'full' === $cw ) {
	echo "$node .ds-hero-wrap { max-width: none; margin: 0; }\n";
} elseif ( 'custom' === $cw ) {
	$cmw = ( isset( $settings->container_max_width ) && '' !== $settings->container_max_width ) ? (int) $settings->container_max_width : 1280;
	echo "$node .ds-hero-wrap { max-width: {$cmw}px; margin: 0 auto; }\n";
}

$col = array( 'DS_Module_UI', 'color' );
$u = array( 'DS_Module_UI', 'u' );
// Opacity on any colour (hex or global var()) via color-mix.
$mix = function( $color, $op ) use ( $col ) {
	$c = $col( $color );
	if ( '' === $c ) { return ''; }
	$op = max( 0, min( 100, (int) $op ) );
	return "color-mix(in srgb, {$c} {$op}%, transparent)";
};

$g   = FLBuilderModel::get_global_settings();
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();

// ---- Min height (responsive) — Style 1 only; Style 2 banner uses adaptive heights ----
if ( 'style2' !== $style ) {
	$mh = $u( $settings->min_height ?? '', 92 );
	echo "$node .ds-hero { min-height: {$mh}vh; }\n";
	if ( '' !== ( $settings->min_height_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-hero { min-height: " . (int) $settings->min_height_medium . "vh; } }\n"; }
	if ( '' !== ( $settings->min_height_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-hero { min-height: " . (int) $settings->min_height_responsive . "vh; } }\n"; }
}

// ---- Ken Burns: slide zoom duration tracks the slide interval ----
if ( ( $settings->kenburns ?? 'no' ) === 'yes' ) {
	$kb = $u( $settings->slide_interval ?? '', 6 );
	echo "$node .ds-hero--kenburns .ds-hero-slide.is-active { --ds-hero-kb: {$kb}s; }\n";
}

// ---- Content width ----
$cw = is_numeric( $settings->content_width ?? '' ) ? (int) $settings->content_width : 760;
echo "$node .ds-hero-inner { max-width: {$cw}px; }\n";

// ---- Overlay ----
$ostyle = $settings->overlay_style ?? 'gradient';
if ( 'solid' === $ostyle ) {
	$bg = $mix( $settings->overlay_color ?? 'var(--fl-global-dark-background)', $settings->overlay_opacity ?? 70 );
	if ( $bg ) { echo "$node .ds-hero-overlay { background: {$bg}; }\n"; }
} elseif ( 'gradient' === $ostyle ) {
	$c1  = $mix( $settings->grad_color1 ?? 'var(--fl-global-dark-background)', $settings->grad_opacity1 ?? 92 );
	$c2  = $mix( $settings->grad_color2 ?? 'var(--fl-global-dark-background)', $settings->grad_opacity2 ?? 35 );
	$ang = $u( $settings->grad_angle ?? '', 105 );
	if ( $c1 && $c2 ) { echo "$node .ds-hero-overlay { background: linear-gradient({$ang}deg, {$c1} 0%, {$c2} 100%); }\n"; }
}

// ---- Colours (Style 1 content colours; Style 2 banner has its own below) ----
if ( 'style2' !== $style ) {
	$colors = array(
		'.ds-hero-eyebrow'  => $settings->eyebrow_color ?? '',
		'.ds-hero-title'    => $settings->title_color ?? '',
		'.ds-hero-accent'   => $settings->accent_color ?? '',
		'.ds-hero-sub'      => $settings->sub_color ?? '',
		'.ds-hero-stat-n'   => $settings->stat_num_color ?? '',
		'.ds-hero-stat-l'   => $settings->stat_label_color ?? '',
	);
	foreach ( $colors as $sel => $v ) {
		$c = $col( $v );
		if ( '' !== $c ) { echo "$node $sel { color: {$c}; }\n"; }
	}
}

// ---- Style 2 (Page Banner): adaptive heights, no-image background, banner colours ----
if ( 'style2' === $style ) {
	$hbg = $u( $settings->banner_h_bg ?? '', 52 );
	$hno = $u( $settings->banner_h_nobg ?? '', 28 );
	echo "$node .ds-banner--has-bg { min-height: {$hbg}vh; }\n";
	echo "$node .ds-banner--no-bg { min-height: {$hno}vh; }\n";
	if ( '' !== ( $settings->banner_h_bg_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-banner--has-bg { min-height: " . (int) $settings->banner_h_bg_medium . "vh; } }\n"; }
	if ( '' !== ( $settings->banner_h_nobg_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-banner--no-bg { min-height: " . (int) $settings->banner_h_nobg_medium . "vh; } }\n"; }

	// No-image background: colour + optional tileable pattern.
	$nb = array();
	$nc = $col( $settings->nobg_color ?? '' );
	if ( '' !== $nc ) { $nb[] = "background-color: {$nc}"; }
	$np = '';
	$pv = $settings->nobg_pattern ?? '';
	if ( is_array( $pv ) )      { $pv = $pv['id'] ?? ( $pv['url'] ?? '' ); }
	elseif ( is_object( $pv ) ) { $pv = $pv->id ?? ( $pv->url ?? '' ); }
	if ( is_numeric( $pv ) ) { $np = (string) wp_get_attachment_image_url( (int) $pv, 'full' ); }
	elseif ( $pv )           { $np = esc_url( (string) $pv ); }
	if ( '' !== $np ) {
		$nr  = in_array( $settings->nobg_repeat ?? 'repeat', array( 'repeat', 'repeat-x', 'repeat-y', 'no-repeat' ), true ) ? ( $settings->nobg_repeat ?? 'repeat' ) : 'repeat';
		$nsz = in_array( $settings->nobg_size ?? 'auto', array( 'auto', 'contain', 'cover' ), true ) ? ( $settings->nobg_size ?? 'auto' ) : 'auto';
		$nb[] = "background-image: url({$np})";
		$nb[] = "background-repeat: {$nr}";
		$nb[] = "background-size: {$nsz}";
		$nb[] = 'background-position: center';
		$blend = in_array( $settings->nobg_blend ?? 'normal', array( 'normal', 'multiply', 'overlay', 'screen', 'soft-light' ), true ) ? ( $settings->nobg_blend ?? 'normal' ) : 'normal';
		if ( 'normal' !== $blend ) { $nb[] = "background-blend-mode: {$blend}"; }
	}
	if ( ! empty( $nb ) ) { echo "$node .ds-banner--no-bg { " . implode( '; ', $nb ) . "; }\n"; }

	// Banner title / subtitle colours (blank = the per-state CSS default).
	$bt = $col( $settings->banner_title_color ?? '' );
	if ( '' !== $bt ) { echo "$node .ds-hero-title { color: {$bt}; }\n"; }
	$bs = $col( $settings->banner_sub_color ?? '' );
	if ( '' !== $bs ) { echo "$node .ds-hero-sub { color: {$bs}; }\n"; }

		// ---- Image scrim (designed darkening preset over the photo, for legibility) ----
		$scrim = preg_replace( '/[^a-z]/', '', (string) ( $settings->scrim_preset ?? 'none' ) );
		if ( in_array( $scrim, array( 'bottom', 'top', 'vignette', 'full' ), true ) ) {
			$sc = $mix( $settings->scrim_color ?? '0a0a0a', $settings->scrim_strength ?? 55 );
			if ( '' !== $sc ) {
				if ( 'bottom' === $scrim )       { $bgv = "linear-gradient(to top, {$sc} 0%, transparent 60%)"; }
				elseif ( 'top' === $scrim )      { $bgv = "linear-gradient(to bottom, {$sc} 0%, transparent 60%)"; }
				elseif ( 'vignette' === $scrim ) { $bgv = "radial-gradient(ellipse at center, transparent 38%, {$sc} 100%)"; }
				else                             { $bgv = $sc; }
				echo "$node .ds-banner-scrim { background: {$bgv}; }\n";
			}
		}

	// Breadcrumbs colour + typography.
	$bc = $col( $settings->breadcrumbs_color ?? '' );
	if ( '' !== $bc ) { echo "$node .ds-banner-crumbs, $node .ds-banner-crumbs a, $node .ds-banner-crumbs .breadcrumb_last { color: {$bc}; }\n"; }
	if ( class_exists( 'FLBuilderCSS' ) && ! empty( $settings->breadcrumbs_typography ) ) {
		FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'breadcrumbs_typography', 'selector' => "$node .ds-banner-crumbs" ) );
	}

	// Default the banner text to the Theme Setting global typography (heading + body),
	// unless the Typography section overrides it. Keeps fonts consistent with the site.
	if ( class_exists( 'FLBuilderCSS' ) && class_exists( 'FLBuilderGlobalStyles' ) ) {
		$gs = FLBuilderGlobalStyles::get_settings( false );
		if ( empty( $settings->title_typography ) && ! empty( $gs->h1_typography ) ) {
			$ht = (object) array(
				'bt'            => $gs->h1_typography,
				'bt_large'      => $gs->h1_typography_large ?? '',
				'bt_medium'     => $gs->h1_typography_medium ?? '',
				'bt_responsive' => $gs->h1_typography_responsive ?? '',
			);
			FLBuilderCSS::typography_field_rule( array( 'settings' => $ht, 'setting_name' => 'bt', 'selector' => "$node .ds-hero--banner .ds-hero-title" ) );
		}
		if ( empty( $settings->sub_typography ) && ! empty( $gs->text_typography ) ) {
			$st = (object) array(
				'bs'            => $gs->text_typography,
				'bs_large'      => $gs->text_typography_large ?? '',
				'bs_medium'     => $gs->text_typography_medium ?? '',
				'bs_responsive' => $gs->text_typography_responsive ?? '',
			);
			FLBuilderCSS::typography_field_rule( array( 'settings' => $st, 'setting_name' => 'bs', 'selector' => "$node .ds-hero--banner .ds-hero-sub" ) );
		}
	}
}
// ---- Slideshow nav (arrows, dots, progress lines) ----
// Progress-line cooldown duration tracks the slide interval.
$bar_dur = $u( $settings->slide_interval ?? '', 6 );
echo "$node .ds-hero-bars { --ds-hero-bar-dur: {$bar_dur}s; }\n";
// Accent tint for the active indicators + arrow hover.
$nav_acc = $col( $settings->accent_color ?? '' );
if ( '' !== $nav_acc ) {
	echo "$node .ds-hero-nav:hover { background: {$nav_acc}; }\n";
	echo "$node .ds-hero-dot.is-active { background: {$nav_acc}; }\n";
	echo "$node .ds-hero-bar-fill { background: {$nav_acc}; }\n";
}

// ---- Buttons: respect the Theme Setting global Button by default ----
$btn_global = ( $settings->btn_global ?? 'yes' ) === 'yes';
$gs         = class_exists( 'FLBuilderGlobalStyles' ) ? FLBuilderGlobalStyles::get_settings( false ) : null;

// Global Button corner radius = the button border compound (CSS order TL TR BR BL).
$gradius = '';
if ( $gs ) {
	$bb = isset( $gs->button_border ) ? (array) $gs->button_border : array();
	$r  = isset( $bb['radius'] ) ? (array) $bb['radius'] : array();
	$tl = $r['top_left'] ?? ''; $tr = $r['top_right'] ?? ''; $bl = $r['bottom_left'] ?? ''; $brr = $r['bottom_right'] ?? '';
	if ( '' !== ( $tl . $tr . $bl . $brr ) ) {
		$ru      = function( $v ) { return ( '' === $v ? '0' : (int) $v ) . 'px'; };
		$gradius = $ru( $tl ) . ' ' . $ru( $tr ) . ' ' . $ru( $brr ) . ' ' . $ru( $bl );
	}
}

if ( $btn_global && $gs ) {
	// FULL theme Button sync (bg, text, hover, border + radius + shadow, typography).
	DS_Module_UI::global_button_css( "$node .ds-hero-btn--primary" );
	// Corner radius + typography on the GHOST button too so it matches the theme.
	if ( '' !== $gradius ) { echo "$node .ds-hero-btn--ghost { border-radius: {$gradius}; }\n"; }
	if ( class_exists( 'FLBuilderCSS' ) && ! empty( $gs->button_typography ) ) {
		$gt = (object) array(
			'gbtypo'            => $gs->button_typography,
			'gbtypo_large'      => $gs->button_typography_large ?? '',
			'gbtypo_medium'     => $gs->button_typography_medium ?? '',
			'gbtypo_responsive' => $gs->button_typography_responsive ?? '',
		);
		FLBuilderCSS::typography_field_rule( array( 'settings' => $gt, 'setting_name' => 'gbtypo', 'selector' => "$node .ds-hero-btn--ghost" ) );
	}
} else {
	// Accent colour primary button.
	$acc = $col( $settings->accent_color ?? '' );
	if ( '' !== $acc ) { echo "$node .ds-hero-btn--primary { background: {$acc} !important; }\n"; }
}

// ---- Alignment (responsive overrides; base set by the modifier class) ----
$align_rules = function( $align ) use ( $node ) {
	if ( 'center' === $align ) {
		return "$node .ds-hero-inner{margin-left:auto;margin-right:auto;text-align:center;}"
			. "$node .ds-hero-eyebrow,$node .ds-hero-cta,$node .ds-hero-proof{justify-content:center;}"
			. "$node .ds-hero-sub{margin-left:auto;margin-right:auto;}";
	}
	return "$node .ds-hero-inner{margin-left:0;margin-right:0;text-align:left;}"
		. "$node .ds-hero-eyebrow,$node .ds-hero-cta,$node .ds-hero-proof{justify-content:flex-start;}"
		. "$node .ds-hero-sub{margin-left:0;margin-right:0;}";
};
if ( '' !== ( $settings->align_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){" . $align_rules( $settings->align_medium ) . "}\n"; }
if ( '' !== ( $settings->align_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){" . $align_rules( $settings->align_responsive ) . "}\n"; }

// ---- Buttons Alignment (GH #94): positions the CTA row independently of the
// module Alignment. Emitted AFTER the alignment rules so an explicit value wins
// the equal-specificity contest at every width; per-device values win over base.
$ba_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
$ba = $settings->btn_align ?? '';
if ( isset( $ba_map[ $ba ] ) ) { echo "$node .ds-hero-cta{justify-content:{$ba_map[ $ba ]};}\n"; }
$bam = $settings->btn_align_medium ?? '';
if ( isset( $ba_map[ $bam ] ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-hero-cta{justify-content:{$ba_map[ $bam ]};} }\n"; }
$bar = $settings->btn_align_responsive ?? '';
if ( isset( $ba_map[ $bar ] ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-hero-cta{justify-content:{$ba_map[ $bar ]};} }\n"; }

// ---- Spacing: padding on the content wrap, margin on the section (deferred) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-hero-wrap",
		'unit'         => 'px',
		'props'        => array( 'padding-top' => 'padding_top', 'padding-right' => 'padding_right', 'padding-bottom' => 'padding_bottom', 'padding-left' => 'padding_left' ),
	) );
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-hero",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

// ---- Typography (deferred; flushed by FLBuilderCSS::render() after this file) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	$typo = array(
		'title_typography'   => '.ds-hero-title',
		'sub_typography'     => '.ds-hero-sub',
		'eyebrow_typography' => '.ds-hero-eyebrow',
	);
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{$key} ) ) {
			FLBuilderCSS::typography_field_rule( array(
				'settings'     => $settings,
				'setting_name' => $key,
				'selector'     => "$node $sel",
			) );
		}
	}
}

/* ---- Outline text ({outline}...{/outline}) per-module override: blank = Theme Setting. ---- */
$oc_ov = DS_Module_UI::color( $settings->outline_color ?? '' );
if ( '' !== $oc_ov ) { echo "$node { --ds-outline-c: {$oc_ov}; }\n"; }
if ( isset( $settings->outline_width ) && '' !== $settings->outline_width ) { echo "$node { --ds-outline-w: " . max( 1, (int) $settings->outline_width ) . "px; }\n"; }
