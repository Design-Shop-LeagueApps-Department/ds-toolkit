<?php
/**
 * Design Shop HQ -> blueprint package fetch. HQ's Plugin Store copies premium plugins from a blueprint.
 *
 * HQ keeps one vetted zip per premium plugin (Beaver Builder, UABB, ACF Pro, WPMU DEV...) for fleet sites whose own
 * licence download fails. The copies come from a blueprint (dslaunchpad5/6/7), which takes the vendors' updates with
 * its own licences. This route lets HQ fetch them itself, weekly or on "Fetch now", over the same signed link as
 * "Check in now":
 *
 *   GET /wp-json/ds-toolkit/v1/hq-package?n=<nonce>                    -> what this blueprint runs (slug => version)
 *   GET /wp-json/ds-toolkit/v1/hq-package?n=<nonce>&type=<t>&slug=<s>  -> that plugin or theme folder as a zip
 *
 * Who gets an answer:
 *   - Only a BLUEPRINT install (WP Engine PWP_NAME in BLUEPRINTS). A partner site cloned from a blueprint carries this
 *     same code but has its own install name, so the route is a 404 there, signed or not.
 *   - Only a call HQ signed with its own key over "dshq-hq\npackage\n<this host>\n<time>\n<nonce>|<type>|<slug>"
 *     (DS_HQ_Link::hq_signed), time within 5 minutes. Anyone else gets a 404.
 *   - Only a folder under wp-content/plugins or the theme root, named like a slug, with no path in it.
 * What comes back is signed by this site's own HQ link key (HQ holds the public half), so HQ knows the zip came from
 * the blueprint it asked: the list over "dshq-package-list\n<nonce>\n<sha256 of the list JSON>", the zip over
 * "dshq-package\n<nonce>\n<type>\n<slug>\n<version>\n<sha256 of the zip>". The zip is built fresh for each call
 * (symlinks, .git, logs and .DS_Store left out) and deleted after it is sent. HQ runs its own checks before it stores
 * anything (one top folder, no secrets, no licence key, the behaviour scan).
 *
 * Off switch: the HQ link's own (ds_toolkit_settings.tripwire_hq_report = 0), or define DS_HQ_PACKAGE_DISABLED.
 *
 * @package DS_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DS_HQ_Package {

	const BLUEPRINTS = array( 'dslaunchpad5', 'dslaunchpad6', 'dslaunchpad7' );
	const MAX_ZIP    = 104857600; // 100 MB of source files; the biggest premium plugin is ~62 MB unzipped

	/** Called from DS_HQ_Link::rest_routes(). */
	public static function rest_routes() {
		register_rest_route( 'ds-toolkit/v1', '/hq-package', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'serve' ),
			'permission_callback' => '__return_true', // HQ's signature is the permission, checked in serve()
		) );
	}

	/** True on a blueprint install only (WP Engine install name, never the domain, which a clone can share). */
	public static function is_blueprint() {
		if ( defined( 'DS_HQ_PACKAGE_DISABLED' ) && DS_HQ_PACKAGE_DISABLED ) {
			return false;
		}
		return defined( 'PWP_NAME' ) && in_array( strtolower( (string) PWP_NAME ), self::BLUEPRINTS, true );
	}

	public static function serve( WP_REST_Request $r ) {
		$n    = (string) $r->get_param( 'n' );
		$type = (string) $r->get_param( 'type' );
		$slug = (string) $r->get_param( 'slug' );
		if ( ! self::is_blueprint() || ! preg_match( '/^[a-f0-9]{16,64}$/', $n )
			|| ( '' !== $slug && ( ! in_array( $type, array( 'plugin', 'theme' ), true ) || ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug ) ) )
			|| ! DS_HQ_Link::has_key() || ! DS_HQ_Link::hq_signed( $r, 'package', "$n|$type|$slug" ) ) {
			return new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', array( 'status' => 404 ) );
		}
		return '' === $slug ? self::listing( $n ) : self::zip( $n, $type, $slug );
	}

	/** Every plugin and theme this blueprint has, with its version. */
	private static function listing( $n ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$items = array( 'plugin' => array(), 'theme' => array() );
		foreach ( get_plugins() as $file => $p ) {
			if ( false !== strpos( $file, '/' ) ) {
				$items['plugin'][ dirname( $file ) ] = (string) $p['Version'];
			}
		}
		foreach ( wp_get_themes() as $slug => $t ) {
			$items['theme'][ $slug ] = (string) $t->get( 'Version' );
		}
		$json = wp_json_encode( $items );
		$res  = rest_ensure_response( array( 'items' => $json, 'sig' => self::sign( "dshq-package-list\n$n\n" . hash( 'sha256', $json ) ) ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/** One plugin or theme folder as a fresh zip, streamed, then deleted. */
	private static function zip( $n, $type, $slug ) {
		if ( 'plugin' === $type ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$ver = '';
			foreach ( get_plugins( '/' . $slug ) as $p ) {
				$ver = (string) $p['Version'];
				break;
			}
			$dir = WP_PLUGIN_DIR . '/' . $slug;
		} else {
			$t   = wp_get_theme( $slug );
			$ver = $t->exists() ? (string) $t->get( 'Version' ) : '';
			$dir = get_theme_root( $slug ) . '/' . $slug;
		}
		if ( '' === $ver || ! is_dir( $dir ) || is_link( $dir ) ) {
			return new WP_Error( 'not_here', 'This blueprint has no ' . $type . ' named ' . $slug . '.', array( 'status' => 404 ) );
		}
		$tmp = self::build_zip( $dir, $slug );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$sha = hash_file( 'sha256', $tmp );
		$sig = self::sign( "dshq-package\n$n\n$type\n$slug\n$ver\n$sha" );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		header( 'X-DS-Pkg-Version: ' . $ver );
		header( 'X-DS-Pkg-Sha256: ' . $sha );
		header( 'X-DS-Pkg-Sig: ' . $sig );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	/**
	 * Zip $dir as "<slug>/..." into a temp file. Symlinks, .git/.svn/node_modules, logs and .DS_Store are left out.
	 * Returns the temp path (the caller deletes it) or WP_Error. Public for the tests.
	 */
	public static function build_zip( $dir, $slug ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_failed', 'ZipArchive is missing.', array( 'status' => 500 ) );
		}
		$tmp = wp_tempnam( 'ds-hq-package.zip' );
		$z   = new ZipArchive();
		if ( true !== $z->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			return new WP_Error( 'zip_failed', 'Could not build the zip.', array( 'status' => 500 ) );
		}
		$total = 0;
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $it as $f ) {
			$rel = substr( str_replace( '\\', '/', $f->getPathname() ), strlen( $dir ) + 1 );
			if ( $f->isLink() || preg_match( '#(^|/)(\.git|\.svn|node_modules)(/|$)|(^|/)\.DS_Store$|\.log$#', $rel ) ) {
				continue;
			}
			if ( $f->isDir() ) {
				$z->addEmptyDir( $slug . '/' . $rel );
			} elseif ( $f->isFile() ) {
				$total += $f->getSize();
				if ( $total > self::MAX_ZIP ) {
					$z->close();
					@unlink( $tmp );
					return new WP_Error( 'too_big', 'The folder is bigger than ' . size_format( self::MAX_ZIP ) . '.', array( 'status' => 413 ) );
				}
				$z->addFile( $f->getPathname(), $slug . '/' . $rel );
			}
		}
		if ( ! $z->close() ) {
			@unlink( $tmp );
			return new WP_Error( 'zip_failed', 'Could not build the zip.', array( 'status' => 500 ) );
		}
		return $tmp;
	}

	/** Base64 Ed25519 signature with this site's HQ link key. */
	private static function sign( $msg ) {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}
		return base64_encode( sodium_crypto_sign_detached( $msg, base64_decode( DS_HQ_Link::state()['sk'] ) ) );
	}
}
