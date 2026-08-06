<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bot Shield.
 *
 * Origin-protection layer for bot floods that page caches and Defender both
 * miss: valid-URL request storms (often with unique query strings that bypass
 * the WP Engine edge cache) which force a full page render per hit until the
 * pod's PHP workers are exhausted and the edge starts returning 502s.
 * Built after the sportsatthebeach.com incidents of 2026-07-27 and 2026-08-03.
 *
 * Three mechanisms, all evaluated at plugins_loaded, before WP runs the main
 * query or Beaver Builder renders anything, so a rejected request costs ~1ms
 * instead of ~1s:
 *
 *  1. Per-IP rate limit: over N frontend GETs per minute puts the IP in a
 *     penalty box that answers 429 for a configurable number of minutes.
 *  2. Global circuit breaker: a shared counter of all frontend PHP hits per
 *     10s window. When it trips, "under attack" mode starts: new visitors
 *     without our cookie get a tiny instant JS cookie-check page (503) and
 *     bots that never execute JS never reach a full render again. Verified
 *     browsers, logged-in users, and known search engines pass through.
 *  3. User-agent blocklist: cheap substring match against self-identified
 *     aggressive crawlers, answered with 403.
 *
 * Counters live in the persistent object cache (memcached on WP Engine),
 * with an APCu fallback. Without either backend the counters would need DB
 * writes per request, which is itself load under a flood, so on such hosts
 * the shield degrades to UA blocking only.
 *
 * Exemptions, checked first: WP-CLI, cron, admin, AJAX, REST, wp-login,
 * logged-in users (cookie presence), private/loopback IPs (WP Engine
 * internals), the IP allowlist, and non-GET/HEAD methods.
 *
 * Kill switches: the settings toggle, the DS_BOT_SHIELD_DISABLE constant,
 * and the ds_bot_shield_enabled filter.
 */
class DS_Bot_Shield {

    const COOKIE       = 'ds_bs_ok';
    const CACHE_GROUP  = 'ds_bot_shield';
    const EVENTS_OPT   = 'ds_bot_shield_events';
    const IPLOG_OPT    = 'ds_bot_shield_iplog';
    const TOTALS_OPT   = 'ds_bot_shield_totals';

    /** UA substrings that always pass the challenge (still rate-limited per IP). */
    const GOOD_BOTS = 'googlebot,bingbot,slurp,duckduckbot,applebot,yandexbot,facebookexternalhit,twitterbot,linkedinbot,pinterest,slackbot,whatsapp,uptimerobot,statuscake,pingdom';

    /** Paths that must stay reachable and are never challenged or limited. */
    const EXEMPT_PATHS = 'robots.txt,favicon.ico,sitemap,.well-known/,wp-cron.php';

    private $settings;
    private $backend = null; // 'object-cache' | 'apcu' | null (degraded)
    private $rolled  = false; // stats folded into the durable total this request

    public function __construct( $settings = array() ) {
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function init() {
        if ( defined( 'DS_BOT_SHIELD_DISABLE' ) && DS_BOT_SHIELD_DISABLE ) {
            return;
        }
        if ( ! apply_filters( 'ds_bot_shield_enabled', true ) ) {
            return;
        }
        if ( is_admin() ) {
            add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
        }
        add_action( 'rest_api_init', array( $this, 'register_rest' ) );
        $this->intercept();
    }

    /**
     * Read-only stats endpoint so an external ops dashboard can poll a site:
     *   GET /wp-json/ds-toolkit/v1/bot-shield
     * Returns the same numbers the dashboard widget shows. Public but
     * cheap (cache reads + one option), CORS-limited to the dashboard
     * origin, and off unless bot_shield_stats_api is enabled.
     */
    public function register_rest() {
        if ( empty( $this->settings['bot_shield_stats_api'] ) ) {
            return;
        }
        register_rest_route( 'ds-toolkit/v1', '/bot-shield', array(
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => array( $this, 'rest_stats' ),
        ) );
    }

    public function rest_stats() {
        $origin = (string) $this->opt( 'bot_shield_stats_origin', 'https://dscommand.wpenginepowered.com' );
        if ( '' !== $origin ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Vary: Origin' );
        }
        // Never let the host edge-cache this. The CORS variant is a separate
        // cache entry keyed by Origin, so a cached copy kept serving browsers
        // a stale payload (missing new fields) long after a deploy, while
        // curl — sending no Origin — saw the fresh one.
        nocache_headers();
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        $this->flush_ip_log();
        $this->roll_up_stats( gmdate( 'Ymd' ) );

        $shape = function ( $rows, $limit ) {
            $out = array();
            foreach ( array_slice( $rows, 0, $limit, true ) as $row ) {
                $out[] = array(
                    'ip'      => $row['ip'],
                    'hits'    => (int) $row['n'],
                    'verdict' => $row['verdict'],
                    'ua'      => $row['ua'],
                    'path'    => $row['path'],
                );
            }
            return $out;
        };
        $offenders       = $shape( $this->ip_log(), 10 );
        $offenders_total = $shape( $this->ip_log_total(), 25 );
        $counts = array();
        $totals = array();
        foreach ( array( 'rate-limit', 'ua-block', 'challenge', 'page-trap' ) as $k ) {
            $counts[ $k ] = (int) $this->stat( $k );
            $totals[ $k ] = (int) $this->stat_total( $k );
        }

        return rest_ensure_response( array(
            'site'          => wp_parse_url( home_url(), PHP_URL_HOST ),
            'mode'          => $this->is_monitor_mode() ? 'monitor' : 'block',
            // today (resets at 00:00 UTC)
            'seen'          => (int) $this->stat( 'seen' ),
            'flagged'       => array_sum( $counts ),
            'counts'        => $counts,
            // running totals since logging began — never reset
            'seen_total'    => (int) $this->stat_total( 'seen' ),
            'flagged_total' => array_sum( $totals ),
            'totals'        => $totals,
            'since'         => $this->stat_since(),
            'offenders'       => $offenders,
            'offenders_total' => $offenders_total,
            'utc'           => gmdate( 'c' ),
        ) );
    }

    /**
     * Monitor mode counts what WOULD be blocked but blocks nothing. It is the
     * shipping default per the Site Stability Push rollout: watch first, read
     * the numbers, then flip to block per site.
     */
    public function is_monitor_mode() {
        return 'block' !== $this->opt( 'bot_shield_mode', 'monitor' );
    }

    // ------------------------------------------------------------------ core

    /**
     * Decide and, when the verdict is a block, answer and exit.
     * Runs at plugins_loaded on every frontend request.
     */
    private function intercept() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
        $ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip  = $this->client_ip();

        $verdict = $this->evaluate(
            isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            $uri,
            $ua,
            $ip,
            $_COOKIE
        );

        if ( 'pass' !== $verdict ) {
            $this->log_ip( $verdict, $ip, $ua, $uri );
        }

        if ( 'pass' !== $verdict && $this->is_monitor_mode() ) {
            // Count it, let it through. Counters and penalty/attack state
            // still advance so the numbers show real would-block behavior.
            $this->bump_stat( $verdict );
            return;
        }

        switch ( $verdict ) {
            case 'page-trap':
                $this->bump_stat( 'page-trap' );
                $this->respond( 410, 'Gone.', array( 'X-DS-Bot-Shield: page-trap' ) );
                break;

            case 'ua-block':
                $this->bump_stat( 'ua-block' );
                $this->respond( 403, 'Access denied.', array( 'X-DS-Bot-Shield: ua-block' ) );
                break; // unreachable, respond() exits

            case 'rate-limit':
                $this->bump_stat( 'rate-limit' );
                $this->respond( 429, 'Too many requests. Please slow down and try again in a minute.', array(
                    'X-DS-Bot-Shield: rate-limit',
                    'Retry-After: 60',
                ) );
                break;

            case 'challenge':
                $this->bump_stat( 'challenge' );
                $this->send_challenge();
                break;
        }
        // 'pass' falls through to normal WordPress.
    }

    /**
     * Pure decision logic, no side effects other than counter increments.
     * Returns 'pass' | 'ua-block' | 'rate-limit' | 'challenge'.
     */
    public function evaluate( $method, $uri, $ua, $ip, $cookies ) {
        // Context exemptions.
        if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || is_admin() ) {
            return 'pass';
        }
        if ( 'GET' !== $method && 'HEAD' !== $method ) {
            return 'pass';
        }

        $path = strtolower( (string) parse_url( $uri, PHP_URL_PATH ) );
        foreach ( explode( ',', self::EXEMPT_PATHS ) as $exempt ) {
            if ( '' !== $exempt && false !== strpos( $path, $exempt ) ) {
                return 'pass';
            }
        }
        // WP endpoints with their own protections / legit machine traffic.
        if ( preg_match( '#/(wp-login\.php|wp-json/|admin-ajax\.php|xmlrpc\.php)#', $path ) ) {
            return 'pass';
        }

        // Logged-in users (editors, partners, BB sessions) are never touched.
        foreach ( (array) $cookies as $name => $value ) {
            if ( 0 === strpos( (string) $name, 'wordpress_logged_in_' ) ) {
                return 'pass';
            }
        }

        // Private / loopback source = WP Engine internal traffic, local dev.
        if ( '' === $ip || $this->is_private_ip( $ip ) || $this->is_allowlisted( $ip ) ) {
            return 'pass';
        }

        // Heartbeat: every request that reaches policing distance counts, so
        // an all-zero block report is distinguishable from a dead feature
        // (zeros with a live "evaluated" number = genuinely quiet day).
        $this->bump_stat( 'seen' );

        // Pagination URL trap (Site Stability Push cause #1): WordPress
        // accepts /page/N/ on anything and runs a full query for pages that
        // don't exist; crawlers follow these forever. No fleet site has
        // anywhere near the cap's worth of real pages, so above it is bot
        // traffic by construction and answers 410 Gone.
        $page_cap = max( 5, (int) $this->opt( 'bot_shield_page_cap', 20 ) );
        if ( preg_match( '#/page/(\d+)(?:/|$)#', $path, $m ) && (int) $m[1] > $page_cap ) {
            return 'page-trap';
        }

        // UA blocklist, cheapest check that can block.
        if ( '' !== $ua && $this->ua_matches( $ua, $this->ua_blocklist() ) ) {
            return 'ua-block';
        }

        // Counters need a memory backend; without one degrade to UA-only.
        if ( null === $this->resolve_backend() ) {
            return 'pass';
        }

        $ip_key = md5( $ip );

        // Penalty box first: already-flagged IPs answer instantly.
        if ( $this->cache_get( 'pen_' . $ip_key ) ) {
            return 'rate-limit';
        }

        // 1) Per-IP fixed window (per minute).
        $limit = max( 30, (int) $this->opt( 'bot_shield_ip_limit', 180 ) );
        $count = $this->cache_incr( 'ip_' . $ip_key . '_' . (int) floor( time() / 60 ), 120 );
        if ( $count > $limit ) {
            $this->cache_set( 'pen_' . $ip_key, 1, max( 1, (int) $this->opt( 'bot_shield_penalty_mins', 10 ) ) * 60 );
            $this->log_event( 'ip-penalty', $ip );
            return 'rate-limit';
        }

        // 2) Global circuit breaker (all frontend PHP hits, 10s window).
        $global_limit = max( 50, (int) $this->opt( 'bot_shield_global_limit', 150 ) );
        $global       = $this->cache_incr( 'glob_' . (int) floor( time() / 10 ), 30 );
        $attack_ttl   = max( 1, (int) $this->opt( 'bot_shield_attack_mins', 10 ) ) * 60;

        if ( $global > $global_limit && ! $this->cache_get( 'attack' ) ) {
            $this->cache_set( 'attack', time(), $attack_ttl );
            $this->log_event( 'under-attack', 'global rate ' . $global . '/10s' );
        }

        if ( $this->challenge_enabled() && $this->cache_get( 'attack' ) ) {
            // HEAD can't run the JS check; let it through, it is cheap anyway.
            if ( 'HEAD' === $method ) {
                return 'pass';
            }
            if ( $this->ua_matches( $ua, explode( ',', self::GOOD_BOTS ) ) ) {
                return 'pass';
            }
            if ( $this->cookie_valid( $cookies ) ) {
                return 'pass';
            }
            return 'challenge';
        }

        return 'pass';
    }

    // ------------------------------------------------------------- responses

    private function respond( $code, $body, $extra_headers = array() ) {
        if ( ! headers_sent() ) {
            status_header( $code );
            nocache_headers();
            header( 'Content-Type: text/plain; charset=utf-8' );
            foreach ( $extra_headers as $h ) {
                header( $h );
            }
        }
        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }

    /**
     * The under-attack browser check: ~600 bytes, sets a signed cookie via JS
     * and reloads. Costs ~1ms to serve vs ~1s for a full page render.
     */
    private function send_challenge() {
        $token = $this->cookie_token();
        if ( ! headers_sent() ) {
            status_header( 503 );
            nocache_headers();
            header( 'Content-Type: text/html; charset=utf-8' );
            header( 'X-DS-Bot-Shield: challenge' );
            header( 'Retry-After: 5' );
        }
        // The token never appears whole in the markup: JS reverses one half
        // and concatenates at runtime, so a scraper that regexes the page
        // source (without executing JS) cannot mint the cookie. The reload
        // only fires once the cookie verifiably stuck, so cookie-blocked
        // browsers see a message instead of looping.
        $a      = substr( $token, 0, 10 );
        $b      = strrev( substr( $token, 10 ) );
        $secure = is_ssl() ? ';secure' : '';
        echo '<!doctype html><html><head><meta charset="utf-8"><title>One moment&hellip;</title>'
            . '<meta name="robots" content="noindex">'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;color:#333}div{text-align:center}</style>'
            . '</head><body><div><p>Checking your browser&hellip;</p>'
            . '<p id="ds-bs-msg" style="display:none">Cookies are required to view this site. Please enable them and reload.</p>'
            . '<noscript><p>JavaScript is required. Please enable it and reload.</p></noscript></div>'
            . '<script>(function(){var t="' . esc_js( $a ) . '"+"' . esc_js( $b ) . '".split("").reverse().join("");'
            . 'document.cookie="' . self::COOKIE . '="+t+";path=/;max-age=86400;samesite=Lax' . $secure . '";'
            . 'if(document.cookie.indexOf("' . self::COOKIE . '=")!==-1){location.reload();}'
            . 'else{document.getElementById("ds-bs-msg").style.display="block";}})();</script>'
            . '</body></html>';
        exit;
    }

    // ----------------------------------------------------------- ip & cookie

    /**
     * Real client IP. WP Engine's edge is Cloudflare, so CF-Connecting-IP is
     * set by infrastructure we trust and beats REMOTE_ADDR (which can be the
     * edge proxy). Elsewhere the header is absent and REMOTE_ADDR is used.
     */
    private function client_ip() {
        $candidates = array();
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $candidates[] = $_SERVER['REMOTE_ADDR'];
        }
        foreach ( $candidates as $ip ) {
            $ip = trim( (string) $ip );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
        return '';
    }

    private function is_private_ip( $ip ) {
        return false === filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function is_allowlisted( $ip ) {
        $raw = (string) $this->opt( 'bot_shield_ip_allowlist', '' );
        if ( '' === trim( $raw ) ) {
            return false;
        }
        foreach ( preg_split( '/[\s,]+/', $raw ) as $entry ) {
            $entry = trim( $entry );
            if ( '' === $entry ) {
                continue;
            }
            if ( $entry === $ip ) {
                return true;
            }
            // CIDR (IPv4 only).
            if ( false !== strpos( $entry, '/' ) && false === strpos( $entry, ':' ) && false === strpos( $ip, ':' ) ) {
                list( $subnet, $bits ) = array_pad( explode( '/', $entry, 2 ), 2, '32' );
                $bits = (int) $bits;
                if ( $bits >= 0 && $bits <= 32 && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                    $mask = $bits === 0 ? 0 : ( ~0 << ( 32 - $bits ) );
                    if ( ( ip2long( $ip ) & $mask ) === ( ip2long( $subnet ) & $mask ) ) {
                        return true;
                    }
                }
            }
            // Prefix form: "203.0.113." matches the whole /24.
            if ( '.' === substr( $entry, -1 ) && 0 === strpos( $ip, $entry ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Deliberately NOT bound to the client IP: dual-stack visitors (IPv4 +
     * IPv6 happy eyeballs), mobile networks, and CGNAT rotate the source IP
     * between requests, and an IP-bound cookie then fails validation and
     * loops the challenge (observed live on dslaunchpad5, 2026-08-04). Bound
     * to the site salt and the day instead; the challenge's job is filtering
     * clients that never execute JS, not authenticating an address.
     */
    private function cookie_token( $day_offset = 0 ) {
        return substr( hash_hmac( 'sha256', 'ds-bs|' . gmdate( 'Ymd', time() - $day_offset * DAY_IN_SECONDS ), wp_salt( 'auth' ) ), 0, 20 );
    }

    private function cookie_valid( $cookies ) {
        if ( empty( $cookies[ self::COOKIE ] ) ) {
            return false;
        }
        $sent = (string) $cookies[ self::COOKIE ];
        // Accept today's and yesterday's token so midnight rollover never
        // challenges an active visitor mid-session.
        return hash_equals( $this->cookie_token(), $sent ) || hash_equals( $this->cookie_token( 1 ), $sent );
    }

    // ------------------------------------------------------------------- ua

    private function ua_blocklist() {
        $raw = (string) $this->opt( 'bot_shield_ua_blocklist', '' );
        return preg_split( '/[\r\n,]+/', strtolower( $raw ), -1, PREG_SPLIT_NO_EMPTY );
    }

    private function ua_matches( $ua, $needles ) {
        $ua = strtolower( $ua );
        foreach ( (array) $needles as $needle ) {
            $needle = trim( (string) $needle );
            if ( '' !== $needle && false !== strpos( $ua, $needle ) ) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------- counters

    /**
     * Backend: persistent object cache (memcached on WPE) or APCu. Null when
     * neither exists; callers then skip counting entirely. Overridable in
     * tests via the ds_bot_shield_backend filter.
     */
    private function resolve_backend() {
        if ( null !== $this->backend ) {
            return $this->backend ?: null;
        }
        $backend = null;
        if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
            $backend = 'object-cache';
        } elseif ( function_exists( 'apcu_inc' ) && filter_var( ini_get( 'apc.enabled' ), FILTER_VALIDATE_BOOLEAN ) ) {
            $backend = 'apcu';
        }
        $backend       = apply_filters( 'ds_bot_shield_backend', $backend );
        $this->backend = $backend ?: false; // false caches the "none" answer
        return $backend;
    }

    private function cache_key( $key ) {
        return 'ds_bs_' . $key;
    }

    private function cache_incr( $key, $ttl ) {
        $backend = $this->resolve_backend();
        if ( 'object-cache' === $backend ) {
            wp_cache_add( $this->cache_key( $key ), 0, self::CACHE_GROUP, $ttl );
            $n = wp_cache_incr( $this->cache_key( $key ), 1, self::CACHE_GROUP );
            return false === $n ? 0 : (int) $n;
        }
        if ( 'apcu' === $backend ) {
            apcu_add( $this->cache_key( $key ), 0, $ttl );
            $n = apcu_inc( $this->cache_key( $key ) );
            return false === $n ? 0 : (int) $n;
        }
        if ( 'array' === $backend ) { // test harness backend
            $n = (int) apply_filters( 'ds_bot_shield_test_incr', 0, $this->cache_key( $key ), $ttl );
            return $n;
        }
        return 0;
    }

    private function cache_get( $key ) {
        $backend = $this->resolve_backend();
        if ( 'object-cache' === $backend ) {
            return wp_cache_get( $this->cache_key( $key ), self::CACHE_GROUP );
        }
        if ( 'apcu' === $backend ) {
            $ok = false;
            $v  = apcu_fetch( $this->cache_key( $key ), $ok );
            return $ok ? $v : false;
        }
        if ( 'array' === $backend ) {
            return apply_filters( 'ds_bot_shield_test_get', false, $this->cache_key( $key ) );
        }
        return false;
    }

    private function cache_set( $key, $value, $ttl ) {
        $backend = $this->resolve_backend();
        if ( 'object-cache' === $backend ) {
            wp_cache_set( $this->cache_key( $key ), $value, self::CACHE_GROUP, $ttl );
        } elseif ( 'apcu' === $backend ) {
            apcu_store( $this->cache_key( $key ), $value, $ttl );
        } elseif ( 'array' === $backend ) {
            apply_filters( 'ds_bot_shield_test_set', null, $this->cache_key( $key ), $value, $ttl );
        }
    }

    // ----------------------------------------------------------- ip logging

    /**
     * Per-IP offender log, flood-safe by construction: hit counts and one
     * sample (UA + path + verdict) per IP live in the object cache; an
     * aggregated top-200 table is persisted to the DB at most once every
     * 2 minutes and kept for today + yesterday. Memcached cannot enumerate
     * keys, so a capped per-day index array makes the entries findable.
     */
    private function log_ip( $verdict, $ip, $ua, $path ) {
        if ( '' === $ip || null === $this->resolve_backend() ) {
            return;
        }
        $day = gmdate( 'Ymd' );
        $k   = substr( md5( $ip ), 0, 12 );

        $this->cache_incr( 'ipn_' . $k . '_' . $day, 2 * DAY_IN_SECONDS );

        if ( false === $this->cache_get( 'ipi_' . $k . '_' . $day ) ) {
            $this->cache_set( 'ipi_' . $k . '_' . $day, array(
                'ip'      => $ip,
                'verdict' => $verdict,
                'ua'      => substr( (string) $ua, 0, 120 ),
                'path'    => substr( (string) $path, 0, 120 ),
            ), 2 * DAY_IN_SECONDS );

            $idx = $this->cache_get( 'ipidx_' . $day );
            $idx = is_array( $idx ) ? $idx : array();
            if ( count( $idx ) < 500 && ! in_array( $k, $idx, true ) ) {
                $idx[] = $k;
                $this->cache_set( 'ipidx_' . $day, $idx, 2 * DAY_IN_SECONDS );
            }
        }

        if ( ! $this->cache_get( 'ipflush' ) ) {
            $this->cache_set( 'ipflush', 1, 60 );
            $this->flush_ip_log( $day );
            $this->roll_up_stats( $day );
        }
    }

    /** Merge the cache aggregate into the persisted option. */
    public function flush_ip_log( $day = null ) {
        $day = $day ?: gmdate( 'Ymd' );
        $idx = $this->cache_get( 'ipidx_' . $day );
        if ( ! is_array( $idx ) || ! $idx ) {
            return;
        }
        $rows = array();
        foreach ( $idx as $k ) {
            $info = $this->cache_get( 'ipi_' . $k . '_' . $day );
            $n    = (int) $this->cache_get( 'ipn_' . $k . '_' . $day );
            if ( is_array( $info ) && $n > 0 ) {
                $rows[ $info['ip'] ] = array_merge( $info, array( 'n' => $n ) );
            }
        }
        if ( ! $rows ) {
            return;
        }
        uasort( $rows, function ( $a, $b ) { return $b['n'] - $a['n']; } );
        $rows = array_slice( $rows, 0, 200, true );

        $log = get_option( self::IPLOG_OPT, array() );
        $log = is_array( $log ) ? $log : array();

        // Migrate the older shape (day keys at the top level) into 'days'.
        if ( ! isset( $log['days'] ) ) {
            $days = array();
            foreach ( $log as $dk => $dv ) {
                if ( is_array( $dv ) ) {
                    $days[ (string) $dk ] = $dv;
                }
            }
            $log = array( 'days' => $days, 'all' => array(), 'seen' => array() );
        }
        foreach ( array( 'days', 'all', 'seen' ) as $bucket ) {
            if ( ! isset( $log[ $bucket ] ) || ! is_array( $log[ $bucket ] ) ) {
                $log[ $bucket ] = array();
            }
        }

        // Fold today's snapshot into a running per-IP total. The cache counter
        // is daily and can be wiped, so track what was last folded and add the
        // delta — same reset-aware rule the stat counters use. Without this the
        // crawler table would restart every midnight.
        foreach ( $rows as $ip => $row ) {
            $n    = (int) $row['n'];
            $last = isset( $log['seen'][ $day ][ $ip ] ) ? (int) $log['seen'][ $day ][ $ip ] : 0;
            $delta = ( $n >= $last ) ? ( $n - $last ) : $n;
            if ( $delta > 0 || ! isset( $log['all'][ $ip ] ) ) {
                $cur = isset( $log['all'][ $ip ]['n'] ) ? (int) $log['all'][ $ip ]['n'] : 0;
                $log['all'][ $ip ] = array(
                    'ip'      => $row['ip'],
                    'verdict' => $row['verdict'],
                    'ua'      => $row['ua'],
                    'path'    => $row['path'],
                    'n'       => $cur + $delta,
                );
            }
            $log['seen'][ $day ][ $ip ] = $n;
        }

        $log['days'][ $day ] = $rows;

        // Daily snapshots and their fold-markers are kept for 30 days; the
        // running 'all' totals are never pruned by date, only capped by size.
        $cutoff = gmdate( 'Ymd', time() - ( 30 * DAY_IN_SECONDS ) );
        foreach ( array( 'days', 'seen' ) as $bucket ) {
            foreach ( array_keys( $log[ $bucket ] ) as $d ) {
                // PHP casts numeric-string keys to int — compare as strings.
                if ( (string) $d < (string) $cutoff ) {
                    unset( $log[ $bucket ][ $d ] );
                }
            }
        }
        if ( count( $log['all'] ) > 300 ) {
            uasort( $log['all'], function ( $a, $b ) { return $b['n'] - $a['n']; } );
            $log['all'] = array_slice( $log['all'], 0, 300, true );
        }

        update_option( self::IPLOG_OPT, $log, false );
    }

    /** Running per-IP offender totals since logging began. */
    public function ip_log_total() {
        $log = get_option( self::IPLOG_OPT, array() );
        if ( ! is_array( $log ) || ! isset( $log['all'] ) || ! is_array( $log['all'] ) ) {
            return array();
        }
        $all = $log['all'];
        uasort( $all, function ( $a, $b ) { return $b['n'] - $a['n']; } );
        return $all;
    }

    /** Persisted offender rows for a day, sorted by hit count. */
    public function ip_log( $day = null ) {
        $day = $day ?: gmdate( 'Ymd' );
        $log = get_option( self::IPLOG_OPT, array() );
        if ( ! is_array( $log ) ) {
            return array();
        }
        if ( isset( $log['days'] ) ) {
            return isset( $log['days'][ $day ] ) ? $log['days'][ $day ] : array();
        }
        return isset( $log[ $day ] ) ? $log[ $day ] : array(); // pre-migration
    }

    // ------------------------------------------------------- stats & logging

    /** Counters are incremented in the object cache — cheap enough for a flood. */
    private function bump_stat( $which ) {
        $this->cache_incr( 'stat_' . $which . '_' . gmdate( 'Ymd' ), 2 * DAY_IN_SECONDS );
    }

    /**
     * A day's total for a counter.
     *
     * The live counter lives in the object cache, which is VOLATILE: a WP Engine
     * purge, a plugin update, a pod restart, or memcached evicting the key under
     * memory pressure all reset it to zero. Reporting the raw cache value made
     * the dashboard appear to count DOWN (observed on icelinerinks: 287 -> 189).
     *
     * So the cache is treated as a fast tick counter that may reset at any time,
     * and the durable total lives in an option updated by roll_up_stats() on the
     * same throttled 2-minute flush as the IP log. The answer is the persisted
     * total plus whatever has ticked since the last roll-up.
     */
    public function stat( $which ) {
        $day = gmdate( 'Ymd' );

        // Fold anything pending into the durable total before reporting, once
        // per request. Without this a cache wipe between two reads would drop
        // the figure. Only admin/REST read stats, never front-end traffic, so
        // this costs at most one option write per dashboard poll.
        if ( ! $this->rolled ) {
            $this->rolled = true;
            $this->roll_up_stats( $day );
        }

        $totals = $this->read_totals();

        $stored = isset( $totals['days'][ $day ]['totals'][ $which ] ) ? (int) $totals['days'][ $day ]['totals'][ $which ] : 0;
        $last   = isset( $totals['days'][ $day ]['last'][ $which ] )   ? (int) $totals['days'][ $day ]['last'][ $which ]   : 0;
        $hw     = isset( $totals['days'][ $day ]['hw'][ $which ] )     ? (int) $totals['days'][ $day ]['hw'][ $which ]     : 0;
        $live   = (int) $this->cache_get( 'stat_' . $which . '_' . $day );

        // $live < $last means the cache was wiped and has restarted from zero;
        // everything currently in it is new since the roll-up.
        $pending = ( $live >= $last ) ? ( $live - $last ) : $live;

        // A wipe loses whatever had ticked since the last roll-up, so the
        // computed figure can dip. The high-water mark keeps the reported
        // number monotonic — it may under-count, but it never counts DOWN.
        return max( $stored + $pending, $hw );
    }

    /**
     * Fold the live cache counters into the durable per-day totals.
     * Called from the same throttled flush as the IP log, so at most one extra
     * option write every 2 minutes no matter how much traffic arrives.
     */
    private function roll_up_stats( $day ) {
        $totals = $this->read_totals();
        if ( ! isset( $totals['days'][ $day ] ) || ! is_array( $totals['days'][ $day ] ) ) {
            $totals['days'][ $day ] = array( 'totals' => array(), 'last' => array(), 'hw' => array() );
        }

        $changed = false;
        foreach ( array( 'seen', 'rate-limit', 'ua-block', 'challenge', 'page-trap' ) as $k ) {
            $live = (int) $this->cache_get( 'stat_' . $k . '_' . $day );
            $last = isset( $totals['days'][ $day ]['last'][ $k ] ) ? (int) $totals['days'][ $day ]['last'][ $k ] : 0;
            if ( 0 === $live && 0 === $last ) {
                continue;
            }
            $delta = ( $live >= $last ) ? ( $live - $last ) : $live; // reset-aware
            if ( $delta > 0 || $live !== $last ) {
                $cur   = isset( $totals['days'][ $day ]['totals'][ $k ] ) ? (int) $totals['days'][ $day ]['totals'][ $k ] : 0;
                $hw    = isset( $totals['days'][ $day ]['hw'][ $k ] )     ? (int) $totals['days'][ $day ]['hw'][ $k ]     : 0;
                $total = $cur + $delta;
                $totals['days'][ $day ]['totals'][ $k ] = $total;
                $totals['days'][ $day ]['last'][ $k ]   = $live;
                $totals['days'][ $day ]['hw'][ $k ]     = max( $hw, $total );
                // The running total never rolls over at midnight — this is what
                // the report shows, so "Checked" is cumulative, not today-only.
                $all = isset( $totals['all'][ $k ] ) ? (int) $totals['all'][ $k ] : 0;
                $totals['all'][ $k ] = $all + $delta;
                $changed = true;
            }
        }

        // Daily buckets are only kept for the trend chart; 30 days of five
        // small ints is a trivial option. The all-time figures are never pruned.
        $cutoff = gmdate( 'Ymd', time() - ( 30 * DAY_IN_SECONDS ) );
        foreach ( array_keys( $totals['days'] ) as $d ) {
            // PHP casts numeric string keys to int, so compare as strings.
            if ( (string) $d < (string) $cutoff ) {
                unset( $totals['days'][ $d ] );
                $changed = true;
            }
        }

        if ( $changed ) {
            update_option( self::TOTALS_OPT, $totals, false );
        }
    }

    /**
     * Load the totals option, migrating the older shape (day keys at the top
     * level, no running total) so existing installs keep the numbers they
     * already collected instead of restarting from zero.
     */
    private function read_totals() {
        $totals = get_option( self::TOTALS_OPT, array() );
        $totals = is_array( $totals ) ? $totals : array();

        if ( ! isset( $totals['days'] ) ) {
            $days = array();
            foreach ( $totals as $k => $v ) {
                if ( is_array( $v ) && isset( $v['totals'] ) ) {
                    $days[ (string) $k ] = $v;
                }
            }
            $all = array();
            foreach ( $days as $bucket ) {
                foreach ( (array) $bucket['totals'] as $key => $n ) {
                    $all[ $key ] = ( isset( $all[ $key ] ) ? $all[ $key ] : 0 ) + (int) $n;
                }
            }
            $totals = array(
                'days'  => $days,
                'all'   => $all,
                'since' => gmdate( 'Y-m-d' ),
            );
        }
        if ( ! isset( $totals['all'] ) || ! is_array( $totals['all'] ) ) {
            $totals['all'] = array();
        }
        if ( empty( $totals['since'] ) ) {
            $totals['since'] = gmdate( 'Y-m-d' );
        }
        return $totals;
    }

    /** Running total since logging began — does not reset at midnight. */
    public function stat_total( $which ) {
        $totals = $this->read_totals();
        $all    = isset( $totals['all'][ $which ] ) ? (int) $totals['all'][ $which ] : 0;
        // Add whatever today's cache holds but has not been rolled up yet.
        $day     = gmdate( 'Ymd' );
        $last    = isset( $totals['days'][ $day ]['last'][ $which ] ) ? (int) $totals['days'][ $day ]['last'][ $which ] : 0;
        $live    = (int) $this->cache_get( 'stat_' . $which . '_' . $day );
        $pending = ( $live >= $last ) ? ( $live - $last ) : $live;
        return $all + $pending;
    }

    /** Date logging started, for the "since" label on the report. */
    public function stat_since() {
        $t = $this->read_totals();
        return $t['since'];
    }

    /**
     * Persist notable transitions (penalty, under-attack) to an option so
     * there's evidence after cache expiry. Writes are rare by construction:
     * penalties once per IP per box, attack mode once per activation.
     */
    private function log_event( $kind, $detail ) {
        // At most one DB write per 60s: under a distributed flood thousands
        // of penalties can fire and option writes must not become the load.
        if ( $this->cache_get( 'evlock' ) ) {
            return;
        }
        $this->cache_set( 'evlock', 1, 60 );
        $events   = get_option( self::EVENTS_OPT, array() );
        $events   = is_array( $events ) ? $events : array();
        $events[] = array( 't' => time(), 'kind' => $kind, 'detail' => $detail );
        update_option( self::EVENTS_OPT, array_slice( $events, -30 ), false );
    }

    // ----------------------------------------------------- dashboard widget

    /**
     * The rollout confirmation box from the Site Stability Push brief: the
     * dashboard shows the mode and today's numbers, so "the box is there"
     * doubles as proof the shield is active on a site.
     */
    public function register_dashboard_widget() {
        wp_add_dashboard_widget( 'ds_bot_shield_status', 'Bot Shield', array( $this, 'render_dashboard_widget' ) );
    }

    public function render_dashboard_widget() {
        $monitor = $this->is_monitor_mode();
        $rows    = array(
            'rate-limit' => 'Rate-limited (429)',
            'ua-block'   => 'Blocked crawlers (403)',
            'challenge'  => 'Browser checks (503)',
            'page-trap'  => 'Pagination traps (410)',
        );
        echo '<p><strong>' . ( $monitor
            ? 'Monitor mode — nothing is being blocked yet.'
            : 'Blocking mode — abusive traffic is being rejected.' ) . '</strong></p>';
        echo '<p>Requests evaluated today: <strong>' . (int) $this->stat( 'seen' ) . '</strong></p>';
        echo '<p>' . ( $monitor ? 'Would have been blocked today:' : 'Blocked today:' ) . '</p><ul style="margin-left:1em;list-style:disc;">';
        foreach ( $rows as $key => $label ) {
            echo '<li>' . esc_html( $label ) . ': <strong>' . (int) $this->stat( $key ) . '</strong></li>';
        }
        echo '</ul>';
        $events = get_option( self::EVENTS_OPT, array() );
        if ( is_array( $events ) && $events ) {
            echo '<p style="margin-top:8px;"><strong>Recent events</strong></p><ul style="margin-left:1em;list-style:disc;">';
            foreach ( array_slice( array_reverse( $events ), 0, 5 ) as $e ) {
                echo '<li>' . esc_html( gmdate( 'M j H:i', (int) $e['t'] ) . ' UTC — ' . $e['kind'] . ' (' . $e['detail'] . ')' ) . '</li>';
            }
            echo '</ul>';
        }
        $this->flush_ip_log();
        $offenders = $this->ip_log();
        if ( $offenders ) {
            echo '<p style="margin-top:8px;"><strong>Top offending IPs today</strong></p><ul style="margin-left:1em;list-style:disc;">';
            foreach ( array_slice( $offenders, 0, 10, true ) as $row ) {
                echo '<li><code>' . esc_html( $row['ip'] ) . '</code> — ' . (int) $row['n'] . '× '
                    . esc_html( $row['verdict'] ) . ', <span title="' . esc_attr( $row['ua'] ) . '">'
                    . esc_html( substr( $row['ua'], 0, 40 ) ) . '…</span> on <code>'
                    . esc_html( substr( $row['path'], 0, 40 ) ) . '</code></li>';
            }
            echo '</ul>';
        }
    }

    // --------------------------------------------------------------- helpers

    private function opt( $key, $default ) {
        return isset( $this->settings[ $key ] ) && '' !== $this->settings[ $key ]
            ? $this->settings[ $key ]
            : $default;
    }

    private function challenge_enabled() {
        return ! empty( $this->settings['bot_shield_challenge_enabled'] );
    }
}
