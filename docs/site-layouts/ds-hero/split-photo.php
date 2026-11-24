<?php
/**
 * Layout Name: Split Photo
 * Description: Text and buttons on the left, the first Background photo in a frame on the right, with an optional badge.
 * Uses: text, seo, buttons, bg, spacing
 * Hide: kenburns, slide_nav
 *
 * Example DS Toolkit site layout. Copy this file, split-photo.json and split-photo.css to
 * <child theme>/ds-layouts/ds-hero/ and pick "Site: Split Photo" in the Hero Banner's Hero Style.
 * Available here: $module (the Hero Banner), $settings (all its settings), $fields (this layout's own fields
 * from split-photo.json, by name) and $layout (this file's header data).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$photo = $module->photo_urls( 'large' );
$frame = in_array( $fields['frame'], array( 'square', 'rounded', 'card' ), true ) ? $fields['frame'] : 'card';
echo $module->part( 'open', 'ds-split ds-split--' . $frame );
echo '<div class="ds-split-wrap"><div class="ds-split-text">';
echo $module->part( 'eyebrow' ) . $module->part( 'heading' ) . $module->part( 'text' ) . $module->part( 'buttons' );
echo '</div>';
if ( $photo ) {
	echo '<figure class="ds-split-photo"><img src="' . esc_url( $photo[0] ) . '" alt="" loading="eager">';
	if ( '' !== trim( (string) $fields['badge'] ) ) {
		echo '<figcaption class="ds-split-badge" style="--ds-split-badge:' . esc_attr( DS_Module_UI::color( $fields['badge_color'] ) ?: 'var(--fl-global-accent)' ) . '">' . esc_html( $fields['badge'] ) . '</figcaption>';
	}
	echo '</figure>';
}
echo '</div>' . $module->part( 'close' );
