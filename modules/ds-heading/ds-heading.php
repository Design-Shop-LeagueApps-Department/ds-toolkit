<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Heading — an in-house Beaver Builder section-heading module.
 *
 * A clean, SEO-aware title block: an optional SUB-HEADING (always a <span>, never
 * a heading tag), a HEADING whose tag is selectable (h1–h6 / p / div, default h2),
 * an optional eyebrow / divider RULE (several styles + positions), and an optional
 * description paragraph. Per-element typography, colours, alignment and spacing.
 *
 * No UABB dependency; fully SSH-editable (markup here, styles in css/ + includes/).
 *
 * @class DS_Heading_Module
 */
class DS_Heading_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Heading', 'ds-toolkit' ),
			'description'     => __( 'SEO-friendly section heading: sub-heading, selectable heading tag, divider rule + description.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-heading/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-heading/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** Allowed heading tags (SEO). Keys are the rendered tag. */
	public static function tags() {
		return array(
			'h1'   => __( 'H1', 'ds-toolkit' ),
			'h2'   => __( 'H2 (default)', 'ds-toolkit' ),
			'h3'   => __( 'H3', 'ds-toolkit' ),
			'h4'   => __( 'H4', 'ds-toolkit' ),
			'h5'   => __( 'H5', 'ds-toolkit' ),
			'h6'   => __( 'H6', 'ds-toolkit' ),
			'p'    => __( 'Paragraph (no heading)', 'ds-toolkit' ),
			'div'  => __( 'Div (no heading)', 'ds-toolkit' ),
			'span' => __( 'Span (no heading)', 'ds-toolkit' ),
		);
	}

	/** Heading markup: escape, {a}..{/a} -> accent span, newlines -> <br>. */
	private function heading_html( $raw ) {
		$h = DS_Module_UI::inline( (string) $raw ); // safe inline HTML (span/strong/em...) allowed
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-heading-accent">', '</span>' ), $h );
		$h = str_replace( array( '{outline}', '{/outline}' ), array( '<span class="ds-outline-text">', '</span>' ), $h );
		return nl2br( $h );
	}

	/** The eyebrow/divider rule element. */
	private function rule_html() {
		return '<span class="ds-heading-rule" aria-hidden="true"><span class="ds-heading-rule-line"></span></span>';
	}

	/** Sub-heading element (always a <span>, optionally carrying an inline rule). */
	private function subheading_html( $inline_rule ) {
		$txt = '<span class="ds-heading-sub-text">' . DS_Module_UI::inline( $this->settings->subheading ) . '</span>';
		if ( $inline_rule ) {
			return '<span class="ds-heading-sub ds-heading-sub--withrule">' . $this->rule_html() . $txt . '</span>';
		}
		return '<span class="ds-heading-sub">' . $txt . '</span>';
	}

	/** Entry point called by includes/frontend.php. */
	public function render_heading() {
		$s = $this->settings;

		$align = in_array( $s->alignment ?? 'left', array( 'left', 'center', 'right' ), true ) ? ( $s->alignment ?? 'left' ) : 'left';

		$tag = $s->heading_tag ?? 'h2';
		if ( ! array_key_exists( $tag, self::tags() ) ) { $tag = 'h2'; }

		$sub_show  = ( $s->subheading_show ?? 'yes' ) === 'yes' && '' !== trim( (string) ( $s->subheading ?? '' ) );
		$sub_pos   = in_array( $s->subheading_position ?? 'above', array( 'above', 'below' ), true ) ? ( $s->subheading_position ?? 'above' ) : 'above';
		$desc_show = ( $s->description_show ?? 'no' ) === 'yes' && '' !== trim( (string) ( $s->description ?? '' ) );
		$div_show  = ( $s->divider_show ?? 'yes' ) === 'yes';
		$div_pos   = in_array( $s->divider_position ?? 'sub', array( 'sub', 'above', 'below', 'predesc', 'bottom' ), true ) ? ( $s->divider_position ?? 'sub' ) : 'sub';
		$div_style = preg_replace( '/[^a-z]/', '', (string) ( $s->divider_style ?? 'solid' ) );

		// If the rule is meant to ride the sub-heading but there's no sub-heading,
		// fall back to a standalone rule above the heading.
		$inline_rule = $div_show && 'sub' === $div_pos && $sub_show;
		if ( $div_show && 'sub' === $div_pos && ! $sub_show ) { $div_pos = 'above'; }

		$mods  = 'ds-heading ds-heading--' . $align;
		$mods .= ' ds-heading--rule-' . ( $div_style ?: 'solid' );
		if ( '' !== trim( (string) ( $s->heading ?? '' ) ) || $sub_show || $desc_show ) {
			// renderable
		} elseif ( FLBuilderModel::is_builder_active() ) {
			echo '<div class="' . esc_attr( $mods ) . '"><p style="padding:14px;opacity:.7">' . esc_html__( 'Add a heading in the module settings.', 'ds-toolkit' ) . '</p></div>';
			return;
		} else {
			return;
		}

		echo '<div class="' . esc_attr( $mods ) . '"><div class="ds-heading-inner">';

		// Sub-heading (above).
		if ( $sub_show && 'above' === $sub_pos ) { echo $this->subheading_html( $inline_rule ); }

		// Standalone rule above the heading.
		if ( $div_show && 'above' === $div_pos ) { echo $this->rule_html(); }

		// Heading.
		if ( '' !== trim( (string) ( $s->heading ?? '' ) ) ) {
			echo '<' . $tag . ' class="ds-heading-title">' . $this->heading_html( $s->heading ) . '</' . $tag . '>';
		}

		// Standalone rule below the heading.
		if ( $div_show && 'below' === $div_pos ) { echo $this->rule_html(); }

		// Sub-heading (below the heading).
		if ( $sub_show && 'below' === $sub_pos ) { echo $this->subheading_html( $inline_rule ); }

		// Standalone rule between the heading and the description (only with a description).
		if ( $div_show && 'predesc' === $div_pos && $desc_show ) { echo $this->rule_html(); }

		// Description.
		if ( $desc_show ) {
			echo '<div class="ds-heading-desc">' . wpautop( wp_kses_post( $s->description ) ) . '</div>';
		}

		// Standalone rule at the very bottom.
		if ( $div_show && 'bottom' === $div_pos ) { echo $this->rule_html(); }

		echo '</div></div>';
	}
}

FLBuilder::register_module( 'DS_Heading_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'text' => array(
				'title'  => __( 'Text', 'ds-toolkit' ),
				'fields' => array(
					'subheading_show'     => array(
						'type'    => 'select',
						'label'   => __( 'Show Sub-heading', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'subheading', 'subheading_position' ) ) ),
						'help'    => __( 'The sub-heading is always output as a <span> (never a heading tag), so it stays SEO-clean.', 'ds-toolkit' ),
					),
					'subheading'          => array( 'type' => 'text', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'default' => 'Sub Heading', 'connections' => array( 'string' ) ),
					'subheading_position' => array(
						'type'    => 'select',
						'label'   => __( 'Sub-heading Position', 'ds-toolkit' ),
						'default' => 'above',
						'options' => array( 'above' => __( 'Above heading', 'ds-toolkit' ), 'below' => __( 'Below heading', 'ds-toolkit' ) ),
					),
					'heading'             => array(
						'type'        => 'textarea',
						'label'       => __( 'Heading', 'ds-toolkit' ),
						'rows'        => 2,
						'default'     => 'Section {a}Heading{/a}',
						'connections' => array( 'string' ),
						'help'        => __( 'Wrap a word in {a}…{/a} to colour it with the accent. Line breaks are kept. Use the connect (+) icon to pull a dynamic field (post title, ACF, etc.). {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'heading_tag'         => array(
						'type'    => 'select',
						'label'   => __( 'Heading Tag (SEO)', 'ds-toolkit' ),
						'default' => 'h2',
						'options' => DS_Heading_Module::tags(),
						'help'    => __( 'Pick the semantic tag. Use one H1 per page; H2 is the safe default for a section title.', 'ds-toolkit' ),
					),
					'alignment'           => array(
						'type'       => 'select',
						'label'      => __( 'Alignment', 'ds-toolkit' ),
						'default'    => 'left',
						'responsive' => true,
						'options'    => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ),
					),
				),
			),
			'desc' => array(
				'title'  => __( 'Description', 'ds-toolkit' ),
				'fields' => array(
					'description_show' => array(
						'type'    => 'select',
						'label'   => __( 'Show Description', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'description' ) ) ),
					),
					'description'      => array( 'type' => 'editor', 'media_buttons' => false, 'wpautop' => false, 'label' => __( 'Description', 'ds-toolkit' ), 'rows' => 4, 'connections' => array( 'string' ), 'help' => __( 'Paragraph under the heading. Line breaks kept; basic inline HTML (e.g. <strong>, <a>) allowed. Use the connect (+) icon for a dynamic field.', 'ds-toolkit' ) ),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'divider' => array(
				'title'  => __( 'Divider / Eyebrow Rule', 'ds-toolkit' ),
				'fields' => array(
					'divider_show'      => array(
						'type'    => 'select',
						'label'   => __( 'Show Divider', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'divider_position', 'divider_style', 'divider_width', 'divider_width_unit', 'divider_thickness', 'divider_radius', 'divider_gap', 'divider_color' ) ) ),
					),
					'divider_position'  => array(
						'type'    => 'select',
						'label'   => __( 'Position', 'ds-toolkit' ),
						'default' => 'sub',
						'options' => array(
							'sub'    => __( 'Eyebrow rule (beside sub-heading)', 'ds-toolkit' ),
							'above'  => __( 'Above heading', 'ds-toolkit' ),
							'below'   => __( 'Below heading', 'ds-toolkit' ),
							'predesc' => __( 'Between heading & description (needs Description on)', 'ds-toolkit' ),
							'bottom' => __( 'Below everything', 'ds-toolkit' ),
						),
						'help'    => __( 'Eyebrow rule sits inline next to the sub-heading (falls back to “Above heading” if the sub-heading is hidden).', 'ds-toolkit' ),
					),
					'divider_style'     => array(
						'type'    => 'select',
						'label'   => __( 'Style', 'ds-toolkit' ),
						'default' => 'solid',
						'options' => array(
							'solid'    => __( 'Solid', 'ds-toolkit' ),
							'dashed'   => __( 'Dashed', 'ds-toolkit' ),
							'dotted'   => __( 'Dotted', 'ds-toolkit' ),
							'double'   => __( 'Double', 'ds-toolkit' ),
							'gradient' => __( 'Gradient fade', 'ds-toolkit' ),
						),
					),
					'divider_width'     => array( 'type' => 'unit', 'label' => __( 'Length', 'ds-toolkit' ), 'default' => '48', 'slider' => array( 'min' => 8, 'max' => 600, 'step' => 1 ) ),
					'divider_width_unit'=> array( 'type' => 'select', 'label' => __( 'Length Unit', 'ds-toolkit' ), 'default' => 'px', 'options' => array( 'px' => 'px', '%' => '%' ), 'help' => __( '% is relative to the heading block width.', 'ds-toolkit' ) ),
					'divider_thickness' => array( 'type' => 'unit', 'label' => __( 'Thickness', 'ds-toolkit' ), 'default' => '3', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 16, 'step' => 1 ) ),
					'divider_radius'    => array( 'type' => 'unit', 'label' => __( 'Rounded Ends', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 12, 'step' => 1 ) ),
					'divider_gap'       => array( 'type' => 'unit', 'label' => __( 'Spacing', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ), 'help' => __( 'Gap between the rule and the adjacent text.', 'ds-toolkit' ) ),
					'divider_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
				),
			),
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'subheading_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'heading_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'heading_accent_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading Accent {a}…{/a}', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'outline_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Outline Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Stroke colour for {outline}…{/outline} text in this module. Blank = the Theme Setting default.', 'ds-toolkit' ) ),
					'outline_width' => array( 'type' => 'unit', 'label' => __( 'Outline Text Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = the Theme Setting default.', 'ds-toolkit' ), 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'description_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'subheading_typography'  => array( 'type' => 'typography', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-heading-sub' ) ),
					'heading_typography'     => array( 'type' => 'typography', 'label' => __( 'Heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-heading-title' ) ),
					'description_typography' => array( 'type' => 'typography', 'label' => __( 'Description', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-heading-desc' ) ),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'gap'       => array( 'type' => 'unit', 'label' => __( 'Element Gap', 'ds-toolkit' ), 'default' => '12', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ), 'help' => __( 'Vertical spacing between sub-heading, heading and description.', 'ds-toolkit' ) ),
					'max_width' => array( 'type' => 'unit', 'label' => __( 'Max Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 200, 'max' => 1200, 'step' => 10 ), 'help' => __( 'Constrain the block width (great for centred headings). Blank = full width.', 'ds-toolkit' ) ),
					'padding'   => array( 'type' => 'dimension', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '0', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
					'margin'    => array( 'type' => 'dimension', 'label' => __( 'Margin', 'ds-toolkit' ), 'default' => '0', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
				),
			),
		),
	),
) );
