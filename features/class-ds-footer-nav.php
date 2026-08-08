<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * `[ds_footer_nav]` — a one-line footer navigation with the partner logo centred
 * inside it (the ISP Event Center footer pattern).
 *
 *     LEAGUES   TOURNAMENTS   PARTIES   [ LOGO ]   RENTALS   RULES   CONTACT
 *
 * WHY A SHORTCODE AND NOT A HAND-BUILT ROW
 * ----------------------------------------
 * The obvious build is three columns — menu / logo / menu — but a single WordPress
 * menu cannot be split across two Beaver Builder Menu modules without hiding items
 * by `:nth-child`, which silently breaks the moment a partner adds or removes a
 * page. Rendering it here means the split is computed from the live menu every
 * time, so the footer follows the partner's navigation with no template edits.
 *
 * Everything it draws is fleet-aware:
 *   - items come from a real WP menu (top level only — a footer bar is not a place
 *     for dropdowns)
 *   - the logo is the ACF `partner_logo` option, falling back to the theme's
 *     Custom Logo and then the Theme Setting social card
 *   - every colour is a `var(--fl-global-*)` with a literal fallback, so it tracks
 *     Theme Setting and still renders on older installs that never defined those
 *     custom properties
 */
class DS_Footer_Nav {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_shortcode( 'ds_footer_nav', array( $this, 'render' ) );
	}

	/**
	 * Resolve the brand image: partner logo → theme custom logo → social card.
	 *
	 * @return string URL or ''.
	 */
	private function logo_url() {
		if ( function_exists( 'get_field' ) ) {
			$logo = get_field( 'partner_logo', 'option' );
			$url  = class_exists( 'DS_Card' ) ? DS_Card::photo_url( $logo, 'medium' ) : '';
			if ( $url ) {
				return $url;
			}
		}
		$custom = get_theme_mod( 'custom_logo' );
		if ( $custom ) {
			$url = wp_get_attachment_image_url( $custom, 'medium' );
			if ( $url ) {
				return $url;
			}
		}
		return class_exists( 'DS_Card' ) ? (string) DS_Card::placeholder_image() : '';
	}

	/**
	 * Top-level items of the requested menu.
	 *
	 * Accepts a menu name, slug, id, or a theme location — a partner site may have
	 * any of those wired up, and guessing wrong would render an empty footer.
	 *
	 * @param string $menu
	 * @return array
	 */
	private function menu_items( $menu ) {
		$obj = '' !== $menu ? wp_get_nav_menu_object( $menu ) : false;

		if ( ! $obj ) {
			$locations = get_nav_menu_locations();
			$try       = array();
			if ( '' !== $menu && isset( $locations[ $menu ] ) ) {
				$try[] = $locations[ $menu ];
			}
			foreach ( array( 'primary', 'main', 'footer' ) as $loc ) {
				if ( ! empty( $locations[ $loc ] ) ) {
					$try[] = $locations[ $loc ];
				}
			}
			foreach ( $try as $id ) {
				$obj = wp_get_nav_menu_object( $id );
				if ( $obj ) {
					break;
				}
			}
		}
		// Last resort: the first menu that exists, so a fresh clone still renders.
		if ( ! $obj ) {
			$all = wp_get_nav_menus();
			$obj = $all ? $all[0] : false;
		}
		if ( ! $obj ) {
			return array();
		}

		$items = wp_get_nav_menu_items( $obj->term_id );
		if ( ! $items ) {
			return array();
		}

		$top = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item->menu_item_parent ) ) {
				continue; // footer bar is single-level by design
			}
			$top[] = $item;
		}
		return $top;
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public function render( $atts ) {
		$a = shortcode_atts(
			array(
				'menu'        => '',      // menu name / slug / id / theme location
				'logo_height' => '54',    // px
				'gap'         => '48',    // px between items
				'uppercase'   => 'yes',
				'show_logo'   => 'yes',
			),
			$atts,
			'ds_footer_nav'
		);

		$items = $this->menu_items( (string) $a['menu'] );
		if ( empty( $items ) ) {
			// Builder-only hint; nothing on the live page.
			if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
				return '<p style="padding:14px;opacity:.7">'
					. esc_html__( 'No menu found for [ds_footer_nav]. Create a menu, or pass menu="Primary Menu".', 'ds-toolkit' )
					. '</p>';
			}
			return '';
		}

		$logo  = ( 'no' === $a['show_logo'] ) ? '' : $this->logo_url();
		$split = $logo ? (int) ceil( count( $items ) / 2 ) : count( $items );

		$li = function ( $item ) {
			$url    = ! empty( $item->url ) ? $item->url : '#';
			$target = ! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '" rel="noopener"' : '';
			return '<li class="ds-footernav-item"><a href="' . esc_url( $url ) . '"' . $target . '>'
				. esc_html( $item->title ) . '</a></li>';
		};

		$out = '<nav class="ds-footernav" aria-label="' . esc_attr__( 'Footer', 'ds-toolkit' ) . '"><ul class="ds-footernav-list">';
		foreach ( $items as $i => $item ) {
			if ( $logo && $i === $split ) {
				$out .= '<li class="ds-footernav-brand"><a href="' . esc_url( home_url( '/' ) ) . '">'
					. '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" loading="lazy" /></a></li>';
			}
			$out .= $li( $item );
		}
		// Odd counts put the logo last if the split never landed mid-loop.
		if ( $logo && $split >= count( $items ) ) {
			$out .= '<li class="ds-footernav-brand"><a href="' . esc_url( home_url( '/' ) ) . '">'
				. '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" loading="lazy" /></a></li>';
		}
		$out .= '</ul></nav>';

		$out .= $this->css( (int) $a['logo_height'], (int) $a['gap'], 'no' !== $a['uppercase'] );

		return $out;
	}

	/**
	 * Scoped CSS, emitted with the markup so the shortcode works anywhere (BB HTML
	 * module, widget, theme template) without a separate stylesheet to enqueue.
	 *
	 * Colours are `var(--fl-global-*, literal)`: they follow Theme Setting, and the
	 * literal keeps them visible on installs that never defined the custom
	 * properties (a var() with no fallback drops the whole declaration).
	 */
	private function css( $logo_h, $gap, $upper ) {
		static $done = false;
		if ( $done ) {
			return ''; // one copy per page even if the shortcode is used twice
		}
		$done = true;

		$logo_h = max( 20, min( 200, $logo_h ) );
		$gap    = max( 8, min( 120, $gap ) );
		$tt     = $upper ? 'uppercase' : 'none';

		// Beaver Builder paints every link inside a layout with
		// `.fl-builder-content a:not(.fl-builder-submenu-link){color:var(--fl-global-primary)}`
		// — specificity (0,2,1), which beats a plain `.ds-footernav-item a` (0,1,1)
		// no matter where our <style> sits. So the link colours are scoped to our
		// own wrapper AND forced; the scope keeps the !important from leaking.
		return '<style id="ds-footernav-css">'
			. '.ds-footernav-list{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;'
				. 'gap:' . $gap . 'px;margin:0;padding:0;list-style:none;}'
			. '.ds-footernav .ds-footernav-item a{color:var(--fl-global-white,#fff) !important;text-decoration:none;'
				. 'font-weight:700;font-size:.9rem;letter-spacing:.06em;text-transform:' . $tt . ';'
				. 'white-space:nowrap;transition:color .2s ease,opacity .2s ease;}'
			. '.ds-footernav .ds-footernav-item a:hover,.ds-footernav .ds-footernav-item a:focus'
				. '{color:var(--fl-global-accent,#069e33) !important;}'
			. '.ds-footernav-brand{display:flex;align-items:center;justify-content:center;line-height:0;}'
			. '.ds-footernav-brand img{height:' . $logo_h . 'px;width:auto;max-width:100%;display:block;}'
			// Phones: brand first, then the links beneath it in a tighter grid.
			. '@media (max-width:900px){'
				. '.ds-footernav-list{gap:' . max( 12, (int) round( $gap / 2 ) ) . 'px;}'
				. '.ds-footernav-brand{order:-1;flex:0 0 100%;margin-bottom:8px;}'
				. '.ds-footernav-item a{font-size:.82rem;}'
			. '}'
			. '</style>';
	}
}
