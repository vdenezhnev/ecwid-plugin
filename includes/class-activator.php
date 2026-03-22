<?php
/**
 * Plugin Activator
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation tasks.
 *
 * Creates database tables, sets default options,
 * and schedules cron jobs.
 *
 * @since 1.0.0
 */
class Activator {

    /**
     * Database table prefix for plugin tables.
     *
     * @var string
     */
    const TABLE_PREFIX = 'ecwid_wc_';

    /**
     * Activate the plugin.
     *
     * @return void
     */
    public static function activate() {
        self::check_requirements();
        self::create_tables();
        self::set_default_options();
        self::schedule_cron_jobs();
        self::set_activation_flag();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Check plugin requirements on activation.
     *
     * @return void
     */
    private static function check_requirements() {
        if ( version_compare( PHP_VERSION, ECWID_WC_MIN_PHP_VERSION, '<' ) ) {
            deactivate_plugins( ECWID_WC_PLUGIN_BASENAME );
            wp_die(
                sprintf(
                    /* translators: %s: Required PHP version */
                    esc_html__( 'Ecwid WooCommerce Integration requires PHP %s or higher.', 'ecwid-woocommerce' ),
                    ECWID_WC_MIN_PHP_VERSION
                ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }
    }

    /**
     * Create custom database tables.
     *
     * @return void
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix . self::TABLE_PREFIX;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Mapping table - stores ID relationships between Ecwid and WooCommerce entities.
        $mapping_table = "CREATE TABLE IF NOT EXISTS {$prefix}mapping (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(50) NOT NULL,
            ecwid_id VARCHAR(100) NOT NULL,
            wc_id BIGINT UNSIGNED NOT NULL,
            ecwid_updated_at DATETIME DEFAULT NULL,
            wc_updated_at DATETIME DEFAULT NULL,
            sync_status VARCHAR(20) DEFAULT 'synced',
            sync_direction VARCHAR(20) DEFAULT NULL,
            last_sync_at DATETIME DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_entity_ecwid (entity_type, ecwid_id),
            KEY idx_entity_wc (entity_type, wc_id),
            KEY idx_sync_status (sync_status),
            KEY idx_last_sync (last_sync_at)
        ) $charset_collate;";

        dbDelta( $mapping_table );

        // Queue table - stores sync jobs for background processing.
        $queue_table = "CREATE TABLE IF NOT EXISTS {$prefix}queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(100) NOT NULL,
            source_system VARCHAR(20) NOT NULL,
            payload LONGTEXT DEFAULT NULL,
            priority INT DEFAULT 10,
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            status VARCHAR(20) DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            scheduled_at DATETIME DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_priority (status, priority, scheduled_at),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_scheduled (scheduled_at)
        ) $charset_collate;";

        dbDelta( $queue_table );

        // Logs table - stores sync activity logs.
        $logs_table = "CREATE TABLE IF NOT EXISTS {$prefix}logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT DEFAULT NULL,
            source VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_level_date (level, created_at),
            KEY idx_source (source),
            KEY idx_created (created_at)
        ) $charset_collate;";

        dbDelta( $logs_table );

        // Store the database version.
        update_option( 'ecwid_wc_db_version', ECWID_WC_VERSION );
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private static function set_default_options() {
        $default_options = array(
            // Ecwid API settings.
            'ecwid_store_id'      => '',
            'ecwid_access_token'  => '',

            // WooCommerce API settings (for external WC).
            'wc_site_url'         => '',
            'wc_consumer_key'     => '',
            'wc_consumer_secret'  => '',
            'wc_use_external'     => false,

            // Sync settings.
            'sync_direction'      => 'ecwid_to_wc',
            'sync_products'       => true,
            'sync_orders'         => false,
            'sync_customers'      => false,
            'sync_categories'     => true,
            'sync_interval'       => 'hourly',
            'sync_batch_size'     => 50,

            // Advanced settings.
            'conflict_resolution' => 'latest',
            'delete_sync'         => false,
            'image_sync'          => true,
            'variation_sync'      => true,

            // Logging settings.
            'log_level'           => 'info',
            'log_retention_days'  => 30,

            // Status flags.
            'initial_sync_done'   => false,
        );

        foreach ( $default_options as $key => $value ) {
            if ( false === get_option( 'ecwid_wc_' . $key ) ) {
                add_option( 'ecwid_wc_' . $key, $value );
            }
        }
    }

    /**
     * Schedule cron jobs for sync operations.
     *
     * @return void
     */
    private static function schedule_cron_jobs() {
        // Queue processor - runs frequently to process pending jobs.
        if ( ! wp_next_scheduled( 'ecwid_wc_process_queue' ) ) {
            wp_schedule_event( time(), 'ecwid_wc_five_minutes', 'ecwid_wc_process_queue' );
        }

        // Scheduled sync - runs based on user settings.
        $sync_interval = get_option( 'ecwid_wc_sync_interval', 'hourly' );
        if ( ! wp_next_scheduled( 'ecwid_wc_scheduled_sync' ) && 'manual' !== $sync_interval ) {
            wp_schedule_event( time(), $sync_interval, 'ecwid_wc_scheduled_sync' );
        }

        // Order import - runs every 15 minutes for near real-time order sync.
        if ( ! wp_next_scheduled( 'ecwid_wc_import_orders' ) ) {
            wp_schedule_event( time(), 'ecwid_wc_fifteen_minutes', 'ecwid_wc_import_orders' );
        }

        // Log cleanup - runs daily.
        if ( ! wp_next_scheduled( 'ecwid_wc_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'ecwid_wc_cleanup_logs' );
        }
    }

    /**
     * Set activation flag for welcome notice.
     *
     * @return void
     */
    private static function set_activation_flag() {
        set_transient( 'ecwid_wc_activated', true, 30 );
    }
}
