<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Calendar -> iCalendar (.ics) text, for the module's Subscribe feed.
 *
 * Works on the events DS_Calendar_Data already holds (nothing is fetched here).
 * Times come from the event's own time text ("6 PM – 7:30 PM", "Sat 9 AM – Sun 5 PM",
 * "6 PM"), which DS_Calendar_Data writes in Eastern wall-clock; they are converted to
 * UTC so every calendar app shows them in the reader's own time zone. An event with no
 * readable time (all day, times that differ by team) is an all-day entry, and all-day
 * entries on consecutive days (a weekend tournament) become one entry.
 *
 * frontend.js mirrors clock() and summary() for the pop-up's Add to calendar button.
 */
class DS_Calendar_ICS {

	const WEEKDAYS = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );

	/**
	 * Time text on a day -> array( DateTime start, DateTime end ) in the calendar's time zone, or null.
	 * A start with no end lasts an hour. An end at or before the start is the next day.
	 */
	public static function clock( $text, $day, $tz = null ) {
		$re = '/^(?:([A-Za-z]{3})\s+)?(\d{1,2})(?::(\d{2}))?\s*([AP]M)(?:\s*[\x{2013}-]\s*(?:([A-Za-z]{3})\s+)?(\d{1,2})(?::(\d{2}))?\s*([AP]M))?$/iu';
		if ( ! preg_match( $re, trim( (string) $text ), $m ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $day ) ) {
			return null;
		}
		$zone  = new DateTimeZone( $tz ? $tz : DS_Calendar_Data::TZ );
		$start = new DateTime( $day . ' 00:00:00', $zone );
		// "Sat 9 AM – Sun 5 PM" is listed on Saturday AND Sunday; both mean the Saturday start.
		if ( ! empty( $m[1] ) ) {
			$want = array_search( strtolower( $m[1] ), self::WEEKDAYS, true );
			if ( false !== $want ) {
				$back = ( (int) $start->format( 'w' ) - $want + 7 ) % 7;
				if ( $back ) {
					$start->modify( '-' . $back . ' days' );
				}
			}
		}
		$start->setTime( self::h24( $m[2], $m[4] ), (int) ( '' !== $m[3] ? $m[3] : 0 ) );
		if ( empty( $m[6] ) ) {
			return array( $start, ( clone $start )->modify( '+1 hour' ) );
		}
		$end = clone $start;
		if ( ! empty( $m[5] ) ) {
			$want = array_search( strtolower( $m[5] ), self::WEEKDAYS, true );
			if ( false !== $want ) {
				$ahead = ( $want - (int) $end->format( 'w' ) + 7 ) % 7;
				if ( $ahead ) {
					$end->modify( '+' . $ahead . ' days' );
				}
			}
		}
		$end->setTime( self::h24( $m[6], $m[8] ), (int) ( isset( $m[7] ) && '' !== $m[7] ? $m[7] : 0 ) );
		if ( $end <= $start ) {
			$end->modify( '+1 day' );
		}
		return array( $start, $end );
	}

	private static function h24( $h, $ap ) {
		return ( (int) $h % 12 ) + ( 'PM' === strtoupper( $ap ) ? 12 : 0 );
	}

	/** The entry's name, the same words the calendar shows. */
	public static function summary( array $e, $team = '' ) {
		$title = (string) ( $e['title'] ?? '' );
		$kind  = (string) ( $e['kind'] ?? '' );
		$mark  = (string) ( $e['mark'] ?? '' );
		if ( 'deadline' === $kind ) {
			return 'Registration closes: ' . $title;
		}
		if ( 'start' === $mark ) {
			return $title . ' starts';
		}
		if ( 'end' === $mark ) {
			return $title . ' ends';
		}
		return '' !== $team && 'prog' !== $kind ? $title . ' (' . $team . ')' : $title;
	}

	/** Events the calendar shows for a team filter value ('t:Name', 'p:Name' or ''), like frontend.js visible(). */
	public static function visible( array $events, $filter ) {
		$filter = (string) $filter;
		if ( ! preg_match( '/^([tp]):(.+)$/s', $filter, $m ) ) {
			return $events;
		}
		return array_values( array_filter( $events, function ( $e ) use ( $m ) {
			$prog = in_array( $e['kind'] ?? '', array( 'prog', 'deadline' ), true );
			if ( 'p' === $m[1] ) {
				return ( $e['grp'] ?? '' ) === $m[2]; // program dates, deadlines and the program's own schedule
			}
			return ! $prog && in_array( $m[2], wp_list_pluck( (array) ( $e['teams'] ?? array() ), 'n' ), true );
		} ) );
	}

	/**
	 * Events -> calendar entries: array( uid, summary, start, end, all_day, loc, desc, url ).
	 * $start/$end are DateTime (timed) or 'Y-m-d' (all day, end exclusive).
	 */
	public static function entries( array $events, $filter = '', $host = 'calendar' ) {
		$team = preg_match( '/^t:(.+)$/s', (string) $filter, $m ) ? $m[1] : '';
		$out  = array();
		$days = array();
		foreach ( self::visible( $events, $filter ) as $e ) {
			$kind  = (string) ( $e['kind'] ?? '' );
			$prog  = in_array( $kind, array( 'prog', 'deadline' ), true );
			$text  = (string) ( $e['time'] ?? '' );
			if ( '' !== $team && ! $prog ) {
				foreach ( (array) ( $e['teams'] ?? array() ) as $t ) {
					if ( ( $t['n'] ?? '' ) === $team && '' !== ( $t['t'] ?? '' ) ) {
						$text = $t['t'];
					}
				}
			}
			$marked = 'deadline' === $kind || '' !== (string) ( $e['mark'] ?? '' );
			$range  = $marked ? null : self::clock( $text, $e['d'] ?? '' );
			$sum    = self::summary( $e, $team );
			$loc    = (string) ( $e['loc'] ?? '' );
			$row    = array(
				'summary' => $sum,
				'loc'     => $loc,
				'desc'    => self::description( $e, '' !== $team ),
				'url'     => (string) ( '' !== ( $e['reg'] ?? '' ) ? $e['reg'] : ( '' !== ( $e['url'] ?? '' ) ? $e['url'] : ( $e['more'] ?? '' ) ) ),
			);
			if ( $range ) {
				$row += array( 'start' => $range[0], 'end' => $range[1], 'all_day' => false );
				// One entry per real start ("Sat 9 AM – Sun 5 PM" sits on both days).
				$row['uid'] = substr( md5( $range[0]->format( 'c' ) . '|' . $sum . '|' . $loc ), 0, 24 ) . '@' . $host;
				$out[ $row['uid'] ] = $row;
				continue;
			}
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $e['d'] ?? '' ) ) ) {
				continue;
			}
			$days[ $sum . "\n" . $loc ][ $e['d'] ] = $row;
		}
		// All-day entries on consecutive days -> one entry.
		foreach ( $days as $list ) {
			ksort( $list );
			$run = null;
			foreach ( $list as $d => $row ) {
				if ( $run && $run['end'] === $d ) {
					$run['end'] = gmdate( 'Y-m-d', strtotime( $d . ' 00:00:00 UTC +1 day' ) );
					continue;
				}
				if ( $run ) {
					$out[ $run['uid'] ] = $run;
				}
				$run = $row + array( 'start' => $d, 'end' => gmdate( 'Y-m-d', strtotime( $d . ' 00:00:00 UTC +1 day' ) ), 'all_day' => true );
				$run['uid'] = substr( md5( $d . '|' . $row['summary'] . '|' . $row['loc'] ), 0, 24 ) . '@' . $host;
			}
			if ( $run ) {
				$out[ $run['uid'] ] = $run;
			}
		}
		$out = array_values( $out );
		usort( $out, function ( $a, $b ) {
			$x = $a['all_day'] ? $a['start'] : $a['start']->format( 'Y-m-d H:i' );
			$y = $b['all_day'] ? $b['start'] : $b['start']->format( 'Y-m-d H:i' );
			return strcmp( $x . $a['summary'], $y . $b['summary'] );
		} );
		return $out;
	}

	private static function description( array $e, $one_team ) {
		$lines = array();
		$type  = (string) ( '' !== ( $e['label'] ?? '' ) ? $e['label'] : ( $e['type'] ?? '' ) );
		if ( '' !== $type ) {
			$lines[] = $type;
		}
		$teams = (array) ( $e['teams'] ?? array() );
		if ( $teams && ! $one_team ) {
			$names = array();
			foreach ( $teams as $t ) {
				// "10U-12U Tuesday 6-7:30PM" already says when; do not add the time twice.
				$has     = preg_match( '/\d\s*(?::\d\d)?\s*[ap]m\b/i', (string) $t['n'] );
				$names[] = $t['n'] . ( ! empty( $e['times'] ) && '' !== ( $t['t'] ?? '' ) && ! $has ? ' ' . $t['t'] : '' );
			}
			$lines[] = ( 'prog' === ( $e['kind'] ?? '' ) ? 'Groups: ' : 'Teams: ' ) . implode( ', ', $names );
		}
		if ( '' !== (string) ( $e['note'] ?? '' ) ) {
			$lines[] = $e['note'];
		}
		if ( '' !== (string) ( $e['reg'] ?? '' ) ) {
			$lines[] = 'Register: ' . $e['reg'];
		}
		if ( '' !== (string) ( $e['more'] ?? '' ) && ( $e['more'] ?? '' ) !== ( $e['reg'] ?? '' ) ) {
			$lines[] = 'Program details: ' . $e['more'];
		}
		if ( '' !== (string) ( $e['url'] ?? '' ) ) {
			$lines[] = 'Event website: ' . $e['url'];
		}
		return implode( "\n", $lines );
	}

	/** The whole feed. */
	public static function build( array $events, $filter, $name, $host ) {
		$host  = preg_replace( '/[^a-z0-9.-]/i', '', (string) $host );
		$host  = '' !== $host ? $host : 'calendar';
		$stamp = gmdate( 'Ymd\THis\Z' );
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Design Shop//DS Toolkit LeagueApps Calendar//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::esc( $name ),
			'X-WR-TIMEZONE:' . DS_Calendar_Data::TZ,
			'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
			'X-PUBLISHED-TTL:PT6H',
		);
		$utc = new DateTimeZone( 'UTC' );
		foreach ( self::entries( $events, $filter, $host ) as $r ) {
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $r['uid'];
			$lines[] = 'DTSTAMP:' . $stamp;
			if ( $r['all_day'] ) {
				$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $r['start'] );
				$lines[] = 'DTEND;VALUE=DATE:' . str_replace( '-', '', $r['end'] );
			} else {
				$lines[] = 'DTSTART:' . ( clone $r['start'] )->setTimezone( $utc )->format( 'Ymd\THis\Z' );
				$lines[] = 'DTEND:' . ( clone $r['end'] )->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			}
			$lines[] = 'SUMMARY:' . self::esc( $r['summary'] );
			if ( '' !== $r['loc'] ) {
				$lines[] = 'LOCATION:' . self::esc( $r['loc'] );
			}
			if ( '' !== $r['desc'] ) {
				$lines[] = 'DESCRIPTION:' . self::esc( $r['desc'] );
			}
			if ( '' !== $r['url'] && preg_match( '~^https?://~i', $r['url'] ) ) {
				$lines[] = 'URL:' . preg_replace( '/[\r\n]/', '', $r['url'] );
			}
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
	}

	/** TEXT value escaping (RFC 5545 3.3.11). */
	public static function esc( $s ) {
		$s = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), (string) $s );
		$s = str_replace( array( "\r\n", "\n", "\r" ), '\\n', $s );
		// Other control characters are not allowed in a TEXT value; one stray byte from LeagueApps can make
		// Google or Outlook reject the whole subscribed calendar.
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s );
	}

	/** Lines of at most 75 octets, never splitting a UTF-8 character. */
	public static function fold( $line ) {
		$out = '';
		$len = 0;
		$chars = preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( false === $chars ? str_split( $line ) : $chars as $ch ) {
			$b = strlen( $ch );
			if ( $len + $b > 75 ) {
				$out .= "\r\n ";
				$len  = 1;
			}
			$out .= $ch;
			$len += $b;
		}
		return $out;
	}
}
