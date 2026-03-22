<?php
/**
 * Logger Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Handles logging functionality.
 *
 * Provides PSR-3 compatible logging with database storage.
 *
 * @since 1.0.0
 */
class Logger {

    /**
     * Log levels.
     */
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Log level priorities.
     *
     * @var array
     */
    private static $level_priority = array(
        self::LEVEL_DEBUG   => 1,
        self::LEVEL_INFO    => 2,
        self::LEVEL_WARNING => 3,
        self::LEVEL_ERROR   => 4,
    );

    /**
     * Single instance.
     *
     * @var Logger|null
     */
    private static $instance = null;

    /**
     * Minimum log level.
     *
     * @var string
     */
    private $min_level;

    /**
     * Table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Get single instance.
     *
     * @return Logger
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ecwid_wc_logs';
        $this->min_level  = get_option( 'ecwid_wc_log_level', self::LEVEL_INFO );
    }

    /**
     * Log a debug message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @param string $source  Log source.
     * @return void
     */
    public function debug( $message, $context = array(), $source = '' ) {
        $this->log( self::LEVEL_DEBUG, $message, $context, $source );
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @param string $source  Log source.
     * @return void
     */
    public function info( $message, $context = array(), $source = '' ) {
        $this->log( self::LEVEL_INFO, $message, $context, $source );
    }

    /**
     * Log a warning message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @param string $source  Log source.
     * @return void
     */
    public function warning( $message, $context = array(), $source = '' ) {
        $this->log( self::LEVEL_WARNING, $message, $context, $source );
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @param string $source  Log source.
     * @return void
     */
    public function error( $message, $context = array(), $source = '' ) {
        $this->log( self::LEVEL_ERROR, $message, $context, $source );
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @param string $source  Log source.
     * @return bool
     */
    public function log( $level, $message, $context = array(), $source = '' ) {
        // Check if this level should be logged.
        if ( ! $this->should_log( $level ) ) {
            return false;
        }

        global $wpdb;

        $data = array(
            'level'      => $level,
            'message'    => $message,
            'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
            'source'     => $source ?: $this->get_caller_source(),
            'created_at' => current_time( 'mysql' ),
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        return false !== $result;
    }

    /**
     * Check if a level should be logged.
     *
     * @param string $level Log level.
     * @return bool
     */
    private function should_log( $level ) {
        $level_priority     = self::$level_priority[ $level ] ?? 0;
        $min_level_priority = self::$level_priority[ $this->min_level ] ?? 0;

        return $level_priority >= $min_level_priority;
    }

    /**
     * Get caller source from backtrace.
     *
     * @return string
     */
    private function get_caller_source() {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

        foreach ( $backtrace as $trace ) {
            if ( isset( $trace['class'] ) && __CLASS__ !== $trace['class'] ) {
                return $trace['class'];
            }
            if ( isset( $trace['function'] ) && ! in_array( $trace['function'], array( 'log', 'debug', 'info', 'warning', 'error' ), true ) ) {
                return $trace['function'];
            }
        }

        return 'unknown';
    }

    /**
     * Get logs with pagination and filtering.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'level'    => '',
            'source'   => '',
            'search'   => '',
            'per_page' => 50,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );

        $args   = wp_parse_args( $args, $defaults );
        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $args['level'] ) ) {
            $where[]  = 'level = %s';
            $values[] = $args['level'];
        }

        if ( ! empty( $args['source'] ) ) {
            $where[]  = 'source = %s';
            $values[] = $args['source'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where[]  = 'message LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $where_clause = implode( ' AND ', $where );
        $offset       = ( $args['page'] - 1 ) * $args['per_page'];
        $orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'created_at DESC';

        // Get total count.
        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $values
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        }

        // Get logs.
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $values[] = $args['per_page'];
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $logs = $wpdb->get_results(
            $wpdb->prepare( $query, $values ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );

        return array(
            'logs'       => $logs ?: array(),
            'total'      => (int) $total,
            'pages'      => ceil( $total / $args['per_page'] ),
            'page'       => $args['page'],
            'per_page'   => $args['per_page'],
        );
    }

    /**
     * Clear logs older than specified days.
     *
     * @param int $days Number of days to retain.
     * @return int Number of deleted rows.
     */
    public function cleanup( $days = null ) {
        global $wpdb;

        if ( null === $days ) {
            $days = (int) get_option( 'ecwid_wc_log_retention_days', 30 );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Clear all logs.
     *
     * @return bool
     */
    public function clear_all() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return false !== $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
    }

    /**
     * Set minimum log level.
     *
     * @param string $level Log level.
     * @return void
     */
    public function set_min_level( $level ) {
        if ( isset( self::$level_priority[ $level ] ) ) {
            $this->min_level = $level;
        }
    }
}
