<?php
/**
 * Ecwid Logger
 *
 * Handles logging for the plugin
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Logger class
 */
class Ecwid_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private $debug_enabled;

    /**
     * Constructor
     */
    public function __construct() {
        $this->debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
    }

    /**
     * Log a message
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function log( $level, $message, $context = array() ) {
        global $wpdb;

        // Skip debug messages if debug mode is disabled
        if ( self::LEVEL_DEBUG === $level && ! $this->debug_enabled ) {
            return;
        }

        $table_name = $wpdb->prefix . 'ecwid_sync_logs';

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        if ( $table_exists ) {
            $wpdb->insert(
                $table_name,
                array(
                    'log_type'   => $level,
                    'message'    => $message,
                    'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s' )
            );
        }

        // Also log to WooCommerce logger if available
        if ( function_exists( 'wc_get_logger' ) ) {
            $wc_logger = wc_get_logger();
            $wc_logger->log( $level, $message . ( ! empty( $context ) ? ' | Context: ' . wp_json_encode( $context ) : '' ), array( 'source' => 'ecwid-sync' ) );
        }
    }

    /**
     * Log debug message
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function debug( $message, $context = array() ) {
        $this->log( self::LEVEL_DEBUG, $message, $context );
    }

    /**
     * Log info message
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function info( $message, $context = array() ) {
        $this->log( self::LEVEL_INFO, $message, $context );
    }

    /**
     * Log warning message
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function warning( $message, $context = array() ) {
        $this->log( self::LEVEL_WARNING, $message, $context );
    }

    /**
     * Log error message
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function error( $message, $context = array() ) {
        $this->log( self::LEVEL_ERROR, $message, $context );
    }

    /**
     * Get logs
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'log_type' => '',
            'limit'    => 100,
            'offset'   => 0,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $table_name = $wpdb->prefix . 'ecwid_sync_logs';

        $where = '1=1';
        $values = array();

        if ( ! empty( $args['log_type'] ) ) {
            $where   .= ' AND log_type = %s';
            $values[] = $args['log_type'];
        }

        $orderby = in_array( $args['orderby'], array( 'id', 'log_type', 'created_at' ), true ) ? $args['orderby'] : 'created_at';
        $order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = intval( $args['limit'] );
        $values[] = intval( $args['offset'] );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

        return $results ? $results : array();
    }

    /**
     * Clear old logs
     *
     * @param int $days Number of days to keep.
     */
    public function clear_old_logs( $days = 30 ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_sync_logs';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                intval( $days )
            )
        );
    }

    /**
     * Clear all logs
     */
    public function clear_all_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_sync_logs';

        $wpdb->query( "TRUNCATE TABLE $table_name" );
    }
}
