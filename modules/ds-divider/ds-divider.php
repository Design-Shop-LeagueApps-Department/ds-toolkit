<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Divider — an in-house Beaver Builder divider module.
 *
 * Horizontal or vertical, with several effects:
 *   - solid     : a plain line
 *   - gradient  : fades out at both ends (gradient border)
 *   - running   : a bright light sweeps along the line like a loading bar
 *                 (down for vertical, across for horizontal)
 *   - glow      : the line pulses / glows
 *   - dashed    : marching dashes
 *
 * All colours connect to the site's global colours; every animation honours
 * prefers-reduced-motion. Markup here, styles in css/ + includes/frontend.css.php.
 *
 * @class DS_Divider_Module
 */
class DS_Divider_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Divider', 'ds-toolkit' ),
			'description'     => __( 'Horizontal or vertical divider with animated effects (gradient fade, running light, glow, dashes).', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-divider/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-divider/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** Entry point called by includes/frontend.php. */
	public function render_divider() {
		$s = $this->settings;
		$o = ( isset( $s->orientation ) && 'vertical' === $s->orientation ) ? 'vertical' : 'horizontal';
		$allowed = array( 'solid', 'gradient', 'running', 'glow', 'dashed' );
		$e = ( isset( $s->effect ) && in_array( $s->effect, $allowed, true ) ) ? $s->effect : 'solid';
		$align = 'center';
		if ( 'horizontal' === $o && isset( $s->h_align ) && in_array( $s->h_align, array( 'left', 'center', 'right' ), true ) ) {
			$align = $s->h_align;
		}
		$cls = 'ds-divider ds-divider--' . ( 'vertical' === $o ? 'v' : 'h' ) . ' is-' . $e . ' align-' . $align;
		printf(
			'<div class="%s" role="separator" aria-orientation="%s"><span class="ds-divider-line" aria-hidden="true"></span></div>',
			esc_attr( $cls ),
			esc_attr( 'vertical' === $o ? 'vertical' : 'horizontal' )
		);
	}
}

FLBuilder::register_module( 'DS_Divider_Module', array(
	'general' => array(
		'title'    => __( 'Divider', 'ds-toolkit' ),
		'sections' => array(
			'divider' => array(
				'title'  => '',
				'fields' => array(
					'orientation' => array(
						'type'    => 'select',
						'label'   => __( 'Orientation', 'ds-toolkit' ),
						'default' => 'horizontal',
						'options' => array(
							'horizontal' => __( 'Horizontal', 'ds-toolkit' ),
							'vertical'   => __( 'Vertical', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'horizontal' => array( 'fields' => array( 'h_width', 'h_width_unit', 'h_align' ) ),
							'vertical'   => array( 'fields' => array( 'v_height' ) ),
						),
					),
					'effect'      => array(
						'type'    => 'select',
						'label'   => __( 'Effect', 'ds-toolkit' ),
						'default' => 'gradient',
						'options' => array(
							'solid'    => __( 'Solid Line', 'ds-toolkit' ),
							'gradient' => __( 'Gradient Fade', 'ds-toolkit' ),
							'running'  => __( 'Running Light (loading)', 'ds-toolkit' ),
							'glow'     => __( 'Glow Pulse', 'ds-toolkit' ),
							'dashed'   => __( 'Marching Dashes', 'ds-toolkit' ),
						),
						'help'    => __( 'Solid is a plain line. Gradient fades at the ends. Running Light sweeps along like a loading bar (down when vertical). Glow pulses. Dashes march.', 'ds-toolkit' ),
						'toggle'  => array(
							'gradient' => array( 'fields' => array( 'color2' ) ),
							'running'  => array( 'fields' => array( 'color2', 'speed', 'reverse' ) ),
							'glow'     => array( 'fields' => array( 'speed', 'glow_size' ) ),
							'dashed'   => array( 'fields' => array( 'speed', 'reverse', 'dash_len', 'dash_gap' ) ),
						),
					),
				),
			),
			'colors'  => array(
				'title'  => __( 'Colors', 'ds-toolkit' ),
				'fields' => array(
					'color1' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Color', 'ds-toolkit' ), 'default' => 'var(--fl-global-primary)', 'show_reset' => true, 'show_alpha' => true ),
					'color2' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Second Color', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Used by Gradient (the blend) and Running Light (the sweep colour).', 'ds-toolkit' ) ),
				),
			),
			'size'    => array(
				'title'  => __( 'Size & Shape', 'ds-toolkit' ),
				'fields' => array(
					'thickness'    => array( 'type' => 'unit', 'label' => __( 'Thickness', 'ds-toolkit' ), 'default' => '3', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 30, 'step' => 1 ) ),
					'h_width'      => array( 'type' => 'unit', 'label' => __( 'Length', 'ds-toolkit' ), 'default' => '100', 'slider' => array( 'min' => 5, 'max' => 100, 'step' => 1 ) ),
					'h_width_unit' => array( 'type' => 'select', 'label' => __( 'Length Unit', 'ds-toolkit' ), 'default' => '%', 'options' => array( '%' => '%', 'px' => 'px' ) ),
					'h_align'      => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
					'v_height'     => array( 'type' => 'unit', 'label' => __( 'Height', 'ds-toolkit' ), 'default' => '80', 'description' => 'px', 'slider' => array( 'min' => 10, 'max' => 600, 'step' => 5 ) ),
					'rounded'      => array( 'type' => 'select', 'label' => __( 'Rounded Ends', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'spacing'      => array( 'type' => 'unit', 'label' => __( 'Spacing', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Margin around the divider (top/bottom when horizontal, left/right when vertical).', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 120, 'step' => 1 ) ),
				),
			),
			'anim'    => array(
				'title'  => __( 'Animation', 'ds-toolkit' ),
				'fields' => array(
					'speed'     => array( 'type' => 'unit', 'label' => __( 'Speed', 'ds-toolkit' ), 'default' => '2', 'description' => 's', 'slider' => array( 'min' => 0.3, 'max' => 8, 'step' => 0.1 ), 'help' => __( 'Seconds per cycle. Lower is faster.', 'ds-toolkit' ) ),
					'reverse'   => array( 'type' => 'select', 'label' => __( 'Reverse Direction', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ) ),
					'glow_size' => array( 'type' => 'unit', 'label' => __( 'Glow Size', 'ds-toolkit' ), 'default' => '12', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'dash_len'  => array( 'type' => 'unit', 'label' => __( 'Dash Length', 'ds-toolkit' ), 'default' => '12', 'description' => 'px', 'slider' => array( 'min' => 2, 'max' => 40, 'step' => 1 ) ),
					'dash_gap'  => array( 'type' => 'unit', 'label' => __( 'Dash Gap', 'ds-toolkit' ), 'default' => '10', 'description' => 'px', 'slider' => array( 'min' => 2, 'max' => 40, 'step' => 1 ) ),
				),
			),
		),
	),
) );
