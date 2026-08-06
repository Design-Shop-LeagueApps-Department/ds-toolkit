<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Pathway — an in-house Beaver Builder module (GH #96).
 *
 * A row of stage cards (eyebrow, title, description) connected by a progress
 * line that runs through one marker per stage. Markers and segments are
 * generated per card, so adding/removing stages automatically re-fits the
 * line; it ends at the final stage with a gradient fade tail and never
 * continues past the last card. Below the configurable stack breakpoint the
 * row becomes a vertical timeline (markers down a left rail) so the line and
 * cards stay aligned on small screens.
 *
 * The track is pure CSS: every stage owns its own segment (marker edge to the
 * next marker's edge, spanning the grid gap), the last stage owns the fade
 * tail. No JS.
 *
 * @class DS_Pathway_Module
 */
class DS_Pathway_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Pathway', 'ds-toolkit' ),
			'description'     => __( 'Stage cards connected by a progress line with per-stage markers and a fading end.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-pathway/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-pathway/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** Normalise a BB link field (string URL or {url,target} object/array). */
	private function link_parts( $link ) {
		$url = ''; $target = '_self';
		if ( is_array( $link ) )      { $url = $link['url'] ?? ''; $target = $link['target'] ?? '_self'; }
		elseif ( is_object( $link ) ) { $url = $link->url ?? '';  $target = $link->target ?? '_self'; }
		else { $url = (string) $link; }
		return array( esc_url( $url ), '_blank' === $target ? '_blank' : '_self' );
	}

	/**
	 * Normalised stages. Blank repeater rows are skipped so markers, segments,
	 * and the fade tail always agree with what actually renders.
	 */
	private function stages() {
		$out = array();
		$raw = $this->settings->stages ?? array();
		if ( ! is_array( $raw ) ) { return $out; }
		foreach ( $raw as $stage ) {
			$stage   = (object) $stage;
			$title   = trim( (string) ( $stage->title ?? '' ) );
			$text    = trim( (string) ( $stage->text ?? '' ) );
			$eyebrow = trim( (string) ( $stage->eyebrow ?? '' ) );
			list( $url, $target ) = $this->link_parts( $stage->link ?? '' );
			if ( '' === $title && '' === $text && '' === $eyebrow ) { continue; }
			$out[] = array(
				'eyebrow' => $eyebrow,
				'title'   => $title,
				'text'    => $text,
				'url'     => $url,
				'target'  => $target,
				'marker'  => ( $stage->marker ?? 'fill' ) === 'outline' ? 'outline' : 'fill',
			);
		}
		return $out;
	}

	public function render_pathway() {
		$s      = $this->settings;
		$stages = $this->stages();

		if ( empty( $stages ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:14px;opacity:.7">' . esc_html__( 'Add stages in the module settings.', 'ds-toolkit' ) . '</p>';
			}
			return;
		}

		$shape = ( $s->marker_shape ?? 'diamond' ) === 'circle' ? 'circle' : 'diamond';
		$mods  = 'ds-pathway ds-pathway--m-' . $shape;
		if ( ( $s->dividers ?? 'no' ) === 'yes' ) { $mods .= ' ds-pathway--dividers'; }

		echo '<section class="' . esc_attr( $mods ) . '"><div class="ds-pathway-wrap">';
		echo '<div class="ds-pathway-grid" style="--ds-path-n:' . count( $stages ) . '">';

		$n = 1;
		$last = count( $stages );
		foreach ( $stages as $i => $stage ) {
			$cls = 'ds-path-stage ds-path-stage--' . $stage['marker'];
			if ( $i + 1 === $last ) { $cls .= ' ds-path-stage--last'; }
			echo '<div class="' . esc_attr( $cls ) . '">';
			echo '<span class="ds-path-track" aria-hidden="true"><span class="ds-path-marker"></span></span>';

			$eyebrow = '' !== $stage['eyebrow'] ? $stage['eyebrow'] : sprintf( __( 'Stage %02d', 'ds-toolkit' ), $n );
			if ( ( $s->show_eyebrow ?? 'yes' ) === 'yes' ) {
				echo '<span class="ds-path-eyebrow">' . DS_Module_UI::inline( $eyebrow ) . '</span>';
			}
			if ( '' !== $stage['title'] ) {
				$title = DS_Module_UI::inline( $stage['title'] );
				if ( '' !== $stage['url'] ) {
					$rel = '_blank' === $stage['target'] ? ' rel="noopener noreferrer"' : '';
					$title = '<a href="' . esc_url( $stage['url'] ) . '" target="' . esc_attr( $stage['target'] ) . '"' . $rel . '>' . $title . '</a>';
				}
				echo '<h3 class="ds-path-title">' . $title . '</h3>';
			}
			if ( '' !== $stage['text'] ) {
				echo '<div class="ds-path-text">' . wpautop( wp_kses_post( $stage['text'] ) ) . '</div>';
			}
			echo '</div>';
			$n++;
		}

		echo '</div></div></section>';
	}
}

/* --------------------------------------------------------------- Stage sub-form */

FLBuilder::register_settings_form( 'ds_pathway_stage_form', array(
	'title' => __( 'Stage', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Stage', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'eyebrow' => array(
							'type'        => 'text',
							'label'       => __( 'Eyebrow', 'ds-toolkit' ),
							'default'     => '',
							'connections' => array( 'string' ),
							'help'        => __( 'Small label above the title. Blank = automatic "Stage 01", "Stage 02", … numbering.', 'ds-toolkit' ),
						),
						'title'   => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'default' => '', 'connections' => array( 'string' ) ),
						'text'    => array( 'type' => 'editor', 'media_buttons' => false, 'wpautop' => false, 'rows' => 3, 'label' => __( 'Description', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'link'    => array( 'type' => 'link', 'label' => __( 'Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ), 'help' => __( 'Optional. Makes the stage title a link.', 'ds-toolkit' ) ),
						'marker'  => array(
							'type'    => 'select',
							'label'   => __( 'Marker', 'ds-toolkit' ),
							'default' => 'fill',
							'options' => array( 'fill' => __( 'Filled', 'ds-toolkit' ), 'outline' => __( 'Outline (hollow)', 'ds-toolkit' ) ),
							'help'    => __( 'Outline reads as an upcoming / future stage, like the reference design.', 'ds-toolkit' ),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Pathway_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'stages' => array(
				'title'       => __( 'Stages', 'ds-toolkit' ),
				'description' => __( 'The pathway steps, left to right. Markers and the connecting line adjust automatically when stages are added, removed, or reordered.', 'ds-toolkit' ),
				'fields'      => array(
					'stages' => array(
						'type'         => 'form',
						'label'        => __( 'Stage', 'ds-toolkit' ),
						'form'         => 'ds_pathway_stage_form',
						'preview_text' => 'title',
						'multiple'     => true,
						'default'      => array(
							array( 'title' => 'Lorem Ipsum', 'text' => 'Dolor sit amet, consectetur adipiscing elit.' ),
							array( 'title' => 'Dolor Sit', 'text' => 'Sed do eiusmod tempor incididunt ut labore.' ),
							array( 'title' => 'Consectetur', 'text' => 'Ut enim ad minim veniam, quis nostrud.', 'marker' => 'outline' ),
						),
					),
					'show_eyebrow' => array(
						'type'    => 'select',
						'label'   => __( 'Eyebrows', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show (auto-numbered when blank)', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'track' => array(
				'title'  => __( 'Progress Line', 'ds-toolkit' ),
				'fields' => array(
					'marker_shape'   => array( 'type' => 'select', 'label' => __( 'Marker Shape', 'ds-toolkit' ), 'default' => 'diamond', 'options' => array( 'diamond' => __( 'Diamond', 'ds-toolkit' ), 'circle' => __( 'Circle', 'ds-toolkit' ) ) ),
					'marker_size'    => array( 'type' => 'unit', 'label' => __( 'Marker Size', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'slider' => array( 'min' => 8, 'max' => 28, 'step' => 1 ) ),
					'line_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Line Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'marker_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Marker Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the Line Colour.', 'ds-toolkit' ) ),
					'line_thickness' => array( 'type' => 'unit', 'label' => __( 'Line Thickness', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'track_gap'      => array( 'type' => 'unit', 'label' => __( 'Space Below Line', 'ds-toolkit' ), 'default' => '26', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
					'stack_at'       => array(
						'type'    => 'select',
						'label'   => __( 'Stack Below', 'ds-toolkit' ),
						'default' => 'medium',
						'options' => array(
							'large'  => __( 'Large breakpoint', 'ds-toolkit' ),
							'medium' => __( 'Medium breakpoint', 'ds-toolkit' ),
							'small'  => __( 'Small breakpoint', 'ds-toolkit' ),
							'never'  => __( 'Never (always one row)', 'ds-toolkit' ),
						),
						'help'    => __( 'Below this breakpoint the pathway becomes a vertical timeline: markers run down a left rail with the line between them, so everything stays aligned on small screens.', 'ds-toolkit' ),
					),
				),
			),
			'cards' => array(
				'title'  => __( 'Stages', 'ds-toolkit' ),
				'fields' => array(
					'gap'           => array( 'type' => 'unit', 'label' => __( 'Gap Between Stages', 'ds-toolkit' ), 'default' => '32', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
					'dividers'      => array( 'type' => 'select', 'label' => __( 'Stage Dividers', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'None', 'ds-toolkit' ), 'yes' => __( 'Thin vertical lines between stages', 'ds-toolkit' ) ) ),
					'divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider Colour', 'ds-toolkit' ), 'default' => 'rgba(255,255,255,0.12)', 'show_reset' => true ),
				),
			),
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'eyebrow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-path-eyebrow', 'property' => 'color' ) ),
					'title_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-path-title', 'property' => 'color' ) ),
					'text_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-path-text', 'property' => 'color' ) ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-path-eyebrow' ) ),
					'title_typography'   => array( 'type' => 'typography', 'label' => __( 'Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-path-title' ) ),
					'text_typography'    => array( 'type' => 'typography', 'label' => __( 'Description', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-path-text' ) ),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'content_width'     => array(
						'type'    => 'select',
						'label'   => __( 'Content Width', 'ds-toolkit' ),
						'default' => 'boxed',
						'options' => array(
							'boxed'  => __( 'Boxed (max 1280px)', 'ds-toolkit' ),
							'full'   => __( 'Full width (fill container)', 'ds-toolkit' ),
							'custom' => __( 'Custom max-width', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'content_max_width' ) ) ),
					),
					'content_max_width' => array( 'type' => 'unit', 'label' => __( 'Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 480, 'max' => 1920, 'step' => 10 ) ),
					'padding' => array( 'type' => 'dimension', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '0', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
					'margin'  => array( 'type' => 'dimension', 'label' => __( 'Margin', 'ds-toolkit' ), 'default' => '0', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
				),
			),
		),
	),
) );
