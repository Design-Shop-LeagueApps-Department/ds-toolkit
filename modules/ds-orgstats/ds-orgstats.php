<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Org Stats — an in-house Beaver Builder stats / "by the numbers" band.
 *
 * A multi-STYLE module (same scaffold as ds-hero / ds-cta / ds-marquee). Style 1
 * is the "Record" band (modeled on the nhbfutbolclub design): a full-bleed dark
 * section over a faded background photo + gradient overlay, a centred eyebrow +
 * heading, then a bordered grid of big stat figures (each an optional prefix, a
 * count-up number, an optional accent suffix like "+" or "×", and an uppercase
 * label) separated by clean grid lines. The figures count up when scrolled into
 * view (vanilla JS, honours prefers-reduced-motion).
 *
 * HOW TO ADD A NEW STYLE:
 *   1. Add it to self::styles() (key + label).
 *   2. Add a render_<key>() method (reuse the shared helpers).
 *   3. Add its option sections to the form and list those keys under the
 *      `orgstats_style` select's `toggle` (keep section keys globally unique).
 *   4. Scope style CSS under the auto-printed `.ds-orgstats--<key>` modifier class.
 *
 * No UABB dependency; fully SSH-editable (markup here, styles in css/ + includes/,
 * count-up behaviour in js/).
 *
 * @class DS_OrgStats_Module
 */
class DS_OrgStats_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Org Stats', 'ds-toolkit' ),
			'description'     => __( 'By-the-numbers stats band with count-up figures (multi-style).', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-orgstats/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-orgstats/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	public static function styles() {
		return array(
			'style1' => __( 'Style 1 — Record Band', 'ds-toolkit' ),
			// 'style2' => __( 'Style 2 — …', 'ds-toolkit' ),
		);
	}

	/** Heading markup: escape, {a}..{/a} -> accent span, newlines -> <br>. */
	private function heading_html( $raw ) {
		$h = esc_html( (string) $raw );
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-orgstats-accent">', '</span>' ), $h );
		return nl2br( $h );
	}

	/** Resolve a BB photo value (id, url, or {id|url} array/object) -> URL string. */
	private function photo_url( $val, $size = 'full' ) {
		if ( is_array( $val ) )      { $val = $val['id'] ?? ( $val['url'] ?? '' ); }
		elseif ( is_object( $val ) ) { $val = $val->id ?? ( $val->url ?? '' ); }
		if ( is_numeric( $val ) ) { $u = wp_get_attachment_image_url( (int) $val, $size ); return $u ? $u : ''; }
		return $val ? esc_url( (string) $val ) : '';
	}

	/** Entry point — dispatch to the selected style. */
	public function render_orgstats() {
		$style  = isset( $this->settings->orgstats_style ) ? preg_replace( '/[^a-z0-9_]/', '', $this->settings->orgstats_style ) : 'style1';
		$method = 'render_' . $style;
		if ( ! array_key_exists( $style, self::styles() ) || ! method_exists( $this, $method ) ) {
			$method = 'render_style1';
		}
		$this->$method();
	}

	/** Style 1 — Record band. */
	public function render_style1() {
		$s = $this->settings;

		$mods = 'ds-orgstats ds-orgstats--style1';
		if ( ( $s->count_up ?? 'yes' ) === 'yes' ) { $mods .= ' ds-orgstats--count'; }
		if ( ( $s->stat_layout ?? 'plain' ) === 'cards' ) { $mods .= ' ds-orgstats--cards'; }

		$bg = ! empty( $s->bg_image ) ? $this->photo_url( $s->bg_image ) : '';

		echo '<section class="' . esc_attr( $mods ) . '">';
		if ( $bg ) {
			echo '<div class="ds-orgstats-bg" aria-hidden="true" style="background-image:url(' . esc_url( $bg ) . ')"></div>';
		}
		echo '<div class="ds-orgstats-overlay" aria-hidden="true"></div>';
		echo '<div class="ds-orgstats-wrap">';

		// Header (optional).
		if ( ( $s->show_header ?? 'yes' ) === 'yes' && ( ! empty( $s->eyebrow ) || ! empty( $s->heading ) ) ) {
			echo '<div class="ds-orgstats-head">';
			if ( ! empty( $s->eyebrow ) ) {
				$erule = in_array( $s->eyebrow_rule ?? 'before', array( 'none', 'before', 'both' ), true ) ? ( $s->eyebrow_rule ?? 'before' ) : 'before';
				echo '<span class="ds-orgstats-eyebrow ds-orgstats-eyebrow--rule-' . esc_attr( $erule ) . '">' . esc_html( $s->eyebrow ) . '</span>';
			}
			if ( ! empty( $s->heading ) ) {
				echo '<h2 class="ds-orgstats-heading">' . $this->heading_html( $s->heading ) . '</h2>';
			}
			echo '</div>';
		}

		$stats = isset( $s->stats ) && is_array( $s->stats ) ? $s->stats : array();
		if ( empty( $stats ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:14px;opacity:.7;text-align:center">' . esc_html__( 'Add stats in the module settings.', 'ds-toolkit' ) . '</p>';
			}
			echo '</div></section>';
			return;
		}

		$cards  = ( ( $s->stat_layout ?? 'plain' ) === 'cards' );
		$calign = in_array( $s->card_align ?? 'bottom-left', array( 'bottom-left', 'bottom-center', 'center' ), true ) ? ( $s->card_align ?? 'bottom-left' ) : 'bottom-left';

		echo '<div class="ds-orgstats-grid">';
		foreach ( $stats as $stat ) {
			$stat   = (object) $stat;
			$number = trim( (string) ( $stat->number ?? '' ) );
			$prefix = trim( (string) ( $stat->prefix ?? '' ) );
			$suffix = trim( (string) ( $stat->suffix ?? '' ) );
			$label  = trim( (string) ( $stat->label ?? '' ) );
			$img    = $cards ? $this->photo_url( $stat->image ?? '' ) : '';

			// A purely-numeric value (optionally with thousands separators) can count up.
			$is_num   = ( '' !== $number ) && preg_match( '/^[0-9][0-9,]*$/', $number );
			$data_cnt = $is_num ? ' data-count="' . esc_attr( (int) str_replace( ',', '', $number ) ) . '"' : '';

			$item_cls = 'ds-orgstats-item';
			if ( $cards ) {
				$item_cls .= ' ds-orgstats-item--card ds-orgstats-item--' . $calign;
				if ( $img ) { $item_cls .= ' ds-orgstats-item--has-img'; }
			}

			echo '<div class="' . esc_attr( $item_cls ) . '">';
			if ( $cards && $img ) {
				echo '<div class="ds-orgstats-card-bg" aria-hidden="true" style="background-image:url(' . esc_url( $img ) . ')"></div>';
				echo '<div class="ds-orgstats-card-overlay" aria-hidden="true"></div>';
			}
			if ( $cards ) { echo '<div class="ds-orgstats-item-inner">'; }
			echo '<div class="ds-orgstats-num"' . $data_cnt . '>';
			if ( '' !== $prefix ) { echo '<span class="ds-orgstats-affix ds-orgstats-prefix">' . esc_html( $prefix ) . '</span>'; }
			echo '<span class="ds-orgstats-value">' . esc_html( $number ) . '</span>';
			if ( '' !== $suffix ) { echo '<span class="ds-orgstats-affix ds-orgstats-suffix">' . esc_html( $suffix ) . '</span>'; }
			echo '</div>';
			if ( '' !== $label ) { echo '<div class="ds-orgstats-label">' . esc_html( $label ) . '</div>'; }
			if ( $cards ) { echo '</div>'; }
			echo '</div>';
		}
		echo '</div></div></section>';
	}
}

/* --------------------------------------------------------------- Stat sub-form */
FLBuilder::register_settings_form( 'ds_orgstats_stat_form', array(
	'title' => __( 'Stat', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Stat', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'prefix' => array(
							'type'        => 'text',
							'label'       => __( 'Prefix', 'ds-toolkit' ),
							'connections' => array( 'string' ),
							'help'        => __( 'Optional. Shown before the number in the accent colour (e.g. $, #).', 'ds-toolkit' ),
						),
						'number' => array(
							'type'        => 'text',
							'label'       => __( 'Number', 'ds-toolkit' ),
							'default'     => '0',
							'connections' => array( 'string' ),
							'help'        => __( 'A plain whole number (e.g. 75) counts up on scroll. Non-numeric text (e.g. "SoCal NPL") is shown as-is.', 'ds-toolkit' ),
						),
						'suffix' => array(
							'type'        => 'text',
							'label'       => __( 'Suffix', 'ds-toolkit' ),
							'connections' => array( 'string' ),
							'help'        => __( 'Optional. Shown after the number in the accent colour (e.g. +, ×, K, %).', 'ds-toolkit' ),
						),
						'label'  => array(
							'type'        => 'text',
							'label'       => __( 'Label', 'ds-toolkit' ),
							'connections' => array( 'string' ),
						),
						'image'  => array(
							'type'        => 'photo',
							'label'       => __( 'Card Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
							'help'        => __( 'Used when Stat Layout is "Image Cards". Sits behind the figure with a dark overlay.', 'ds-toolkit' ),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_OrgStats_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'orgstats_style_sec' => array(
				'title'  => __( 'Stats Style', 'ds-toolkit' ),
				'fields' => array(
					'orgstats_style' => array(
						'type'    => 'select',
						'label'   => __( 'Stats Style', 'ds-toolkit' ),
						'default' => 'style1',
						'options' => DS_OrgStats_Module::styles(),
						'help'    => __( 'Choose a stats layout. More styles will be added; the options below adapt to the selected style.', 'ds-toolkit' ),
						'toggle'  => array(
							'style1' => array(
								'sections' => array( 'header', 'stats', 'cards', 'grid', 'background', 'overlay', 'dividers', 'motion', 'colors', 'typography', 'spacing' ),
							),
						),
					),
				),
			),
			'header' => array(
				'title'  => __( 'Header', 'ds-toolkit' ),
				'fields' => array(
					'show_header' => array(
						'type'    => 'select',
						'label'   => __( 'Show Header', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'eyebrow', 'heading' ) ) ),
					),
					'eyebrow' => array(
						'type'        => 'text',
						'label'       => __( 'Eyebrow', 'ds-toolkit' ),
						'default'     => 'Lorem Ipsum',
						'connections' => array( 'string' ),
						'help'        => __( 'The small accent label above the heading (with a leading rule).', 'ds-toolkit' ),
					),
					'eyebrow_rule' => array(
						'type'    => 'select',
						'label'   => __( 'Eyebrow Rule', 'ds-toolkit' ),
						'default' => 'before',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'before' => __( 'Leading line', 'ds-toolkit' ), 'both' => __( 'Both sides', 'ds-toolkit' ) ),
						'help'    => __( 'The small line(s) around the eyebrow text.', 'ds-toolkit' ),
						'toggle'  => array(
							'before' => array( 'fields' => array( 'eyebrow_rule_length', 'eyebrow_rule_thickness' ) ),
							'both'   => array( 'fields' => array( 'eyebrow_rule_length', 'eyebrow_rule_thickness' ) ),
						),
					),
					'eyebrow_rule_length' => array( 'type' => 'unit', 'label' => __( 'Rule Length', 'ds-toolkit' ), 'default' => '34', 'description' => 'px', 'slider' => array( 'min' => 8, 'max' => 160, 'step' => 1 ) ),
					'eyebrow_rule_thickness' => array( 'type' => 'unit', 'label' => __( 'Rule Thickness', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 10, 'step' => 1 ) ),
					'heading' => array(
						'type'        => 'textarea',
						'label'       => __( 'Heading', 'ds-toolkit' ),
						'rows'        => 2,
						'default'     => 'Lorem {a}Ipsum{/a}',
						'connections' => array( 'string' ),
						'help'        => __( 'Line breaks kept. Wrap a word in {a}…{/a} to colour it with the accent.', 'ds-toolkit' ),
					),
				),
			),
			'stats' => array(
				'title'       => __( 'Stats', 'ds-toolkit' ),
				'description' => __( 'The figures shown in the grid. Plain whole numbers count up on scroll.', 'ds-toolkit' ),
				'fields'      => array(
					'stats' => array(
						'type'         => 'form',
						'label'        => __( 'Stat', 'ds-toolkit' ),
						'form'         => 'ds_orgstats_stat_form',
						'preview_text' => 'label',
						'multiple'     => true,
						'default'      => array(
							array( 'number' => '3',  'label' => 'Lorem Ipsum' ),
							array( 'number' => '49', 'label' => 'Dolor Sit Amet' ),
							array( 'number' => '75', 'suffix' => '+', 'label' => 'Consectetur' ),
							array( 'number' => '8',  'label' => 'Adipiscing Elit' ),
						),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'cards' => array(
				'title'  => __( 'Stat Layout', 'ds-toolkit' ),
				'fields' => array(
					'stat_layout' => array(
						'type'    => 'select',
						'label'   => __( 'Stat Layout', 'ds-toolkit' ),
						'default' => 'plain',
						'options' => array( 'plain' => __( 'Plain figures (grid lines)', 'ds-toolkit' ), 'cards' => __( 'Image Cards', 'ds-toolkit' ) ),
						'help'    => __( 'Image Cards renders each stat as a photo card with a dark overlay (set a Card Image per stat). Plain figures keeps the bordered grid.', 'ds-toolkit' ),
						'toggle'  => array( 'cards' => array( 'fields' => array( 'card_min_height', 'card_radius', 'card_gap', 'card_pad', 'card_align', 'card_ov_color', 'card_ov_top', 'card_ov_bottom' ) ) ),
					),
					'card_min_height' => array( 'type' => 'unit', 'label' => __( 'Card Height', 'ds-toolkit' ), 'default' => '360', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 160, 'max' => 640, 'step' => 10 ) ),
					'card_radius'     => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'card_gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card_pad'        => array( 'type' => 'unit', 'label' => __( 'Inner Padding', 'ds-toolkit' ), 'default' => '32', 'description' => 'px', 'slider' => array( 'min' => 8, 'max' => 80, 'step' => 1 ) ),
					'card_align'      => array( 'type' => 'select', 'label' => __( 'Content Position', 'ds-toolkit' ), 'default' => 'bottom-left', 'options' => array( 'bottom-left' => __( 'Bottom Left', 'ds-toolkit' ), 'bottom-center' => __( 'Bottom Center', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ) ) ),
					'card_ov_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'card_ov_top'     => array( 'type' => 'unit', 'label' => __( 'Overlay Top Opacity', 'ds-toolkit' ), 'default' => '20', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'card_ov_bottom'  => array( 'type' => 'unit', 'label' => __( 'Overlay Bottom Opacity', 'ds-toolkit' ), 'default' => '78', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
				),
			),
			'grid' => array(
				'title'  => __( 'Grid', 'ds-toolkit' ),
				'fields' => array(
					'columns'    => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Defaults: 2 on tablet, 2 on mobile if left blank.', 'ds-toolkit' ) ),
					'item_pad_v' => array( 'type' => 'unit', 'label' => __( 'Item Padding (vertical)', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 10, 'max' => 100, 'step' => 1 ) ),
					'item_pad_h' => array( 'type' => 'unit', 'label' => __( 'Item Padding (horizontal)', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'content_width' => array(
						'type'    => 'select',
						'label'   => __( 'Content Width', 'ds-toolkit' ),
						'default' => 'boxed',
						'options' => array(
							'boxed'  => __( 'Boxed', 'ds-toolkit' ),
							'full'   => __( 'Full width (fill container)', 'ds-toolkit' ),
							'custom' => __( 'Custom max-width', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'boxed'  => array( 'fields' => array( 'max_width' ) ),
							'custom' => array( 'fields' => array( 'max_width' ) ),
						),
						'help'    => __( 'Full width lets the stats fill a full-width row/column.', 'ds-toolkit' ),
					),
					'max_width'  => array( 'type' => 'unit', 'label' => __( 'Content Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 600, 'max' => 1920, 'step' => 10 ) ),
				),
			),
			'background' => array(
				'title'  => __( 'Background', 'ds-toolkit' ),
				'fields' => array(
					'bg_image'   => array( 'type' => 'photo', 'label' => __( 'Background Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Optional. Sits faded behind the overlay.', 'ds-toolkit' ) ),
					'bg_opacity' => array( 'type' => 'unit', 'label' => __( 'Image Opacity', 'ds-toolkit' ), 'default' => '16', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'bg_size'       => array( 'type' => 'select', 'label' => __( 'Image Size', 'ds-toolkit' ), 'default' => 'cover', 'options' => array( 'cover' => __( 'Cover', 'ds-toolkit' ), 'contain' => __( 'Contain', 'ds-toolkit' ), 'auto' => __( 'Auto / actual', 'ds-toolkit' ) ) ),
					'bg_position'   => array( 'type' => 'select', 'label' => __( 'Image Position', 'ds-toolkit' ), 'default' => 'center center', 'options' => array( 'center center' => __( 'Center', 'ds-toolkit' ), 'center top' => __( 'Top', 'ds-toolkit' ), 'center bottom' => __( 'Bottom', 'ds-toolkit' ), 'left center' => __( 'Left', 'ds-toolkit' ), 'right center' => __( 'Right', 'ds-toolkit' ) ) ),
					'bg_repeat'     => array( 'type' => 'select', 'label' => __( 'Image Repeat', 'ds-toolkit' ), 'default' => 'no-repeat', 'options' => array( 'no-repeat' => __( 'No Repeat', 'ds-toolkit' ), 'repeat' => __( 'Tile', 'ds-toolkit' ), 'repeat-x' => __( 'Tile Horizontally', 'ds-toolkit' ), 'repeat-y' => __( 'Tile Vertically', 'ds-toolkit' ) ) ),
					'bg_attachment' => array( 'type' => 'select', 'label' => __( 'Image Attachment', 'ds-toolkit' ), 'default' => 'scroll', 'options' => array( 'scroll' => __( 'Scroll', 'ds-toolkit' ), 'fixed' => __( 'Fixed (parallax)', 'ds-toolkit' ) ), 'help' => __( 'Fixed keeps the image still as the page scrolls.', 'ds-toolkit' ) ),
					'bg_blur'       => array( 'type' => 'unit', 'label' => __( 'Image Blur', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
				),
			),
			'overlay' => array(
				'title'  => __( 'Overlay', 'ds-toolkit' ),
				'fields' => array(
					'overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'overlay_top'     => array( 'type' => 'unit', 'label' => __( 'Top Opacity', 'ds-toolkit' ), 'default' => '86', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_bottom'  => array( 'type' => 'unit', 'label' => __( 'Bottom Opacity', 'ds-toolkit' ), 'default' => '94', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
				),
			),
			'dividers' => array(
				'title'  => __( 'Dividers / Border', 'ds-toolkit' ),
				'fields' => array(
					'divider_style'   => array(
						'type'    => 'select',
						'label'   => __( 'Divider Style', 'ds-toolkit' ),
						'default' => 'solid',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'solid' => __( 'Solid', 'ds-toolkit' ), 'dashed' => __( 'Dashed', 'ds-toolkit' ), 'dotted' => __( 'Dotted', 'ds-toolkit' ) ),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'divider_width', 'divider_color', 'divider_opacity' ) ),
							'dashed' => array( 'fields' => array( 'divider_width', 'divider_color', 'divider_opacity' ) ),
							'dotted' => array( 'fields' => array( 'divider_width', 'divider_color', 'divider_opacity' ) ),
						),
						'help'    => __( 'Clean grid lines drawn around and between the figures.', 'ds-toolkit' ),
					),
					'divider_width'   => array( 'type' => 'unit', 'label' => __( 'Line Width', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'divider_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Line Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'divider_opacity' => array( 'type' => 'unit', 'label' => __( 'Line Opacity', 'ds-toolkit' ), 'default' => '22', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
				),
			),
			'motion' => array(
				'title'  => __( 'Motion', 'ds-toolkit' ),
				'fields' => array(
					'count_up'    => array(
						'type'    => 'select',
						'label'   => __( 'Count Up Numbers', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'count_duration' ) ) ),
						'help'    => __( 'Animate whole numbers from 0 when scrolled into view. Always off for reduced-motion visitors.', 'ds-toolkit' ),
					),
					'count_duration' => array(
						'type'        => 'unit',
						'label'       => __( 'Count Duration', 'ds-toolkit' ),
						'default'     => '1600',
						'description' => 'ms',
						'slider'      => array( 'min' => 400, 'max' => 4000, 'step' => 100 ),
					),
				),
			),
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'section_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Section Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'eyebrow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank falls back to the global Accent colour. The leading rule tracks this colour.', 'ds-toolkit' ) ),
					'heading_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'accent_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent (heading word, prefix, suffix)', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank falls back to the global Accent colour.', 'ds-toolkit' ) ),
					'number_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Number', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'label_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Label', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-orgstats-eyebrow' ) ),
					'heading_typography' => array( 'type' => 'typography', 'label' => __( 'Heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-orgstats-heading' ) ),
					'number_typography'  => array( 'type' => 'typography', 'label' => __( 'Number', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-orgstats-num' ) ),
					'label_typography'   => array( 'type' => 'typography', 'label' => __( 'Label', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-orgstats-label' ) ),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'padding' => array(
						'type'       => 'dimension',
						'label'      => __( 'Padding', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Inner spacing around the band content. 0 = compact / flush.', 'ds-toolkit' ),
					),
					'margin'  => array(
						'type'       => 'dimension',
						'label'      => __( 'Margin', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Outer spacing around the whole section. 0 = compact / flush.', 'ds-toolkit' ),
					),
				),
			),
		),
	),
) );
