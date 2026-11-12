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
require_once __DIR__ . '/bot-shield/trait-crawlers.php';
require_once __DIR__ . '/bot-shield/trait-store.php';
require_once __DIR__ . '/bot-shield/trait-stats.php';
require_once __DIR__ . '/bot-shield/trait-widget.php';

class DS_Bot_Shield {

    // The supporting jobs live in features/bot-shield/, one trait each. This file keeps the request path: the
    // hooks, the decision, the reply, the visitor's address and the allow list.
    use DS_Bot_Shield_Crawlers,
        DS_Bot_Shield_Store,
        DS_Bot_Shield_Stats,
        DS_Bot_Shield_Widget;

    const COOKIE       = 'ds_bs_ok';
    const CACHE_GROUP  = 'ds_bot_shield';
    const EVENTS_OPT   = 'ds_bot_shield_events';
    const IPLOG_OPT    = 'ds_bot_shield_iplog';
    const TOTALS_OPT   = 'ds_bot_shield_totals';

    /** UA substrings that always pass the challenge (still rate-limited per IP). */
    const GOOD_BOTS = 'googlebot,bingbot,slurp,duckduckbot,applebot,yandexbot,facebookexternalhit,twitterbot,linkedinbot,pinterest,slackbot,whatsapp,uptimerobot,statuscake,pingdom';

    /**
     * Search engines and social link-preview crawlers that must NEVER be
     * refused, so indexing and link cards can't be harmed. Claiming one of
     * these is not enough — verify_crawler() confirms it by reverse DNS
     * before the exemption applies, otherwise the UA is a free bypass.
     * Key = UA substring, value = hostnames the PTR record must end with.
     */
    const SE_VERIFY = array(
        'googlebot'            => array( '.googlebot.com', '.google.com' ),
        'google-inspectiontool' => array( '.googlebot.com', '.google.com' ),
        'adsbot-google'        => array( '.googlebot.com', '.google.com' ),
        'mediapartners-google' => array( '.googlebot.com', '.google.com' ),
        'bingbot'              => array( '.search.msn.com' ),
        'adidxbot'             => array( '.search.msn.com' ),
        'msnbot'               => array( '.search.msn.com' ),
        'duckduckbot'          => array( '.duckduckgo.com' ),
        'yandexbot'            => array( '.yandex.ru', '.yandex.net', '.yandex.com' ),
        'baiduspider'          => array( '.baidu.com', '.baidu.jp' ),
        'applebot'             => array( '.applebot.apple.com', '.apple.com' ),
    );

    /**
     * How many NEW addresses claiming to be a search engine get the DNS check per 10 seconds without being
     * about to be refused. A crawler verified once is remembered for a day, so real crawlers stay far under
     * this; a flood of spoofed crawler requests on rotating addresses used to cost two blocking DNS lookups
     * per request, and is now capped here. Before any refusal the check always runs, whatever the count.
     */
    const SE_VERIFY_BURST = 5;

    /** Paths that must stay reachable and are never challenged or limited. */
    const EXEMPT_PATHS = 'robots.txt,favicon.ico,sitemap,.well-known/,wp-cron.php';

    private $settings;
    private $backend = null; // 'object-cache' | 'apcu' | null (degraded)
    private $rolled  = false; // stats folded into the durable total this request
    private $page_candidate = 0; // deep /page/N/ request awaiting confirmation
    private $se_checked = null;  // memoised verified-search-engine result

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

        // A deep /page/N/ request is only judged once WordPress has resolved
        // the query and we can tell a real archive page from an empty one.
        if ( $this->page_candidate ) {
            add_action( 'template_redirect', array( $this, 'confirm_page_trap' ), 0 );
        }
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

        // Verified search engines are never refused, by anything: every refusal
        // below asks is_verified_se() first, so indexing can't be harmed and
        // spoofers (who fail verification) are policed like anyone else.
        //
        // The DNS check itself is two blocking lookups, so it is rationed:
        //  - an address verified (or failed) earlier today is answered from the
        //    cache, no lookup;
        //  - a new address is checked right away only while fewer than
        //    SE_VERIFY_BURST new ones arrived in this 10 s window, so a real
        //    crawler passes on its first request and stays out of the counters,
        //    while a spoofed-crawler flood on rotating addresses cannot make
        //    every request pay for DNS (it was a worker-exhaustion amplifier);
        //  - without a memory backend nothing caches the answer and the only
        //    verdict left is a UA block, so the check waits for that.
        // confirm_page_trap() verifies on its own.
        $has_backend = ( null !== $this->resolve_backend() );
        if ( $has_backend && null !== $this->se_domains( $ua ) ) {
            $known = $this->cache_get( 'se_' . md5( $ip ) );
            if ( false !== $known ) {
                $this->se_checked = (bool) $known;
            } elseif ( $this->cache_incr( 'sev_' . (int) floor( time() / 10 ), 30 ) <= self::SE_VERIFY_BURST ) {
                $this->is_verified_se( $ua, $ip );
            }
            if ( true === $this->se_checked ) {
                return 'pass';
            }
        }

        // Pagination URL trap (Site Stability Push cause #1): WordPress
        // accepts /page/N/ on anything and renders a full page for numbers
        // that don't exist; crawlers follow these forever.
        //
        // Deciding on the page NUMBER alone is not safe — a site with a deep
        // real archive would have genuine pages refused, which would deindex
        // content. So this only marks the request as a candidate; the verdict
        // is settled later in confirm_page_trap(), once WordPress knows
        // whether that page actually exists.
        $page_cap = max( 5, (int) $this->opt( 'bot_shield_page_cap', 20 ) );
        if ( preg_match( '#/page/(\d+)(?:/|$)#', $path, $m ) && (int) $m[1] > $page_cap ) {
            $this->page_candidate = (int) $m[1];
        }

        // UA blocklist, cheapest check that can block.
        if ( '' !== $ua && $this->ua_matches( $ua, $this->ua_blocklist() ) ) {
            if ( $this->is_verified_se( $ua, $ip ) ) {
                return 'pass';
            }
            return 'ua-block';
        }

        // Counters need a memory backend; without one degrade to UA-only.
        if ( ! $has_backend ) {
            return 'pass';
        }

        $ip_key = md5( $ip );

        // Penalty box first: already-flagged IPs answer instantly.
        if ( $this->cache_get( 'pen_' . $ip_key ) ) {
            return $this->is_verified_se( $ua, $ip ) ? 'pass' : 'rate-limit';
        }

        // 1) Per-IP fixed window (per minute).
        $limit = max( 30, (int) $this->opt( 'bot_shield_ip_limit', 180 ) );
        $count = $this->cache_incr( 'ip_' . $ip_key . '_' . (int) floor( time() / 60 ), 120 );
        if ( $count > $limit ) {
            if ( $this->is_verified_se( $ua, $ip ) ) {
                return 'pass'; // a real crawler that was not checked on arrival (over the burst): never penalised
            }
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
            // The challenge reload carries ?ds_bs=1. Seeing it here means the
            // browser was already asked once, set the cookie, and came back
            // WITHOUT it — i.e. the host strips non-allowlisted cookies, as
            // WP Engine does (proven: HTTP_COOKIE arrives empty). Challenging
            // again would loop the visitor forever, so fail OPEN and stop
            // trying on this site until the flag expires.
            if ( ! empty( $_GET['ds_bs'] ) ) {
                $this->cache_set( 'nochallenge', 1, 6 * 3600 );
                $this->log_event( 'challenge-unsupported', 'host strips cookies; challenge disabled 6h' );
                return 'pass';
            }
            if ( $this->cache_get( 'nochallenge' ) ) {
                return 'pass';
            }
            if ( $this->is_verified_se( $ua, $ip ) ) {
                return 'pass';
            }
            return 'challenge';
        }

        return 'pass';
    }

    /**
     * Decide a deep /page/N/ request now that the main query has run.
     *
     * Two shapes of the trap:
     *  - singular: /some-article/page/50/ where the post has no such page
     *    (WordPress happily renders the whole post again at 200).
     *  - archive:  /news/page/50/ with no posts on it.
     *
     * A page that genuinely exists is left completely alone, so a real deep
     * archive is never refused. This runs before the theme renders, so a
     * confirmed trap still skips the expensive part (the ~142 KB build).
     */
    public function confirm_page_trap() {
        if ( ! $this->page_candidate || is_admin() ) {
            return;
        }
        // Never refuse a verified search engine, even on a page that is empty.
        if ( $this->is_verified_se(
                isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
                $this->client_ip() ) ) {
            return;
        }
        $empty = false;

        if ( is_singular() ) {
            $post = get_queried_object();
            if ( $post instanceof WP_Post ) {
                $numpages = substr_count( $post->post_content, '<!--nextpage-->' ) + 1;
                $empty    = ( $this->page_candidate > $numpages );
            }
        } elseif ( is_404() ) {
            $empty = true;
        } else {
            global $wp_query;
            $empty = ( isset( $wp_query->post_count ) && 0 === (int) $wp_query->post_count );
        }

        if ( ! $empty ) {
            return; // a real page — never touched
        }

        $this->log_ip( 'page-trap', $this->client_ip(),
            isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
            isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' );
        $this->bump_stat( 'page-trap' );

        if ( $this->is_monitor_mode() ) {
            return;
        }
        $this->respond( 410, 'Gone.', array( 'X-DS-Bot-Shield: page-trap' ) );
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
            . 'if(document.cookie.indexOf("' . self::COOKIE . '=")!==-1){'
            . 'var u=location.href.split("#")[0];'
            . 'u+=(u.indexOf("?")===-1?"?":"&")+"ds_bs=1";'
            . 'location.replace(u);}'
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
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
        $remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
        // CF-Connecting-IP is only Cloudflare's word when the request reached PHP from Cloudflare (or from a
        // private proxy in front of it). Anywhere else a visitor can send the header themselves: as a private
        // address to be exempt from everything, rotated to dodge the per-IP limit, or set to someone else's
        // address to get them rate-limited. Measured on WP Engine (2026-10-28): REMOTE_ADDR is already the
        // visitor and the header arrives as "ip, ip, ip", so this changes nothing there.
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && ( '' === $remote || $this->is_private_ip( $remote ) || $this->is_cloudflare_ip( $remote ) ) ) {
            $first = trim( explode( ',', (string) $_SERVER['HTTP_CF_CONNECTING_IP'] )[0] );
            if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
                return $first;
            }
        }
        return $remote;
    }

    /** Cloudflare's published edge ranges (cloudflare.com/ips, 2026-10-28). */
    private function is_cloudflare_ip( $ip ) {
        static $ranges = array(
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
            '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        );
        $bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if ( false === $bin ) {
            return false;
        }
        foreach ( $ranges as $cidr ) {
            list( $net, $bits ) = explode( '/', $cidr );
            $nb = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            if ( false === $nb || strlen( $nb ) !== strlen( $bin ) ) {
                continue;
            }
            $bits  = (int) $bits;
            $bytes = intdiv( $bits, 8 );
            if ( substr( $bin, 0, $bytes ) !== substr( $nb, 0, $bytes ) ) {
                continue;
            }
            $rem = $bits % 8;
            if ( 0 === $rem || ( ( ord( $bin[ $bytes ] ) ^ ord( $nb[ $bytes ] ) ) & ( 0xFF << ( 8 - $rem ) ) & 0xFF ) === 0 ) {
                return true;
            }
        }
        return false;
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
