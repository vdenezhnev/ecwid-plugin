<?php
/**
 * Ecwid Cron Handler
 *
 * Handles periodic sync via WP-Cron
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Cron Handler class
 */
class Ecwid_Cron_Handler {

    /**
     * Order sync instance
     *
     * @var Ecwid_Order_Sync
     */
    private $order_sync;

    /**
     * Logger instance
     *
     * @var Ecwid_Logger
     */
    private $logger;

    /**
     * Cron hook name
     *
     * @var string
     */
    const CRON_HOOK = 'ecwid_sync_orders_cron';

    /**
     * Log cleanup cron hook
     *
     * @var string
     */
    const LOG_CLEANUP_HOOK = 'ecwid_cleanup_logs_cron';

    /**
     * Constructor
     *
     * @param Ecwid_Order_Sync $order_sync Order sync instance.
     */
    public function __construct( Ecwid_Order_Sync $order_sync ) {
        $this->order_sync = $order_sync;
        $this->logger     = new Ecwid_Logger();

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register custom cron schedules
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

        // Register cron handlers
        add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );
        add_action( self::LOG_CLEANUP_HOOK, array( $this, 'cleanup_logs' ) );

        // Schedule log cleanup if not scheduled
        if ( ! wp_next_scheduled( self::LOG_CLEANUP_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::LOG_CLEANUP_HOOK );
        }
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public function add_cron_schedules( $schedules ) {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'ecwid-woocommerce-sync' ),
        );

        $schedules['every_fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => __( 'Every 15 Minutes', 'ecwid-woocommerce-sync' ),
        );

        $schedules['every_thirty_minutes'] = array(
            'interval' => 1800,
            'display'  => __( 'Every 30 Minutes', 'ecwid-woocommerce-sync' ),
        );

        return $schedules;
    }

    /**
     * Run order sync
     */
    public function run_sync() {
        // Check if sync is enabled
        $sync_enabled = get_option( 'ecwid_sync_enabled', 'yes' );

        if ( 'yes' !== $sync_enabled ) {
            $this->logger->info( 'Cron sync skipped - sync is disabled' );
            return;
        }

        // Check if API is configured
        $api = new Ecwid_API_Client();
        
        if ( ! $api->is_configured() ) {
            $this->logger->warning( 'Cron sync skipped - API not configured' );
            return;
        }

        $this->logger->info( 'Starting cron sync' );

        // Get last sync time
        $last_sync = get_option( 'ecwid_last_sync', '' );

        if ( empty( $last_sync ) ) {
            // First sync - get orders from configured start date or last 30 days
            $sync_from = get_option( 'ecwid_sync_from_date', '' );
            
            if ( empty( $sync_from ) ) {
                $sync_from = gmdate( 'c', strtotime( '-30 days' ) );
            }
        } else {
            // Subsequent syncs - get orders updated since last sync
            $sync_from = $last_sync;
        }

        // Run the sync
        $result = $this->order_sync->sync_orders_since( $sync_from );

        // Update last sync time
        update_option( 'ecwid_last_sync', gmdate( 'c' ) );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Cron sync failed', array(
                'error' => $result->get_error_message(),
            ) );
            return;
        }

        $this->logger->info( 'Cron sync completed', array(
            'imported' => $result['imported'],
            'updated'  => $result['updated'],
            'skipped'  => $result['skipped'],
            'errors'   => $result['errors'],
        ) );
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_logs() {
        $this->logger->clear_old_logs( 30 );
        $this->logger->info( 'Old logs cleaned up' );
    }

    /**
     * Schedule sync
     *
     * @param string $interval Cron interval.
     */
    public function schedule_sync( $interval = 'hourly' ) {
        // Clear existing schedule
        $this->unschedule_sync();

        // Schedule new event
        $valid_intervals = array( 'every_five_minutes', 'every_fifteen_minutes', 'every_thirty_minutes', 'hourly', 'twicedaily', 'daily' );
        
        if ( ! in_array( $interval, $valid_intervals, true ) ) {
            $interval = 'hourly';
        }

        wp_schedule_event( time(), $interval, self::CRON_HOOK );

        $this->logger->info( 'Sync scheduled', array( 'interval' => $interval ) );
    }

    /**
     * Unschedule sync
     */
    public function unschedule_sync() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }

        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Check if sync is scheduled
     *
     * @return bool
     */
    public function is_scheduled() {
        return (bool) wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Get next scheduled sync time
     *
     * @return int|false
     */
    public function get_next_scheduled() {
        return wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Run sync immediately (manually triggered)
     *
     * @return array|WP_Error
     */
    public function run_manual_sync() {
        $this->logger->info( 'Manual sync triggered' );
        
        $this->run_sync();
        
        return array(
            'status'  => 'success',
            'message' => __( 'Manual sync completed.', 'ecwid-woocommerce-sync' ),
        );
    }

    /**
     * Run full sync (all orders)
     *
     * @param string $from_date Start date for sync.
     * @return array|WP_Error
     */
    public function run_full_sync( $from_date = '' ) {
        $this->logger->info( 'Full sync triggered', array( 'from_date' => $from_date ) );

        if ( empty( $from_date ) ) {
            $from_date = gmdate( 'c', strtotime( '-1 year' ) );
        }

        $result = $this->order_sync->sync_all_orders( $from_date );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Full sync failed', array(
                'error' => $result->get_error_message(),
            ) );
            return $result;
        }

        // Update last sync time
        update_option( 'ecwid_last_sync', gmdate( 'c' ) );

        $this->logger->info( 'Full sync completed', $result );

        return $result;
    }

    /**
     * Get available sync intervals
     *
     * @return array
     */
    public static function get_available_intervals() {
        return array(
            'every_five_minutes'    => __( 'Every 5 Minutes', 'ecwid-woocommerce-sync' ),
            'every_fifteen_minutes' => __( 'Every 15 Minutes', 'ecwid-woocommerce-sync' ),
            'every_thirty_minutes'  => __( 'Every 30 Minutes', 'ecwid-woocommerce-sync' ),
            'hourly'                => __( 'Hourly', 'ecwid-woocommerce-sync' ),
            'twicedaily'            => __( 'Twice Daily', 'ecwid-woocommerce-sync' ),
            'daily'                 => __( 'Daily', 'ecwid-woocommerce-sync' ),
        );
    }
}
