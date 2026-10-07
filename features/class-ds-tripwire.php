<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DS Tripwire — daily indicator-of-compromise check + instant new-admin alert.
 *
 * Born from the Aug/Sep 2026 "WDG" fleet campaign (98 sites, both hosts).
 * Defender sat on nearly every compromised site and never alerted; the kit's
 * tells were nonetheless trivially checkable: rogue files in mu-plugins, fake
 * wdg-<hex>/starter-* plugin folders, and a self-replicating
 * self-healing comment block (the campaign's marker) appended to functions.php / index.php /
 * wp-config.php. On the campaign's patient zero the attacker's admin account
 * appeared TWO HOURS before the first file drop — an instant new-admin email
 * would have turned a 98-site campaign into a one-site incident.
 *
 * 1.9.130 (2026-09-16): the September wave got past everything above. On rattlers
 * and eysoccer a `<hex>wp` shell was planted at the WEB ROOT (not wp-content), sat
 * through two cleanups, and was used 10-14 days later to drop page-shadowing
 * doorway folders that served gambling pages to Google and empty 500s to members
 * (72 failed /register/ loads in one day). This class ran daily on both sites the
 * whole time and found nothing, for two reasons: nothing looked at the web root,
 * and the one "web root" check read ABSPATH, which on Flywheel resolves to the
 * root-owned /wordpress/ platform dir, not /www where the attacker writes. So:
 * - the web root is dirname(WP_CONTENT_DIR) on every host, never ABSPATH;
 * - it is listed against an ALLOWLIST of what belongs, and everything else is a
 *   finding (enumerating attacker names is a losing game: one regex missed the
 *   entire <hex>wp family because 'w' and 'p' are not hex);
 * - "executable" means a PHP extension OR a "<?php" header, because every 600 KB
 *   doorway index.php was static HTML with no PHP tag and PHP ran it anyway, and
 *   the hidden shells wore .php5/.phtml/.php7/.png/.mov;
 * - findings carry a tier. CRITICAL and HIGH email; REVIEW is recorded in state
 *   only, so the daily mail is never noise;
 * - Design Shop staff (@leagueapps.com) adding their own access is recorded and
 *   never emailed, from the instant hook too (it paged the team twice on Sep 15
 *   about our own cleanup logins).
 *
 * Design constraints:
 * - Cheap: a web-root listing, a plugin-folder listing, the tails of four files,
 *   and a name-only walk for fixed-name shells (plugins/themes 3 deep, uploads
 *   and the web root 1-2 deep, 20k entries max). No file-content scans beyond
 *   the first 4 KB of a bounded set. Measured 1-3 s once a day inside wp-cron,
 *   never on a visitor's request.
 * - Zero frontend cost: scheduling and hooks are registered in admin/cron/CLI
 *   contexts; ordinary visits never pay for it.
 * - Clone-compatible / fail open: first run seeds baselines silently; a fresh
 *   clone of the blueprint never emails anyone. Hard IOC patterns alert
 *   regardless of baseline.
 * - No dependencies: wp_mail only, one option row of state.
 * - Testable without fixtures: DS_Tripwire::scan_root( $dir ) runs the exact
 *   web-root classifier against any directory, e.g. a .quarantine-* folder that
 *   still holds the real malware with its original names.
 *
 * 1.9.132 (2026-09-17): everything above asks "does a NAME match?". dallaskicsfc showed
 * the limit: of five malicious files in one fake plugin, four were caught only by the
 * Nx###.php filename rule and the fifth (dsa.php, an eval(base64_decode(strrev(curl())))
 * loader) by nothing, and w5x9k2m7q4.php spelled "e"."x"."e"."c" from fragments so that
 * grep finds no "exec(". The hourly CONTENT scan (run_content_scan) hands every candidate
 * file to includes/ds-scan-engine.php, which tokenises it and scores BEHAVIOUR: request
 * input reaching a command sink, eval of a decoder, a call name assembled at runtime, a
 * self-rewriting loader, the campaign's markers. 63/63 on the fleet malware corpus, 0
 * CRITICAL on 10,893 clean blueprint files. Its resource contract is in the method's
 * docblock and in fleet-audit/SCANNER-SPEC.md section 6c.
 */
class DS_Tripwire {

    const CRON_HOOK = 'ds_tripwire_daily';
    const STATE_OPT = 'ds_tripwire_state';

    /**
     * Hard blocklist: mu-plugins basenames that are malware camouflage in this
     * campaign. These alert even if a baseline somehow contains them.
     */
    const MU_BLOCKLIST = array(
        'sso.php',
        'wordfence-security.php',   // the real Wordfence never lives in mu-plugins
        'nox-google-bot-bypass.php',
        'zz-no-cache.php',
    );

    /** A legit mu-plugins/index.php is an empty guard file; loaders are 20KB+. */
    const MU_INDEX_MAX_BYTES = 4096;
    /** mu-plugins files ds-toolkit itself writes; trusted only when small and clean (see check_mu_plugins). */
    /* Toolkit helpers that get installed on site after site, so the "new file in mu-plugins" rule
       would otherwise email once PER SITE as a rollout lands. ds-cache-purge-globals.php is the
       standard slow-site fix in speed-kit/SPEED.md and is 4854 bytes, which is why the cap moves from
       4096 to 8192. The gate is deliberately still name + size + no-obfuscation and NOT the bare
       ds-* prefix: the Sep 2026 attacker overwrote ds-origin-guard.php with an 8 KB loader, so a name
       on its own is not trust. One-off per-site files are not listed here on purpose - the rule
       self-heals into mu_baseline after a single alert, so they cost one email, not one per site. */
    const MU_MANAGED = array( 'ds-origin-guard.php', 'ds-antibot-off.php', 'ds-doorway-block.php', 'ds-cache-purge-globals.php' );
    const MU_MANAGED_MAX_BYTES = 8192;

    /** Self-heal markers; any hit in a scanned tail is a confirmed infection. */
    const MARKERS = array( 'WDG-CORE-' . 'START', '$wdg' . '_k', '$co' . 'ki' );

    /**
     * What a web root legitimately contains on Flywheel and WP Engine. Anything
     * at depth 1 that does not match is a finding. Attacker names are NOT listed
     * here on purpose: the allowlist is the rule, not the exception list.
     */
    const ROOT_ALLOW = '/^(wp-admin|wp-content|wp-includes|\.wordpress|flywheel-config|_wpeprivate|\.well-known|fw-scanner'
        . '|index\.php|wp-activate\.php|wp-blog-header\.php|wp-comments-post\.php|wp-config\.php|wp-config-sample\.php|wp-cron\.php'
        . '|wp-links-opml\.php|wp-load\.php|wp-login\.php|wp-mail\.php|wp-settings\.php|wp-signup\.php|wp-trackback\.php|xmlrpc\.php'
        . '|\.htaccess|robots\.txt|license\.txt|readme\.html|favicon\.ico|apple-touch-icon[a-z-]*\.png|\.DS_Store'
        . '|fw-flush-cache\.php|fw-status-check\.php|scan-manifest\..*\.json|(scan|malware|dbscan)-results-.*\.csv'
        . '|\.user\.ini|php\.ini|\.ssh|\.wp-cli|\.bash.*|\.profile|\.cache|\.config|\.local'
        . '|bv-preload\.php|bv_connector_[0-9a-f]+\.php|malcare-waf\.php'
        . '|\.quarantine-[0-9]+[a-z]?|\.sucuriquarantine|\.ms_jp_quarantine)$/i';

    /** The Play Store doorway kit's file set; three of these in one folder is the kit. */
    const KIT_FILES = array( 'apps.php', 'link.php', 'template.html', 'url_list.txt', 'urlkey.txt', 'word.txt', 'sitemap_index.xml' );

    /** Google Search Console verification tokens the attacker planted across 19 sites. */
    const KNOWN_BAD_GSC = array(
        '6313bd76ab531865', 'efa7380652dbb9c7', '423d49517cadfeab', '87c76e4ecadcf9a4',
        '1c82f06233560100', 'a7f9083ec5e0ee0b', 'f31c338420ffa4f7', 'cb9f43e6aa7e3150',
        // Added 2026-09-18. Each was found on a fleet site during the Sep 17-18 batch and scored
        // only REVIEW because it was unknown. '517c3ca44ca38f1d' appeared on TWO unrelated sites
        // (plljuniors.com, summitfieldhockey.com), which is the same many-sites-one-token pattern
        // that identified f31c338420ffa4f7 (three sites) and cb9f43e6aa7e3150.
        '517c3ca44ca38f1d', '48d14fbc900051df', '3222669cc1aaedd0',
    );

    /**
     * OUR OWN Search Console tokens. A google<16hex>.html in a web root is indistinguishable from an
     * attacker's by shape, so without this list the team's own verification files get reported as
     * suspected attacker tokens - which is exactly what would have happened to the five uploaded on
     * 2026-09-18. Add a token here only when we placed it.
     */
    const KNOWN_GOOD_GSC = array(
        'b53305670f66d641',  // Design Shop, placed 2026-09-18 on pocono, plljuniors, summitfieldhockey, primetimelacrosse, somerssports
    );

    /**
     * Malware we have identified by exact hash, so the alert NAMES it instead of describing it.
     * The engine already catches these behaviourally; this only improves what the email says, which
     * matters when someone is triaging twenty alerts at 3am. Hash, not filename: the dropper below
     * wears a `.gitignore` name precisely because a filename is worthless as evidence.
     */
    const KNOWN_BAD_MD5 = array(
        // 567-byte /www/.gitignore POLYGLOT, byte-identical on plljuniors.com and
        // summitfieldhockey.com (2026-09-18). Opens with a PHP tag, builds its variable name with
        // chr(0x78^0x1d), and curls its payload from http://lxml.ahkj.lol/gitignore.txt.
        '79880f0eb3d9cddb3198626bcbd6c081' => 'lxml.ahkj.lol gitignore-polyglot remote loader',
    );

    /** Fixed filenames the campaign reuses for its shells (Wordfence: file manager / RCE). */
    const SHELL_NAMES = '/^(Nx[0-9]{3}\.php|egl\.php|kir\.php|filefuns\.php)$/';

    /** Bound on the name-only shell walk so a 50k-file site stays sub-second. */
    const WALK_CAP = 20000;

    /* ------------------------------------------ content engine (1.9.132) ------------------ */
    /** Hourly behaviour-scan hook, separate from the daily IOC check so each cost is bounded alone. */
    const CONTENT_HOOK = 'ds_tripwire_content';
    const MAILCHECK_HOOK = 'ds_tripwire_mailcheck';
    /** Seconds of scanning per cron run; the file list resumes from a cursor next run. */
    const CONTENT_BUDGET_S = 15;
    /** Never token_get_all() a file above this. Measured 346 bytes of memory per source byte on a
     *  token-dense file: 256 KB keeps the worst case under 100 MB inside a 256 MB request. Every
     *  shell on record is under 30 KB; bigger files get the engine's regex fallback instead. */
    const CONTENT_TOKCAP = 262144;
    /** Directory entries visited per run before the listing stops (bounds the walk, not the scan). */
    const CONTENT_WALK_CAP = 40000;
    /** Files modified within this window are scanned FIRST every run, so a fresh drop is seen within
     *  the hour on any site while the full sweep proceeds across runs in the background. */
    const CONTENT_RECENT_S = 259200;
    /** Newest-first cap on that priority set (a plugin update can touch thousands of files). */
    const CONTENT_RECENT_MAX = 3000;
    /** Extensions scanned anywhere. Any other extension is scanned only at the web root, in uploads
     *  and in mu-plugins, which is where polyglots and dotfile shells hide. */
    /** A file this small has no room for code, so it cannot execute anything. See is_executable_file(). */
    const EXEC_MIN_BYTES = 16;
    /* What the content scan will OPEN. A rule cannot fire on a file that is never read, and until
       2026-09-27 this list meant three whole classes were invisible unless they happened to sit at
       depth 1, in uploads, or in mu-plugins:
         - MEDIA extensions. The 09-26 re-seed on dallaskicsfc put w.avi and qjISgYFUT.m4a, both
           opening <?php, inside real plugin trees. The polyglot rule that convicts them is
           header-based and correct; the files simply never reached it. Found by hand instead.
         - .htaccess. Every nest in the campaign drops one with an identical fingerprint, which is our
           single best nest locator, and the ROOT .htaccess was itself rewritten into a backdoor.
         - .off, our own disable convention, so camouflage cannot hide behind it outside mu-plugins.
       Measured cost of the whole widening on a live install: +11 files against 12,608 already read
       out of 22,097 total, so ~0.05%.
       ARCHIVES ARE DELIBERATELY NOT HERE. A real .zip is binary, so its PHP is compressed and the
       polyglot test cannot see it, while "every plugin zip contains PHP" is an established false
       positive that has already cost two allow-list hashes (bb-theme-builder.zip, mokan-core.zip).
       Adding .zip would spread that FP into every plugin tree and buy nothing. A payload stash inside
       a nest is caught by the nest .htaccess rule, which takes the whole directory. */
    const CONTENT_EXT = '/\.(php|phtml|php[3-8]|phar|pht|inc|html?|module|install|off|htaccess|avi|m4a|mp2|mp3|mp4|mov|wav|wmv|f4v|ogv|jpx|wbmp)$/i';

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function init() {
        // The cron callback must be bound on every request type so a due event
        // can always fire; everything below it is admin/cron/CLI-only.
        add_action( self::CRON_HOOK, array( $this, 'run_checks' ) );
        add_action( self::CONTENT_HOOK, array( $this, 'run_content_scan' ) );
        // Mail reachability probe. Bound on every request type because it MUST run in the web
        // context: wp_mail() can never succeed from WP-CLI on either platform (WP Engine has no
        // /usr/sbin/sendmail at all; Flywheel's is a setuid shim whose config is unreadable
        // outside a web request), so a CLI test reports a false negative on every site.
        add_action( self::MAILCHECK_HOOK, array( $this, 'run_mailcheck' ) );
        // Copy every alert and a daily check-in to Design Shop HQ (features/class-ds-hq-link.php).
        require_once __DIR__ . '/class-ds-hq-link.php';
        DS_HQ_Link::boot( $this->settings );

        // Instant alert when an administrator appears. Registration and role
        // grants only ever happen in admin/AJAX/CLI flows, so these hooks are
        // effectively free on the frontend too.
        add_action( 'user_register', array( $this, 'on_user_change' ), 10, 1 );
        add_action( 'set_user_role', array( $this, 'on_role_change' ), 10, 3 );
        add_action( 'add_user_role', array( $this, 'on_role_added' ), 10, 2 );

        if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
                // 03:10 site-local time: off-peak, and offset from the :00 herd.
                // strtotime against the tz-shifted clock gives a "local reading";
                // subtract the offset to get the real UTC timestamp cron expects.
                $local  = strtotime( 'tomorrow 03:10', current_time( 'timestamp' ) );
                $first  = $local ? $local - (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) : 0;
                wp_schedule_event( $first ? $first : time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
            }
            if ( ! wp_next_scheduled( self::CONTENT_HOOK ) ) {
                // Hourly. The first run is spread over the next hour so the fleet never starts together.
                wp_schedule_event( time() + wp_rand( 300, 3600 ), 'hourly', self::CONTENT_HOOK );
            }
        }
    }

    /* ---------------------------------------------------------------- paths */

    /**
     * The directory the attacker can write to and nginx serves first. On Flywheel
     * ABSPATH is /www/.wordpress/ -> /wordpress (root-owned, unwritable); the real
     * web root is /www. On WP Engine ABSPATH and the web root coincide. The parent
     * of wp-content is the web root on both, so that is the definition.
     */
    public static function web_root() {
        $r = dirname( WP_CONTENT_DIR );
        return is_dir( $r ) ? $r : untrailingslashit( ABSPATH );
    }

    /* ---------------------------------------------------------------- cron */

    public function run_checks() {
        $state  = get_option( self::STATE_OPT, array() );
        if ( ! is_array( $state ) ) {
            $state = array();
        }
        $seeded = ! empty( $state['seeded'] );

        // Every finding is [ tier, text ]. CRITICAL = a shell or re-infection
        // source; HIGH = act today; REVIEW = a person should look, no email.
        $f = array();
        foreach ( $this->check_mu_plugins( $state, $seeded ) as $t ) { $f[] = array( 'CRITICAL', $t ); }
        foreach ( $this->check_plugin_dirs() as $t )                { $f[] = array( 'CRITICAL', $t ); }
        foreach ( $this->check_marker_tails() as $t )               { $f[] = array( 'CRITICAL', $t ); }
        // Web root: CRITICAL always mails. HIGH/REVIEW entries mail ONCE, then are
        // remembered; a site's own bespoke folder (brsoccer /classes/) or an old
        // WordPress copy (georgiakings /gkb001/) must not page the team daily.
        $seen  = isset( $state['root_seen'] ) ? (array) $state['root_seen'] : array();
        $now   = array();
        foreach ( self::scan_root( self::web_root() ) as $pair ) {
            $now[ $pair[2] ] = true;
            if ( 'CRITICAL' !== $pair[0] && isset( $seen[ $pair[2] ] ) ) {
                $pair[0] = 'REVIEW';   // already reported once: keep in state, keep out of mail
            }
            $f[] = $pair;
        }
        $state['root_seen'] = $now;
        foreach ( $this->check_named_shells() as $t )               { $f[] = array( 'CRITICAL', $t ); }
        foreach ( $this->check_admins( $state, $seeded ) as $t )    { $f[] = array( 'HIGH', $t ); }
        // Owns its own tier and its own once-a-day throttle, so it returns pairs.
        foreach ( $this->check_stale_staging( $state ) as $pair )    { $f[] = $pair; }

        $state['seeded']        = 1;
        $state['last_run']      = time();
        $state['last_findings'] = array_map( function ( $p ) { return $p[0] . ': ' . $p[1]; }, $f );
        update_option( self::STATE_OPT, $state, false );

        $mail = array_filter( $f, function ( $p ) { return 'REVIEW' !== $p[0]; } );
        // After the alert below has gone out, so a slow HQ can never delay the email.
        add_action( 'shutdown', function () use ( $f ) { do_action( 'ds_tripwire_checked', $f ); } );
        if ( $mail && $seeded ) {
            $worst = 'HIGH';
            foreach ( $mail as $p ) { if ( 'CRITICAL' === $p[0] ) { $worst = 'CRITICAL'; break; } }
            $this->alert( $worst, array_map( function ( $p ) { return '[' . $p[0] . '] ' . $p[1]; }, $mail ) );
        }
        return $f;
    }

    /** New PHP files in mu-plugins vs baseline, plus hard IOC names. */
    private function check_mu_plugins( &$state, $seeded ) {
        $out = array();
        $dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
        if ( ! is_dir( $dir ) ) return $out;

        $current = array();
        foreach ( (array) glob( $dir . '/*.php' ) as $f ) {
            $base = basename( $f );
            $size = (int) @filesize( $f );
            $current[ $base ] = $size;

            if ( preg_match( '/^wdg-[0-9a-f]{8}\.php$/i', $base ) ) {
                $out[] = "A known malware loader file was found in mu-plugins: {$base} ({$size} bytes). This is the wdg backdoor from the recent attack.";
            } elseif ( in_array( $base, self::MU_BLOCKLIST, true ) ) {
                $out[] = "A file called {$base} was found in mu-plugins. Malware from the recent attack disguises itself with this exact name.";
            } elseif ( 'index.php' === $base && $size > self::MU_INDEX_MAX_BYTES ) {
                $out[] = "The mu-plugins/index.php file is unusually big ({$size} bytes). A normal one is empty; the malware version is a large hidden loader.";
            }
        }

        $baseline = isset( $state['mu_baseline'] ) ? (array) $state['mu_baseline'] : array();
        if ( $seeded ) {
            foreach ( array_diff_key( $current, $baseline ) as $base => $size ) {
                // Toolkit-managed helpers (Origin Guard, the incident-response AntiBot
                // and doorway files) are exempt ONLY when they look like ours: tiny and
                // free of obfuscation. The Sep 2026 attacker overwrote ds-origin-guard.php
                // with an 8 KB loader, so a bare name is not trust.
                if ( in_array( $base, self::MU_MANAGED, true ) && $size <= self::MU_MANAGED_MAX_BYTES
                    && ! preg_match( '/eval\s*\(|base64_decode|gzinflate|gzuncompress|str_rot13|goto [A-Za-z_]/', (string) @file_get_contents( $dir . '/' . $base, false, null, 0, 8192 ) ) ) {
                    continue;
                }
                $out[] = "A new PHP file appeared in mu-plugins since yesterday: {$base} ({$size} bytes). Nobody should be adding files there.";
            }
        }
        $state['mu_baseline'] = $current;
        return array_unique( $out );
    }

    /**
     * Leftover ds-toolkit install staging folders.
     *
     * The WP Engine installer stages a release in wp-content/upgrade/.dstk-new.<pid> and moves the
     * outgoing copy aside to .dstk-prev.<pid>, then deletes both at the end of a successful run. If
     * the run is killed mid-install (leash timeout, dropped SSH, container recycle) its EXIT trap
     * never fires and the folder stays behind.
     *
     * Nothing else reports that, which is the whole reason this rule exists. The content scan skips
     * /wp-content/upgrade/ entirely because it is WordPress's own update scratch dir, and since
     * 1.10.19 the scan also ignores byte-identical copies of our own engine wherever they sit. Both
     * of those are correct: a staged copy of ds-toolkit is not a web shell, and calling it one
     * mailed 71 false CRITICALs during the 1.10.16 push on 2026-09-28. Together, though, they made a
     * leftover folder invisible, and a silent leftover is exactly what we were asked not to have.
     * Alipio, 2026-09-29: "if it leave trace its good the tripwire notify me."
     *
     * This does not undo 1.10.19. That change stops a copy of our engine being called a web shell;
     * this reports the FOLDER as a leftover, by name and age. Both are true at once.
     *
     * HIGH, not CRITICAL: it is our own garbage, not an attacker's, and the fix is a delete rather
     * than an incident. Younger than an hour is an install in progress, so it stays silent.
     *
     * The shape matched here is deliberately the same one job-toolkit-install-wpe-fast.sh deletes
     * (.dstk-new.<digits> / .dstk-prev.<digits>, directories only). If this rule were looser it
     * would report folders the installer refuses to remove, and the alert could never be cleared.
     */
    private function check_stale_staging( &$state ) {
        $out  = array();
        $hour = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
        $day  = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
        $seen = isset( $state['staging_seen'] ) ? (array) $state['staging_seen'] : array();
        $now  = time();

        foreach ( array( 'plugins', 'upgrade' ) as $sub ) {
            foreach ( array( 'new', 'prev' ) as $kind ) {
                // GLOB_ONLYDIR is what makes a FILE wearing a staging name produce nothing; the
                // installer only ever creates directories, so a file is not our leftover.
                foreach ( (array) glob( WP_CONTENT_DIR . '/' . $sub . '/.dstk-' . $kind . '.*', GLOB_ONLYDIR ) as $d ) {
                    $base = basename( $d );
                    if ( ! preg_match( '/^\.dstk-(new|prev)\.[0-9]+$/', $base ) ) {
                        continue;
                    }
                    $mtime = (int) @filemtime( $d );
                    if ( ! $mtime ) {
                        continue;
                    }
                    $age = $now - $mtime;
                    if ( $age < $hour ) {
                        continue;   // an install is running right now
                    }
                    $rel = 'wp-content/' . $sub . '/' . $base;
                    $out[] = array(
                        // Already mailed within the day: keep it in state, keep it out of the mail,
                        // the same way the web-root rule handles a site's own bespoke folder.
                        ( ! empty( $seen[ $rel ] ) && ( $now - (int) $seen[ $rel ] ) < $day ) ? 'REVIEW' : 'HIGH',
                        "Leftover DS Toolkit install folder: {$rel} (" . self::age_phrase( $age ) . " old). An update was interrupted; delete the folder.",
                    );
                    if ( empty( $seen[ $rel ] ) || ( $now - (int) $seen[ $rel ] ) >= $day ) {
                        $seen[ $rel ] = $now;
                    }
                }
            }
        }

        // Drop folders that are gone so state cannot grow without bound.
        $live = array();
        foreach ( $out as $pair ) {
            if ( preg_match( '#(wp-content/[a-z]+/\.dstk-[a-z]+\.[0-9]+)#', $pair[1], $m ) ) {
                $live[ $m[1] ] = isset( $seen[ $m[1] ] ) ? $seen[ $m[1] ] : $now;
            }
        }
        $state['staging_seen'] = $live;
        return $out;
    }

    /** Whole hours, switching to whole days past two, so the message reads like a person wrote it. */
    private static function age_phrase( $age ) {
        $h = (int) floor( $age / 3600 );
        if ( $h >= 48 ) {
            return (int) floor( $h / 24 ) . ' days';
        }
        return $h . ' h';
    }

    /** Fake plugin folders used by the campaign. Pure directory listing. */
    private function check_plugin_dirs() {
        $out = array();
        foreach ( (array) glob( WP_CONTENT_DIR . '/plugins/*', GLOB_ONLYDIR ) as $d ) {
            $base = basename( $d );
            if ( preg_match( '/^(wdg-[0-9a-f]{8}|starter-[a-z]+-[a-z0-9]+)$/i', $base ) ) {
                $out[] = "A fake plugin folder that matches the attacker naming pattern was found: {$base}. Real plugins are never named like this.";
            }
        }
        return $out;
    }

    /** Marker scan of the four known self-heal injection targets (tails only). */
    private function check_marker_tails() {
        $out     = array();
        $root    = self::web_root();
        $targets = array(
            get_stylesheet_directory() . '/functions.php',
            get_template_directory() . '/functions.php',
            $root . '/index.php',
        );
        // wp-config can live in the web root, ABSPATH, or one level up.
        foreach ( array( $root . '/wp-config.php', ABSPATH . 'wp-config.php', dirname( ABSPATH ) . '/wp-config.php' ) as $cfg ) {
            if ( file_exists( $cfg ) ) { $targets[] = $cfg; break; }
        }

        foreach ( array_unique( $targets ) as $file ) {
            if ( ! is_readable( $file ) ) continue;
            $size = (int) @filesize( $file );
            $fh   = @fopen( $file, 'rb' );
            if ( ! $fh ) continue;
            if ( $size > 98304 ) @fseek( $fh, -98304, SEEK_END );
            $tail = (string) stream_get_contents( $fh );
            fclose( $fh );
            foreach ( self::MARKERS as $marker ) {
                if ( false !== strpos( $tail, $marker ) ) {
                    $out[] = 'This file contains the malware signature and is infected: ' . $file;
                    break;
                }
            }
        }
        return $out;
    }

    /* ----------------------------------------------------------- web root */

    /**
     * True when a file would be executed by PHP: a PHP-family extension (PHP
     * happily runs a .php that is pure HTML; every doorway index.php was exactly
     * that), OR a "<?php" tag in the first 4 KB whatever the extension (.png,
     * .mov, dotfile shells).
     */
    private static function is_executable_file( $path ) {
        // A file with no room for code cannot execute anything, whatever its extension.
        // skyy2win.com carried 47,082 identical 7-byte "<?php\n\n" directory-index guards
        // (md5 3beefb00777a6bd04265b7d33c23efa9). Counting each as an executable produced
        // 2,494 HIGH + 6,326 REVIEW findings and a 7.6 MB alert email that Gmail delivered as an
        // attachment nobody could read - while 242 REAL payloads sat in the same tree unseen.
        // The smallest useful shell, "<?php eval($_GET[0]);", is 21 bytes. A failed filesize()
        // falls through rather than under-reporting. The NAME-based shell rules are deliberately
        // not gated on this: somerssports.org's 0-byte filefuns.php must still be caught by name.
        $sz = @filesize( $path );
        if ( false !== $sz && $sz <= self::EXEC_MIN_BYTES ) {
            return false;
        }
        if ( preg_match( '/\.(php|phtml|php5|php7|phar)$/i', $path ) ) {
            return true;
        }
        $head = (string) @file_get_contents( $path, false, null, 0, 4096 );
        return false !== strpos( $head, '<?php' );
    }

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

    private static function known_good_md5() {
        static $set = null;
        if ( null !== $set ) {
            return $set;
        }
        $set  = array();
        $file = DS_TOOLKIT_PATH . 'includes/known-good.md5';
        $fh   = is_readable( $file ) ? @fopen( $file, 'r' ) : false;
        if ( $fh ) {
            while ( false !== ( $line = fgets( $fh ) ) ) {
                $line = trim( $line );
                if ( 32 === strlen( $line ) && ctype_xdigit( $line ) ) {
                    $set[ $line ] = true;
                }
            }
            fclose( $fh );
        }
        // Remote additions (1.9.141), merged AFTER the bundle so the bundle stays authoritative and the
        // remote copy can only ever ADD. Every fetch failure leaves $set exactly as the bundle built it,
        // which is the behaviour every release before this one had.
        foreach ( self::remote_list( 'allow' ) as $md5 => $v ) {
            $set[ $md5 ] = true;
        }
        return $set;
    }

    /* ------------------------------------------ remote lists (1.9.141) ------------------------ */

    /**
     * Where the fleet-wide lists live. Raw GitHub, main branch. PUBLIC on purpose: the lists hold
     * md5s of files and nothing secret, and a private repo would put a read token on every one of
     * ~1,100 sites, where the first compromised site leaks it. What protects the fleet is WRITE
     * access to main, not read access to the file. A hash committed here is live on every site
     * within one cron cycle, with no release and no fleet push.
     */
    const REMOTE_LIST_BASE = 'https://raw.githubusercontent.com/Design-Shop-LeagueApps-Department/ds-toolkit/main/lists/';
    const REMOTE_TTL       = 3600;   // one cron cycle
    const REMOTE_JITTER    = 900;    // per-site spread, so 1,100 sites do not hit GitHub in the same second
    const REMOTE_MAX_BYTES = 524288; // 512 KB; the remote lists are deltas, the ~530 KB bundle stays bundled
    const REMOTE_TIMEOUT   = 4;      // seconds; runs BEFORE the scan deadline is stamped, cached the other 59 min

    /** Per-list fetch health for this run, recorded in state so silence is auditable. */
    /**
     * VENDOR MANIFEST verification. wordpress.org publishes per-file md5 for every plugin release at
     * downloads.wordpress.org/plugin-checksums/<slug>/<version>.json. A file that the vendor
     * verifiably ships, at that exact path, in the version the plugin declares, is not a finding.
     *
     * This exists because a vendor false positive is one hash PER VERSION PER FILE PER PLUGIN. On
     * 2026-09-27 five CRITICALs stayed unread and four were one plugin's own code; clearing them by
     * hand took four releases' worth of allow-list entries (#245 #246 #247 #250) and still only
     * covered the versions we happened to see. WP Engine auto-updates plugins, so on that half of
     * the fleet the hand-curated list can never keep up.
     *
     * WHY IT IS NOT A SOFTENING. It is NOT "trust files inside a registered plugin" - two shells in
     * our own signature table live inside real active plugins (bb-plugin's mailerlite vendor tree,
     * wpforms-lite's assets/images/entry-importer/). The test is this md5 at this path in this
     * version, which is the same hash equality known_good_md5() does, with the vendor answering
     * instead of a human. Verified: the wpforms-lite shell path is absent from that plugin's
     * official 4,856-file manifest, and premium plugins (bb-plugin, css-hero, wpe-site-migration)
     * plus ds-toolkit itself have no manifest at all, so they can never be cleared this way.
     *
     * Every failure refuses to clear: no manifest, path not shipped, md5 differs (someone edited a
     * vendor file - interesting), transport error, budget spent. Only an exact hit returns true.
     */
    const VENDOR_BASE      = 'https://downloads.wordpress.org/plugin-checksums/';
    const VENDOR_TIMEOUT   = 4;       // seconds, same as the remote lists
    const VENDOR_MAX_BYTES = 4194304; // 4 MB; the largest manifests are ~500 KB
    const VENDOR_FETCH_MAX = 6;       // manifests per run. A scoring file is rare, so this is generous.
    const VENDOR_TTL       = 604800;  // 7 days; a slug+version manifest is immutable once published

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
        $key   = 'ds_tripwire_rl_' . $which;
        $good  = 'ds_tripwire_rlg_' . $which;
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

    /** Count executable files under $dir, capped so a 35k-file WordPress copy does not stall the cron. */
    private static function exec_count( $dir, $cap = 300 ) {
        $n = 0; $seen = 0;
        try {
            $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
            foreach ( $it as $file ) {
                if ( ++$seen > $cap ) break;
                if ( $file->isFile() && self::is_executable_file( $file->getPathname() ) ) $n++;
            }
        } catch ( Exception $e ) { /* unreadable: report what we have */ }
        return $n;
    }

    /** Published page slugs, so a web-root folder named after a page is named as such and not guessed. */
    private static function page_slugs() {
        global $wpdb;
        $slugs = $wpdb->get_col( "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'" );
        return array_fill_keys( array_map( 'strval', (array) $slugs ), true );
    }

    /**
     * Classify everything at depth 1 of $root against the allowlist. Returns
     * [ tier, text ] pairs. Public and static so it can be pointed at a
     * .quarantine-* folder holding the real malware, as a test with no fixtures.
     */
    public static function scan_root( $root, $slugs = null ) {
        $out = array();
        if ( ! is_dir( $root ) ) return $out;
        if ( null === $slugs ) $slugs = self::page_slugs();
        $rel = function ( $p ) { return str_replace( dirname( WP_CONTENT_DIR ), '', $p ); };
        // Every pair carries the entry name as [2] so run_checks() can remember
        // non-critical entries and email them once, not daily.
        $add = function ( $tier, $text ) use ( &$out, &$e ) { $out[] = array( $tier, $text, $e ); };

        foreach ( (array) scandir( $root ) as $e ) {
            if ( '.' === $e || '..' === $e || preg_match( self::ROOT_ALLOW, $e ) ) continue;
            /* .maintenance is WordPress's own updater marker: core writes it when an update starts and
               deletes it when the update ends, so a scan that lands in that window sees it legitimately.
               It is NOT in ROOT_ALLOW by name on purpose - the comment on that constant is the rule, and
               a bare name there would wave through a shell called .maintenance. Hash-allow-listing is
               unwinnable too, because the whole body is a timestamp and every leftover differs. So match
               the SHAPE, exactly as includes/ds-scan-engine.php already does for the same file; anything
               else wearing the name falls through and is judged normally.
               3hlhockey.com, 2026-10-07: HIGH on a 33-byte .maintenance, which is precisely the length of
               core's own "<?php $upgrading = <10-digit epoch>; ?>". The engine knew; this rule did not. */
            if ( '.maintenance' === strtolower( $e ) ) {
                // $p is not assigned until further down the loop, so build the path here.
                $body = (string) @file_get_contents( $root . '/' . $e, false, null, 0, 512 );
                if ( preg_match( '/^\s*<\?php\s*\$upgrading\s*=\s*\d+\s*;\s*(\?>)?\s*$/', $body ) ) {
                    continue;
                }
            }
            // A site's OWN bespoke web-root code is a documented false-positive class
            // (SCANNER-SPEC section 4). brsoccer.org keeps a 2019 schedules app in /www/events/,
            // /www/classes/ and /www/require/ that bb-theme-child/header.php requires on every
            // page; quarantining it took that site down for ~25 minutes, and because /events
            // is also a published page slug the page-shadow rule scores it CRITICAL, which by
            // design never baselines - so it alerts every single day. Exact names only, never a
            // regex, so a site cannot break the scan with a bad pattern, and the names come from
            // an mu-plugin on that site rather than from this list.
            $extra = apply_filters( 'ds_tripwire_root_extra_allow', array(), $root );
            if ( is_array( $extra ) && in_array( $e, $extra, true ) ) continue;
            $p = $root . '/' . $e;

            if ( is_dir( $p ) ) {
                if ( preg_match( '/^[0-9a-f]{3,8}wp$/i', $e ) ) {
                    $add( 'CRITICAL', "Attacker toolbox folder {$e}/ in the web root ({$rel($p)}). This is the web shell that re-infected rattlers and eysoccer after cleanup: quarantine it FIRST, then the doorways." );
                    continue;
                }
                if ( isset( $slugs[ $e ] ) ) {
                    $add( 'CRITICAL', "A folder named after this site's own page sits in the web root: {$e}/ ({$rel($p)}). The server serves it before WordPress, so visitors get the attacker's page (or a 500) instead of the real one. Page-shadowing doorway." );
                    continue;
                }
                $kit = 0;
                foreach ( self::KIT_FILES as $k ) { if ( file_exists( $p . '/' . $k ) ) $kit++; }
                $sitemaps = count( (array) glob( $p . '/sitemap_*.xml' ) );
                if ( $kit >= 3 || $sitemaps >= 3 ) {
                    $add( 'CRITICAL', "The Play Store doorway kit sits in {$e}/ ({$kit}/7 kit files, {$sitemaps} bulk sitemaps). It serves fake app-store pages under this domain to Google." );
                    continue;
                }
                foreach ( (array) glob( $p . '/google*.html' ) as $g ) {
                    $add( 'CRITICAL', 'Fake Google Search Console verification file inside ' . $e . '/: ' . basename( $g ) . '. The attacker claims this domain in Search Console with it.' );
                }
                $x = self::exec_count( $p );
                if ( $x > 0 ) {
                    $add( 'HIGH', "Unexpected folder {$e}/ in the web root holds {$x} executable file(s) (PHP by extension or header). Nothing outside WordPress should be here; list it." );
                } else {
                    $add( 'REVIEW', "Unexpected folder {$e}/ in the web root, no executable content found. Probably an old backup or an asset dump; confirm and remove." );
                }
                continue;
            }

            // files
            if ( preg_match( '/^google([0-9a-f]{16})\.html$/i', $e, $m ) ) {
                if ( in_array( strtolower( $m[1] ), self::KNOWN_GOOD_GSC, true ) ) {
                    continue;   // our own verification file; not a finding at all
                }
                if ( in_array( strtolower( $m[1] ), self::KNOWN_BAD_GSC, true ) ) {
                    $add( 'CRITICAL', "Known attacker Google Search Console token in the web root: {$e}. Removing the file does not revoke the owner; the partner must remove it under Search Console > Settings > Users and permissions." );
                } else {
                    $add( 'REVIEW', "Google Search Console verification file in the web root: {$e}. Not a known attacker token; confirm the partner or their SEO vendor placed it." );
                }
                continue;
            }
            $size = (int) @filesize( $p );
            if ( preg_match( '/^wp-.*\.php$/i', $e ) ) {
                $add( 'HIGH', "{$e} in the web root is NOT a WordPress core file ({$size} bytes). Backdoors wear fake core names (wp-confih.php, wp-mails.php, wp-configs.php)." );
            } elseif ( preg_match( '/^sitemap.*\.xml$/i', $e ) ) {
                $add( 'REVIEW', "A physical sitemap file sits in the web root: {$e} ({$size} bytes). Yoast serves sitemaps virtually; a real file here is unusual." );
            } elseif ( self::is_executable_file( $p ) ) {
                $add( 'HIGH', "Unexpected executable file in the web root: {$e} ({$size} bytes)." );
            } else {
                $add( 'REVIEW', "Unexpected file in the web root: {$e} ({$size} bytes), not executable." );
            }
        }
        return $out;
    }

    /**
     * Fixed-name shells the campaign drops (Nx###.php was inside every one of 35
     * fake plugins; egl.php / kir.php in the fake themes; kir.php twice in
     * uploads) and the BypassServ self-resurrecting nest. Name-only walk, capped.
     */
    private function check_named_shells() {
        $out  = array();
        $root = self::web_root();
        $seen = 0;
        $walk = function ( $dir, $depth ) use ( &$out, &$seen, $root ) {
            if ( ! is_dir( $dir ) ) return;
            try {
                $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
                $it->setMaxDepth( $depth );
                foreach ( $it as $f ) {
                    if ( ++$seen > self::WALK_CAP ) return;
                    $name = $f->getFilename();
                    $path = $f->getPathname();
                    if ( false !== strpos( $path, '/.quarantine-' ) || false !== strpos( $path, '/.sucuriquarantine/' ) ) continue;
                    if ( $f->isDir() && '.sys_cache_log' === $name ) {
                        $out[] = 'Self-resurrecting BypassServ shell nest: ' . $path . '. It restores deleted copies from its other nests, so every nest must go in one pass.';
                    } elseif ( $f->isFile() && preg_match( self::SHELL_NAMES, $name ) ) {
                        $out[] = 'Fixed-name shell from this campaign: ' . $path . ' (' . (int) $f->getSize() . ' bytes).';
                    }
                }
            } catch ( Exception $e ) { /* unreadable subtree: skip */ }
        };
        // Every fixed-name shell on record sat at depth <= 3 under plugins/themes
        // (plugins/starter-x/Nx444.php, themes/starter-x/egl.php) and at depth 1
        // in uploads (uploads/kir.php). Deeper walks cost seconds for nothing.
        $walk( WP_CONTENT_DIR . '/plugins', 3 );
        $walk( WP_CONTENT_DIR . '/themes', 3 );
        $walk( WP_CONTENT_DIR . '/mu-plugins', 2 );
        $walk( WP_CONTENT_DIR . '/uploads', 1 );
        $walk( $root, 2 );
        return array_values( array_unique( $out ) );
    }

    /**
     * Administrator roster drift since the previous run.
     *
     * Since alert_new_admin() folds every hook-caught account into the baseline,
     * anything this still catches got its role WITHOUT going through
     * user_register / set_user_role / add_user_role: a direct database write, or
     * a plugin bypassing the roles API. That is a stronger signal than it used to
     * be, not a weaker one, so the wording below stays blunt.
     */
    private function check_admins( &$state, $seeded ) {
        $out    = array();
        $admins = array();
        foreach ( get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'user_login', 'user_email' ) ) ) as $u ) {
            $admins[ (int) $u->ID ] = $u->user_login . ' <' . $u->user_email . '>';
        }
        $baseline = isset( $state['admin_baseline'] ) ? (array) $state['admin_baseline'] : array();
        if ( $seeded ) {
            foreach ( array_diff_key( $admins, $baseline ) as $id => $label ) {
                // Design Shop staff adding their own access is routine, not a break-in.
                if ( preg_match( '/<[^>]+@leagueapps\.com>$/i', $label ) ) continue;
                $out[] = "A new administrator account appeared since yesterday: {$label}. If nobody on the team created it, treat this as a break-in.";
            }
        }
        $state['admin_baseline'] = $admins;
        return $out;
    }

    /* ------------------------------------------------- instant admin alerts */

    public function on_user_change( $user_id ) {
        $user = get_userdata( $user_id );
        if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
            $this->alert_new_admin( $user, 'registered with the administrator role' );
        }
    }

    public function on_role_change( $user_id, $role, $old_roles ) {
        if ( 'administrator' === $role && ! in_array( 'administrator', (array) $old_roles, true ) ) {
            $user = get_userdata( $user_id );
            if ( $user ) $this->alert_new_admin( $user, 'was promoted to administrator' );
        }
    }

    public function on_role_added( $user_id, $role ) {
        if ( 'administrator' === $role ) {
            $user = get_userdata( $user_id );
            if ( $user ) $this->alert_new_admin( $user, 'was granted the administrator role' );
        }
    }

    /**
     * Alert on a new administrator, but only when the account cannot be explained
     * by someone already trusted on this site.
     *
     * Two things were wrong with alerting on every new administrator (found
     * 2026-09-04 on accessplus1, where a real partner user created by a Design
     * Shop admin produced a "you may be compromised" email):
     *
     * 1. Routine provisioning paged the whole team. An alert that fires on our own
     *    normal work teaches people to skim past it, and then the genuine one gets
     *    skimmed past too, which is the exact failure this feature exists to avoid.
     * 2. The same event was reported TWICE. check_admins() diffs against
     *    admin_baseline, which this path never updated, so the account came back
     *    that night as "appeared since yesterday".
     *
     * The campaign's signature is the opposite of routine provisioning: its admins
     * (adminxix, the TDN####Cz@tripledown.org set, admin_xxxxxx@example.com) arrived
     * with no logged-in creator at all. So attribution is the filter. An established
     * administrator, or our own WP-CLI tooling, is recorded silently; anything
     * unattributable, freshly escalated, or carrying a throwaway address still
     * emails immediately.
     *
     * 1.9.130: a Design Shop staff address (@leagueapps.com) is recorded and never
     * emailed, whoever created it. Our own cleanup logins on 2026-09-15 arrived as
     * "unknown actor" and paged the team twice about ourselves.
     */
    private function alert_new_admin( $user, $how ) {
        static $alerted = array();
        if ( isset( $alerted[ $user->ID ] ) ) {
            return;
        }
        $alerted[ $user->ID ] = true;

        $actor = $this->current_actor();

        // Recorded either way, so the nightly roster check never reports the same
        // account a second time.
        $this->remember_admin( $user, $actor );

        if ( preg_match( '/@leagueapps\.com$/i', (string) $user->user_email ) ) {
            return;
        }
        if ( $this->actor_is_established() && ! self::email_is_throwaway( $user->user_email ) ) {
            return;
        }

        $this->alert(
            'HIGH',
            array(
                "A new administrator just appeared on this site: {$user->user_login} <{$user->user_email}> ({$how}).",
                "Created by: {$actor}.",
                'If nobody on the team did this, the site may be compromised. In the recent attack a rogue admin account appeared two hours before the malware. Flag it to the Design Shop point person for security right away.',
            )
        );
    }

    /** Who is performing the change, for the alert body and the state log. */
    private function current_actor() {
        if ( function_exists( 'wp_get_current_user' ) ) {
            $current = wp_get_current_user();
            if ( $current && $current->exists() ) {
                return $current->user_login;
            }
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return 'WP-CLI';
        }
        return 'unknown actor';
    }

    /**
     * True when the change is attributable to someone already trusted here: our own
     * WP-CLI tooling, or a logged-in administrator whose account is more than a day
     * old. A brand-new administrator immediately creating another one is precisely
     * the escalation chain we DO want to hear about, so the age floor matters.
     */
    private function actor_is_established() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }
        if ( ! function_exists( 'wp_get_current_user' ) ) {
            return false;
        }
        $current = wp_get_current_user();
        // No session at all: self-registration, which is how the campaign's
        // accounts appeared. Always worth an email.
        if ( ! $current || ! $current->exists() ) {
            return false;
        }
        if ( ! in_array( 'administrator', (array) $current->roles, true ) ) {
            return false;
        }
        $registered = strtotime( (string) $current->user_registered );
        return $registered && ( time() - $registered ) > DAY_IN_SECONDS;
    }

    /** Disposable / placeholder domains seen on the campaign's rogue accounts. */
    private static function email_is_throwaway( $email ) {
        $at = strrpos( (string) $email, '@' );
        if ( false === $at ) {
            return true;
        }
        $domain = strtolower( substr( (string) $email, $at + 1 ) );
        $bad    = array(
            'example.com',
            'example.org',
            'example.net',
            'example.invalid',
            'test.com',
            'mailinator.com',
            'tripledown.org',
        );
        if ( in_array( $domain, $bad, true ) ) {
            return true;
        }
        return '.invalid' === substr( $domain, -8 );
    }

    /**
     * Fold a just-seen administrator into the nightly baseline so check_admins()
     * does not re-report it, and keep a short local audit trail of who added whom.
     * The label format must match check_admins() exactly or the diff misses.
     */
    private function remember_admin( $user, $actor ) {
        $state = get_option( self::STATE_OPT, array() );
        if ( ! is_array( $state ) ) {
            $state = array();
        }

        $baseline                          = isset( $state['admin_baseline'] ) ? (array) $state['admin_baseline'] : array();
        $baseline[ (int) $user->ID ]       = $user->user_login . ' <' . $user->user_email . '>';
        $state['admin_baseline']           = $baseline;

        $log   = isset( $state['admin_log'] ) ? (array) $state['admin_log'] : array();
        $log[] = array(
            'at'  => gmdate( 'Y-m-d H:i' ),
            'who' => $user->user_login . ' <' . $user->user_email . '>',
            'by'  => $actor,
        );
        $state['admin_log'] = array_slice( $log, -20 );

        update_option( self::STATE_OPT, $state, false );
    }

    /* ------------------------------------------------------ content engine */

    /**
     * Hourly behaviour scan. See the class docblock for why it exists. Resource contract
     * (fleet-audit/SCANNER-SPEC.md section 6c, every number measured on a Flywheel container):
     * - the engine is loaded HERE, inside try/catch, never at bootstrap, so a broken engine costs
     *   one cron run and never a page view;
     * - at most CONTENT_BUDGET_S seconds per run, resumed from a cursor; recently modified files
     *   go first every run;
     * - nothing above CONTENT_TOKCAP is tokenised, and the engine also refuses when the request
     *   lacks memory headroom, so a huge file degrades to a regex check instead of a memory fatal;
     * - only CRITICAL emails, once per file per day; HIGH is recorded in state for
     *   fw-check-site-v2.sh to show. There is no fleet manifest here to clear dual-use vendor code,
     *   and an alert that fires on clean sites is an alert nobody reads;
     * - this file and the engine are exempt by exact PATH, never by content (an attacker can copy a
     *   string), and the whole plugin directory is verified against the sha256 list the release
     *   workflow ships as includes/release-hashes.txt;
     * - a run that dies (memory, time) leaves in_progress set; the next run counts it, and after two
     *   in a row skips 250 files past the cursor with a smaller tokenize cap, so a poison file
     *   cannot kill every hour forever. A clean finish resets the counter.
     */
    public function run_content_scan( $budget = null ) {
        // Kill switches, because there was no fast one for Bot Shield: a wp-config constant, a
        // per-site setting (wp option patch update ds_toolkit_settings tripwire_content_enabled 0),
        // or a filter. None of them touch the daily IOC check.
        if ( defined( 'DS_TRIPWIRE_CONTENT_OFF' ) && DS_TRIPWIRE_CONTENT_OFF ) { return array(); }
        if ( isset( $this->settings['tripwire_content_enabled'] ) && ! $this->settings['tripwire_content_enabled'] ) { return array(); }
        if ( ! apply_filters( 'ds_tripwire_content_enabled', true ) ) { return array(); }

        $budget = $budget ? (int) $budget : self::CONTENT_BUDGET_S;
        $state  = get_option( self::STATE_OPT, array() );
        if ( ! is_array( $state ) ) { $state = array(); }
        $c = ( isset( $state['content'] ) && is_array( $state['content'] ) ) ? $state['content'] : array();
        $c['last_run'] = time();
        $c['error']    = '';

        $engine = DS_TOOLKIT_PATH . 'includes/ds-scan-engine.php';
        if ( PHP_VERSION_ID < 70400 || ! function_exists( 'token_get_all' ) || ! is_readable( $engine ) ) {
            $c['error'] = 'engine unavailable: php ' . PHP_VERSION . ', tokenizer ' . ( function_exists( 'token_get_all' ) ? 'present' : 'missing' ) . ', file ' . ( is_readable( $engine ) ? 'present' : 'missing' );
            $state['content'] = $c;
            update_option( self::STATE_OPT, $state, false );
            return array();
        }
        try {
            require_once $engine;
        } catch ( \Throwable $e ) {
            $c['error'] = 'engine failed to load: ' . get_class( $e ) . ': ' . substr( $e->getMessage(), 0, 120 );
            $state['content'] = $c;
            update_option( self::STATE_OPT, $state, false );
            return array();
        }
        if ( ! function_exists( 'dsscan_scan_list' ) || ! defined( 'DSSCAN_HIGH_SCORE' ) ) {
            $c['error'] = 'engine loaded but its API is missing';
            $state['content'] = $c;
            update_option( self::STATE_OPT, $state, false );
            return array();
        }
        $c['engine'] = defined( 'DSSCAN_VERSION' ) ? DSSCAN_VERSION : '?';

        // poison-file protection: an unfinished previous run is counted before we start this one
        $poison = ! empty( $c['in_progress'] );
        $inc    = isset( $c['incomplete'] ) ? (int) $c['incomplete'] : 0;
        if ( $poison ) { $inc++; }
        $c['incomplete']  = $inc;
        $c['in_progress'] = 1;
        $state['content'] = $c;
        update_option( self::STATE_OPT, $state, false );

        list( $recent, $rest ) = self::content_candidates( self::web_root() );
        $cursor = isset( $c['cursor'] ) ? (int) $c['cursor'] : 0;
        $tokcap = self::CONTENT_TOKCAP;
        if ( $inc >= 2 ) {
            $recent  = array();
            $cursor += 250;
            $tokcap  = 65536;
        }
        if ( $cursor >= count( $rest ) ) { $cursor = 0; }
        $n_recent = count( $recent );
        $paths    = array_merge( $recent, array_slice( $rest, $cursor ) );

        $self    = array_values( array_filter( array( realpath( __FILE__ ), realpath( $engine ) ) ) );
        $found   = array();
        $skipped = 0;
        // Lists BEFORE the deadline is stamped: a cold remote fetch (4 s cap, then cached for the
        // cycle) must never eat the scan's own budget. Both loaders are exception-proof and fall back
        // to bundled data, so the scan runs identically whether GitHub answered or not. The catches
        // here are belt-and-braces: an empty allow set means "suppress nothing", the loud direction.
        try { $known = self::known_good_md5(); } catch ( \Throwable $e ) { $known = array(); }
        try { $bad   = self::known_bad_md5();  } catch ( \Throwable $e ) { $bad   = self::KNOWN_BAD_MD5; }
        try { $own   = self::own_marker_md5(); } catch ( \Throwable $e ) { $own   = array(); }
        $opts    = array(
            'min'          => DSSCAN_HIGH_SCORE,
            'tokenize_cap' => $tokcap,
            'deadline'     => time() + $budget,
            'self_paths'   => $self,
        );
        try {
            $cleared    = 0;
            $own_copies = 0;
            $stats   = dsscan_scan_list( $paths, $opts, function ( $f ) use ( &$found, &$skipped, &$cleared, &$own_copies, $known, $bad, $own ) {
                if ( ! empty( $f['skipped'] ) ) { $skipped++; return; }
                // a byte-identical COPY of our own engine or of this class, wherever it sits: a staging
                // dir, a backup plugin's snapshot, a manual copy. See own_marker_md5() for why only these two.
                if ( ! empty( $f['md5'] ) && isset( $own[ $f['md5'] ] ) ) { $own_copies++; return; }
                // known-good by HASH: verified vendor and blueprint code, never a path or name match
                if ( ! empty( $f['md5'] ) && isset( $known[ $f['md5'] ] ) ) { $cleared++; return; }
                // vendor-verified by HASH against the plugin's own official manifest. Same equality
                // test, answered by wordpress.org instead of a curated list, so a plugin update no
                // longer mints a false positive. Every failure path leaves the finding standing.
                if ( ! empty( $f['md5'] ) && ! empty( $f['path'] ) && self::vendor_verified( $f['path'], $f['md5'] ) ) { $cleared++; return; }
                // name it if we have identified this exact file before (bundled list + remote deny list)
                if ( ! empty( $f['md5'] ) && isset( $bad[ $f['md5'] ] ) ) {
                    $f['known_bad'] = true; // never a "repeat": HQ must not be asked to silence named malware
                    $f['reasons'] = array_merge(
                        array( 'KNOWN MALWARE: ' . $bad[ $f['md5'] ] ),
                        isset( $f['reasons'] ) ? (array) $f['reasons'] : array()
                    );
                }
                $found[] = $f;
            } );
        } catch ( \Throwable $e ) {
            $stats      = array( 'scanned' => 0, 'stopped_at' => 0, 'elapsed' => 0, 'peak_mb' => 0 );
            $c['error'] = 'scan aborted: ' . get_class( $e ) . ': ' . substr( $e->getMessage(), 0, 120 );
        }

        // where to resume next run: stopped_at indexes $paths (recent first, then rest from cursor)
        if ( null === $stats['stopped_at'] ) {
            $c['cursor']    = 0;
            $c['last_full'] = time();
        } else {
            $stopped     = (int) $stats['stopped_at'];
            $c['cursor'] = ( $stopped > $n_recent ) ? $cursor + ( $stopped - $n_recent ) : $cursor;
        }

        $integrity = self::check_toolkit_integrity();

        // record everything at HIGH and above; email CRITICAL only, once per file per day
        $alerted = isset( $c['alerted'] ) ? (array) $c['alerted'] : array();
        $mail    = array();
        $files   = array(); // per mailed line: the file (path, size, FULL md5) for HQ, or null
        $repeat  = true;    // every mailed line is a file this site has already emailed about (see alert())
        $record  = array();
        foreach ( $found as $f ) {
            $line     = self::content_line( $f );
            $record[] = $f['tier'] . ': ' . $line;
            if ( 'CRIT' === $f['tier'] ) {
                $key = md5( $f['path'] . '|' . $f['md5'] );
                if ( empty( $alerted[ $key ] ) || ( time() - (int) $alerted[ $key ] ) > DAY_IN_SECONDS ) {
                    $mail[]          = $line;
                    $files[]         = array( 'path' => (string) $f['path'], 'size' => (int) @filesize( $f['path'] ), 'md5' => strtolower( (string) $f['md5'] ) );
                    // A repeat only when this exact file was emailed before, is not on the deny list, and its path
                    // cannot be misread in the alert line (whitespace or parentheses could pose as another file).
                    $repeat          = $repeat && ! empty( $alerted[ $key ] ) && empty( $f['known_bad'] ) && ! preg_match( '/[\s()]/', (string) $f['path'] );
                    $alerted[ $key ] = time();
                }
            }
        }
        foreach ( $integrity as $pair ) {
            $record[] = 'CRIT: ' . $pair[1];
            $key      = md5( $pair[1] );
            if ( empty( $alerted[ $key ] ) || ( time() - (int) $alerted[ $key ] ) > DAY_IN_SECONDS ) {
                $mail[]          = $pair[1];
                $files[]         = null;
                $repeat          = false; // toolkit integrity is never HQ's to silence
                $alerted[ $key ] = time();
            }
        }
        arsort( $alerted );
        $c['alerted']     = array_slice( $alerted, 0, 100, true );
        $c['findings']    = array_slice( $record, 0, 50 );
        $c['skipped']     = $skipped;
        // How many findings the known-good hash gate suppressed. Recorded because a scanner that
        // goes quiet has to be able to say why: "cleared=412" is auditable, silence is not.
        $c['cleared']     = $cleared;
        $c['own_copies']  = isset( $own_copies ) ? $own_copies : 0;
        $c['known_good']  = count( $known );
        // observable, so "the noise went away" can be checked instead of assumed
        $c['vendor']      = self::$vendor_stats;
        // Remote list health for this run. A scan that suppressed or named more than the bundle can
        // say where that came from, and a dead endpoint shows up here instead of silently leaving the
        // bundle in charge. Read it with: wp option get ds_tripwire_state --format=json (content.remote)
        $c['remote']      = self::$remote_status;
        $c['stats']       = array(
            'scanned' => (int) $stats['scanned'],
            'elapsed' => $stats['elapsed'],
            'peak_mb' => $stats['peak_mb'],
            'recent'  => $n_recent,
            'total'   => $n_recent + count( $rest ),
            'budget'  => $budget,
        );
        $c['in_progress'] = 0;
        $c['incomplete']  = ( '' === $c['error'] ) ? 0 : $inc + 1;
        $state['content'] = $c;
        update_option( self::STATE_OPT, $state, false );

        if ( $mail ) {
            $this->alert( 'CRITICAL', array_map( function ( $l ) { return '[CRITICAL] ' . $l; }, $mail ), $files, $repeat );
        }
        return $found;
    }

    /**
     * md5 of the two files that carry the campaign markers ON PURPOSE: the running engine and this
     * class. Both are exempt in place by exact path (self_paths). This extends that exemption to
     * byte-identical COPIES of them anywhere under the web root, by hash, never by name or folder.
     *
     * Why: the WP Engine fleet installer staged each release in wp-content/plugins/.dstk-new.<PID>/,
     * the content scan read that copy of its own engine mid-install, and 71 sites mailed a false
     * CRITICAL during the 1.10.16 push (2026-09-28). Any backup plugin or manual copy does the same.
     *
     * Deliberately NOT every toolkit file: hashing the whole running plugin would also clear a
     * toolkit file an attacker had modified in place. These two are already exempt in place, so
     * covering their copies adds no blind spot. A copy from a DIFFERENT release (hash differs) still
     * scores; the installer now stages in wp-content/upgrade/, which the walk skips.
     */
    private static function own_marker_md5() {
        $set = array();
        foreach ( array( __FILE__, DS_TOOLKIT_PATH . 'includes/ds-scan-engine.php' ) as $p ) {
            $m = is_readable( $p ) ? @md5_file( $p ) : false;
            // never the empty-file hash: a 0-byte file must not inherit an exemption
            if ( $m && 'd41d8cd98f00b204e9800998ecf8427e' !== $m ) { $set[ $m ] = true; }
        }
        return $set;
    }

    /** Manual entry point for a canary or a support session: wp eval 'DS_Tripwire::content_scan_now( 60 );' */
    public static function content_scan_now( $budget = 60 ) {
        $tw = new self( get_option( 'ds_toolkit_settings', array() ) );
        return $tw->run_content_scan( (int) $budget );
    }

    /**
     * Candidate files, split into [recent, rest]: files modified within CONTENT_RECENT_S (newest
     * first, capped) and everything else sorted by path so a cursor into it is stable between runs.
     * Scanned anywhere: PHP-family, .inc, .html. Scanned whatever the extension: web root depth 1,
     * uploads (minus page-builder cache folders) and mu-plugins. Symlinked directories are not
     * descended: on Flywheel wp-admin and wp-includes point at the root-owned platform core.
     */
    public static function content_candidates( $root ) {
        $recent = array();
        $rest   = array();
        $seen   = 0;
        $now    = time();
        $root   = untrailingslashit( $root );
        $up      = function_exists( 'wp_get_upload_dir' ) ? wp_get_upload_dir() : wp_upload_dir();
        $uploads = isset( $up['basedir'] ) ? untrailingslashit( $up['basedir'] ) : WP_CONTENT_DIR . '/uploads';
        $mu      = untrailingslashit( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' );
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ( $it as $f ) {
                if ( ++$seen > self::CONTENT_WALK_CAP ) { break; }
                $p = $f->getPathname();
                // wp-content/upgrade-temp-backup/ is WordPress's own plugin-update ROLLBACK store and
                // wp-content/upgrade/ its extract scratch: neither is ever executed by WordPress. They
                // also hold a copy of THIS PLUGIN after every self-update, and because the engine's
                // self-exemption is by absolute path, that copy was scored CRITICAL (915 on
                // ds-scan-engine.php, 140 on class-ds-tripwire.php) for carrying the campaign markers
                // it hunts for. Seen on somerssports.org 2026-09-17 17:15 and oklahomabasketballacademy.com
                // 5 times in one evening; it would have fired on every fleet site after each update.
                if ( false !== strpos( $p, '/.quarantine' ) || false !== strpos( $p, '/.sucuriquarantine/' ) || false !== strpos( $p, '/node_modules/' )
                    || false !== strpos( $p, '/upgrade-temp-backup/' ) || false !== strpos( $p, '/wp-content/upgrade/' ) ) { continue; }
                if ( ! $f->isFile() ) { continue; }
                $depth1     = ( dirname( $p ) === $root );
                $in_uploads = ( 0 === strpos( $p, $uploads . '/' ) );
                $in_mu      = ( 0 === strpos( $p, $mu . '/' ) );
                if ( ! preg_match( self::CONTENT_EXT, $p ) ) {
                    if ( ! $depth1 && ! $in_uploads && ! $in_mu ) { continue; }
                    if ( $in_uploads && false !== strpos( $p, '/cache/' ) ) { continue; }
                }
                $mt = (int) @filemtime( $p );
                if ( $now - $mt < self::CONTENT_RECENT_S ) { $recent[ $p ] = $mt; } else { $rest[] = $p; }
            }
        } catch ( \Throwable $e ) { /* unreadable subtree: scan what we have */ }
        arsort( $recent );
        $recent = array_slice( array_keys( $recent ), 0, self::CONTENT_RECENT_MAX );
        sort( $rest, SORT_STRING );
        return array( $recent, $rest );
    }

    /**
     * Our own plugin directory against the sha256 list the release workflow ships as
     * includes/release-hashes.txt: a PHP file not in the list is a foreign file inside DS Toolkit
     * (the akismet-husk trick aimed at us), and a listed file whose hash differs has been modified
     * since release. This is also what backs the engine's path-based self-exemption. No list (a
     * development checkout) means the check is skipped, never an alert.
     */
    public static function check_toolkit_integrity() {
        $out  = array();
        $list = DS_TOOLKIT_PATH . 'includes/release-hashes.txt';
        if ( ! is_readable( $list ) ) { return $out; }
        $want = array();
        foreach ( (array) file( $list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
            if ( preg_match( '/^([0-9a-f]{64})\s+\*?(?:\.\/)?(.+)$/', trim( (string) $line ), $m ) ) { $want[ $m[2] ] = $m[1]; }
        }
        if ( ! $want ) { return $out; }
        $base = untrailingslashit( DS_TOOLKIT_PATH );
        $seen = 0;
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ( $it as $f ) {
                if ( ++$seen > 5000 ) { break; }
                if ( ! $f->isFile() || ! preg_match( '/\.(php|phtml|php[3-8]|phar|pht|inc)$/i', $f->getFilename() ) ) { continue; }
                $rel = ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( $base ) ) ), '/' );
                if ( ! isset( $want[ $rel ] ) ) {
                    $out[] = array( 'CRIT', "A PHP file that is not part of the DS Toolkit release sits inside the plugin folder: {$rel} (" . (int) $f->getSize() . ' bytes). Nothing adds files there except an update; treat it as planted.' );
                    continue;
                }
                if ( @hash_file( 'sha256', $f->getPathname() ) !== $want[ $rel ] ) {
                    $out[] = array( 'CRIT', "A DS Toolkit file has been MODIFIED since its release: {$rel}. Reinstall the toolkit from GitHub and find what wrote it." );
                }
            }
        } catch ( \Throwable $e ) { /* unreadable: report what we have */ }
        return $out;
    }

    /** One alert / state line for a content finding: path, size, hash, score, the top two reasons. */
    private static function content_line( $f ) {
        $size = (int) @filesize( $f['path'] );
        $why  = isset( $f['reasons'] ) ? array_slice( (array) $f['reasons'], 0, 2 ) : array();
        return 'Web shell by behaviour: ' . $f['path'] . ' (' . $size . ' bytes, md5 ' . substr( (string) $f['md5'], 0, 8 ) . ', score ' . (int) $f['score'] . '). '
            . implode(' ', $why )
            . ' Quarantine it (move, never delete), then check both theme functions.php files and mu-plugins for a loader that re-creates it.';
    }

    /* -------------------------------------------------------------- output */

    /**
     * $tier is CRITICAL or HIGH; it leads the subject so the inbox sorts itself.
     *
     * $files runs parallel to $lines: the file each line names (path, size, full md5) or null. They go to HQ with the
     * alert so HQ matches on the full hash.
     *
     * $repeat is true only from the content scan, when EVERY line is a file this site has already emailed about
     * before. Only then may the email be skipped, and only when Design Shop HQ answers, inside this same run, with a
     * signature over this run's own nonce that the alert repeats a false alarm a person already closed for this site
     * (DS_HQ_Link::ask_mail). Any other answer, no answer, a timeout or an error sends the email now. Nothing is held
     * for later, so nothing can be lost; a new file, an admin alert or a toolkit integrity alert always emails.
     */
    private function alert( $tier, array $lines, array $files = array(), $repeat = false ) {
        $asked = array( false, false ); // [ HQ has this alert, HQ signed a skip ]
        if ( $repeat && class_exists( 'DS_HQ_Link' ) && ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
            try {
                $asked = DS_HQ_Link::ask_mail( $tier, $lines, $files );
            } catch ( \Throwable $e ) {
                $asked = array( false, false ); // any failure sends the email
            }
        }
        if ( ! empty( $asked[1] ) ) {
            // ds_tripwire_last_notify stays the last email that left; skips are recorded on their own.
            update_option( 'ds_tripwire_last_skip', array(
                'time'  => gmdate( 'c' ),
                'tier'  => ( 'CRITICAL' === $tier ) ? 'CRITICAL' : 'HIGH',
                'lines' => count( $lines ),
                'why'   => (string) ( $asked[2] ?? '' ),
            ), false );
            return;
        }
        // Comma-separated list supported; invalid entries are dropped. The
        // fallback is the shared Design Shop inbox so alerts always reach a
        // person who can route them.
        $raw = (string) ( $this->settings['tripwire_alert_email'] ?? '' );
        $to  = array_filter( array_map( 'trim', explode( ',', $raw ) ), 'is_email' );
        if ( ! $to ) {
            $to = array( 'design@leagueapps.com' );
        }
        $to   = apply_filters( 'ds_tripwire_alert_email', $to );
        $site = home_url();
        $host = wp_parse_url( $site, PHP_URL_HOST );
        $tier = ( 'CRITICAL' === $tier ) ? 'CRITICAL' : 'HIGH';
        $body = "Hi team,\n\n"
              . "DS Tripwire is the small security watchdog that keeps watch on every Design Shop site. "
              . "It just noticed something on this site that looks like the recent malware attack:\n\n"
              . "Site:     {$site}\n"
              . "Severity: {$tier}\n"
              . 'Checked:  ' . gmdate( 'Y-m-d H:i' ) . " UTC\n\n"
              . "What it found:\n"
              . "- " . implode( "\n\n- ", $lines ) . "\n\n"
              . ( 'CRITICAL' === $tier
                  ? "What this means: a web shell or an active doorway is on the site right now. This is the thing that re-infects a site after a cleanup, so it should be actioned today, not reviewed.\n\n"
                  : "What this means: it might be a false alarm, but with the current attack campaign it should be looked at today.\n\n" )
              . "What to do:\n"
              . "1. Do not delete anything yourself — this malware repairs itself if only one copy is removed.\n"
              . "2. Forward this email to the Design Shop point person for security, who has the removal playbook, so it can be actioned right away.\n"
              . "3. If the site looks fine to visitors, that is normal — this malware hides from people "
              . "and only shows itself to search engines.\n\n"
              . "— DS Tripwire (part of the DS Toolkit plugin)\n";
        $sent = wp_mail( $to, '[DS Tripwire] ' . $tier . ' — ' . $host, $body );
        // Record what the handoff did. Until now the return was discarded, so no site knew whether
        // its own alert ever left, and "found something but the mail failed" was unobservable.
        // NOTE: true only means PHPMailer accepted it, never that it was delivered.
        update_option( 'ds_tripwire_last_notify', array(
            'time'      => gmdate( 'c' ),
            'tier'      => $tier,
            'to'        => implode( ',', (array) $to ),
            'accepted'  => (bool) $sent,
        ), false );
        // The same alert to Design Shop HQ (DS_HQ_Link), unless ask_mail() already delivered it. Email stays primary.
        if ( empty( $asked[0] ) ) {
            do_action( 'ds_tripwire_alert', $tier, $lines, $files );
        }
    }

    /**
     * Send one token email so the fleet can be mapped for mail reachability, and record the
     * outcome locally so a read-only collector can pair "what wp_mail said" with "what arrived".
     *
     * Driven by two options so nothing is hardcoded and the hook is inert until asked:
     *   ds_mailcheck_to     recipient (must pass is_email)
     *   ds_mailcheck_token  unique per site, so an arrival maps back to its sender
     *
     * Fire it in the web context:
     *   wp option update ds_mailcheck_to "you@example.com"
     *   wp option update ds_mailcheck_token "MT<unique>"
     *   wp cron event schedule ds_tripwire_mailcheck now
     *   curl -s "https://<domain>/wp-cron.php?doing_wp_cron"
     */
    public function run_mailcheck() {
        $to  = (string) get_option( 'ds_mailcheck_to', '' );
        $tok = (string) get_option( 'ds_mailcheck_token', '' );
        if ( '' === $to || '' === $tok || ! is_email( $to ) ) {
            return;
        }
        // Claim the token atomically. WordPress cron has a race: two concurrent cron processes can
        // both pick up the same event before either unschedules it, so the probe sent twice within
        // two seconds on 495lacrosse.com and 4leaflax.org. add_option() fails if the row exists
        // (option_name is UNIQUE), so exactly one process proceeds. A result-existence check cannot
        // close this, because in a real race neither process has written a result yet.
        if ( ! add_option( 'ds_mailcheck_claim_' . $tok, time(), '', false ) ) {
            return;
        }
        $err = '';
        $cap = function ( $e ) use ( &$err ) {
            if ( is_wp_error( $e ) ) { $err = $e->get_error_message(); }
        };
        add_action( 'wp_mail_failed', $cap );
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $ok   = wp_mail(
            $to,
            '[DS MAILTEST] ' . $host . ' ' . $tok,
            "site: " . home_url() . "\ntoken: " . $tok . "\nsent_at: " . gmdate( 'c' ) . "\n"
        );
        remove_action( 'wp_mail_failed', $cap );
        update_option( 'ds_mailcheck_result', array(
            'token'    => $tok,
            'to'       => $to,
            'accepted' => (bool) $ok,
            'error'    => $err,
            'time'     => gmdate( 'c' ),
            'context'  => ( defined( 'WP_CLI' ) && WP_CLI ) ? 'cli' : ( wp_doing_cron() ? 'cron' : 'web' ),
        ), false );
    }
}
