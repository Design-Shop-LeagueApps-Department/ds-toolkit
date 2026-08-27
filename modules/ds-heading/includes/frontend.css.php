<?php
/**
 * Leagueapps Heading — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";

$col = array( 'DS_Module_UI', 'color' );
$u = array( 'DS_Module_UI', 'u' );

$g   = FLBuilderModel::get_global_settings();
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();

/* ---- Alignment (responsive) ----
 * The base align comes from the .ds-heading--{align} wrapper class (static CSS).
 * The alignment field is responsive=>true, so honor the medium/responsive
 * variants by overriding the inner block + rule at each breakpoint. */
$align_css = static function ( $a ) {
	$map = array(
		'left'   => array( 'flex-start', 'left' ),
		'center' => array( 'center', 'center' ),
		'right'  => array( 'flex-end', 'right' ),
	);
	if ( ! isset( $map[ $a ] ) ) { return ''; }
	list( $items, $text ) = $map[ $a ];
	$ml = ( 'center' === $a ) ? 'auto' : ( 'right' === $a ? 'auto' : '0' );
	$mr = ( 'center' === $a ) ? 'auto' : ( 'right' === $a ? '0' : 'auto' );
	$rule_justify = ( 'center' === $a ) ? 'center' : ( 'right' === $a ? 'flex-end' : 'flex-start' );
	// The centred eyebrow shows a rule on BOTH sides, so the trailing one has to
	// follow the alignment that is active at THIS breakpoint (GH #143).
	$after = ( 'center' === $a ) ? 'flex' : 'none';
	return "ALIGN_INNER{align-items:{$items};text-align:{$text};margin-left:{$ml};margin-right:{$mr};}ALIGN_RULE{justify-content:{$rule_justify};}"
		. "ALIGN_AFTER{display:{$after};}";
};
$am = in_array( $settings->alignment_medium ?? '', array( 'left', 'center', 'right' ), true ) ? $settings->alignment_medium : '';
$ar = in_array( $settings->alignment_responsive ?? '', array( 'left', 'center', 'right' ), true ) ? $settings->alignment_responsive : '';
if ( '' !== $am ) {
	$css = str_replace( array( 'ALIGN_INNER', 'ALIGN_RULE', 'ALIGN_AFTER' ), array( "$node .ds-heading-inner", "$node .ds-heading-rule", "$node .ds-heading-sub--withrule .ds-heading-rule--after" ), $align_css( $am ) );
	echo "@media (max-width:{$bpm}px){ {$css} }\n";
}
if ( '' !== $ar ) {
	$css = str_replace( array( 'ALIGN_INNER', 'ALIGN_RULE', 'ALIGN_AFTER' ), array( "$node .ds-heading-inner", "$node .ds-heading-rule", "$node .ds-heading-sub--withrule .ds-heading-rule--after" ), $align_css( $ar ) );
	echo "@media (max-width:{$bpr}px){ {$css} }\n";
}

/* ---- Element gap (responsive) ---- */
$gap = $u( $settings->gap ?? '', 12 );
echo "$node .ds-heading-inner { gap: {$gap}px; }\n";
if ( '' !== ( $settings->gap_medium ?? '' ) )     { echo "@media (max-width:{$bpm}px){ $node .ds-heading-inner { gap: " . (int) $settings->gap_medium . "px; } }\n"; }
if ( '' !== ( $settings->gap_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-heading-inner { gap: " . (int) $settings->gap_responsive . "px; } }\n"; }

/* ---- Max width (responsive) ---- */
if ( '' !== ( $settings->max_width ?? '' ) )            { echo "$node .ds-heading-inner { max-width: " . (int) $settings->max_width . "px; }\n"; }
if ( '' !== ( $settings->max_width_medium ?? '' ) )     { echo "@media (max-width:{$bpm}px){ $node .ds-heading-inner { max-width: " . (int) $settings->max_width_medium . "px; } }\n"; }
if ( '' !== ( $settings->max_width_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-heading-inner { max-width: " . (int) $settings->max_width_responsive . "px; } }\n"; }

/* ---- Colours ---- */
$sc = $col( $settings->subheading_color ?? '' );
if ( '' !== $sc ) { echo "$node .ds-heading-sub { color: {$sc}; }\n"; }
$hc = $col( $settings->heading_color ?? '' );
if ( '' !== $hc ) { echo "$node .ds-heading-title { color: {$hc}; }\n"; }
$ha = $col( $settings->heading_accent_color ?? '' );
if ( '' !== $ha ) { echo "$node .ds-heading-title .ds-heading-accent { color: {$ha}; }\n"; }
$dc = $col( $settings->description_color ?? '' );
if ( '' !== $dc ) { echo "$node .ds-heading-desc, $node .ds-heading-desc p { color: {$dc}; }\n"; }

/* ---- Divider / eyebrow rule ---- */
if ( ( $settings->divider_show ?? 'yes' ) === 'yes' ) {
	$dstyle = preg_replace( '/[^a-z]/', '', (string) ( $settings->divider_style ?? 'solid' ) );
	if ( ! in_array( $dstyle, array( 'solid', 'dashed', 'dotted', 'double', 'gradient' ), true ) ) { $dstyle = 'solid'; }
	$dw   = $u( $settings->divider_width ?? '', 48 );
	$dwu  = ( ( $settings->divider_width_unit ?? 'px' ) === '%' ) ? '%' : 'px';
	$dth  = $u( $settings->divider_thickness ?? '', 3 );
	$drad = $u( $settings->divider_radius ?? '', 2 );
	$dcol = $col( $settings->divider_color ?? '' ) ?: 'var(--fl-global-accent)';

	// Width + radius for every style.
	echo "$node .ds-heading-rule-line { width: {$dw}{$dwu}; border-radius: {$drad}px; }\n";

	if ( 'gradient' === $dstyle ) {
		echo "$node .ds-heading-rule-line { height: {$dth}px; background: linear-gradient(90deg, {$dcol}, transparent); border: 0; }\n";
	} elseif ( in_array( $dstyle, array( 'dashed', 'dotted', 'double' ), true ) ) {
		// Line drawn with a top border so dashed/dotted/double render correctly.
		$bh = ( 'double' === $dstyle ) ? max( 3, $dth ) : $dth;
		echo "$node .ds-heading-rule-line { height: 0; background: none; border-top: {$bh}px {$dstyle} {$dcol}; border-radius: 0; }\n";
	} else { // solid
		echo "$node .ds-heading-rule-line { height: {$dth}px; background: {$dcol}; border: 0; }\n";
	}

	// Image divider (GH #169): authored HEIGHT, width follows, per breakpoint. Same
	// max-height approach as the Style 2 end mark so the aspect ratio is preserved.
	if ( 'image' === ( $settings->divider_type ?? 'line' ) ) {
		foreach ( array( '' => '', '_medium' => $bpm, '_responsive' => $bpr ) as $sfx => $bp ) {
			$v = $settings->{ 'divider_img_h' . $sfx } ?? '';
			if ( '' === $v || null === $v ) { continue; }
			$rule = "$node .ds-heading-rule-img { max-height: " . (int) $v . "px; }";
			echo ( '' === $sfx ) ? "$rule\n" : "@media (max-width:{$bp}px){ $rule }\n";
		}
	}

	// Gap between an inline (eyebrow) rule and the sub-heading text.
	$dgap = $u( $settings->divider_gap ?? '', 14 );
	echo "$node .ds-heading-sub--withrule { gap: {$dgap}px; }\n";
}

/* ---- Spacing: padding + margin (deferred) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-heading",
		'unit'         => 'px',
		'props'        => array( 'padding-top' => 'padding_top', 'padding-right' => 'padding_right', 'padding-bottom' => 'padding_bottom', 'padding-left' => 'padding_left' ),
	) );
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-heading",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

/* ---- Typography (deferred; flushed by FLBuilderCSS::render()) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	$typo = array(
		'subheading_typography'  => '.ds-heading-sub',
		'heading_typography'     => '.ds-heading-title',
		'description_typography' => '.ds-heading-desc',
	);
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{$key} ) ) {
			FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $key, 'selector' => "$node $sel" ) );
		}
	}
}

/* ---- Sub-heading Align (GH #36) ----
 * The sub-heading is a shrink-wrapped inline-flex span, so the text-align the
 * typography Align buttons write cannot move it. Mirror the chosen value as
 * align-self (the inner block is a flex column) at each breakpoint so the
 * eyebrow actually follows the alignment. */
$sub_align = static function ( $typo ) {
	$t = json_decode( wp_json_encode( $typo ), true );
	$a = is_array( $t ) && isset( $t['text_align'] ) ? $t['text_align'] : '';
	$map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
	return isset( $map[ $a ] ) ? $map[ $a ] : '';
};
$sa = $sub_align( $settings->subheading_typography ?? null );
if ( '' !== $sa ) { echo "$node .ds-heading-sub{align-self:{$sa};}\n"; }
$sa = $sub_align( $settings->subheading_typography_medium ?? null );
if ( '' !== $sa ) { echo "@media (max-width:{$bpm}px){ $node .ds-heading-sub{align-self:{$sa};} }\n"; }
$sa = $sub_align( $settings->subheading_typography_responsive ?? null );
if ( '' !== $sa ) { echo "@media (max-width:{$bpr}px){ $node .ds-heading-sub{align-self:{$sa};} }\n"; }

/* ---- Outline text ({outline}...{/outline}) per-module override: blank = Theme Setting. ---- */
$oc_ov = DS_Module_UI::color( $settings->outline_color ?? '' );
if ( '' !== $oc_ov ) { echo "$node { --ds-outline-c: {$oc_ov}; }\n"; }
if ( isset( $settings->outline_width ) && '' !== $settings->outline_width ) { echo "$node { --ds-outline-w: " . max( 1, (int) $settings->outline_width ) . "px; }\n"; }

/* ---- Style 2 (GH #143) ---- */
$sep_h = $u( $settings->divider_thickness ?? '', 2 );
echo "$node .ds-heading-sep { --ds-heading-sep-h: {$sep_h}px; }\n";
$dc = DS_Module_UI::color( $settings->divider_color ?? '' );
if ( '' !== $dc ) { echo "$node .ds-heading-sep { background: {$dc}; }\n"; }
foreach ( array( '' => '', '_medium' => $bpm, '_responsive' => $bpr ) as $sfx => $bp ) {
	$v = $settings->{'style2_mark_h' . $sfx} ?? '';
	if ( '' === $v || null === $v ) { continue; }
	// max-height, not height: paired with width:auto/max-width:100% the mark scales
	// down against whichever limit binds and never loses its aspect ratio.
	$rule = "$node .ds-heading-mark img { max-height: " . (int) $v . "px; }";
	echo ( '' === $sfx ) ? "$rule\n" : "@media (max-width:{$bp}px){ $rule }\n";
}
