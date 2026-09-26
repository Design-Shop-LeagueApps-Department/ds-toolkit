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
 */
class DS_Loop_Manager {

	const PREFIX   = 'dsm1:';
	const MAX      = 200;          // entries listed / written per module
	const FAKE_ID  = 2000000000;   // preview-only IDs for entries not created yet

	/** ACF field types the panel can edit; anything else links to the dashboard. */
	const FIELD_TYPES = array( 'text', 'email', 'url', 'number', 'textarea', 'wysiwyg', 'image', 'select', 'radio', 'true_false', 'date_picker', 'oembed' );

	/** Tags the simple rich-text box (bio, ACF wysiwyg) may save. */
	public static function allowed_html() {
		return array(
			'p' => array(), 'br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(),
			'ul' => array(), 'ol' => array(), 'li' => array(),
			'a'  => array( 'href' => true, 'target' => true, 'rel' => true ),
		);
	}

	/** Rich text made safe: script / style blocks dropped with their content, then only allowed_html() tags. */
	public static function clean_html( $v ) {
		return wp_kses( preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', (string) $v ), self::allowed_html() );
	}

	public static function init() {
		add_action( 'wp_ajax_ds_loop_manage_list', array( __CLASS__, 'ajax_list' ) );
		add_action( 'fl_builder_after_save_layout', array( __CLASS__, 'on_publish' ), 10, 4 );
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
		return get_posts( $args );
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
			$ids = wp_get_object_terms( $p->ID, $t['name'], array( 'fields' => 'ids' ) );
			$terms[ $t['name'] ] = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
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
			if ( 0 === strpos( (string) $k, 'flt_' ) ) { $s[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( is_array( $v ) ? implode( ',', $v ) : $v ) ); }
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
		switch ( $f['type'] ) {
			case 'email':      return sanitize_email( (string) $v );
			case 'url':
			case 'oembed':     return esc_url_raw( (string) $v, array( 'http', 'https' ) );
			case 'number':     return '' === trim( (string) $v ) ? '' : ( is_numeric( $v ) ? (string) ( 0 + $v ) : null );
			case 'textarea':   return sanitize_textarea_field( (string) $v );
			case 'wysiwyg':    return self::clean_html( $v );
			case 'image':      $id = absint( is_array( $v ) ? ( $v['id'] ?? 0 ) : $v ); return ( 0 === $id || wp_attachment_is_image( $id ) ) ? $id : null;
			case 'select':
			case 'radio':      return ( '' === (string) $v || array_key_exists( (string) $v, (array) $f['choices'] ) ) ? (string) $v : null;
			case 'true_false': return empty( $v ) ? 0 : 1;
			case 'text':
			case 'date_picker': return sanitize_text_field( (string) $v );
		}
		return null;
	}

	/* ---------------------------------------------------------- applying */

	/**
	 * Write a module's pending changes. Returns a short report; every write is
	 * checked against the user's capabilities for that post.
	 */
	public static function apply( array $c, $s ) {
		$report = array( 'updated' => 0, 'created' => 0, 'trashed' => 0, 'reordered' => 0, 'skipped' => 0 );
		$pt     = sanitize_key( $c['pt'] ?? '' );
		if ( ! self::supported( $pt ) ) { return $report; }
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
				if ( ! current_user_can( $ptobj->cap->create_posts ) ) { $report['skipped']++; continue; }
				$status = ( 'publish' === ( $ch['status'] ?? 'publish' ) && current_user_can( $ptobj->cap->publish_posts ) ) ? 'publish' : 'draft';
				$id     = wp_insert_post( array(
					'post_type'    => $pt,
					'post_status'  => $status,
					'post_title'   => sanitize_text_field( (string) ( $ch['title'] ?? '' ) ) ?: __( 'New entry', 'ds-toolkit' ),
					'post_content' => $schema['editor'] ? self::clean_html( $ch['content'] ?? '' ) : '',
				), true );
				if ( is_wp_error( $id ) || ! $id ) { $report['skipped']++; continue; }
				$made[ $key ] = (int) $id;
				$report['created']++;
			} else {
				$id = (int) $key;
				$p  = get_post( $id );
				if ( ! $p || $p->post_type !== $pt || ! current_user_can( 'edit_post', $id ) ) { $report['skipped']++; continue; }
				$up = array();
				if ( array_key_exists( 'title', $ch ) ) { $up['post_title'] = sanitize_text_field( (string) $ch['title'] ); }
				if ( array_key_exists( 'content', $ch ) && $schema['editor'] ) { $up['post_content'] = self::clean_html( $ch['content'] ); }
				if ( array_key_exists( 'status', $ch ) ) {
					$want = 'publish' === $ch['status'] ? 'publish' : 'draft';
					if ( 'publish' !== $want || current_user_can( 'publish_post', $id ) || current_user_can( $ptobj->cap->publish_posts ) ) { $up['post_status'] = $want; }
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
				if ( in_array( $tax, $taxes, true ) ) { wp_set_object_terms( $id, array_map( 'absint', (array) $ids ), $tax ); }
			}
		}

		foreach ( array_slice( array_map( 'absint', (array) ( $c['trash'] ?? array() ) ), 0, self::MAX ) as $id ) {
			$p = get_post( $id );
			if ( $p && $p->post_type === $pt && current_user_can( 'delete_post', $id ) && wp_trash_post( $id ) ) { $report['trashed']++; }
		}

		if ( ! empty( $c['order'] ) && is_array( $c['order'] ) ) {
			$ids = array();
			foreach ( array_slice( $c['order'], 0, self::MAX ) as $k ) {
				$id = is_numeric( $k ) ? (int) $k : (int) ( $made[ $k ] ?? 0 );
				$p  = $id ? get_post( $id ) : null;
				if ( $p && $p->post_type === $pt && 'trash' !== $p->post_status && current_user_can( 'edit_post', $id ) ) { $ids[] = $id; }
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

	/** On Publish: apply every Post Loop's pending changes, then clear them from the layout. */
	public static function on_publish( $post_id, $publish, $data, $settings ) {
		if ( ! $publish || ! is_array( $data ) ) { return; }
		$done = array();
		foreach ( $data as $node_id => $node ) {
			if ( ! is_object( $node ) || 'module' !== ( $node->type ?? '' ) || 'ds-post-loop' !== ( $node->settings->type ?? '' ) ) { continue; }
			$c = self::decode( $node->settings->pl_manage ?? '' );
			if ( ! $c ) { continue; }
			$done[ $node_id ] = self::apply( $c, $node->settings );
		}
		if ( ! $done ) { return; }
		foreach ( array( '_fl_builder_data', '_fl_builder_draft' ) as $mk ) {
			$layout = get_post_meta( $post_id, $mk, true );
			if ( ! is_array( $layout ) ) { continue; }
			foreach ( array_keys( $done ) as $node_id ) {
				if ( isset( $layout[ $node_id ]->settings ) ) { $layout[ $node_id ]->settings->pl_manage = ''; }
			}
			update_post_meta( $post_id, $mk, $layout );
		}
		update_post_meta( $post_id, '_ds_loop_manager_last', array( 'time' => time(), 'user' => get_current_user_id(), 'report' => $done ) );
	}

	/* ----------------------------------------------------------- preview */

	private static $pv = null; // active preview: changes + lookups

	public static function previewing() { return null !== self::$pv; }

	/** Overlay a module's pending changes on everything the loop reads, until end_preview(). */
	public static function begin_preview( $settings ) {
		if ( ! class_exists( 'FLBuilderModel' ) || ! FLBuilderModel::is_builder_active() ) { return false; }
		$c = self::decode( $settings->pl_manage ?? '' );
		if ( ! $c || ( $c['pt'] ?? '' ) !== ( $settings->post_type ?? '' ) ) { return false; }
		$items = (array) ( $c['items'] ?? array() );
		$ids   = array();
		$n     = 0;
		foreach ( $items as $key => $ch ) {
			if ( is_numeric( $key ) ) { $ids[ (string) $key ] = (int) $key; continue; }
			$fid = self::FAKE_ID + ( ++$n );
			$ids[ (string) $key ] = $fid;
			$post = new WP_Post( (object) array( 'ID' => $fid, 'post_type' => $c['pt'], 'post_status' => 'publish', 'post_title' => (string) ( $ch['title'] ?? '' ), 'post_name' => 'new-entry-' . $n, 'post_content' => (string) ( $ch['content'] ?? '' ), 'post_date' => current_time( 'mysql' ), 'post_date_gmt' => current_time( 'mysql', 1 ), 'filter' => 'raw', 'comment_status' => 'closed', 'ping_status' => 'closed' ) );
			wp_cache_set( $fid, $post, 'posts' );
		}
		self::$pv = array( 'c' => $c, 'ids' => $ids, 'byid' => array() );
		foreach ( $items as $key => $ch ) { self::$pv['byid'][ $ids[ (string) $key ] ] = (array) $ch; }
		add_filter( 'the_posts', array( __CLASS__, 'pv_posts' ), 20, 2 );
		add_filter( 'the_title', array( __CLASS__, 'pv_title' ), 20, 2 );
		add_filter( 'get_post_metadata', array( __CLASS__, 'pv_meta' ), 20, 4 );
		return true;
	}

	public static function end_preview() {
		if ( ! self::$pv ) { return; }
		remove_filter( 'the_posts', array( __CLASS__, 'pv_posts' ), 20 );
		remove_filter( 'the_title', array( __CLASS__, 'pv_title' ), 20 );
		remove_filter( 'get_post_metadata', array( __CLASS__, 'pv_meta' ), 20 );
		foreach ( self::$pv['ids'] as $fid ) { if ( $fid >= self::FAKE_ID ) { wp_cache_delete( $fid, 'posts' ); } }
		self::$pv = null;
	}

	/** The loop's own query: drop trashed / hidden, add shown and new entries, apply the list order. */
	public static function pv_posts( $posts, $q ) {
		if ( ! self::$pv || ! $q->get( 'ds_loop_preview' ) ) { return $posts; }
		$c     = self::$pv['c'];
		$trash = array_map( 'intval', (array) ( $c['trash'] ?? array() ) );
		$out   = array();
		foreach ( $posts as $p ) {
			$ch = self::$pv['byid'][ $p->ID ] ?? array();
			if ( in_array( (int) $p->ID, $trash, true ) || ( isset( $ch['status'] ) && 'publish' !== $ch['status'] ) ) { continue; }
			$out[ $p->ID ] = $p;
		}
		foreach ( self::$pv['byid'] as $id => $ch ) {
			if ( isset( $out[ $id ] ) || in_array( (int) $id, $trash, true ) ) { continue; }
			$new = $id >= self::FAKE_ID;
			if ( ( $new && 'publish' === ( $ch['status'] ?? 'publish' ) ) || ( ! $new && 'publish' === ( $ch['status'] ?? '' ) ) ) {
				$p = get_post( $id );
				if ( $p ) { $out[ $id ] = $p; }
			}
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
		return ( $ch && array_key_exists( 'title', $ch ) ) ? (string) $ch['title'] : $title; // raw, like get_the_title(); cards escape it
	}

	public static function pv_meta( $value, $id, $key, $single ) {
		$ch = self::$pv['byid'][ (int) $id ] ?? null;
		if ( ! $ch ) { return $value; }
		if ( '_thumbnail_id' === $key && array_key_exists( 'thumb', $ch ) ) {
			$t = (int) ( is_array( $ch['thumb'] ) ? ( $ch['thumb']['id'] ?? 0 ) : $ch['thumb'] );
			return $single ? array( $t ? $t : '' ) : array( $t );
		}
		$f = (array) ( $ch['fields'] ?? array() );
		if ( array_key_exists( $key, $f ) ) {
			$v = is_array( $f[ $key ] ) ? (int) ( $f[ $key ]['id'] ?? 0 ) : $f[ $key ];
			return array( $v );
		}
		if ( (int) $id >= self::FAKE_ID ) { return $single ? array( '' ) : array(); } // new entry: nothing stored yet
		return $value;
	}
}
DS_Loop_Manager::init();
