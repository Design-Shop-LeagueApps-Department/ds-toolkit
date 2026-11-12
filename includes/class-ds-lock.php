<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * One holder at a time, across requests and across containers.
 *
 * A transient is not a lock: two requests can both read "free" and both write "taken", and on WP Engine each
 * container has its own object cache, so they do not even see each other's write. This uses the database's
 * own atomicity instead: INSERT IGNORE of an options row either creates the row (this request holds the lock)
 * or changes nothing (someone else does). The row holds the time it was taken, so a run that died is taken
 * over after $ttl seconds, by a compare-and-swap UPDATE that only one waiting request can win.
 *
 * The same code lived, copied by hand, in DS_HQ_Link, DS_HQ_Update and DS_Roster_Data. New code uses this.
 */
class DS_Lock {

	/**
	 * @param string $name option name of the lock row (register it in DS_Toolkit_Lifecycle::OPTIONS)
	 * @param int    $ttl  seconds after which a lock whose holder died may be taken over
	 * @return bool  true = this request holds the lock now and must release() it
	 */
	public static function acquire( $name, $ttl ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return true; // no database (a unit test): nothing to race with
		}
		$now = time();
		if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) $now ) ) ) {
			return true;
		}
		$held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( $now - $held > (int) $ttl ) { // its holder died: one of the waiting requests takes it over
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) $now, $name, (string) $held ) );
		}
		return false;
	}

	public static function release( $name ) {
		global $wpdb;
		if ( is_object( $wpdb ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( $name, 'options' );
			}
		}
	}

	/**
	 * An option read from the database, not from the object cache, for a value that is about to be changed and
	 * saved again. On WP Engine the object cache is per container (memcached on a local socket), so a cached copy
	 * can be older than what another container saved; writing it back would undo that save.
	 */
	public static function fresh_option( $name, $default = false ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $name, 'options' );
			$no = function_exists( 'wp_cache_get' ) ? wp_cache_get( 'notoptions', 'options' ) : false;
			if ( is_array( $no ) && isset( $no[ $name ] ) ) {
				unset( $no[ $name ] );
				wp_cache_set( 'notoptions', $no, 'options' );
			}
		}
		return get_option( $name, $default );
	}
}
