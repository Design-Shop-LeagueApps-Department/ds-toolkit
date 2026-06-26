<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Leagueapps Page Cards — the module version of the [child_pages] shortcode.
 *
 * Lists the child pages of the current page (or a chosen parent) as cards in a
 * responsive grid. Each card is either the built-in DEFAULT card (image + title
 * + excerpt + button, fully styleable here) or a saved Beaver Builder layout you
 * pick. In both modes the card is rendered with the child as the current post, so
 * the title / permalink / featured image resolve per page.
 *
 * @class DS_Page_Cards_Module
 */
class DS_Page_Cards_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Page Cards', 'ds-toolkit' ),
			'description'     => __( 'List child pages as cards, with a styleable built-in card or a saved layout (the module version of [child_pages]).', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-page-cards/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-page-cards/',
			'partial_refresh' => false,
			'editor_export'   => false,
			'enabled'         => true,
		) );
	}

	/** Saved Beaver Builder templates for the "card" picker (layout / row types). */
	public static function template_options() {
		$out  = array( '0' => __( '— Select a saved card template —', 'ds-toolkit' ) );
		$tpls = get_posts( array(
			'post_type'      => 'fl-builder-template',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'tax_query'      => array( array(
				'taxonomy' => 'fl-builder-template-type',
				'field'    => 'slug',
				'terms'    => array( 'layout', 'row' ),
				'operator' => 'IN',
			) ),
		) );
		if ( empty( $tpls ) ) {
			$tpls = get_posts( array( 'post_type' => 'fl-builder-template', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
		}
		foreach ( $tpls as $id ) { $out[ (string) $id ] = get_the_title( $id ) . " (#{$id})"; }
		return $out;
	}

	/** The built-in default card for one child page. */
	private function render_default_card( $child ) {
		$s     = $this->settings;
		$pid   = $child->ID;
		$url   = get_permalink( $pid );
		$title = get_the_title( $pid );

		$cls = 'dst-card';
		if ( ( $s->show_image ?? 'no' ) === 'yes' && ( $s->image_position ?? 'top' ) === 'left' ) {
			$cls .= ' dst-card--imgleft'; // image beside the text instead of above it.
		}
		$h = '<a class="' . $cls . '" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $title ) . '">';

		if ( ( $s->show_image ?? 'no' ) === 'yes' ) {
			$img = get_the_post_thumbnail_url( $pid, 'large' );
			if ( ! $img && class_exists( 'DS_Social_Card' ) && method_exists( 'DS_Social_Card', 'url' ) ) {
				$img = DS_Social_Card::url(); // brand placeholder when a page has no featured image.
			}
			$h .= '<div class="dst-card-img"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
		}

		$h .= '<div class="dst-card-body">';
		$h .= '<h3 class="dst-card-title">' . esc_html( $title ) . '</h3>';

		if ( ( $s->show_excerpt ?? 'no' ) === 'yes' ) {
			$ex = trim( (string) $child->post_excerpt ); // manual excerpt only (BB page content isn't excerpt-friendly).
			if ( '' !== $ex ) {
				$len = max( 4, (int) ( $s->excerpt_length ?? 18 ) );
				$h  .= '<p class="dst-card-excerpt">' . esc_html( wp_trim_words( $ex, $len, '&hellip;' ) ) . '</p>';
			}
		}

		if ( ( $s->show_button ?? 'yes' ) === 'yes' ) {
			$bt = trim( (string) ( $s->button_text ?? '' ) );
			if ( '' === $bt ) { $bt = __( 'View page', 'ds-toolkit' ); }
			$bs = ( ( $s->button_style ?? 'link' ) === 'button' ) ? 'button' : 'link';
			$h .= '<span class="dst-card-btn dst-card-btn--' . $bs . '">' . esc_html( $bt ) . '</span>';
		}

		$h .= '</div></a>';
		return $h;
	}

	public function render() {
		// Re-entry guard: a saved card template that contains this module again
		// would recurse forever (each child re-running the whole loop).
		static $depth = 0;
		if ( $depth > 0 ) { return; }

		$s           = $this->settings;
		$building     = class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active();
		$card_source  = (string) ( $s->card_source ?? 'default' );
		$template_id  = absint( $s->template ?? 0 );

		if ( 'template' === $card_source && ! $template_id ) {
			if ( $building ) { echo '<p style="padding:18px;opacity:.7;text-align:center">' . esc_html__( 'Page Cards: pick a saved card template, or switch the Card to "Default".', 'ds-toolkit' ) . '</p>'; }
			return;
		}

		// Parent page: the current page by default, or a specifically chosen one.
		if ( 'specific' === (string) ( $s->source ?? 'current' ) && ! empty( $s->parent_page ) ) {
			$pp        = is_array( $s->parent_page ) ? reset( $s->parent_page ) : $s->parent_page;
			$parent_id = absint( $pp );
		} else {
			$parent_id = (int) get_the_ID();
		}
		if ( ! $parent_id ) { return; }

		$limit      = max( 1, min( 200, absint( $s->limit ?? 50 ) ?: 50 ) );
		$orderby_ok = array( 'menu_order title', 'menu_order', 'title', 'date', 'modified' );
		$orderby    = in_array( (string) ( $s->order_by ?? 'menu_order title' ), $orderby_ok, true ) ? (string) $s->order_by : 'menu_order title';
		$order      = ( strtoupper( (string) ( $s->order ?? 'ASC' ) ) === 'DESC' ) ? 'DESC' : 'ASC';

		$children = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_parent'    => $parent_id,
			'orderby'        => $orderby,
			'order'          => $order,
			'posts_per_page' => $limit,
		) );

		if ( empty( $children ) ) {
			if ( $building ) { echo '<p style="padding:18px;opacity:.7;text-align:center">' . esc_html__( 'Page Cards: this parent page has no published child pages.', 'ds-toolkit' ) . '</p>'; }
			return;
		}

		$cols = max( 1, absint( $s->columns ?? 3 ) );
		$colt = max( 1, absint( $s->columns_tablet ?? 2 ) );
		$colm = max( 1, absint( $s->columns_mobile ?? 1 ) );
		$gap  = max( 0, absint( $s->gap ?? 24 ) );

		$depth++;
		global $post;
		$orig = $post;
		$out  = '';
		foreach ( $children as $child ) {
			$post = $child; // BB reads the child's title / permalink / featured image.
			setup_postdata( $post );
			$card = ( 'template' === $card_source )
				? do_shortcode( '[fl_builder_insert_layout id="' . $template_id . '"]' )
				: $this->render_default_card( $child );
			$out .= '<div class="dst-child-page-item">' . $card . '</div>';
		}
		wp_reset_postdata();
		$post = $orig;
		$depth--;

		$style = sprintf( '--dst-cols:%d;--dst-cols-tablet:%d;--dst-cols-mobile:%d;--dst-gap:%dpx;', $cols, $colt, $colm, $gap );
		echo '<div class="dst-child-pages" style="' . esc_attr( $style ) . '">' . $out . '</div>';
	}
}

FLBuilder::register_module( 'DS_Page_Cards_Module', array(
	'content' => array(
		'title'    => __( 'Pages', 'ds-toolkit' ),
		'sections' => array(
			'source_sec' => array(
				'title'  => __( 'Which Pages', 'ds-toolkit' ),
				'fields' => array(
					'source'      => array(
						'type'    => 'select',
						'label'   => __( 'List Children Of', 'ds-toolkit' ),
						'default' => 'current',
						'options' => array(
							'current'  => __( 'The current page', 'ds-toolkit' ),
							'specific' => __( 'A specific page', 'ds-toolkit' ),
						),
						'toggle'  => array( 'specific' => array( 'fields' => array( 'parent_page' ) ) ),
						'help'    => __( 'On a parent page, leave as "The current page" so it lists that page\'s children automatically.', 'ds-toolkit' ),
					),
					'parent_page' => array( 'type' => 'suggest', 'label' => __( 'Parent Page', 'ds-toolkit' ), 'action' => 'fl_as_posts', 'data' => 'page', 'limit' => 1 ),
					'limit'       => array( 'type' => 'unit', 'label' => __( 'Max Pages', 'ds-toolkit' ), 'default' => '50', 'slider' => array( 'min' => 1, 'max' => 200, 'step' => 1 ), 'help' => __( 'Capped at 200 to keep the per-card render from exhausting memory.', 'ds-toolkit' ) ),
					'order_by'    => array(
						'type'    => 'select',
						'label'   => __( 'Order By', 'ds-toolkit' ),
						'default' => 'menu_order title',
						'options' => array(
							'menu_order title' => __( 'Page Order, then Title', 'ds-toolkit' ),
							'menu_order'       => __( 'Page Order', 'ds-toolkit' ),
							'title'            => __( 'Title', 'ds-toolkit' ),
							'date'             => __( 'Date Published', 'ds-toolkit' ),
							'modified'         => __( 'Last Modified', 'ds-toolkit' ),
						),
					),
					'order'       => array( 'type' => 'select', 'label' => __( 'Order', 'ds-toolkit' ), 'default' => 'ASC', 'options' => array( 'ASC' => __( 'Ascending', 'ds-toolkit' ), 'DESC' => __( 'Descending', 'ds-toolkit' ) ) ),
				),
			),
			'card_sec' => array(
				'title'  => __( 'Card', 'ds-toolkit' ),
				'fields' => array(
					'card_source'    => array(
						'type'    => 'select',
						'label'   => __( 'Card', 'ds-toolkit' ),
						'default' => 'default',
						'options' => array(
							'default'  => __( 'Default card (built-in)', 'ds-toolkit' ),
							'template' => __( 'Saved layout template', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'default'  => array(
								'fields'   => array( 'show_image', 'show_excerpt', 'excerpt_length', 'show_button', 'button_text', 'button_style' ),
								'sections' => array( 'cardstyle_sec', 'image_sec', 'text_sec' ),
							),
							'template' => array( 'fields' => array( 'template' ) ),
						),
					),
					'template'       => array(
						'type'    => 'select',
						'label'   => __( 'Card Template', 'ds-toolkit' ),
						'default' => '0',
						'options' => DS_Page_Cards_Module::template_options(),
						'help'    => __( 'Each child page is rendered with this saved Beaver Builder template.', 'ds-toolkit' ),
					),
					'show_image'     => array( 'type' => 'select', 'label' => __( 'Show Image', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'image_position' ) ) ), 'help' => __( 'Uses the page\'s featured image, or the brand social card when it has none.', 'ds-toolkit' ) ),
					'show_excerpt'   => array( 'type' => 'select', 'label' => __( 'Show Excerpt', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'excerpt_length' ) ) ), 'help' => __( 'Shows the page\'s manual excerpt (auto-hides if it has none).', 'ds-toolkit' ) ),
					'excerpt_length' => array( 'type' => 'unit', 'label' => __( 'Excerpt Length', 'ds-toolkit' ), 'default' => '18', 'description' => __( 'words', 'ds-toolkit' ), 'slider' => array( 'min' => 4, 'max' => 60, 'step' => 1 ) ),
					'show_button'    => array( 'type' => 'select', 'label' => __( 'Show Link', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'button_text', 'button_style' ) ) ) ),
					'button_text'    => array( 'type' => 'text', 'label' => __( 'Link Text', 'ds-toolkit' ), 'default' => 'View page' ),
					'button_style'   => array( 'type' => 'select', 'label' => __( 'Link Style', 'ds-toolkit' ), 'default' => 'link', 'options' => array( 'link' => __( 'Text link', 'ds-toolkit' ), 'button' => __( 'Button (follows theme)', 'ds-toolkit' ) ) ),
				),
			),
		),
	),
	'style' => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'grid_sec' => array(
				'title'  => __( 'Grid', 'ds-toolkit' ),
				'fields' => array(
					'columns'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'columns_tablet' => array( 'type' => 'unit', 'label' => __( 'Columns (Tablet)', 'ds-toolkit' ), 'default' => '2', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Applies at 1024px and below.', 'ds-toolkit' ) ),
					'columns_mobile' => array( 'type' => 'unit', 'label' => __( 'Columns (Mobile)', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 1, 'max' => 4, 'step' => 1 ), 'help' => __( 'Applies at 768px and below.', 'ds-toolkit' ) ),
					'gap'            => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				),
			),
			'cardstyle_sec' => array(
				'title'  => __( 'Card Style', 'ds-toolkit' ),
				'fields' => array(
					'card_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_padding'      => array( 'type' => 'unit', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card_radius'       => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'card_border_width' => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 8, 'step' => 1 ) ),
					'card_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_align'        => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ) ),
				),
			),
			'image_sec' => array(
				'title'  => __( 'Image', 'ds-toolkit' ),
				'fields' => array(
					'image_position' => array(
						'type'    => 'select',
						'label'   => __( 'Image Position', 'ds-toolkit' ),
						'default' => 'top',
						'options' => array(
							'top'  => __( 'Top (above the text)', 'ds-toolkit' ),
							'left' => __( 'Left (beside the text)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'top'  => array( 'fields' => array( 'image_ratio' ) ),
							'left' => array( 'fields' => array( 'image_width' ) ),
						),
					),
					'image_ratio'    => array(
						'type'    => 'select',
						'label'   => __( 'Image Ratio', 'ds-toolkit' ),
						'default' => '3 / 2',
						'options' => array(
							'16 / 9' => __( '16:9', 'ds-toolkit' ),
							'3 / 2'  => __( '3:2', 'ds-toolkit' ),
							'4 / 3'  => __( '4:3', 'ds-toolkit' ),
							'1 / 1'  => __( '1:1 (square)', 'ds-toolkit' ),
							'4 / 5'  => __( '4:5 (portrait)', 'ds-toolkit' ),
						),
					),
					'image_width'    => array(
						'type'        => 'unit',
						'label'       => __( 'Image Width', 'ds-toolkit' ),
						'default'     => '42',
						'description' => '%',
						'slider'      => array( 'min' => 20, 'max' => 65, 'step' => 1 ),
						'help'        => __( 'Share of the card the image takes when placed on the left. Stacks on top on mobile.', 'ds-toolkit' ),
					),
				),
			),
			'text_sec' => array(
				'title'  => __( 'Text', 'ds-toolkit' ),
				'fields' => array(
					'title_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank inherits the theme heading colour.', 'ds-toolkit' ) ),
					'excerpt_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Excerpt Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
		),
	),
) );
