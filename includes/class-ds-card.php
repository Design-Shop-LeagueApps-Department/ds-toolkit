<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shared card / people helpers for the in-house LeagueApps modules.
 *
 * Single source of truth for the contact/brand SVG glyphs, the stretched
 * card-link overlay, the BB photo-value + link-field normalisers, and the
 * Social Card image fallback. Lets ds-post-loop, ds-team-detail, ds-carousel,
 * ds-page-cards, ds-orgstats, ds-hero, etc. drop their copy-pasted versions.
 *
 * Every string here is the verbatim markup the modules already emit, so routing
 * a module through these helpers keeps its rendered HTML byte-identical.
 */
class DS_Card {

	/** Name-keyed SVG glyph registry (verbatim, currentColor-driven). */
	public static function icon( $name ) {
		$icons = array(
			'mail'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>',
			'phone'     => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11.4 11.4 0 0 0 3.6.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .6 3.6 1 1 0 0 1-.24 1z"/></svg>',
			'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>',
			'linkedin'  => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5A2.5 2.5 0 1 1 0 3.5a2.5 2.5 0 0 1 4.98 0zM.2 8.4h4.6V24H.2zM8 8.4h4.4v2.1h.06c.6-1.1 2.1-2.3 4.3-2.3 4.6 0 5.4 3 5.4 6.9V24h-4.6v-6.9c0-1.6 0-3.8-2.3-3.8s-2.7 1.8-2.7 3.6V24H8z"/></svg>',
			'facebook'  => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/></svg>',
			'x'         => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2H22l-7.3 8.3L23.3 22h-6.8l-5.3-6.9L5.1 22H2l7.8-8.9L1.5 2h7l4.8 6.3L18.9 2zm-2.4 18h1.9L7.6 4H5.6z"/></svg>',
			'arrow'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
			'external'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M14 5h5v5M19 5l-9 9M11 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-5"/></svg>',
		);
		return $icons[ $name ] ?? '';
	}

	/**
	 * A round contact/social icon link (.ds-people-ico).
	 * $type: 'mail' (mailto:), 'tel' (tel:, digits only), or 'url' (external, _blank).
	 */
	public static function contact_link( $href, $svg, $label, $type = 'url' ) {
		$href = trim( (string) $href );
		if ( '' === $href ) { return ''; }
		if ( 'mail' === $type )      { $url = 'mailto:' . $href; $t = ''; }
		elseif ( 'tel' === $type )   { $url = 'tel:' . preg_replace( '/[^0-9+]/', '', $href ); $t = ''; }
		else { $url = esc_url( $href ); $t = ' target="_blank" rel="noopener noreferrer"'; }
		return '<a class="ds-people-ico" href="' . esc_attr( $url ) . '" aria-label="' . esc_attr( $label ) . '"' . $t . '>' . $svg . '</a>';
	}

	/** Stretched overlay link covering a whole card (icons stay clickable above it via z-index). */
	public static function stretched_link( $url, $label ) {
		return '<a class="ds-card-link" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $label ) . '"></a>';
	}

	/** Resolve a BB photo value (id | url | array{ID|id|url} | object) to a URL. Blank -> ''. */
	public static function photo_url( $val, $size = 'large' ) {
		if ( is_object( $val ) ) { $val = $val->ID ?? ( $val->id ?? ( $val->url ?? '' ) ); }
		if ( is_array( $val ) )  { $val = $val['ID'] ?? ( $val['id'] ?? ( $val['url'] ?? '' ) ); }
		if ( is_numeric( $val ) ) { $u = wp_get_attachment_image_url( (int) $val, $size ); return $u ? $u : ''; }
		return $val ? esc_url( (string) $val ) : '';
	}

	/** Resolve a BB link field (string | array | object) to array( esc_url($url|'#'), '_self'|'_blank' ). */
	public static function link_parts( $link ) {
		$url = ''; $target = '_self';
		if ( is_array( $link ) )      { $url = $link['url'] ?? ''; $target = $link['target'] ?? '_self'; }
		elseif ( is_object( $link ) ) { $url = $link->url ?? '';  $target = $link->target ?? '_self'; }
		else { $url = (string) $link; }
		return array( esc_url( $url ?: '#' ), $target === '_blank' ? '_blank' : '_self' );
	}

	/** The Theme Setting Social Card image URL, used as the people/card image fallback. '' if unset. */
	public static function placeholder_image() {
		if ( class_exists( 'DS_Social_Card' ) && method_exists( 'DS_Social_Card', 'get_card' ) ) {
			$card = DS_Social_Card::get_card();
			if ( ! empty( $card['url'] ) ) { return (string) $card['url']; }
		}
		return '';
	}
}
