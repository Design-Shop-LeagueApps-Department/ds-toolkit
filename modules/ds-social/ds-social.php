<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Social — an in-house Beaver Builder social-icons module.
 *
 * Renders a row of social icons whose URLs come from the ACF Partner Settings
 * (partner_fb / partner_instagram / partner_x / partner_youtube /
 * partner_linkedin / partner_tiktok). A network with no Partner Setting value
 * is silently skipped, and if none are set the module renders nothing on the
 * front end (a hint shows only inside the builder).
 *
 * Style controls: optional background tile (with its own colour + hover +
 * padding + radius), icon size, icon gap, icon colour + hover colour, and a
 * responsive alignment. Icon size / gap / alignment are fully responsive.
 *
 * Fully owned / SSH-editable (markup here, styles in css/ + includes/frontend.css.php).
 * No icon-font or UABB dependency — brand marks are inline SVG (simple-icons).
 *
 * @class DS_Social_Module
 */
class DS_Social_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Partner Social', 'ds-toolkit' ),
			'description'     => __( 'Social icons pulled from Partner Settings.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-social/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-social/',
			// No 'icon' param: BB auto-loads modules/ds-social/icon.svg. (A bare
			// filename here is only resolved against BB's own img/svg/ bundle, so
			// a custom name like social.svg would fall back to a broken dashicon.)
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/**
	 * Network registry: key => [ Partner Settings ACF field, accessible label ].
	 * Order here is the render order.
	 */
	public static function networks() {
		return array(
			'facebook'  => array( 'field' => 'partner_fb',        'label' => 'Facebook' ),
			'instagram' => array( 'field' => 'partner_instagram', 'label' => 'Instagram' ),
			'x'         => array( 'field' => 'partner_x',          'label' => 'X' ),
			'youtube'   => array( 'field' => 'partner_youtube',    'label' => 'YouTube' ),
			'linkedin'  => array( 'field' => 'partner_linkedin',   'label' => 'LinkedIn' ),
			'tiktok'    => array( 'field' => 'partner_tiktok',      'label' => 'TikTok' ),
		);
	}

	/** Read a Partner Settings URL, falling back to post meta if ACF is absent. */
	private function partner_url( $field ) {
		if ( function_exists( 'get_field' ) ) {
			$v = get_field( $field, 'option' );
		} else {
			$v = get_option( 'options_' . $field );
		}
		$v = is_string( $v ) ? trim( $v ) : '';
		return $v;
	}

	/** Inline brand SVG (viewBox 0 0 24 24, single path, fill via currentColor). */
	public static function svg( $key ) {
		$paths = array(
			'facebook'  => 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z',
			'instagram' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z',
			'x'         => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
			'youtube'   => 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
			'linkedin'  => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
			'tiktok'    => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.08-.14 1.62.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
		);
		if ( empty( $paths[ $key ] ) ) {
			return '';
		}
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $paths[ $key ] . '"/></svg>';
	}

	/** Entry point called by includes/frontend.php. */
	public function render_icons() {
		$s      = $this->settings;
		$target = ( isset( $s->link_target ) && '_self' === $s->link_target ) ? '_self' : '_blank';

		$links = array();
		foreach ( self::networks() as $key => $net ) {
			$url = $this->partner_url( $net['field'] );
			if ( '' === $url ) {
				continue; // no Partner Setting -> don't show this icon
			}
			$rel  = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';
			$links[] = sprintf(
				'<a class="ds-social-link ds-social-%1$s" href="%2$s" target="%3$s"%4$s aria-label="%5$s">%6$s</a>',
				esc_attr( $key ),
				esc_url( $url ),
				esc_attr( $target ),
				$rel,
				esc_attr( $net['label'] ),
				self::svg( $key )
			);
		}

		if ( empty( $links ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:12px;margin:0;opacity:.7">'
					. esc_html__( 'No Partner Settings social links are set, so nothing shows on the live site. Add URLs under Partner Settings (Facebook, Instagram, X, YouTube, LinkedIn, TikTok).', 'ds-toolkit' )
					. '</p>';
			}
			return;
		}

		$wrap = 'ds-social';
		if ( ! isset( $s->show_bg ) || 'yes' === $s->show_bg ) {
			$wrap .= ' ds-social--bg';
		}

		echo '<nav class="' . esc_attr( $wrap ) . '" aria-label="' . esc_attr__( 'Social media', 'ds-toolkit' ) . '">'
			. implode( '', $links )
			. '</nav>';
	}
}

FLBuilder::register_module( 'DS_Social_Module', array(
	'general' => array(
		'title'    => __( 'General', 'ds-toolkit' ),
		'sections' => array(
			'links' => array(
				'title'  => __( 'Links', 'ds-toolkit' ),
				'fields' => array(
					'link_target' => array(
						'type'    => 'select',
						'label'   => __( 'Open Links In', 'ds-toolkit' ),
						'default' => '_blank',
						'options' => array(
							'_blank' => __( 'New Tab', 'ds-toolkit' ),
							'_self'  => __( 'Same Tab', 'ds-toolkit' ),
						),
					),
					'alignment'   => array(
						'type'       => 'select',
						'label'      => __( 'Alignment', 'ds-toolkit' ),
						'default'    => 'center',
						'responsive' => true,
						'options'    => array(
							'flex-start' => __( 'Left', 'ds-toolkit' ),
							'center'     => __( 'Center', 'ds-toolkit' ),
							'flex-end'   => __( 'Right', 'ds-toolkit' ),
						),
						'help'       => __( 'Icons auto-render from Partner Settings; any network without a URL is skipped, and the module hides entirely when none are set.', 'ds-toolkit' ),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'icons' => array(
				'title'  => __( 'Icons', 'ds-toolkit' ),
				'fields' => array(
					'icon_size'        => array(
						'type'       => 'unit',
						'label'      => __( 'Icon Size', 'ds-toolkit' ),
						'default'    => '18',
						'description'=> 'px',
						'responsive' => true,
						'slider'     => array( 'min' => 8, 'max' => 60, 'step' => 1 ),
					),
					'icon_gap'         => array(
						'type'       => 'unit',
						'label'      => __( 'Gap Between Icons', 'ds-toolkit' ),
						'default'    => '10',
						'description'=> 'px',
						'responsive' => true,
						'slider'     => array( 'min' => 0, 'max' => 60, 'step' => 1 ),
					),
					'icon_color'       => array(
						'type'        => 'color',
						'connections' => array( 'color' ),
						'label'       => __( 'Icon Color', 'ds-toolkit' ),
						'default'     => 'var(--fl-global-white)',
						'show_reset'  => true,
						'show_alpha'  => true,
					),
					'icon_hover_color' => array(
						'type'        => 'color',
						'connections' => array( 'color' ),
						'label'       => __( 'Icon Hover Color', 'ds-toolkit' ),
						'show_reset'  => true,
						'show_alpha'  => true,
						'help'        => __( 'Blank keeps the icon color on hover.', 'ds-toolkit' ),
					),
				),
			),
			'bg'    => array(
				'title'  => __( 'Background Tile', 'ds-toolkit' ),
				'fields' => array(
					'show_bg'         => array(
						'type'    => 'select',
						'label'   => __( 'Icon Background', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array(
							'yes' => __( 'Show (tile behind icon)', 'ds-toolkit' ),
							'no'  => __( 'None (icon only)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'yes' => array( 'fields' => array( 'bg_color', 'bg_hover_color', 'bg_padding', 'bg_radius' ) ),
						),
					),
					'bg_color'        => array(
						'type'        => 'color',
						'connections' => array( 'color' ),
						'label'       => __( 'Background Color', 'ds-toolkit' ),
						'default'     => 'var(--fl-global-primary)',
						'show_reset'  => true,
						'show_alpha'  => true,
					),
					'bg_hover_color'  => array(
						'type'        => 'color',
						'connections' => array( 'color' ),
						'label'       => __( 'Background Hover Color', 'ds-toolkit' ),
						'default'     => 'var(--fl-global-accent)',
						'show_reset'  => true,
						'show_alpha'  => true,
						'help'        => __( 'Blank keeps the background color on hover.', 'ds-toolkit' ),
					),
					'bg_padding'      => array(
						'type'       => 'unit',
						'label'      => __( 'Tile Padding', 'ds-toolkit' ),
						'default'    => '12',
						'description'=> 'px',
						'responsive' => true,
						'slider'     => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
					),
					'bg_radius'       => array(
						'type'       => 'unit',
						'label'      => __( 'Corner Radius', 'ds-toolkit' ),
						'default'    => '',
						'description'=> 'px',
						'slider'     => array( 'min' => 0, 'max' => 50, 'step' => 1 ),
						'help'       => __( 'Blank uses the global Corner Radius.', 'ds-toolkit' ),
					),
				),
			),
		),
	),
) );
