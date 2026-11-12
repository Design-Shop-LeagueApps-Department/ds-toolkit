<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Is the toolkit's background work healthy on this site? One place that reads what the features already record
 * (no network call, no scan, nothing written) and gives it back two ways:
 *
 *  - rows()      for the staff-only Status card on Settings > DS Toolkit > Features
 *  - hq_fields() flat values added to the "tripwire" part of the Design Shop HQ check-in
 *
 * Before the 2026-11 audit none of this was visible anywhere: a Tripwire that stopped running, a dead list
 * endpoint, an alert mail the server refused or an update check that kept failing all looked like a healthy
 * site, and Bot Shield printed "0 evaluated" on a host where it cannot count at all.
 *
 * hq_fields() must stay FLAT SCALARS: HQ keeps every scalar it finds in "tripwire" (cut to 200 characters) and
 * drops arrays, so a new value shows up there without an HQ release, and an old HQ ignores it.
 */
class DS_Status {

	/** Result of the last look at GitHub for a newer release: array( time, ok, tag ). Not autoloaded. */
	const UPDATE_CHECK_OPT = 'ds_toolkit_update_check';

	/** The daily run is late after this long, the hourly scan after this long. */
	const DAILY_LATE_S  = 129600; // 36 h
	const HOURLY_LATE_S = 10800;  // 3 h

	/** Called by the updater each time it really asks GitHub (not on a cached answer). */
	public static function note_update_check( $ok, $tag = '' ) {
		update_option( self::UPDATE_CHECK_OPT, array(
			'time' => time(),
			'ok'   => $ok ? 1 : 0,
			'tag'  => substr( (string) $tag, 0, 40 ),
		), false );
	}

	private static function opt( $name ) {
		$v = get_option( $name, array() );
		return is_array( $v ) ? $v : array();
	}

	/** A setting that is on unless someone saved it off (Tripwire and its parts default to on). */
	private static function on( array $opts, $key ) {
		return ! isset( $opts[ $key ] ) || ! empty( $opts[ $key ] );
	}

	/** Bot Shield's memory backend: 'object-cache', 'apcu', 'none', or 'off' when the feature is off. */
	public static function bot_shield_backend( array $opts ) {
		if ( empty( $opts['bot_shield_enabled'] ) ) {
			return 'off';
		}
		if ( ! class_exists( 'DS_Bot_Shield' ) && defined( 'DS_TOOLKIT_PATH' ) ) {
			require_once DS_TOOLKIT_PATH . 'features/class-ds-bot-shield.php';
		}
		if ( ! class_exists( 'DS_Bot_Shield' ) ) {
			return 'none';
		}
		$name = ( new DS_Bot_Shield( $opts ) )->backend_name();
		return '' !== $name ? $name : 'none';
	}

	/** The raw facts, in one array. Times are Unix seconds, 0 = never. */
	public static function collect( array $opts ) {
		$st     = self::opt( 'ds_tripwire_state' );
		$c      = isset( $st['content'] ) && is_array( $st['content'] ) ? $st['content'] : array();
		$stats  = isset( $c['stats'] ) && is_array( $c['stats'] ) ? $c['stats'] : array();
		$remote = isset( $c['remote'] ) && is_array( $c['remote'] ) ? $c['remote'] : array();
		$mail   = self::opt( 'ds_tripwire_last_notify' );
		$skip   = self::opt( 'ds_tripwire_last_skip' );
		$upd    = self::opt( self::UPDATE_CHECK_OPT );
		$lists  = array();
		foreach ( array( 'allow', 'deny' ) as $which ) {
			if ( isset( $remote[ $which ] ) && is_array( $remote[ $which ] ) ) {
				$lists[ $which ] = array(
					'status' => (string) ( $remote[ $which ]['status'] ?? '' ),
					'count'  => (int) ( $remote[ $which ]['count'] ?? 0 ),
				);
			}
		}
		return array(
			'tripwire_on'   => self::on( $opts, 'tripwire_enabled' ),
			'content_on'    => self::on( $opts, 'tripwire_enabled' ) && self::on( $opts, 'tripwire_content_enabled' ),
			'daily_last'    => (int) ( $st['last_run'] ?? 0 ),
			'hourly_last'   => (int) ( $c['last_run'] ?? 0 ),
			'hourly_error'  => (string) ( $c['error'] ?? '' ),
			'scanned'       => (int) ( $stats['scanned'] ?? 0 ),
			'total'         => (int) ( $stats['total'] ?? 0 ),
			'recent'        => (int) ( $stats['recent'] ?? 0 ),
			'run_s'         => (float) ( $stats['run_s'] ?? 0 ),
			'ceiling'       => (float) ( $stats['ceiling'] ?? 0 ),
			'peak_mb'       => (float) ( $stats['peak_mb'] ?? 0 ),
			'lists'         => $lists,
			'mail_time'     => ! empty( $mail['time'] ) ? (int) strtotime( (string) $mail['time'] ) : 0,
			'mail_tier'     => (string) ( $mail['tier'] ?? '' ),
			'mail_accepted' => ! empty( $mail['accepted'] ),
			'skip_time'     => ! empty( $skip['time'] ) ? (int) strtotime( (string) $skip['time'] ) : 0,
			'skip_why'      => (string) ( $skip['why'] ?? '' ),
			'update_time'   => (int) ( $upd['time'] ?? 0 ),
			'update_ok'     => ! empty( $upd['ok'] ),
			'update_tag'    => (string) ( $upd['tag'] ?? '' ),
			'bot_backend'   => self::bot_shield_backend( $opts ),
		);
	}

	/** True when a remote list could not be fetched or checked on the last scan. */
	private static function list_failed( $status ) {
		return 0 === strpos( $status, 'fail' ) || 0 === strpos( $status, 'exception' ) || 'unavailable' === $status;
	}

	private static function lists_text( array $lists ) {
		$parts = array();
		foreach ( $lists as $which => $l ) {
			$parts[] = $which . ': ' . $l['status'] . ' (' . $l['count'] . ')';
		}
		return implode( '; ', $parts );
	}

	/** Flat scalars for the HQ check-in. Keys are new, so nothing HQ already reads changes meaning. */
	public static function hq_fields( array $opts ) {
		$f = self::collect( $opts );
		return array(
			'content_recent'     => $f['recent'],
			'content_run_s'      => $f['run_s'],
			'content_peak_mb'    => $f['peak_mb'],
			'lists'              => substr( self::lists_text( $f['lists'] ), 0, 200 ),
			'lists_ok'           => (int) ! array_filter( array_column( $f['lists'], 'status' ), array( __CLASS__, 'list_failed' ) ),
			'last_mail_accepted' => $f['mail_time'] ? (int) $f['mail_accepted'] : '',
			'update_checked'     => $f['update_time'],
			'update_check_ok'    => $f['update_time'] ? (int) $f['update_ok'] : '',
			'bot_shield'         => $f['bot_backend'],
		);
	}

	private static function ago( $ts, $now ) {
		return human_time_diff( (int) $ts, (int) $now ) . ' ago';
	}

	/**
	 * The Status card: a list of array( label, state, text ), state one of ok, warn, bad, off.
	 * Plain text only: the view escapes it.
	 */
	public static function rows( array $opts, $now = null ) {
		$now  = null === $now ? time() : (int) $now;
		$f    = self::collect( $opts );
		$rows = array();

		// Tripwire, daily run.
		if ( ! $f['tripwire_on'] ) {
			$rows[] = array( 'Tripwire daily check', 'off', 'Tripwire is off.' );
		} elseif ( ! $f['daily_last'] ) {
			$rows[] = array( 'Tripwire daily check', 'warn', 'Has not run yet. It runs once a day from WP-Cron.' );
		} else {
			$late   = ( $now - $f['daily_last'] ) > self::DAILY_LATE_S;
			$rows[] = array( 'Tripwire daily check', $late ? 'bad' : 'ok', 'Last ran ' . self::ago( $f['daily_last'], $now ) . ( $late ? '. It should run every day: check WP-Cron on this site.' : '.' ) );
		}

		// Tripwire, hourly file scan.
		if ( ! $f['content_on'] ) {
			$rows[] = array( 'Tripwire hourly scan', 'off', $f['tripwire_on'] ? 'The file scan is off.' : 'Tripwire is off.' );
		} elseif ( ! $f['hourly_last'] ) {
			$rows[] = array( 'Tripwire hourly scan', 'warn', 'Has not run yet. It runs every hour from WP-Cron.' );
		} else {
			$late = ( $now - $f['hourly_last'] ) > self::HOURLY_LATE_S;
			$text = 'Last ran ' . self::ago( $f['hourly_last'], $now );
			if ( $f['total'] ) {
				$text .= ': ' . number_format_i18n( $f['scanned'] ) . ' of ' . number_format_i18n( $f['total'] ) . ' files read';
				if ( $f['run_s'] ) {
					$text .= ' in ' . $f['run_s'] . ' s' . ( $f['ceiling'] ? ' (limit ' . $f['ceiling'] . ' s)' : '' );
				}
				if ( $f['peak_mb'] ) {
					$text .= ', ' . $f['peak_mb'] . ' MB peak';
				}
			}
			$text .= '.';
			if ( '' !== $f['hourly_error'] ) {
				$text .= ' Error: ' . $f['hourly_error'];
			} elseif ( $late ) {
				$text .= ' It should run every hour: check WP-Cron on this site.';
			}
			$rows[] = array( 'Tripwire hourly scan', ( '' !== $f['hourly_error'] || $late ) ? 'bad' : 'ok', $text );
		}

		// The allow and deny lists fetched from GitHub.
		if ( $f['content_on'] ) {
			if ( ! $f['lists'] ) {
				$rows[] = array( 'Remote lists', 'warn', 'Not fetched yet: the bundled lists are in use.' );
			} else {
				$failed = (bool) array_filter( array_column( $f['lists'], 'status' ), array( __CLASS__, 'list_failed' ) );
				$rows[] = array( 'Remote lists', $failed ? 'warn' : 'ok', self::lists_text( $f['lists'] ) . ( $failed ? '. The scan still runs on the bundled or last good list.' : '.' ) );
			}
		}

		// The last alert email handed to the mail server.
		if ( $f['tripwire_on'] ) {
			if ( ! $f['mail_time'] ) {
				$text  = 'No alert email has been sent from this site.';
				$state = 'ok';
			} elseif ( $f['mail_accepted'] ) {
				$text  = 'Last alert (' . $f['mail_tier'] . ') ' . self::ago( $f['mail_time'], $now ) . ': accepted by the mail server. Accepted is not the same as delivered.';
				$state = 'ok';
			} else {
				$text  = 'Last alert (' . $f['mail_tier'] . ') ' . self::ago( $f['mail_time'], $now ) . ': the mail server did NOT accept it. Check how this site sends mail.';
				$state = 'bad';
			}
			if ( $f['skip_time'] > $f['mail_time'] ) {
				$text .= ' A later alert was not emailed (' . self::ago( $f['skip_time'], $now ) . ( '' !== $f['skip_why'] ? ', ' . $f['skip_why'] : '' ) . ').';
			}
			$rows[] = array( 'Last alert email', $state, $text );
		}

		// The last look at GitHub for a newer release.
		if ( ! $f['update_time'] ) {
			$rows[] = array( 'Update check', 'warn', 'No check recorded yet. WordPress checks about twice a day.' );
		} elseif ( $f['update_ok'] ) {
			$rows[] = array( 'Update check', 'ok', 'Last checked ' . self::ago( $f['update_time'], $now ) . ( '' !== $f['update_tag'] ? ': latest release ' . $f['update_tag'] : '' ) . '.' );
		} else {
			$rows[] = array( 'Update check', 'bad', 'The last check, ' . self::ago( $f['update_time'], $now ) . ', failed: GitHub did not answer. It is tried again after 15 minutes.' );
		}

		// Bot Shield needs a memory backend to count and to rate-limit.
		if ( 'off' === $f['bot_backend'] ) {
			$rows[] = array( 'Bot Shield', 'off', 'Bot Shield is off.' );
		} elseif ( 'none' === $f['bot_backend'] ) {
			$rows[] = array( 'Bot Shield', 'warn', 'No cache backend on this host (no persistent object cache, no APCu): requests are not counted and rate limits cannot run. Only the crawler block list works.' );
		} else {
			$rows[] = array( 'Bot Shield', 'ok', 'Counting with ' . ( 'apcu' === $f['bot_backend'] ? 'APCu' : 'the object cache' ) . '.' );
		}

		return $rows;
	}
}
