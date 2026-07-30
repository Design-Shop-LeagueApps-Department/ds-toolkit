<?php
/**
 * Migrate manual-list card nodes out of ds-post-loop and into ds-cta.
 *
 *   ds-post-loop card_layout=program  ->  ds-cta cta_style=style6
 *
 * WHY IT IS A VERBATIM CARRY
 * --------------------------
 * Beaver Builder merges a node's STORED settings over the module defaults and
 * keeps keys it does not recognise (FLBuilderModel::get_node_settings_with_defaults_merged).
 * So changing `type` and adding `cta_style` preserves every value the node
 * already holds; keys that only mean something to Post Loop become inert. That
 * removes the need for a 300-key field map, which is where a migration like this
 * would normally go wrong.
 *
 * The three keys that DO need handling are the ones ds-cta defines but a
 * post-loop node never stored, where CTA's default would newly apply:
 *   - cta_style   : set to style6 (the point of the migration)
 *   - cards       : CTA's styles 1-5 repeater, defaults to 4 dummy cards. Style 6
 *                   reads `programs`, so `cards` is forced empty to keep it inert.
 *   - header_label: CTA's header adds a right-side label defaulting to
 *                   "Lorem ipsum →" which would appear on every migrated node.
 *
 * SAFETY
 * ------
 *   - Dry run is the default. Writing requires --execute.
 *   - `_fl_builder_data` and `_fl_builder_draft` migrate TOGETHER per post; if
 *     only one is present that is reported, because a half-migrated post reverts
 *     the next time an editor opens it.
 *   - `card_layout` is left in place as the rollback discriminator.
 *   - Revisions are never touched. A revision restore therefore resurrects a
 *     ds-post-loop/program node, which is exactly why that layout still renders.
 *   - The original settings are stashed on the node (`_ds_premigration`) so a
 *     rollback needs no external state.
 *
 * @package ds-toolkit
 * @since   1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DS_Program_Cards_Migrator {

	const STASH_KEY = '_ds_premigration';

	/** Layout -> target CTA style. */
	public static function map() {
		return array( 'program' => 'style6' );
	}

	/**
	 * Find every affected node, grouped by post.
	 *
	 * @return array post_id => array( 'post' => WP_Post, 'nodes' => array( meta_key => array( node_id => layout ) ) )
	 */
	public static function scan() {
		global $wpdb;
		$map  = self::map();
		$rows = $wpdb->get_results(
			"SELECT p.ID, m.meta_key, m.meta_value
			   FROM {$wpdb->postmeta} m
			   JOIN {$wpdb->posts} p ON p.ID = m.post_id
			  WHERE m.meta_key IN ('_fl_builder_data','_fl_builder_draft')
			    AND p.post_type <> 'revision'
			    AND m.meta_value LIKE '%ds-post-loop%'"
		);

		$out = array();
		foreach ( $rows as $r ) {
			$data = maybe_unserialize( $r->meta_value );
			if ( ! is_array( $data ) ) {
				continue;
			}
			foreach ( $data as $node_id => $node ) {
				if ( ! is_object( $node ) || ( $node->settings->type ?? '' ) !== 'ds-post-loop' ) {
					continue;
				}
				$layout = $node->settings->card_layout ?? '';
				if ( ! isset( $map[ $layout ] ) ) {
					continue;
				}
				if ( ! isset( $out[ $r->ID ] ) ) {
					$out[ $r->ID ] = array(
						'post'  => get_post( $r->ID ),
						'nodes' => array(),
					);
				}
				$out[ $r->ID ]['nodes'][ $r->meta_key ][ $node_id ] = $layout;
			}
		}
		return $out;
	}

	/**
	 * Convert one node's settings in place.
	 *
	 * @param object $settings Node settings (modified by reference semantics via return).
	 * @param string $layout   Source card_layout.
	 * @return object
	 */
	public static function convert( $settings, $layout ) {
		$map = self::map();

		// Stash the original so rollback needs no external state.
		if ( ! isset( $settings->{self::STASH_KEY} ) ) {
			$settings->{self::STASH_KEY} = array(
				'type'        => 'ds-post-loop',
				'card_layout' => $layout,
			);
		}

		$settings->type      = 'ds-cta';
		$settings->cta_style = $map[ $layout ];

		// Neutralise the two CTA defaults that would otherwise newly apply.
		$settings->cards        = array();
		$settings->header_label = '';

		// card_layout intentionally retained as the rollback discriminator.
		return $settings;
	}

	/**
	 * Run the migration.
	 *
	 * @param bool $execute      Write when true; report only when false.
	 * @param bool $require_pair Skip posts that do not have both data and draft.
	 * @return array Report lines + counters.
	 */
	public static function run( $execute = false, $require_pair = true ) {
		$found  = self::scan();
		$report = array();
		$stats  = array(
			'posts'   => 0,
			'nodes'   => 0,
			'skipped' => 0,
		);

		foreach ( $found as $post_id => $info ) {
			$keys      = array_keys( $info['nodes'] );
			$has_data  = in_array( '_fl_builder_data', $keys, true );
			$has_draft = in_array( '_fl_builder_draft', $keys, true );
			$title     = $info['post'] ? $info['post']->post_title : '(unknown)';
			$ptype     = $info['post'] ? $info['post']->post_type : '?';

			// A post whose published and draft layouts disagree would visually
			// revert as soon as an editor opened it, so pairs are enforced unless
			// explicitly overridden.
			if ( $require_pair && ! ( $has_data && $has_draft ) ) {
				$stats['skipped']++;
				$report[] = sprintf(
					'SKIP  post %d (%s: %s) — only %s present; re-run with --allow-unpaired to migrate anyway',
					$post_id,
					$ptype,
					$title,
					$has_data ? '_fl_builder_data' : '_fl_builder_draft'
				);
				continue;
			}

			$stats['posts']++;
			foreach ( $info['nodes'] as $meta_key => $nodes ) {
				$data = maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) );
				if ( ! is_array( $data ) ) {
					continue;
				}
				$changed = false;
				foreach ( $nodes as $node_id => $layout ) {
					if ( ! isset( $data[ $node_id ] ) ) {
						continue;
					}
					$data[ $node_id ]->settings = self::convert( $data[ $node_id ]->settings, $layout );
					$changed = true;
					$stats['nodes']++;
					$report[] = sprintf(
						'%s post %d (%s: %s) %s node %s : ds-post-loop/%s -> ds-cta/%s',
						$execute ? 'DONE ' : 'WOULD',
						$post_id,
						$ptype,
						$title,
						str_replace( '_fl_builder_', '', $meta_key ),
						$node_id,
						$layout,
						self::map()[ $layout ]
					);
				}
				if ( $changed && $execute ) {
					update_post_meta( $post_id, $meta_key, $data );
				}
			}

			if ( $execute ) {
				self::clear_cache( $post_id );
			}
		}

		return array(
			'report' => $report,
			'stats'  => $stats,
		);
	}

	/**
	 * Roll one or all migrated nodes back to ds-post-loop using the stash.
	 *
	 * @param bool $execute Write when true.
	 * @return array
	 */
	public static function rollback( $execute = false ) {
		global $wpdb;
		$rows   = $wpdb->get_results(
			"SELECT p.ID, m.meta_key, m.meta_value
			   FROM {$wpdb->postmeta} m
			   JOIN {$wpdb->posts} p ON p.ID = m.post_id
			  WHERE m.meta_key IN ('_fl_builder_data','_fl_builder_draft')
			    AND p.post_type <> 'revision'
			    AND m.meta_value LIKE '%" . self::STASH_KEY . "%'"
		);
		$report = array();
		$n      = 0;

		foreach ( $rows as $r ) {
			$data = maybe_unserialize( $r->meta_value );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$changed = false;
			foreach ( $data as $node_id => $node ) {
				if ( ! is_object( $node ) || empty( $node->settings->{self::STASH_KEY} ) ) {
					continue;
				}
				$stash = (array) $node->settings->{self::STASH_KEY};
				$node->settings->type        = $stash['type'] ?? 'ds-post-loop';
				$node->settings->card_layout = $stash['card_layout'] ?? 'program';
				unset( $node->settings->cta_style, $node->settings->{self::STASH_KEY} );
				$changed = true;
				$n++;
				$report[] = sprintf(
					'%s post %d %s node %s -> ds-post-loop/%s',
					$execute ? 'DONE ' : 'WOULD',
					$r->ID,
					str_replace( '_fl_builder_', '', $r->meta_key ),
					$node_id,
					$node->settings->card_layout
				);
			}
			if ( $changed && $execute ) {
				update_post_meta( $r->ID, $r->meta_key, $data );
				self::clear_cache( $r->ID );
			}
		}

		return array(
			'report' => $report,
			'stats'  => array( 'nodes' => $n ),
		);
	}

	/**
	 * Drop Beaver Builder's cached CSS/JS for a post so the new module's assets
	 * are regenerated. Without this the page keeps serving the old layout CSS.
	 *
	 * @param int $post_id Post id.
	 */
	public static function clear_cache( $post_id ) {
		if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'delete_asset_cache' ) ) {
			FLBuilderModel::delete_asset_cache( $post_id );
		}
		// The asset cache helper misses symlinked/renamed files on some hosts, so
		// unlink the layout files directly as well.
		$dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/uploads/bb-plugin/cache' : '';
		if ( $dir && is_dir( $dir ) ) {
			foreach ( glob( $dir . '/' . (int) $post_id . '-layout*.{css,js}', GLOB_BRACE ) as $f ) {
				@unlink( $f );
			}
		}
	}
}

/* -------------------------------------------------------------- WP-CLI ---- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Move manual-list card modules out of Post Loop into CTA.
	 */
	class DS_Program_Cards_CLI {

		/**
		 * Report or perform the migration.
		 *
		 * ## OPTIONS
		 *
		 * [--execute]
		 * : Write the changes. Without this flag nothing is modified.
		 *
		 * [--allow-unpaired]
		 * : Migrate posts that only have published OR draft builder data.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ds migrate-program-cards
		 *     wp ds migrate-program-cards --execute
		 */
		public function __invoke( $args, $assoc ) {
			$execute = isset( $assoc['execute'] );
			$paired  = ! isset( $assoc['allow-unpaired'] );

			$res = DS_Program_Cards_Migrator::run( $execute, $paired );
			foreach ( $res['report'] as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::log( '' );
			WP_CLI::log( sprintf( 'posts: %d   nodes: %d   skipped posts: %d', $res['stats']['posts'], $res['stats']['nodes'], $res['stats']['skipped'] ) );
			if ( ! $execute ) {
				WP_CLI::success( 'DRY RUN — nothing was written. Re-run with --execute to apply.' );
			} else {
				WP_CLI::success( 'Migration applied. Verify the pages before purging any host cache.' );
			}
		}
	}

	/**
	 * Undo the migration using the settings stash.
	 */
	class DS_Program_Cards_Rollback_CLI {

		/**
		 * ## OPTIONS
		 *
		 * [--execute]
		 * : Write the changes. Without this flag nothing is modified.
		 */
		public function __invoke( $args, $assoc ) {
			$execute = isset( $assoc['execute'] );
			$res     = DS_Program_Cards_Migrator::rollback( $execute );
			foreach ( $res['report'] as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::log( '' );
			WP_CLI::log( sprintf( 'nodes: %d', $res['stats']['nodes'] ) );
			if ( ! $execute ) {
				WP_CLI::success( 'DRY RUN — nothing was written. Re-run with --execute to apply.' );
			} else {
				WP_CLI::success( 'Rolled back.' );
			}
		}
	}

	WP_CLI::add_command( 'ds migrate-program-cards', 'DS_Program_Cards_CLI' );
	WP_CLI::add_command( 'ds rollback-program-cards', 'DS_Program_Cards_Rollback_CLI' );
}
