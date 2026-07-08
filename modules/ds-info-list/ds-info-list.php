<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Leagueapps Info List.
 *
 * One module that renders a list of "icon + text + link" rows, each pulling its
 * value from an ACF / meta field on the CURRENT post (staff, or any future single
 * page). Rows whose field is empty hide automatically, so a single Themer template
 * needs ONE module for all contact info instead of a stack of icon/text modules.
 *
 * Each row: an icon, a value (from an ACF field by name, or static / connectable
 * text), an optional display label, and an auto / tel / mailto / url link.
 *
 * @class DS_Info_List_Module
 */
class DS_Info_List_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Post Details', 'ds-toolkit' ),
			'description'     => __( 'Icon + text + link rows from the current post\'s ACF fields — for Themer single templates (staff, events). Empty rows hide automatically.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-info-list/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-info-list/',
			'partial_refresh' => true,
			'enabled'         => true,
		) );
		$this->add_css( 'font-awesome-5' );
	}

	/** Read a value for the current post from an ACF field (or raw meta), as a string. */
	private function field_value( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) { return ''; }
		$source = (string) ( $this->settings->field_source ?? 'post' );
		$val = '';
		if ( 'option' === $source ) {
			// Partner / site-wide ACF options page (e.g. partner_phone, partner_email).
			if ( function_exists( 'get_field' ) ) { $val = get_field( $key, 'option' ); }
		} else {
			$pid = get_the_ID();
			if ( ! $pid ) { return ''; }
			if ( function_exists( 'get_field' ) ) { $val = get_field( $key, $pid ); }
			if ( null === $val || false === $val || '' === $val ) { $val = get_post_meta( $pid, $key, true ); }
		}
		if ( is_array( $val ) ) { $val = $val['url'] ?? ( $val['title'] ?? ( is_scalar( reset( $val ) ) ? reset( $val ) : '' ) ); }
		return is_scalar( $val ) ? trim( (string) $val ) : '';
	}

	/** Options for the per-item ACF field picker: every registered ACF field, grouped. */
	public static function acf_field_options() {
		$out = array( '' => __( '— None (use Value below) —', 'ds-toolkit' ) );
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) { return $out; }
		foreach ( acf_get_field_groups() as $group ) {
			$fields = acf_get_fields( $group['key'] );
			if ( ! is_array( $fields ) ) { continue; }
			foreach ( $fields as $f ) {
				if ( empty( $f['name'] ) ) { continue; }
				$out[ $f['name'] ] = $group['title'] . ': ' . ( $f['label'] ?: $f['name'] );
			}
		}
		return $out;
	}

	/** Build an href for a value given the link type. */
	private function build_href( $val, $type ) {
		$val = trim( (string) $val );
		if ( '' === $val || 'none' === $type ) { return ''; }
		if ( 'tel' === $type )    { return 'tel:' . preg_replace( '/[^0-9+]/', '', $val ); }
		if ( 'mailto' === $type ) { return 'mailto:' . $val; }
		if ( 'url' === $type )    { return preg_match( '#^https?://#i', $val ) ? $val : 'https://' . ltrim( $val, '/' ); }
		// auto: detect email / URL / phone.
		if ( is_email( $val ) )                              { return 'mailto:' . $val; }
		if ( preg_match( '#^https?://#i', $val ) )           { return $val; }
		if ( preg_match( '#^[0-9 ()+.\-]{6,}$#', $val ) )    { return 'tel:' . preg_replace( '/[^0-9+]/', '', $val ); }
		return '';
	}

	public function render() {
		// Re-entry guard. A row's Value connected to "Post Content" (or a
		// [wpbb post:content] shortcode) on a normal page renders the page
		// inside itself -> infinite recursion -> memory exhaustion. Render
		// once per request depth; a nested call bails out empty.
		static $depth = 0;
		if ( $depth > 0 ) { return; }
		$depth++;
		$this->render_inner();
		$depth--;
	}

	private function render_inner() {
		$s     = $this->settings;
		$items = ( isset( $s->items ) && is_array( $s->items ) ) ? $s->items : array();
		$out   = '';

		foreach ( $items as $it ) {
			$it    = (object) $it;
			$acf   = trim( (string) ( $it->acf_field ?? '' ) );
			if ( '' !== $acf ) {
				$value = $this->field_value( $acf );
			} else {
				// Static text, or a dynamic field connected to / typed into Value
				// (a [wpbb] tag resolves here per current post).
				$value = trim( (string) ( $it->value ?? '' ) );
				if ( false !== strpos( $value, '[' ) ) { $value = trim( do_shortcode( $value ) ); }
			}
			if ( '' === $value ) { continue; } // The whole row hides when its field is empty.

			$label = trim( (string) ( $it->label ?? '' ) );
			$mode  = preg_replace( '/[^a-z]/', '', (string) ( $it->text_mode ?? 'auto' ) );
			if ( 'labelvalue' === $mode && '' !== $label ) {
				$text = $label . ': ' . $value;     // e.g. "Height: 5'10"" — stat rows.
			} elseif ( 'value' === $mode ) {
				$text = $value;                       // always the value, ignore the label.
			} else {
				$text = ( '' !== $label ) ? $label : $value; // auto (default): label replaces value if set.
			}
			$type  = preg_replace( '/[^a-z]/', '', (string) ( $it->link_type ?? 'auto' ) );
			$href  = $this->build_href( $value, $type );
			$icon  = trim( (string) ( $it->icon ?? '' ) );

			$ic    = '' !== $icon ? '<span class="ds-info-ico"><i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i></span>' : '';
			$inner = $ic . '<span class="ds-info-text">' . DS_Module_UI::inline( $text ) . '</span>';

			if ( '' !== $href ) {
				$ext  = ( 0 === strpos( $href, 'http' ) ) ? ' target="_blank" rel="noopener noreferrer"' : '';
				$out .= '<a class="ds-info-item" href="' . esc_attr( $href ) . '"' . $ext . '>' . $inner . '</a>';
			} else {
				$out .= '<span class="ds-info-item ds-info-item--plain">' . $inner . '</span>';
			}
		}

		if ( '' === $out ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:12px;opacity:.6">' . esc_html__( 'Info List: no values to show. Add items and map each to an ACF field (rows with an empty field hide on the live page).', 'ds-toolkit' ) . '</p>';
			}
			return;
		}

		echo '<div class="ds-info-list">' . $out . '</div>';
	}
}

/* ----------------------------------------------------------------- Item sub-form */
FLBuilder::register_settings_form( 'ds_info_item_form', array(
	'title' => __( 'Item', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => '',
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'icon'      => array( 'type' => 'icon', 'label' => __( 'Icon', 'ds-toolkit' ), 'show_remove' => true ),
						'acf_field' => array( 'type' => 'select', 'label' => __( 'ACF Field', 'ds-toolkit' ), 'default' => '', 'options' => DS_Info_List_Module::acf_field_options(), 'help' => __( 'Pick a field on the current post to pull (e.g. "Staff: Phone"). Reads it per post, hides the row when empty. Leave as None to use the Value below.', 'ds-toolkit' ) ),
						'value'     => array( 'type' => 'text', 'label' => __( 'Value (static)', 'ds-toolkit' ), 'connections' => array( 'string' ), 'help' => __( 'Used when no field name is set. Also accepts a dynamic field via the connect icon.', 'ds-toolkit' ) ),
						'label'     => array( 'type' => 'text', 'label' => __( 'Display Label (optional)', 'ds-toolkit' ), 'connections' => array( 'string' ), 'help' => __( 'A label for this row (e.g. "Height", "Email Us"). How it is used depends on Text below.', 'ds-toolkit' ) ),
						'text_mode' => array(
							'type'    => 'select',
							'label'   => __( 'Text', 'ds-toolkit' ),
							'default' => 'auto',
							'options' => array(
								'auto'       => __( 'Label if set, otherwise value', 'ds-toolkit' ),
								'labelvalue' => __( 'Label + value (e.g. "Height: 5\'10\"")', 'ds-toolkit' ),
								'value'      => __( 'Value only (ignore label)', 'ds-toolkit' ),
							),
							'help'    => __( 'How the row text is built. Use "Label + value" for stats like "GPA: 3.8"; "Label if set" suits links (shows "Instagram" instead of the URL).', 'ds-toolkit' ),
						),
						'link_type' => array(
							'type'    => 'select',
							'label'   => __( 'Link', 'ds-toolkit' ),
							'default' => 'auto',
							'options' => array(
								'auto'   => __( 'Auto (detect email / phone / URL)', 'ds-toolkit' ),
								'tel'    => __( 'Phone (tel:)', 'ds-toolkit' ),
								'mailto' => __( 'Email (mailto:)', 'ds-toolkit' ),
								'url'    => __( 'Website (URL)', 'ds-toolkit' ),
								'none'   => __( 'No link', 'ds-toolkit' ),
							),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Info_List_Module', array(
	'content' => array(
		'title'    => __( 'Items', 'ds-toolkit' ),
		'sections' => array(
			'items_sec' => array(
				'title'       => __( 'Info Items', 'ds-toolkit' ),
				'description' => __( 'Each row pulls a field and hides automatically when that field is empty. One module covers all the contact info on a template or a Contact page.', 'ds-toolkit' ),
				'fields'      => array(
					'field_source' => array(
						'type'    => 'select',
						'label'   => __( 'Read Fields From', 'ds-toolkit' ),
						'default' => 'post',
						'options' => array(
							'post'   => __( 'Current post (staff, athlete, etc.)', 'ds-toolkit' ),
							'option' => __( 'Partner / Site Settings', 'ds-toolkit' ),
						),
						'help'    => __( 'Where each row pulls its value. "Partner / Site Settings" reads the Partner options (phone, email, address, socials). Ideal for a Contact page.', 'ds-toolkit' ),
					),
					'items' => array(
						'type'         => 'form',
						'label'        => __( 'Item', 'ds-toolkit' ),
						'form'         => 'ds_info_item_form',
						'preview_text' => 'acf_field',
						'multiple'     => true,
					),
				),
			),
		),
	),
	'style' => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'layout_sec' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'layout' => array( 'type' => 'select', 'label' => __( 'Direction', 'ds-toolkit' ), 'default' => 'stack', 'options' => array( 'stack' => __( 'Stacked (vertical)', 'ds-toolkit' ), 'row' => __( 'Inline (row)', 'ds-toolkit' ) ) ),
					'align'  => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
					'gap'    => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '12', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 48, 'step' => 1 ) ),
				),
			),
			'icon_sec' => array(
				'title'  => __( 'Icon', 'ds-toolkit' ),
				'fields' => array(
					'icon_style' => array( 'type' => 'select', 'label' => __( 'Icon Style', 'ds-toolkit' ), 'default' => 'circle', 'options' => array( 'circle' => __( 'Circle background', 'ds-toolkit' ), 'plain' => __( 'Plain', 'ds-toolkit' ) ) ),
					'icon_size'  => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '16', 'description' => 'px', 'slider' => array( 'min' => 10, 'max' => 40, 'step' => 1 ) ),
					'icon_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'icon_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
				),
			),
			'text_sec' => array(
				'title'  => __( 'Text', 'ds-toolkit' ),
				'fields' => array(
					'text_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
		),
	),
) );

/* Offer this module in the builder panel ONLY while editing a Themer layout
   (fl-theme-layout): its rows read ACF fields off the rendered post, which is
   meaningless on a normal page and — via a "Post Content" connection — can even
   recurse. Instances already placed on pages keep rendering (the flag only
   controls the panel listing). */
add_filter( 'fl_builder_register_module', function ( $enabled, $instance ) {
	if ( ! isset( $instance->slug ) || 'ds-info-list' !== $instance->slug ) {
		return $enabled;
	}
	$pid = class_exists( 'FLBuilderModel' ) ? FLBuilderModel::get_post_id() : 0;
	if ( ! $pid ) {
		return $enabled;
	}
	return 'fl-theme-layout' === get_post_type( $pid );
}, 10, 2 );
