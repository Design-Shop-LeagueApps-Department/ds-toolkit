<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tripwire: the fleet allow and deny lists fetched from the repository, their signature check and the deny list.
 *
 * One part of DS_Tripwire, which was a single 1,960-line class until the 2026-11 audit. The methods were
 * moved here as they were: a trait, so they are still methods of the feature (same visibility, same $this).
 * Every detector, the constants, the two scans and the sending of an alert stay in class-ds-tripwire.php, which
 * is also the one file Tripwire exempts from its own content scan: nothing that holds a detection pattern may
 * move out of it.
 */
trait DS_Tripwire_Lists {

    private static $remote_status = array();

    /** md5 => true for files this site has already verified against a vendor manifest. */
    private static $vendor_ok = null;
    /** slug|version => true for manifests already fetched (or failed) this run, to fetch each once. */
    private static $vendor_tried = array();
    private static $vendor_fetches = 0;
    private static $vendor_stats = array( 'verified' => 0, 'no_manifest' => 0, 'not_shipped' => 0, 'mismatch' => 0, 'error' => 0, 'budget' => 0, 'canonical' => 0 );

    /**
     * Parse an md5 list body. PURE: no WordPress, no I/O, so the unit test can feed it garbage.
     * A line counts only if its first 32 characters are hex and the 33rd is end-of-line or
     * whitespace. Everything else is ignored: comments, an HTML error page, a rate-limit notice,
     * a truncated line, a 31-character typo. For a named list the rest of the line is the label.
     * Returns [md5 => true] or, when $named, [md5 => label].
     */
    private static function parse_md5_list( $body, $named = false ) {
        $out = array();
        if ( ! is_string( $body ) || '' === $body ) {
            return $out;
        }
        foreach ( preg_split( '/\r\n|\r|\n/', $body ) as $line ) {
            $line = trim( $line );
            if ( '' === $line || '#' === $line[0] ) {
                continue;
            }
            $md5  = strtolower( substr( $line, 0, 32 ) );
            $tail = trim( (string) substr( $line, 32 ) );
            if ( 32 !== strlen( $md5 ) || ! ctype_xdigit( $md5 ) ) {
                continue;
            }
            if ( '' !== $tail && ! ctype_space( $line[32] ) ) {
                continue; // 33+ hex chars run together: not a hash, do not guess
            }
            $out[ $md5 ] = $named ? ( '' !== $tail ? $tail : 'listed in the remote deny list' ) : true;
        }
        return $out;
    }

    /**
     * Fetch one remote list, cached for a cron cycle. Built for a fleet of thousands where the worst
     * bug is an email flood, so every failure path is conservative:
     *   - not 200, not text, too large, transport error  -> failure
     *   - a body with ZERO valid hashes                   -> failure (an error page, a rate-limit
     *     notice and a truncated response all parse to zero; none may replace a good list)
     *   - failure                                         -> the last GOOD copy if one exists, else
     *     nothing, cached 15 min so a dead endpoint is retried, not hammered
     *   - anything thrown                                 -> nothing
     * "Nothing" means the bundled list runs alone, which is exactly the behaviour of every earlier
     * release. This function can reduce suppression to what the bundle does; it can never widen a
     * failure into silence, and it never throws.
     */
    private static function remote_list( $which ) {
        $which = ( 'deny' === $which ) ? 'deny' : 'allow';
        $named = ( 'deny' === $which );
        // The ALLOW list silences findings, so since 1.12.0 it must carry a signature by the release key
        // (tripwire-allow.md5.sig, see lists/README.md): without one a commit to main could no longer switch
        // detection off fleet-wide. New cache keys, so a copy an older version cached unsigned is never used.
        // The deny list only names findings the engine already scored, so it stays unsigned.
        $signed = ! $named;
        $key   = ( $signed ? 'ds_tripwire_rl2_' : 'ds_tripwire_rl_' ) . $which;
        $good  = ( $signed ? 'ds_tripwire_rlg2_' : 'ds_tripwire_rlg_' ) . $which;
        $day   = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
        try {
            if ( ! function_exists( 'get_transient' ) || ! function_exists( 'wp_remote_get' ) ) {
                self::$remote_status[ $which ] = array( 'status' => 'unavailable', 'count' => 0 );
                return array();
            }
            $cached = get_transient( $key );
            if ( is_array( $cached ) ) {
                self::$remote_status[ $which ] = array( 'status' => 'cached', 'count' => count( $cached ) );
                return $cached;
            }
            $r    = wp_remote_get( self::REMOTE_LIST_BASE . 'tripwire-' . $which . '.md5', array(
                'timeout'     => self::REMOTE_TIMEOUT,
                'redirection' => 1,
                'sslverify'   => true,
                'headers'     => array( 'Accept' => 'text/plain' ),
                'user-agent'  => 'DS-Tripwire/' . ( defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '?' ),
            ) );
            $fail = '';
            $list = array();
            if ( is_wp_error( $r ) ) {
                $fail = 'error: ' . substr( $r->get_error_message(), 0, 60 );
            } else {
                $code = (int) wp_remote_retrieve_response_code( $r );
                $body = (string) wp_remote_retrieve_body( $r );
                $ct   = (string) wp_remote_retrieve_header( $r, 'content-type' );
                if ( 200 !== $code ) {
                    $fail = 'http ' . $code;
                } elseif ( strlen( $body ) > self::REMOTE_MAX_BYTES ) {
                    $fail = 'too large';
                } elseif ( '' !== $ct && 0 !== strpos( $ct, 'text/' ) ) {
                    $fail = 'not text: ' . substr( $ct, 0, 40 );
                } elseif ( $signed && '' !== ( $sig_fail = self::remote_list_sig_fail( 'tripwire-' . $which, $body ) ) ) {
                    $fail = $sig_fail;
                } else {
                    $list = self::parse_md5_list( $body, $named );
                    if ( 0 === count( $list ) ) {
                        $fail = 'no valid hashes';
                    }
                }
            }
            if ( '' === $fail ) {
                $spread = function_exists( 'home_url' ) ? ( crc32( (string) home_url() ) % self::REMOTE_JITTER ) : 0;
                set_transient( $key, $list, self::REMOTE_TTL + $spread );
                set_transient( $good, $list, 30 * $day );
                self::$remote_status[ $which ] = array( 'status' => 'fetched', 'count' => count( $list ) );
                return $list;
            }
            $last = get_transient( $good );
            $last = is_array( $last ) ? $last : array();
            set_transient( $key, $last, 900 );
            self::$remote_status[ $which ] = array(
                'status' => 'fail (' . $fail . ')' . ( $last ? ', using last good' : ', bundle only' ),
                'count'  => count( $last ),
            );
            return $last;
        } catch ( \Throwable $e ) {
            self::$remote_status[ $which ] = array( 'status' => 'exception: ' . substr( $e->getMessage(), 0, 60 ), 'count' => 0 );
            return array();
        }
    }

    /** Why the fetched list $name is not signed by the release key, or '' when it is. */
    private static function remote_list_sig_fail( $name, $body ) {
        if ( ! class_exists( 'DS_HQ_Update' ) ) {
            require_once DS_TOOLKIT_PATH . 'features/class-ds-hq-update.php';
        }
        $r = wp_remote_get( self::REMOTE_LIST_BASE . $name . '.md5.sig', array(
            'timeout'     => self::REMOTE_TIMEOUT,
            'redirection' => 1,
            'sslverify'   => true,
            'user-agent'  => 'DS-Tripwire/' . ( defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '?' ),
        ) );
        if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
            return 'unsigned (' . ( is_wp_error( $r ) ? 'no signature' : 'signature http ' . (int) wp_remote_retrieve_response_code( $r ) ) . ')';
        }
        return DS_HQ_Update::list_sig_ok( $name, hash( 'sha256', (string) $body ), (string) wp_remote_retrieve_body( $r ) ) ? '' : 'bad signature';
    }

    /**
     * Bundled KNOWN_BAD_MD5 plus the remote deny list, [md5 => name]. A deny entry only NAMES a
     * finding the engine has already scored on behaviour; it never creates one. So a wrong deny hash
     * costs a wrong label on a real finding, and can never manufacture an alert on a clean file.
     */
    private static function known_bad_md5() {
        static $bad = null;
        if ( null !== $bad ) {
            return $bad;
        }
        $bad = self::KNOWN_BAD_MD5;
        foreach ( self::remote_list( 'deny' ) as $md5 => $name ) {
            if ( ! isset( $bad[ $md5 ] ) ) {
                $bad[ $md5 ] = is_string( $name ) ? $name : 'listed in the remote deny list';
            }
        }
        return $bad;
    }
}
