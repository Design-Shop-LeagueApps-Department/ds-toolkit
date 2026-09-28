<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Drop Area: a builder-only guide that marks where editors drag their own modules
 * ("Drag your schedule here"). Visitors never see it, and an area that holds nothing but its
 * heading and this guide is hidden on the live page (see the Content Router's area CSS).
 *
 * It is a module rather than an empty column because Beaver Builder deletes a column the moment
 * its last module goes, which would take the area with it.
 *
 * Seed Field: when a post is first opened in Beaver Builder from a starter template (Content
 * Router, "Each post can be built in Beaver Builder"), the post's current value of this field is
 * copied in as a module just above the guide, so switching a team to the builder loses nothing.
 * `team_roster` / `schedule` (ACF wysiwyg) become a Text Editor module; `_thumbnail` is the
 * featured image and is placed by the starter's own Photo module instead.
 *
 * @class DS_Drop_Area_Module
 */
class DS_Drop_Area_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Drop Area (guide)', 'ds-toolkit' ),
			'description'     => __( 'Marks where editors drag their own modules. Shows only in Beaver Builder.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-drop-area/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-drop-area/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** Builder only: a dashed guide box. Visitors get nothing. */
	public function render_guide() {
		if ( ! class_exists( 'FLBuilderModel' ) || ! FLBuilderModel::is_builder_active() ) { return; }
		$text = trim( (string) ( $this->settings->guide_text ?? '' ) );
		if ( '' === $text ) { $text = __( 'Drag modules here', 'ds-toolkit' ); }
		$hint = trim( (string) ( $this->settings->guide_hint ?? '' ) );
		echo '<div class="ds-drop-area" role="note">'
			. '<span class="ds-drop-area-icon" aria-hidden="true">+</span>'
			. '<span class="ds-drop-area-text">' . esc_html( $text ) . '</span>'
			. ( '' !== $hint ? '<span class="ds-drop-area-hint">' . esc_html( $hint ) . '</span>' : '' )
			. '</div>';
	}
}

// Placed by whoever builds the starter template; editors work around it, they never add one.
DS_Module_UI::offer_only_in_templates( 'ds-drop-area' );

FLBuilder::register_module( 'DS_Drop_Area_Module', array(
	'general' => array(
		'title'    => __( 'Guide', 'ds-toolkit' ),
		'sections' => array(
			'guide' => array(
				'title'  => '',
				'fields' => array(
					'guide_text' => array( 'type' => 'text', 'label' => __( 'Guide Text', 'ds-toolkit' ), 'default' => 'Drag modules here', 'help' => __( 'Shown only in Beaver Builder, e.g. "Drag your schedule here".', 'ds-toolkit' ) ),
					'guide_hint' => array( 'type' => 'text', 'label' => __( 'Hint', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Optional smaller line, e.g. "A Ninja Tables module works best".', 'ds-toolkit' ) ),
					'seed_field' => array( 'type' => 'text', 'label' => __( 'Seed Field', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Optional. A field of the post (e.g. schedule) copied in above this guide the first time the post is opened in Beaver Builder from a starter template.', 'ds-toolkit' ) ),
				),
			),
		),
	),
) );
