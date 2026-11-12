<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Every feature and every setting of the toolkit, declared ONCE.
 *
 * Adding a feature toggle used to mean editing five places by hand (the loader registry, the defaults, the admin
 * page's variables, the card markup, the group count), and CONTRIBUTING.md noted that most rejected changes failed
 * that wiring. This class is now the only place:
 *
 *   DS_Features::registry()        what DS_Toolkit loads (file, class, min blueprint), in load order
 *   DS_Features::defaults( $bp )   the default of every setting for a blueprint generation
 *   DS_Features::sanitize()        type and range of every setting, applied when the settings page saves
 *   DS_Features::layout()          which card sits in which group on Settings > DS Toolkit, in display order
 *   DS_Features::modules()         the Beaver Builder blocks (label + description), in display order
 *
 * One entry in all():
 *
 *   'key' => array(
 *       'file' / 'class'   the feature class the loader starts when the key is on. Left out for a setting that
 *                          switches part of another feature (tripwire_hq_report, origin_guard_enabled, mcp_*).
 *       'min_blueprint'    the feature never loads below this generation (modules are exempt: opt-in anywhere).
 *       'default'          the saved value on a new install. Left out = no default (the key is absent until
 *                          someone switches it on). 'default_bp' = the default applies from that generation only.
 *       'label', 'summary' the card: a name and ONE sentence. 'more' = the detail, shown behind "More".
 *       'icon'             a dashicons name without the prefix.
 *       'scope'            the chip beside the name: fleet | lp6 | lp7 | compat | '' (no chip).
 *       'fields'           the feature's own settings: name => type, default, label, help, min, max, options,
 *                          'advanced' (folded behind "Advanced"), 'ui' => false (drawn by hand or on another tab).
 *   )
 *
 * Field types: bool, int, digits (a number kept as text), text, url, email, lines (a textarea, one item per
 * line), choice, html, css, mappings (the ACF to CSS variable rows).
 *
 * tests/features-schema-test.php pins the registry and the defaults to what 1.17.4 shipped, checks that every
 * settings field in an admin view is known here, and that every card is placed exactly once.
 */
class DS_Features {

	/** Settings page groups, in display order: id => array( label, dashicon, open by default ). */
	const GROUPS = array(
		'security' => array( 'Security & Access', 'shield-alt', true ),
		'partner'  => array( 'Partner Experience', 'welcome-learn-more', true ),
		'modules'  => array( 'LeagueApps Modules (Beaver Builder blocks)', 'grid-view', true ),
		'site'     => array( 'Site & Content', 'admin-customizer', true ),
		'dev'      => array( 'Shortcodes & Dev Tools', 'editor-code', false ),
	);

	/** Chip text for 'scope'. */
	const SCOPES = array(
		'fleet'  => 'Fleet-wide',
		'lp6'    => 'Launchpad 6+',
		'lp7'    => 'Launchpad 7+',
		'compat' => 'Compatibility fix',
	);

	/** Which card sits in which group, top to bottom. The Modules group is filled from modules(). */
	public static function layout() {
		return array(
			'security' => array( 'hide_fl_assistant', 'user_roles_enabled', 'disable_comments_enabled', 'admin_menu_tidy_enabled', 'bot_shield_enabled', 'origin_guard_enabled', 'tripwire_enabled', 'tripwire_hq_report' ),
			'partner'  => array( 'enable_login_branding', 'design_academy_enabled', 'school_emblems_enabled', 'ds_builder_defaults_enabled' ),
			'modules'  => array(),
			'site'     => array( 'theme_setting_enabled', 'home_templates_enabled', 'ds_global_heading_font_enabled', 'ds_social_card_enabled', 'page_banner_sync_enabled', 'image_optimization_enabled', 'ds_pattern_library_enabled', 'nested_order_enabled' ),
			'dev'      => array( 'ds_admin_bar_links_enabled', 'uabb_post_loop_fix_enabled', 'defender_flywheel_reset_fix_enabled', 'hummingbird_critical_fix_enabled', 'acf_css_vars_enabled', 'getsubmenu_enabled', 'current_year_enabled', 'overlay_nav_enabled', 'forminator_email_partner_enabled', 'child_pages_enabled' ),
		);
	}

	/** Feature keys that have their own tab instead of a card on the Features tab. */
	const OWN_TAB = array( 'global_css_enabled', 'global_js_enabled', 'copyright_shortcode_enabled' );

	/** The Beaver Builder blocks, in the order the Modules group lists them. */
	const MODULE_ORDER = array(
		'ds_hero_module_enabled', 'ds_menu_module_enabled', 'ds_post_loop_module_enabled', 'ds_page_cards_module_enabled',
		'ds_carousel_module_enabled', 'ds_orgstats_module_enabled', 'ds_marquee_module_enabled', 'ds_pathway_module_enabled',
		'ds_info_list_module_enabled', 'ds_cta_module_enabled', 'ds_heading_module_enabled', 'ds_divider_module_enabled',
		'ds_device_module_enabled', 'ds_team_detail_module_enabled', 'ds_content_router_module_enabled', 'ds_social_module_enabled',
		'ds_table_module_enabled', 'ds_programs_module_enabled', 'ds_la_calendar_module_enabled', 'ds_la_roster_module_enabled',
	);

	private static $all = null;

	/** A feature class entry. */
	private static function feature( $slug, $class, $more = array() ) {
		return array_merge( array( 'file' => 'features/class-ds-' . $slug . '.php', 'class' => $class ), $more );
	}

	/** A Beaver Builder block: a feature class, gen-6 gated for its DEFAULT only (the loader lets modules through). */
	private static function module( $slug, $class, $label, $desc, $default_bp = 6 ) {
		$m = array( 'file' => 'features/class-ds-' . $slug . '.php', 'class' => $class, 'min_blueprint' => 6, 'module' => true, 'label' => $label, 'summary' => $desc );
		if ( $default_bp ) {
			$m['default']    = 1;
			$m['default_bp'] = $default_bp;
		}
		return $m;
	}

	/**
	 * The schema. ORDER MATTERS: it is the order features are loaded in (it was the order of the old registry).
	 * Settings-only entries (no 'file') and the HQ link go at the end.
	 */
	public static function all() {
		if ( null !== self::$all ) {
			return self::$all;
		}
		$staff = defined( 'DS_TOOLKIT_ADMIN_DOMAIN' ) ? DS_TOOLKIT_ADMIN_DOMAIN : '@leagueapps.com';
		$all   = array(

			'enable_login_branding' => self::feature( 'login-branding', 'DS_Login_Branding', array(
				'default' => 1, 'label' => 'LeagueApps Custom Login', 'icon' => 'admin-appearance', 'scope' => 'fleet',
				'summary' => 'Custom logo, "Powered by LeagueApps Design Shop" branding and a support link on the WordPress login page.',
				'fields'  => array( 'login_logo_id' => array( 'type' => 'int', 'min' => 0, 'ui' => false ) ),
			) ),

			'hide_fl_assistant' => self::feature( 'hide-fl-assistant', 'DS_Hide_FL_Assistant', array(
				'default' => 1, 'label' => 'Hide Beaver Builder Cloud Icon', 'icon' => 'cloud', 'scope' => 'fleet',
				'summary' => 'Hides the FL Assistant cloud button in the Beaver Builder toolbar for everyone except LeagueApps accounts.',
			) ),

			'acf_css_vars_enabled' => self::feature( 'acf-css-vars', 'DS_ACF_CSS_Vars', array(
				'default' => 0, 'label' => 'ACF Theme Options to CSS Variables', 'icon' => 'editor-code',
				'summary' => 'Maps ACF option fields to CSS custom properties printed in :root on every page.',
				'fields'  => array( 'acf_css_vars_mappings' => array( 'type' => 'mappings', 'ui' => false, 'default' => array(
					array( 'acf_field' => 'header_scrolled_bar_color', 'css_var' => '--header-scrolled-bar-color', 'fallback' => 'var(--fl-global-accent)' ),
				) ) ),
			) ),

			'getsubmenu_enabled' => self::feature( 'getsubmenu', 'DS_Getsubmenu', array(
				'default' => 0, 'label' => '[getsubmenu] Shortcode', 'icon' => 'menu-alt',
				'summary' => 'A shortcode that lists the child pages of any page, or the children of a menu item, as navigation links.',
				'more'    => '<p>Use <code>listfrom</code> to name the parent page or menu item, and <code>mode</code> to choose where to look.</p>'
					. '<p><strong>Mode: pages</strong> lists the child pages of a parent page. Use the page title, slug or ID:</p><code>[getsubmenu listfrom="Programs" mode="pages"]</code>'
					. '<p><strong>Mode: menus</strong> finds a nav menu item by title and lists its direct children from that menu:</p><code>[getsubmenu listfrom="Programs" mode="menus"]</code>'
					. '<p>The output is a <code>&lt;div class="submenu-text"&gt;</code> with links separated by line breaks, ready to style with CSS.</p>',
			) ),

			'current_year_enabled' => self::feature( 'current-year', 'DS_Current_Year', array(
				'default' => 0, 'label' => '[current_year] Shortcode', 'icon' => 'calendar-alt',
				'summary' => 'Prints the current year, so a footer copyright line never needs a yearly edit.',
				'more'    => '<p>Place it anywhere the year should appear:</p><code>[current_year]</code>'
					. '<p>Example: type <code>&amp;copy; [current_year] LeagueApps Design Shop</code>.</p>',
			) ),

			'forminator_email_partner_enabled' => self::feature( 'forminator-email-partner', 'DS_Forminator_Email_Partner', array(
				'default' => 0, 'label' => 'Forminator {email_partner} Variable', 'icon' => 'email-alt',
				'summary' => 'Adds {email_partner} to Forminator forms: the address saved in the ACF options field partner_email.',
				'more'    => '<p>In any Forminator notification, use <code>{email_partner}</code> as a recipient or in the email body. It is replaced with the address stored in <strong>ACF Theme Options, partner_email</strong>.</p>'
					. '<p>Example: set the "Send To" field of a notification to <code>{email_partner}</code>.</p>',
				'fields'  => array( 'forminator_email_partner_fallback' => array(
					'type' => 'email', 'default' => 'designshop' . $staff, 'label' => 'Fallback email',
					'help' => 'Used when partner_email is empty in ACF.',
				) ),
			) ),

			'global_css_enabled' => self::feature( 'global-css', 'DS_Global_CSS', array(
				'default' => 0, 'label' => 'Global CSS',
				'fields'  => array( 'global_css_overrides' => array( 'type' => 'css', 'default' => '', 'ui' => false ) ),
			) ),

			'global_js_enabled' => self::feature( 'global-js', 'DS_Global_JS', array( 'default' => 0, 'label' => 'Global JS' ) ),

			'child_pages_enabled' => self::feature( 'child-pages', 'DS_Child_Pages', array(
				'default' => 0, 'label' => '[child_pages] List Pages Cards', 'icon' => 'grid-view',
				'summary' => 'A responsive grid of child-page cards drawn with a Beaver Builder template. Also registers [get_parent_page_title].',
				'more'    => '<p>Place <code>[child_pages]</code> on any parent page. It finds the page\'s child pages and draws each with the template below.</p>'
					. '<p>Inside the card template, <code>[get_parent_page_title]</code> prints the parent page\'s title (for example "Programs").</p>'
					. '<p>Override any setting per shortcode:</p><code>[child_pages template="56369" columns="4" columns_tablet="2" columns_mobile="1"]</code>',
				'fields'  => array(
					// The id of the card template on the Launchpad blueprint; kept as a string, as it always was saved.
					'child_pages_template_id'    => array( 'type' => 'digits', 'default' => '56369', 'label' => 'Default BB template ID', 'help' => 'The saved Beaver Builder layout used as the card. Its ID is in the address bar while editing the template.' ),
					'child_pages_columns'        => array( 'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 6, 'label' => 'Columns, desktop' ),
					'child_pages_columns_tablet' => array( 'type' => 'int', 'default' => 2, 'min' => 1, 'max' => 4, 'label' => 'Columns, tablet' ),
					'child_pages_columns_mobile' => array( 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 2, 'label' => 'Columns, mobile' ),
				),
			) ),

			// Compat patches: each only acts when the plugin it patches is doing the thing it patches, so they are on fleet-wide.
			'uabb_post_loop_fix_enabled' => self::feature( 'uabb-post-loop-fix', 'DS_UABB_Post_Loop_Fix', array(
				'default' => 1, 'label' => 'UABB Advanced Posts: Featured Image Loop Fix', 'icon' => 'format-image', 'scope' => 'compat',
				'summary' => 'Fixes UABB Advanced Posts showing the first post\'s featured image on every post of a "Custom" layout.',
				'more'    => '<p>Toggles Beaver Themer\'s <code>FLThemeBuilderFieldConnections::$in_post_grid_loop</code> flag around each post through UABB\'s <code>uabb_blog_posts_before_post</code> and <code>uabb_blog_posts_after_post</code> hooks. Works for any post type. The hooks only fire while UABB Advanced Posts renders, so sites without UABB are unaffected.</p>',
			) ),

			'defender_flywheel_reset_fix_enabled' => self::feature( 'defender-flywheel-reset-fix', 'DS_Defender_Flywheel_Reset_Fix', array(
				'default' => 1, 'label' => 'Defender Mask Login on Flywheel: Password Reset Link Fix', 'icon' => 'shield-alt', 'scope' => 'compat',
				'summary' => 'Makes password-reset emails work again on Flywheel sites that use Defender\'s Mask Login.',
				'more'    => '<p>Flywheel strips the password-reset cookie on a masked login URL, so Defender swaps in a <code>wd-ml-token</code> parameter on the emailed link. Defender 6.2.x looks for the WordPress 6 link order and misses the WordPress 7 one, so the token is never added and every reset link says "appears to be invalid". This re-appends the token after Defender runs. It does nothing unless Mask Login is on and the host is Flywheel.</p>',
			) ),

			'hummingbird_critical_fix_enabled' => self::feature( 'hummingbird-critical-fix', 'DS_Hummingbird_Critical_Fix', array(
				'default' => 1, 'label' => 'Hummingbird Critical CSS: Refresh on Publish', 'icon' => 'performance', 'scope' => 'compat',
				'summary' => 'Deletes a page\'s saved Critical CSS snapshot when its layout is published, so new modules are never shown unstyled.',
				'more'    => '<p>Hummingbird\'s Critical CSS serves each page with a saved snapshot of its CSS and loads the real stylesheets only when the visitor moves the mouse or scrolls. It never rebuilds that snapshot when the page changes, so a module added later shows unstyled to logged-out visitors (a 4-column grid as one column, for example). This deletes the page\'s snapshot whenever its layout is published; Hummingbird serves the full CSS and builds a fresh one. Saving a Themer layout or saved template refreshes every page. It does nothing unless Hummingbird\'s Critical CSS is on.</p>',
			) ),

			'overlay_nav_enabled' => self::feature( 'overlay-nav', 'DS_Overlay_Nav', array(
				'default' => 0, 'label' => '[ds_overlay_nav] / [ds_overlay_subs] Shortcodes', 'icon' => 'menu',
				'summary' => 'Renders a WordPress menu as a full-screen overlay: a numbered left panel and a matching sub-link right panel.',
				'more'    => '<p>Place <code>[ds_overlay_nav]</code> in the left panel for the top-level items, numbered automatically. Place <code>[ds_overlay_subs]</code> in the right panel, on the same page and after it, for the child links of every item that has a submenu.</p>'
					. '<code>[ds_overlay_nav menu="primary"]</code> <code>[ds_overlay_subs]</code>'
					. '<p><code>menu</code> takes a menu name or slug (default <code>primary</code>). The markup is unstyled by design: pair it with the theme\'s overlay CSS and JS.</p>',
			) ),

			// Not blueprint-gated: any site can switch it on. Off by default because it changes the visible order of live archives.
			'nested_order_enabled' => self::feature( 'nested-order', 'DS_Nested_Order', array(
				'default' => 0, 'label' => 'Nested Pages Order on the Front End', 'icon' => 'sort',
				'summary' => 'Lists teams, staff, athletes and events on the public site in the order set by dragging them in Nested Pages.',
				'more'    => '<p><strong>Off by default on existing sites</strong>, because switching it on changes the visible order of live archives.</p><p>Nested Pages saves the dragged order but never applies it to the public site, so archives otherwise fall back to newest-first. Also makes a Post Loop set to "Menu Order / Nested Pages position" read top-to-bottom instead of reversed. Applies to the custom post types Nested Pages manages. Blog posts stay newest-first, and any layout or loop that sets its own order always wins.</p>',
			) ),

			// 'min_blueprint' gates a feature to a blueprint generation: it only loads on installs whose
			// ds_blueprint_version is >= that number, and its toggle defaults ON there (see defaults()).
			'disable_comments_enabled' => self::feature( 'disable-comments', 'DS_Disable_Comments', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Disable Comments', 'icon' => 'admin-comments', 'scope' => 'lp6',
				'summary' => 'Turns WordPress comments off site-wide and removes them from the admin.',
				'more'    => '<p>Closes comments and pings, hides existing comments, removes comment support from every post type, and cleans the admin (Comments menu, dashboard widget, admin-bar item). Replaces the standalone Disable Comments plugin.</p>',
			) ),

			'copyright_shortcode_enabled' => self::feature( 'copyright', 'DS_Copyright', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Footer copyright shortcode',
				'fields'  => array( 'footer_copyright_text' => array( 'type' => 'html', 'default' => '&copy; {year} LeagueApps Design Shop', 'default_bp' => 6, 'ui' => false ) ),
			) ),

			'theme_setting_enabled' => self::feature( 'theme-setting', 'DS_Theme_Setting', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Theme Setting', 'icon' => 'admin-customizer', 'scope' => 'lp6',
				'summary' => 'The Theme Setting admin page: the site\'s global colours and text, heading, link and button styles, kept in sync with Beaver Builder Global Styles.',
				'more'    => '<p>Registers the page directly below <strong>Partner Setting</strong>, visible only to LeagueApps accounts unless opened to others below. It reads and writes the same store as Beaver Builder, Global Styles, so edits here and in the builder stay in sync.</p>',
				'fields'  => array( 'theme_setting_all_users' => array(
					'type' => 'bool', 'default' => 0, 'label' => 'Open Theme Setting to other users',
					'help' => 'Also shows the page to non-LeagueApps users who can change the site\'s design (Administrators and the Partner role). Home page templates stay LeagueApps-only.',
				) ),
			) ),

			'home_templates_enabled' => self::feature( 'home-templates', 'DS_Home_Templates', array(
				'min_blueprint' => 7, 'default' => 1, 'default_bp' => 7, 'label' => 'Home Page Templates (LeagueApps only)', 'icon' => 'layout', 'scope' => 'lp7',
				'summary' => 'The Home page section of Theme Setting: apply, preview, revert and save home layouts on a development copy.',
				'more'    => '<p>Covers the header, footer, page and archive banners and site styles. Home templates are hidden from non-LeagueApps users. Turning this off removes the section and the hiding, and changes no page.</p>',
			) ),

			'ds_global_heading_font_enabled' => self::feature( 'global-heading-font', 'DS_Global_Heading_Font', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Global Heading Font Loader', 'icon' => 'editor-textcolor', 'scope' => 'lp6',
				'summary' => 'Loads the Google font picked under Theme Setting, Heading, "All", which Beaver Builder styles but never requests.',
				'more'    => '<p>The "All" heading tab saves to the <code>h_typography</code> global style. Beaver Builder writes it into the layout CSS but does not add it to its font request, so headings fell back to a system font. Leave on unless headings are loaded another way.</p>',
			) ),

			'admin_menu_tidy_enabled' => self::feature( 'admin-menu', 'DS_Admin_Menu', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Admin Menu Tidy', 'icon' => 'menu', 'scope' => 'lp6',
				'summary' => 'Declutters the admin menu: moves Defender, Yoast and Media Library Organizer under Tools, and hides ACF and Beaver Builder from non-LeagueApps users.',
				'more'    => '<p>Appearance stays visible to everyone so partners can manage menus. Capabilities are untouched: it only relocates or hides what a user can already open.</p>',
			) ),

			'ds_menu_module_enabled'   => self::module( 'menu', 'DS_Menu', 'Menu', 'Responsive navigation bar with dropdowns, mega-menus, and a full-screen mobile overlay.' ),
			'ds_social_module_enabled' => self::module( 'social', 'DS_Social', 'Partner Social', 'A row of the partner\'s social-media links.' ),

			'ds_social_card_enabled' => self::feature( 'social-card', 'DS_Social_Card', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Social Sharing Card', 'icon' => 'share', 'scope' => 'lp6',
				'summary' => 'One image set in Theme Setting, General becomes the site\'s default social card and a Beaver Builder dynamic field.',
				'more'    => '<p>Synced into Yoast\'s default social image, so Yoast handles the og: and twitter: tags; without Yoast the toolkit prints them. A page or post with its own image keeps it.</p>',
			) ),

			'ds_hero_module_enabled'      => self::module( 'hero', 'DS_Hero', 'Hero Banner', 'Full-width hero (image, video, or slideshow) with overlay, eyebrow, headline, and call-to-action buttons.' ),
			'ds_cta_module_enabled'       => self::module( 'cta', 'DS_CTA', 'CTA', 'A call-to-action band with a heading, text, and buttons.' ),
			'ds_marquee_module_enabled'   => self::module( 'marquee', 'DS_Marquee', 'Marquee', 'A scrolling announcement ticker with a pinned label.' ),
			'ds_pathway_module_enabled'   => self::module( 'pathway', 'DS_Pathway', 'Pathway', 'Stage cards connected by a progress line: per-stage markers, a fading end, and a vertical timeline on small screens.' ),
			'ds_post_loop_module_enabled' => self::module( 'post-loop', 'DS_Post_Loop', 'Post Loop', 'Lists of news, staff, teams, programs, sponsors, or tournaments in a range of card layouts.' ),
			'ds_orgstats_module_enabled'  => self::module( 'orgstats', 'DS_OrgStats', 'Org Stats', 'Headline stat figures (the record) as plain figures or photo cards.' ),
			'ds_carousel_module_enabled'  => self::module( 'carousel', 'DS_Carousel', 'Images/Videos Carousel', 'A stacked-deck slider or a multi-column reels strip of images and videos.' ),
			'ds_heading_module_enabled'   => self::module( 'heading', 'DS_Heading', 'Heading', 'A styled section heading with an eyebrow line and accent options.' ),
			'ds_divider_module_enabled'   => self::module( 'divider', 'DS_Divider', 'Divider', 'A horizontal or vertical divider with gradient-fade, running-light, glow, and dashed effects.' ),
			'ds_device_module_enabled'    => self::module( 'device', 'DS_Device', 'Device', 'Images and videos inside a phone, tablet or desktop frame: one image, a slideshow, a video, or a mix.' ),

			'image_optimization_enabled' => self::feature( 'image-optimization', 'DS_Image_Optimization', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Image Optimization on Upload', 'icon' => 'images-alt2', 'scope' => 'lp6',
				'summary' => 'Downscales oversized uploads to 2048px on the longest edge and saves them as WebP.',
				'more'    => '<p>Runs the moment an image is uploaded, before thumbnails are built, so every size is WebP too (quality 82). A 12 MB phone photo becomes a lightweight WebP. Covers Media Library uploads and WP-CLI or sideload imports. Only touches JPEG and PNG (and oversized WebP); GIFs and SVGs pass through, and an image that would not benefit is left untouched.</p>',
			) ),

			'page_banner_sync_enabled' => self::feature( 'page-banner', 'DS_Page_Banner', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Page Banner Sync', 'icon' => 'cover-image', 'scope' => 'lp6',
				'summary' => 'Keeps a page\'s banner Background Photo and its Featured Image the same, and adds a banner image button to the Beaver Builder top bar.',
				'more'    => '<p>A partner sets the image in one place: whichever of the two was changed in a save is copied to the other, including a removal. The top-bar button sets or removes the banner image without leaving the builder.</p>',
			) ),

			'ds_pattern_library_enabled' => self::feature( 'pattern-library', 'DS_Pattern_Library', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Pattern Library', 'icon' => 'screenoptions', 'scope' => 'lp6',
				'summary' => 'Adds "Browse patterns" beside every background image button in Theme Setting: 330 tileable SVG patterns in the site\'s palette.',
				'more'    => '<p>Patterns are from pattern.monster (MIT), with colour, opacity, size and line weight. A pick is saved as a small file in <code>uploads/ds-patterns/</code>, never the Media Library, and goes in the same field an uploaded image does, so turning this off leaves every chosen pattern in place.</p>',
			) ),

			'ds_content_router_module_enabled' => self::module( 'content-router', 'DS_Content_Router', 'Content Router', 'Renders the right body layout for each page type (single vs. archive) from one Themer template.' ),

			// LeagueApps data blocks: opt-in on every blueprint (no default), each needs credentials on the LeagueApps tab.
			// The retired Schedule block (ds_la_schedule_module_enabled) is no longer registered; team pages use the Table block.
			'ds_programs_module_enabled'    => self::module( 'programs', 'DS_Programs', 'LeagueApps Programs', 'Live tournament, league, camp and clinic listings from LeagueApps as a filterable table. Needs a site ID and API key on the LeagueApps tab.', 0 ),
			'ds_la_calendar_module_enabled' => self::module( 'la-calendar', 'DS_LA_Calendar', 'LeagueApps Calendar', 'Team schedules from LeagueApps (tournaments, games, practices) as a month calendar and an upcoming list. Uses the site ID and API key on the LeagueApps tab.', 0 ),
			'ds_la_roster_module_enabled'   => self::module( 'la-roster', 'DS_LA_Roster', 'LeagueApps Roster', 'A team\'s players and coaches from LeagueApps as a designed roster (name, position, grad year, height). Needs the club\'s private LeagueApps key, uploaded on the LeagueApps tab by an administrator.', 0 ),
			// New modules default ON for new builds only (Launchpad 7+); older sites opt in, so a release adds nothing to a
			// live site's builder, AJAX or cron (pre-release audit 2026-09-27).
			'ds_table_module_enabled'       => self::module( 'table', 'DS_Table', 'Table', 'A sortable, searchable table typed in the builder, uploaded as a CSV, or kept in sync with a CSV file or a Google Sheet.', 7 ),
			'ds_info_list_module_enabled'   => self::module( 'info-list', 'DS_Info_List', 'Post Details', 'Icon-and-text rows from the current post\'s ACF fields, for Themer single templates (staff, events); empty rows hide.' ),
			'ds_page_cards_module_enabled'  => self::module( 'page-cards', 'DS_Page_Cards', 'Page Cards', 'A grid of linked cards built from child pages, from your own manually added cards, or both together.' ),
			'ds_team_detail_module_enabled' => self::module( 'team-detail', 'DS_Team_Detail', 'Team Detail', 'A single-team layout: roster, schedule, and coaches.' ),

			'ds_builder_defaults_enabled' => self::feature( 'builder-defaults', 'DS_Builder_Defaults', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Builder Defaults', 'icon' => 'admin-tools', 'scope' => 'lp6',
				'summary' => 'Opens every module\'s settings on its first (Content) tab, and lists the LeagueApps blocks right under Basic in the module panel.',
				'more'    => '<p>Beaver Builder otherwise re-opens the last tab used, so a partner editing a module lands on Style. Builder interface only: no cost on the public site.</p>',
			) ),

			'ds_admin_bar_links_enabled' => self::feature( 'admin-bar-links', 'DS_Admin_Bar_Links', array(
				'min_blueprint' => 6, 'default' => 1, 'default_bp' => 6, 'label' => 'Admin Bar Links', 'icon' => 'admin-links', 'scope' => 'lp6',
				'summary' => 'Adds "Theme Setting" and "DS Toolkit" to the admin toolbar for LeagueApps users, in wp-admin and on the public site.',
				'more'    => '<p>Each link shows only to a user who can open its page, so partners never see a dead link.</p>',
			) ),

			// Partner-safe access control and the Design Academy panel: on for the whole fleet, whatever the blueprint.
			'user_roles_enabled' => self::feature( 'user-roles', 'DS_User_Roles', array(
				'default' => 1, 'label' => 'User Roles (Partner)', 'icon' => 'groups', 'scope' => 'fleet',
				'summary' => 'Registers the Partner role (an Administrator without plugin management or file editors) and hides Plugins, Tools and Settings from non-LeagueApps users.',
				'more'    => '<p>Partner accounts get safe admin access with one pick on the Users screen. The menus are hidden by label only; capabilities are untouched.</p>',
				'fields'  => array( 'partner_plugin_access' => array(
					'type' => 'bool', 'default' => 0, 'label' => 'Allow plugin access',
					'help' => 'Gives the Partner role plugin management again and stops hiding the Plugins menu from non-LeagueApps users. For a site where a partner should manage plugins.',
				) ),
			) ),

			// Fleet-wide origin protection against bot floods (see the class docblock for the sportsatthebeach incident).
			// Started first by DS_Toolkit::run(), whatever its place here.
			'bot_shield_enabled' => self::feature( 'bot-shield', 'DS_Bot_Shield', array(
				'default' => 1, 'label' => 'Bot Shield', 'icon' => 'shield', 'scope' => 'fleet',
				'summary' => 'Refuses bot floods before the page is built: a per-address rate limit, a site-wide "under attack" check and a crawler blocklist.',
				'more'    => '<p>Protects against valid-URL request storms that page caching and Defender miss and that exhaust PHP workers (502s). Abusive traffic is refused in about 1 ms. Logged-in users, WP Engine internals and verified search engines are never touched. It ships in <strong>Monitor</strong> mode (counts, blocks nothing) so a day of numbers can be read before switching to Block. A Bot Shield box on the WordPress dashboard shows the mode and today\'s counts.</p>',
				'fields'  => array(
					// Monitor first: every rule is safe on its own, but there is no fast fleet-wide kill switch, so
					// blocking is switched on per site after its numbers are read.
					'bot_shield_mode'              => array( 'type' => 'choice', 'default' => 'monitor', 'label' => 'Mode', 'options' => array( 'monitor' => 'Monitor (count only, block nothing)', 'block' => 'Block (enforce)' ) ),
					'bot_shield_page_cap'          => array( 'type' => 'int', 'default' => 20, 'min' => 5, 'max' => 100000, 'label' => 'Pagination cap', 'help' => 'A /page/N/ address above this is checked, and refused when the page does not exist.', 'advanced' => true ),
					// A person browsing a cached site rarely makes more than about 20 PHP hits a minute; 180 only catches scrapers and floods.
					'bot_shield_ip_limit'          => array( 'type' => 'int', 'default' => 180, 'min' => 30, 'max' => 100000, 'label' => 'Per-address limit, per minute', 'advanced' => true ),
					'bot_shield_penalty_mins'      => array( 'type' => 'int', 'default' => 10, 'min' => 1, 'max' => 1440, 'label' => 'Penalty, minutes', 'advanced' => true ),
					'bot_shield_global_limit'      => array( 'type' => 'int', 'default' => 150, 'min' => 50, 'max' => 100000, 'label' => 'Site limit, per 10 seconds', 'advanced' => true ),
					'bot_shield_attack_mins'       => array( 'type' => 'int', 'default' => 10, 'min' => 1, 'max' => 1440, 'label' => 'Attack mode, minutes', 'advanced' => true ),
					// Off by default: WP Engine strips non-allowlisted cookies before PHP sees them, so a cookie-based
					// check cannot complete there (it fails open if enabled anyway).
					'bot_shield_challenge_enabled' => array( 'type' => 'bool', 'default' => 0, 'label' => 'Browser check when under attack', 'help' => 'Not on WP Engine: it removes the cookie the check relies on.', 'advanced' => true ),
					// msie / trident: Internet Explorer is gone since 2022; those strings are the "older browser" bot signature
					// WP Engine support identified in the sportsatthebeach flood.
					'bot_shield_ua_blocklist'      => array( 'type' => 'lines', 'default' => "mj12bot\ndotbot\nbytespider\npetalbot\nseekportbot\nbarkrowler\nblexbot\nmsie\ntrident/", 'label' => 'Blocked user agents', 'help' => 'One per line. Any user agent containing the text is answered 403.', 'rows' => 4, 'advanced' => true ),
					'bot_shield_ip_allowlist'      => array( 'type' => 'lines', 'default' => '', 'label' => 'Address allowlist', 'help' => 'Never limited or checked. One per line: an address, a range (203.0.113.0/24) or a prefix (203.0.113.).', 'rows' => 3, 'advanced' => true ),
					// Read-only numbers for an ops dashboard. Off by default; switched on per site that should be pollable.
					'bot_shield_stats_api'         => array( 'type' => 'bool', 'default' => 0, 'label' => 'Stats endpoint', 'help' => 'Lets an ops dashboard read today\'s counts at /wp-json/ds-toolkit/v1/bot-shield.', 'advanced' => true ),
					'bot_shield_stats_origin'      => array( 'type' => 'url', 'default' => 'https://dscommand.wpenginepowered.com', 'label' => 'Stats endpoint: allowed origin', 'help' => 'The one site whose browser pages may read the endpoint.', 'advanced' => true ),
				),
			) ),

			// Daily checks for signs of compromise plus instant new-administrator alerts (Aug/Sep 2026 campaign; see the class
			// docblock). The first run only records what is there (clone-safe); it emails on later change or a hard finding.
			'tripwire_enabled' => self::feature( 'tripwire', 'DS_Tripwire', array(
				'default' => 1, 'label' => 'Tripwire', 'icon' => 'warning', 'scope' => 'fleet',
				'summary' => 'Checks the site every day for signs of compromise and emails the team when something changes or a new administrator appears.',
				'more'    => '<p>Looks at must-use plugins, executable files in the web root, known malware markers, the toolkit\'s own files and the administrator list, and scans plugin and theme code by behaviour every hour. With it off the site still checks in with Design Shop HQ (see Report to Design Shop HQ), which then shows Tripwire as off.</p>',
				'fields'  => array( 'tripwire_alert_email' => array( 'type' => 'email', 'default' => 'design@leagueapps.com', 'label' => 'Alert email', 'help' => 'Where alerts are sent.', 'advanced' => true ) ),
			) ),

			'design_academy_enabled' => self::feature( 'design-academy', 'DS_Design_Academy', array(
				'default' => 1, 'label' => 'Design Academy Dashboard', 'icon' => 'welcome-learn-more', 'scope' => 'fleet',
				'summary' => 'A Design Academy panel at the top of the WordPress dashboard: a pinned getting-started course and the five newest tutorials.',
				'more'    => '<p>Tutorials come from <a href="https://designacademy.leagueapps.com/" target="_blank" rel="noopener">designacademy.leagueapps.com</a>, cached for 6 hours; if the academy cannot be reached the last good list is shown.</p>',
				'fields'  => array(
					'academy_pinned_label' => array( 'type' => 'text', 'default' => 'How to Edit Your Website: A Beginner\'s Guide to WordPress & Beaver Builder', 'label' => 'Pinned course: title', 'help' => 'The "Start here" item at the top of the panel.' ),
					'academy_pinned_url'   => array( 'type' => 'url', 'default' => 'https://designacademy.leagueapps.com/course/how-to-edit-your-website-a-beginners-guide-to-wordpress-beaverbuilder/', 'label' => 'Pinned course: link' ),
				),
			) ),

			'school_emblems_enabled' => self::feature( 'school-emblems', 'DS_School_Emblems', array(
				'default' => 1, 'label' => 'School Emblems (college logos)', 'icon' => 'awards', 'scope' => 'fleet',
				'summary' => 'On an athlete, the School Name box searches the Design Shop HQ library of college athletic logos and fills in School Logo.',
				'more'    => '<p>Search by name, team or initials (UCLA). Picking a school adds its logo to <strong>School Logo</strong> on Update, once per site; a logo the partner uploaded is never replaced.</p>',
			) ),

			// ---- settings without a feature class of their own ----

			// The link to Design Shop HQ: a check-in on HQ's interval, a copy of every Tripwire alert, and the toolkit
			// updates HQ rolls out. Its own feature since the 2026-11 audit (Tripwire used to start it, so a site with
			// Tripwire off went Silent on HQ and stopped taking updates). Loaded after Tripwire, as before.
			'tripwire_hq_report' => self::feature( 'hq-link', 'DS_HQ_Link', array(
				'default' => 1, 'label' => 'Report to Design Shop HQ', 'icon' => 'cloud-upload', 'scope' => 'fleet',
				'summary' => 'Sends a regular check-in and every Tripwire alert to the Design Shop HQ dashboard, so the team sees the whole fleet in one place.',
				'more'    => '<p>The check-in carries the toolkit version, the last scan and the current findings; a site that stops reporting shows as Silent on HQ. It keeps working with Tripwire switched off (HQ then shows Tripwire as off), and it is how HQ rolls a signed toolkit update out to this site. The email alert is unchanged. This site signs everything with its own key, which is kept on this site and never shown.</p>',
			) ),

			// An mu-plugin the toolkit installs and maintains (includes/class-ds-origin-guard-installer.php): it refuses the
			// flood's exact request shapes BEFORE plugins load, the layer Bot Shield cannot reach. The rules only refuse
			// traffic no real visitor produces, so it is on fleet-wide.
			'origin_guard_enabled' => array(
				'default' => 1, 'label' => 'Origin Guard', 'icon' => 'shield-alt', 'scope' => 'fleet',
				'summary' => 'A must-use plugin that refuses the request shapes of a bot flood before WordPress loads any plugin.',
				'more'    => '<p>Each attack request costs about 20 ms instead of a full page render (50 to 100 times more capacity on the attacked addresses). It rejects anonymous REST user listing (<code>/wp-json/wp/v2/users</code>), login POSTs that never loaded the login form, and XML-RPC. Logged-in users, authenticated API calls and normal visitors are never touched. Built after WP Engine ticket #8578620 (the August 2026 fleet flood).</p>',
				'fields'  => array(
					'origin_guard_block_login'  => array( 'type' => 'bool', 'default' => 1, 'label' => 'Block cold login POSTs', 'help' => 'Credential-stuffing POSTs that never rendered the login form.' ),
					'origin_guard_block_xmlrpc' => array( 'type' => 'bool', 'default' => 1, 'label' => 'Block XML-RPC', 'help' => 'Skipped automatically on a site running Jetpack or the WordPress mobile app.' ),
				),
			),
		);

		// MCP tool groups (the MCP tab): each off until switched on.
		foreach ( array( 'posts_pages', 'cpt', 'taxonomies', 'acf', 'toolkit_settings', 'bb', 'acf_schema', 'menus', 'maintenance', 'options', 'users' ) as $group ) {
			$all[ 'mcp_' . $group . '_enabled' ] = array( 'default' => 0, 'label' => 'MCP tools: ' . str_replace( '_', ' ', $group ), 'tab' => 'mcp' );
		}

		self::$all = $all;
		return $all;
	}

	/** What the loader needs: key => file, class and (when gated) min_blueprint, in load order. */
	public static function registry() {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( empty( $def['file'] ) ) {
				continue;
			}
			$out[ $key ] = array( 'file' => $def['file'], 'class' => $def['class'] );
			if ( ! empty( $def['min_blueprint'] ) ) {
				$out[ $key ]['min_blueprint'] = (int) $def['min_blueprint'];
			}
		}
		return $out;
	}

	/** The Beaver Builder blocks for the Modules group: key => label, desc. */
	public static function modules() {
		$all = self::all();
		$out = array();
		foreach ( self::MODULE_ORDER as $key ) {
			if ( isset( $all[ $key ] ) ) {
				$out[ $key ] = array( 'label' => $all[ $key ]['label'], 'desc' => $all[ $key ]['summary'] );
			}
		}
		return $out;
	}

	/**
	 * The default of every setting for a blueprint generation. A key with 'default_bp' is added only from that
	 * generation up, so older and unstamped installs never gain it (and so never show its toggle switched on).
	 */
	public static function defaults( $bp ) {
		$bp  = (int) $bp;
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( array_key_exists( 'default', $def ) && $bp >= (int) ( $def['default_bp'] ?? 0 ) ) {
				$out[ $key ] = $def['default'];
			}
			foreach ( (array) ( $def['fields'] ?? array() ) as $name => $field ) {
				if ( array_key_exists( 'default', $field ) && $bp >= (int) ( $field['default_bp'] ?? 0 ) ) {
					$out[ $name ] = $field['default'];
				}
			}
		}
		return $out;
	}

	/** Every setting the schema knows: name => array( type, default?, min?, max?, options? ). Toggles are bool. */
	public static function settings() {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			$out[ $key ] = array( 'type' => 'bool' ) + ( array_key_exists( 'default', $def ) ? array( 'default' => $def['default'] ) : array() );
			foreach ( (array) ( $def['fields'] ?? array() ) as $name => $field ) {
				$out[ $name ] = $field;
			}
		}
		return $out;
	}

	/**
	 * Clean the settings a form posted, by type and range. Only names the schema knows are touched; anything else
	 * passes through as it came (other code may keep its own keys in the option). A value that cannot be used
	 * (a word in a number box, a broken email) falls back to what is saved now, then to the default, so a typo never
	 * saves and never blanks a working setting.
	 *
	 * @param array $new      what was posted
	 * @param array $existing the saved option
	 */
	public static function sanitize( array $new, array $existing = array() ) {
		$known = self::settings();
		foreach ( $new as $name => $value ) {
			if ( ! isset( $known[ $name ] ) ) {
				continue;
			}
			$f    = $known[ $name ];
			$keep = array_key_exists( $name, $existing ) ? $existing[ $name ] : ( $f['default'] ?? '' );
			switch ( $f['type'] ) {
				case 'bool':
					$new[ $name ] = empty( $value ) || 'false' === $value ? 0 : 1;
					break;
				case 'int':
					if ( is_scalar( $value ) && '' !== trim( (string) $value ) && is_numeric( trim( (string) $value ) ) ) {
						$n = (int) round( (float) trim( (string) $value ) );
						if ( isset( $f['min'] ) ) { $n = max( (int) $f['min'], $n ); }
						if ( isset( $f['max'] ) ) { $n = min( (int) $f['max'], $n ); }
						$new[ $name ] = $n;
					} elseif ( is_scalar( $value ) && '' === trim( (string) $value ) && ! array_key_exists( 'default', $f ) ) {
						$new[ $name ] = ''; // an optional number (the login logo) can be cleared
					} else {
						$new[ $name ] = $keep;
					}
					break;
				case 'digits':
					$d            = is_scalar( $value ) ? preg_replace( '/\D+/', '', (string) $value ) : '';
					$new[ $name ] = '' !== $d ? $d : $keep;
					break;
				case 'email':
					$e            = is_scalar( $value ) ? sanitize_email( trim( (string) $value ) ) : '';
					$new[ $name ] = ( '' !== $e && is_email( $e ) ) ? $e : $keep;
					break;
				case 'url':
					$u            = is_scalar( $value ) ? esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) ) : '';
					$new[ $name ] = ( '' !== $u || '' === trim( (string) ( is_scalar( $value ) ? $value : 'x' ) ) ) ? $u : $keep;
					break;
				case 'choice':
					$new[ $name ] = ( is_scalar( $value ) && isset( $f['options'][ (string) $value ] ) ) ? (string) $value : $keep;
					break;
				case 'text':
					$new[ $name ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $keep;
					break;
				case 'lines':
					$new[ $name ] = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : $keep;
					break;
				case 'html':
					$new[ $name ] = is_scalar( $value ) ? wp_kses_post( (string) $value ) : $keep;
					break;
				case 'css':
					// Printed inside a <style> element: the one thing it must never contain is the end of that element.
					$new[ $name ] = is_scalar( $value ) ? preg_replace( '#</\s*style#i', '', (string) $value ) : $keep;
					break;
				case 'mappings':
					$rows = array();
					foreach ( (array) $value as $row ) {
						$row = (array) $row;
						$r   = array(
							'acf_field' => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $row['acf_field'] ?? '' ) ),
							'css_var'   => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $row['css_var'] ?? '' ) ),
							'fallback'  => trim( str_replace( array( ';', '{', '}', '<', '>' ), '', sanitize_text_field( (string) ( $row['fallback'] ?? '' ) ) ) ),
						);
						if ( '' !== $r['acf_field'] || '' !== $r['css_var'] || '' !== $r['fallback'] ) {
							$rows[] = $r;
						}
					}
					$new[ $name ] = $rows;
					break;
			}
		}
		return $new;
	}
}
