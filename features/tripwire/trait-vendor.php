<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tripwire: is this file exactly what its vendor published? (wordpress.org per-release checksums.)
 *
 * One part of DS_Tripwire, which was a single 1,960-line class until the 2026-11 audit. The methods were
 * moved here as they were: a trait, so they are still methods of the feature (same visibility, same $this).
 * Every detector, the constants, the two scans and the sending of an alert stay in class-ds-tripwire.php, which
 * is also the one file Tripwire exempts from its own content scan: nothing that holds a detection pattern may
 * move out of it.
 */
trait DS_Tripwire_Vendor {

    /**
     * md5s of files already verified as known-good, loaded from includes/known-good.md5.
     *
     * This is the gate the engine's own header asks the caller to apply: "WHAT MAKES IT SAFE TO BE
     * AGGRESSIVE: the CALLER suppresses findings whose md5 is known-good." fw-check-site-v2.sh has
     * always done this with the fleet manifest and reported 3 findings on thepaohio.com; this plugin
     * had no gate at all and alerted on 100 files there. Same engine, same rules - the list is the
     * entire difference, and it is why stock Beaver Builder core (on every fleet site), Divi, Headway,
     * Hummingbird and Wordfence were being reported as web shells.
     *
     * Suppression is by HASH, never by path or name: a file cannot be made to collide with a verified
     * hash, and if it matches it IS that file. Missing or unreadable list = no suppression, so the
     * failure mode is "keep scanning", never "go quiet". Regenerate with
     * fleet-audit/bin/gen-known-good.sh on every release; a stale list only costs a false positive.
     */
    /**
     * Is this file one the vendor verifiably ships, at this path, in the version the plugin declares?
     * Returns true ONLY on an exact path + md5 hit in the official manifest. See VENDOR_BASE above
     * for why this is hash equality and not a name or path exemption.
     */
    /**
     * One manifest GET. $canonical lifts pre_http_request for the call, which is how we reach
     * wordpress.org itself on a host that redirects WP's HTTP layer to a lagging mirror. The filter
     * set is restored in every exit path, including a thrown one.
     */
    private static function vendor_get( $url, $canonical ) {
        $args = array(
            'timeout'     => self::VENDOR_TIMEOUT,
            'redirection' => 1,
            'sslverify'   => true,
            'user-agent'  => 'DS-Tripwire/' . ( defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '?' ),
        );
        if ( ! $canonical ) {
            return wp_remote_get( $url, $args );
        }
        $saved = isset( $GLOBALS['wp_filter']['pre_http_request'] ) ? $GLOBALS['wp_filter']['pre_http_request'] : null;
        if ( null !== $saved ) { unset( $GLOBALS['wp_filter']['pre_http_request'] ); }
        try {
            return wp_remote_get( $url, $args );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'ds_vendor_canonical', $e->getMessage() );
        } finally {
            if ( null !== $saved ) { $GLOBALS['wp_filter']['pre_http_request'] = $saved; }
        }
    }

    private static function vendor_verified( $path, $md5 ) {
        try {
            if ( '' === (string) $path || '' === (string) $md5 ) { return false; }
            $md5 = strtolower( $md5 );

            // a file already verified on this site never needs the network again
            if ( null === self::$vendor_ok ) {
                $c = function_exists( 'get_transient' ) ? get_transient( 'ds_tripwire_vendor_ok' ) : false;
                self::$vendor_ok = is_array( $c ) ? $c : array();
            }
            if ( isset( self::$vendor_ok[ $md5 ] ) ) { self::$vendor_stats['verified']++; return true; }

            // the path must sit inside a plugin folder, and give us the slug + the vendor-relative path
            $norm = str_replace( '\\', '/', (string) $path );
            if ( ! preg_match( '#/wp-content/plugins/([^/]+)/(.+)$#', $norm, $mm ) ) { return false; }
            $slug = $mm[1];
            $rel  = $mm[2];
            // our own plugin is never on wordpress.org; do not even ask
            if ( 'ds-toolkit' === $slug || ! preg_match( '/^[a-z0-9][a-z0-9\-\.]*$/i', $slug ) ) { return false; }

            $ver = self::plugin_version( $slug );
            if ( '' === $ver || ! preg_match( '/^[0-9A-Za-z\.\-]+$/', $ver ) ) { return false; }

            $key = $slug . '|' . $ver;
            if ( isset( self::$vendor_tried[ $key ] ) ) {
                // already fetched (or failed) this run. If it succeeded, the md5 set was merged, so
                // arriving here means this file is simply not a verified vendor file.
                return false;
            }
            if ( self::$vendor_fetches >= self::VENDOR_FETCH_MAX ) { self::$vendor_stats['budget']++; return false; }

            self::$vendor_tried[ $key ] = true;
            self::$vendor_fetches++;

            if ( ! function_exists( 'wp_remote_get' ) ) { self::$vendor_stats['error']++; return false; }
            $url = self::VENDOR_BASE . rawurlencode( $slug ) . '/' . rawurlencode( $ver ) . '.json';
            $r   = self::vendor_get( $url, false );
            $code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
            // WP ENGINE MIRROR LAG. WPE hooks pre_http_request and serves wordpress.org from its own
            // artifact bucket (wpe-wp-updater-artifacts-*), which trails the real index: on
            // 2026-09-28 google-site-kit 1.188.0, released 09-21, was still NoSuchKey there while
            // wordpress.org had it, and every one of 20 pilot installs was on 1.186-1.188. Smart
            // Plugin Manager keeps WPE sites on the newest release, so the mirror is most likely to
            // be missing exactly the version installed - i.e. it fails where we need it most. So on
            // a 404 only, ask the canonical host once with those filters lifted. Read-only, one
            // small GET, and the answer is cached for a week.
            if ( 200 !== $code ) {
                $r2 = self::vendor_get( $url, true );
                $c2 = is_wp_error( $r2 ) ? 0 : (int) wp_remote_retrieve_response_code( $r2 );
                if ( 200 === $c2 ) { $r = $r2; $code = $c2; self::$vendor_stats['canonical']++; }
            }
            if ( is_wp_error( $r ) ) { self::$vendor_stats['error']++; return false; }
            if ( 200 !== $code ) { self::$vendor_stats['no_manifest']++; return false; }  // premium, or a version nobody published
            $body = (string) wp_remote_retrieve_body( $r );
            if ( '' === $body || strlen( $body ) > self::VENDOR_MAX_BYTES ) { self::$vendor_stats['error']++; return false; }
            $d = json_decode( $body, true );
            if ( ! is_array( $d ) || empty( $d['files'] ) || ! is_array( $d['files'] ) ) { self::$vendor_stats['error']++; return false; }

            // Merge EVERY md5 this manifest declares into the verified set, so the rest of this run
            // and the next week of runs cost nothing. Keyed by md5 only, which is safe because a
            // match still had to come from this plugin's own manifest.
            $add = array();
            foreach ( $d['files'] as $fpath => $meta ) {
                if ( is_array( $meta ) && ! empty( $meta['md5'] ) ) { $add[ strtolower( $meta['md5'] ) ] = true; }
            }
            $hit = isset( $d['files'][ $rel ] ) && is_array( $d['files'][ $rel ] )
                && strtolower( (string) $d['files'][ $rel ]['md5'] ) === $md5;

            if ( $hit ) {
                self::$vendor_ok = array_merge( self::$vendor_ok, $add );
                if ( function_exists( 'set_transient' ) ) {
                    // cap the stored set so a site with many plugins cannot grow this without bound
                    $keep = self::$vendor_ok;
                    if ( count( $keep ) > 20000 ) { $keep = array_slice( $keep, -20000, null, true ); }
                    set_transient( 'ds_tripwire_vendor_ok', $keep, self::VENDOR_TTL );
                }
                self::$vendor_stats['verified']++;
                return true;
            }
            if ( ! isset( $d['files'][ $rel ] ) ) { self::$vendor_stats['not_shipped']++; }
            else { self::$vendor_stats['mismatch']++; }
            return false;
        } catch ( \Throwable $e ) {
            self::$vendor_stats['error']++;
            return false;
        }
    }

    /** The version a plugin declares, from its own main file header. '' when unknown. */
    private static function plugin_version( $slug ) {
        try {
            $dir = ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/' . $slug;
            if ( ! is_dir( $dir ) ) { return ''; }
            $files = glob( $dir . '/*.php' );
            if ( ! is_array( $files ) ) { return ''; }
            // the main file is usually <slug>.php; try it first, then any file with a Version header
            usort( $files, function ( $a, $b ) use ( $slug ) {
                $pa = ( basename( $a ) === $slug . '.php' ) ? 0 : 1;
                $pb = ( basename( $b ) === $slug . '.php' ) ? 0 : 1;
                return $pa === $pb ? strcmp( $a, $b ) : $pa - $pb;
            } );
            foreach ( array_slice( $files, 0, 12 ) as $f ) {
                $head = @file_get_contents( $f, false, null, 0, 8192 );
                if ( false === $head ) { continue; }
                if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $head, $m ) ) {
                    return trim( $m[1] );
                }
            }
            return '';
        } catch ( \Throwable $e ) {
            return '';
        }
    }
}
