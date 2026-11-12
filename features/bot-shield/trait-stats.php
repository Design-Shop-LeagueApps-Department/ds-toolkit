<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bot Shield: the daily counters, their roll-up and the event log.
 *
 * One part of DS_Bot_Shield, which was a single 1,200-line class until the 2026-11 audit. The methods were
 * moved here as they were: a trait, so they are still methods of the feature (same visibility, same $this).
 * The decision itself (who is let through, slowed or refused) stays in class-ds-bot-shield.php.
 */
trait DS_Bot_Shield_Stats {

    // ------------------------------------------------------- stats & logging

    /** Counters are incremented in the object cache — cheap enough for a flood. */
    private function bump_stat( $which ) {
        $this->cache_incr( 'stat_' . $which . '_' . gmdate( 'Ymd' ), 2 * DAY_IN_SECONDS );
    }

    /**
     * A day's total for a counter.
     *
     * The live counter lives in the object cache, which is VOLATILE: a WP Engine
     * purge, a plugin update, a pod restart, or memcached evicting the key under
     * memory pressure all reset it to zero. Reporting the raw cache value made
     * the dashboard appear to count DOWN (observed on icelinerinks: 287 -> 189).
     *
     * So the cache is treated as a fast tick counter that may reset at any time,
     * and the durable total lives in an option updated by roll_up_stats() on the
     * same throttled 2-minute flush as the IP log. The answer is the persisted
     * total plus whatever has ticked since the last roll-up.
     */
    public function stat( $which ) {
        $day = gmdate( 'Ymd' );

        // Fold anything pending into the durable total before reporting, once
        // per request. Without this a cache wipe between two reads would drop
        // the figure. Only admin/REST read stats, never front-end traffic, so
        // this costs at most one option write per dashboard poll.
        if ( ! $this->rolled ) {
            $this->rolled = true;
            $this->roll_up_stats( $day );
        }

        $totals = $this->read_totals();

        $stored = isset( $totals['days'][ $day ]['totals'][ $which ] ) ? (int) $totals['days'][ $day ]['totals'][ $which ] : 0;
        $last   = isset( $totals['days'][ $day ]['last'][ $which ] )   ? (int) $totals['days'][ $day ]['last'][ $which ]   : 0;
        $hw     = isset( $totals['days'][ $day ]['hw'][ $which ] )     ? (int) $totals['days'][ $day ]['hw'][ $which ]     : 0;
        $live   = (int) $this->cache_get( 'stat_' . $which . '_' . $day );

        // $live < $last means the cache was wiped and has restarted from zero;
        // everything currently in it is new since the roll-up.
        $pending = ( $live >= $last ) ? ( $live - $last ) : $live;

        // A wipe loses whatever had ticked since the last roll-up, so the
        // computed figure can dip. The high-water mark keeps the reported
        // number monotonic — it may under-count, but it never counts DOWN.
        return max( $stored + $pending, $hw );
    }

    /**
     * Fold the live cache counters into the durable per-day totals.
     * Called from the same throttled flush as the IP log, so at most one extra
     * option write every 2 minutes no matter how much traffic arrives.
     */
    private function roll_up_stats( $day ) {
        $totals = $this->read_totals();
        if ( ! isset( $totals['days'][ $day ] ) || ! is_array( $totals['days'][ $day ] ) ) {
            $totals['days'][ $day ] = array( 'totals' => array(), 'last' => array(), 'hw' => array() );
        }

        $changed = false;
        foreach ( array( 'seen', 'rate-limit', 'ua-block', 'challenge', 'page-trap' ) as $k ) {
            $live = (int) $this->cache_get( 'stat_' . $k . '_' . $day );
            $last = isset( $totals['days'][ $day ]['last'][ $k ] ) ? (int) $totals['days'][ $day ]['last'][ $k ] : 0;
            if ( 0 === $live && 0 === $last ) {
                continue;
            }
            $delta = ( $live >= $last ) ? ( $live - $last ) : $live; // reset-aware
            if ( $delta > 0 || $live !== $last ) {
                $cur   = isset( $totals['days'][ $day ]['totals'][ $k ] ) ? (int) $totals['days'][ $day ]['totals'][ $k ] : 0;
                $hw    = isset( $totals['days'][ $day ]['hw'][ $k ] )     ? (int) $totals['days'][ $day ]['hw'][ $k ]     : 0;
                $total = $cur + $delta;
                $totals['days'][ $day ]['totals'][ $k ] = $total;
                $totals['days'][ $day ]['last'][ $k ]   = $live;
                $totals['days'][ $day ]['hw'][ $k ]     = max( $hw, $total );
                // The running total never rolls over at midnight — this is what
                // the report shows, so "Checked" is cumulative, not today-only.
                $all = isset( $totals['all'][ $k ] ) ? (int) $totals['all'][ $k ] : 0;
                $totals['all'][ $k ] = $all + $delta;
                $changed = true;
            }
        }

        // Daily buckets are only kept for the trend chart; 30 days of five
        // small ints is a trivial option. The all-time figures are never pruned.
        $cutoff = gmdate( 'Ymd', time() - ( 30 * DAY_IN_SECONDS ) );
        foreach ( array_keys( $totals['days'] ) as $d ) {
            // PHP casts numeric string keys to int, so compare as strings.
            if ( (string) $d < (string) $cutoff ) {
                unset( $totals['days'][ $d ] );
                $changed = true;
            }
        }

        if ( $changed ) {
            update_option( self::TOTALS_OPT, $totals, false );
        }
    }

    /**
     * Load the totals option, migrating the older shape (day keys at the top
     * level, no running total) so existing installs keep the numbers they
     * already collected instead of restarting from zero.
     */
    private function read_totals() {
        $totals = get_option( self::TOTALS_OPT, array() );
        $totals = is_array( $totals ) ? $totals : array();

        if ( ! isset( $totals['days'] ) ) {
            $days = array();
            foreach ( $totals as $k => $v ) {
                if ( is_array( $v ) && isset( $v['totals'] ) ) {
                    $days[ (string) $k ] = $v;
                }
            }
            $all = array();
            foreach ( $days as $bucket ) {
                foreach ( (array) $bucket['totals'] as $key => $n ) {
                    $all[ $key ] = ( isset( $all[ $key ] ) ? $all[ $key ] : 0 ) + (int) $n;
                }
            }
            $totals = array(
                'days'  => $days,
                'all'   => $all,
                'since' => gmdate( 'Y-m-d' ),
            );
        }
        if ( ! isset( $totals['all'] ) || ! is_array( $totals['all'] ) ) {
            $totals['all'] = array();
        }
        if ( empty( $totals['since'] ) ) {
            $totals['since'] = gmdate( 'Y-m-d' );
        }
        return $totals;
    }

    /** Running total since logging began — does not reset at midnight. */
    public function stat_total( $which ) {
        $totals = $this->read_totals();
        $all    = isset( $totals['all'][ $which ] ) ? (int) $totals['all'][ $which ] : 0;
        // Add whatever today's cache holds but has not been rolled up yet.
        $day     = gmdate( 'Ymd' );
        $last    = isset( $totals['days'][ $day ]['last'][ $which ] ) ? (int) $totals['days'][ $day ]['last'][ $which ] : 0;
        $live    = (int) $this->cache_get( 'stat_' . $which . '_' . $day );
        $pending = ( $live >= $last ) ? ( $live - $last ) : $live;
        return $all + $pending;
    }

    /** Date logging started, for the "since" label on the report. */
    public function stat_since() {
        $t = $this->read_totals();
        return $t['since'];
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
}
