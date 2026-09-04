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
 * Design constraints:
 * - Cheap: no full-site scans. A daily cron reads one directory listing, a
 *   plugin-folder listing, and the tails of four files. Milliseconds.
 * - Zero frontend cost: scheduling and hooks are registered in admin/cron/CLI
 *   contexts; ordinary visits never pay for it.
 * - Clone-compatible / fail open: first run seeds baselines silently; a fresh
 *   clone of the blueprint never emails anyone. Hard IOC patterns (the wdg
 *   family) alert regardless of baseline.
 * - No dependencies: wp_mail only, one option row of state.
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

    /** Self-heal markers; any hit in a scanned tail is a confirmed infection. */
    const MARKERS = array( 'WDG-CORE-' . 'START', '$wdg' . '_k', '$co' . 'ki' );

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function init() {
        // The cron callback must be bound on every request type so a due event
        // can always fire; everything below it is admin/cron/CLI-only.
        add_action( self::CRON_HOOK, array( $this, 'run_checks' ) );

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
        }
    }

    /* ---------------------------------------------------------------- cron */

    public function run_checks() {
        $state    = get_option( self::STATE_OPT, array() );
        $seeded   = ! empty( $state['seeded'] );
        $findings = array();

        $findings = array_merge(
            $findings,
            $this->check_mu_plugins( $state, $seeded ),
            $this->check_plugin_dirs(),
            $this->check_marker_tails(),
            $this->check_webroot(),
            $this->check_admins( $state, $seeded )
        );

        $state['seeded']   = 1;
        $state['last_run'] = time();
        $state['last_findings'] = $findings;
        update_option( self::STATE_OPT, $state, false );

        if ( $findings && $seeded ) {
            $this->alert( "Daily tripwire found indicators of compromise", $findings );
        }
        return $findings;
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
                if ( 'ds-origin-guard.php' === $base ) continue; // toolkit-managed
                $out[] = "A new PHP file appeared in mu-plugins since yesterday: {$base} ({$size} bytes). Nobody should be adding files there.";
            }
        }
        $state['mu_baseline'] = $current;
        return array_unique( $out );
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
        $targets = array(
            get_stylesheet_directory() . '/functions.php',
            get_template_directory() . '/functions.php',
            ABSPATH . 'index.php',
        );
        // wp-config can live in ABSPATH or one level up.
        foreach ( array( ABSPATH . 'wp-config.php', dirname( ABSPATH ) . '/wp-config.php' ) as $cfg ) {
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

    /** Rogue error.php dropped in the web root (campaign IOC). */
    private function check_webroot() {
        $f = ABSPATH . 'error.php';
        if ( file_exists( $f ) ) {
            return array( 'An unexpected error.php file sits in the site root (' . (int) @filesize( $f ) . ' bytes). The recent attack dropped files with this name.' );
        }
        return array();
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

        if ( $this->actor_is_established() && ! self::email_is_throwaway( $user->user_email ) ) {
            return;
        }

        $this->alert(
            'New administrator: ' . $user->user_login,
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

    /* -------------------------------------------------------------- output */

    private function alert( $subject, array $lines ) {
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
        $body = "Hi team,\n\n"
              . "DS Tripwire is the small security watchdog that checks every Design Shop site once a day. "
              . "It just noticed something on this site that looks like the recent malware attack:\n\n"
              . "Site:    {$site}\n"
              . 'Checked: ' . gmdate( 'Y-m-d H:i' ) . " UTC\n\n"
              . "What it found:\n"
              . "- " . implode( "\n\n- ", $lines ) . "\n\n"
              . "What this means: it might be a false alarm, but with the current attack campaign it "
              . "should be looked at today.\n\n"
              . "What to do:\n"
              . "1. Do not delete anything yourself — this malware repairs itself if only one copy is removed.\n"
              . "2. Forward this email to the Design Shop point person for security, who has the removal playbook, so it can be actioned right away.\n"
              . "3. If the site looks fine to visitors, that is normal — this malware hides from people "
              . "and only shows itself to search engines.\n\n"
              . "— DS Tripwire (part of the DS Toolkit plugin)\n";
        wp_mail( $to, '[DS Tripwire] Security alert — ' . $host, $body );
    }
}
