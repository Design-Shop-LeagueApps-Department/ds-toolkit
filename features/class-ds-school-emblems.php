<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * School Emblems: pick a college on an athlete and its athletic logo comes with it.
 *
 * Zay, 2026-10-26 (Ethos alumni page): "how I can get the logos to show of the colleges without needed to download
 * them". Design Shop HQ keeps one library of college athletic logos (/emblems/ on HQ, NCAA, NAIA and junior colleges)
 * and publishes it as two kinds of static files: index.json (every school, its acronyms and its logo file) and one
 * 512 px PNG per school.
 *
 * On any ACF field named school_name that has a school_logo image field beside it (the Athlete Settings group on every
 * Launchpad generation), the School Name box becomes a search over that library: name, short name, team name or
 * initials ("UCLA", "TAMU", "UConn"). On save, the picked school's logo is downloaded from HQ's fixed https address,
 * checked to be a real PNG of library size, added to this site's Media Library once (later athletes from the same
 * school reuse it; the library md5 identifies the version) and put in School Logo. So a site never needs HQ to SHOW a logo: HQ is only asked when a partner picks a school.
 *
 * A logo the partner uploaded themselves is never replaced: the toolkit only fills School Logo when it is empty or
 * still holds the logo the toolkit put there (AUTO_META). Typing a name that is not in the library works as before.
 */
class DS_School_Emblems {

	const HQ          = 'https://designshophq.wpenginepowered.com/wp-content/uploads/dshq-emblems/';
	const INDEX_URL   = self::HQ . 'index.json';
	const LOGO_BASE   = self::HQ . 'logos/';
	const NAME_FIELD  = 'school_name';
	const LOGO_FIELD  = 'school_logo';
	const CACHE       = 'ds_school_emblems_index';      // transient, 12 hours
	const LAST_GOOD   = 'ds_school_emblems_index_last'; // option (not autoloaded): served when HQ cannot be reached
	const AJAX        = 'ds_school_emblems_index';
	const NONCE       = 'ds_school_emblems';
	const ID_META     = '_ds_school_id';                // on the post: the picked school's library ID
	const AUTO_META   = '_ds_school_logo_auto';         // on the post: the attachment the toolkit put in School Logo
	const ATT_ID      = '_ds_school_emblem';            // on the attachment: library ID
	const ATT_MD5     = '_ds_school_emblem_md5';        // on the attachment: library md5 of that logo
	const NOTICE      = 'ds_school_emblems_notice_';    // + user ID: a save problem to show on the next screen

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'acf/render_field/name=' . self::NAME_FIELD, array( $this, 'render' ), 20 );
		add_action( 'acf/input/admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_index' ) );
		add_action( 'acf/save_post', array( $this, 'save' ), 20 ); // after ACF has written the fields
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/* ------------------------------------------------------------------ fields */

	/** The school_logo image field in the same field group as this school_name field, or null. Top level only. */
	private function logo_field( $name_field ) {
		if ( ! function_exists( 'acf_get_fields' ) || empty( $name_field['parent'] ) || ! function_exists( 'acf_get_field_group' ) || ! acf_get_field_group( $name_field['parent'] ) ) {
			return null;
		}
		foreach ( (array) acf_get_fields( $name_field['parent'] ) as $f ) {
			if ( self::LOGO_FIELD === ( $f['name'] ?? '' ) && 'image' === ( $f['type'] ?? '' ) ) {
				return $f;
			}
		}
		return null;
	}

	/**
	 * True when School Logo may be replaced: it is empty, still holds the logo the toolkit put there, or holds a
	 * placeholder (see is_placeholder()). Anything else is a logo the partner chose and stays.
	 */
	public static function may_replace( $current_id, $auto_id, $placeholder = false ) {
		$current_id = (int) $current_id;
		return 0 === $current_id || $current_id === (int) $auto_id || (bool) $placeholder;
	}

	/**
	 * A School Logo image that is not a school's logo: the club's own logo (Partner Settings), the blueprint's
	 * "LeagueApps Logo" (every LP7 sample athlete carries it, so every clone would), or one image used for athletes
	 * at 3+ different schools. Two names are allowed on purpose: renamed schools (Ethos: "Martin Methodist" and
	 * "UT Southern" share the UT Southern logo).
	 */
	public static function is_placeholder( $att_id ) {
		global $wpdb;
		$att_id = (int) $att_id;
		if ( $att_id <= 0 ) {
			return false;
		}
		if ( $att_id === (int) get_option( 'options_partner_logo' ) || $att_id === (int) get_theme_mod( 'custom_logo' ) ) {
			return true;
		}
		if ( 'leagueapps logo' === strtolower( trim( (string) get_the_title( $att_id ) ) ) ) {
			return true;
		}
		$schools = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT LOWER(TRIM(n.meta_value))) FROM {$wpdb->postmeta} l
			 JOIN {$wpdb->postmeta} n ON n.post_id = l.post_id AND n.meta_key = %s AND n.meta_value <> ''
			 WHERE l.meta_key = %s AND l.meta_value = %s",
			self::NAME_FIELD,
			self::LOGO_FIELD,
			(string) $att_id
		) );
		return $schools >= 3;
	}

	/** may_replace() for one post's School Logo field. */
	private function replaceable( $post_id, array $logo ) {
		$current = get_post_meta( $post_id, $logo['name'], true );
		$auto    = get_post_meta( $post_id, self::AUTO_META, true );
		return self::may_replace( $current, $auto, self::may_replace( $current, $auto ) ? false : self::is_placeholder( $current ) );
	}

	/** Prints the hidden school ID and the chip holder under the School Name box. */
	public function render( $field ) {
		$logo = $this->logo_field( $field );
		if ( ! $logo ) {
			return;
		}
		$post_id = function_exists( 'acf_get_form_data' ) ? acf_get_form_data( 'post_id' ) : 0;
		$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
		$sid     = $post_id ? (string) get_post_meta( $post_id, self::ID_META, true ) : '';
		$ours    = $post_id ? $this->replaceable( $post_id, $logo ) : true;
		printf(
			'<input type="hidden" class="ds-school-id" name="ds_school_emblem[%1$s]" value="%2$s" data-logo-ours="%3$d">',
			esc_attr( $field['key'] ),
			esc_attr( $sid ),
			$ours ? 1 : 0
		);
		static $nonce_done = false;
		if ( ! $nonce_done ) {
			wp_nonce_field( self::NONCE, 'ds_school_emblem_nonce', false );
			$nonce_done = true;
		}
		echo '<div class="ds-school-chip" hidden></div>';
	}

	public function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		wp_enqueue_style( 'ds-school-emblems', DS_TOOLKIT_URL . 'assets/css/school-emblems.css', array(), DS_TOOLKIT_VERSION );
		wp_enqueue_script( 'ds-school-emblems', DS_TOOLKIT_URL . 'assets/js/school-emblems.js', array(), DS_TOOLKIT_VERSION, true );
		wp_localize_script( 'ds-school-emblems', 'dsSchoolEmblems', array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'action' => self::AJAX,
			'nonce'  => wp_create_nonce( self::AJAX ),
			'logos'  => self::LOGO_BASE,
		) );
	}

	/* ------------------------------------------------------------------ the library */

	/** The library index (array), from the 12-hour cache, else HQ, else the last copy that worked; null if none. */
	public static function index( $refresh = false ) {
		$doc = $refresh ? false : get_transient( self::CACHE );
		if ( is_array( $doc ) ) {
			return $doc;
		}
		$res = wp_remote_get( self::INDEX_URL, array( 'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 5 * MB_IN_BYTES ) );
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$doc = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( is_array( $doc ) && isset( $doc['schools'] ) && is_array( $doc['schools'] ) ) {
				$doc = array( 'rev' => (int) ( $doc['rev'] ?? 0 ), 'schools' => $doc['schools'] );
				set_transient( self::CACHE, $doc, 12 * HOUR_IN_SECONDS );
				update_option( self::LAST_GOOD, $doc, false );
				return $doc;
			}
		}
		$last = get_option( self::LAST_GOOD );
		if ( is_array( $last ) ) {
			set_transient( self::CACHE, $last, 30 * MINUTE_IN_SECONDS ); // do not ask a down HQ on every keystroke
			return $last;
		}
		return null;
	}

	/** One school from the index by its library ID, or null. */
	public static function school( $id ) {
		$doc = self::index();
		foreach ( (array) ( $doc['schools'] ?? array() ) as $s ) {
			if ( isset( $s['id'] ) && $s['id'] === $id ) {
				return $s;
			}
		}
		return null;
	}

	public function ajax_index() {
		check_ajax_referer( self::AJAX );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		$doc = self::index();
		if ( ! $doc ) {
			wp_send_json_error( array( 'message' => 'The school list could not be loaded. Type the name; the logo can be added later.' ), 503 );
		}
		wp_send_json_success( $doc );
	}

	/** Same school name, ignoring case, spacing and punctuation ("St. Mary's" = "St Marys"). */
	public static function same_name( $a, $b ) {
		$n = function ( $s ) {
			return preg_replace( '/[^a-z0-9]+/', '', strtolower( remove_accents( wp_specialchars_decode( (string) $s, ENT_QUOTES ) ) ) );
		};
		return '' !== $n( $a ) && $n( $a ) === $n( $b );
	}

	/** True for a library logo file name and md5 we are willing to fetch (nothing else ever reaches download_url()). */
	public static function valid_logo( $school ) {
		return is_array( $school )
			&& isset( $school['f'], $school['m'] )
			&& 1 === preg_match( '/^[a-z0-9-]+\.png$/', (string) $school['f'] )
			&& 1 === preg_match( '/^[a-f0-9]{32}$/', (string) $school['m'] );
	}

	/**
	 * This site's attachment for a library logo: the one already imported (same school, same md5), else downloaded
	 * from HQ, md5-checked, PNG-checked and added to the Media Library.
	 *
	 * @return int|WP_Error
	 */
	private function attachment_for( array $school, $post_id ) {
		$have = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => self::ATT_ID, 'value' => $school['id'] ),
				array( 'key' => self::ATT_MD5, 'value' => $school['m'] ),
			),
		) );
		if ( $have ) {
			return (int) $have[0];
		}
		if ( ! self::valid_logo( $school ) ) {
			return new WP_Error( 'ds_school_emblem_bad', 'the library entry looks wrong' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		// Streamed to a temp file with a hard 2 MB cap and no redirects (download_url() follows redirects and has
		// no size limit: the 2 MB check below only ran after the whole file had been written).
		$tmp = wp_tempnam( $school['f'] );
		$res = $tmp ? wp_safe_remote_get( self::LOGO_BASE . $school['f'], array( 'timeout' => 15, 'redirection' => 0, 'stream' => true, 'filename' => $tmp, 'limit_response_size' => 2 * MB_IN_BYTES ) ) : new WP_Error( 'tmp', '' );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			if ( $tmp ) {
				wp_delete_file( $tmp );
			}
			return new WP_Error( 'ds_school_emblem_download', 'Design Shop HQ could not be reached' );
		}
		// Not a byte checksum: HQ's Cloudflare (Polish) recompresses PNGs losslessly on the way out, so the bytes never
		// equal the library md5 (seen 2026-10-26: 14,731 -> 12,678 bytes, same pixels). A checksum served by the same host
		// would add nothing anyway. What we require: a real PNG that decodes, of library size, under 2 MB.
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$ok   = $info && IMAGETYPE_PNG === $info[2] && $info[0] >= 32 && $info[0] <= 2048 && $info[1] >= 32 && $info[1] <= 2048 && filesize( $tmp ) < 2 * MB_IN_BYTES;
		if ( $ok && function_exists( 'imagecreatefromstring' ) ) {
			$img = @imagecreatefromstring( (string) file_get_contents( $tmp ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			$ok  = false !== $img;
		}
		if ( ! $ok ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'ds_school_emblem_check', 'the downloaded file was not a usable logo' );
		}
		$title = sanitize_text_field( $school['n'] ?? $school['id'] ) . ' logo';
		$att   = media_handle_sideload( array( 'name' => $school['id'] . '.png', 'tmp_name' => $tmp ), $post_id, $title );
		if ( is_wp_error( $att ) ) {
			wp_delete_file( $tmp );
			return $att;
		}
		update_post_meta( $att, '_wp_attachment_image_alt', $title );
		update_post_meta( $att, self::ATT_ID, $school['id'] );
		update_post_meta( $att, self::ATT_MD5, $school['m'] );
		return (int) $att;
	}

	/* ------------------------------------------------------------------ save */

	public function save( $post_id ) {
		// phpcs:disable WordPress.Security.NonceVerification -- verified just below
		if ( empty( $_POST['ds_school_emblem'] ) || ! is_array( $_POST['ds_school_emblem'] ) ) {
			return;
		}
		$nonce = isset( $_POST['ds_school_emblem_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ds_school_emblem_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! is_numeric( $post_id ) ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$picked = wp_unslash( $_POST['ds_school_emblem'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value sanitised below
		// phpcs:enable
		foreach ( $picked as $name_key => $sid ) {
			$name_field = acf_get_field( sanitize_key( $name_key ) );
			if ( ! $name_field || self::NAME_FIELD !== $name_field['name'] ) {
				continue;
			}
			$logo = $this->logo_field( $name_field );
			if ( ! $logo ) {
				continue;
			}
			$sid = sanitize_title( (string) $sid );
			if ( '' === $sid ) {
				delete_post_meta( $post_id, self::ID_META ); // a typed name: School Logo is the partner's to set
				continue;
			}
			$school = self::school( $sid );
			if ( ! $school ) {
				$this->flag( 'that school is not in the School Emblems list any more' );
				continue;
			}
			// The name box was edited after the pick (or before the list loaded): it is a typed name now.
			if ( ! self::same_name( (string) get_post_meta( $post_id, $name_field['name'], true ), (string) ( $school['n'] ?? '' ) ) ) {
				delete_post_meta( $post_id, self::ID_META );
				continue;
			}
			update_post_meta( $post_id, self::ID_META, $sid );
			if ( ! $this->replaceable( $post_id, $logo ) ) {
				continue; // the partner's own logo stays
			}
			if ( empty( $school['f'] ) ) {
				continue; // no logo in the library yet: School Logo stays as it is
			}
			$att = $this->attachment_for( $school, $post_id );
			if ( is_wp_error( $att ) ) {
				$this->flag( $att->get_error_message() );
				continue;
			}
			update_field( $logo['key'], $att, $post_id );
			update_post_meta( $post_id, self::AUTO_META, $att );
		}
	}

	private function flag( $why ) {
		set_transient( self::NOTICE . get_current_user_id(), $why, 5 * MINUTE_IN_SECONDS );
	}

	public function notice() {
		$key = self::NOTICE . get_current_user_id();
		$why = get_transient( $key );
		if ( ! $why ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( 'The school was saved, but its logo could not be added (%s). Click Update again in a few minutes, or upload the logo in School Logo.', $why ) )
		);
	}
}
