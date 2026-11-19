<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'DS_Site_Layouts' ) ) :
/**
 * Site layouts: extra styles for a LeagueApps block that live in the site's theme, not in the plugin (1.20.0).
 *
 * Alipio 2026-11-19: "update the leagueapps hero banner module to have custom template and it will adapt in options
 * below ... open doors on ssh to just utilize the hero banner custom template to create cool things". Until now a
 * site-only look meant hand-written hooks in functions.php (team91national "Numeral Window", 2026-11-11). Now a
 * developer drops files into the (child) theme and the block offers them:
 *
 *   <theme>/ds-layouts/<block>/<slug>.php    the markup (required). Header comment, read without running the file:
 *                                              Layout Name: Numeral Window       label in the style list ("Site: ...")
 *                                              Description: ...                  shown as the field's help
 *                                              Uses: text, buttons, bg           the block's own sections to keep showing
 *                                              Hide: kenburns, slide_nav         block fields to hide inside those sections
 *   <theme>/ds-layouts/<block>/<slug>.json   the layout's own settings (optional), Beaver field definitions:
 *                                              {"sections":[{"key":"numeral","title":"Numeral","fields":{
 *                                                "number":{"type":"text","label":"Number","default":"91"}}}]}
 *   <theme>/ds-layouts/<block>/<slug>.css    loaded only on pages that use the layout (optional)
 *   <theme>/ds-layouts/<block>/<slug>.js     ditto, in the footer (optional)
 *
 * The .php file runs with $module (the block), $settings (all of the block's settings), $fields (this layout's own
 * values by their JSON names, defaults filled in) and $layout (this file's header data). Its own field values are
 * stored as sl_<slug>__<name> so they can never collide with the block's fields.
 *
 * Safety: the files come from the server only (nothing is typed into the builder); a layout that is missing, or
 * throws, falls back to the block's first style, so a renamed theme or a typo never breaks a page. Only the field
 * types and keys in allowed_*() pass from the JSON into the form.
 */
class DS_Site_Layouts {

	const PREFIX = 'site-';

	/** @var array block => slug => layout */
	private static $cache = array();

	/** Folders searched for a block's layouts: the child theme first, then the parent theme. */
	public static function dirs( $block ) {
		$block = self::clean( $block );
		$dirs  = array();
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $root ) {
			$d = trailingslashit( $root ) . 'ds-layouts/' . $block . '/';
			if ( ! in_array( $d, $dirs, true ) ) { $dirs[] = $d; }
		}
		return (array) apply_filters( 'ds_site_layout_dirs', $dirs, $block );
	}

	/** Every layout found for a block, keyed by slug, in label order. Cached per request. */
	public static function all( $block ) {
		$block = self::clean( $block );
		if ( isset( self::$cache[ $block ] ) ) { return self::$cache[ $block ]; }
		$out = array();
		foreach ( self::dirs( $block ) as $dir ) {
			foreach ( (array) glob( $dir . '*.php' ) as $file ) {
				$slug = basename( $file, '.php' );
				if ( ! self::slug_ok( $slug ) || isset( $out[ $slug ] ) ) { continue; }   // a child-theme file wins over the parent's
				$out[ $slug ] = self::read( $block, $slug, $file );
			}
		}
		uasort( $out, function ( $a, $b ) { return strcasecmp( $a['label'], $b['label'] ); } );
		return self::$cache[ $block ] = $out;
	}

	/** Forget what was read (tests, and after a deploy in the same request). */
	public static function flush() { self::$cache = array(); }

	/** The layout a saved style key points at ("site-<slug>"), or null. */
	public static function get( $block, $key ) {
		$key = (string) $key;
		if ( 0 !== strpos( $key, self::PREFIX ) ) { return null; }
		$all = self::all( $block );
		$slug = substr( $key, strlen( self::PREFIX ) );
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/** True when a saved style key names a site layout (found or not). */
	public static function is_site_key( $key ) { return 0 === strpos( (string) $key, self::PREFIX ); }

	/** Options for the block's style select: "site-<slug>" => "Site: <Layout Name>". */
	public static function style_options( $block ) {
		$o = array();
		foreach ( self::all( $block ) as $slug => $l ) { $o[ self::PREFIX . $slug ] = sprintf( __( 'Site: %s', 'ds-toolkit' ), $l['label'] ); }
		return $o;
	}

	/** Where a layout's own field is stored in the block settings. */
	public static function field_name( $slug, $field ) {
		return 'sl_' . str_replace( '-', '_', $slug ) . '__' . preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $field ) );
	}

	/**
	 * Adds every layout of $block to the block's settings form when Beaver Builder registers it: the style option,
	 * the toggle (the block sections in Uses + the layout's own sections), the hidden fields and the sections.
	 * $tab/$section/$field locate the style select; $default_uses is used when a layout has no Uses line.
	 */
	public static function hook_form( $block, $tab, $section, $field, $default_uses ) {
		add_filter( 'fl_builder_register_settings_form', function ( $form, $id ) use ( $block, $tab, $section, $field, $default_uses ) {
			if ( $id !== $block || empty( $form[ $tab ]['sections'][ $section ]['fields'][ $field ] ) ) { return $form; }
			return self::extend_form( $form, $block, $tab, $section, $field, $default_uses );
		}, 15, 2 );
	}

	/** The form with $block's layouts added (see hook_form). Pure, so it can be tested without Beaver Builder. */
	public static function extend_form( $form, $block, $tab, $section, $field, $default_uses ) {
		$layouts = self::all( $block );
		if ( ! $layouts ) { return $form; }
		$known = array();
		foreach ( $form as $t ) { foreach ( (array) ( $t['sections'] ?? array() ) as $k => $unused ) { $known[] = $k; } }
		$sel = &$form[ $tab ]['sections'][ $section ]['fields'][ $field ];
		foreach ( $layouts as $slug => $l ) {
			$key  = self::PREFIX . $slug;
			$uses = $l['uses'] ? $l['uses'] : $default_uses;
			$uses = array_values( array_intersect( $uses, $known ) );   // a typo in Uses never names a section that does not exist
			$own  = array();
			foreach ( $l['sections'] as $sk => $sec ) { $own[] = $sk; $form[ $tab ]['sections'][ $sk ] = $sec; }
			$sel['options'][ $key ] = sprintf( __( 'Site: %s', 'ds-toolkit' ), $l['label'] );
			$sel['toggle'][ $key ]  = array( 'sections' => array_merge( $uses, $own ) );
			if ( $l['hide'] ) { $sel['hide'][ $key ] = array( 'fields' => $l['hide'] ); }
		}
		if ( empty( $sel['help'] ) ) { $sel['help'] = ''; }
		$sel['help'] = trim( $sel['help'] . ' ' . __( 'Styles marked "Site:" come from this site\'s theme (ds-layouts folder).', 'ds-toolkit' ) );
		unset( $sel );
		return $form;
	}

	/** This layout's own values by their JSON names, the field default where nothing is saved. */
	public static function values( $layout, $settings ) {
		$v = array();
		foreach ( $layout['sections'] as $sec ) {
			foreach ( $sec['fields'] as $name => $f ) {
				$short = $f['ds_name'];
				$v[ $short ] = isset( $settings->$name ) && '' !== $settings->$name && array() !== $settings->$name ? $settings->$name : ( $f['default'] ?? '' );
			}
		}
		return $v;
	}

	/**
	 * Prints the layout. Returns false (and prints nothing) when the file is gone or throws, so the caller can fall
	 * back to its first style. In the builder the layout's CSS/JS are linked inline, because a partial refresh does
	 * not pick up assets enqueued after the page loaded.
	 */
	public static function render( $layout, $module, $settings ) {
		if ( empty( $layout['file'] ) || ! is_readable( $layout['file'] ) ) { return false; }
		$fields = self::values( $layout, $settings );
		ob_start();
		try {
			( static function ( $__file, $module, $settings, $fields, $layout ) { include $__file; } )( $layout['file'], $module, $settings, $fields, $layout );
		} catch ( Throwable $e ) {
			ob_end_clean();
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<!-- DS site layout "' . esc_html( $layout['slug'] ) . '" failed: ' . esc_html( $e->getMessage() ) . ' (' . esc_html( basename( $e->getFile() ) ) . ':' . (int) $e->getLine() . ') -->';
			}
			return false;
		}
		$html = ob_get_clean();
		if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
			if ( $layout['css'] ) { $html = '<link rel="stylesheet" href="' . esc_url( add_query_arg( 'ver', $layout['css_ver'], $layout['css'] ) ) . '">' . $html; }
			if ( $layout['js'] ) { $html .= '<script src="' . esc_url( add_query_arg( 'ver', $layout['js_ver'], $layout['js'] ) ) . '"></script>'; }
		} else {
			self::enqueue( $layout );   // normally already done in the head by the block's enqueue_scripts()
		}
		echo $html; // the layout file escapes its own output, as any theme template does
		return true;
	}

	/** Enqueues the layout's CSS (head when called early) and JS (footer). */
	public static function enqueue( $layout ) {
		$h = 'ds-site-layout-' . $layout['block'] . '-' . $layout['slug'];
		if ( $layout['css'] ) { wp_enqueue_style( $h, $layout['css'], array(), $layout['css_ver'] ); }
		if ( $layout['js'] ) { wp_enqueue_script( $h, $layout['js'], array(), $layout['js_ver'], true ); }
	}

	/* ------------------------------------------------------------------ internals */

	private static function read( $block, $slug, $file ) {
		$h    = get_file_data( $file, array( 'name' => 'Layout Name', 'desc' => 'Description', 'uses' => 'Uses', 'hide' => 'Hide' ) );
		$base = substr( $file, 0, -4 );
		$list = function ( $s ) { return array_values( array_filter( array_map( function ( $x ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( trim( $x ) ) ); }, explode( ',', (string) $s ) ) ) ); };
		$json = is_readable( $base . '.json' ) ? json_decode( (string) file_get_contents( $base . '.json' ), true ) : null;
		$url  = function ( $path ) {
			if ( ! is_readable( $path ) ) { return ''; }
			foreach ( array( get_stylesheet_directory() => get_stylesheet_directory_uri(), get_template_directory() => get_template_directory_uri() ) as $dir => $uri ) {
				if ( 0 === strpos( $path, trailingslashit( $dir ) ) ) { return $uri . '/' . substr( $path, strlen( trailingslashit( $dir ) ) ); }
			}
			return (string) apply_filters( 'ds_site_layout_asset_url', '', $path );
		};
		$css = $url( $base . '.css' ); $js = $url( $base . '.js' );
		return array(
			'block'       => $block,
			'slug'        => $slug,
			'file'        => $file,
			'label'       => '' !== trim( $h['name'] ) ? trim( $h['name'] ) : ucwords( str_replace( '-', ' ', $slug ) ),
			'description' => trim( $h['desc'] ),
			'uses'        => $list( $h['uses'] ),
			'hide'        => $list( $h['hide'] ),
			'sections'    => self::sections( $slug, is_array( $json ) ? $json : array(), trim( $h['desc'] ) ),
			'css'         => $css, 'css_ver' => $css ? (string) filemtime( $base . '.css' ) : '',
			'js'          => $js, 'js_ver' => $js ? (string) filemtime( $base . '.js' ) : '',
		);
	}

	/** The JSON sections as Beaver form sections, keys namespaced to the layout, only the allowed types and keys. */
	private static function sections( $slug, $json, $desc ) {
		$out = array();
		$i   = 0;
		foreach ( (array) ( $json['sections'] ?? array() ) as $sec ) {
			if ( ! is_array( $sec ) ) { continue; }
			$i++;
			$sk     = 'sl_' . str_replace( '-', '_', $slug ) . '_' . ( preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $sec['key'] ?? '' ) ) ) ?: 's' . $i );
			$fields = array();
			foreach ( (array) ( $sec['fields'] ?? array() ) as $name => $f ) {
				if ( ! is_array( $f ) || ! in_array( $f['type'] ?? '', self::allowed_types(), true ) ) { continue; }
				$clean = array_intersect_key( $f, array_flip( self::allowed_keys() ) );
				if ( 'select' === $clean['type'] && empty( $clean['options'] ) ) { continue; }   // a select with nothing to pick is a broken field
				$clean['label']   = (string) ( $clean['label'] ?? ucwords( str_replace( '_', ' ', $name ) ) );
				$clean['preview'] = array( 'type' => 'refresh' );
				$clean['ds_name'] = (string) $name;
				$fields[ self::field_name( $slug, $name ) ] = $clean;
			}
			if ( $fields ) {
				$out[ $sk ] = array( 'title' => (string) ( $sec['title'] ?? __( 'Layout', 'ds-toolkit' ) ), 'fields' => $fields );
				if ( 1 === $i && '' !== $desc ) { $out[ $sk ]['description'] = $desc; }
			}
		}
		return $out;
	}

	/** Field types a layout may declare: plain content and design controls, nothing that runs code. */
	public static function allowed_types() {
		return array( 'text', 'textarea', 'editor', 'select', 'photo', 'multiple-photos', 'video', 'color', 'unit', 'link', 'icon', 'typography', 'dimension', 'align' );
	}

	/** Field keys passed from the JSON into the form. */
	public static function allowed_keys() {
		return array( 'type', 'label', 'default', 'options', 'help', 'description', 'placeholder', 'multiple', 'show_remove', 'show_reset', 'show_alpha',
			'units', 'slider', 'responsive', 'rows', 'media_buttons', 'connections', 'show_target', 'show_nofollow' );
	}

	public static function slug_ok( $slug ) { return (bool) preg_match( '/^[a-z0-9][a-z0-9-]{0,48}$/', (string) $slug ); }

	private static function clean( $block ) { return preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $block ) ); }
}
endif;
