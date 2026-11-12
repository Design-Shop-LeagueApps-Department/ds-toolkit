<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bot Shield: the dashboard widget.
 *
 * One part of DS_Bot_Shield, which was a single 1,200-line class until the 2026-11 audit. The methods were
 * moved here as they were: a trait, so they are still methods of the feature (same visibility, same $this).
 * The decision itself (who is let through, slowed or refused) stays in class-ds-bot-shield.php.
 */
trait DS_Bot_Shield_Widget {

    // ----------------------------------------------------- dashboard widget

    /**
     * The rollout confirmation box from the Site Stability Push brief: the
     * dashboard shows the mode and today's numbers, so "the box is there"
     * doubles as proof the shield is active on a site.
     */
    public function register_dashboard_widget() {
        wp_add_dashboard_widget( 'ds_bot_shield_status', 'Bot Shield', array( $this, 'render_dashboard_widget' ) );
    }

    public function render_dashboard_widget() {
        $monitor = $this->is_monitor_mode();
        $rows    = array(
            'rate-limit' => 'Rate-limited (429)',
            'ua-block'   => 'Blocked crawlers (403)',
            'challenge'  => 'Browser checks (503)',
            'page-trap'  => 'Pagination traps (410)',
        );
        echo '<p><strong>' . ( $monitor
            ? 'Monitor mode — nothing is being blocked yet.'
            : 'Blocking mode — abusive traffic is being rejected.' ) . '</strong></p>';
        if ( '' === $this->backend_name() ) {
            // Zeros here would read as a quiet day. They are not: nothing can be counted on this host.
            echo '<p><strong>Not counting:</strong> this host has no cache backend (no persistent object cache, no APCu), so rate limits cannot run. Only the crawler block list works.</p>';
            return;
        }
        echo '<p>Requests evaluated today: <strong>' . (int) $this->stat( 'seen' ) . '</strong></p>';
        echo '<p>' . ( $monitor ? 'Would have been blocked today:' : 'Blocked today:' ) . '</p><ul style="margin-left:1em;list-style:disc;">';
        foreach ( $rows as $key => $label ) {
            echo '<li>' . esc_html( $label ) . ': <strong>' . (int) $this->stat( $key ) . '</strong></li>';
        }
        echo '</ul>';
        $events = get_option( self::EVENTS_OPT, array() );
        if ( is_array( $events ) && $events ) {
            echo '<p style="margin-top:8px;"><strong>Recent events</strong></p><ul style="margin-left:1em;list-style:disc;">';
            foreach ( array_slice( array_reverse( $events ), 0, 5 ) as $e ) {
                echo '<li>' . esc_html( gmdate( 'M j H:i', (int) $e['t'] ) . ' UTC — ' . $e['kind'] . ' (' . $e['detail'] . ')' ) . '</li>';
            }
            echo '</ul>';
        }
        $this->flush_ip_log();
        $offenders = $this->ip_log();
        if ( $offenders ) {
            echo '<p style="margin-top:8px;"><strong>Top offending IPs today</strong></p><ul style="margin-left:1em;list-style:disc;">';
            foreach ( array_slice( $offenders, 0, 10, true ) as $row ) {
                echo '<li><code>' . esc_html( $row['ip'] ) . '</code> — ' . (int) $row['n'] . '× '
                    . esc_html( $row['verdict'] ) . ', <span title="' . esc_attr( $row['ua'] ) . '">'
                    . esc_html( substr( $row['ua'], 0, 40 ) ) . '…</span> on <code>'
                    . esc_html( substr( $row['path'], 0, 40 ) ) . '</code></li>';
            }
            echo '</ul>';
        }
    }
}
