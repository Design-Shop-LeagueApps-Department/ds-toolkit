<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The shared way to read another service's JSON, and to keep the last good answer.
 *
 * Before the 2026-11 audit each data class (Programs, Calendar, Roster, Table) carried its own copy of the
 * same three things: a GET wrapper with its own idea of what to retry, a lock, and a "last good copy". This
 * class is the one version, adopted one data class at a time (DS_Programs_Data first). The lock is DS_Lock.
 *
 * Rules the GET follows, each paid for once already:
 *   - Only a failure on OUR side of the wire is retried (DNS, TLS, timeout, a body that is not JSON). An
 *     answer from the service (403, 404, 429, 5xx) is final for this call: retrying a struggling API is how
 *     an outage gets worse.
 *   - A 429 carries Retry-After up to the caller, which decides how long to stay away.
 *   - A deadline, when given, is absolute: no attempt starts after it and no single wait runs past it, so a
 *     job fetching several things cannot overrun its own budget on the last one.
 *
 * No hooks, no options of its own, no output: safe to load anywhere, and in a stub test.
 */
if ( ! class_exists( 'DS_Data_Client' ) ) :
class DS_Data_Client {

	/**
	 * GET a URL that answers with JSON.
	 *
	 * @param string $url
	 * @param array  $args timeout (seconds, default 8), attempts (default 3), deadline (unix time as float,
	 *                     0 = none), agent (short name for the User-Agent, e.g. "programs") or user_agent (the
	 *                     whole string, for a caller that must not name the site), headers (array), pause
	 *                     (microseconds between attempts, multiplied by the attempt number; default 300000),
	 *                     http (more wp_remote_get arguments: sslverify, redirection, reject_unsafe_urls).
	 * @return array ok (bool), code (int, 0 = no answer), tries (int), json (array|null), body (string, whatever
	 *               the service sent), retry_after (int seconds, when the service asked for a wait), kind ('' |
	 *               connection | json | key | missing | throttled | http | deadline), error (string), wp_error
	 *               (the transport's own WP_Error on a connection failure).
	 */
	public static function get_json( $url, array $args = array() ) {
		$timeout  = max( 1, (int) ( $args['timeout'] ?? 8 ) );
		$attempts = max( 1, (int) ( $args['attempts'] ?? 3 ) );
		$deadline = (float) ( $args['deadline'] ?? 0 );
		$pause    = (int) ( $args['pause'] ?? 300000 );
		$out      = array( 'ok' => false, 'code' => 0, 'tries' => 0, 'json' => null, 'body' => '', 'retry_after' => 0, 'kind' => 'connection', 'error' => 'no attempt made', 'wp_error' => null );
		$agent    = isset( $args['user_agent'] ) ? (string) $args['user_agent'] : self::user_agent( (string) ( $args['agent'] ?? 'data' ) );

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			$wait = $timeout;
			if ( $deadline > 0 ) {
				$left = $deadline - microtime( true );
				if ( $left < 1 ) {
					if ( 0 === $out['tries'] ) {
						$out['kind']  = 'deadline';
						$out['error'] = 'out of time before the request could start';
					}
					return $out;
				}
				$wait = (int) max( 1, min( $timeout, floor( $left ) ) );
			}
			$out['tries'] = $attempt;
			$res = wp_remote_get( $url, array_merge( (array) ( $args['http'] ?? array() ), array(
				'timeout'    => $wait,
				'user-agent' => $agent,
				'headers'    => array_merge( array( 'Accept' => 'application/json' ), (array) ( $args['headers'] ?? array() ) ),
			) ) );

			if ( is_wp_error( $res ) ) {
				// DNS, TLS, timeout: a blip on our side, worth another try.
				$out['kind']     = 'connection';
				$out['error']    = $res->get_error_message();
				$out['wp_error'] = $res;
			} else {
				$code        = (int) wp_remote_retrieve_response_code( $res );
				$out['code'] = $code;
				$out['body'] = (string) wp_remote_retrieve_body( $res );
				if ( 200 === $code ) {
					$json = json_decode( $out['body'], true );
					if ( is_array( $json ) ) {
						return array_merge( $out, array( 'ok' => true, 'json' => $json, 'kind' => '', 'error' => '', 'wp_error' => null ) );
					}
					$out['kind']     = 'json';
					$out['error']    = 'malformed JSON';
					$out['wp_error'] = null;
				} else {
					$kinds           = array( 403 => 'key', 404 => 'missing', 429 => 'throttled' );
					$out['kind']     = $kinds[ $code ] ?? 'http';
					$out['error']    = 'HTTP ' . $code;
					$out['wp_error'] = null;
					// Sent with a 429, and sometimes with a 503: either way the service is asking for a wait.
					$out['retry_after'] = max( 0, (int) wp_remote_retrieve_header( $res, 'retry-after' ) );
					return $out; // the service answered: final for this call
				}
			}
			if ( $attempt < $attempts && $pause > 0 ) {
				usleep( $pause * $attempt );
			}
		}
		return $out;
	}

	/** "ds-toolkit-<name>/<version> (+<site>)": the service's logs can tell which block of which site called. */
	public static function user_agent( $name ) {
		return 'ds-toolkit-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) ) . '/' . ( defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '0' ) . ' (+' . home_url( '/' ) . ')';
	}

	/**
	 * Keep an answer as the last good copy: its own option, not autoloaded, so it is read only by the block
	 * that needs it and an object cache cannot drop it the way it drops a transient. Register the option's
	 * prefix in DS_Toolkit_Lifecycle::OPTION_PREFIXES.
	 *
	 * @param string $name option name
	 * @param array  $pack what to keep; `fetched` (unix time) is added when missing
	 */
	public static function keep( $name, array $pack ) {
		if ( empty( $pack['fetched'] ) ) {
			$pack['fetched'] = time();
		}
		update_option( $name, $pack, false );
	}

	/** The last good copy, or null when there is none or it is older than $max_age seconds (0 = any age). */
	public static function kept( $name, $max_age = 0 ) {
		$pack = get_option( $name, null );
		if ( ! is_array( $pack ) ) {
			return null;
		}
		if ( $max_age > 0 && time() - (int) ( $pack['fetched'] ?? 0 ) > $max_age ) {
			return null;
		}
		return $pack;
	}
}
endif;
