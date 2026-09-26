<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Manage entries: edit the posts a Post Loop shows (Staff, Athletes for Commitments and
 * Alumni, Teams, ...) from inside the module's settings panel, without the dashboard.
 *
 * The panel (js/manager.js) keeps every change in the hidden `pl_manage` setting as
 * "dsm1:" + base64(JSON) (Beaver Builder json-decodes JSON-looking settings), so:
 *   - nothing touches a post until the layout is PUBLISHED (fl_builder_after_save_layout
 *     with $publish), then the changes are written and the setting is cleared;
 *   - Cancel / Discard draft throws the changes away, like any other module setting;
 *   - the builder preview shows the pending changes, by overlaying them on the loop's
 *     query and on the title / meta / featured image reads while the module renders.
 *
 * The edit form is built from the post type's ACF field groups, so any post type with
 * fields works. Only a change the editor actually made is written (field by field), so
 * an edit made in the dashboard meanwhile is not overwritten. Removing an entry moves
 * it to the Trash; nothing is ever deleted.
 *
 * Safety (audit 2026-09-27). The base64 envelope passes Beaver Builder's kses check, so the
 * server re-validates it wherever it enters: on save (fl_builder_pre_verify_node_settings:
 * shape, types, the author's user id), in the preview, and on apply. A published change set
 * is applied once: its hash is recorded before anything is written, so a restored revision,
 * a history undo, a duplicated or global module, or a crash half way cannot replay it.
 */
class DS_Loop_Manager {

	const PREFIX   = 'dsm1:';
	const MAX      = 200;          // entries listed / written per module
	const FAKE_ID  = 2000000000;   // preview-only IDs for entries not created yet

	/** ACF field types the panel can edit; anything else links to the dashboard. */
	const FIELD_TYPES = array( 'text', 'email', 'url', 'number', 'textarea', 'wysiwyg', 'image', 'select', 'radio', 'true_false', 'date_picker', 'oembed' );

	/** Tags the simple rich-text box (bio, ACF wysiwyg) produces. The panel only edits text limited to these. */
	public static function allowed_html() {
		return array(
			'p' => array(), 'br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(),
			'ul' => array(), 'ol' => array(), 'li' => array(),
			'a'  => array( 'href' => true, 'target' => true, 'rel' => true ),
		);
	}

	/**
	 * Rich text made safe: script / style blocks dropped with their content, then the tags a
	 * post may hold (wp_kses_post). Not the panel's short list: a Teams schedule or roster is
	 * an HTML table, and the panel never edits such text (it links to the dashboard), but a
	 * value it passes through must not lose its markup.
	 */
	public static function clean_html( $v ) {
		if ( ! is_scalar( $v ) ) { return ''; }
		return wp_kses_post( preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', (string) $v ) );
	}

	/** An entry name: the inline markup the cards render (span, strong, br …), nothing else. */
	public static function clean_title( $v ) {
		if ( ! is_scalar( $v ) ) { return ''; }
		$v = trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $v ) );
		return class_exists( 'DS_Module_UI' ) ? trim( DS_Module_UI::inline( $v ) ) : sanitize_text_field( $v );
	}

	public static function init() {
		add_action( 'wp_ajax_ds_loop_manage_list', array( __CLASS__, 'ajax_list' ) );
		// Priority 5: before Beaver Builder's own save_layout_revision (10), so the revision it
		// takes no longer carries the change set.
		add_action( 'fl_builder_after_save_layout', array( __CLASS__, 'on_publish' ), 5, 4 );
		add_action( 'fl_builder_after_save_layout', array( __CLASS__, 'scrub_revisions' ), 20, 1 );
		add_filter( 'fl_builder_pre_verify_node_settings', array( __CLASS__, 'on_save_settings' ), 10, 2 );
	}

	/* ------------------------------------------------------------ storage */

	public static function decode( $v ) {
		$v = is_string( $v ) ? trim( $v ) : '';
		if ( 0 !== strpos( $v, self::PREFIX ) ) { return array(); }
		$json = base64_decode( substr( $v, strlen( self::PREFIX ) ), true );
		$data = false === $json ? null : json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}

	public static function encode( array $changes ) {
		return self::PREFIX . base64_encode( wp_json_encode( $changes ) );
	}

	/** An entry key: a post ID ("12") or a new entry ("n3"). */
	public static function valid_key( $k ) {
		return is_scalar( $k ) && (bool) preg_match( '/^(?:[1-9]\d{0,9}|n[1-9]\d{0,5})$/', (string) $k );
	}

	/**
	 * A change set reduced to the shape the panel writes, with every value typed: numeric or
	 * "n<k>" keys, integer IDs, known statuses, text through the same cleaners apply() uses.
	 * Image URLs are rebuilt from their attachment IDs, never taken from the payload.
	 */
	public static function sanitize_payload( $c ) {
		if ( ! is_array( $c ) ) { return array(); }
		$ids  = function ( $v ) { return array_values( array_unique( array_filter( array_map( 'absint', array_filter( (array) $v, 'is_scalar' ) ) ) ) ); };
		$img  = function ( $v ) {
			$id = absint( is_array( $v ) ? ( $v['id'] ?? 0 ) : ( is_scalar( $v ) ? $v : 0 ) );
			return array( 'id' => $id, 'url' => $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '' );
		};
		$terms = function ( $v ) use ( $ids ) {
			$out = array();
			foreach ( (array) $v as $tax => $list ) { if ( is_string( $tax ) && taxonomy_exists( $tax ) ) { $out[ $tax ] = $ids( $list ); } }
			return $out;
		};
		$out = array(
			'pt'       => sanitize_key( is_scalar( $c['pt'] ?? null ) ? (string) $c['pt'] : '' ),
			't'        => absint( $c['t'] ?? 0 ),
			'items'    => array(),
			'trash'    => array_slice( $ids( $c['trash'] ?? array() ), 0, self::MAX ),
			'order'    => null,
			'defaults' => $terms( $c['defaults'] ?? array() ),
		);
		if ( ! empty( $c['by'] ) ) { $out['by'] = absint( $c['by'] ); }
		foreach ( array_slice( (array) ( $c['items'] ?? array() ), 0, self::MAX, true ) as $k => $ch ) {
			if ( ! self::valid_key( $k ) || ! is_array( $ch ) ) { continue; }
			$o = array();
			if ( 'n' === ( (string) $k )[0] ) { $o['_new'] = 1; }
			if ( array_key_exists( 'title', $ch ) )   { $o['title'] = self::clean_title( $ch['title'] ); }
			if ( array_key_exists( 'content', $ch ) ) { $o['content'] = self::clean_html( $ch['content'] ); }
			if ( array_key_exists( 'status', $ch ) )  { $o['status'] = 'publish' === $ch['status'] ? 'publish' : 'draft'; }
			if ( array_key_exists( 'thumb', $ch ) )   { $o['thumb'] = $img( $ch['thumb'] ); }
			if ( isset( $ch['fields'] ) && is_array( $ch['fields'] ) ) {
				foreach ( $ch['fields'] as $name => $v ) {
					if ( ! is_string( $name ) || ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $name ) ) { continue; }
					$o['fields'][ $name ] = is_array( $v ) ? $img( $v ) : ( is_scalar( $v ) ? (string) $v : '' );
				}
			}
			if ( isset( $ch['terms'] ) ) { $o['terms'] = $terms( $ch['terms'] ); }
			$out['items'][ (string) $k ] = $o;
		}
		if ( isset( $c['order'] ) && is_array( $c['order'] ) ) {
			$out['order'] = array_values( array_unique( array_map( 'strval', array_filter( array_slice( $c['order'], 0, self::MAX ), array( __CLASS__, 'valid_key' ) ) ) ) );
		}
		return $out;
	}

	/**
	 * A Post Loop's settings being saved in the builder: the change set is re-validated here
	 * (it passes BB's kses check as base64) and stamped with who wrote it, so apply() can hold
	 * the changes to that user's rights as well as the publisher's.
	 */
	public static function on_save_settings( $settings, $node ) {
		if ( ! is_object( $settings ) || 'ds-post-loop' !== ( $node->settings->type ?? '' ) || ! isset( $settings->pl_manage ) ) { return $settings; }
		$c = self::sanitize_payload( self::decode( $settings->pl_manage ) );
		if ( ! $c || '' === $c['pt'] ) { $settings->pl_manage = ''; return $settings; }
		$c['by'] = get_current_user_id();
		$settings->pl_manage = self::encode( $c );
		return $settings;
	}

	/** A post type the manager may edit: a public custom type, not posts, pages or media. */
	public static function supported( $pt ) {
		$o = get_post_type_object( (string) $pt );
		return $o && $o->public && ! in_array( $o->name, array( 'post', 'page', 'attachment' ), true );
	}

	/* ------------------------------------------------------------- schema */

	/** What the edit form shows for a post type: ACF fields, core parts, taxonomies. */
	public static function schema( $pt ) {
		$fields = array();
		if ( function_exists( 'acf_get_field_groups' ) ) {
			foreach ( acf_get_field_groups( array( 'post_type' => $pt ) ) as $g ) {
				foreach ( (array) acf_get_fields( $g['key'] ) as $f ) {
					$type     = (string) $f['type'];
					if ( 'select' === $type && ! empty( $f['multiple'] ) ) { $type = 'select_multiple'; } // the panel edits one value
					$fields[] = array(
						'name'     => (string) $f['name'],
						'key'      => (string) $f['key'],
						'label'    => (string) ( $f['label'] ?: $f['name'] ),
						'type'     => in_array( $type, self::FIELD_TYPES, true ) ? $type : 'unsupported',
						'acf'      => $type,
						'choices'  => in_array( $type, array( 'select', 'radio' ), true ) ? (array) ( $f['choices'] ?? array() ) : new stdClass(),
						'help'     => wp_strip_all_tags( (string) ( $f['instructions'] ?? '' ) ),
					);
				}
			}
		}
		$tax = array();
		foreach ( get_object_taxonomies( $pt, 'objects' ) as $t ) {
			if ( ! $t->public && ! $t->show_ui ) { continue; }
			$terms = get_terms( array( 'taxonomy' => $t->name, 'hide_empty' => false, 'number' => 200 ) );
			$tax[] = array(
				'name'  => $t->name,
				'label' => $t->labels->name,
				'terms' => is_wp_error( $terms ) ? array() : array_map( function ( $x ) { return array( 'id' => (int) $x->term_id, 'name' => $x->name ); }, $terms ),
			);
		}
		$o = get_post_type_object( $pt );
		return array(
			'post_type' => $pt,
			'label'     => $o->labels->name,
			'singular'  => $o->labels->singular_name,
			'editor'    => post_type_supports( $pt, 'editor' ),
			'thumbnail' => post_type_supports( $pt, 'thumbnail' ),
			'fields'    => $fields,
			'tax'       => $tax,
		);
	}

	/* ------------------------------------------------------------ listing */

	/** The loop's taxonomy filter (same rules as DS_Post_Loop_Module::tax_query_args). */
	public static function tax_filter( $s ) {
		$s   = (object) $s;
		$tax = trim( (string) ( $s->filter_tax ?? '' ) );
		if ( '' === $tax || ! taxonomy_exists( $tax ) ) { return array(); }
		$fk  = 'flt_' . str_replace( '-', '_', $tax );
		$raw = $s->$fk ?? '';
		if ( is_array( $raw ) ) { $raw = implode( ',', $raw ); }
		if ( '' === trim( (string) $raw ) && isset( $s->filter_terms ) ) { $raw = (string) $s->filter_terms; }
		$list = array_values( array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) ) );
		if ( ! $list ) { return array(); }
		$numeric = count( $list ) === count( array_filter( $list, 'is_numeric' ) );
		return array( 'taxonomy' => $tax, 'field' => $numeric ? 'term_id' : 'slug', 'terms' => $list );
	}

	/** Does this loop render menu_order top-down (ascending)? */
	public static function ascending( $s ) {
		$s   = (object) $s;
		$opt = get_option( 'ds_toolkit_settings' );
		if ( 'menu_order' === ( $s->order_by ?? 'date' ) && is_array( $opt ) && ! empty( $opt['nested_order_enabled'] ) ) { return true; }
		return 'ASC' === strtoupper( (string) ( $s->order ?? 'DESC' ) );
	}

	/** Every entry the loop can show (shown or hidden), in the loop's order. */
	public static function list_posts( $pt, $s ) {
		$s    = (object) $s;
		$ob   = in_array( ( $s->order_by ?? 'date' ), array( 'date', 'title', 'menu_order', 'modified' ), true ) ? $s->order_by : 'date';
		$args = array(
			'post_type'        => $pt,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page'   => self::MAX,
			'orderby'          => array( $ob => self::ascending( $s ) ? 'ASC' : 'DESC', 'date' => 'DESC' ),
			'suppress_filters' => false,
			'ignore_custom_sort' => 'menu_order' !== $ob,
		);
		$tf = self::tax_filter( $s );
		if ( $tf ) { $args['tax_query'] = array( $tf ); }
		// The loop's hand-picked entries (Include / Exclude), so the list holds what the loop can show.
		list( $inc, $exc ) = self::include_exclude( $pt, $s );
		if ( $inc ) { $args['post__in'] = $inc; }
		if ( $exc ) { $args['post__not_in'] = $exc; }
		$posts = get_posts( $args );
		if ( $posts && function_exists( 'update_post_thumbnail_cache' ) ) {
			$q = new WP_Query(); $q->posts = $posts; update_post_thumbnail_cache( $q );
		}
		return $posts;
	}

	/** The loop's Include / Exclude IDs (inc_<type> / exc_<type>, as DS_Post_Loop_Module reads them). */
	public static function include_exclude( $pt, $s ) {
		$s   = (object) $s;
		$key = str_replace( '-', '_', (string) $pt );
		$get = function ( $f ) use ( $s ) {
			$v = $s->{$f} ?? '';
			return array_values( array_unique( array_filter( array_map( 'absint', is_array( $v ) ? $v : explode( ',', (string) $v ) ) ) ) );
		};
		return array( $get( 'inc_' . $key ), $get( 'exc_' . $key ) );
	}

	/** One entry as the panel needs it. */
	public static function item( WP_Post $p, array $schema ) {
		$fields = array();
		foreach ( $schema['fields'] as $f ) {
			if ( 'unsupported' === $f['type'] ) { continue; }
			$v = get_post_meta( $p->ID, $f['name'], true );
			if ( 'image' === $f['type'] ) {
				$id = is_array( $v ) ? (int) ( $v['ID'] ?? 0 ) : (int) $v;
				$fields[ $f['name'] ] = array( 'id' => $id, 'url' => $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '' );
			} else {
				$fields[ $f['name'] ] = is_scalar( $v ) ? (string) $v : '';
			}
		}
		$terms = array();
		foreach ( $schema['tax'] as $t ) {
			$got = get_the_terms( $p, $t['name'] ); // primed by the list query's term cache
			$terms[ $t['name'] ] = ( ! $got || is_wp_error( $got ) ) ? array() : array_map( 'intval', wp_list_pluck( $got, 'term_id' ) );
		}
		$thumb = (int) get_post_thumbnail_id( $p->ID );
		return array(
			'id'      => $p->ID,
			'title'   => $p->post_title,
			'status'  => 'publish' === $p->post_status ? 'publish' : 'draft',
			'thumb'   => array( 'id' => $thumb, 'url' => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'thumbnail' ) : '' ),
			'content' => $schema['editor'] ? $p->post_content : '',
			'fields'  => $fields,
			'terms'   => $terms,
			'edit'    => get_edit_post_link( $p->ID, 'raw' ),
			'view'    => 'publish' === $p->post_status ? get_permalink( $p ) : '',
		);
	}

	public static function ajax_list() {
		check_ajax_referer( 'ds_loop_manager', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => __( 'You are not allowed to edit these entries.', 'ds-toolkit' ) ), 403 ); }
		$pt = sanitize_key( wp_unslash( $_POST['post_type'] ?? '' ) );
		if ( ! self::supported( $pt ) ) { wp_send_json_error( array( 'message' => __( 'This post type cannot be managed here.', 'ds-toolkit' ) ) ); }
		$s = array();
		foreach ( array( 'filter_tax', 'filter_terms', 'order_by', 'order' ) as $k ) { $s[ $k ] = sanitize_text_field( wp_unslash( $_POST['q'][ $k ] ?? '' ) ); }
		foreach ( (array) ( $_POST['q'] ?? array() ) as $k => $v ) {
			if ( 0 === strpos( (string) $k, 'flt_' ) || 0 === strpos( (string) $k, 'inc_' ) || 0 === strpos( (string) $k, 'exc_' ) ) { $s[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( is_array( $v ) ? implode( ',', $v ) : $v ) ); }
		}
		$schema = self::schema( $pt );
		$items  = array();
		foreach ( self::list_posts( $pt, $s ) as $p ) {
			if ( current_user_can( 'edit_post', $p->ID ) ) { $items[] = self::item( $p, $schema ); }
		}
		$tf = self::tax_filter( $s );
		wp_send_json_success( array(
			'schema'    => $schema,
			'items'     => $items,
			'ascending' => self::ascending( $s ),
			'manual'    => 'menu_order' === ( $s['order_by'] ?? '' ),
			'defaults'  => $tf ? array( $tf['taxonomy'] => self::term_ids( $tf ) ) : new stdClass(),
			'canCreate' => current_user_can( get_post_type_object( $pt )->cap->create_posts ),
		) );
	}

	/** The term IDs a filter names (it may use slugs). */
	private static function term_ids( array $tf ) {
		$ids = array();
		foreach ( $tf['terms'] as $t ) {
			$term = get_term_by( 'term_id' === $tf['field'] ? 'id' : 'slug', $t, $tf['taxonomy'] );
			if ( $term ) { $ids[] = (int) $term->term_id; }
		}
		return $ids;
	}

	/* ---------------------------------------------------------- sanitizing */

	/** One field value made safe for its ACF type; null = drop it. */
	public static function clean_field( $v, array $f ) {
		if ( 'image' !== $f['type'] && ! is_scalar( $v ) ) { return null; }
		switch ( $f['type'] ) {
			case 'email':      return sanitize_email( (string) $v );
			case 'url':
			case 'oembed':     return esc_url_raw( (string) $v, array( 'http', 'https' ) );
			case 'number':
				if ( '' === trim( (string) $v ) ) { return ''; }
				return ( is_numeric( $v ) && is_finite( 0 + $v ) ) ? (string) ( 0 + $v ) : null;
			case 'textarea':   return self::clean_html( $v ); // may hold markup the site prints; scripts never
			case 'wysiwyg':    return self::clean_html( $v );
			case 'image':      $id = absint( is_array( $v ) ? ( $v['id'] ?? 0 ) : ( is_scalar( $v ) ? $v : 0 ) ); return ( 0 === $id || wp_attachment_is_image( $id ) ) ? $id : null;
			case 'select':
			case 'radio':      return ( '' === (string) $v || array_key_exists( (string) $v, (array) $f['choices'] ) ) ? (string) $v : null;
			case 'true_false': return empty( $v ) ? 0 : 1;
			case 'date_picker': return ( '' === (string) $v || preg_match( '/^\d{8}$/', (string) $v ) ) ? (string) $v : null; // ACF stores Ymd
			case 'text':       return sanitize_text_field( (string) $v );
		}
		return null;
	}

	/** The publisher may do it, and so may the user who made the change (when that is someone else). */
	private static function can( $by, $cap, ...$args ) {
		if ( ! current_user_can( $cap, ...$args ) ) { return false; }
		return ! $by || get_current_user_id() === $by || user_can( $by, $cap, ...$args );
	}

	/* ---------------------------------------------------------- applying */

	/**
	 * Write a module's pending changes. Returns a short report; every write is
	 * checked against the user's capabilities for that post.
	 */
	public static function apply( array $c, $s ) {
		$report = array( 'updated' => 0, 'created' => 0, 'trashed' => 0, 'reordered' => 0, 'skipped' => 0 );
		$c      = self::sanitize_payload( $c );
		$s      = (object) $s;
		$pt     = $c['pt'] ?? '';
		// The changes belong to the post type this loop shows (a leftover set for another type is ignored).
		if ( ! self::supported( $pt ) || ( isset( $s->post_type ) && (string) $s->post_type !== $pt ) ) { return $report; }
		$by     = (int) ( $c['by'] ?? 0 );
		if ( $by && ! get_userdata( $by ) ) { return $report; }
		$schema = self::schema( $pt );
		$fmap   = array();
		foreach ( $schema['fields'] as $f ) { if ( 'unsupported' !== $f['type'] ) { $fmap[ $f['name'] ] = $f; } }
		$taxes  = wp_list_pluck( $schema['tax'], 'name' );
		$ptobj  = get_post_type_object( $pt );
		$made   = array(); // "n1" => new post ID

		foreach ( array_slice( (array) ( $c['items'] ?? array() ), 0, self::MAX, true ) as $key => $ch ) {
			$ch    = (array) $ch;
			$isnew = ! is_numeric( $key );
			if ( $isnew ) {
				if ( ! self::can( $by, $ptobj->cap->create_posts ) ) { $report['skipped']++; continue; }
				$status = ( 'publish' === ( $ch['status'] ?? 'publish' ) && self::can( $by, $ptobj->cap->publish_posts ) ) ? 'publish' : 'draft';
				$id     = wp_insert_post( wp_slash( array(
					'post_type'    => $pt,
					'post_status'  => $status,
					'post_title'   => ( $ch['title'] ?? '' ) ?: __( 'New entry', 'ds-toolkit' ),
					'post_content' => $schema['editor'] ? ( $ch['content'] ?? '' ) : '',
				) ), true );
				if ( is_wp_error( $id ) || ! $id ) { $report['skipped']++; continue; }
				$made[ $key ] = (int) $id;
				$report['created']++;
			} else {
				$id = (int) $key;
				$p  = get_post( $id );
				if ( ! $p || $p->post_type !== $pt || 'trash' === $p->post_status || ! self::can( $by, 'edit_post', $id ) ) { $report['skipped']++; continue; }
				$up = array();
				if ( array_key_exists( 'title', $ch ) ) { $up['post_title'] = $ch['title']; }
				if ( array_key_exists( 'content', $ch ) && $schema['editor'] ) { $up['post_content'] = $ch['content']; }
				if ( array_key_exists( 'status', $ch ) ) {
					$want = $ch['status'];
					if ( 'publish' !== $want || self::can( $by, 'publish_post', $id ) || self::can( $by, $ptobj->cap->publish_posts ) ) { $up['post_status'] = $want; }
				}
				if ( $up ) { $up['ID'] = $id; wp_update_post( wp_slash( $up ) ); }
				$report['updated']++;
			}
			if ( array_key_exists( 'thumb', $ch ) && $schema['thumbnail'] ) {
				$tid = absint( is_array( $ch['thumb'] ) ? ( $ch['thumb']['id'] ?? 0 ) : $ch['thumb'] );
				if ( $tid && wp_attachment_is_image( $tid ) ) { set_post_thumbnail( $id, $tid ); } elseif ( ! $tid ) { delete_post_thumbnail( $id ); }
			}
			foreach ( (array) ( $ch['fields'] ?? array() ) as $name => $v ) {
				if ( ! isset( $fmap[ $name ] ) ) { continue; }
				$v = self::clean_field( $v, $fmap[ $name ] );
				if ( null === $v ) { continue; }
				if ( function_exists( 'update_field' ) ) { update_field( $fmap[ $name ]['key'], $v, $id ); } else { update_post_meta( $id, $name, $v ); }
			}
			$terms = (array) ( $ch['terms'] ?? array() );
			if ( $isnew && ! $terms && ! empty( $c['defaults'] ) ) { $terms = (array) $c['defaults']; }
			foreach ( $terms as $tax => $ids ) {
				if ( ! in_array( $tax, $taxes, true ) ) { continue; }
				// The panel lists at most 200 terms: keep any term the post has that it did not show.
				$listed = array();
				foreach ( $schema['tax'] as $t ) { if ( $t['name'] === $tax ) { $listed = wp_list_pluck( $t['terms'], 'id' ); } }
				$had    = $isnew ? array() : wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
				$keep   = is_wp_error( $had ) ? array() : array_diff( array_map( 'intval', $had ), $listed );
				wp_set_object_terms( $id, array_values( array_unique( array_merge( $keep, array_map( 'absint', (array) $ids ) ) ) ), $tax );
			}
		}

		foreach ( array_slice( array_map( 'absint', (array) ( $c['trash'] ?? array() ) ), 0, self::MAX ) as $id ) {
			$p = get_post( $id );
			if ( $p && $p->post_type === $pt && 'trash' !== $p->post_status && self::can( $by, 'delete_post', $id ) && wp_trash_post( $id ) ) { $report['trashed']++; }
		}

		// The list order becomes menu_order only for a loop that sorts by it. Other loops and
		// Nested Pages listings of the same type read menu_order too, so a drag in a date-sorted
		// loop must not renumber them ("Sort the loop by this list" switches the loop first).
		if ( ! empty( $c['order'] ) && is_array( $c['order'] ) && 'menu_order' === ( $s->order_by ?? '' ) ) {
			$ids = array();
			foreach ( array_slice( $c['order'], 0, self::MAX ) as $k ) {
				$id = is_numeric( $k ) ? (int) $k : (int) ( $made[ $k ] ?? 0 );
				$p  = $id ? get_post( $id ) : null;
				if ( $p && $p->post_type === $pt && 'trash' !== $p->post_status && self::can( $by, 'edit_post', $id ) ) { $ids[] = $id; }
			}
			// Numbered so this loop shows the list top-down, whichever direction it sorts.
			$asc = self::ascending( $s );
			$n   = count( $ids );
			foreach ( $ids as $i => $id ) {
				$mo = $asc ? $i + 1 : $n - $i;
				if ( (int) get_post_field( 'menu_order', $id ) !== $mo ) { wp_update_post( array( 'ID' => $id, 'menu_order' => $mo ) ); $report['reordered']++; }
			}
		}
		return $report;
	}

	/** Change sets already applied (hash => time), newest last. Kept to the last 500. */
	const APPLIED = 'ds_loop_manager_applied';

	private static $cleared = array(); // node IDs cleared in this request, for scrub_revisions()

	/**
	 * On Publish: apply every Post Loop's pending changes, then clear them from the layout.
	 *
	 * Each change set is applied once. Its hash is recorded BEFORE anything is written, so a
	 * copy that comes back later (a restored revision, a history undo, a duplicated module, a
	 * global module's template, a retry after a timeout half way) is cleared, not replayed.
	 */
	public static function on_publish( $post_id, $publish, $data, $settings ) {
		if ( ! $publish || ! is_array( $data ) ) { return; }
		$done    = array();
		$applied = get_option( self::APPLIED, array() );
		$applied = is_array( $applied ) ? $applied : array();
		$globals = array(); // template post ID => [ template node IDs ]
		foreach ( $data as $node_id => $node ) {
			if ( ! is_object( $node ) || 'module' !== ( $node->type ?? '' ) || 'ds-post-loop' !== ( $node->settings->type ?? '' ) ) { continue; }
			$raw = (string) ( $node->settings->pl_manage ?? '' );
			$c   = self::decode( $raw );
			if ( ! $c ) { continue; }
			if ( class_exists( 'FLBuilderModel' ) && ( $tpl = FLBuilderModel::is_node_global( $node ) ) && ! empty( $node->template_node_id ) ) {
				$globals[ (int) $tpl ][] = (string) $node->template_node_id;
			}
			$hash = md5( $raw );
			if ( isset( $applied[ $hash ] ) ) { $done[ $node_id ] = array( 'replay' => true ); continue; }
			$applied[ $hash ] = time();
			if ( count( $applied ) > 500 ) { $applied = array_slice( $applied, -500, null, true ); }
			update_option( self::APPLIED, $applied, false );
			$done[ $node_id ] = self::apply( $c, $node->settings );
		}
		if ( ! $done ) { return; }
		self::$cleared = array_merge( self::$cleared, array_map( 'strval', array_keys( $done ) ) );
		self::clear_nodes( $post_id, array_keys( $done ) );
		foreach ( $globals as $tpl => $nodes ) {
			if ( (int) $tpl !== (int) $post_id ) { self::clear_nodes( (int) $tpl, $nodes ); }
		}
		update_post_meta( $post_id, '_ds_loop_manager_last', array( 'time' => time(), 'user' => get_current_user_id(), 'report' => $done ) );
		// The page was purged by the save before the entries changed; a visitor in between may
		// have cached the old list.
		clean_post_cache( $post_id );
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) { WpeCommon::purge_varnish_cache( $post_id ); }
	}

	/**
	 * Empty pl_manage on these nodes in a post's published and draft layout. Written with
	 * Beaver Builder's own writer, which slashes the data: update_post_meta() would strip one
	 * level of backslashes from every module on the page (CSS "\201C", a regex in an HTML module).
	 */
	private static function clear_nodes( $post_id, array $node_ids ) {
		foreach ( array( 'published', 'draft' ) as $status ) {
			$layout = FLBuilderModel::get_layout_data( $status, $post_id );
			if ( ! is_array( $layout ) || ! $layout ) { continue; }
			$changed = false;
			foreach ( $node_ids as $nid ) {
				if ( isset( $layout[ $nid ]->settings ) && '' !== (string) ( $layout[ $nid ]->settings->pl_manage ?? '' ) ) {
					$layout[ $nid ] = clone $layout[ $nid ];
					$layout[ $nid ]->settings = clone $layout[ $nid ]->settings;
					$layout[ $nid ]->settings->pl_manage = '';
					$changed = true;
				}
			}
			if ( $changed ) { FLBuilderModel::update_layout_data( $layout, $status, $post_id ); }
		}
	}

	/** After the publish's revisions exist: take the applied change sets out of them too. */
	public static function scrub_revisions( $post_id ) {
		if ( ! self::$cleared || ! function_exists( 'wp_get_post_revisions' ) ) { return; }
		foreach ( wp_get_post_revisions( $post_id, array( 'posts_per_page' => 3 ) ) as $rev ) {
			$layout = get_post_meta( $rev->ID, '_fl_builder_data', true );
			if ( ! is_array( $layout ) ) { continue; }
			$changed = false;
			foreach ( self::$cleared as $nid ) {
				if ( isset( $layout[ $nid ]->settings ) && '' !== (string) ( $layout[ $nid ]->settings->pl_manage ?? '' ) ) {
					$layout[ $nid ]->settings->pl_manage = '';
					$changed = true;
				}
			}
			// Revisions are written straight to their meta, so slash like BB does.
			if ( $changed ) { update_metadata( 'post', $rev->ID, '_fl_builder_data', FLBuilderModel::slash_settings( $layout ) ); }
		}
		self::$cleared = array();
	}

	/* ----------------------------------------------------------- preview */

	private static $pv    = null;    // active preview: changes + lookups (top of $stack)
	private static $stack = array(); // a loop rendered inside another loop's template previews too

	public static function previewing() { return null !== self::$pv; }

	/** Overlay a module's pending changes on everything the loop reads, until end_preview(). */
	public static function begin_preview( $settings ) {
		if ( ! class_exists( 'FLBuilderModel' ) || ! FLBuilderModel::is_builder_active() ) { return false; }
		// The panel's unsaved settings reach the preview straight from the browser: the same
		// cleaning as a save, so the overlay never feeds raw markup to a title or field read.
		$c = self::sanitize_payload( self::decode( $settings->pl_manage ?? '' ) );
		if ( ! $c || '' === $c['pt'] || $c['pt'] !== ( $settings->post_type ?? '' ) ) { return false; }
		$items = $c['items'];
		$ids   = array();
		// Preview-only IDs for entries not created yet: a random block per request (two editors
		// previewing at once never share one), cached for a minute at most in case the request dies.
		$base  = self::FAKE_ID + wp_rand( 0, 99999 ) * 1000;
		$n     = 0;
		foreach ( $items as $key => $ch ) {
			if ( empty( $ch['_new'] ) ) { $ids[ (string) $key ] = (int) $key; continue; }
			$fid = $base + ( ++$n );
			$ids[ (string) $key ] = $fid;
			$post = new WP_Post( (object) array( 'ID' => $fid, 'post_type' => $c['pt'], 'post_status' => 'publish', 'post_title' => (string) ( $ch['title'] ?? '' ), 'post_name' => 'new-entry-' . $n, 'post_content' => (string) ( $ch['content'] ?? '' ), 'post_date' => current_time( 'mysql' ), 'post_date_gmt' => current_time( 'mysql', 1 ), 'filter' => 'raw', 'comment_status' => 'closed', 'ping_status' => 'closed' ) );
			wp_cache_set( $fid, $post, 'posts', MINUTE_IN_SECONDS );
		}
		$pv = array( 'c' => $c, 'ids' => $ids, 'byid' => array(), 'fake' => array(), 'fmap' => array() );
		foreach ( $items as $key => $ch ) { $pv['byid'][ $ids[ (string) $key ] ] = $ch; if ( ! empty( $ch['_new'] ) ) { $pv['fake'][ $ids[ (string) $key ] ] = true; } }
		foreach ( self::schema( $c['pt'] )['fields'] as $f ) { if ( 'unsupported' !== $f['type'] ) { $pv['fmap'][ $f['name'] ] = $f; } }
		if ( ! self::$stack ) {
			add_filter( 'the_posts', array( __CLASS__, 'pv_posts' ), 20, 2 );
			add_filter( 'the_title', array( __CLASS__, 'pv_title' ), 20, 2 );
			add_filter( 'get_post_metadata', array( __CLASS__, 'pv_meta' ), 20, 4 );
		}
		self::$stack[] = $pv;
		self::$pv      = $pv;
		return true;
	}

	public static function end_preview() {
		if ( ! self::$stack ) { return; }
		$pv = array_pop( self::$stack );
		foreach ( array_keys( $pv['fake'] ) as $fid ) { wp_cache_delete( $fid, 'posts' ); }
		self::$pv = self::$stack ? end( self::$stack ) : null;
		if ( ! self::$stack ) {
			remove_filter( 'the_posts', array( __CLASS__, 'pv_posts' ), 20 );
			remove_filter( 'the_title', array( __CLASS__, 'pv_title' ), 20 );
			remove_filter( 'get_post_metadata', array( __CLASS__, 'pv_meta' ), 20 );
		}
	}

	/** The loop's own query: drop trashed / hidden, add shown and new entries, apply the list order. */
	public static function pv_posts( $posts, $q ) {
		if ( ! self::$pv || ! $q->get( 'ds_loop_preview' ) ) { return $posts; }
		$c     = self::$pv['c'];
		$trash = $c['trash'];
		$picked = array_values( array_filter( array_map( 'intval', (array) ( $q->get( 'post__in' ) ?: array() ) ) ) ); // the loop's Include list
		$out   = array();
		foreach ( $posts as $p ) {
			$ch = self::$pv['byid'][ $p->ID ] ?? array();
			if ( in_array( (int) $p->ID, $trash, true ) || ( isset( $ch['status'] ) && 'publish' !== $ch['status'] ) ) { continue; }
			$out[ $p->ID ] = $p;
		}
		foreach ( self::$pv['byid'] as $id => $ch ) {
			if ( isset( $out[ $id ] ) || in_array( (int) $id, $trash, true ) ) { continue; }
			if ( ! empty( self::$pv['fake'][ $id ] ) ) {
				// A new entry is not in a hand-picked Include list, so the live loop will not show it either.
				if ( ! $picked && 'publish' === ( $ch['status'] ?? 'publish' ) ) { $p = get_post( $id ); if ( $p ) { $out[ $id ] = $p; } }
				continue;
			}
			// An existing entry being shown again: only an entry of this loop's type that the editor may edit.
			if ( 'publish' !== ( $ch['status'] ?? '' ) || ( $picked && ! in_array( (int) $id, $picked, true ) ) ) { continue; }
			$p = get_post( $id );
			if ( $p && $p->post_type === $c['pt'] && 'trash' !== $p->post_status && current_user_can( 'edit_post', $p->ID ) ) { $out[ $id ] = $p; }
		}
		if ( ! empty( $c['order'] ) ) {
			$rank = array();
			foreach ( array_values( (array) $c['order'] ) as $i => $k ) { $rank[ self::$pv['ids'][ (string) $k ] ?? (int) $k ] = $i; }
			uasort( $out, function ( $a, $b ) use ( $rank ) { return ( $rank[ $a->ID ] ?? PHP_INT_MAX ) <=> ( $rank[ $b->ID ] ?? PHP_INT_MAX ); } );
		}
		$out = array_values( $out );
		$lim = (int) $q->get( 'posts_per_page' );
		if ( $lim > 0 ) { $out = array_slice( $out, 0, $lim ); }
		$q->post_count = count( $out );
		return $out;
	}

	public static function pv_title( $title, $id = 0 ) {
		$ch = self::$pv['byid'][ (int) $id ] ?? null;
		return ( $ch && array_key_exists( 'title', $ch ) ) ? (string) $ch['title'] : $title; // cleaned like a saved title
	}

	public static function pv_meta( $value, $id, $key, $single ) {
		$ch = self::$pv['byid'][ (int) $id ] ?? null;
		if ( ! $ch ) { return $value; }
		if ( '_thumbnail_id' === $key && array_key_exists( 'thumb', $ch ) ) {
			$t = (int) ( $ch['thumb']['id'] ?? 0 );
			return $single ? array( $t ? $t : '' ) : array( $t );
		}
		$f = (array) ( $ch['fields'] ?? array() );
		if ( array_key_exists( $key, $f ) && isset( self::$pv['fmap'][ $key ] ) ) {
			// The value apply() would store, so the preview never shows what publishing would refuse.
			$v = self::clean_field( $f[ $key ], self::$pv['fmap'][ $key ] );
			if ( null !== $v ) { return array( $v ); }
		}
		if ( ! empty( self::$pv['fake'][ (int) $id ] ) ) { return $single ? array( '' ) : array(); } // new entry: nothing stored yet
		return $value;
	}
}
DS_Loop_Manager::init();
