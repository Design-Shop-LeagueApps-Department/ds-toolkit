<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DS_CTA_Module: its sub-forms and its settings form (every tab, section and field), and the call that
 * registers the module with them.
 *
 * Required at the end of ds-cta.php, in the same scope. Moved out of that file as it was in the 2026-11
 * audit, so the class (what the block does) and the form (what an editor can set) can be read apart.
 */

/* Style 6 per-card sub-form. Owned by the shared class so this module never
   depends on the Post Loop module being enabled. */
DS_Program_Cards::register_form();

/* ----------------------------------------------------------- Card sub-form */
FLBuilder::register_settings_form( 'ds_cta_card_form', array(
	'title' => __( 'Card', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Card', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'image'   => array( 'type' => 'photo', 'label' => __( 'Background Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ) ),
						'logo'    => array( 'type' => 'photo', 'label' => __( 'Logo / Badge (Style 5)', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Optional secondary image overlaid on the card — a club logo, badge, or graphic. Position and animation are set in the module\'s Logo Overlay section.', 'ds-toolkit' ) ),
						'eyebrow' => array( 'type' => 'text', 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'title'     => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'link'      => array( 'type' => 'link', 'label' => __( 'Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ) ),
						'link_text' => array( 'type' => 'text', 'label' => __( 'Link Text', 'ds-toolkit' ), 'default' => 'Learn More', 'connections' => array( 'string' ), 'help' => __( 'Style 2 button / link text; Style 3 text-cell button. (Style 1 uses a chevron only.)', 'ds-toolkit' ) ),
					),
				),
				'bento' => array(
					'title'  => __( 'Bento (Style 3)', 'ds-toolkit' ),
					'fields' => array(
						'type'      => array(
							'type'    => 'select',
							'label'   => __( 'Cell Type', 'ds-toolkit' ),
							'default' => 'image',
							'options' => array( 'image' => __( 'Image', 'ds-toolkit' ), 'text' => __( 'Text / CTA', 'ds-toolkit' ) ),
							// 'desc' is no longer toggled off for image cells: they render
							// Title / Description / Link Text now too (GH #123).
							'toggle'  => array( 'image' => array( 'fields' => array( 'cell_overlay' ) ) ),
							'help'    => __( 'Bento (Style 3) only. Image cells sit on the Background Image with an overlay; Text cells sit on the Treatment background. Both render Eyebrow, Title, Description and the Link Text button.', 'ds-toolkit' ),
						),
						'desc'      => array( 'type' => 'editor', 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'label' => __( 'Description', 'ds-toolkit' ) ),
						'number'    => array( 'type' => 'text', 'label' => __( 'Number', 'ds-toolkit' ), 'connections' => array( 'string' ), 'help' => __( 'Optional index shown above the eyebrow, e.g. 01. Free text, so 01 / A / 1 of 4 all work.', 'ds-toolkit' ) ),
						'badge'     => array( 'type' => 'text', 'label' => __( 'Badge', 'ds-toolkit' ), 'connections' => array( 'string' ), 'help' => __( 'Optional pill label, e.g. NEW or SOLD OUT.', 'ds-toolkit' ) ),
						'treatment' => array(
							'type'    => 'select',
							'label'   => __( 'Treatment', 'ds-toolkit' ),
							'default' => 'auto',
							'options' => array(
								'auto'   => __( 'Automatic (module colours)', 'ds-toolkit' ),
								'dark'   => __( 'Dark background, light text', 'ds-toolkit' ),
								'light'  => __( 'Light background, dark text', 'ds-toolkit' ),
								'accent' => __( 'Brand accent, light text', 'ds-toolkit' ),
							),
							'help'    => __( 'Lets one grid mix an image card, a light card and a brand-colour feature card without needing separate modules. Automatic keeps the module-wide colours, so existing layouts are unchanged.', 'ds-toolkit' ),
						),
						'cell_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Overrides the Treatment background for this card only. On an image card it shows wherever the image does not cover.', 'ds-toolkit' ) ),
						'cell_text' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Overrides the Treatment text colour for this card only. Set this if you pick a Background Colour the Treatment was not designed for.', 'ds-toolkit' ) ),
						'cell_overlay' => array( 'type' => 'unit', 'label' => __( 'Image Overlay', 'ds-toolkit' ), 'default' => '', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 5 ), 'help' => __( 'Darkens the image so text stays readable. Blank is automatic: none for a card with only an eyebrow pill, 45% once it carries a title or description.', 'ds-toolkit' ) ),
						'col_span'  => array( 'type' => 'unit', 'label' => __( 'Column Span', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 1, 'max' => 4, 'step' => 1 ), 'help' => __( 'How many grid columns this card occupies. Combine with Row Span for a 2x2 feature card, a 2x1 wide card, or a 1x2 tall card. Every card drops to 1x1 below the tablet breakpoint so nothing overflows on a phone.', 'ds-toolkit' ) ),
						'row_span'  => array( 'type' => 'unit', 'label' => __( 'Row Span', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 1, 'max' => 3, 'step' => 1 ) ),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_CTA_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'cta_style_sec' => array(
				'title'  => __( 'CTA Style', 'ds-toolkit' ),
				'fields' => array(
					'cta_style' => array(
						'type'    => 'select',
						'label'   => __( 'CTA Style', 'ds-toolkit' ),
						'default' => 'style1',
						'options' => DS_CTA_Module::styles(),
						'help'    => __( 'Choose a CTA layout. More styles will be added; the options below adapt to the selected style.', 'ds-toolkit' ),
						'toggle'  => array(
							'style1' => array(
								'sections' => array( 'section_style', 'header', 'header_colors', 'cards', 'grid', 'clip', 'overlay', 'colors', 'typography', 'spacing' ),
							),
							'style2' => array(
								'sections' => array( 'section_style', 'header', 'header_colors', 'cards', 'grid', 'card2', 'overlay', 'colors', 'typography', 'spacing' ),
							),
							'style3' => array(
								'sections' => array( 'section_style', 'header', 'header_colors', 'cards', 'grid', 'bento', 'colors', 'typography', 'spacing' ),
							),
							'style4' => array(
								'sections' => array( 'hero', 'hero_contact', 'hero_button', 'hero_social', 'hero_bg', 'hero_box', 'hero_colors', 'hero_social_style', 'hero_typography', 'spacing' ),
							),
							'style5' => array(
								'sections' => array( 'section_style', 'header', 'header_colors', 'cards', 'mcard', 'mcard_fx', 'mcard_logo', 'typography', 'spacing' ),
							),
							'style6' => array(
								'sections' => array( 'section_style', 'header', 'header_colors', 'programs_sec', 'program_opts', 'program_chrome', 'spacing' ),
							),
							'style7' => array(
								'sections' => array( 'section_style', 'header', 'header_colors', 'programs_sec', 'pp_layout', 'pp_number', 'pp_text', 'spacing' ),
							),
						),
					),
				),
			),
			'cards' => array(
				'title'  => __( 'Cards', 'ds-toolkit' ),
				'fields' => array(
					'cards' => array(
						'type'         => 'form',
						'label'        => __( 'Card', 'ds-toolkit' ),
						'form'         => 'ds_cta_card_form',
						'preview_text' => 'title',
						'multiple'     => true,
						'default'      => array(
							array( 'eyebrow' => '', 'title' => '' ),
							array( 'eyebrow' => '', 'title' => '' ),
							array( 'eyebrow' => '', 'title' => '' ),
							array( 'eyebrow' => '', 'title' => '' ),
						),
					),
				),
			),
			// ---- Style 4 only: Big Hero content ----
			'hero' => array(
				'title'  => __( 'Hero Content', 'ds-toolkit' ),
				'fields' => array(
					'hero_show_eyebrow' => array(
						'type'    => 'select',
						'label'   => __( 'Show Sub-heading', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'hero_eyebrow', 'hero_eyebrow_line' ) ) ),
					),
					'hero_eyebrow'      => array( 'type' => 'text', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'default' => 'Get In Touch', 'connections' => array( 'string' ) ),
					'hero_eyebrow_line' => array(
						'type'    => 'select',
						'label'   => __( 'Sub-heading Accent Line', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
						'help'    => __( 'Small accent dash beside the sub-heading.', 'ds-toolkit' ),
					),
					'hero_heading'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'ds-toolkit' ),
						'rows'    => 2,
						'default' => 'Questions? {a}Contact Us.{/a}',
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the accent. Line breaks are kept. {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'hero_align'        => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ),
					),
					'hero_box'          => array(
						'type'    => 'select',
						'label'   => __( 'Framed Box', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes — bordered panel', 'ds-toolkit' ), 'no' => __( 'No — plain', 'ds-toolkit' ) ),
						'help'    => __( 'Wrap the content in a bordered panel (with an optional top accent bar). Style it under “Hero Box”.', 'ds-toolkit' ),
					),
				),
			),
			'hero_contact' => array(
				'title'  => __( 'Contact Details', 'ds-toolkit' ),
				'fields' => array(
					'show_location' => array( 'type' => 'select', 'label' => __( 'Show Location', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'show_email'    => array( 'type' => 'select', 'label' => __( 'Show Email', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'show_phone'    => array( 'type' => 'select', 'label' => __( 'Show Phone', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'contact_icons' => array( 'type' => 'select', 'label' => __( 'Show Icons', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Location, Email and Phone pull from Partner Settings (Address / Email / Phone). Empty values are skipped automatically.', 'ds-toolkit' ) ),
				),
			),
			'hero_button' => array(
				'title'  => __( 'Button', 'ds-toolkit' ),
				'fields' => array(
					'hero_btn_show'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Button', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'hero_btn_text', 'hero_btn_link', 'hero_btn_arrow', 'hero_btn_global' ) ) ),
					),
					'hero_btn_text'   => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => 'Express Tryout Interest', 'connections' => array( 'string' ) ),
					'hero_btn_link'   => array( 'type' => 'link', 'label' => __( 'Button Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ), 'help' => __( 'Tip: connect to a Partner Setting (e.g. the LeagueApps link) or pick the Register page.', 'ds-toolkit' ) ),
					'hero_btn_arrow'  => array( 'type' => 'select', 'label' => __( 'Trailing Arrow', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ) ),
					'hero_btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'ds-toolkit' ),
						'default' => 'global',
						'options' => array(
							'global' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ),
							'accent' => __( 'Accent colour', 'ds-toolkit' ),
							'custom' => __( 'Custom colours', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'hero_btn_bg', 'hero_btn_text_color', 'hero_btn_hover_bg', 'hero_btn_hover_text' ) ) ),
						'help'    => __( 'By default the button inherits the global Button (background, hover, radius, shape, typography) from Theme Setting.', 'ds-toolkit' ),
					),
					'hero_btn_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'hero_btn_text_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text', 'ds-toolkit' ), 'show_reset' => true ),
					'hero_btn_hover_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'hero_btn_hover_text' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Text', 'ds-toolkit' ), 'show_reset' => true ),
				),
			),
			'hero_social' => array(
				'title'  => __( 'Social Icons', 'ds-toolkit' ),
				'fields' => array(
					'hero_show_social'   => array(
						'type'    => 'select',
						'label'   => __( 'Show Social Icons', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'hero_social_target' ) ) ),
						'help'    => __( 'Pulled from Partner Settings (Facebook, Instagram, X, YouTube, LinkedIn, TikTok). Networks with no URL are skipped.', 'ds-toolkit' ),
					),
					'hero_social_target' => array( 'type' => 'select', 'label' => __( 'Open Links In', 'ds-toolkit' ), 'default' => '_blank', 'options' => array( '_blank' => __( 'New Tab', 'ds-toolkit' ), '_self' => __( 'Same Tab', 'ds-toolkit' ) ) ),
				),
			),
			// ---- Style 6 only: manual Program Cards list ----
			// Key stays `programs` (NOT `cards`) so a node migrated from the Post
			// Loop module carries its list verbatim and never collides with the
			// styles 1-5 `cards` repeater.
			'programs_sec' => array(
				'title'       => __( 'Program Cards (manual list)', 'ds-toolkit' ),
				'description' => __( 'Build each card by hand: date, sub-heading, title, description, a link/button, and an icon or image.', 'ds-toolkit' ),
				'fields'      => array(
					'programs' => array(
						'type'         => 'form',
						'label'        => __( 'Program', 'ds-toolkit' ),
						'form'         => 'ds_program_form',
						'preview_text' => 'prog_title',
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
						// Off for NEW modules (GH #147). The Heading default used to be the
						// placeholder "{a}Lorem{/a} Ipsum Dolor", so the old default meant
						// every freshly dropped CTA rendered Lorem copy on the page until
						// someone edited or unticked it. Since 1.14 the Heading default is
						// empty too (an empty field reads as "fill me in", Lorem reads as
						// finished copy). Modules already saved are untouched: they carry
						// their own value, and the render paths still fall back to 'yes' for
						// any module whose settings predate this field entirely.
						'default' => 'no',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'heading', 'header_label' ) ) ),
						'help'    => __( 'Off by default. Switch to Yes and type a heading to add one above the cards; existing modules keep whatever they were set to.', 'ds-toolkit' ),
					),
					'heading'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'ds-toolkit' ),
						'rows'    => 2,
						'default' => '',
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the header accent. {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'header_label' => array( 'type' => 'text', 'label' => __( 'Right-side Label', 'ds-toolkit' ), 'default' => '' ),
					'header_desc'  => array( 'type' => 'editor', 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'label' => __( 'Header Description', 'ds-toolkit' ), 'help' => __( 'Paragraph beside the heading (Bento / Style 3).', 'ds-toolkit' ) ),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'section_style' => array(
				'title'  => __( 'Section', 'ds-toolkit' ),
				'fields' => array(
					'section_bg' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Section Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-light-background)', 'show_reset' => true ),
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
						'help'    => __( 'Full width lets the CTA fill a full-width row/column. Use the row/column padding for side spacing.', 'ds-toolkit' ),
					),
					'content_max_width' => array( 'type' => 'unit', 'label' => __( 'Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 480, 'max' => 1920, 'step' => 10 ) ),
				),
			),
			'grid' => array(
				'title'  => __( 'Grid', 'ds-toolkit' ),
				'fields' => array(
					'columns'    => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				),
			),
			// --- Style 1 only: clip-card shape ---
			'clip' => array(
				'title'  => __( 'Card Shape', 'ds-toolkit' ),
				'fields' => array(
					'ratio'          => array( 'type' => 'text', 'label' => __( 'Card Ratio', 'ds-toolkit' ), 'default' => '3/4', 'help' => __( 'CSS aspect-ratio, e.g. 3/4, 1/1, 4/5.', 'ds-toolkit' ) ),
					'clip_tl'        => array( 'type' => 'unit', 'label' => __( 'Top-Left Cut', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'clip_br'        => array( 'type' => 'unit', 'label' => __( 'Bottom-Right Cut', 'ds-toolkit' ), 'default' => '26', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'stagger'        => array(
						'type'    => 'select',
						'label'   => __( 'Stagger Cards', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'stagger_offset' ) ) ),
					),
					'stagger_offset' => array( 'type' => 'unit', 'label' => __( 'Stagger Offset', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 120, 'step' => 2 ) ),
				),
			),
			// --- Style 2 only: bordered tile ---
			// --- Style 3 only: bento grid ---
			'bento' => array(
				'title'  => __( 'Bento', 'ds-toolkit' ),
				'fields' => array(
					'row_height' => array( 'type' => 'unit', 'label' => __( 'Row Height', 'ds-toolkit' ), 'default' => '220', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 120, 'max' => 400, 'step' => 10 ) ),
					'bento_hover' => array(
						'type'    => 'select',
						'label'   => __( 'Card Hover', 'ds-toolkit' ),
						'default' => 'lift',
						'options' => array(
							'lift' => __( 'Lift + shadow (image cards also zoom)', 'ds-toolkit' ),
							'zoom' => __( 'Image zoom only', 'ds-toolkit' ),
							'none' => __( 'None', 'ds-toolkit' ),
						),
						'help'    => __( 'Applies to every card, image or solid, and responds to hovering anywhere on the card rather than only on a link. Skipped for visitors who ask for reduced motion.', 'ds-toolkit' ),
					),
					'bento_radius' => array( 'type' => 'unit', 'label' => __( 'Cell Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Rounds every bento cell (image + text cells).', 'ds-toolkit' ) ),
					'bento_border_width' => array( 'type' => 'unit', 'label' => __( 'Cell Border Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 10, 'step' => 1 ), 'help' => __( 'Border around every bento cell. Blank or 0 = no border.', 'ds-toolkit' ) ),
					'bento_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Cell Border Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-line-color)', 'show_reset' => true, 'show_alpha' => true ),
					'bento_shadow' => array(
						'type'    => 'select',
						'label'   => __( 'Cell Shadow', 'ds-toolkit' ),
						'default' => '',
						'options' => array(
							''       => __( 'None', 'ds-toolkit' ),
							'soft'   => __( 'Soft', 'ds-toolkit' ),
							'medium' => __( 'Medium', 'ds-toolkit' ),
							'strong' => __( 'Strong', 'ds-toolkit' ),
						),
						'help'    => __( 'Drop shadow on every bento cell.', 'ds-toolkit' ),
					),
					'bento_eyebrow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Eyebrow Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Colour of the cell eyebrow label. Blank = accent on text cells, white on image cells.', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-bento-eyebrow', 'property' => 'color' ) ),
					'bento_eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Eyebrow Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-bento-eyebrow' ) ),
					'bento_img_title_typography' => array( 'type' => 'typography', 'label' => __( 'Image Card Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-bento-img .ds-cta-bento-title' ), 'help' => __( 'Title on image cards only. Leave blank and image cards follow Style > Typography > Card Title like every other card. Set it to give an image card a different title, for example a larger feature card.', 'ds-toolkit' ) ),
					'cell_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Cell Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'img_cell_bg' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Image Cell Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Shows behind image cells (visible with transparent logos or while the image loads).', 'ds-toolkit' ) ),
					'btn_global' => array( 'type' => 'select', 'label' => __( 'Button Style', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ), 'no' => __( 'Accent colour', 'ds-toolkit' ) ), 'help' => __( 'Text-cell buttons inherit the global Button (background, hover, radius, typography) from Theme Setting by default.', 'ds-toolkit' ) ),
				),
			),
			'card2' => array(
				'title'  => __( 'Tile', 'ds-toolkit' ),
				'fields' => array(
					'min_height'         => array( 'type' => 'unit', 'label' => __( 'Min Height', 'ds-toolkit' ), 'default' => '430', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 200, 'max' => 700, 'step' => 10 ) ),
					'card_radius'        => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'border_width'       => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '3', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 10, 'step' => 1 ) ),
					'border_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'border_hover_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Hover Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'img_zoom'           => array( 'type' => 'select', 'label' => __( 'Zoom Image on Hover', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
				),
			),
			'overlay' => array(
				'title'  => __( 'Overlay', 'ds-toolkit' ),
				'fields' => array(
					'overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'overlay_top'     => array( 'type' => 'unit', 'label' => __( 'Top Opacity', 'ds-toolkit' ), 'default' => '10', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_bottom'  => array( 'type' => 'unit', 'label' => __( 'Bottom Opacity', 'ds-toolkit' ), 'default' => '92', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_hover_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Overlay Colour', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Overlay colour the card/tile fades to on hover. Blank = no colour change.', 'ds-toolkit' ) ),
					'overlay_hover_opacity' => array( 'type' => 'unit', 'label' => __( 'Hover Overlay Opacity', 'ds-toolkit' ), 'default' => '70', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_hover_blend'   => array(
						'type'    => 'select',
						'label'   => __( 'Hover Blend Mode', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => array(
							'normal'      => __( 'Normal', 'ds-toolkit' ),
							'multiply'    => __( 'Multiply (darken / rich)', 'ds-toolkit' ),
							'screen'      => __( 'Screen (lighten)', 'ds-toolkit' ),
							'overlay'     => __( 'Overlay (contrast)', 'ds-toolkit' ),
							'soft-light'  => __( 'Soft Light', 'ds-toolkit' ),
							'hard-light'  => __( 'Hard Light', 'ds-toolkit' ),
							'color'       => __( 'Color (duotone)', 'ds-toolkit' ),
							'hue'         => __( 'Hue', 'ds-toolkit' ),
							'saturation'  => __( 'Saturation', 'ds-toolkit' ),
							'luminosity'  => __( 'Luminosity', 'ds-toolkit' ),
							'difference'  => __( 'Difference (invert)', 'ds-toolkit' ),
							'exclusion'   => __( 'Exclusion', 'ds-toolkit' ),
							'darken'      => __( 'Darken', 'ds-toolkit' ),
							'lighten'     => __( 'Lighten', 'ds-toolkit' ),
							'color-dodge' => __( 'Color Dodge', 'ds-toolkit' ),
							'color-burn'  => __( 'Color Burn', 'ds-toolkit' ),
						),
						'help'    => __( 'Blends the hover overlay INTO the image for a colour-shift on hover. Pair with a Hover Overlay Colour.', 'ds-toolkit' ),
					),
				),
			),
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'accent_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent (eyebrow, chevron, bar)', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'outline_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Outline Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Stroke colour for {outline}…{/outline} text in this module. Blank = the Theme Setting default.', 'ds-toolkit' ) ),
					'outline_width' => array( 'type' => 'unit', 'label' => __( 'Outline Text Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = the Theme Setting default.', 'ds-toolkit' ), 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'cta_shadow' => array(
						'type'    => 'select',
						'label'   => __( 'Card Shadow', 'ds-toolkit' ),
						'default' => '',
						'options' => array(
							''       => __( 'None', 'ds-toolkit' ),
							'soft'   => __( 'Soft', 'ds-toolkit' ),
							'medium' => __( 'Medium', 'ds-toolkit' ),
							'strong' => __( 'Strong', 'ds-toolkit' ),
						),
						'help'    => __( 'Drop shadow on the cards / tiles / cells of the selected style.', 'ds-toolkit' ),
					),
					'title_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Title', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'card_bg'             => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
				),
			),
			// The header's own colours, in their own section: every style with a header lists it (styles 5, 6
			// and 7 show a header but not the Colours section, so these three fields could not be reached).
			'header_colors' => array(
				'title'  => __( 'Header Colours', 'ds-toolkit' ),
				'fields' => array(
					'header_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Header Text', 'ds-toolkit' ), 'default' => 'var(--fl-global-headings)', 'show_reset' => true ),
					'header_accent_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Header Accent', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'header_label_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Header Label', 'ds-toolkit' ), 'default' => 'var(--fl-global-body)', 'show_reset' => true ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'heading_typography' => array( 'type' => 'typography', 'label' => __( 'Header Heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-heading' ) ),
					// One shared field per role, and each style names its title and eyebrow
					// differently (includes/frontend.css.php maps them). The preview lists every
					// style's class so the builder preview moves whichever style is selected;
					// Beaver Builder scopes each comma-separated part to the node.
					'title_typography'   => array( 'type' => 'typography', 'label' => __( 'Card Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-card-title, .ds-cta-tile-title, .ds-cta-bento-title, .ds-mcard-title' ) ),
					'eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Card Eyebrow', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-card-eyebrow, .ds-cta-tile-num, .ds-cta-bento-desc, .ds-mcard-eyebrow' ) ),
				),
			),
			// ---- Style 4 only: Big Hero background ----
			'hero_bg' => array(
				'title'  => __( 'Hero Background', 'ds-toolkit' ),
				'fields' => array(
					'hero_image'           => array( 'type' => 'photo', 'label' => __( 'Background Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Full-bleed image behind the CTA. A dark overlay sits on top for legibility.', 'ds-toolkit' ) ),
					'hero_bg_position'     => array(
						'type'    => 'select',
						'label'   => __( 'Image Position', 'ds-toolkit' ),
						'default' => 'center center',
						'options' => array(
							'left top'      => __( 'Top Left', 'ds-toolkit' ),
							'center top'    => __( 'Top Center', 'ds-toolkit' ),
							'right top'     => __( 'Top Right', 'ds-toolkit' ),
							'left center'   => __( 'Center Left', 'ds-toolkit' ),
							'center center' => __( 'Center', 'ds-toolkit' ),
							'right center'  => __( 'Center Right', 'ds-toolkit' ),
							'left bottom'   => __( 'Bottom Left', 'ds-toolkit' ),
							'center bottom' => __( 'Bottom Center', 'ds-toolkit' ),
							'right bottom'  => __( 'Bottom Right', 'ds-toolkit' ),
						),
						'help'    => __( 'Which part of the image stays in view as the section scales.', 'ds-toolkit' ),
					),
					'hero_bg_size'         => array(
						'type'    => 'select',
						'label'   => __( 'Image Size', 'ds-toolkit' ),
						'default' => 'cover',
						'options' => array(
							'cover'   => __( 'Cover (fill the area)', 'ds-toolkit' ),
							'contain' => __( 'Contain (fit, no crop)', 'ds-toolkit' ),
							'auto'    => __( 'Auto (original size)', 'ds-toolkit' ),
							'custom'  => __( 'Custom…', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'hero_bg_size_custom' ) ) ),
					),
					'hero_bg_size_custom'  => array( 'type' => 'text', 'label' => __( 'Custom Size', 'ds-toolkit' ), 'default' => '100% auto', 'help' => __( 'Any CSS background-size, e.g. “100% auto”, “80%”, “600px 400px”.', 'ds-toolkit' ) ),
					'hero_bg_repeat'       => array(
						'type'    => 'select',
						'label'   => __( 'Image Repeat', 'ds-toolkit' ),
						'default' => 'no-repeat',
						'options' => array(
							'no-repeat' => __( 'No Repeat', 'ds-toolkit' ),
							'repeat'    => __( 'Tile (repeat)', 'ds-toolkit' ),
							'repeat-x'  => __( 'Repeat Horizontally', 'ds-toolkit' ),
							'repeat-y'  => __( 'Repeat Vertically', 'ds-toolkit' ),
						),
						'help'    => __( 'Use Tile with a small/seamless texture; keep No Repeat for a photo.', 'ds-toolkit' ),
					),
					'hero_bg_attachment'   => array(
						'type'    => 'select',
						'label'   => __( 'Image Attachment', 'ds-toolkit' ),
						'default' => 'scroll',
						'options' => array(
							'scroll' => __( 'Scroll (normal)', 'ds-toolkit' ),
							'fixed'  => __( 'Fixed (parallax)', 'ds-toolkit' ),
						),
						'help'    => __( 'Fixed pins the image while the page scrolls (some mobile browsers ignore it).', 'ds-toolkit' ),
					),
					'hero_overlay_type'    => array(
						'type'    => 'select',
						'label'   => __( 'Overlay Style', 'ds-toolkit' ),
						'default' => 'solid',
						'options' => array(
							'solid'  => __( 'Solid tint', 'ds-toolkit' ),
							'linear' => __( 'Linear gradient', 'ds-toolkit' ),
							'radial' => __( 'Radial gradient', 'ds-toolkit' ),
							'none'   => __( 'None', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'hero_overlay_color', 'hero_overlay_opacity', 'hero_overlay_blend' ) ),
							'linear' => array( 'fields' => array( 'hero_overlay_color', 'hero_overlay_opacity', 'hero_overlay_color2', 'hero_overlay_opacity2', 'hero_overlay_angle', 'hero_overlay_blend' ) ),
							'radial' => array( 'fields' => array( 'hero_overlay_color', 'hero_overlay_opacity', 'hero_overlay_color2', 'hero_overlay_opacity2', 'hero_overlay_radial', 'hero_overlay_blend' ) ),
						),
						'help'    => __( 'Darkens the image for legible text. Gradients give a more creative fade — set two colours and their opacities.', 'ds-toolkit' ),
					),
					'hero_overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'hero_overlay_opacity' => array( 'type' => 'unit', 'label' => __( 'Overlay Opacity', 'ds-toolkit' ), 'default' => '82', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'hero_overlay_color2'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour 2', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'hero_overlay_opacity2'=> array( 'type' => 'unit', 'label' => __( 'Overlay Opacity 2', 'ds-toolkit' ), 'default' => '20', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'hero_overlay_angle'   => array( 'type' => 'unit', 'label' => __( 'Gradient Angle', 'ds-toolkit' ), 'default' => '180', 'description' => 'deg', 'slider' => array( 'min' => 0, 'max' => 360, 'step' => 5 ) ),
					'hero_overlay_radial'  => array(
						'type'    => 'select',
						'label'   => __( 'Radial Centre', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array(
							'center'       => __( 'Center', 'ds-toolkit' ),
							'top'          => __( 'Top', 'ds-toolkit' ),
							'bottom'       => __( 'Bottom', 'ds-toolkit' ),
							'left'         => __( 'Left', 'ds-toolkit' ),
							'right'        => __( 'Right', 'ds-toolkit' ),
							'left top'     => __( 'Top Left', 'ds-toolkit' ),
							'right top'    => __( 'Top Right', 'ds-toolkit' ),
							'left bottom'  => __( 'Bottom Left', 'ds-toolkit' ),
							'right bottom' => __( 'Bottom Right', 'ds-toolkit' ),
						),
					),
					'hero_overlay_blend'   => array(
						'type'    => 'select',
						'label'   => __( 'Overlay Blend Mode', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => array(
							'normal'      => __( 'Normal (tint)', 'ds-toolkit' ),
							'multiply'    => __( 'Multiply (darken / rich)', 'ds-toolkit' ),
							'screen'      => __( 'Screen (lighten)', 'ds-toolkit' ),
							'overlay'     => __( 'Overlay (contrast)', 'ds-toolkit' ),
							'soft-light'  => __( 'Soft Light', 'ds-toolkit' ),
							'hard-light'  => __( 'Hard Light', 'ds-toolkit' ),
							'color'       => __( 'Color (duotone)', 'ds-toolkit' ),
							'hue'         => __( 'Hue', 'ds-toolkit' ),
							'saturation'  => __( 'Saturation', 'ds-toolkit' ),
							'luminosity'  => __( 'Luminosity', 'ds-toolkit' ),
							'difference'  => __( 'Difference (invert)', 'ds-toolkit' ),
							'exclusion'   => __( 'Exclusion', 'ds-toolkit' ),
							'darken'      => __( 'Darken', 'ds-toolkit' ),
							'lighten'     => __( 'Lighten', 'ds-toolkit' ),
							'color-dodge' => __( 'Color Dodge', 'ds-toolkit' ),
							'color-burn'  => __( 'Color Burn', 'ds-toolkit' ),
						),
						'help'    => __( 'Blends the overlay colour INTO the image for colour-shift effects: try Multiply or Color with a brand colour for a duotone, or Screen to lighten. Text stays unaffected.', 'ds-toolkit' ),
					),
					'hero_min_height'      => array( 'type' => 'unit', 'label' => __( 'Min Height', 'ds-toolkit' ), 'default' => '520', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 240, 'max' => 900, 'step' => 10 ) ),
				),
			),
			'hero_box' => array(
				'title'  => __( 'Hero Box', 'ds-toolkit' ),
				'fields' => array(
					'box_max_width'     => array( 'type' => 'unit', 'label' => __( 'Content Max Width', 'ds-toolkit' ), 'default' => '860', 'description' => 'px', 'slider' => array( 'min' => 360, 'max' => 1200, 'step' => 10 ) ),
					'box_padding'       => array( 'type' => 'dimension', 'label' => __( 'Box Padding', 'ds-toolkit' ), 'default' => '60', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
					'box_bg'            => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Box Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Optional tint behind the content (only when Framed Box is on). Blank = transparent.', 'ds-toolkit' ) ),
					'box_border_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => 'rgba(255,255,255,0.22)', 'show_reset' => true, 'show_alpha' => true ),
					'box_border_width'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 8, 'step' => 1 ) ),
					'box_radius'        => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'box_top_bar'       => array(
						'type'    => 'select',
						'label'   => __( 'Top Accent Bar', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'box_top_bar_color', 'box_top_bar_height' ) ) ),
					),
					'box_top_bar_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Top Bar Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'box_top_bar_height' => array( 'type' => 'unit', 'label' => __( 'Top Bar Height', 'ds-toolkit' ), 'default' => '4', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
				),
			),
			'hero_colors' => array(
				'title'  => __( 'Hero Colours', 'ds-toolkit' ),
				'fields' => array(
					'hero_accent_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent (sub-heading, line, icons)', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'hero_heading_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'hero_heading_accent_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading Accent {a}…{/a}', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'hero_contact_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Contact Text', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'hero_contact_icon_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Contact Icons', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
				),
			),
			'hero_social_style' => array(
				'title'  => __( 'Social Style', 'ds-toolkit' ),
				'fields' => array(
					'social_icon_size'   => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 10, 'max' => 48, 'step' => 1 ) ),
					'social_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap Between Icons', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 48, 'step' => 1 ) ),
					'social_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true ),
					'social_hover_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Hover Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'show_alpha' => true ),
				),
			),
			'hero_typography' => array(
				'title'  => __( 'Hero Typography', 'ds-toolkit' ),
				'fields' => array(
					'hero_eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-eyebrow' ) ),
					'hero_heading_typography' => array( 'type' => 'typography', 'label' => __( 'Heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-heading' ) ),
					'hero_contact_typography' => array( 'type' => 'typography', 'label' => __( 'Contact Details', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-citem' ) ),
					'hero_btn_typography'     => array( 'type' => 'typography', 'label' => __( 'Button (Custom/Accent only)', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-btn' ) ),
				),
			),
			'mcard' => array(
				'title'  => __( 'Motion Card', 'ds-toolkit' ),
				'fields' => array(
					'mc_cols'   => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ), 'help' => __( 'Responsive: set per-device values with the device icon. Blank smaller sizes keep the automatic fallback (max 2 columns on tablet, 1 on phone).', 'ds-toolkit' ) ),
					'mc_gap'    => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'mc_ratio'  => array( 'type' => 'select', 'label' => __( 'Card Ratio', 'ds-toolkit' ), 'default' => '4 / 5', 'options' => array( '4 / 5' => '4:5', '3 / 4' => '3:4', '1 / 1' => '1:1', '4 / 3' => '4:3', '16 / 10' => '16:10' ) ),
					'mc_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-mcard', 'property' => 'border-radius', 'unit' => 'px' ) ),
					'mc_shadow' => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => 'soft', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'medium' => __( 'Medium', 'ds-toolkit' ), 'strong' => __( 'Strong', 'ds-toolkit' ) ) ),
					'mc_lift'   => array( 'type' => 'unit', 'label' => __( 'Hover Lift', 'ds-toolkit' ), 'default' => '6', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 20, 'step' => 1 ), 'help' => __( 'How far the card rises on hover; the shadow deepens with it.', 'ds-toolkit' ) ),
					'mc_speed'  => array( 'type' => 'unit', 'label' => __( 'Hover Transition Speed', 'ds-toolkit' ), 'default' => '250', 'description' => 'ms', 'slider' => array( 'min' => 100, 'max' => 800, 'step' => 10 ) ),
				),
			),
			'mcard_fx' => array(
				'title'  => __( 'Background Effects', 'ds-toolkit' ),
				'fields' => array(
					'mcgrad_enable' => array(
						'type'    => 'select',
						'label'   => __( 'Default Gradient', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Enabled', 'ds-toolkit' ), 'no' => __( 'Disabled', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'mcgrad_color', 'mcgrad_opacity', 'mcgrad_direction', 'mcgrad_coverage' ) ) ),
						'help'    => __( 'Adds a persistent gradient above the image for consistent text readability. It works independently from the default and hover effects below.', 'ds-toolkit' ),
					),
					'mcgrad_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'mcgrad_opacity' => array( 'type' => 'unit', 'label' => __( 'Gradient Opacity', 'ds-toolkit' ), 'default' => '60', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'mcgrad_direction' => array(
						'type'    => 'select',
						'label'   => __( 'Gradient Direction', 'ds-toolkit' ),
						'default' => 'bottom-top',
						'options' => array(
							'top-bottom' => __( 'Top to Bottom', 'ds-toolkit' ),
							'bottom-top' => __( 'Bottom to Top', 'ds-toolkit' ),
							'left-right' => __( 'Left to Right', 'ds-toolkit' ),
							'right-left' => __( 'Right to Left', 'ds-toolkit' ),
							'radial'     => __( 'Radial', 'ds-toolkit' ),
						),
					),
					'mcgrad_coverage' => array( 'type' => 'unit', 'label' => __( 'Gradient Coverage', 'ds-toolkit' ), 'default' => '75', 'description' => '%', 'slider' => array( 'min' => 10, 'max' => 100, 'step' => 1 ), 'help' => __( 'Controls how far the gradient extends from its solid edge. Radial mode uses it as the width of the dark outer fade.', 'ds-toolkit' ) ),
					'mcfx_base'    => array(
						'type'    => 'select',
						'label'   => __( 'Default Effect', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'blur' => __( 'Blur', 'ds-toolkit' ), 'brightness' => __( 'Darken (brightness)', 'ds-toolkit' ), 'grayscale' => __( 'Grayscale', 'ds-toolkit' ), 'opacity' => __( 'Opacity', 'ds-toolkit' ), 'overlay' => __( 'Colour Overlay', 'ds-toolkit' ) ),
						'toggle'  => array(
							'blur'       => array( 'fields' => array( 'mcfx_amount' ) ),
							'brightness' => array( 'fields' => array( 'mcfx_amount' ) ),
							'opacity'    => array( 'fields' => array( 'mcfx_amount' ) ),
							'overlay'    => array( 'fields' => array( 'mcfx_overlay', 'mcfx_blend' ) ),
						),
					),
					'mcfx_amount'  => array( 'type' => 'unit', 'label' => __( 'Effect Amount', 'ds-toolkit' ), 'default' => '60', 'description' => '% (px for blur)', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'mcfx_overlay' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-primary)', 'show_reset' => true, 'show_alpha' => true ),
					'mcfx_blend'   => array( 'type' => 'select', 'label' => __( 'Blend Mode', 'ds-toolkit' ), 'default' => 'multiply', 'options' => array( 'normal' => 'Normal', 'multiply' => 'Multiply', 'screen' => 'Screen', 'overlay' => 'Overlay', 'soft-light' => 'Soft Light', 'hard-light' => 'Hard Light', 'darken' => 'Darken', 'lighten' => 'Lighten' ) ),
					'mcfx_hover'   => array(
						'type'    => 'select',
						'label'   => __( 'Hover Effect', 'ds-toolkit' ),
						'default' => 'zoom-in',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'clear' => __( 'Remove Effect', 'ds-toolkit' ), 'brighten' => __( 'Brighten', 'ds-toolkit' ), 'zoom-in' => __( 'Zoom In', 'ds-toolkit' ), 'zoom-out' => __( 'Zoom Out', 'ds-toolkit' ) ),
						'toggle'  => array( 'zoom-in' => array( 'fields' => array( 'mcfx_zoom' ) ), 'zoom-out' => array( 'fields' => array( 'mcfx_zoom' ) ) ),
					),
					'mcfx_zoom'    => array( 'type' => 'unit', 'label' => __( 'Zoom Amount', 'ds-toolkit' ), 'default' => '6', 'description' => '%', 'slider' => array( 'min' => 1, 'max' => 25, 'step' => 1 ) ),
				),
			),
			'mcard_logo' => array(
				'title'       => __( 'Logo Overlay', 'ds-toolkit' ),
				'description' => __( 'Styling + animation for each card\'s Logo / Badge image (set per card).', 'ds-toolkit' ),
				'fields'      => array(
					'mcl_display' => array(
						'type'    => 'select',
						'label'   => __( 'Logo Display', 'ds-toolkit' ),
						'default' => 'always',
						'options' => array(
							'always' => __( 'Always Visible', 'ds-toolkit' ),
							'hover'  => __( 'Show On Card Hover', 'ds-toolkit' ),
						),
						'help'    => __( 'The whole card triggers the logo animation. Hover mode hides the logo until the card is hovered or keyboard-focused.', 'ds-toolkit' ),
					),
					'mcl_pos'    => array( 'type' => 'select', 'label' => __( 'Position', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'top-left' => __( 'Top Left', 'ds-toolkit' ), 'top-right' => __( 'Top Right', 'ds-toolkit' ), 'bottom-left' => __( 'Bottom Left', 'ds-toolkit' ), 'bottom-right' => __( 'Bottom Right', 'ds-toolkit' ) ) ),
					'mcl_size'   => array( 'type' => 'unit', 'label' => __( 'Size', 'ds-toolkit' ), 'default' => '84', 'description' => 'px', 'slider' => array( 'min' => 32, 'max' => 200, 'step' => 2 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-mcard-logo img', 'property' => 'width', 'unit' => 'px' ) ),
					'mcl_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Backing Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Optional plate behind the logo. Blank = none.', 'ds-toolkit' ) ),
					'mcl_pad'    => array( 'type' => 'unit', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '10', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'mcl_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '999', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 999, 'step' => 1 ) ),
					'mcl_shadow' => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => 'soft', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'medium' => __( 'Medium', 'ds-toolkit' ), 'strong' => __( 'Strong', 'ds-toolkit' ) ) ),
					'mcl_in'     => array( 'type' => 'select', 'label' => __( 'Card Hover Entrance', 'ds-toolkit' ), 'default' => 'fade-up', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'fade' => __( 'Fade In', 'ds-toolkit' ), 'fade-up' => __( 'Fade Up', 'ds-toolkit' ), 'fade-down' => __( 'Fade Down', 'ds-toolkit' ), 'fade-left' => __( 'Fade Left', 'ds-toolkit' ), 'fade-right' => __( 'Fade Right', 'ds-toolkit' ), 'slide-up' => __( 'Slide Up', 'ds-toolkit' ), 'slide-down' => __( 'Slide Down', 'ds-toolkit' ), 'slide-left' => __( 'Slide Left', 'ds-toolkit' ), 'slide-right' => __( 'Slide Right', 'ds-toolkit' ), 'zoom-in' => __( 'Zoom In', 'ds-toolkit' ), 'zoom-out' => __( 'Zoom Out', 'ds-toolkit' ) ), 'help' => __( 'Plays whenever the whole card is hovered or keyboard-focused. It can replay after the pointer leaves and returns. Skipped for reduced-motion visitors.', 'ds-toolkit' ) ),
					'mcl_dur'    => array( 'type' => 'unit', 'label' => __( 'Animation Duration', 'ds-toolkit' ), 'default' => '600', 'description' => 'ms', 'slider' => array( 'min' => 100, 'max' => 2000, 'step' => 50 ) ),
					'mcl_delay'  => array( 'type' => 'unit', 'label' => __( 'Animation Delay', 'ds-toolkit' ), 'default' => '0', 'description' => 'ms', 'slider' => array( 'min' => 0, 'max' => 2000, 'step' => 50 ) ),
					'mcl_ease'   => array( 'type' => 'select', 'label' => __( 'Animation Easing', 'ds-toolkit' ), 'default' => 'ease-out', 'options' => array( 'ease' => 'Ease', 'ease-out' => 'Ease Out', 'ease-in-out' => 'Ease In-Out', 'cubic-bezier(.34,1.56,.64,1)' => __( 'Spring (overshoot)', 'ds-toolkit' ) ) ),
					'mcl_hover'  => array( 'type' => 'select', 'label' => __( 'Hover Animation', 'ds-toolkit' ), 'default' => 'float', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'scale-up' => __( 'Scale Up', 'ds-toolkit' ), 'scale-down' => __( 'Scale Down', 'ds-toolkit' ), 'float' => __( 'Float', 'ds-toolkit' ), 'bounce' => __( 'Bounce', 'ds-toolkit' ), 'rotate' => __( 'Rotate', 'ds-toolkit' ), 'pulse' => __( 'Pulse', 'ds-toolkit' ), 'fade' => __( 'Fade', 'ds-toolkit' ), 'glow' => __( 'Glow', 'ds-toolkit' ) ) ),
				),
			),
			// ---- Style 6 only: Program Card options. Keys mirror the Post Loop
			// module's `program_opts` exactly so migration is a verbatim carry. ----
			'program_opts' => array(
				'title'  => __( 'Program Cards', 'ds-toolkit' ),
				'fields' => array(
					'pg_layout'      => array(
						'type'    => 'select',
						'label'   => __( 'Layout', 'ds-toolkit' ),
						'default' => 'columns',
						'options' => array(
							'columns' => __( 'Horizontal — cards side by side (default)', 'ds-toolkit' ),
							'rows'    => __( 'Vertical — full-width rows', 'ds-toolkit' ),
						),
						'help'    => __( 'Horizontal flows the cards left to right into the Columns count below. Vertical stacks one full-width row per program, with the date, text and button laid across the row; rows collapse back to stacked cards on phones. Fonts, colours and buttons stay exactly as styled below in both.', 'ds-toolkit' ),
						'toggle'  => array(
							'columns' => array( 'fields' => array( 'pg_cols', 'pg_same_height', 'pg_align', 'pg_img_h' ) ),
						),
					),
					'pg_cols'        => array(
						'type'       => 'unit',
						'label'      => __( 'Columns', 'ds-toolkit' ),
						'default'    => '3',
						'responsive' => true,
						'slider'     => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
						'help'       => __( 'Cards per row. Use the responsive icon on this field for tablet and mobile counts; leaving one blank keeps the size above it. The saved value stays the desktop count, so existing layouts are unchanged.', 'ds-toolkit' ),
					),
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
			// ---- Style 7 only: Program Pathway. Own pp_* keys, so Style 6 settings never move. ----
			'pp_layout' => array(
				'title'       => __( 'Program Pathway: Layout', 'ds-toolkit' ),
				'description' => __( 'Uses each program\'s Image, Sub-heading (the label beside the title), Title, Description and Link from the Program Cards list. Date, Icon and Button Text are Style 6 fields and are not shown. A card with a link is clickable everywhere; the number follows the card order.', 'ds-toolkit' ),
				'fields'      => array(
					'pp_cols'     => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'responsive' => array( 'default' => array( 'default' => '3', 'medium' => '2', 'responsive' => '1' ) ), 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Cards per row, up to 6. Above 4 the titles step down in size so long program names still fit.', 'ds-toolkit' ) ),
					'pp_gap'      => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 80, 'step' => 2 ) ),
					'pp_ratio'    => array( 'type' => 'select', 'label' => __( 'Image Shape', 'ds-toolkit' ), 'default' => '4/5', 'options' => array( '1/1' => __( 'Square', 'ds-toolkit' ), '4/5' => __( 'Portrait 4:5 (default)', 'ds-toolkit' ), '3/4' => __( 'Portrait 3:4', 'ds-toolkit' ), '4/3' => __( 'Landscape 4:3', 'ds-toolkit' ), '16/9' => __( 'Wide 16:9', 'ds-toolkit' ) ), 'help' => __( 'Every image is cropped to this shape, never stretched.', 'ds-toolkit' ) ),
					'pp_bar'      => array( 'type' => 'unit', 'label' => __( 'Line Under Image', 'ds-toolkit' ), 'default' => '6', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 16, 'step' => 1 ), 'help' => __( 'Drawn in the number outline colour. 0 = none.', 'ds-toolkit' ) ),
					'pp_lines'    => array( 'type' => 'select', 'label' => __( 'Grid Lines Behind Cards', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'pp_lines_color' ) ) ) ),
					'pp_lines_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Grid Line Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank = a faint tint of the heading colour.', 'ds-toolkit' ) ),
					'pp_hover'    => array( 'type' => 'select', 'label' => __( 'Card Hover', 'ds-toolkit' ), 'default' => 'lift', 'options' => array( 'lift' => __( 'Lift the card and zoom the image', 'ds-toolkit' ), 'zoom' => __( 'Zoom the image only', 'ds-toolkit' ), 'none' => __( 'None', 'ds-toolkit' ) ), 'help' => __( 'Linked cards only. Keyboard focus always shows an outline; motion is off for visitors who ask for reduced motion.', 'ds-toolkit' ) ),
				),
			),
			'pp_number' => array(
				'title'  => __( 'Program Pathway: Number', 'ds-toolkit' ),
				'fields' => array(
					'pp_num_size'    => array( 'type' => 'unit', 'label' => __( 'Number Size', 'ds-toolkit' ), 'default' => '104', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 40, 'max' => 200, 'step' => 2 ) ),
					'pp_num_fill'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Number Fill', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true ),
					'pp_num_outline' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Number Outline (and image line)', 'ds-toolkit' ), 'default' => 'var(--fl-global-primary)', 'show_reset' => true ),
					'pp_num_width'   => array( 'type' => 'unit', 'label' => __( 'Outline Width', 'ds-toolkit' ), 'default' => '4', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 10, 'step' => 1 ) ),
				),
			),
			'pp_text' => array(
				'title'  => __( 'Program Pathway: Text', 'ds-toolkit' ),
				'fields' => array(
					'pp_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-headings)', 'show_reset' => true ),
					'pp_sub_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Label Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'help' => __( 'The Sub-heading shown beside the title. Check it reads on the section background.', 'ds-toolkit' ) ),
					'pp_desc_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-body)', 'show_reset' => true ),
					'pp_title_typo'  => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-pp-title' ) ),
					'pp_sub_typo'    => array( 'type' => 'typography', 'label' => __( 'Label Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-pp-sub' ) ),
					'pp_desc_typo'   => array( 'type' => 'typography', 'label' => __( 'Description Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-pp-desc' ) ),
				),
			),
			// Card border + hover. Same keys as the Post Loop module's shared
			// `card_border` / `hover` sections so migrated values keep applying.
			'program_chrome' => array(
				'title'  => __( 'Card Border & Hover', 'ds-toolkit' ),
				'fields' => array(
					'card_bd_style'  => array( 'type' => 'select', 'label' => __( 'Border Style', 'ds-toolkit' ), 'default' => 'default', 'options' => array( 'default' => __( 'Default (layout look)', 'ds-toolkit' ), 'none' => __( 'None', 'ds-toolkit' ), 'solid' => __( 'Solid', 'ds-toolkit' ), 'dashed' => __( 'Dashed', 'ds-toolkit' ), 'dotted' => __( 'Dotted', 'ds-toolkit' ) ), 'toggle' => array( 'solid' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ), 'dashed' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ), 'dotted' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ) ) ),
					'card_bd_width'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 12, 'step' => 1 ) ),
					'card_bd_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Line Color.', 'ds-toolkit' ) ),
					'card_bd_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank = the theme corner radius.', 'ds-toolkit' ) ),
					'hover_effect'   => array( 'type' => 'select', 'label' => __( 'Hover Effect', 'ds-toolkit' ), 'default' => 'lift', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'lift' => __( 'Lift', 'ds-toolkit' ), 'grow' => __( 'Grow', 'ds-toolkit' ), 'shadow' => __( 'Shadow', 'ds-toolkit' ), 'border' => __( 'Border', 'ds-toolkit' ), 'zoom' => __( 'Zoom Image', 'ds-toolkit' ) ) ),
					'hover_speed'    => array( 'type' => 'unit', 'label' => __( 'Hover Speed', 'ds-toolkit' ), 'default' => '300', 'description' => 'ms', 'slider' => array( 'min' => 0, 'max' => 900, 'step' => 10 ) ),
					'hover_bg'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					// NOTE: `section_bg` is deliberately NOT redefined here — the module
					// already declares it (Colors section). A second definition of the
					// same key would render the control twice and fight over the default.
					// A migrated node's stored value still applies via chrome_css().
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
						'help'       => __( 'Inner spacing around the grid. 0 = compact / flush.', 'ds-toolkit' ),
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
