<?php
/**
 * Leagueapps Hero Banner — dynamic CSS. $module, $settings, $id in scope.
 *
 * Child selectors are written "$node .ds-hero .ds-hero-x" — three classes, not the
 * two "$node .ds-hero-x" would give. The stylesheet carries modifier rules such as
 * `.ds-hero--center .ds-hero-inner{max-width:760px}` at (0,2,0), and a page that
 * renders a Themer layout PLUS page content loads TWO Beaver Builder layout
 * stylesheets, each carrying a copy of this module's static CSS. The second copy
 * lands after the per-node rules, so a two-class node rule ties on specificity and
 * loses on source order — which is how a Content Width of 1600 rendered as 760 when
 * Alignment was Center (GH #136). Three classes settle it whatever the load order.
 *
 * Root-level rules are deliberately left at two classes ($node .ds-hero, and the
 * .ds-hero--x / .ds-banner--x modifiers): the root cannot be a descendant of itself,
 * and nothing static competes with them at that specificity.
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
	echo "$node .ds-hero .ds-hero-wrap { max-width: none; margin: 0; }\n";
} elseif ( 'custom' === $cw ) {
	$cmw = ( isset( $settings->container_max_width ) && '' !== $settings->container_max_width ) ? (int) $settings->container_max_width : 1280;
	echo "$node .ds-hero .ds-hero-wrap { max-width: {$cmw}px; margin: 0 auto; }\n";
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

// ---- Min height (responsive) — Style 1 only; Style 2 banner uses adaptive heights and
// Style 3 takes its height from the slide, so neither may inherit this. ----
if ( 'style1' === $style ) {
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
echo "$node .ds-hero .ds-hero-inner { max-width: {$cw}px; }\n";

// The text column lives inside .ds-hero-wrap, which carries its own cap — 1280px
// from the stylesheet, or whatever Container Width is set to. A Content Width above
// that cap was therefore silently clamped: asking for 1600 rendered 1280, with
// nothing in the UI to say why (GH #134). It is NOT alignment-specific, despite how
// it presents; left, center and right all clamp identically.
//
// So when the content asks for more room than its container allows, the container is
// widened to match. Only ever widened, and only in that case: a Container Width set
// narrower than the content is a deliberate choice and is left alone below the cap.
$cwrap = $settings->container_width ?? 'boxed';
if ( 'full' !== $cwrap ) {
	$cap = ( 'custom' === $cwrap && isset( $settings->container_max_width ) && '' !== $settings->container_max_width )
		? (int) $settings->container_max_width
		: 1280; // the .ds-hero-wrap default in css/frontend.css
	if ( $cw > $cap ) {
		echo "$node .ds-hero .ds-hero-wrap { max-width: {$cw}px; margin: 0 auto; }\n";
	}
}

// ---- Overlay ----
$ostyle = $settings->overlay_style ?? 'gradient';
if ( 'solid' === $ostyle ) {
	$bg = $mix( $settings->overlay_color ?? 'var(--fl-global-dark-background)', $settings->overlay_opacity ?? 70 );
	if ( $bg ) { echo "$node .ds-hero .ds-hero-overlay { background: {$bg}; }\n"; }
} elseif ( 'gradient' === $ostyle ) {
	$c1  = $mix( $settings->grad_color1 ?? 'var(--fl-global-dark-background)', $settings->grad_opacity1 ?? 92 );
	$c2  = $mix( $settings->grad_color2 ?? 'var(--fl-global-dark-background)', $settings->grad_opacity2 ?? 35 );
	$ang = $u( $settings->grad_angle ?? '', 105 );
	if ( $c1 && $c2 ) { echo "$node .ds-hero .ds-hero-overlay { background: linear-gradient({$ang}deg, {$c1} 0%, {$c2} 100%); }\n"; }
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
	if ( '' !== $bt ) { echo "$node .ds-hero .ds-hero-title { color: {$bt}; }\n"; }
	$bs = $col( $settings->banner_sub_color ?? '' );
	if ( '' !== $bs ) { echo "$node .ds-hero .ds-hero-sub { color: {$bs}; }\n"; }

		// ---- Image scrim (designed darkening preset over the photo, for legibility) ----
		$scrim = preg_replace( '/[^a-z]/', '', (string) ( $settings->scrim_preset ?? 'none' ) );
		if ( in_array( $scrim, array( 'bottom', 'top', 'vignette', 'full' ), true ) ) {
			$sc = $mix( $settings->scrim_color ?? '0a0a0a', $settings->scrim_strength ?? 55 );
			if ( '' !== $sc ) {
				if ( 'bottom' === $scrim )       { $bgv = "linear-gradient(to top, {$sc} 0%, transparent 60%)"; }
				elseif ( 'top' === $scrim )      { $bgv = "linear-gradient(to bottom, {$sc} 0%, transparent 60%)"; }
				elseif ( 'vignette' === $scrim ) { $bgv = "radial-gradient(ellipse at center, transparent 38%, {$sc} 100%)"; }
				else                             { $bgv = $sc; }
				echo "$node .ds-hero .ds-banner-scrim { background: {$bgv}; }\n";
			}
		}

	// Breadcrumbs colour + typography.
	$bc = $col( $settings->breadcrumbs_color ?? '' );
	if ( '' !== $bc ) { echo "$node .ds-hero .ds-banner-crumbs, $node .ds-hero .ds-banner-crumbs a, $node .ds-hero .ds-banner-crumbs .breadcrumb_last { color: {$bc}; }\n"; }
	if ( class_exists( 'FLBuilderCSS' ) && ! empty( $settings->breadcrumbs_typography ) ) {
		FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'breadcrumbs_typography', 'selector' => "$node .ds-hero .ds-banner-crumbs" ) );
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
echo "$node .ds-hero .ds-hero-bars { --ds-hero-bar-dur: {$bar_dur}s; }\n";
// Accent tint for the active indicators + arrow hover.
$nav_acc = $col( $settings->accent_color ?? '' );
if ( '' !== $nav_acc ) {
	echo "$node .ds-hero .ds-hero-nav:hover { background: {$nav_acc}; }\n";
	echo "$node .ds-hero .ds-hero-dot.is-active { background: {$nav_acc}; }\n";
	echo "$node .ds-hero .ds-hero-bar-fill { background: {$nav_acc}; }\n";
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
	DS_Module_UI::global_button_css( "$node .ds-hero .ds-hero-btn--primary" );
	// Corner radius + typography on the GHOST button too so it matches the theme.
	if ( '' !== $gradius ) { echo "$node .ds-hero .ds-hero-btn--ghost { border-radius: {$gradius}; }\n"; }
	if ( class_exists( 'FLBuilderCSS' ) && ! empty( $gs->button_typography ) ) {
		$gt = (object) array(
			'gbtypo'            => $gs->button_typography,
			'gbtypo_large'      => $gs->button_typography_large ?? '',
			'gbtypo_medium'     => $gs->button_typography_medium ?? '',
			'gbtypo_responsive' => $gs->button_typography_responsive ?? '',
		);
		FLBuilderCSS::typography_field_rule( array( 'settings' => $gt, 'setting_name' => 'gbtypo', 'selector' => "$node .ds-hero .ds-hero-btn--ghost" ) );
	}
} else {
	// Accent colour primary button.
	$acc = $col( $settings->accent_color ?? '' );
	if ( '' !== $acc ) { echo "$node .ds-hero .ds-hero-btn--primary { background: {$acc} !important; }\n"; }
}

// ---- Alignment (responsive overrides; base set by the modifier class) ----
$align_rules = function( $align ) use ( $node ) {
	if ( 'center' === $align ) {
		return "$node .ds-hero .ds-hero-inner{margin-left:auto;margin-right:auto;text-align:center;}"
			. "$node .ds-hero .ds-hero-eyebrow,$node .ds-hero .ds-hero-cta,$node .ds-hero .ds-hero-proof{justify-content:center;}"
			. "$node .ds-hero .ds-hero-sub{margin-left:auto;margin-right:auto;}";
	}
	return "$node .ds-hero .ds-hero-inner{margin-left:0;margin-right:0;text-align:left;}"
		. "$node .ds-hero .ds-hero-eyebrow,$node .ds-hero .ds-hero-cta,$node .ds-hero .ds-hero-proof{justify-content:flex-start;}"
		. "$node .ds-hero .ds-hero-sub{margin-left:0;margin-right:0;}";
};
if ( '' !== ( $settings->align_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){" . $align_rules( $settings->align_medium ) . "}\n"; }
if ( '' !== ( $settings->align_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){" . $align_rules( $settings->align_responsive ) . "}\n"; }

// ---- Buttons Alignment (GH #94): positions the CTA row independently of the
// module Alignment. Emitted AFTER the alignment rules so an explicit value wins
// the equal-specificity contest at every width; per-device values win over base.
$ba_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
$ba = $settings->btn_align ?? '';
if ( isset( $ba_map[ $ba ] ) ) { echo "$node .ds-hero .ds-hero-cta{justify-content:{$ba_map[ $ba ]};}\n"; }
$bam = $settings->btn_align_medium ?? '';
if ( isset( $ba_map[ $bam ] ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-hero .ds-hero-cta{justify-content:{$ba_map[ $bam ]};} }\n"; }
$bar = $settings->btn_align_responsive ?? '';
if ( isset( $ba_map[ $bar ] ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-hero .ds-hero-cta{justify-content:{$ba_map[ $bar ]};} }\n"; }

// ---- Spacing: padding on the content wrap, margin on the section (deferred) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-hero .ds-hero-wrap",
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

/* ---- Style 1 background video framing (GH #163) ----
   object-fit:cover crops the video to the hero, which can push the action out of
   frame. These move the visible crop without resizing the video. Each axis falls
   back to the desktop value before the 50% centre, so setting only Y at a
   breakpoint cannot silently reset an X the editor chose for desktop. */
if ( 'style1' === $style && 'video' === ( $settings->bg_type ?? 'image' ) ) {
	$vpos = function( $suffix ) use ( $settings ) {
		$axis = function( $key ) use ( $settings, $suffix ) {
			foreach ( array( $key . $suffix, $key ) as $k ) {
				$v = $settings->{$k} ?? '';
				if ( '' !== $v && null !== $v ) { return max( 0, min( 100, (float) $v ) ); }
			}
			return null;
		};
		// Nothing set at this breakpoint and nothing on desktop: emit no rule at all.
		$raw_x = $settings->{ 'video_pos_x' . $suffix } ?? '';
		$raw_y = $settings->{ 'video_pos_y' . $suffix } ?? '';
		if ( ( '' === $raw_x || null === $raw_x ) && ( '' === $raw_y || null === $raw_y ) ) { return ''; }
		$x = $axis( 'video_pos_x' );
		$y = $axis( 'video_pos_y' );
		return 'object-position:' . ( null === $x ? 50 : $x ) . '% ' . ( null === $y ? 50 : $y ) . '%;';
	};
	$vsel = "$node .ds-hero .ds-hero-video";
	$vb = $vpos( '' );
	if ( '' !== $vb ) { echo "$vsel { $vb }\n"; }
	$vm = $vpos( '_medium' );
	if ( '' !== $vm ) { echo "@media (max-width:{$bpm}px){ $vsel { $vm } }\n"; }
	$vr = $vpos( '_responsive' );
	if ( '' !== $vr ) { echo "@media (max-width:{$bpr}px){ $vsel { $vr } }\n"; }
}

/* ---------------- Style 3 — Peek Slider (GH #160) ----------------
   Every dimension is written as a custom property on the .ds-peek root, so a
   breakpoint override is one re-declaration instead of a second copy of the
   layout rules. Child selectors keep the three-class form documented at the top. */
if ( 'style3' === $style ) {
	list( $md, $sm ) = DS_Module_UI::breakpoints();
	$peek = "$node .ds-hero--style3 .ds-peek";

	// Responsive custom properties: desktop, then medium, then small.
	//
	// Each breakpoint carries its OWN fallback rather than inheriting the desktop value.
	// A fixed 30px gap eats the free space at narrow widths — at 900px a 93% slide leaves
	// only 1.5px of neighbour showing and at 390px none at all, which loses the one thing
	// this style exists for. Narrower slides plus a tighter gap keep the peek visible all
	// the way down. These fallbacks must be emitted here, not as static media queries:
	// the dynamic rule is (0,3,0) and would outrank a (0,2,0) static @media whatever the
	// load order.
	$fallbacks = array(
		//                        desktop, medium, small
		'peek_height'        => array( 640, 520, 440 ),
		'peek_width'         => array( 93, 92, 88 ),
		'peek_gap'           => array( 30, 20, 12 ),
		'peek_radius'        => array( 16, 14, 12 ),
		'peek_pad'           => array( 48, 32, 22 ),
		'peek_content_width' => array( 640, 520, 400 ),
	);
	$units = array(
		'peek_height'        => array( '--ds-peek-h', 'px' ),
		'peek_width'         => array( '--ds-peek-w', '%' ),
		'peek_gap'           => array( '--ds-peek-gap', 'px' ),
		'peek_radius'        => array( '--ds-peek-r', 'px' ),
		'peek_pad'           => array( '--ds-peek-pad', 'px' ),
		'peek_content_width' => array( '--ds-peek-cmw', 'px' ),
	);
	$vars = function( $suffix, $tier ) use ( $settings, $fallbacks, $units ) {
		$out = '';
		foreach ( $units as $key => $pair ) {
			$v = $settings->{ $key . $suffix } ?? '';
			if ( '' === $v || null === $v ) {
				if ( 0 === $tier ) { continue; }
				$base = (string) ( $settings->{$key} ?? '' );
				// A desktop value the editor actually changed carries down, the way Beaver
				// Builder responsive fields inherit. An untouched desktop value means they
				// never had an opinion, so use the tier default and keep the peek visible.
				$touched = ( '' !== $base && (float) $base !== (float) $fallbacks[ $key ][0] );
				$v = $touched ? $base : $fallbacks[ $key ][ $tier ];
			}
			$out .= $pair[0] . ':' . ( (float) $v ) . $pair[1] . ';';
		}
		return $out;
	};

	$base = $vars( '', 0 );
	if ( '' !== $base ) { echo "$peek { $base }\n"; }
	$mid = $vars( '_medium', 1 );
	if ( '' !== $mid ) { echo "@media (max-width:{$md}px){ $peek { $mid } }\n"; }
	$small = $vars( '_responsive', 2 );
	if ( '' !== $small ) { echo "@media (max-width:{$sm}px){ $peek { $small } }\n"; }

	$pct = function( $v, $d ) {
		$n = ( isset( $v ) && '' !== $v ) ? (float) $v : $d;
		return max( 0, min( 100, $n ) ) / 100;
	};
	$blend = function( $v ) {
		$v = preg_replace( '/[^a-z-]/', '', strtolower( (string) $v ) );
		return in_array( $v, array( 'normal', 'multiply', 'overlay', 'screen', 'darken', 'lighten', 'soft-light' ), true ) ? $v : 'normal';
	};

	// ---- Base colour (behind the image) ----
	$basec = DS_Module_UI::color( $settings->peek_base_color ?? '' );
	if ( '' !== $basec ) { echo "$peek .ds-peek-bg { background: {$basec}; }\n"; }

	// ---- Main image ----
	$img_decl = '';
	$isize = ( ( $settings->peek_img_size ?? 'cover' ) === 'contain' ) ? 'contain' : 'cover';
	$img_decl .= "object-fit:{$isize};";
	$iop = $pct( $settings->peek_img_opacity ?? '', 100 );
	if ( $iop < 1 ) { $img_decl .= 'opacity:' . rtrim( rtrim( number_format( $iop, 3, '.', '' ), '0' ), '.' ) . ';'; }
	$ibl = $blend( $settings->peek_img_blend ?? 'normal' );
	if ( 'normal' !== $ibl ) { $img_decl .= "mix-blend-mode:{$ibl};"; }
	if ( '' !== $img_decl ) { echo "$peek .ds-peek-img { $img_decl }\n"; }

	// ---- Pattern / texture ----
	$pat = '';
	$praw = $settings->peek_pattern ?? '';
	if ( is_array( $praw ) )      { $praw = $praw['id'] ?? ( $praw['url'] ?? '' ); }
	elseif ( is_object( $praw ) ) { $praw = $praw->id ?? ( $praw->url ?? '' ); }
	if ( ! empty( $praw ) ) {
		$purl = is_numeric( $praw ) ? (string) wp_get_attachment_image_url( (int) $praw, 'full' ) : esc_url( (string) $praw );
		if ( '' !== $purl ) {
			$positions = array(
				'left top', 'center top', 'right top',
				'left center', 'center center', 'right center',
				'left bottom', 'center bottom', 'right bottom',
			);
			$psize   = (string) ( $settings->peek_pattern_size ?? 'auto' );
			if ( ! in_array( $psize, array( 'auto', 'cover', 'contain' ), true ) ) { $psize = 'auto'; }
			$prepeat = (string) ( $settings->peek_pattern_repeat ?? 'repeat' );
			if ( ! in_array( $prepeat, array( 'repeat', 'repeat-x', 'repeat-y', 'no-repeat' ), true ) ) { $prepeat = 'repeat'; }
			$ppos    = (string) ( $settings->peek_pattern_pos ?? 'center center' );
			if ( ! in_array( $ppos, $positions, true ) ) { $ppos = 'center center'; }
			$pat    .= 'background-image:url(' . $purl . ');';
			$pat    .= "background-size:{$psize};background-repeat:{$prepeat};background-position:{$ppos};";
			$pop     = $pct( $settings->peek_pattern_opacity ?? '', 100 );
			if ( $pop < 1 ) { $pat .= 'opacity:' . rtrim( rtrim( number_format( $pop, 3, '.', '' ), '0' ), '.' ) . ';'; }
			$pbl = $blend( $settings->peek_pattern_blend ?? 'normal' );
			if ( 'normal' !== $pbl ) { $pat .= "mix-blend-mode:{$pbl};"; }
		}
	}
	if ( '' !== $pat ) { echo "$peek .ds-peek-pattern { $pat }\n"; }

	// ---- Overlay ----
	$otype = (string) ( $settings->peek_ov_type ?? 'gradient' );
	if ( ! in_array( $otype, array( 'none', 'solid', 'gradient' ), true ) ) { $otype = 'gradient'; }
	if ( 'none' === $otype ) {
		echo "$peek .ds-peek-overlay { display:none; }\n";
	} else {
		$ov = '';
		$c1 = DS_Module_UI::color( $settings->peek_ov_color ?? '' );
		$c2 = DS_Module_UI::color( $settings->peek_ov_color2 ?? '' );
		if ( 'solid' === $otype ) {
			if ( '' !== $c1 ) { $ov .= "background:{$c1};"; }
		} elseif ( '' !== $c1 || '' !== $c2 ) {
			// One colour set = fade that colour to transparent, so a single pick still reads.
			$g1  = '' !== $c1 ? $c1 : 'transparent';
			$g2  = '' !== $c2 ? $c2 : 'transparent';
			$dir = (string) ( $settings->peek_ov_dir ?? 'to bottom' );
			$dir = in_array( $dir, array( 'to bottom', 'to top', 'to right', 'to left', 'to bottom right', 'to bottom left' ), true ) ? $dir : 'to bottom';
			$ov .= "background:linear-gradient({$dir},{$g1},{$g2});";
		}
		$oop = $pct( $settings->peek_ov_opacity ?? '', 100 );
		if ( $oop < 1 ) { $ov .= 'opacity:' . rtrim( rtrim( number_format( $oop, 3, '.', '' ), '0' ), '.' ) . ';'; }
		$obl = $blend( $settings->peek_ov_blend ?? 'normal' );
		if ( 'normal' !== $obl ) { $ov .= "mix-blend-mode:{$obl};"; }
		if ( '' !== $ov ) { echo "$peek .ds-peek-overlay { $ov }\n"; }
	}

	// ---- Heading ----
	$tc = DS_Module_UI::color( $settings->peek_title_color ?? '' );
	if ( '' !== $tc ) { echo "$peek .ds-peek-title { color:{$tc}; }\n"; }

	// ---- CTA button ----
	$sizes = array(
		'small'  => 'padding:9px 16px;font-size:14px;',
		'medium' => 'padding:12px 22px;font-size:16px;',
		'large'  => 'padding:16px 30px;font-size:18px;',
	);
	$bsize = $settings->peek_btn_size ?? 'medium';
	echo "$peek .ds-peek-cta { " . ( $sizes[ $bsize ] ?? $sizes['medium'] ) . " }\n";

	if ( ( $settings->peek_btn_global ?? 'yes' ) === 'yes' ) {
		DS_Module_UI::global_button_css( "$peek .ds-peek-cta" );
	} else {
		$b = '';
		$bbg = DS_Module_UI::color( $settings->peek_btn_bg ?? '' );
		$bfg = DS_Module_UI::color( $settings->peek_btn_color ?? '' );
		$bbd = DS_Module_UI::color( $settings->peek_btn_border ?? '' );
		if ( '' !== $bbg ) { $b .= "background:{$bbg};"; }
		if ( '' !== $bfg ) { $b .= "color:{$bfg};"; }
		if ( '' !== $bbd ) { $b .= "border:2px solid {$bbd};"; }
		if ( '' !== $b ) { echo "$peek .ds-peek-cta { $b }\n"; }

		$h   = '';
		$hbg = DS_Module_UI::color( $settings->peek_btn_hover_bg ?? '' );
		$hfg = DS_Module_UI::color( $settings->peek_btn_hover_color ?? '' );
		if ( '' !== $hbg ) { $h .= "background:{$hbg};"; }
		if ( '' !== $hfg ) { $h .= "color:{$hfg};"; }
		if ( '' !== $h ) { echo "$peek .ds-peek-cta:hover, $peek .ds-peek-cta:focus { $h }\n"; }
	}
	if ( isset( $settings->peek_btn_radius ) && '' !== $settings->peek_btn_radius ) {
		echo "$peek .ds-peek-cta { border-radius:" . max( 0, (int) $settings->peek_btn_radius ) . "px; }\n";
	}

	// ---- Typography (deferred; flushed by FLBuilderCSS::render() after this file) ----
	if ( class_exists( 'FLBuilderCSS' ) ) {
		$ptypo = array(
			'peek_title_typography' => '.ds-peek-title',
			'peek_btn_typography'   => '.ds-peek-cta',
		);
		foreach ( $ptypo as $key => $sel ) {
			if ( ! empty( $settings->{$key} ) ) {
				FLBuilderCSS::typography_field_rule( array(
					'settings'     => $settings,
					'setting_name' => $key,
					'selector'     => "$peek $sel",
				) );
			}
		}
	}
}
