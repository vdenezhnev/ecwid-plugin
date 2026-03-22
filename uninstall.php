<?php
/**
 * Plugin Uninstall
 *
 * Fired when the plugin is uninstalled.
 * Removes all plugin data including database tables and options.
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove all plugin data.
 *
 * This function is called when the plugin is deleted through
 * the WordPress admin interface.
 */
function ecwid_wc_uninstall() {
    global $wpdb;

    // Check if we should preserve data (user option).
    $preserve_data = get_option( 'ecwid_wc_preserve_data_on_uninstall', false );

    if ( $preserve_data ) {
        return;
    }

    // Drop custom tables.
    $table_prefix = $wpdb->prefix . 'ecwid_wc_';

    $tables = array(
        $table_prefix . 'mapping',
        $table_prefix . 'queue',
        $table_prefix . 'logs',
    );

    foreach ( $tables as $table ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
    }

    // Delete all plugin options.
    $options = array(
        // API settings.
        'ecwid_wc_ecwid_store_id',
        'ecwid_wc_ecwid_access_token',
        'ecwid_wc_wc_site_url',
        'ecwid_wc_wc_consumer_key',
        'ecwid_wc_wc_consumer_secret',
        'ecwid_wc_wc_use_external',

        // Sync settings.
        'ecwid_wc_sync_direction',
        'ecwid_wc_sync_products',
        'ecwid_wc_sync_orders',
        'ecwid_wc_sync_customers',
        'ecwid_wc_sync_categories',
        'ecwid_wc_sync_interval',
        'ecwid_wc_sync_batch_size',

        // Advanced settings.
        'ecwid_wc_conflict_resolution',
        'ecwid_wc_delete_sync',
        'ecwid_wc_image_sync',
        'ecwid_wc_variation_sync',

        // Logging settings.
        'ecwid_wc_log_level',
        'ecwid_wc_log_retention_days',

        // Status flags.
        'ecwid_wc_initial_sync_done',
        'ecwid_wc_db_version',
        'ecwid_wc_preserve_data_on_uninstall',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Delete all transients.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_ecwid_wc_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_ecwid_wc_' ) . '%'
        )
    );

    // Clear scheduled cron jobs.
    wp_clear_scheduled_hook( 'ecwid_wc_process_queue' );
    wp_clear_scheduled_hook( 'ecwid_wc_scheduled_sync' );
    wp_clear_scheduled_hook( 'ecwid_wc_cleanup_logs' );

    // Delete plugin-specific user meta.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like( 'ecwid_wc_' ) . '%'
        )
    );

    // Delete plugin-specific post meta from WooCommerce products/orders.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like( '_ecwid_' ) . '%'
        )
    );

    // Clear any object caches.
    wp_cache_flush();
}

// Run uninstall.
ecwid_wc_uninstall();
