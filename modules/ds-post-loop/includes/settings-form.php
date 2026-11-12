<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Post Loop: the settings form (the two sub-forms for manual lists, then $ds_pl_form: every tab, section
 * and field, with the query and taxonomy fields built from what this site has registered).
 *
 * Required by ds-post-loop.php right before FLBuilder::register_module(), in the same scope, so the
 * variables it builds are the ones registered. Moved out of that file as it was in the 2026-11 audit.
 */

FLBuilder::register_settings_form( 'ds_sponsor_form', array(
	'title' => __( 'Sponsor', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => '',
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'sponsor_image'   => array( 'type' => 'photo', 'label' => __( 'Logo / Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ) ),
						'sponsor_caption' => array( 'type' => 'text', 'label' => __( 'Caption / Name', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'sponsor_url'     => array( 'type' => 'link', 'label' => __( 'Link URL', 'ds-toolkit' ), 'connections' => array( 'url' ) ),
						'sponsor_desc'    => array( 'type' => 'editor', 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'label' => __( 'Description', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
) );

FLBuilder::register_settings_form( 'ds_program_form', array(
	'title' => __( 'Program', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => '',
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'prog_icon'       => array( 'type' => 'icon', 'label' => __( 'Icon', 'ds-toolkit' ), 'show_remove' => true, 'help' => __( 'Optional. Shown instead of the image when set.', 'ds-toolkit' ) ),
						'prog_image'      => array( 'type' => 'photo', 'label' => __( 'Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Used when no icon is set.', 'ds-toolkit' ) ),
						'prog_date'       => array( 'type' => 'text', 'label' => __( 'Date', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'prog_subheading' => array( 'type' => 'text', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'prog_title'      => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'prog_desc'       => array( 'type' => 'editor', 'label' => __( 'Description', 'ds-toolkit' ), 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'connections' => array( 'string' ) ),
						'prog_url'        => array( 'type' => 'link', 'label' => __( 'Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ) ),
						'prog_btn'        => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'help' => __( 'Optional. With text a button shows; blank makes the whole card the link.', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
) );

$ds_pl_form = array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'card_layout_sec' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'card_layout' => array(
						'type'    => 'select',
						'label'   => __( 'Card Layout', 'ds-toolkit' ),
						'default' => 'news_featured',
						'options' => DS_Post_Loop_Module::card_layouts(),
						'help'    => __( 'How each result is presented. The Query tab decides WHICH posts are pulled (Post Type + filters). Set Post Type to match the card (Staff card uses the Staff type, etc.).', 'ds-toolkit' ),
						'toggle'  => array(
							'news_featured'  => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'layout', 'featured', 'cards', 'header_style', 'typography', 'hover', 'card_border', 'ds_borders' ), 'tabs' => array( 'query' ), 'fields' => array( 'date_format' ) ),
							'news_grid'      => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'cards2', 'header_style', 'typography', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ), 'fields' => array( 'date_format' ) ),
							'staff_card'     => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'staff_card', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_photo'  => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'commit_card', 'commit_filter_opts', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_logo'   => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'commit_card', 'commit_filter_opts', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_action' => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'commit_card', 'commit_filter_opts', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_strip'  => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'commit_strip_opts', 'commit_filter_opts', 'header_style', 'hover', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'team_list'      => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'team_list_opts', 'header_style', 'hover', 'card_border', 'ds_borders' ), 'tabs' => array( 'query' ) ),
							'team_card'      => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'team_card_opts', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'custom'         => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'loopcard', 'header_style', 'typography', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ), 'fields' => array( 'date_format' ) ),
							'sponsor'        => array( 'sections' => array( 'header', 'sponsors_sec', 'sponsor_opts', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ) ),
							'program'        => array( 'sections' => array( 'header', 'programs_sec', 'program_opts', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ) ),
							// No news 'typography' section here: its fields target .ds-news-card-* classes
							// that never render in tournament markup (GH: title/meta typography live in
							// the Tournament Cards section as tn_title_typo / tn_meta_typo).
							'tournament'     => array( 'sections' => array( 'header', 'manage_sec', 'query', 'query_filter', 'tn_filter_opts', 'tournament_opts', 'header_style', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
						),
					),
					'date_format'    => array( 'type' => 'text', 'label' => __( 'Date Format', 'ds-toolkit' ), 'default' => 'M Y', 'help' => __( 'PHP date format for the card date (e.g. M Y → Jun 2026).', 'ds-toolkit' ) ),
				),
			),
			// Edit the entries this loop shows, in place (DS_Loop_Manager). Changes are
			// held in pl_manage and written to the posts only when the page is published.
			'manage_sec' => array(
				'title'       => __( 'Manage entries', 'ds-toolkit' ),
				'description' => __( 'Edit, add, hide, remove and reorder the entries this loop shows. Changes are saved to them when you publish; Cancel or discarding the draft throws them away.', 'ds-toolkit' ),
				'fields'      => array(
					'pl_manage_ui' => array( 'type' => 'raw', 'label' => '', 'content' => '<div class="ds-lm" data-ds-loop-manager><p class="ds-lm-msg">' . esc_html__( 'Loading entries…', 'ds-toolkit' ) . '</p></div>' ),
					'pl_manage'    => array( 'type' => 'text', 'label' => '', 'default' => '', 'className' => 'ds-lm-store' ),
				),
			),
			// Sits directly below the Layout selector. Only revealed when the
			// "Custom Loop Layout" is selected (it IS content: your per-post markup).
			'loopcard' => array(
				'title'  => __( 'Custom Loop Layout', 'ds-toolkit' ),
				'fields' => array(
					'loop_custom' => array(
						'type'        => 'code',
						'editor'      => 'html',
						'mode'        => 'html',
						'label'       => __( 'Custom Item Markup', 'ds-toolkit' ),
						'rows'        => 14,
						'default'     => "<article class=\"my-card\">\n\t<a href=\"{permalink}\">\n\t\t<img src=\"{image}\" alt=\"{title}\" />\n\t\t<span class=\"cat\">{category}</span>\n\t\t<h3>{title}</h3>\n\t\t<time>{date}</time>\n\t</a>\n</article>",
						'connections' => array( 'string' ),
						'help'        => __( 'Rendered once per post. Use the connect (+) icon to insert a dynamic field, the {tokens} below, or any shortcode (e.g. a saved layout: [fl_builder_insert_layout id="123"]). Tokens: {title} {permalink} {date} {category} {excerpt} {image} {id}. Shortcodes + dynamic fields resolve against each post. Relationship fields: [ds_team_coach_avatars field=\"team_coach\"] draws the related posts\' featured images as small overlapping circles (atts: field, size, overlap, ring, ring_width, limit, link, image_size, class).', 'ds-toolkit' ),
						'preview'     => array( 'type' => 'none' ),
					),
					'loop_cols' => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Grid columns wrapping your items. Defaults: 2 on tablet, 1 on mobile.', 'ds-toolkit' ) ),
					'loop_gap'  => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				),
			),
			// Program Cards manual list: content (not a query), so it lives on the Content
			// tab below Layout. Revealed only when Card Layout = Program (card_layout toggle).
			'programs_sec' => array(
				'title'       => __( 'Program Cards (manual list)', 'ds-toolkit' ),
				'description' => __( 'Build each card by hand: date, sub-heading, title, description, a link/button, and an icon or image.', 'ds-toolkit' ),
				'fields' => array(
					'programs' => array(
						'type'         => 'form',
						'label'        => __( 'Program', 'ds-toolkit' ),
						'form'         => 'ds_program_form',
						'preview_text' => 'prog_title',
						'multiple'     => true,
					),
				),
			),
			// Sponsors manual list: content, not a query — Content tab, revealed only when
			// Card Layout = Sponsor (card_layout toggle).
			'sponsors_sec' => array(
				'title'  => __( 'Sponsors (manual list)', 'ds-toolkit' ),
				'fields' => array(
					'sponsors' => array(
						'type'         => 'form',
						'label'        => __( 'Sponsor', 'ds-toolkit' ),
						'form'         => 'ds_sponsor_form',
						'preview_text' => 'sponsor_caption',
						'multiple'     => true,
					),
				),
			),
			'header' => array(
				'title'  => __( 'Header', 'ds-toolkit' ),
				'fields' => array(
					'show_header'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Header', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'heading', 'show_button', 'header_divider' ) ) ),
					),
					'heading'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'ds-toolkit' ),
						'rows'    => 2,
						'default' => '',
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the header accent. {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'show_button'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Button', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'button_text', 'button_link' ) ) ),
					),
					'button_text'  => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => 'View All' ),
					'button_link'  => array( 'type' => 'link', 'label' => __( 'Button Link', 'ds-toolkit' ), 'show_target' => true, 'default' => '/news/', 'connections' => array( 'url' ) ),
					'header_divider'    => array(
						'type'    => 'select',
						'label'   => __( 'Header Divider', 'ds-toolkit' ),
						'default' => 'solid',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'solid' => __( 'Solid', 'ds-toolkit' ), 'dashed' => __( 'Dashed', 'ds-toolkit' ), 'dotted' => __( 'Dotted', 'ds-toolkit' ) ),
						'help'    => __( 'Draws a line between the header and the content below.', 'ds-toolkit' ),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'header_divider_w', 'header_divider_color', 'header_divider_gap' ) ),
							'dashed' => array( 'fields' => array( 'header_divider_w', 'header_divider_color', 'header_divider_gap' ) ),
							'dotted' => array( 'fields' => array( 'header_divider_w', 'header_divider_color', 'header_divider_gap' ) ),
						),
					),
					'header_divider_w'     => array( 'type' => 'unit', 'label' => __( 'Divider Width', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 10, 'step' => 1 ) ),
					'header_divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-line-color)', 'show_reset' => true ),
					'header_divider_gap'   => array( 'type' => 'unit', 'label' => __( 'Space Below Divider', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
				),
			),
			'ds_display' => array(
				'title'  => __( 'Display', 'ds-toolkit' ),
				'fields' => array(
					'display' => array(
						'type'    => 'select',
						'label'   => __( 'Display As', 'ds-toolkit' ),
						'default' => 'grid',
						'options' => array( 'grid' => __( 'Grid', 'ds-toolkit' ), 'carousel' => __( 'Carousel', 'ds-toolkit' ), 'paginated' => __( 'Grid + Pagination', 'ds-toolkit' ) ),
						'toggle'  => array(
							'carousel'  => array( 'fields' => array( 'car_per_view', 'car_gap', 'car_autoplay', 'car_loop', 'car_pause_hover', 'car_drag', 'car_arrows', 'car_dots', 'car_speed', 'car_nav_color' ) ),
							'paginated' => array( 'fields' => array( 'pag_per_page', 'pag_type', 'pag_color', 'pag_text_color' ) ),
						),
					),
					'car_per_view'   => array( 'type' => 'unit', 'label' => __( 'Slides Per View', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ), 'help' => __( 'How many cards show at once. Defaults: tablet 2, mobile 1.', 'ds-toolkit' ) ),
					'car_gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'car_autoplay'   => array( 'type' => 'select', 'label' => __( 'Autoplay', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'car_interval' ) ) ) ),
					'car_interval'   => array( 'type' => 'unit', 'label' => __( 'Interval', 'ds-toolkit' ), 'default' => '5', 'description' => 's', 'slider' => array( 'min' => 2, 'max' => 15, 'step' => 1 ) ),
					'car_loop'       => array( 'type' => 'select', 'label' => __( 'Loop', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_pause_hover'=> array( 'type' => 'select', 'label' => __( 'Pause on Hover', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_drag'       => array( 'type' => 'select', 'label' => __( 'Drag / Swipe', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_arrows'     => array( 'type' => 'select', 'label' => __( 'Arrows', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_dots'       => array( 'type' => 'select', 'label' => __( 'Dots', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_speed'      => array( 'type' => 'unit', 'label' => __( 'Transition Speed', 'ds-toolkit' ), 'default' => '500', 'description' => 'ms', 'slider' => array( 'min' => 150, 'max' => 1200, 'step' => 50 ) ),
					'car_nav_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Arrows / Dots Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pag_per_page'   => array( 'type' => 'unit', 'label' => __( 'Items Per Page', 'ds-toolkit' ), 'default' => '6', 'slider' => array( 'min' => 1, 'max' => 24, 'step' => 1 ) ),
					'pag_type'       => array( 'type' => 'select', 'label' => __( 'Pagination Style', 'ds-toolkit' ), 'default' => 'numbers', 'options' => array( 'numbers' => __( 'Page Numbers', 'ds-toolkit' ), 'loadmore' => __( 'Load More button', 'ds-toolkit' ) ) ),
					'pag_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active / Button Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pag_text_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
		),
	),
	'query'   => array(
		'title'    => __( 'Query', 'ds-toolkit' ),
		'sections' => array(
			'query' => array(
				'title'  => __( 'Posts', 'ds-toolkit' ),
				'fields' => array(
					'source'         => array( 'type' => 'select', 'label' => __( 'Source', 'ds-toolkit' ), 'default' => 'custom', 'options' => array( 'custom' => __( 'This query (below)', 'ds-toolkit' ), 'archive' => __( 'Current archive (main query)', 'ds-toolkit' ), 'related' => __( 'Related to this page (ACF relationship)', 'ds-toolkit' ) ), 'help' => __( 'On an archive template choose “Current archive” to loop whatever the archive shows (team-category term, category, tag, CPT archive). “Related to this page” loops the posts a relationship field on the page being viewed points to, in the order they were picked (a team’s coaches). Otherwise build a custom query below.', 'ds-toolkit' ), 'hide' => array( 'archive' => array( 'sections' => array( 'query_filter', 'query_sort', 'query_more' ) ), 'related' => array( 'sections' => array( 'query_filter', 'query_sort', 'query_more' ) ) ), 'toggle' => array( 'custom' => array( 'fields' => array( 'post_type', 'posts_per_page', 'order_by', 'order', 'offset', 'exclude_current', 'date_after', 'date_before', 'keyword' ) ), 'related' => array( 'fields' => array( 'related_field' ) ) ) ),
					'related_field'  => array( 'type' => 'text', 'label' => __( 'Relationship Field', 'ds-toolkit' ), 'default' => 'team_coach', 'help' => __( 'The ACF relationship / post-object field name on the page being viewed (team_coach = a team’s coaches).', 'ds-toolkit' ) ),
					'post_type'      => array( 'type' => 'select', 'label' => __( 'Post Type', 'ds-toolkit' ), 'default' => 'post', 'options' => DS_Post_Loop_Module::post_type_options() ),
					'posts_per_page' => array( 'type' => 'unit', 'label' => __( 'Number of Posts', 'ds-toolkit' ), 'default' => '5', 'slider' => array( 'min' => 1, 'max' => 12, 'step' => 1 ), 'help' => __( 'Total posts pulled. The first one becomes the large featured card; the rest fill the loop.', 'ds-toolkit' ) ),
				),
			),
			'query_filter' => array(
				'title'  => __( 'Filter by Taxonomy', 'ds-toolkit' ),
				// Fields injected after the form is defined (one term-suggest field per
				// public taxonomy, revealed by the Taxonomy selector's toggle).
				'fields' => array(),
			),
			'query_sort' => array(
				'title'  => __( 'Sort', 'ds-toolkit' ),
				'fields' => array(
					'order_by'       => array(
						'type'    => 'select',
						'label'   => __( 'Order By', 'ds-toolkit' ),
						'default' => 'date',
						'options' => array(
							'date'           => __( 'Date', 'ds-toolkit' ),
							'modified'       => __( 'Last Modified', 'ds-toolkit' ),
							'title'          => __( 'Title', 'ds-toolkit' ),
							'menu_order'     => __( 'Menu Order / Nested Pages position', 'ds-toolkit' ),
							'meta_value'     => __( 'Custom Field (text)', 'ds-toolkit' ),
							'meta_value_num' => __( 'Custom Field (number)', 'ds-toolkit' ),
							'rand'           => __( 'Random', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'meta_value'     => array( 'fields' => array( 'order_meta_key' ) ),
							'meta_value_num' => array( 'fields' => array( 'order_meta_key' ) ),
						),
						'help'    => __( 'Menu Order follows the drag order set by the Nested Pages plugin (and any post type’s Page Attributes order).', 'ds-toolkit' ),
					),
					'order_meta_key' => array( 'type' => 'text', 'label' => __( 'Custom Field Key', 'ds-toolkit' ), 'default' => '', 'help' => __( 'The meta_key to order by, e.g. an ACF field name. Pick the (number) option above for numeric fields.', 'ds-toolkit' ) ),
					'order'          => array(
						'type'    => 'select',
						'label'   => __( 'Order', 'ds-toolkit' ),
						'default' => 'DESC',
						'options' => array( 'DESC' => __( 'Descending (Z to A, newest first)', 'ds-toolkit' ), 'ASC' => __( 'Ascending (A to Z, oldest first)', 'ds-toolkit' ) ),
					),
				),
			),
			'query_more' => array(
				'title'     => __( 'More Filters', 'ds-toolkit' ),
				'collapsed' => true,
				'fields'    => array(
					'date_after'     => array( 'type' => 'text', 'label' => __( 'From Date', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Only posts published on/after this date. YYYY-MM-DD, or a relative window like "-30 days". Blank = no lower bound.', 'ds-toolkit' ) ),
					'date_before'    => array( 'type' => 'text', 'label' => __( 'To Date', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Only posts published on/before this date. Same formats as From Date. Blank = no upper bound.', 'ds-toolkit' ) ),
					'keyword'        => array( 'type' => 'text', 'label' => __( 'Keyword Search', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Only posts matching this keyword (searches title and content).', 'ds-toolkit' ) ),
					'offset'         => array( 'type' => 'unit', 'label' => __( 'Offset', 'ds-toolkit' ), 'default' => '0', 'slider' => array( 'min' => 0, 'max' => 20, 'step' => 1 ), 'help' => __( 'Skip this many posts from the start of the result set.', 'ds-toolkit' ) ),
					'exclude_current' => array( 'type' => 'select', 'label' => __( 'Exclude Current Post', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ), 'help' => __( 'On a single post / CPT view, leave out the post being viewed — ideal for a “More News / Related” strip.', 'ds-toolkit' ) ),
				),
			),
			'empty_cfg' => array(
				'title'     => __( 'When There Are No Results', 'ds-toolkit' ),
				'collapsed' => true,
				'fields' => array(
					'empty_show' => array(
						'type'    => 'select',
						'label'   => __( 'Show a Notice', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No (render nothing)', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'empty_text', 'empty_heading', 'empty_desc', 'empty_align' ) ) ),
						'help'    => __( 'What visitors see when the query returns nothing. Without it the section renders as a heading over blank space. Choose No only where an empty loop should collapse silently.', 'ds-toolkit' ),
					),
					'empty_text' => array(
						'type'        => 'text',
						'label'       => __( 'Badge', 'ds-toolkit' ),
						'default'     => '',
						'connections' => array( 'string' ),
						'help'        => __( 'Optional small pill above the heading, e.g. COMING SOON.', 'ds-toolkit' ),
					),
					'empty_heading' => array(
						'type'        => 'text',
						'label'       => __( 'Heading', 'ds-toolkit' ),
						'default'     => '',
						'connections' => array( 'string' ),
						'help'        => __( 'Blank shows "Coming Soon", unless a Badge or Description is set.', 'ds-toolkit' ),
					),
					'empty_desc' => array(
						'type'          => 'editor',
						'media_buttons' => false,
						'rows'          => 3,
						'wpautop'       => false,
						'label'         => __( 'Description', 'ds-toolkit' ),
						'help'          => __( 'Optional line under the heading, e.g. "Our next tournament dates are being finalised."', 'ds-toolkit' ),
					),
					'empty_align' => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ),
					),
				),
			),
			'tn_filter_opts' => array(
				'title'       => __( 'Filter Bar (Event Card)', 'ds-toolkit' ),
				'description' => __( 'The front-end filter bar above the Tournament "Event Card" grid/list. Every element is optional and developer-configured here — nothing is hard-coded.', 'ds-toolkit' ),
				'fields'      => array(
					'tn_filter'        => array(
						'type'    => 'select',
						'label'   => __( 'Filter Bar', 'ds-toolkit' ),
						'default' => 'disable',
						'options' => array( 'disable' => __( 'Disable', 'ds-toolkit' ), 'enable' => __( 'Enable', 'ds-toolkit' ) ),
						'toggle'  => array( 'enable' => array( 'fields' => array( 'tn_filter_tabs', 'tn_filter_state', 'tn_filter_search', 'tn_filter_count' ) ) ),
					),
					'tn_filter_tabs'   => array(
						'type'    => 'select',
						'label'   => __( 'Tabs Source', 'ds-toolkit' ),
						'default' => '',
						'options' => DS_Post_Loop_Module::tourn_tab_options(),
						'help'    => __( 'What drives the tab pills (All / …): pick any taxonomy or ACF choice field that exists on THIS site for the queried post type. The plugin registers nothing itself — if the site has no suitable taxonomy, create one (e.g. via ACF → Taxonomies) or leave tabs off. The chosen values also feed the row eyebrow and the card chips.', 'ds-toolkit' ),
					),
					'tn_filter_state'  => array(
						'type'    => 'select',
						'label'   => __( 'State Dropdown', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
						'help'    => __( 'Built from each event\'s State field (event_state), falling back to the ", XX" tail of the Location address. Hidden automatically when no event has a state.', 'ds-toolkit' ),
					),
					'tn_filter_search' => array(
						'type'    => 'select',
						'label'   => __( 'Search Box', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
					),
					'tn_filter_count'  => array(
						'type'    => 'select',
						'label'   => __( 'Event Count', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			// Top to bottom as the module renders: header, the chosen layout's cards, then
			// what applies to every card, the section box, and last the legacy width block.
			'header_style' => array(
				'title'  => __( 'Header', 'ds-toolkit' ),
				'fields' => array(
					'heading_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'heading_accent_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading Accent', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'outline_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Outline Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Stroke colour for {outline}…{/outline} text in this module. Blank = the Theme Setting default.', 'ds-toolkit' ) ),
					'outline_width' => array( 'type' => 'unit', 'label' => __( 'Outline Text Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = the Theme Setting default.', 'ds-toolkit' ), 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'heading_typography'  => array( 'type' => 'typography', 'label' => __( 'Heading Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-heading' ) ),
					'btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array(
							'yes'    => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ),
							'dark'   => __( 'Dark', 'ds-toolkit' ),
							'accent' => __( 'Heading Accent colour', 'ds-toolkit' ),
						),
						'help'    => __( 'The “See all” button inherits the global Button (background, hover, radius, typography) from Theme Setting by default.', 'ds-toolkit' ),
						'toggle'  => array( 'dark' => array( 'fields' => array( 'btn_dark_bg', 'btn_dark_color' ) ) ),
					),
					'btn_dark_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'btn_dark_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
			'layout' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'gap'             => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'feature_width'   => array( 'type' => 'text', 'label' => __( 'Featured Column Width', 'ds-toolkit' ), 'default' => '1.4fr', 'help' => __( 'CSS track size for the featured column (e.g. 1.4fr, 1.6fr, 480px).', 'ds-toolkit' ) ),
					'feature_min_h'   => array( 'type' => 'unit', 'label' => __( 'Featured Min Height', 'ds-toolkit' ), 'default' => '420', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 240, 'max' => 700, 'step' => 10 ) ),
					'card_min_h'      => array( 'type' => 'unit', 'label' => __( 'Card Min Height', 'ds-toolkit' ), 'default' => '195', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 120, 'max' => 400, 'step' => 5 ) ),
				),
			),
			'featured' => array(
				'title'  => __( 'Featured Card', 'ds-toolkit' ),
				'fields' => array(
					'featured_badge'  => array( 'type' => 'text', 'label' => __( 'Badge Override', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Optional. Overrides the featured badge text (defaults to the post’s category).', 'ds-toolkit' ) ),
					'feature_bg'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Fallback Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Shown behind / when a post has no featured image.', 'ds-toolkit' ) ),
					'overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'overlay_top'     => array( 'type' => 'unit', 'label' => __( 'Overlay Top Opacity', 'ds-toolkit' ), 'default' => '15', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_bottom'  => array( 'type' => 'unit', 'label' => __( 'Overlay Bottom Opacity', 'ds-toolkit' ), 'default' => '92', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'badge_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Badge Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'badge_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Badge Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'feature_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Featured Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'excerpt_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Excerpt Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'readmore_text'   => array( 'type' => 'text', 'label' => __( 'Read More Text', 'ds-toolkit' ), 'default' => 'Read More' ),
					'readmore_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Read More Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
			'cards' => array(
				'title'  => __( 'Loop Cards', 'ds-toolkit' ),
				'fields' => array(
					'card_bg'            => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_border_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_hover_border'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Hover Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_border_width'  => array( 'type' => 'unit', 'label' => __( 'Card Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'card_radius'        => array( 'type' => 'unit', 'label' => __( 'Card Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'category_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Category Eyebrow', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_title_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'date_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'chevron_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Chevron', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
			// --- Style 2 only: card grid ---
			'cards2' => array(
				'title'  => __( 'Cards', 'ds-toolkit' ),
				'fields' => array(
					'cols2'           => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'gap2'            => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card2_min'       => array( 'type' => 'unit', 'label' => __( 'Image Height', 'ds-toolkit' ), 'default' => '210', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 120, 'max' => 400, 'step' => 5 ) ),
					'card2_radius'    => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'card2_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card2_border'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card2_border_w'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'pill2_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pill2_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'title2_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'date2_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'readmore2_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Read More', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'readmore2_text'  => array( 'type' => 'text', 'label' => __( 'Read More Text', 'ds-toolkit' ), 'default' => 'Read More' ),
				),
			),
			// --- Staff card layout options ---
			'staff_card' => array(
				'title'  => __( 'Staff Cards', 'ds-toolkit' ),
				'fields' => array(
					'staff_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Defaults: 2 on tablet, 1 on mobile.', 'ds-toolkit' ) ),
					'staff_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card_link'         => array( 'type' => 'select', 'label' => __( 'Link Card to Post', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Makes the whole card a clickable link to the post. Turn off to remove the card link (contact / social icons stay clickable).', 'ds-toolkit' ) ),
					'staff_show_photo'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Photo', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No (names only)', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'staff_photo_style', 'staff_photo_ratio', 'staff_avatar_size', 'staff_avatar_layout', 'staff_initials_bg', 'staff_initials_color' ) ) ),
						// Photo Style then hides whichever of its own fields do not apply.
						'trigger' => array( 'yes' => array( 'fields' => array( 'staff_photo_style' ) ) ),
						'help'    => __( 'No: every card shows the name, title and contact icons without a photo, whether or not the entry has one.', 'ds-toolkit' ),
					),
					'staff_photo_style' => array(
						'type'    => 'select',
						'label'   => __( 'Photo Style', 'ds-toolkit' ),
						'default' => 'card',
						'options' => array( 'card' => __( 'Full card (photo on top)', 'ds-toolkit' ), 'avatar' => __( 'Small circle', 'ds-toolkit' ) ),
						'toggle'  => array(
							'card'   => array( 'fields' => array( 'staff_photo_ratio' ) ),
							'avatar' => array( 'fields' => array( 'staff_avatar_size', 'staff_avatar_layout', 'staff_initials_bg', 'staff_initials_color' ) ),
						),
						'help'    => __( 'Small circle suits small or low-quality headshots: the photo shows as a small round avatar instead of filling the top of the card. Anyone without a photo gets their initials in the circle.', 'ds-toolkit' ),
					),
					'staff_photo_ratio' => array(
						'type'    => 'select',
						'label'   => __( 'Photo Ratio', 'ds-toolkit' ),
						'default' => '3 / 4',
						'options' => array( '3 / 4' => __( 'Portrait 3:4', 'ds-toolkit' ), '4 / 5' => __( 'Portrait 4:5', 'ds-toolkit' ), '1 / 1' => __( 'Square 1:1', 'ds-toolkit' ), '4 / 3' => __( 'Landscape 4:3', 'ds-toolkit' ) ),
					),
					'staff_avatar_size'    => array( 'type' => 'unit', 'label' => __( 'Circle Size', 'ds-toolkit' ), 'default' => '96', 'description' => 'px', 'slider' => array( 'min' => 56, 'max' => 160, 'step' => 2 ), 'help' => __( 'Diameter of the photo circle.', 'ds-toolkit' ) ),
					'staff_avatar_layout'  => array(
						'type'    => 'select',
						'label'   => __( 'Circle Layout', 'ds-toolkit' ),
						'default'    => 'stack',
						'responsive' => true,
						'options'    => array( 'stack' => __( 'Centred, circle above the name', 'ds-toolkit' ), 'side' => __( 'Side by side, circle left of the name', 'ds-toolkit' ) ),
						'help'       => __( 'Side by side makes a compact list: good for one or two columns, or a long staff list on phones (set it on the phone size only to keep the desktop grid centred).', 'ds-toolkit' ),
					),
					'staff_initials_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Initials Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Circle colour for someone without a photo. Blank uses the heading colour.', 'ds-toolkit' ) ),
					'staff_initials_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Initials Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank uses white.', 'ds-toolkit' ) ),
					'staff_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_card_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'staff_name_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Name', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_role_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_ico_size'    => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '38', 'description' => 'px', 'slider' => array( 'min' => 24, 'max' => 72, 'step' => 1 ), 'help' => __( 'Diameter of the round contact / social icons; the glyph scales with it.', 'ds-toolkit' ) ),
					'staff_ico_bg'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_ico_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_ico_hover'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_show_email'  => array( 'type' => 'select', 'label' => __( 'Show Email', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'staff_show_phone'  => array( 'type' => 'select', 'label' => __( 'Show Phone', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'staff_show_social' => array( 'type' => 'select', 'label' => __( 'Show Social', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'staff_name_typo'   => array( 'type' => 'typography', 'label' => __( 'Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-name' ) ),
					'staff_role_typo'   => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-role' ) ),
				),
			),
			// --- Commitments / Athletes card options (shared by Photo / Logo / Action) ---
			'commit_card' => array(
				'title'  => __( 'Commitment Cards', 'ds-toolkit' ),
				'fields' => array(
					'commit_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Logo Row reads better at 1-2 columns. Defaults: 2 tablet, 1 mobile.', 'ds-toolkit' ) ),
					'commit_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card_link'          => array( 'type' => 'select', 'label' => __( 'Link Card to Post', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Makes the whole card a clickable link to the post. Turn off to remove the card link.', 'ds-toolkit' ) ),
					'commit_photo_ratio' => array( 'type' => 'select', 'label' => __( 'Photo Ratio (Photo / Action)', 'ds-toolkit' ), 'default' => '3 / 4', 'options' => array( '3 / 4' => __( 'Portrait 3:4', 'ds-toolkit' ), '4 / 5' => __( 'Portrait 4:5', 'ds-toolkit' ), '1 / 1' => __( 'Square 1:1', 'ds-toolkit' ), '4 / 3' => __( 'Landscape 4:3', 'ds-toolkit' ), '16 / 10' => __( 'Landscape 16:10', 'ds-toolkit' ) ) ),
					'commit_photo_fit'   => array( 'type' => 'select', 'label' => __( 'Photo Fit', 'ds-toolkit' ), 'default' => 'cover', 'options' => array( 'cover' => __( 'Fill (crop to the ratio)', 'ds-toolkit' ), 'contain' => __( 'Fit (whole photo, no crop)', 'ds-toolkit' ) ), 'help' => __( 'Fill crops the photo to the card ratio; use Photo Focus to keep the subject in frame. Fit always shows the whole photo, letterboxed on the Photo Backdrop colour.', 'ds-toolkit' ) ),
					'commit_photo_focus' => array( 'type' => 'select', 'label' => __( 'Photo Focus', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'top' => __( 'Top (faces)', 'ds-toolkit' ), 'bottom' => __( 'Bottom', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ), 'help' => __( 'Which part of the photo stays visible when Fill crops it — Top keeps heads in frame on portrait ratios.', 'ds-toolkit' ) ),
					'commit_photo_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Photo Backdrop', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Behind the photo (visible with Fit, or while loading). Blank = the global Headings colour.', 'ds-toolkit' ) ),
					'commit_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_dark_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Logo Row Background (dark)', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_radius'      => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'commit_border_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_border_width'=> array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 8, 'step' => 1 ) ),
					'commit_name_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Name', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_school_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'School / Meta', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_name_typo'   => array( 'type' => 'typography', 'label' => __( 'Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-name' ) ),
					'commit_meta_typo'   => array( 'type' => 'typography', 'label' => __( 'School / Meta Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-role' ) ),
					'commit_show_year'   => array( 'type' => 'select', 'label' => __( 'Show Class Year (Action Card)', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'The Action Card meta line reads "year | college". Set to No for a college-only line.', 'ds-toolkit' ) ),
					'commit_school_key'  => array( 'type' => 'text', 'label' => __( 'College Name Field', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Meta/ACF key holding the college name. Blank auto-detects school_name, university, college, college_name, school.', 'ds-toolkit' ) ),
					'commit_logo_key'    => array( 'type' => 'text', 'label' => __( 'College Logo Field', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Meta/ACF key holding the college logo image. Blank auto-detects school_logo, college_logo, logo.', 'ds-toolkit' ) ),
				),
			),
			// --- Compact Strip (logo | name | club mark) ---
			'commit_strip_opts' => array(
				'title'       => __( 'Compact Strip', 'ds-toolkit' ),
				'description' => __( 'A short dense row per athlete: college logo on the left, name and college in the middle, the club mark on the right. No athlete photo — ideal when athletes share a placeholder image.', 'ds-toolkit' ),
				'fields'      => array(
					'cr_cols'         => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ), 'help' => __( 'Defaults: 2 tablet, 1 mobile.', 'ds-toolkit' ) ),
					'cr_gap'          => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '16', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'card_link'       => array( 'type' => 'select', 'label' => __( 'Link Card to Post', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'cr_show_college' => array( 'type' => 'select', 'label' => __( 'Show College Name', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Off leaves just the athlete name beside the logo.', 'ds-toolkit' ) ),
					'cr_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Strip Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Accent colour.', 'ds-toolkit' ) ),
					'cr_radius'       => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '4', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 24, 'step' => 1 ) ),
					'cr_pad'          => array( 'type' => 'unit', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '12', 'description' => 'px', 'slider' => array( 'min' => 4, 'max' => 32, 'step' => 1 ) ),
					'cr_name_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Name Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = white.', 'ds-toolkit' ) ),
					'cr_meta_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'College Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'cr_logo_size'    => array( 'type' => 'unit', 'label' => __( 'College Logo Size', 'ds-toolkit' ), 'default' => '44', 'description' => 'px', 'slider' => array( 'min' => 24, 'max' => 90, 'step' => 1 ) ),
					'cr_logo_bg'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'College Logo Backdrop', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'A light chip behind the logo keeps dark college marks legible on a strong background. Blank = white.', 'ds-toolkit' ) ),
					'cr_brand_show'   => array( 'type' => 'select', 'label' => __( 'Show Club Logo', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'cr_brand', 'cr_brand_size' ) ) ) ),
					'cr_brand'        => array( 'type' => 'photo', 'label' => __( 'Club Logo', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Blank = the site\'s Partner Logo setting. Use a version that reads on the strip background (a white mark on a strong colour).', 'ds-toolkit' ) ),
					'cr_brand_size'   => array( 'type' => 'unit', 'label' => __( 'Club Logo Size', 'ds-toolkit' ), 'default' => '38', 'description' => 'px', 'slider' => array( 'min' => 20, 'max' => 80, 'step' => 1 ) ),
					'cr_name_typo'    => array( 'type' => 'typography', 'label' => __( 'Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-commit-strip .ds-people-name' ) ),
					'cr_meta_typo'    => array( 'type' => 'typography', 'label' => __( 'College Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-commit-strip .ds-people-role' ) ),
				),
			),
			// --- Front-end filter bar for the Commitment / Athlete card grids ---
			'commit_filter_opts' => array(
				'title'       => __( 'Filter Bar (Commitments)', 'ds-toolkit' ),
				'description' => __( 'A pill bar above the card grid that filters the flat list in place — no page reload, no grouping. The facet is developer-chosen (a meta field like Division, or a taxonomy); every value found in the results becomes a pill, and "All" shows everything.', 'ds-toolkit' ),
				'fields'      => array(
					'cf_filter'     => array( 'type' => 'select', 'label' => __( 'Filter Bar', 'ds-toolkit' ), 'default' => 'disable', 'options' => array( 'disable' => __( 'Disable', 'ds-toolkit' ), 'enable' => __( 'Enable', 'ds-toolkit' ) ), 'toggle' => array( 'enable' => array( 'fields' => array( 'cf_source', 'cf_order', 'cf_all_label', 'cf_count', 'cf_count_noun', 'cf_align', 'cf_bar_bg', 'cf_tab_color', 'cf_tab_active_bg', 'cf_tab_active_color', 'cf_tab_typo' ) ) ) ),
					'cf_source'     => array( 'type' => 'text', 'label' => __( 'Filter By', 'ds-toolkit' ), 'default' => 'meta:division', 'help' => __( 'meta:&lt;field_key&gt; for an ACF/meta field (default meta:division), or tax:&lt;taxonomy_slug&gt; for a taxonomy. A comma or pipe separated meta value tags the card with several values.', 'ds-toolkit' ) ),
					'cf_order'      => array( 'type' => 'text', 'label' => __( 'Pill Order', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Optional comma-separated order for the pills, e.g. "D1, D2, D3, MCLA". Values not listed keep their natural order at the end.', 'ds-toolkit' ) ),
					'cf_all_label'  => array( 'type' => 'text', 'label' => __( '"All" Pill Label', 'ds-toolkit' ), 'default' => 'All' ),
					'cf_count'      => array( 'type' => 'select', 'label' => __( 'Result Count', 'ds-toolkit' ), 'default' => 'show', 'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ) ),
					'cf_count_noun' => array( 'type' => 'text', 'label' => __( 'Count Noun', 'ds-toolkit' ), 'default' => 'commitments', 'help' => __( 'Word after the number, e.g. "20 commitments".', 'ds-toolkit' ) ),
					'cf_align'      => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
					'cf_bar_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Bar Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank = transparent (pills sit straight on the page).', 'ds-toolkit' ) ),
					'cf_tab_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'cf_tab_active_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active Pill Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Accent colour.', 'ds-toolkit' ) ),
					'cf_tab_active_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active Pill Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'cf_tab_typo'   => array( 'type' => 'typography', 'label' => __( 'Pill Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-commit-tab' ) ),
				),
			),
			// --- Team list options ---
			'team_card_opts' => array(
				'title'       => __( 'Team Cards', 'ds-toolkit' ),
				'description' => __( 'Photo grid of teams. Cards link to the team\'s External Link (new tab) when set, else the team page. Teams without a featured image use the Social Card (Theme Setting on Launchpad 6; the stock LA social card on Launchpad 5).', 'ds-toolkit' ),
				'fields'      => array(
					'tc_cols'       => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ) ),
					'tc_gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'tc_ratio'      => array( 'type' => 'select', 'label' => __( 'Photo Ratio', 'ds-toolkit' ), 'default' => '16 / 10', 'options' => array( '16 / 9' => '16:9', '16 / 10' => '16:10', '4 / 3' => '4:3', '3 / 2' => '3:2', '1 / 1' => '1:1' ) ),
					'tc_card_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tc_name_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Team Name Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tc_ico_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Arrow Icon Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Accent colour.', 'ds-toolkit' ) ),
					'tc_name_typo'  => array( 'type' => 'typography', 'label' => __( 'Team Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-teamcard-name' ) ),
				),
			),
			'team_list_opts' => array(
				'title'  => __( 'Team List', 'ds-toolkit' ),
				'fields' => array(
					'team_gap'          => array( 'type' => 'unit', 'label' => __( 'Row Gap', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'team_row_bg'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Row Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_row_border'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Row Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_row_border_w' => array( 'type' => 'unit', 'label' => __( 'Row Border Width', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'team_row_radius'   => array( 'type' => 'unit', 'label' => __( 'Row Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 24, 'step' => 1 ) ),
					'team_name_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Team Name', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_btn_bg'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_btn_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Icon', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_name_typo'    => array( 'type' => 'typography', 'label' => __( 'Team Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-team-name' ) ),
				),
			),
			'tournament_opts' => array(
				'title'       => __( 'Tournament Cards', 'ds-toolkit' ),
				'description' => __( 'Ordering is automatic: upcoming events first, sorted by the Event Date field, with past events hidden. Entries with no Event Date (programs, clinics) follow them in the Query tab\'s Order By / Order. Number of Posts blank shows 6.', 'ds-toolkit' ),
				'fields' => array(
					'tn_style'       => array(
						'type'    => 'select',
						'label'   => __( 'Card Style', 'ds-toolkit' ),
						'default' => 'overlap',
						'options' => array(
							'overlap' => __( 'Classic — overlapping details card', 'ds-toolkit' ),
							'event'   => __( 'Event Card — badge, details list, filter-ready', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'overlap' => array( 'fields' => array( 'tn_overlap', 'tn_inset', 'tn_align', 'tn_logo_shape' ) ),
							'event'   => array( 'fields' => array( 'tn_display', 'tn_badge', 'tn_filter' ) ),
						),
						'help'    => __( 'Event Card: flat card with a badge pill on the image, left-aligned date/location plus gender & division chips, and a full-width button. Supports the filter bar.', 'ds-toolkit' ),
					),
					'tn_display'     => array(
						'type'    => 'select',
						'label'   => __( 'Display', 'ds-toolkit' ),
						'default' => 'grid',
						'options' => array( 'grid' => __( 'Grid (cards)', 'ds-toolkit' ), 'list' => __( 'List (rows)', 'ds-toolkit' ) ),
						'toggle'  => array( 'grid' => array( 'fields' => array( 'tn_badge', 'tn_cols', 'tn_img_ratio' ) ) ),
						'help'    => __( 'List renders each event as a full-width row: date tile, logo, gender + title + location, division chips, and a Register button.', 'ds-toolkit' ),
					),
					'tn_badge'       => array( 'type' => 'text', 'label' => __( 'Badge Text', 'ds-toolkit' ), 'default' => 'Tournament', 'help' => __( 'The pill shown at the top of the image. Blank = no badge.', 'ds-toolkit' ) ),
					'tn_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ) ),
					'tn_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '28', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'tn_img_ratio'   => array( 'type' => 'select', 'label' => __( 'Image Ratio', 'ds-toolkit' ), 'default' => '16 / 10', 'options' => array( '16 / 9' => '16:9', '16 / 10' => '16:10', '4 / 3' => '4:3', '3 / 2' => '3:2', '1 / 1' => '1:1' ) ),
					'tn_logo_size'   => array( 'type' => 'unit', 'label' => __( 'Event Image Size', 'ds-toolkit' ), 'default' => '96', 'description' => 'px', 'slider' => array( 'min' => 48, 'max' => 200, 'step' => 1 ), 'help' => __( 'Size of the small Event Image centered over the featured image. Shown only when an event has an Event Image.', 'ds-toolkit' ) ),
					'tn_logo_shape'  => array( 'type' => 'select', 'label' => __( 'Event Image Shape', 'ds-toolkit' ), 'default' => 'circle', 'options' => array( 'circle' => __( 'Circle', 'ds-toolkit' ), 'square' => __( 'Rounded square', 'ds-toolkit' ) ) ),
					'tn_overlap'     => array( 'type' => 'unit', 'label' => __( 'Card Overlap', 'ds-toolkit' ), 'default' => '48', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 120, 'step' => 1 ), 'help' => __( 'How far the details card pulls up over the image.', 'ds-toolkit' ) ),
					'tn_inset'       => array( 'type' => 'unit', 'label' => __( 'Card Side Inset', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ), 'help' => __( 'Horizontal inset of the details card from the image edges.', 'ds-toolkit' ) ),
					'tn_align'       => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ) ),
					'tn_surface_bg'  => array(
						'type'        => 'color',
						'connections' => array( 'color' ),
						'label'       => __( 'Card Background', 'ds-toolkit' ),
						'default'     => '',
						'show_reset'  => true,
						'show_alpha'  => true,
						'help'        => __( 'The card itself. Blank keeps the built-in white on Event cards, and keeps the card transparent on Overlap cards (where the Details panel below is the visible surface).', 'ds-toolkit' ),
						'preview'     => array( 'type' => 'css', 'selector' => '.ds-tourn-card', 'property' => 'background-color' ),
					),
					'tn_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Details Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'The inner details panel that overlaps the image on the Overlap layout — not the card. For the card surface use Card Background above.', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-tourn-body', 'property' => 'background-color' ) ),
					'tn_pad'         => array( 'type' => 'unit', 'label' => __( 'Details Padding', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'tn_radius'      => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank = global Theme Setting corner radius.', 'ds-toolkit' ) ),
					'tn_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tn_meta_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date / Location Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tn_icon_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Meta Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'tourn_btn'      => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => 'View More' ),
					'tn_excerpt'       => array(
						'type'    => 'select',
						'label'   => __( 'Excerpt', 'ds-toolkit' ),
						'default' => 'hide',
						'options' => array( 'hide' => __( 'Hide', 'ds-toolkit' ), 'show' => __( 'Show', 'ds-toolkit' ) ),
						'toggle'  => array( 'show' => array( 'fields' => array( 'tn_excerpt_words', 'tn_excerpt_color' ) ) ),
						'help'    => __( 'A short description under the details: the entry\'s Excerpt, or the start of its content when the Excerpt is empty.', 'ds-toolkit' ),
					),
					'tn_excerpt_words' => array( 'type' => 'unit', 'label' => __( 'Excerpt Length', 'ds-toolkit' ), 'default' => '18', 'description' => __( 'words', 'ds-toolkit' ), 'slider' => array( 'min' => 5, 'max' => 60, 'step' => 1 ) ),
					'tn_excerpt_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Excerpt Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tn_title_typo'  => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-tourn-title' ) ),
					'tn_meta_typo'   => array( 'type' => 'typography', 'label' => __( 'Date / Location Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-tourn-meta' ) ),
				),
			),
			'program_opts' => array(
				'title'  => __( 'Program Cards', 'ds-toolkit' ),
				'fields' => array(
					'pg_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'pg_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'pg_align'       => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ) ) ),
					'pg_same_height' => array( 'type' => 'select', 'label' => __( 'Card Height', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Equal (match tallest in row)', 'ds-toolkit' ), 'no' => __( 'Natural (fit content)', 'ds-toolkit' ) ), 'help' => __( 'Equal makes every card in a row the same height (buttons line up at the bottom).', 'ds-toolkit' ) ),
					'pg_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_pad'         => array( 'type' => 'unit', 'label' => __( 'Card Padding', 'ds-toolkit' ), 'default' => '28', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'pg_img_h'       => array( 'type' => 'unit', 'label' => __( 'Image Height', 'ds-toolkit' ), 'default' => '180', 'description' => 'px', 'slider' => array( 'min' => 80, 'max' => 360, 'step' => 1 ), 'help' => __( 'Image height for items that use an image (not an icon).', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-media', 'property' => 'height', 'unit' => 'px' ) ),
					'pg_icon_size'   => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'slider' => array( 'min' => 16, 'max' => 96, 'step' => 1 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-ico', 'property' => 'font-size', 'unit' => 'px' ) ),
					'pg_icon_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-ico', 'property' => 'color' ) ),
					'pg_date_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'pg_sub_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Sub-heading Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_desc_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_style'      => array( 'type' => 'select', 'label' => __( 'Button Style', 'ds-toolkit' ), 'default' => 'theme', 'options' => array( 'theme' => __( 'Theme button (default)', 'ds-toolkit' ), 'custom' => __( 'Custom', 'ds-toolkit' ) ), 'toggle' => array( 'custom' => array( 'fields' => array( 'pg_btn_bg', 'pg_btn_color', 'pg_btn_hover_bg', 'pg_btn_hover_color', 'pg_btn_radius' ) ) ), 'help' => __( 'Theme button follows Theme Setting (colour + shape). Choose Custom to style this card\'s button below.', 'ds-toolkit' ) ),
					'pg_btn_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Button colour.', 'ds-toolkit' ) ),
					'pg_btn_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_hover_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_hover_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_radius'     => array( 'type' => 'unit', 'label' => __( 'Button Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank = follow the theme Button Shape. Set a value for a custom rounded button (overrides the theme shape for this card).', 'ds-toolkit' ) ),
					'pg_date_typo'      => array( 'type' => 'typography', 'label' => __( 'Date Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-date' ) ),
					'pg_sub_typo'       => array( 'type' => 'typography', 'label' => __( 'Sub-heading Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-sub' ) ),
					'pg_title_typo'     => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-title' ) ),
					'pg_desc_typo'      => array( 'type' => 'typography', 'label' => __( 'Description Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-desc' ) ),
					'pg_btn_typo'       => array( 'type' => 'typography', 'label' => __( 'Button Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-btn' ) ),
				),
			),
			'sponsor_opts' => array(
				'title'  => __( 'Sponsor Grid', 'ds-toolkit' ),
				'fields' => array(
					'sp_cols'       => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'sp_gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'sp_logo_h'     => array( 'type' => 'unit', 'label' => __( 'Logo Max Height', 'ds-toolkit' ), 'default' => '70', 'description' => 'px', 'slider' => array( 'min' => 30, 'max' => 180, 'step' => 1 ) ),
					'sp_pad'        => array( 'type' => 'unit', 'label' => __( 'Card Padding', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'sp_card_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'sp_name_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Caption Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'sp_desc_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'sp_grayscale'  => array( 'type' => 'select', 'label' => __( 'Greyscale Logos', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes (colour on hover)', 'ds-toolkit' ) ) ),
				),
			),
			'typography' => array(
				'title'  => __( 'Card Typography', 'ds-toolkit' ),
				'fields' => array(
					'feature_title_typography' => array( 'type' => 'typography', 'label' => __( 'Featured Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-feature-title' ) ),
					'excerpt_typography'       => array( 'type' => 'typography', 'label' => __( 'Featured Excerpt', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-feature-excerpt' ) ),
					'badge_typography'         => array( 'type' => 'typography', 'label' => __( 'Badge', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-badge' ) ),
					'card_title_typography'    => array( 'type' => 'typography', 'label' => __( 'Card Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-card-title, .ds-news-card2-title' ) ),
					'card_cat_typography'      => array( 'type' => 'typography', 'label' => __( 'Card Category', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-card-cat, .ds-news-card2-pill' ) ),
					'card_date_typography'     => array( 'type' => 'typography', 'label' => __( 'Card Date', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-card-date, .ds-news-card2-date' ) ),
				),
			),
			'card_border' => array(
				'title'  => __( 'Card Border', 'ds-toolkit' ),
				'fields' => array(
					'card_bd_style' => array(
						'type'    => 'select',
						'label'   => __( 'Border Style', 'ds-toolkit' ),
						'default' => 'default',
						'options' => array(
							'default' => __( 'Theme Default', 'ds-toolkit' ),
							'none'    => __( 'None', 'ds-toolkit' ),
							'solid'   => __( 'Solid', 'ds-toolkit' ),
							'dashed'  => __( 'Dashed', 'ds-toolkit' ),
							'dotted'  => __( 'Dotted', 'ds-toolkit' ),
							'double'  => __( 'Double', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
							'dashed' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
							'dotted' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
							'double' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
						),
						'help'    => __( 'Border around each card. “Theme Default” keeps the layout’s built-in border.', 'ds-toolkit' ),
					),
					'card_bd_width'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 10, 'step' => 1 ) ),
					'card_bd_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_bd_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank keeps the layout default.', 'ds-toolkit' ) ),
				),
			),
			// --- Display: Grid / Carousel / Paginated (card-grid layouts) ---
			'hover' => array(
				'title'  => __( 'Hover & Animation', 'ds-toolkit' ),
				'fields' => array(
					'hover_effect' => array(
						'type'    => 'select',
						'label'   => __( 'Hover Effect', 'ds-toolkit' ),
						'default' => 'lift',
						'options' => array(
							'none'   => __( 'None', 'ds-toolkit' ),
							'lift'   => __( 'Lift', 'ds-toolkit' ),
							'grow'   => __( 'Grow', 'ds-toolkit' ),
							'zoom'   => __( 'Zoom Image', 'ds-toolkit' ),
							'shadow' => __( 'Shadow', 'ds-toolkit' ),
							'border' => __( 'Border Highlight', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'lift'   => array( 'fields' => array( 'hover_distance', 'hover_speed', 'hover_shadow_color' ) ),
							'grow'   => array( 'fields' => array( 'hover_scale', 'hover_speed', 'hover_shadow_color' ) ),
							'zoom'   => array( 'fields' => array( 'hover_scale', 'hover_speed' ) ),
							'shadow' => array( 'fields' => array( 'hover_speed', 'hover_shadow_color' ) ),
							'border' => array( 'fields' => array( 'hover_speed', 'hover_border_color' ) ),
						),
						'help'    => __( 'Animation when a card is hovered. Applies to every card layout.', 'ds-toolkit' ),
					),
					'hover_distance'     => array( 'type' => 'unit', 'label' => __( 'Lift Distance', 'ds-toolkit' ), 'default' => '6', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'hover_scale'        => array( 'type' => 'unit', 'label' => __( 'Scale', 'ds-toolkit' ), 'default' => '105', 'description' => '%', 'slider' => array( 'min' => 100, 'max' => 120, 'step' => 1 ) ),
					'hover_speed'        => array( 'type' => 'unit', 'label' => __( 'Transition Speed', 'ds-toolkit' ), 'default' => '300', 'description' => 'ms', 'slider' => array( 'min' => 100, 'max' => 800, 'step' => 25 ) ),
					'hover_shadow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Shadow Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'hover_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'hover_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Optional. Colour the card fades to on hover (any layout).', 'ds-toolkit' ) ),
				),
			),
			// Retired in 1.10.4: a new Post Loop fills its column and takes its spacing from the
			// Advanced tab like any Beaver module. This block only appears on modules saved on
			// Boxed / Full / Custom (content_width toggles its own section), so they keep their
			// width and padding; picking "Fill the column" retires it for that module.
			'spacing' => array(
				'title'       => __( 'Width & Spacing (legacy)', 'ds-toolkit' ),
				'description' => __( 'Kept for modules built before 1.10.4. New modules fill their column; set spacing on the Advanced tab.', 'ds-toolkit' ),
				'fields'      => array(
					'content_width'     => array(
						'type'    => 'select',
						'label'   => __( 'Content Width', 'ds-toolkit' ),
						'default' => 'fill',
						'options' => array(
							'fill'   => __( 'Fill the column (recommended)', 'ds-toolkit' ),
							'boxed'  => __( 'Boxed (max 1280px)', 'ds-toolkit' ),
							'full'   => __( 'Full width + padding / margin below', 'ds-toolkit' ),
							'custom' => __( 'Custom max-width', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'boxed'  => array( 'sections' => array( 'spacing' ) ),
							'full'   => array( 'sections' => array( 'spacing' ) ),
							'custom' => array( 'sections' => array( 'spacing' ), 'fields' => array( 'content_max_width' ) ),
						),
						'help'    => __( 'Fill the column hides this block: the loop takes its column\'s width and the Advanced tab\'s spacing, like other modules. Padding and Margin here stop applying.', 'ds-toolkit' ),
					),
					'content_max_width' => array( 'type' => 'unit', 'label' => __( 'Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 480, 'max' => 1920, 'step' => 10 ) ),
					'padding' => array(
						'type'       => 'dimension',
						'label'      => __( 'Padding', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Inner spacing around the content. 0 = compact / flush.', 'ds-toolkit' ),
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
);
// Shared "Border & Divider" section, shown for every layout as "Section" with the section
// background, placed before the legacy width block so that block stays last.
$ds_pl_spacing = $ds_pl_form['style']['sections']['spacing'];
unset( $ds_pl_form['style']['sections']['spacing'] );
$ds_pl_form['style']['sections'] = array_merge( $ds_pl_form['style']['sections'], DS_Module_UI::border_section( false ), array( 'spacing' => $ds_pl_spacing ) );
$ds_pl_form['style']['sections']['ds_borders']['title']  = __( 'Section', 'ds-toolkit' );
$ds_pl_form['style']['sections']['ds_borders']['fields'] = array(
	'section_bg' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Section Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
) + $ds_pl_form['style']['sections']['ds_borders']['fields'];
unset( $ds_pl_spacing );

/* Include / Exclude specific posts (GH #132) — one post-suggest (autocomplete + pills)
   pair per public post type, revealed by the Post Type selector's toggle. Built the
   same way as the taxonomy filter below and for the same reason: a BB settings form is
   registered once at init, so a single field cannot know which post type a given module
   instance will be set to. Generating a pair per type means each one can point
   `fl_as_posts` at its own type, which is what makes the picker search only Staff when
   Staff is selected. */
$ds_pl_q =& $ds_pl_form['query']['sections']['query']['fields'];
if ( isset( $ds_pl_q['post_type'] ) ) {
	if ( ! isset( $ds_pl_q['post_type']['toggle'] ) ) { $ds_pl_q['post_type']['toggle'] = array(); }
	foreach ( DS_Post_Loop_Module::post_type_options() as $ds_pt_name => $ds_pt_label ) {
		$ds_pt_key = str_replace( '-', '_', $ds_pt_name );
		$ds_inc    = 'inc_' . $ds_pt_key;
		$ds_exc    = 'exc_' . $ds_pt_key;
		$ds_pl_q['post_type']['toggle'][ $ds_pt_name ] = array( 'fields' => array( $ds_inc, $ds_exc ) );
		if ( isset( $ds_pl_q['source']['toggle']['custom']['fields'] ) ) {
			// A field named in no toggle branch is always visible, so without this the
			// pickers would linger when Source is "Current archive" (which ignores them).
			$ds_pl_q['source']['toggle']['custom']['fields'][] = $ds_inc;
			$ds_pl_q['source']['toggle']['custom']['fields'][] = $ds_exc;
		}
		$ds_pl_q[ $ds_inc ] = array(
			'type'   => 'suggest',
			'label'  => sprintf( __( 'Include Specific %s', 'ds-toolkit' ), $ds_pt_label ),
			'action' => 'fl_as_posts',
			'data'   => $ds_pt_name,
			'help'   => __( 'Type to search; picks show as pills. Leave empty for the normal query. When set, ONLY these entries are shown — Number of Posts, Order and Offset still apply on top.', 'ds-toolkit' ),
		);
		$ds_pl_q[ $ds_exc ] = array(
			'type'   => 'suggest',
			'label'  => sprintf( __( 'Exclude Specific %s', 'ds-toolkit' ), $ds_pt_label ),
			'action' => 'fl_as_posts',
			'data'   => $ds_pt_name,
			'help'   => __( 'Everything the query would normally return, minus these. Combines with Exclude Current Post rather than replacing it.', 'ds-toolkit' ),
		);
	}
	unset( $ds_pt_name, $ds_pt_label, $ds_pt_key, $ds_inc, $ds_exc );
	// Posts section order: Source, Post Type, Include / Exclude Specific, then Number of Posts.
	if ( isset( $ds_pl_q['posts_per_page'] ) ) { $ds_pp = $ds_pl_q['posts_per_page']; unset( $ds_pl_q['posts_per_page'] ); $ds_pl_q['posts_per_page'] = $ds_pp; unset( $ds_pp ); }
}
unset( $ds_pl_q );

/* Taxonomy filter — a Taxonomy selector plus one term-suggest (autocomplete + pills)
   field per public taxonomy, revealed by the selector's toggle. Built dynamically so
   it always matches the site's registered taxonomies. */
$ds_pl_taxes = array();
foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $ds_tx ) {
	if ( 'post_format' === $ds_tx->name ) { continue; }
	$ds_pl_taxes[ $ds_tx->name ] = $ds_tx->label;
}
$ds_tax_fields = array(
	'filter_tax' => array(
		'type'    => 'select',
		'label'   => __( 'Filter by Taxonomy', 'ds-toolkit' ),
		'default' => '',
		'options' => array_merge( array( '' => __( '— No filter —', 'ds-toolkit' ) ), $ds_pl_taxes ),
		'toggle'  => array(),
		'help'    => __( 'Limit the loop to specific terms. Choose a taxonomy, then add terms below (they appear as pills).', 'ds-toolkit' ),
	),
);
foreach ( $ds_pl_taxes as $ds_tx_name => $ds_tx_label ) {
	$ds_field = 'flt_' . str_replace( '-', '_', $ds_tx_name );
	$ds_tax_fields['filter_tax']['toggle'][ $ds_tx_name ] = array( 'fields' => array( $ds_field ) );
	$ds_tax_fields[ $ds_field ] = array(
		'type'   => 'suggest',
		'label'  => sprintf( __( '%s Terms', 'ds-toolkit' ), $ds_tx_label ),
		'action' => 'fl_as_terms',
		'data'   => $ds_tx_name,
		'help'   => __( 'Type to search; selected terms show as pills. Leave empty for all terms in this taxonomy.', 'ds-toolkit' ),
	);
}
$ds_pl_form['query']['sections']['query_filter']['fields'] = $ds_tax_fields;
