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

    /** UA substrings that always pass the challenge (still rate-limited per IP). */
    const GOOD_BOTS = 'googlebot,bingbot,slurp,duckduckbot,applebot,yandexbot,facebookexternalhit,twitterbot,linkedinbot,pinterest,slackbot,whatsapp,uptimerobot,statuscake,pingdom';

    /** Paths that must stay reachable and are never challenged or limited. */
    const EXEMPT_PATHS = 'robots.txt,favicon.ico,sitemap,.well-known/,wp-cron.php';

    private $settings;
    private $backend = null; // 'object-cache' | 'apcu' | null (degraded)

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
        $this->intercept();
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
        $verdict = $this->evaluate(
            isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/',
            isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
            $this->client_ip(),
            $_COOKIE
        );

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

    // ------------------------------------------------------- stats & logging

    /** Daily counters surfaced on the settings page. Cache-only, cheap. */
    private function bump_stat( $which ) {
        $this->cache_incr( 'stat_' . $which . '_' . gmdate( 'Ymd' ), 2 * DAY_IN_SECONDS );
    }

    public function stat( $which ) {
        return (int) $this->cache_get( 'stat_' . $which . '_' . gmdate( 'Ymd' ) );
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
