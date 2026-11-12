<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tripwire: the instant alert when an administrator account appears or a role changes.
 *
 * One part of DS_Tripwire, which was a single 1,960-line class until the 2026-11 audit. The methods were
 * moved here as they were: a trait, so they are still methods of the feature (same visibility, same $this).
 * Every detector, the constants, the two scans and the sending of an alert stay in class-ds-tripwire.php, which
 * is also the one file Tripwire exempts from its own content scan: nothing that holds a detection pattern may
 * move out of it.
 */
trait DS_Tripwire_Admins {

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

        /* 1.13.1: created by Design Shop staff (an administrator with an @leagueapps.com address, the
           1.12.0 rule) is our own onboarding, so it is recorded for HQ and not mailed, on any site. The
           producer is usually new on the site, so actor_is_established() above cannot help. Every such
           alert on HQ to 2026-10-29 (6) was our own onboarding (lpacheco adding partner admins on
           futureswpl, goldstarlax, paplaymakers, teamhoustonbb). HQ closes the record itself when the line below names
           a @leagueapps.com creator, so it is named by email, not login. A throwaway address still mails. */
        $current = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
        $staff   = class_exists( 'DS_Toolkit' ) && DS_Toolkit::is_leagueapps_staff( $current )
            && ! self::email_is_throwaway( $user->user_email );
        if ( $staff ) {
            $actor = $current->user_email;
        }

        /* On a pre-launch build the producer is often newly added themselves, so actor_is_established()
           above cannot help: goldstarlax raised three of these in 70 seconds on 2026-10-15 while
           onboarding the partner's own staff. Record it for HQ, do not mail it. */
        $this->alert(
            'HIGH',
            array(
                "A new administrator just appeared on this site: {$user->user_login} <{$user->user_email}> ({$how}).",
                "Created by: {$actor}.",
                'If nobody on the team did this, the site may be compromised. In the recent attack a rogue admin account appeared two hours before the malware. Flag it to the Design Shop point person for security right away.',
            ),
            array(),
            false,
            ! self::prelaunch() && ! $staff
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
        $state = self::fresh_state();

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
}
