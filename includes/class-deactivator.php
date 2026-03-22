<?php
/**
 * Plugin Deactivator
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation tasks.
 *
 * Clears cron jobs and transients while preserving
 * user data for potential reactivation.
 *
 * @since 1.0.0
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate() {
        self::clear_scheduled_cron_jobs();
        self::clear_transients();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Clear all scheduled cron jobs.
     *
     * @return void
     */
    private static function clear_scheduled_cron_jobs() {
        // Queue processor.
        $timestamp = wp_next_scheduled( 'ecwid_wc_process_queue' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'ecwid_wc_process_queue' );
        }

        // Scheduled sync.
        $timestamp = wp_next_scheduled( 'ecwid_wc_scheduled_sync' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'ecwid_wc_scheduled_sync' );
        }

        // Log cleanup.
        $timestamp = wp_next_scheduled( 'ecwid_wc_cleanup_logs' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'ecwid_wc_cleanup_logs' );
        }

        // Clear all hooks with our prefix.
        wp_clear_scheduled_hook( 'ecwid_wc_process_queue' );
        wp_clear_scheduled_hook( 'ecwid_wc_scheduled_sync' );
        wp_clear_scheduled_hook( 'ecwid_wc_cleanup_logs' );
    }

    /**
     * Clear plugin transients.
     *
     * @return void
     */
    private static function clear_transients() {
        global $wpdb;

        // Delete all transients with our prefix.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like( '_transient_ecwid_wc_' ) . '%',
                $wpdb->esc_like( '_transient_timeout_ecwid_wc_' ) . '%'
            )
        );

        // Delete specific transients.
        delete_transient( 'ecwid_wc_activated' );
        delete_transient( 'ecwid_wc_sync_status' );
        delete_transient( 'ecwid_wc_api_cache' );
    }
}
