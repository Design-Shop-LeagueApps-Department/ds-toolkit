<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Marquee — an in-house Beaver Builder scrolling-ticker module.
 *
 * A multi-STYLE module (same scaffold as ds-hero / ds-cta). Style 1 is the
 * "Scrolling Tape" broadcast ticker (modeled on the threehl home-dev design):
 * a slim full-width bar with a pinned accent label (with an optional pulsing
 * status dot) on the left and an infinitely scrolling row of items separated
 * by a glyph (a diamond by default). The scroll is a seamless CSS loop, pauses
 * on hover, honours prefers-reduced-motion, and can run left or right.
 *
 * HOW TO ADD A NEW STYLE:
 *   1. Add it to self::styles() (key + label).
 *   2. Add a render_<key>() method (reuse the shared helpers).
 *   3. Add its option sections to the form and list those keys under the
 *      `marquee_style` select's `toggle`.
 *   4. Scope style CSS under the auto-printed `.ds-marquee--<key>` modifier class.
 *
 * No UABB dependency; fully SSH-editable (markup here, styles in css/ +
 * includes/, behaviour in js/).
 *
 * @class DS_Marquee_Module
 */
class DS_Marquee_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Marquee', 'ds-toolkit' ),
			'description'     => __( 'Scrolling ticker / marquee bar with a pinned label (multi-style).', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-marquee/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-marquee/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	public static function styles() {
		return array(
			'style1' => __( 'Style 1 — Scrolling Tape', 'ds-toolkit' ),
			// 'style2' => __( 'Style 2 — …', 'ds-toolkit' ),
		);
	}

	/** Normalise a BB link field (string URL or {url,target} object/array) -> array(url,target). */
	private function link_parts( $link ) {
		$url = ''; $target = '_self';
		if ( is_array( $link ) )      { $url = $link['url'] ?? ''; $target = $link['target'] ?? '_self'; }
		elseif ( is_object( $link ) ) { $url = $link->url ?? '';  $target = $link->target ?? '_self'; }
		else { $url = (string) $link; }
		$url = trim( (string) $url );
		return array( $url, '_blank' === $target ? '_blank' : '_self' );
	}

	/** Render one item (linked <a> when a URL is set, otherwise a <span>). */
	private function item_html( $item ) {
		$item = (object) $item;
		$text = trim( (string) ( $item->text ?? '' ) );
		if ( '' === $text ) { return ''; }
		list( $url, $target ) = $this->link_parts( $item->link ?? '' );
		if ( '' !== $url && '#' !== $url ) {
			$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
			return sprintf(
				'<a class="ds-marquee-item ds-marquee-item--link" href="%s" target="%s"%s>%s</a>',
				esc_url( $url ),
				esc_attr( $target ),
				$rel,
				esc_html( $text )
			);
		}
		return '<span class="ds-marquee-item">' . esc_html( $text ) . '</span>';
	}

	/** Build one full group: every item followed by a separator glyph. */
	private function group_html( $items, $sep ) {
		$out = '';
		$sep_html = ( '' !== $sep )
			? '<span class="ds-marquee-sep" aria-hidden="true">' . esc_html( $sep ) . '</span>'
			: '';
		foreach ( $items as $item ) {
			$ih = $this->item_html( $item );
			if ( '' === $ih ) { continue; }
			$out .= $ih . $sep_html;
		}
		return $out;
	}

	/** Entry point — dispatch to the selected style. */
	public function render_marquee() {
		$style  = isset( $this->settings->marquee_style ) ? preg_replace( '/[^a-z0-9_]/', '', $this->settings->marquee_style ) : 'style1';
		$method = 'render_' . $style;
		if ( ! array_key_exists( $style, self::styles() ) || ! method_exists( $this, $method ) ) {
			$method = 'render_style1';
		}
		$this->$method();
	}

	/** Style 1 — Scrolling Tape (broadcast ticker). */
	public function render_style1() {
		$s = $this->settings;

		$items = isset( $s->items ) && is_array( $s->items ) ? $s->items : array();
		// Drop empty rows so the seamless loop maths stays clean.
		$items = array_values( array_filter( $items, function( $i ) {
			$i = (object) $i;
			return '' !== trim( (string) ( $i->text ?? '' ) );
		} ) );

		$mods = 'ds-marquee ds-marquee--style1';
		if ( ( $s->direction ?? 'left' ) === 'right' ) { $mods .= ' ds-marquee--right'; }
		if ( ( $s->pause_hover ?? 'yes' ) === 'yes' )   { $mods .= ' ds-marquee--pause'; }

		echo '<div class="' . esc_attr( $mods ) . '">';

		// Pinned label (optional).
		if ( ( $s->show_label ?? 'yes' ) === 'yes' && '' !== trim( (string) ( $s->label_text ?? '' ) ) ) {
			echo '<div class="ds-marquee-label">';
			if ( ( $s->show_dot ?? 'yes' ) === 'yes' ) {
				echo '<span class="ds-marquee-dot" aria-hidden="true"></span>';
			}
			echo '<span class="ds-marquee-label-text">' . esc_html( $s->label_text ) . '</span>';
			echo '</div>';
		}

		if ( empty( $items ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<div class="ds-marquee-empty">' . esc_html__( 'Add items in the module settings.', 'ds-toolkit' ) . '</div>';
			}
			echo '</div>';
			return;
		}

		$sep   = isset( $s->separator ) ? trim( (string) $s->separator ) : '◆';
		$group = $this->group_html( $items, $sep );

		// Two identical groups give a seamless translateX(-50%) loop. The JS tops
		// this up with more clones when one group is narrower than the viewport.
		echo '<div class="ds-marquee-viewport"><div class="ds-marquee-track">';
		echo '<div class="ds-marquee-group">' . $group . '</div>';
		echo '<div class="ds-marquee-group" aria-hidden="true">' . $group . '</div>';
		echo '</div></div>';

		echo '</div>';
	}
}

/* --------------------------------------------------------------- Item sub-form */
FLBuilder::register_settings_form( 'ds_marquee_item_form', array(
	'title' => __( 'Item', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Item', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'text' => array(
							'type'        => 'text',
							'label'       => __( 'Text', 'ds-toolkit' ),
							'connections' => array( 'string' ),
						),
						'link' => array(
							'type'        => 'link',
							'label'       => __( 'Link (optional)', 'ds-toolkit' ),
							'show_target' => true,
							'connections' => array( 'url' ),
							'help'        => __( 'Leave blank for plain text. With a link, the item becomes a hover-coloured anchor.', 'ds-toolkit' ),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Marquee_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'marquee_style_sec' => array(
				'title'  => __( 'Marquee Style', 'ds-toolkit' ),
				'fields' => array(
					'marquee_style' => array(
						'type'    => 'select',
						'label'   => __( 'Marquee Style', 'ds-toolkit' ),
						'default' => 'style1',
						'options' => DS_Marquee_Module::styles(),
						'help'    => __( 'Choose a marquee layout. More styles will be added; the options below adapt to the selected style.', 'ds-toolkit' ),
						'toggle'  => array(
							'style1' => array(
								'sections' => array( 'label', 'items', 'motion', 'layout', 'border', 'colors', 'typography', 'spacing' ),
							),
						),
					),
				),
			),
			'label' => array(
				'title'  => __( 'Pinned Label', 'ds-toolkit' ),
				'fields' => array(
					'show_label'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Label', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'label_text', 'show_dot' ) ) ),
						'help'    => __( 'The fixed accent chip pinned to the left of the bar.', 'ds-toolkit' ),
					),
					'label_text'  => array(
						'type'        => 'text',
						'label'       => __( 'Label Text', 'ds-toolkit' ),
						'default'     => 'Lorem Ipsum',
						'connections' => array( 'string' ),
					),
					'show_dot'    => array(
						'type'    => 'select',
						'label'   => __( 'Pulsing Status Dot', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'help'    => __( 'A small blinking dot before the label text (a live / on-air cue).', 'ds-toolkit' ),
					),
				),
			),
			'items' => array(
				'title'       => __( 'Items', 'ds-toolkit' ),
				'description' => __( 'The repeating messages that scroll across the bar. Each can optionally link somewhere.', 'ds-toolkit' ),
				'fields'      => array(
					'items' => array(
						'type'         => 'form',
						'label'        => __( 'Item', 'ds-toolkit' ),
						'form'         => 'ds_marquee_item_form',
						'preview_text' => 'text',
						'multiple'     => true,
						'default'      => array(
							array( 'text' => 'Lorem Ipsum Dolor' ),
							array( 'text' => 'Consectetur Adipiscing' ),
							array( 'text' => 'Sed Do Eiusmod' ),
							array( 'text' => 'Tempor Incididunt' ),
							array( 'text' => 'Magna Aliqua' ),
						),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'motion' => array(
				'title'  => __( 'Motion', 'ds-toolkit' ),
				'fields' => array(
					'speed'       => array(
						'type'        => 'unit',
						'label'       => __( 'Scroll Duration', 'ds-toolkit' ),
						'default'     => '32',
						'description' => 's',
						'slider'      => array( 'min' => 5, 'max' => 120, 'step' => 1 ),
						'help'        => __( 'Seconds for one full loop. Lower = faster.', 'ds-toolkit' ),
					),
					'direction'   => array(
						'type'    => 'select',
						'label'   => __( 'Direction', 'ds-toolkit' ),
						'default' => 'left',
						'options' => array( 'left' => __( 'Right → Left', 'ds-toolkit' ), 'right' => __( 'Left → Right', 'ds-toolkit' ) ),
					),
					'pause_hover' => array(
						'type'    => 'select',
						'label'   => __( 'Pause on Hover', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'help'    => __( 'The scroll always stops for visitors who prefer reduced motion.', 'ds-toolkit' ),
					),
				),
			),
			'layout' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'height'    => array(
						'type'        => 'unit',
						'label'       => __( 'Bar Height', 'ds-toolkit' ),
						'default'     => '34',
						'description' => 'px',
						'responsive'  => true,
						'slider'      => array( 'min' => 22, 'max' => 90, 'step' => 1 ),
					),
					'item_gap'  => array(
						'type'        => 'unit',
						'label'       => __( 'Item Spacing', 'ds-toolkit' ),
						'default'     => '38',
						'description' => 'px',
						'slider'      => array( 'min' => 10, 'max' => 120, 'step' => 1 ),
						'help'        => __( 'Gap between each item and its separator.', 'ds-toolkit' ),
					),
					'separator' => array(
						'type'    => 'text',
						'label'   => __( 'Separator', 'ds-toolkit' ),
						'default' => '◆',
						'help'    => __( 'Glyph between items, e.g. ◆ • / | ★ →. Leave blank for none.', 'ds-toolkit' ),
					),
					'sep_size'  => array(
						'type'        => 'unit',
						'label'       => __( 'Separator Size', 'ds-toolkit' ),
						'default'     => '12',
						'description' => 'px',
						'slider'      => array( 'min' => 4, 'max' => 48, 'step' => 1 ),
					),
					'label_pad' => array(
						'type'        => 'unit',
						'label'       => __( 'Label Side Padding', 'ds-toolkit' ),
						'default'     => '16',
						'description' => 'px',
						'slider'      => array( 'min' => 0, 'max' => 48, 'step' => 1 ),
					),
				),
			),
			'border' => array(
				'title'  => __( 'Border', 'ds-toolkit' ),
				'fields' => array(
					'border_style'   => array(
						'type'    => 'select',
						'label'   => __( 'Border', 'ds-toolkit' ),
						'default' => 'solid',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'solid' => __( 'Solid', 'ds-toolkit' ), 'dashed' => __( 'Dashed', 'ds-toolkit' ), 'dotted' => __( 'Dotted', 'ds-toolkit' ) ),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'border_sides', 'border_width', 'border_color', 'border_opacity' ) ),
							'dashed' => array( 'fields' => array( 'border_sides', 'border_width', 'border_color', 'border_opacity' ) ),
							'dotted' => array( 'fields' => array( 'border_sides', 'border_width', 'border_color', 'border_opacity' ) ),
						),
					),
					'border_sides'   => array( 'type' => 'select', 'label' => __( 'Border Sides', 'ds-toolkit' ), 'default' => 'bottom', 'options' => array( 'bottom' => __( 'Bottom', 'ds-toolkit' ), 'top' => __( 'Top', 'ds-toolkit' ), 'both' => __( 'Top & Bottom', 'ds-toolkit' ) ) ),
					'border_width'   => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
					'border_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'help' => __( 'Blank falls back to the global Accent colour.', 'ds-toolkit' ) ),
					'border_opacity' => array( 'type' => 'unit', 'label' => __( 'Border Opacity', 'ds-toolkit' ), 'default' => '18', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
				),
			),
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'bar_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Bar Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'item_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Item Text', 'ds-toolkit' ), 'default' => 'var(--fl-global-light-background)', 'show_reset' => true ),
					'item_hover_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Item Hover (links)', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'sep_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Separator', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'help' => __( 'Blank falls back to the global Accent colour.', 'ds-toolkit' ) ),
					'label_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Label Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'help' => __( 'Blank falls back to the global Accent colour.', 'ds-toolkit' ) ),
					'label_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Label Text', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'dot_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Status Dot', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'item_typography'  => array( 'type' => 'typography', 'label' => __( 'Items', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-marquee-item' ) ),
					'label_typography' => array( 'type' => 'typography', 'label' => __( 'Label', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-marquee-label-text' ) ),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'margin' => array(
						'type'       => 'dimension',
						'label'      => __( 'Margin', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Outer spacing around the whole bar. 0 = flush.', 'ds-toolkit' ),
					),
				),
			),
		),
	),
) );
