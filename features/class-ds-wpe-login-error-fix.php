<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Stops a failed login check from becoming a "critical error" on WP Engine.
 *
 * WP Engine's platform plugin (mu-plugins/wpengine-common/plugin.php) hooks wpe_refresh_user() on
 * wp_authenticate_user at priority 10 when WP_CACHE is on. It reads ->user_login off whatever it is given
 * and returns get_user_by(), so when an earlier callback has already refused the login with a WP_Error
 * (Defender's CAPTCHA check runs at priority 8), the error becomes `false`. Core then reads ->user_pass off
 * `false` and wp_check_password() throws a TypeError in hash_equals(): the visitor sees "There has been a
 * critical error on this website" instead of "Turnstile verification failed". It only happens for a real
 * username (an unknown one is refused before this filter runs), so it reaches the partner, not a bot.
 * Seen on thestjamescamps.com, 2026-11-17.
 *
 * Fix: at priority 9, when the login is already refused, take wpe_refresh_user off the hook for this
 * request so the WP_Error reaches core untouched. A successful check is left alone, so WP Engine's cache
 * refresh still runs on every login that can succeed. Inert off WP Engine (the function is not hooked).
 */
class DS_WPE_Login_Error_Fix {

    public function __construct( $settings = array() ) {}

    public function init() {
        add_filter( 'wp_authenticate_user', array( $this, 'keep_error' ), 9 );
    }

    /**
     * @param WP_User|WP_Error $user
     * @return WP_User|WP_Error unchanged
     */
    public function keep_error( $user ) {
        if ( is_wp_error( $user ) ) {
            remove_filter( 'wp_authenticate_user', 'wpe_refresh_user' );
        }
        return $user;
    }
}
