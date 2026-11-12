<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DS_Hero_Module: its sub-forms and its settings form (every tab, section and field), and the call that
 * registers the module with them.
 *
 * Required at the end of ds-hero.php, in the same scope. Moved out of that file as it was in the 2026-11
 * audit, so the class (what the block does) and the form (what an editor can set) can be read apart.
 */

/* ---------------------------------------------------- Mixed Media slide sub-form */

FLBuilder::register_settings_form( 'ds_hero_slide_form', array(
	'title' => __( 'Slide', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Slide', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'slide_type' => array(
							'type'    => 'select',
							'label'   => __( 'Slide Type', 'ds-toolkit' ),
							'default' => 'image',
							'options' => array(
								'image' => __( 'Image', 'ds-toolkit' ),
								'video' => __( 'Video', 'ds-toolkit' ),
							),
							'toggle'  => array(
								'image' => array( 'fields' => array( 'photo' ) ),
								'video' => array( 'fields' => array( 'video_media', 'video_url', 'video_poster', 'video_advance' ) ),
							),
						),
						'photo' => array(
							'type'        => 'photo',
							'label'       => __( 'Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
						),
						'video_media' => array(
							'type'  => 'video',
							'label' => __( 'Video (Media Library)', 'ds-toolkit' ),
							'help'  => __( 'Upload / pick an MP4 from the Media Library. Takes priority over the Video URL below.', 'ds-toolkit' ),
						),
						'video_url' => array(
							'type'        => 'text',
							'label'       => __( 'Video URL (MP4)', 'ds-toolkit' ),
							'connections' => array( 'url' ),
							'help'        => __( 'A direct .mp4 URL if the video is hosted elsewhere.', 'ds-toolkit' ),
						),
						'video_poster' => array(
							'type'        => 'photo',
							'label'       => __( 'Poster Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
							'help'        => __( 'Optional. Shown while the video loads.', 'ds-toolkit' ),
						),
						'video_advance' => array(
							'type'    => 'select',
							'label'   => __( 'Next Slide', 'ds-toolkit' ),
							'default' => 'end',
							'options' => array(
								'end'      => __( 'When the video ends', 'ds-toolkit' ),
								'interval' => __( 'After the Slide Interval (video loops)', 'ds-toolkit' ),
							),
							'help'    => __( 'Videos autoplay muted while their slide is showing (browsers require muted autoplay). Choose when the slideshow moves on.', 'ds-toolkit' ),
						),
					),
				),
			),
		),
	),
) );

/* -------------------------------------------- Style 3 Peek Slider slide sub-form */

FLBuilder::register_settings_form( 'ds_hero_peek_slide_form', array(
	'title' => __( 'Slide', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Slide', 'ds-toolkit' ),
			'sections' => array(
				'image' => array(
					'title'  => __( 'Image', 'ds-toolkit' ),
					'fields' => array(
						'image' => array(
							'type'        => 'photo',
							'label'       => __( 'Desktop Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
						),
						'image_mobile' => array(
							'type'        => 'photo',
							'label'       => __( 'Mobile Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
							'help'        => __( 'Optional. Used below the small breakpoint, handy when the desktop crop loses its subject on a phone.', 'ds-toolkit' ),
						),
						'img_alt' => array(
							'type'        => 'text',
							'label'       => __( 'Image Alt Text', 'ds-toolkit' ),
							'default'     => '',
							'connections' => array( 'string' ),
							'help'        => __( 'Describe the photo for screen readers. Leave blank if the image is purely decorative.', 'ds-toolkit' ),
						),
						'focal' => array(
							'type'    => 'select',
							'label'   => __( 'Image Focal Point', 'ds-toolkit' ),
							'default' => 'center center',
							'options' => array(
								'left top'      => __( 'Top Left', 'ds-toolkit' ),
								'center top'    => __( 'Top Centre', 'ds-toolkit' ),
								'right top'     => __( 'Top Right', 'ds-toolkit' ),
								'left center'   => __( 'Middle Left', 'ds-toolkit' ),
								'center center' => __( 'Centre', 'ds-toolkit' ),
								'right center'  => __( 'Middle Right', 'ds-toolkit' ),
								'left bottom'   => __( 'Bottom Left', 'ds-toolkit' ),
								'center bottom' => __( 'Bottom Centre', 'ds-toolkit' ),
								'right bottom'  => __( 'Bottom Right', 'ds-toolkit' ),
							),
							'help'    => __( 'The part of the photo to keep in frame when it is cropped to the slide.', 'ds-toolkit' ),
						),
					),
				),
				'content' => array(
					'title'  => __( 'Content', 'ds-toolkit' ),
					'fields' => array(
						'heading' => array(
							'type'        => 'text',
							'label'       => __( 'Heading', 'ds-toolkit' ),
							'default'     => '',
							'connections' => array( 'string' ),
						),
						'heading_tag' => array(
							'type'    => 'select',
							'label'   => __( 'Heading Tag', 'ds-toolkit' ),
							'default' => 'h2',
							'options' => array(
								'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3',
								'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
								'p'  => __( 'Paragraph', 'ds-toolkit' ),
								'div' => 'DIV',
							),
							'help'    => __( 'Use one H1 per page. On a page that already has an H1, H2 is the right choice here.', 'ds-toolkit' ),
						),
						'cta_text' => array(
							'type'        => 'text',
							'label'       => __( 'CTA Label', 'ds-toolkit' ),
							'default'     => '',
							'connections' => array( 'string' ),
							'help'        => __( 'Leave blank for a slide with no button.', 'ds-toolkit' ),
						),
						'cta_link' => array(
							'type'        => 'link',
							'label'       => __( 'CTA Link', 'ds-toolkit' ),
							'default'     => '',
							'connections' => array( 'url' ),
						),
						'cta_target' => array(
							'type'    => 'select',
							'label'   => __( 'CTA Link Target', 'ds-toolkit' ),
							'default' => '_self',
							'options' => array(
								'_self'  => __( 'Same Window', 'ds-toolkit' ),
								'_blank' => __( 'New Window', 'ds-toolkit' ),
							),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Hero_Module', array(
	// Reorganised only (Alipio 2026-09-27: "too crowded, just re organizing it so that it wont affect the
	// other builds or setting"). Content first; every field keeps its key, type, default and option values,
	// so saved heroes render exactly as before. Section keys are what hero_style toggles, so a section added
	// or renamed here must be listed there too.
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'hero_style_sec' => array(
				'title'  => __( 'Hero Style', 'ds-toolkit' ),
				'fields' => array(
					'hero_style' => array(
						'type'    => 'select',
						'label'   => __( 'Hero Style', 'ds-toolkit' ),
						'default' => 'style1',
						'options' => DS_Hero_Module::styles(),
						'help'    => __( 'Choose a hero layout. The options below change to match the style you pick.', 'ds-toolkit' ),
						// Each style shows only its own sections. Add a key per style.
						'toggle'  => array(
							'style1' => array( 'sections' => array( 'text', 'seo', 'buttons', 'bg', 'stats', 'layout', 'overlay', 'colors', 'typography', 'buttons_style', 'effects', 'spacing' ) ),
							'style2' => array( 'sections' => array( 'banner', 'seo', 'banner_media', 'banner_crumbs', 'banner_design', 'banner_shape', 'overlay', 'banner_scrim', 'banner_colors', 'typography', 'banner_nobg', 'effects', 'spacing' ) ),
							'style3' => array( 'sections' => array( 'peek_slides_sec', 'peek_behavior', 'peek_layout', 'peek_overlay', 'peek_text', 'peek_cta', 'peek_bg', 'peek_pattern_sec', 'spacing' ) ),
						),
						// Controls inside a shared section that do nothing in one style: the Page Banner
						// eyebrow is .ds-banner-eyebrow (Eyebrow typography styles .ds-hero-eyebrow), the
						// Slider has no .ds-hero-wrap for Container Width or Padding to reach, and the
						// breadcrumbs exist only on the Page Banner. Only fields no other toggle drives.
						'hide'    => array(
							'style1' => array( 'fields' => array( 'breadcrumbs_typography' ) ),
							'style2' => array( 'fields' => array( 'eyebrow_typography' ) ),
							'style3' => array( 'fields' => array( 'container_width', 'container_max_width', 'padding' ) ),
						),
					),
				),
			),
			'text' => array(
				'title'       => __( 'Text', 'ds-toolkit' ),
				'fields'      => array(
					'eyebrow' => array(
						'type'    => 'text',
						'label'   => __( 'Eyebrow', 'ds-toolkit' ),
						'default' => '',
						'help'    => __( 'Small label above the headline (optional).', 'ds-toolkit' ),
					),
					'heading' => array(
						'type'    => 'textarea',
						'label'   => __( 'Headline', 'ds-toolkit' ),
						'rows'    => 3,
						'default' => '',
						'help'    => __( 'Line breaks are kept. Wrap a word in {a}…{/a} to colour it with the accent colour, or in {g}…{/g} for a two-colour gradient (Style → Gradient & Outline Text). {outline}…{/outline} renders outlined (transparent, stroked) text, default style in Theme Setting. Markers cannot be nested; wrap separate ranges.', 'ds-toolkit' ),
					),
					'subtext' => array(
						'type'    => 'editor', 'media_buttons' => false, 'wpautop' => false,
						'label'   => __( 'Subtext', 'ds-toolkit' ),
						'rows'    => 3,
						'default' => '',
					),
					// Optional mark above the eyebrow (GH #192): a crest, badge or logo. Same
					// shape as the Heading module's divider image — the editor authors a
					// HEIGHT and the width follows, so the aspect ratio is never touched.
					'eyebrow_image' => array(
						'type'        => 'photo',
						'label'       => __( 'Eyebrow Image', 'ds-toolkit' ),
						'show_remove' => true,
						'connections' => array( 'photo' ),
						'help'        => __( 'Optional crest, badge or logo shown directly above the Eyebrow text. Blank renders nothing, not even a gap.', 'ds-toolkit' ),
					),
					'eyebrow_img_h' => array(
						'type'        => 'unit',
						'label'       => __( 'Eyebrow Image Size', 'ds-toolkit' ),
						'default'     => '',
						'description' => 'px',
						'responsive'  => true,
						'slider'      => array( 'min' => 16, 'max' => 320, 'step' => 1 ),
						'help'        => __( 'Height of the eyebrow image; the width follows so the aspect ratio never changes. Blank = 72px. Use the responsive icon for tablet and mobile sizes.', 'ds-toolkit' ),
					),
				),
			),
			'buttons' => array(
				'title'       => __( 'Buttons', 'ds-toolkit' ),
				'fields'      => array(
					'btn1_text'  => array( 'type' => 'text', 'label' => __( 'Button 1 Text', 'ds-toolkit' ), 'default' => 'Learn More' ),
					'btn1_link'  => array( 'type' => 'link', 'label' => __( 'Button 1 Link', 'ds-toolkit' ), 'default' => '' ),
					'btn1_style' => array( 'type' => 'select', 'label' => __( 'Button 1 Style', 'ds-toolkit' ), 'default' => 'primary', 'options' => array( 'primary' => __( 'Primary (filled)', 'ds-toolkit' ), 'ghost' => __( 'Ghost (outline)', 'ds-toolkit' ) ) ),
					'btn2_text'  => array( 'type' => 'text', 'label' => __( 'Button 2 Text', 'ds-toolkit' ), 'default' => '' ),
					'btn2_link'  => array( 'type' => 'link', 'label' => __( 'Button 2 Link', 'ds-toolkit' ), 'default' => '' ),
					'btn2_style' => array( 'type' => 'select', 'label' => __( 'Button 2 Style', 'ds-toolkit' ), 'default' => 'ghost', 'options' => array( 'primary' => __( 'Primary (filled)', 'ds-toolkit' ), 'ghost' => __( 'Ghost (outline)', 'ds-toolkit' ) ) ),
				),
			),
			'bg' => array(
				'title'       => __( 'Background', 'ds-toolkit' ),
				'fields'      => array(
					'bg_type' => array(
						'type'    => 'select',
						'label'   => __( 'Background Type', 'ds-toolkit' ),
						'default' => 'image',
						'options' => array(
							'image'     => __( 'Single Image', 'ds-toolkit' ),
							'slideshow' => __( 'Image Slideshow', 'ds-toolkit' ),
							'video'     => __( 'Video', 'ds-toolkit' ),
							'mixed'     => __( 'Mixed Media (Images + Videos)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'image'     => array( 'fields' => array( 'bg_photo', 'kenburns' ) ),
							'slideshow' => array( 'fields' => array( 'bg_photos', 'slide_interval', 'kenburns', 'slide_nav' ) ),
							'video'     => array( 'fields' => array( 'video_media', 'video_url', 'video_poster', 'video_pos_x', 'video_pos_y' ) ),
							'mixed'     => array( 'fields' => array( 'mixed_slides', 'slide_interval', 'kenburns', 'slide_nav' ) ),
						),
					),
					'bg_photo'       => array( 'type' => 'photo', 'label' => __( 'Image', 'ds-toolkit' ), 'show_remove' => true ),
					'bg_photos'      => array( 'type' => 'multiple-photos', 'label' => __( 'Slideshow Images', 'ds-toolkit' ) ),
					'mixed_slides'   => array(
						'type'         => 'form',
						'label'        => __( 'Slide', 'ds-toolkit' ),
						'form'         => 'ds_hero_slide_form',
						'preview_text' => 'slide_type',
						'multiple'     => true,
						'help'         => __( 'Build the slideshow from any mix of image and video slides. Drag to reorder.', 'ds-toolkit' ),
					),
					'slide_interval' => array( 'type' => 'unit', 'label' => __( 'Slide Interval', 'ds-toolkit' ), 'default' => '6', 'description' => 's', 'slider' => array( 'min' => 2, 'max' => 15, 'step' => 1 ) ),
					'kenburns'       => array( 'type' => 'select', 'label' => __( 'Ken Burns Effect', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes (slow zoom)', 'ds-toolkit' ) ), 'help' => __( 'Slowly zooms the background image(s). Disabled for reduced-motion visitors.', 'ds-toolkit' ) ),
					'slide_nav'      => array( 'type' => 'select', 'label' => __( 'Slide Navigation', 'ds-toolkit' ), 'default' => 'lines', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'lines' => __( 'Progress Lines (cooldown)', 'ds-toolkit' ), 'dots' => __( 'Dots', 'ds-toolkit' ), 'arrows' => __( 'Arrows', 'ds-toolkit' ), 'lines_arrows' => __( 'Lines + Arrows', 'ds-toolkit' ), 'both' => __( 'Dots + Arrows', 'ds-toolkit' ) ), 'help' => __( 'Navigation for the slideshow (2+ slides). Progress Lines are thin bars that fill over the slide interval (a cooldown to the next slide; on a play-until-end video slide they fill over the video). The active indicator and arrow hover use the accent colour.', 'ds-toolkit' ) ),
					'video_media'    => array( 'type' => 'video', 'label' => __( 'Video (Media Library)', 'ds-toolkit' ), 'help' => __( 'Upload / pick an MP4 from the Media Library. Takes priority over the Video URL below. Autoplays muted and loops.', 'ds-toolkit' ) ),
					'video_url'      => array( 'type' => 'text', 'label' => __( 'Video URL (MP4)', 'ds-toolkit' ), 'connections' => array( 'url' ), 'help' => __( 'Direct .mp4 URL if the video is hosted elsewhere. Autoplays muted and loops.', 'ds-toolkit' ) ),
					'video_poster'   => array( 'type' => 'photo', 'label' => __( 'Video Poster', 'ds-toolkit' ), 'show_remove' => true ),
					'video_pos_x'    => array(
						'type'        => 'unit',
						'label'       => __( 'Video Position X', 'ds-toolkit' ),
						'default'     => '50',
						'description' => '%',
						'responsive'  => true,
						'slider'      => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
						'help'        => __( 'Which part of the video stays in frame when it is cropped to the hero. 0% is the left edge, 100% the right. Use it to keep the action in view.', 'ds-toolkit' ),
					),
					'video_pos_y'    => array(
						'type'        => 'unit',
						'label'       => __( 'Video Position Y', 'ds-toolkit' ),
						'default'     => '50',
						'description' => '%',
						'responsive'  => true,
						'slider'      => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
						'help'        => __( '0% is the top edge, 100% the bottom.', 'ds-toolkit' ),
					),
				),
			),
			'stats' => array(
				'title'       => __( 'Stats Row', 'ds-toolkit' ),
				'description' => __( 'Up to three proof points. Leave a pair blank to hide it.', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'stat1_number' => array( 'type' => 'text', 'label' => __( 'Stat 1 Number', 'ds-toolkit' ), 'default' => '' ),
					'stat1_label'  => array( 'type' => 'text', 'label' => __( 'Stat 1 Label', 'ds-toolkit' ), 'default' => '' ),
					'stat2_number' => array( 'type' => 'text', 'label' => __( 'Stat 2 Number', 'ds-toolkit' ), 'default' => '' ),
					'stat2_label'  => array( 'type' => 'text', 'label' => __( 'Stat 2 Label', 'ds-toolkit' ), 'default' => '' ),
					'stat3_number' => array( 'type' => 'text', 'label' => __( 'Stat 3 Number', 'ds-toolkit' ), 'default' => '' ),
					'stat3_label'  => array( 'type' => 'text', 'label' => __( 'Stat 3 Label', 'ds-toolkit' ), 'default' => '' ),
				),
			),
			'banner' => array(
				'title'       => __( 'Page Banner', 'ds-toolkit' ),
				'description' => __( 'Leave everything blank and the banner fills in automatically from each page (the page name + its Page Banner box). To override, type a value or click the dynamic-field (database) icon on a field to connect it to any field.', 'ds-toolkit' ),
				'fields'      => array(
					'banner_eyebrow'  => array( 'type' => 'text', 'label' => __( 'Sub-heading (eyebrow)', 'ds-toolkit' ), 'default' => '', 'connections' => array( 'string' ), 'help' => __( 'Small line above the heading (optional).', 'ds-toolkit' ) ),
					'banner_heading'  => array( 'type' => 'text', 'label' => __( 'Heading', 'ds-toolkit' ), 'default' => '', 'connections' => array( 'string' ), 'help' => __( 'Blank = the page’s Banner Title, or the page name.', 'ds-toolkit' ) ),
					'banner_subtitle' => array( 'type' => 'editor', 'media_buttons' => false, 'wpautop' => false, 'label' => __( 'Subtitle', 'ds-toolkit' ), 'default' => '', 'rows' => 2, 'connections' => array( 'string' ), 'help' => __( 'Blank = the page’s Banner Subtitle.', 'ds-toolkit' ) ),
				),
			),
			'banner_media' => array(
				'title'       => __( 'Background', 'ds-toolkit' ),
				'fields'      => array(
					'banner_image'    => array( 'type' => 'photo', 'label' => __( 'Background Photo', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Blank = the page’s Banner photo.', 'ds-toolkit' ) ),
					'banner_video'    => array( 'type' => 'text', 'label' => __( 'Background Video URL', 'ds-toolkit' ), 'default' => '', 'connections' => array( 'string' ), 'help' => __( 'Optional MP4 URL. Blank = the page’s Banner video.', 'ds-toolkit' ) ),
					'banner_hide_text' => array(
						'type'    => 'select',
						'label'   => __( 'Text When Image Present', 'ds-toolkit' ),
						'default' => '',
						'options' => array(
							''           => __( 'Theme Setting default (recommended)', 'ds-toolkit' ),
							'force_show' => __( 'Always show (this instance)', 'ds-toolkit' ),
							'force_hide' => __( 'Always hide, image only (this instance)', 'ds-toolkit' ),
						),
						'help'    => __( 'When the banner has a background image or video, hide the auto heading / subtitle for a clean image-only banner.', 'ds-toolkit' ),
					),
				),
			),
			'banner_crumbs' => array(
				'title'       => __( 'Breadcrumbs', 'ds-toolkit' ),
				'fields'      => array(
					'show_breadcrumbs'     => array(
						'type'    => 'select',
						'label'   => __( 'Breadcrumbs', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'Hide', 'ds-toolkit' ), 'yes' => __( 'Show (Yoast)', 'ds-toolkit' ) ),
						'help'    => __( 'Shows the Yoast SEO breadcrumb trail. Enable Yoast breadcrumbs under SEO → Settings → Breadcrumbs.', 'ds-toolkit' ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'breadcrumbs_position', 'breadcrumbs_style' ) ) ),
					),
					'breadcrumbs_position' => array( 'type' => 'select', 'label' => __( 'Breadcrumbs Position', 'ds-toolkit' ), 'default' => 'top', 'options' => array( 'top' => __( 'Top (above heading)', 'ds-toolkit' ), 'below' => __( 'Below (under description)', 'ds-toolkit' ), 'edge' => __( 'On the bottom edge', 'ds-toolkit' ) ), 'toggle' => array( 'edge' => array( 'fields' => array( 'breadcrumbs_edge_lift' ) ) ) ),
					'breadcrumbs_style'    => array(
						'type'    => 'select',
						'label'   => __( 'Breadcrumbs Style', 'ds-toolkit' ),
						'default' => 'plain',
						'options' => array( 'plain' => __( 'Plain text', 'ds-toolkit' ), 'pill' => __( 'Pill (glass)', 'ds-toolkit' ), 'tab' => __( 'Tab (slanted)', 'ds-toolkit' ) ),
						'help'    => __( 'Pill sets the trail in a rounded, frosted capsule; Tab in a solid slanted tab with underlined links. Their colours are under Style > Colours.', 'ds-toolkit' ),
						'toggle'  => array( 'pill' => array( 'fields' => array( 'breadcrumbs_pill_bg', 'breadcrumbs_pill_border' ) ), 'tab' => array( 'fields' => array( 'breadcrumbs_pill_bg' ) ) ),
					),
				),
			),
			// Which elements the eyebrow and headline are, for search engines. The Slider has its
			// own per-slide Heading Tag.
			'seo' => array(
				'title'     => __( 'SEO Tags', 'ds-toolkit' ),
				'collapsed' => true,
				'fields'    => array(
					'eyebrow_tag' => array(
						'type'    => 'select',
						'label'   => __( 'Eyebrow Tag (SEO)', 'ds-toolkit' ),
						'default' => 'span',
						'options' => DS_Hero_Module::seo_tags(),
						'help'    => __( 'The element the eyebrow renders as. Span is what it has always been. When the eyebrow is the page title, make it H1 and set the Headline Tag below to H2 or Span. Changes the markup only, never the look.', 'ds-toolkit' ),
					),
					'title_tag' => array(
						'type'    => 'select',
						'label'   => __( 'Headline Tag (SEO)', 'ds-toolkit' ),
						'default' => 'h1',
						'options' => DS_Hero_Module::seo_tags(),
						'help'    => __( 'Use one H1 per page. Changes the markup only, never the look: the headline keeps the H1 styling from Theme Setting whatever its tag.', 'ds-toolkit' ),
					),
				),
			),
			'peek_slides_sec' => array(
				'title'       => __( 'Slides', 'ds-toolkit' ),
				'description' => __( 'Add, duplicate, remove and drag to reorder. The order here is the order on the page.', 'ds-toolkit' ),
				'fields'      => array(
					'peek_slides' => array(
						'type'         => 'form',
						'label'        => __( 'Slide', 'ds-toolkit' ),
						'form'         => 'ds_hero_peek_slide_form',
						'preview_text' => 'heading',
						'multiple'     => true,
					),
					'peek_label' => array(
						'type'    => 'text',
						'label'   => __( 'Carousel Label', 'ds-toolkit' ),
						'default' => '',
						'help'    => __( 'Names the carousel for screen readers, e.g. "Featured programs". Blank uses a generic label.', 'ds-toolkit' ),
					),
				),
			),
			'peek_behavior' => array(
				'title'       => __( 'Slider Behaviour', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'peek_autoplay' => array(
						'type'    => 'select',
						'label'   => __( 'Autoplay', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Enabled', 'ds-toolkit' ), 'no' => __( 'Disabled', 'ds-toolkit' ) ),
						'help'    => __( 'Always off for visitors who ask for reduced motion.', 'ds-toolkit' ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'peek_interval', 'peek_pause_hover', 'peek_resume', 'peek_playpause' ) ) ),
					),
					'peek_interval' => array(
						'type'        => 'unit',
						'label'       => __( 'Autoplay Speed', 'ds-toolkit' ),
						'default'     => '7',
						'description' => 's',
						'slider'      => array( 'min' => 2, 'max' => 20, 'step' => 1 ),
					),
					'peek_pause_hover' => array(
						'type'    => 'select',
						'label'   => __( 'Pause on Hover', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'help'    => __( 'Keyboard focus inside the slider always pauses it, so a tabbing visitor is never moved mid-read.', 'ds-toolkit' ),
					),
					'peek_resume' => array(
						'type'    => 'select',
						'label'   => __( 'After a Manual Move', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array(
							'yes' => __( 'Resume autoplay', 'ds-toolkit' ),
							'no'  => __( 'Stop autoplay', 'ds-toolkit' ),
						),
					),
					'peek_playpause' => array(
						'type'    => 'select',
						'label'   => __( 'Pause / Play Button', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
						'help'    => __( 'Recommended: an autoplaying carousel should give visitors a way to stop it.', 'ds-toolkit' ),
					),
					'peek_speed' => array(
						'type'        => 'unit',
						'label'       => __( 'Transition Speed', 'ds-toolkit' ),
						'default'     => '525',
						'description' => 'ms',
						'slider'      => array( 'min' => 150, 'max' => 1500, 'step' => 25 ),
					),
					'peek_ease' => array(
						'type'    => 'select',
						'label'   => __( 'Slide Motion', 'ds-toolkit' ),
						'default' => 'settle',
						'options' => array(
							'settle' => __( 'Settle (recommended)', 'ds-toolkit' ),
							'snap'   => __( 'Snap (faster, sharper)', 'ds-toolkit' ),
							'smooth' => __( 'Smooth (even glide)', 'ds-toolkit' ),
						),
						'help'    => __( 'Settle moves most of the way quickly then eases the last stretch in, so the photo appears to settle into place. Smooth glides at an even rate.', 'ds-toolkit' ),
					),
					'peek_loop' => array(
						'type'    => 'select',
						'label'   => __( 'Infinite Loop', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Enabled', 'ds-toolkit' ), 'no' => __( 'Disabled', 'ds-toolkit' ) ),
					),
					'peek_drag' => array(
						'type'    => 'select',
						'label'   => __( 'Drag / Swipe', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Enabled', 'ds-toolkit' ), 'no' => __( 'Disabled', 'ds-toolkit' ) ),
						'help'    => __( 'Mouse dragging and touch swiping. Vertical swipes still scroll the page.', 'ds-toolkit' ),
					),
					'peek_arrows' => array(
						'type'    => 'select',
						'label'   => __( 'Navigation Arrows', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
						'help'    => __( 'Hidden on phones either way: they cost more width than they earn.', 'ds-toolkit' ),
					),
					'peek_dots' => array(
						'type'    => 'select',
						'label'   => __( 'Pagination Dots', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'layout' => array(
				'title'       => __( 'Layout', 'ds-toolkit' ),
				'fields'      => array(
					'min_height'    => array( 'type' => 'unit', 'label' => __( 'Min Height', 'ds-toolkit' ), 'default' => '92', 'description' => 'vh', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'content_width' => array( 'type' => 'unit', 'label' => __( 'Content Width', 'ds-toolkit' ), 'default' => '760', 'description' => 'px', 'slider' => array( 'min' => 300, 'max' => 1920, 'step' => 10 ), 'help' => __( 'Max width of the text column (eyebrow, headline, subtext, buttons, stats). Going wider than the Container Width below now widens the container to match, so this value is always honoured.', 'ds-toolkit' ) ),
					'align'         => array(
						'type'       => 'select',
						'label'      => __( 'Alignment', 'ds-toolkit' ),
						'default'    => 'left',
						'responsive' => true,
						'options'    => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ) ),
					),
				),
			),
			'banner_design' => array(
				'title'       => __( 'Layout', 'ds-toolkit' ),
				'description' => __( 'The banner is taller when a page has a photo or video, and shorter when it has none.', 'ds-toolkit' ),
				'fields'      => array(
					'banner_align'       => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'center', 'responsive' => true, 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
					'banner_valign'      => array(
						'type'    => 'select',
						'label'   => __( 'Title Alignment', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array(
							'top'    => __( 'Top', 'ds-toolkit' ),
							'center' => __( 'Center (default)', 'ds-toolkit' ),
							'bottom' => __( 'Bottom', 'ds-toolkit' ),
						),
						'help'    => __( 'Where the title block sits vertically inside the banner. Bottom keeps a photo\'s subject clear — faces in a team photo stay uncovered. Only the title placement moves; the photo, overlay and typography are untouched.', 'ds-toolkit' ),
					),
					'banner_h_bg'   => array( 'type' => 'unit', 'label' => __( 'Height with Photo / Video', 'ds-toolkit' ), 'default' => '52', 'description' => 'vh', 'responsive' => true, 'slider' => array( 'min' => 20, 'max' => 100, 'step' => 1 ) ),
					'banner_h_nobg' => array( 'type' => 'unit', 'label' => __( 'Height without Photo / Video', 'ds-toolkit' ), 'default' => '28', 'description' => 'vh', 'responsive' => true, 'slider' => array( 'min' => 12, 'max' => 80, 'step' => 1 ) ),
					'banner_parallax' => array(
						'type'    => 'select',
						'label'   => __( 'Parallax', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes (photo scrolls slower)', 'ds-toolkit' ) ),
						'help'    => __( 'Background photo moves slower than the page on scroll. Photo banners only; disabled for reduced-motion visitors.', 'ds-toolkit' ),
					),
				),
			),
			'banner_shape' => array(
				'title'       => __( 'Shape & Accent Line', 'ds-toolkit' ),
				'fields'      => array(
					'banner_radius'         => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 80, 'step' => 1 ), 'help' => __( 'Rounds the banner. Blank = square. Use the device icon for tablet and phone values.', 'ds-toolkit' ) ),
					'banner_radius_corners' => array( 'type' => 'select', 'label' => __( 'Rounded Corners', 'ds-toolkit' ), 'default' => 'bottom', 'options' => array( 'bottom' => __( 'Bottom corners', 'ds-toolkit' ), 'all' => __( 'All corners', 'ds-toolkit' ) ) ),
					'banner_line_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent Line Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'A line along the banner\'s lower edge that follows its rounded corners. Blank = no line.', 'ds-toolkit' ) ),
					'banner_line_width'     => array( 'type' => 'unit', 'label' => __( 'Accent Line Thickness', 'ds-toolkit' ), 'default' => '6', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 20, 'step' => 1 ) ),
				),
			),
			'peek_layout' => array(
				'title'       => __( 'Slide Layout', 'ds-toolkit' ),
				'fields'      => array(
					'peek_height' => array(
						'type'        => 'unit',
						'label'       => __( 'Slide Height', 'ds-toolkit' ),
						'default'     => '640',
						'description' => 'px',
						'responsive'  => true,
						'slider'      => array( 'min' => 240, 'max' => 900, 'step' => 10 ),
					),
					'peek_width' => array(
						'type'        => 'unit',
						'label'       => __( 'Slide Width', 'ds-toolkit' ),
						'default'     => '93',
						'description' => '%',
						'responsive'  => true,
						'slider'      => array( 'min' => 50, 'max' => 100, 'step' => 1 ),
						'help'        => __( 'Under 100% is what makes the neighbouring slides peek in at the edges.', 'ds-toolkit' ),
					),
					'peek_gap' => array(
						'type'        => 'unit',
						'label'       => __( 'Gap Between Slides', 'ds-toolkit' ),
						'default'     => '30',
						'description' => 'px',
						'responsive'  => true,
						'slider'      => array( 'min' => 0, 'max' => 80, 'step' => 2 ),
					),
					'peek_radius' => array(
						'type'        => 'unit',
						'label'       => __( 'Border Radius', 'ds-toolkit' ),
						'default'     => '16',
						'description' => 'px',
						'responsive'  => true,
						'slider'      => array( 'min' => 0, 'max' => 60, 'step' => 1 ),
					),
					'peek_pad' => array(
						'type'        => 'unit',
						'label'       => __( 'Content Padding', 'ds-toolkit' ),
						'default'     => '48',
						'description' => 'px',
						'responsive'  => true,
						'slider'      => array( 'min' => 8, 'max' => 120, 'step' => 2 ),
					),
					'peek_content_width' => array(
						'type'        => 'unit',
						'label'       => __( 'Heading Maximum Width', 'ds-toolkit' ),
						'default'     => '640',
						'description' => 'px',
						'responsive'  => true,
						'slider'      => array( 'min' => 200, 'max' => 1200, 'step' => 10 ),
					),
					'peek_content_v' => array(
						'type'    => 'select',
						'label'   => __( 'Vertical Content Position', 'ds-toolkit' ),
						'default' => 'bottom',
						'options' => array(
							'top'    => __( 'Top', 'ds-toolkit' ),
							'center' => __( 'Middle', 'ds-toolkit' ),
							'bottom' => __( 'Bottom', 'ds-toolkit' ),
						),
					),
					'peek_content_align' => array(
						'type'    => 'select',
						'label'   => __( 'Heading & CTA Alignment', 'ds-toolkit' ),
						'default' => 'split',
						'options' => array(
							'split'  => __( 'Split: heading left, button right', 'ds-toolkit' ),
							'left'   => __( 'Stacked left', 'ds-toolkit' ),
							'center' => __( 'Stacked centre', 'ds-toolkit' ),
							'right'  => __( 'Stacked right', 'ds-toolkit' ),
						),
						'help'    => __( 'Split stacks automatically on phones so nothing is squeezed.', 'ds-toolkit' ),
					),
				),
			),
			'overlay' => array(
				'title'       => __( 'Overlay', 'ds-toolkit' ),
				'fields'      => array(
					'overlay_style' => array(
						'type'    => 'select',
						'label'   => __( 'Overlay', 'ds-toolkit' ),
						'default' => 'gradient',
						'options' => array(
							'none'     => __( 'None', 'ds-toolkit' ),
							'solid'    => __( 'Solid Colour', 'ds-toolkit' ),
							'gradient' => __( 'Gradient', 'ds-toolkit' ),
							'multiple' => __( 'Multiple Backgrounds', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'solid'    => array( 'fields' => array( 'overlay_color', 'overlay_opacity' ) ),
							'gradient' => array( 'fields' => array( 'grad_color1', 'grad_opacity1', 'grad_color2', 'grad_opacity2', 'grad_angle' ) ),
							'multiple' => array( 'fields' => array( 'overlay_bg' ) ),
						),
					),
					'overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'overlay_opacity' => array( 'type' => 'unit', 'label' => __( 'Opacity', 'ds-toolkit' ), 'default' => '70', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'grad_color1'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient Start', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'grad_opacity1' => array( 'type' => 'unit', 'label' => __( 'Start Opacity', 'ds-toolkit' ), 'default' => '92', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'grad_color2'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient End', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'grad_opacity2' => array( 'type' => 'unit', 'label' => __( 'End Opacity', 'ds-toolkit' ), 'default' => '35', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'grad_angle'    => array( 'type' => 'unit', 'label' => __( 'Gradient Angle', 'ds-toolkit' ), 'default' => '105', 'description' => 'deg', 'slider' => array( 'min' => 0, 'max' => 360, 'step' => 1 ) ),
					// Multiple Backgrounds — Beaver Builder's native layered "background" control.
					// Stack colours / gradients / images; BB's auto-css engine composites them into
					// one `background:` shorthand (persisted under ->css) and emits it onto
					// .ds-hero-overlay automatically. The preview `enabled` guard scopes that emission
					// to overlay_style = 'multiple', so switching styles never leaves a stray paint.
					'overlay_bg' => array(
						'type'       => 'background',
						'label'      => __( 'Background Layers', 'ds-toolkit' ),
						'responsive' => array(
							'default' => array(
								'default' => array(
									array(
										'id'    => 1,
										'type'  => 'color',
										'state' => array( 'color' => 'rgba(10, 14, 21, 0.45)' ),
									),
								),
							),
						),
						'preview'    => array(
							'type'      => 'css',
							'auto'      => true,
							'selector'  => '.ds-hero-overlay',
							'property'  => 'background',
							'sub_value' => array( 'setting_name' => 'css' ),
							'enabled'   => array( 'overlay_style' => 'multiple' ),
						),
						'help'       => __( 'Stack multiple background layers (colours, gradients, images) like a Beaver Builder row. They composite top to bottom into the hero overlay.', 'ds-toolkit' ),
					),
				),
			),
			'peek_overlay' => array(
				'title'       => __( 'Slide Overlay', 'ds-toolkit' ),
				'fields'      => array(
					'peek_ov_type' => array(
						'type'    => 'select',
						'label'   => __( 'Overlay Type', 'ds-toolkit' ),
						'default' => 'gradient',
						'options' => array(
							'none'     => __( 'None', 'ds-toolkit' ),
							'solid'    => __( 'Solid Colour', 'ds-toolkit' ),
							'gradient' => __( 'Gradient', 'ds-toolkit' ),
						),
						'help'    => __( 'The default gradient is light at the top and dark near the bottom, so a heading placed low stays readable.', 'ds-toolkit' ),
						'toggle'  => array(
							'solid'    => array( 'fields' => array( 'peek_ov_color', 'peek_ov_opacity', 'peek_ov_blend' ) ),
							'gradient' => array( 'fields' => array( 'peek_ov_color', 'peek_ov_color2', 'peek_ov_dir', 'peek_ov_opacity', 'peek_ov_blend' ) ),
						),
					),
					'peek_ov_color' => array(
						'type'        => 'color',
						'label'       => __( 'Overlay Colour', 'ds-toolkit' ),
						'default'     => '',
						'show_reset'  => true,
						'show_alpha'  => true,
						'connections' => array( 'color' ),
						'help'        => __( 'Gradient start. Blank keeps the designed default.', 'ds-toolkit' ),
					),
					'peek_ov_color2' => array(
						'type'        => 'color',
						'label'       => __( 'Overlay Colour 2', 'ds-toolkit' ),
						'default'     => '',
						'show_reset'  => true,
						'show_alpha'  => true,
						'connections' => array( 'color' ),
						'help'        => __( 'Gradient end.', 'ds-toolkit' ),
					),
					'peek_ov_dir' => array(
						'type'    => 'select',
						'label'   => __( 'Gradient Direction', 'ds-toolkit' ),
						'default' => 'to bottom',
						'options' => array(
							'to bottom'       => __( 'Top to bottom', 'ds-toolkit' ),
							'to top'          => __( 'Bottom to top', 'ds-toolkit' ),
							'to right'        => __( 'Left to right', 'ds-toolkit' ),
							'to left'         => __( 'Right to left', 'ds-toolkit' ),
							'to bottom right' => __( 'Diagonal ↘', 'ds-toolkit' ),
							'to bottom left'  => __( 'Diagonal ↙', 'ds-toolkit' ),
						),
					),
					'peek_ov_opacity' => array(
						'type'        => 'unit',
						'label'       => __( 'Overlay Opacity', 'ds-toolkit' ),
						'default'     => '100',
						'description' => '%',
						'slider'      => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
					),
					'peek_ov_blend' => array(
						'type'    => 'select',
						'label'   => __( 'Overlay Blend Mode', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => DS_Hero_Module::blend_modes(),
					),
				),
			),
			'banner_scrim' => array(
				'title'       => __( 'Image Scrim', 'ds-toolkit' ),
				'description' => __( 'A designed darkening over the banner photo so the heading stays readable (Page Banner with an image).', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'scrim_preset' => array(
						'type'    => 'select',
						'label'   => __( 'Scrim', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array(
							'none'     => __( 'None', 'ds-toolkit' ),
							'bottom'   => __( 'Bottom fade', 'ds-toolkit' ),
							'top'      => __( 'Top fade', 'ds-toolkit' ),
							'vignette' => __( 'Vignette (edges)', 'ds-toolkit' ),
							'full'     => __( 'Full (even darken)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'bottom'   => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
							'top'      => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
							'vignette' => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
							'full'     => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
						),
					),
					'scrim_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Scrim Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'scrim_strength' => array( 'type' => 'unit', 'label' => __( 'Strength', 'ds-toolkit' ), 'default' => '55', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
				),
			),
			'colors' => array(
				'title'       => __( 'Colours', 'ds-toolkit' ),
				'fields'      => array(
					'eyebrow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'title_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Headline', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'accent_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent Word', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'help' => __( 'Colour of {a}…{/a} text and the slideshow indicators, and of the primary button when Button Style is Accent colour.', 'ds-toolkit' ) ),
					'sub_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Subtext', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'stat_num_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Stat Number', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'stat_label_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Stat Label', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
				),
			),
			'banner_colors' => array(
				'title'       => __( 'Colours', 'ds-toolkit' ),
				'fields'      => array(
					'banner_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = automatic: light on a photo, dark on a plain background.', 'ds-toolkit' ) , 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero--banner .ds-hero-title', 'property' => 'color' ) ),
					'banner_sub_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Subtitle Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true , 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero--banner .ds-hero-sub', 'property' => 'color' ) ),
					'breadcrumbs_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Breadcrumbs Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = automatic (light on a photo, muted on a plain background).', 'ds-toolkit' ) , 'preview' => array( 'type' => 'css', 'selector' => '.ds-banner-crumbs, .ds-banner-crumbs a, .ds-banner-crumbs .breadcrumb_last', 'property' => 'color' ) ),
					'breadcrumbs_pill_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Breadcrumbs Pill / Tab Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank = frosted glass (white at 10%) for the pill, the Dark Background colour for the tab.', 'ds-toolkit' ) ),
					'breadcrumbs_edge_lift'   => array( 'type' => 'unit', 'label' => __( 'Breadcrumbs Lift', 'ds-toolkit' ), 'default' => '30', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 120, 'step' => 1 ), 'help' => __( 'On the bottom edge. Tab: how far it rises over the banner\'s lower edge (0 hangs it just below). Plain and Pill: how far above the edge they sit, always on the banner.', 'ds-toolkit' ) ),
					'breadcrumbs_pill_border' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Breadcrumbs Pill Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank = white at 28%.', 'ds-toolkit' ) ),
				),
			),
			'peek_text' => array(
				'title'       => __( 'Slide Heading', 'ds-toolkit' ),
				'fields'      => array(
					'peek_title_color' => array(
						'type'        => 'color',
						'label'       => __( 'Heading Colour', 'ds-toolkit' ),
						'default'     => '',
						'show_reset'  => true,
						'show_alpha'  => true,
						'connections' => array( 'color' ),
						'help'        => __( 'Blank = white, which is what reads on a photo under the default overlay.', 'ds-toolkit' ),
						'preview'     => array( 'type' => 'css', 'selector' => '.ds-peek-title', 'property' => 'color' ),
					),
					'peek_title_typography' => array(
						'type'       => 'typography',
						'label'      => __( 'Heading Typography', 'ds-toolkit' ),
						'responsive' => true,
						'preview'    => array( 'type' => 'css', 'selector' => '.ds-peek-title' ),
						'help'       => __( 'Font, size, line height, letter spacing and text transform, per breakpoint.', 'ds-toolkit' ),
					),
				),
			),
			'typography' => array(
				'title'       => __( 'Typography', 'ds-toolkit' ),
				'fields'      => array(
					'title_typography' => array( 'type' => 'typography', 'label' => __( 'Headline', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero-title' ) ),
					'sub_typography'   => array( 'type' => 'typography', 'label' => __( 'Subtext', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero-sub' ) ),
					'eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero-eyebrow' ) ),
					'breadcrumbs_typography' => array( 'type' => 'typography', 'label' => __( 'Breadcrumbs Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-banner-crumbs' ) ),
				),
			),
			'buttons_style' => array(
				'title'       => __( 'Buttons', 'ds-toolkit' ),
				'fields'      => array(
					'btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array(
							'yes' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ),
							'no'  => __( 'Accent colour', 'ds-toolkit' ),
						),
						'help'    => __( 'Primary buttons inherit the global Button style (background, hover, radius, typography) from Theme Setting → Elements → Button by default. Both buttons pick up its corner radius and typography.', 'ds-toolkit' ),
					),
					'btn_align' => array(
						'type'       => 'select',
						'label'      => __( 'Buttons Alignment', 'ds-toolkit' ),
						'default'    => '',
						'responsive' => true,
						'options'    => array(
							''       => __( 'Follow Alignment (Style → Layout)', 'ds-toolkit' ),
							'left'   => __( 'Left', 'ds-toolkit' ),
							'center' => __( 'Center', 'ds-toolkit' ),
							'right'  => __( 'Right', 'ds-toolkit' ),
						),
						'help'       => __( 'Horizontal position of the button group only; text and stats keep following the module Alignment. Blank = follow Alignment; use the device icon for per-device values. (On phones the buttons go full-width by design, so alignment has no visible effect there.)', 'ds-toolkit' ),
						'preview'    => array( 'type' => 'css', 'selector' => '.ds-hero-cta', 'property' => 'justify-content' ),
					),
				),
			),
			'peek_cta' => array(
				'title'       => __( 'Slide Button', 'ds-toolkit' ),
				'fields'      => array(
					'peek_btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array(
							'yes' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ),
							'no'  => __( 'Custom (below)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'no' => array( 'fields' => array( 'peek_btn_bg', 'peek_btn_color', 'peek_btn_border', 'peek_btn_hover_bg', 'peek_btn_hover_color' ) ),
						),
					),
					'peek_btn_size' => array(
						'type'    => 'select',
						'label'   => __( 'Button Size', 'ds-toolkit' ),
						'default' => 'medium',
						'options' => array(
							'small'  => __( 'Small', 'ds-toolkit' ),
							'medium' => __( 'Medium', 'ds-toolkit' ),
							'large'  => __( 'Large', 'ds-toolkit' ),
						),
					),
					'peek_btn_radius' => array(
						'type'        => 'unit',
						'label'       => __( 'Button Border Radius', 'ds-toolkit' ),
						'default'     => '',
						'description' => 'px',
						'slider'      => array( 'min' => 0, 'max' => 60, 'step' => 1 ),
						'help'        => __( 'Blank follows the site button.', 'ds-toolkit' ),
					),
					'peek_btn_bg'          => array( 'type' => 'color', 'label' => __( 'Background Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'connections' => array( 'color' ) ),
					'peek_btn_color'       => array( 'type' => 'color', 'label' => __( 'Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ),
					'peek_btn_border'      => array( 'type' => 'color', 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ),
					'peek_btn_hover_bg'    => array( 'type' => 'color', 'label' => __( 'Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'connections' => array( 'color' ) ),
					'peek_btn_hover_color' => array( 'type' => 'color', 'label' => __( 'Hover Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ),
					'peek_btn_typography'  => array( 'type' => 'typography', 'label' => __( 'Button Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-peek-cta' ) ),
				),
			),
			'banner_nobg' => array(
				'title'       => __( 'No-Image Background', 'ds-toolkit' ),
				'description' => __( 'Used only when a page has no banner photo or video. Give plain pages a brand colour and/or a subtle, tileable pattern.', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'nobg_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the site default from Theme Setting (General > Page Banner). Set a colour to override it for this page.', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-banner--no-bg', 'property' => 'background-color' ) ),
					'nobg_pattern' => array( 'type' => 'photo', 'label' => __( 'Pattern / Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Optional. A small, tileable pattern (PNG/SVG) looks best with Repeat = Tile.', 'ds-toolkit' ) ),
					'nobg_repeat'  => array( 'type' => 'select', 'label' => __( 'Repeat', 'ds-toolkit' ), 'default' => 'repeat', 'options' => array( 'repeat' => __( 'Tile', 'ds-toolkit' ), 'repeat-x' => __( 'Tile horizontally', 'ds-toolkit' ), 'repeat-y' => __( 'Tile vertically', 'ds-toolkit' ), 'no-repeat' => __( 'No repeat', 'ds-toolkit' ) ) ),
					'nobg_size'    => array( 'type' => 'select', 'label' => __( 'Size', 'ds-toolkit' ), 'default' => 'auto', 'options' => array( 'auto' => __( 'Auto (real size, best for patterns)', 'ds-toolkit' ), 'contain' => __( 'Contain', 'ds-toolkit' ), 'cover' => __( 'Cover (fill)', 'ds-toolkit' ) ) ),
					'nobg_blend'   => array( 'type' => 'select', 'label' => __( 'Blend with Colour', 'ds-toolkit' ), 'default' => 'normal', 'options' => array( 'normal' => __( 'Normal (pattern over colour)', 'ds-toolkit' ), 'multiply' => __( 'Multiply', 'ds-toolkit' ), 'overlay' => __( 'Overlay', 'ds-toolkit' ), 'screen' => __( 'Screen', 'ds-toolkit' ), 'soft-light' => __( 'Soft Light', 'ds-toolkit' ) ), 'help' => __( 'How the pattern mixes with the Background Colour beneath it. Multiply/Overlay tint the pattern with the colour.', 'ds-toolkit' ) ),
				),
			),
			'peek_bg' => array(
				'title'       => __( 'Slide Image', 'ds-toolkit' ),
				'description' => __( 'Stacked bottom to top: base colour, slide image, pattern, overlay, then the heading and CTA. Each slide sets its own image and focal point; these settings style every slide the same way.', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'peek_base_color' => array(
						'type'        => 'color',
						'label'       => __( 'Base Background Colour', 'ds-toolkit' ),
						'default'     => '',
						'show_reset'  => true,
						'show_alpha'  => true,
						'connections' => array( 'color' ),
						'help'        => __( 'Sits under the image and shows through a transparent PNG or a reduced image opacity.', 'ds-toolkit' ),
					),
					'peek_img_size' => array(
						'type'    => 'select',
						'label'   => __( 'Image Size', 'ds-toolkit' ),
						'default' => 'cover',
						'options' => array(
							'cover'   => __( 'Cover (fill the slide)', 'ds-toolkit' ),
							'contain' => __( 'Contain (fit inside)', 'ds-toolkit' ),
						),
					),
					'peek_img_opacity' => array(
						'type'        => 'unit',
						'label'       => __( 'Image Opacity', 'ds-toolkit' ),
						'default'     => '100',
						'description' => '%',
						'slider'      => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
					),
					'peek_img_blend' => array(
						'type'    => 'select',
						'label'   => __( 'Image Blend Mode', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => DS_Hero_Module::blend_modes(),
					),
				),
			),
			'peek_pattern_sec' => array(
				'title'       => __( 'Slide Pattern', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'peek_pattern' => array(
						'type'        => 'photo',
						'label'       => __( 'Pattern / Texture', 'ds-toolkit' ),
						'show_remove' => true,
						'connections' => array( 'photo' ),
						'help'        => __( 'Optional tileable image laid over the photo.', 'ds-toolkit' ),
					),
					'peek_pattern_size' => array(
						'type'    => 'select',
						'label'   => __( 'Pattern Size', 'ds-toolkit' ),
						'default' => 'auto',
						'options' => array(
							'auto'    => __( 'Original', 'ds-toolkit' ),
							'cover'   => __( 'Cover', 'ds-toolkit' ),
							'contain' => __( 'Contain', 'ds-toolkit' ),
						),
					),
					'peek_pattern_repeat' => array(
						'type'    => 'select',
						'label'   => __( 'Pattern Repeat', 'ds-toolkit' ),
						'default' => 'repeat',
						'options' => array(
							'repeat'    => __( 'Tile', 'ds-toolkit' ),
							'repeat-x'  => __( 'Tile horizontally', 'ds-toolkit' ),
							'repeat-y'  => __( 'Tile vertically', 'ds-toolkit' ),
							'no-repeat' => __( 'No repeat', 'ds-toolkit' ),
						),
					),
					'peek_pattern_pos' => array(
						'type'    => 'select',
						'label'   => __( 'Pattern Position', 'ds-toolkit' ),
						'default' => 'center center',
						'options' => array(
							'left top'      => __( 'Top Left', 'ds-toolkit' ),
							'center top'    => __( 'Top Centre', 'ds-toolkit' ),
							'right top'     => __( 'Top Right', 'ds-toolkit' ),
							'left center'   => __( 'Middle Left', 'ds-toolkit' ),
							'center center' => __( 'Centre', 'ds-toolkit' ),
							'right center'  => __( 'Middle Right', 'ds-toolkit' ),
							'left bottom'   => __( 'Bottom Left', 'ds-toolkit' ),
							'center bottom' => __( 'Bottom Centre', 'ds-toolkit' ),
							'right bottom'  => __( 'Bottom Right', 'ds-toolkit' ),
						),
					),
					'peek_pattern_opacity' => array(
						'type'        => 'unit',
						'label'       => __( 'Pattern Opacity', 'ds-toolkit' ),
						'default'     => '100',
						'description' => '%',
						'slider'      => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
					),
					'peek_pattern_blend' => array(
						'type'    => 'select',
						'label'   => __( 'Pattern Blend Mode', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => DS_Hero_Module::blend_modes(),
					),
				),
			),
			'effects' => array(
				'title'       => __( 'Gradient & Outline Text', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'gradient_start_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient Start Colour {g}…{/g}', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Wrap headline text in {g}…{/g} to apply a left-to-right gradient. Set both gradient colours to control it; a blank value falls back to the Accent Word colour.', 'ds-toolkit' ) ),
					'gradient_end_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient End Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'outline_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Outline Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Stroke colour for {outline}…{/outline} text in this module. Blank = the Theme Setting default.', 'ds-toolkit' ) ),
					'outline_width' => array( 'type' => 'unit', 'label' => __( 'Outline Text Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = the Theme Setting default.', 'ds-toolkit' ), 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
				),
			),
			'spacing' => array(
				'title'       => __( 'Spacing', 'ds-toolkit' ),
				'collapsed'   => true,
				'fields'      => array(
					'container_width'   => array(
						'type'    => 'select',
						'label'   => __( 'Container Width', 'ds-toolkit' ),
						'default' => 'boxed',
						'options' => array(
							'boxed'  => __( 'Boxed (max 1280px)', 'ds-toolkit' ),
							'full'   => __( 'Full width (fill container)', 'ds-toolkit' ),
							'custom' => __( 'Custom max-width', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'container_max_width' ) ) ),
						'help'    => __( 'Full width lets this fill a full-width row/column; use the row/column padding for side spacing.', 'ds-toolkit' ),
					),
					'container_max_width' => array( 'type' => 'unit', 'label' => __( 'Container Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 480, 'max' => 1920, 'step' => 10 ) ),
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
) );
