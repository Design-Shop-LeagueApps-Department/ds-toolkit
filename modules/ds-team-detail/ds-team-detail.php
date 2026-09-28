<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Team Detail — the single-team body, rendered from the current
 * team post's ACF fields. Built for the Single Teams template (gen 6+).
 *
 * Behaviour (mirrors the DS5 build):
 *  - LEFT column is the team's FEATURED IMAGE. If the team has no featured image
 *    the column is dropped entirely and the content spans 100%.
 *  - RIGHT column stacks Roster / Schedule / Coaches sections. EACH section is
 *    omitted (heading included) when its field is empty, so nothing shows for
 *    a team that hasn't filled that field in.
 *
 * No UABB dependency; fully SSH-editable (markup here, styles in css/ + includes/).
 *
 * @class DS_Team_Detail_Module
 */
class DS_Team_Detail_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Team Detail', 'ds-toolkit' ),
			'description'     => __( 'Single-team body: featured image (auto-hidden when absent) + Roster / Schedule / Coaches sections that hide when empty.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-team-detail/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-team-detail/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/**
	 * Line thickness defaults to 2px since 1.10.8 (it was 3). A module saved before the
	 * field existed has no value, and Beaver would fill it from the new default; pin those
	 * to the old 3 so nothing already on a site changes. Saved modules store their own value.
	 */
	public function filter_raw_settings_defaults( $settings, $defaults ) {
		if ( is_object( $settings ) && ! property_exists( $settings, 'divider_thickness' ) ) { $settings->divider_thickness = '3'; }
		return $settings;
	}

	/** Beaver Builder before 2.9 calls this one instead. */
	public function filter_raw_settings( $settings ) {
		return $this->filter_raw_settings_defaults( $settings, null );
	}

	/*
	 * Editing a team's content in Beaver Builder (Content Router route "Edit each post's content").
	 * On a team open in the builder, the "This team" section edits that team's own fields: photo
	 * (featured image), roster, schedule and coaches. The panel is pre-filled from those fields,
	 * edits stay in the builder draft (tc_pending) so the preview shows them, and publishing writes
	 * them to the same fields the dashboard edits (publish_team_content). Discard throws them away.
	 */

	/** The team open in Beaver Builder (page load or builder AJAX), or 0. */
	public static function editing_team_id() {
		if ( ! class_exists( 'FLBuilderModel' ) ) { return 0; }
		$active = FLBuilderModel::is_builder_active() || ( wp_doing_ajax() && isset( $_POST['fl_builder_data'] ) );
		if ( ! $active ) { return 0; }
		$pid = class_exists( 'DS_Content_Router_Module' ) ? DS_Content_Router_Module::builder_post_id() : (int) FLBuilderModel::get_post_id();
		return ( $pid && 'teams' === get_post_type( $pid ) && current_user_can( 'edit_post', $pid ) ) ? $pid : 0;
	}

	/** The ACF field names this module reads (its Content settings, with the defaults). */
	private static function team_fields( $s ) {
		$f = function ( $k, $d ) use ( $s ) { $v = trim( (string) ( $s->$k ?? '' ) ); return '' !== $v ? $v : $d; };
		return array( 'roster' => $f( 'roster_field', 'team_roster' ), 'sched' => $f( 'sched_field', 'schedule' ), 'coach' => $f( 'coach_field', 'team_coach' ) );
	}

	/** Raw (unformatted) field value, so shortcodes such as [ninja_tables] stay as typed. */
	private static function raw_field( $name, $pid ) {
		return function_exists( 'get_field' ) ? get_field( $name, $pid, false ) : get_post_meta( $pid, $name, true );
	}

	public function filter_settings( $settings, $helper ) {
		$pid = self::editing_team_id();
		if ( ! $pid || ! is_object( $settings ) || '1' === (string) ( $settings->tc_pending ?? '' ) ) { return $settings; }
		$f = self::team_fields( $settings );
		$settings->tc_roster = (string) self::raw_field( $f['roster'], $pid );
		$settings->tc_sched  = (string) self::raw_field( $f['sched'], $pid );
		$ids = array_filter( array_map( 'intval', (array) self::raw_field( $f['coach'], $pid ) ) );
		$settings->tc_coaches = implode( ',', $ids );
		$thumb = (int) get_post_thumbnail_id( $pid );
		$settings->tc_photo     = $thumb ? (string) $thumb : '';
		$settings->tc_photo_src = $thumb ? (string) wp_get_attachment_image_url( $thumb, 'large' ) : '';
		return $settings;
	}

	/**
	 * Writes a published team's "This team" edits to its fields, then clears them from the layout
	 * so the fields stay the one source. Hooked to fl_builder_after_save_layout.
	 */
	public static function publish_team_content( $post_id, $publish, $data, $settings ) {
		if ( ! $publish || 'teams' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$touched = false;
		foreach ( (array) $data as $node ) {
			if ( ! is_object( $node ) || 'ds-team-detail' !== ( $node->settings->type ?? '' ) || '1' !== (string) ( $node->settings->tc_pending ?? '' ) ) { continue; }
			$s = $node->settings; $f = self::team_fields( $s ); $touched = true;
			$save = function ( $name, $value ) use ( $post_id ) {
				if ( function_exists( 'update_field' ) ) { update_field( $name, $value, $post_id ); } else { update_post_meta( $post_id, $name, $value ); }
			};
			// Write only what was changed, so an untouched field keeps its exact stored markup (the
			// editor drops <p> tags the dashboard kept, which would otherwise rewrite every field).
			$same_text = function ( $a, $b ) { $n = function ( $v ) { return trim( preg_replace( '/\s+/', ' ', wpautop( trim( (string) $v ) ) ) ); }; return $n( $a ) === $n( $b ); };
			foreach ( array( 'roster' => 'tc_roster', 'sched' => 'tc_sched' ) as $k => $prop ) {
				$new = wp_kses_post( (string) ( $s->$prop ?? '' ) );
				if ( ! $same_text( $new, self::raw_field( $f[ $k ], $post_id ) ) ) { $save( $f[ $k ], $new ); }
			}
			$ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) ( $s->tc_coaches ?? '' ) ) ), function ( $id ) { return $id > 0 && 'staff' === get_post_type( $id ); } ) );
			if ( $ids !== array_values( array_filter( array_map( 'intval', (array) self::raw_field( $f['coach'], $post_id ) ) ) ) ) { $save( $f['coach'], $ids ); }
			$photo = (int) ( $s->tc_photo ?? 0 );
			if ( $photo !== (int) get_post_thumbnail_id( $post_id ) ) {
				if ( $photo && wp_attachment_is_image( $photo ) ) { set_post_thumbnail( $post_id, $photo ); } elseif ( ! $photo ) { delete_post_thumbnail( $post_id ); }
			}
			break; // one Team Detail per team page
		}
		if ( ! $touched ) { return; }
		foreach ( array( 'published', 'draft' ) as $status ) {
			$layout = FLBuilderModel::get_layout_data( $status, $post_id );
			$clean  = array();
			foreach ( (array) $layout as $id => $node ) {
				$node = unserialize( serialize( $node ) );
				if ( is_object( $node ) && 'ds-team-detail' === ( $node->settings->type ?? '' ) ) {
					foreach ( array( 'tc_pending', 'tc_roster', 'tc_sched', 'tc_coaches', 'tc_photo', 'tc_photo_src' ) as $k ) { unset( $node->settings->$k ); }
				}
				$clean[ $id ] = $node;
			}
			FLBuilderModel::update_layout_data( $clean, $status, $post_id );
		}
	}

	/** Marks "This team" edits as pending when the panel is saved on a team page (all BB versions). */
	public function update( $settings ) {
		if ( self::editing_team_id() && is_object( $settings ) ) { $settings->tc_pending = '1'; }
		return $settings;
	}

	/**
	 * What the page shows for one of the team's fields: while the team is open in the builder, the
	 * panel's values (so an unpublished edit previews); otherwise the saved field.
	 */
	private function team_value( $what, $pid ) {
		$s = $this->settings;
		if ( self::editing_team_id() === (int) $pid && property_exists( $s, 'tc_roster' ) ) {
			switch ( $what ) {
				case 'roster': return (string) ( $s->tc_roster ?? '' );
				case 'sched':  return (string) ( $s->tc_sched ?? '' );
				case 'coach':  return array_filter( array_map( 'intval', explode( ',', (string) ( $s->tc_coaches ?? '' ) ) ) );
				case 'photo':  return (int) ( $s->tc_photo ?? 0 );
			}
		}
		$f = self::team_fields( $s );
		switch ( $what ) {
			case 'roster': return function_exists( 'get_field' ) ? (string) get_field( $f['roster'], $pid ) : (string) get_post_meta( $pid, $f['roster'], true );
			case 'sched':  return function_exists( 'get_field' ) ? (string) get_field( $f['sched'], $pid ) : (string) get_post_meta( $pid, $f['sched'], true );
			case 'coach':  return function_exists( 'get_field' ) ? get_field( $f['coach'], $pid ) : get_post_meta( $pid, $f['coach'], true );
			case 'photo':  return (int) get_post_thumbnail_id( $pid );
		}
		return '';
	}

	/** True when a wysiwyg/text value carries real, visible content. */
	private function has_content( $raw ) {
		$raw = (string) $raw;
		if ( preg_match( '/<(img|iframe|video|audio|embed|table|ul|ol|blockquote)\b/i', $raw ) ) { return true; }
		$plain = trim( str_replace( "\xc2\xa0", '', wp_strip_all_tags( $raw ) ) );
		return '' !== $plain;
	}

	/** A titled section wrapper (heading + divider + body). */
	private function section_html( $title, $tag, $body, $extra_class = '' ) {
		$head = '';
		if ( '' !== trim( (string) $title ) ) {
			$head  = '<' . $tag . ' class="ds-teamdetail-title">' . DS_Module_UI::inline( $title ) . '</' . $tag . '>';
			if ( ( $this->settings->divider_show ?? 'yes' ) === 'yes' ) {
				$head .= '<span class="ds-teamdetail-divider" aria-hidden="true"></span>';
			}
		}
		$cls = 'ds-teamdetail-section' . ( $extra_class ? ' ' . $extra_class : '' );
		return '<section class="' . esc_attr( $cls ) . '">' . $head . '<div class="ds-teamdetail-body">' . $body . '</div></section>';
	}

	/** Roster / Schedule — a wysiwyg ACF field, rendered only when filled. */
	private function wysiwyg_section( $key, $def_title, $def_field, $tag, $pid ) {
		$s = $this->settings;
		if ( ( $s->{ $key . '_show' } ?? 'yes' ) !== 'yes' ) { return ''; }
		$raw = (string) $this->team_value( $key, $pid );
		if ( ! $this->has_content( $raw ) ) { return ''; }
		$title = (string) ( $s->{ $key . '_title' } ?? $def_title );
		return $this->section_html( $title, $tag, apply_filters( 'the_content', $raw ) );
	}

	/** Coaches — the team_coach relationship, rendered as staff cards. */
	private function coaches_section( $tag, $pid ) {
		$s = $this->settings;
		if ( ( $s->coach_show ?? 'yes' ) !== 'yes' ) { return ''; }
		$rel = $this->team_value( 'coach', $pid );

		$ids = array();
		if ( is_array( $rel ) ) {
			foreach ( $rel as $r ) {
				if ( is_object( $r ) && isset( $r->ID ) ) { $ids[] = (int) $r->ID; }
				elseif ( is_array( $r ) && isset( $r['ID'] ) ) { $ids[] = (int) $r['ID']; }
				elseif ( is_numeric( $r ) ) { $ids[] = (int) $r; }
			}
		} elseif ( is_numeric( $rel ) ) { $ids[] = (int) $rel; }
		$ids = array_filter( array_unique( $ids ) );
		if ( empty( $ids ) ) { return ''; }

		$show_contact = ( $s->coach_show_contact ?? 'yes' ) === 'yes';

		// Photo fallback for coaches with no featured image = the Theme Setting Social Card.
		$ph = DS_Card::placeholder_image();

		$cards = '';
		foreach ( $ids as $cid ) {
			if ( 'publish' !== get_post_status( $cid ) ) { continue; }
			$img  = get_the_post_thumbnail_url( $cid, 'large' );
			$name = get_the_title( $cid );
			$role = (string) get_post_meta( $cid, 'staff_title', true );

			$icons = '';
			if ( $show_contact ) {
				$icons .= DS_Card::contact_link( get_post_meta( $cid, 'staff_email', true ),     DS_Card::icon( 'mail' ),      __( 'Email', 'ds-toolkit' ), 'mail' );
				$icons .= DS_Card::contact_link( get_post_meta( $cid, 'staff_phone', true ),     DS_Card::icon( 'phone' ),     __( 'Phone', 'ds-toolkit' ), 'tel' );
				$icons .= DS_Card::contact_link( get_post_meta( $cid, 'staff_instagram', true ), DS_Card::icon( 'instagram' ), 'Instagram' );
				$icons .= DS_Card::contact_link( get_post_meta( $cid, 'staff_linkedin', true ),  DS_Card::icon( 'linkedin' ),  'LinkedIn' );
				$icons .= DS_Card::contact_link( get_post_meta( $cid, 'staff_facebook', true ),  DS_Card::icon( 'facebook' ),  'Facebook' );
				$icons .= DS_Card::contact_link( get_post_meta( $cid, 'staff_x', true ),         DS_Card::icon( 'x' ),         'X' );
			}

			// Featured image, else the Social Card; the Photo Placeholder Background
			// colour shows only if neither resolves.
			$src = $img ?: $ph;
			$bg  = $src ? ' style="background-image:url(' . esc_url( $src ) . ')"' : '';
			// Stretched link over the whole card -> the coach's profile (contact icons
			// stay clickable above it via z-index). Off when "Link Card to Profile" = No.
			$link = ( ( $s->coach_card_link ?? 'yes' ) === 'yes' )
				? DS_Card::stretched_link( get_permalink( $cid ), $name )
				: '';
			$cards .= '<div class="ds-people-card">'
				. $link
				. '<div class="ds-people-photo"' . $bg . '></div>'
				. '<div class="ds-people-body">'
				. ( '' !== $name ? '<h3 class="ds-people-name">' . DS_Module_UI::inline( $name ) . '</h3>' : '' )
				. ( '' !== $role ? '<span class="ds-people-role">' . esc_html( $role ) . '</span>' : '' )
				. ( '' !== $icons ? '<div class="ds-people-contacts">' . $icons . '</div>' : '' )
				. '</div></div>';
		}
		if ( '' === $cards ) { return ''; }

		$title = (string) ( $s->coach_title ?? 'Coaches' );
		$head  = '';
		if ( '' !== trim( $title ) ) {
			$head  = '<' . $tag . ' class="ds-teamdetail-title">' . DS_Module_UI::inline( $title ) . '</' . $tag . '>';
			if ( ( $s->divider_show ?? 'yes' ) === 'yes' ) { $head .= '<span class="ds-teamdetail-divider" aria-hidden="true"></span>'; }
		}
		return '<section class="ds-teamdetail-section ds-teamdetail-section--coaches">' . $head . '<div class="ds-people-grid ds-people-grid--coaches">' . $cards . '</div></section>';
	}

	/** Entry point (called by includes/frontend.php). */
	public function render_body() {
		$s   = $this->settings;
		$pid = get_the_ID();
		if ( ! $pid ) { return; }

		$tag = in_array( $s->heading_tag ?? 'h3', array( 'h2', 'h3', 'h4', 'h5' ), true ) ? ( $s->heading_tag ?? 'h3' ) : 'h3';

		$thumb   = (int) $this->team_value( 'photo', $pid );
		$has_img = $thumb > 0;
		$img     = $has_img ? (string) wp_get_attachment_image_url( $thumb, 'large' ) : '';

		$out  = $this->wysiwyg_section( 'roster', __( 'Team Roster', 'ds-toolkit' ), 'team_roster', $tag, $pid );
		$out .= $this->wysiwyg_section( 'sched', __( 'Schedule', 'ds-toolkit' ), 'schedule', $tag, $pid );
		$out .= $this->coaches_section( $tag, $pid );

		// Nothing filled in yet. A blank team page reads as broken, so unless the
		// notice is switched off we show a "Coming Soon" placeholder instead of
		// leaving the page empty. Fires whenever the sections are empty — a team
		// with only a featured image is still a team with no information on it.
		if ( '' === $out ) {
			$empty_show = ( $s->empty_show ?? 'yes' ) === 'yes';
			$empty_text = trim( (string) ( $s->empty_text ?? '' ) );
			if ( '' === $empty_text ) {
				$empty_text = __( 'Coming Soon', 'ds-toolkit' );
			}

			if ( $empty_show ) {
				$heading = trim( (string) ( $s->empty_heading ?? '' ) );
				$desc    = trim( (string) ( $s->empty_desc ?? '' ) );
				$align   = ( ( $s->empty_align ?? 'center' ) === 'left' ) ? 'left' : 'center';

				$out = '<div class="ds-teamdetail-empty ds-teamdetail-empty--' . esc_attr( $align ) . '">';
				if ( '' !== $empty_text ) {
					$out .= '<span class="ds-teamdetail-soon">' . esc_html( $empty_text ) . '</span>';
				}
				if ( '' !== $heading ) {
					$out .= '<' . $tag . ' class="ds-teamdetail-empty-title">' . DS_Module_UI::inline( $heading ) . '</' . $tag . '>';
				}
				if ( '' !== $desc ) {
					$out .= '<div class="ds-teamdetail-empty-desc">' . wpautop( wp_kses_post( $desc ) ) . '</div>';
				}
				$out .= '</div>';
			} elseif ( ! $has_img ) {
				// Notice off and nothing to show at all — keep the old behaviour of
				// rendering nothing live, with a builder-only explanation.
				if ( FLBuilderModel::is_builder_active() ) {
					echo '<div class="ds-teamdetail"><div class="ds-teamdetail-main"><p style="padding:14px;opacity:.7">'
						. esc_html__( 'This team has no roster, schedule, coaches or featured image yet — nothing renders on the live page.', 'ds-toolkit' )
						. '</p></div></div>';
				}
				return;
			}
		}

		$cls = 'ds-teamdetail' . ( $has_img ? ' ds-teamdetail--hasimg' : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		if ( $has_img ) {
			echo '<div class="ds-teamdetail-media"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $pid ) ) . '" loading="lazy" /></div>';
		}
		echo '<div class="ds-teamdetail-main">' . $out . '</div>';
		echo '</div>';
	}
}

// Reads the current team's fields, so it only belongs in the Single Teams template (a saved
// template the Content Router inserts) or a Themer layout. Hidden from the module list on ordinary pages.
DS_Module_UI::offer_only_in_templates( 'ds-team-detail' );

// "This team" is only meaningful with a team open in the builder: drop it from the form everywhere
// else. Beaver Builder loads the forms in a separate request (the post URL + fl_builder_load_settings_config,
// served on 'wp' at 10), so this runs before it and decides by the queried post.
add_action( 'wp', function () {
	if ( ( ! isset( $_GET['fl_builder'] ) && ! isset( $_GET['fl_builder_load_settings_config'] ) ) || ! class_exists( 'FLBuilderModel' ) || ! isset( FLBuilderModel::$modules['ds-team-detail'] ) ) { return; }
	$pid = is_singular() ? (int) get_queried_object_id() : 0;
	if ( $pid && 'teams' === get_post_type( $pid ) && current_user_can( 'edit_post', $pid ) ) { return; }
	unset( FLBuilderModel::$modules['ds-team-detail']->form['content']['sections']['team_content'] );
}, 5 );
add_action( 'fl_builder_after_save_layout', array( 'DS_Team_Detail_Module', 'publish_team_content' ), 10, 4 );

FLBuilder::register_module( 'DS_Team_Detail_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			// Only while a team is open in Beaver Builder (removed from the form everywhere else,
			// see below). Pre-filled from the team's own fields; Publish writes them back.
			'team_content' => array(
				'title'       => __( 'This team', 'ds-toolkit' ),
				'description' => __( 'This team’s own photo, roster, schedule and coaches: the same fields as the Teams screen in the dashboard. Changes show here straight away and go live when you Publish.', 'ds-toolkit' ),
				'fields'      => array(
					'tc_photo'   => array( 'type' => 'photo', 'label' => __( 'Team Photo', 'ds-toolkit' ), 'show_remove' => true, 'help' => __( 'The team’s featured image. Remove it and the roster spans the full width.', 'ds-toolkit' ) ),
					'tc_roster'  => array( 'type' => 'editor', 'label' => __( 'Roster', 'ds-toolkit' ), 'media_buttons' => true, 'wpautop' => false, 'rows' => 8 ),
					'tc_sched'   => array( 'type' => 'editor', 'label' => __( 'Schedule', 'ds-toolkit' ), 'media_buttons' => false, 'wpautop' => false, 'rows' => 6, 'help' => __( 'Paste a Ninja Tables shortcode (e.g. [ninja_tables id="12"]) or type the schedule.', 'ds-toolkit' ) ),
					'tc_coaches' => array( 'type' => 'suggest', 'label' => __( 'Coaches', 'ds-toolkit' ), 'action' => 'fl_as_posts', 'data' => 'staff', 'help' => __( 'Type a staff name; picks show as pills, in this order.', 'ds-toolkit' ) ),
				),
			),
			'layout' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'media_width'  => array( 'type' => 'unit', 'label' => __( 'Image Column Width', 'ds-toolkit' ), 'default' => '38', 'description' => '%', 'slider' => array( 'min' => 20, 'max' => 60, 'step' => 1 ), 'help' => __( 'Width of the featured-image column. When the team has NO featured image the column is removed and the content spans 100%.', 'ds-toolkit' ) ),
					'media_gap'    => array( 'type' => 'unit', 'label' => __( 'Column Gap', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'heading_tag'  => array( 'type' => 'select', 'label' => __( 'Section Heading Tag', 'ds-toolkit' ), 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3 (default)', 'h4' => 'H4', 'h5' => 'H5' ) ),
					'divider_show' => array( 'type' => 'select', 'label' => __( 'Section Divider', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
				),
			),
			'empty_cfg' => array(
				'title'       => __( 'Empty Team Notice', 'ds-toolkit' ),
				'description' => __( 'What a team page shows before its roster, schedule or coaches have been filled in.', 'ds-toolkit' ),
				'fields'      => array(
					'empty_show' => array(
						'type'    => 'select',
						'label'   => __( 'Show notice when the team is empty', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes — show the placeholder', 'ds-toolkit' ), 'no' => __( 'No — leave the page blank', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'empty_text', 'empty_heading', 'empty_desc', 'empty_align' ) ) ),
						'help'    => __( 'A team with nothing filled in renders as an empty page, which reads as broken. This shows a proper placeholder instead.', 'ds-toolkit' ),
					),
					'empty_text' => array(
						'type'        => 'text',
						'label'       => __( 'Badge', 'ds-toolkit' ),
						'default'     => 'Coming Soon',
						'connections' => array( 'string' ),
						'help'        => __( 'Small pill above the heading. Leave blank to hide it.', 'ds-toolkit' ),
					),
					'empty_heading' => array(
						'type'        => 'text',
						'label'       => __( 'Heading', 'ds-toolkit' ),
						'default'     => 'Roster and Schedule Coming Soon',
						'connections' => array( 'string' ),
						'help'        => __( 'Leave blank to hide. Wrap a word in {a}…{/a} to accent it.', 'ds-toolkit' ),
					),
					'empty_desc' => array(
						'type'          => 'editor',
						'label'         => __( 'Description', 'ds-toolkit' ),
						'media_buttons' => false,
						'rows'          => 4,
						'wpautop'       => false,
						'default'       => "This team's roster, coaching staff and game schedule will be published here shortly. Rosters and registration are managed on our LeagueApps site in the meantime.",
						'connections'   => array( 'string' ),
					),
					'empty_align' => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ),
					),
				),
			),
			'sections_cfg' => array(
				'title'  => __( 'Sections (auto-hide when empty)', 'ds-toolkit' ),
				'fields' => array(
					'roster_show'  => array( 'type' => 'select', 'label' => __( 'Roster Section', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'roster_title', 'roster_field' ) ) ) ),
					'roster_title' => array( 'type' => 'text', 'label' => __( 'Roster Title', 'ds-toolkit' ), 'default' => 'Team Roster' ),
					'roster_field' => array( 'type' => 'text', 'label' => __( 'Roster ACF Field', 'ds-toolkit' ), 'default' => 'team_roster' ),
					'sched_show'   => array( 'type' => 'select', 'label' => __( 'Schedule Section', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'sched_title', 'sched_field' ) ) ) ),
					'sched_title'  => array( 'type' => 'text', 'label' => __( 'Schedule Title', 'ds-toolkit' ), 'default' => 'Schedule' ),
					'sched_field'  => array( 'type' => 'text', 'label' => __( 'Schedule ACF Field', 'ds-toolkit' ), 'default' => 'schedule' ),
					'coach_show'   => array( 'type' => 'select', 'label' => __( 'Coaches Section', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'coach_title', 'coach_field', 'coach_cols', 'coach_card_link', 'coach_show_contact' ) ) ) ),
					'coach_title'  => array( 'type' => 'text', 'label' => __( 'Coaches Title', 'ds-toolkit' ), 'default' => 'Coaches' ),
					'coach_field'  => array( 'type' => 'text', 'label' => __( 'Coaches ACF Field (relationship)', 'ds-toolkit' ), 'default' => 'team_coach' ),
					'coach_cols'   => array( 'type' => 'unit', 'label' => __( 'Coach Columns', 'ds-toolkit' ), 'default' => '2', 'slider' => array( 'min' => 1, 'max' => 4, 'step' => 1 ) ),
					'coach_card_link' => array( 'type' => 'select', 'label' => __( 'Link Card to Profile', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Makes the whole coach card click through to that staff member’s page. Contact icons stay independently clickable.', 'ds-toolkit' ) ),
					'coach_show_contact' => array( 'type' => 'select', 'label' => __( 'Coach Contact Icons', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'sections' => array( 'coach_icons' ) ) ), 'help' => __( 'Mail / phone / social buttons. Style them under the Style tab → Coach Icons.', 'ds-toolkit' ) ),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'colors' => array(
				'title'  => __( 'Section Colours', 'ds-toolkit' ),
				'fields' => array(
					'title_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Section Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'body_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Body Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
				),
			),
			'divider' => array(
				'title'  => __( 'Divider', 'ds-toolkit' ),
				'fields' => array(
					'divider_width'     => array( 'type' => 'unit', 'label' => __( 'Length', 'ds-toolkit' ), 'default' => '48', 'description' => 'px', 'slider' => array( 'min' => 8, 'max' => 300, 'step' => 1 ) ),
					'divider_thickness' => array( 'type' => 'unit', 'label' => __( 'Thickness', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'section_gap'  => array( 'type' => 'unit', 'label' => __( 'Section Gap', 'ds-toolkit' ), 'default' => '36', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 8, 'max' => 90, 'step' => 1 ) ),
					'media_radius' => array( 'type' => 'unit', 'label' => __( 'Image Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank inherits the global Corner Radius theme setting.', 'ds-toolkit' ) ),
				),
			),
			'typography' => array(
				'title'  => __( 'Section Typography', 'ds-toolkit' ),
				'fields' => array(
					'title_typography' => array( 'type' => 'typography', 'label' => __( 'Section Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-teamdetail-title' ) ),
					'body_typography'  => array( 'type' => 'typography', 'label' => __( 'Body Text', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-teamdetail-body' ) ),
				),
			),
			'coach_card' => array(
				'title'  => __( 'Coach Card', 'ds-toolkit' ),
				'fields' => array(
					'coach_card_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank uses a light grey.', 'ds-toolkit' ) ),
					'coach_card_radius'       => array( 'type' => 'unit', 'label' => __( 'Card Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank inherits the global Corner Radius theme setting.', 'ds-toolkit' ) ),
					'coach_card_padding'      => array( 'type' => 'dimension', 'label' => __( 'Card Body Padding', 'ds-toolkit' ), 'default' => '', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true, 'help' => __( 'Blank keeps the default 18 / 16 / 22.', 'ds-toolkit' ) ),
					'coach_card_border_width' => array( 'type' => 'unit', 'label' => __( 'Card Border Width', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ), 'help' => __( '0 = no border.', 'ds-toolkit' ) ),
					'coach_card_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'coach_card_shadow'       => array( 'type' => 'select', 'label' => __( 'Card Shadow', 'ds-toolkit' ), 'default' => 'none', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'sm' => __( 'Subtle', 'ds-toolkit' ), 'md' => __( 'Medium', 'ds-toolkit' ), 'lg' => __( 'Large', 'ds-toolkit' ) ) ),
					'coach_card_hover_lift'   => array( 'type' => 'select', 'label' => __( 'Card Hover Lift', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ) ),
					'coach_gap'               => array( 'type' => 'unit', 'label' => __( 'Card Grid Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				),
			),
			'coach_photo' => array(
				'title'  => __( 'Coach Photo', 'ds-toolkit' ),
				'fields' => array(
					'coach_photo_ratio'     => array( 'type' => 'select', 'label' => __( 'Photo Aspect Ratio', 'ds-toolkit' ), 'default' => '3/4', 'options' => array( '3/4' => __( 'Portrait (3:4)', 'ds-toolkit' ), '1/1' => __( 'Square (1:1)', 'ds-toolkit' ), '4/5' => __( 'Tall (4:5)', 'ds-toolkit' ), '4/3' => __( 'Landscape (4:3)', 'ds-toolkit' ), '16/9' => __( 'Wide (16:9)', 'ds-toolkit' ) ) ),
					'coach_photo_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Photo Placeholder Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Shown for coaches with no photo. Blank uses the global Headings colour.', 'ds-toolkit' ) ),
					'coach_photo_grayscale' => array( 'type' => 'select', 'label' => __( 'Photo Grayscale', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'Off', 'ds-toolkit' ), 'always' => __( 'Always Grayscale', 'ds-toolkit' ), 'hover' => __( 'Colour on Hover', 'ds-toolkit' ) ) ),
				),
			),
			'coach_text' => array(
				'title'  => __( 'Coach Text', 'ds-toolkit' ),
				'fields' => array(
					'coach_text_align'      => array( 'type' => 'select', 'label' => __( 'Text Alignment', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ) ),
					'coach_text_gap'        => array( 'type' => 'unit', 'label' => __( 'Name / Role Spacing', 'ds-toolkit' ), 'default' => '5', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 24, 'step' => 1 ) ),
					'coach_name_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Coach Name Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'coach_name_typography' => array( 'type' => 'typography', 'label' => __( 'Coach Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-name' ) ),
					'coach_role_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Coach Role Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'coach_role_typography' => array( 'type' => 'typography', 'label' => __( 'Coach Role Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-role' ) ),
				),
			),
			'coach_icons' => array(
				'title'  => __( 'Coach Icons', 'ds-toolkit' ),
				'fields' => array(
					'coach_icon_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Glyph Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank = white.', 'ds-toolkit' ) ),
					'coach_icon_color_hover'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Glyph Colour (Hover)', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'coach_icon_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank uses the global Accent colour.', 'ds-toolkit' ) ),
					'coach_icon_bg_hover'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background (Hover)', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'coach_icon_size'         => array( 'type' => 'unit', 'label' => __( 'Button Size', 'ds-toolkit' ), 'default' => '38', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 28, 'max' => 64, 'step' => 1 ) ),
					'coach_icon_glyph_size'   => array( 'type' => 'unit', 'label' => __( 'Glyph Size', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 12, 'max' => 32, 'step' => 1 ), 'help' => __( 'Keep below the button size.', 'ds-toolkit' ) ),
					'coach_icon_radius'       => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '50', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 50, 'step' => 1 ), 'help' => __( '50% = circle; lower for a rounded square.', 'ds-toolkit' ) ),
					'coach_icon_border_width' => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 4, 'step' => 1 ), 'help' => __( '0 = no border (use with a transparent background for ghost icons).', 'ds-toolkit' ) ),
					'coach_icon_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'coach_icon_hover_lift'   => array( 'type' => 'unit', 'label' => __( 'Hover Lift', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 8, 'step' => 1 ), 'help' => __( 'How far the icon rises on hover. 0 = none.', 'ds-toolkit' ) ),
					'coach_icon_gap'          => array( 'type' => 'unit', 'label' => __( 'Icon Spacing', 'ds-toolkit' ), 'default' => '10', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'coach_icon_top_gap'      => array( 'type' => 'unit', 'label' => __( 'Icons Top Spacing', 'ds-toolkit' ), 'default' => '12', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'coach_icon_align'        => array( 'type' => 'select', 'label' => __( 'Icons Alignment', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'flex-start' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'flex-end' => __( 'Right', 'ds-toolkit' ) ) ),
				),
			),
		),
	),
) );
