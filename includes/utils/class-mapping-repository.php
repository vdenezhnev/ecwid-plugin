<?php
/**
 * Mapping Repository Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Repository for managing ID mappings between WooCommerce and Ecwid.
 *
 * @since 1.0.0
 */
class Mapping_Repository {

    /**
     * Table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Single instance.
     *
     * @var Mapping_Repository|null
     */
    private static $instance = null;

    /**
     * Get single instance.
     *
     * @return Mapping_Repository
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
        $this->table_name = $wpdb->prefix . 'ecwid_wc_mapping';
    }

    /**
     * Find mapping by Ecwid ID.
     *
     * @param string $entity_type Entity type (product, order, customer, category).
     * @param int    $ecwid_id    Ecwid entity ID.
     * @return object|null Mapping object or null.
     */
    public function find_by_ecwid_id( $entity_type, $ecwid_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE entity_type = %s AND ecwid_id = %s",
                $entity_type,
                (string) $ecwid_id
            )
        );
    }

    /**
     * Find mapping by WooCommerce ID.
     *
     * @param string $entity_type Entity type (product, order, customer, category).
     * @param int    $wc_id       WooCommerce entity ID.
     * @return object|null Mapping object or null.
     */
    public function find_by_wc_id( $entity_type, $wc_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE entity_type = %s AND wc_id = %d",
                $entity_type,
                $wc_id
            )
        );
    }

    /**
     * Get Ecwid ID from WooCommerce ID.
     *
     * @param string $entity_type Entity type.
     * @param int    $wc_id       WooCommerce entity ID.
     * @return int|null Ecwid ID or null.
     */
    public function get_ecwid_id( $entity_type, $wc_id ) {
        $mapping = $this->find_by_wc_id( $entity_type, $wc_id );
        return $mapping ? (int) $mapping->ecwid_id : null;
    }

    /**
     * Get WooCommerce ID from Ecwid ID.
     *
     * @param string $entity_type Entity type.
     * @param int    $ecwid_id    Ecwid entity ID.
     * @return int|null WooCommerce ID or null.
     */
    public function get_wc_id( $entity_type, $ecwid_id ) {
        $mapping = $this->find_by_ecwid_id( $entity_type, $ecwid_id );
        return $mapping ? (int) $mapping->wc_id : null;
    }

    /**
     * Create a new mapping.
     *
     * @param array $data Mapping data.
     * @return int|false Insert ID or false on failure.
     */
    public function create( $data ) {
        global $wpdb;

        $defaults = array(
            'entity_type'      => '',
            'ecwid_id'         => '',
            'wc_id'            => 0,
            'ecwid_updated_at' => null,
            'wc_updated_at'    => null,
            'sync_status'      => 'synced',
            'sync_direction'   => null,
            'last_sync_at'     => current_time( 'mysql' ),
            'error_message'    => null,
        );

        $data = wp_parse_args( $data, $defaults );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'entity_type'      => $data['entity_type'],
                'ecwid_id'         => (string) $data['ecwid_id'],
                'wc_id'            => (int) $data['wc_id'],
                'ecwid_updated_at' => $data['ecwid_updated_at'],
                'wc_updated_at'    => $data['wc_updated_at'],
                'sync_status'      => $data['sync_status'],
                'sync_direction'   => $data['sync_direction'],
                'last_sync_at'     => $data['last_sync_at'],
                'error_message'    => $data['error_message'],
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing mapping.
     *
     * @param int   $id   Mapping ID.
     * @param array $data Update data.
     * @return bool True on success, false on failure.
     */
    public function update( $id, $data ) {
        global $wpdb;

        $allowed_fields = array(
            'ecwid_id',
            'wc_id',
            'ecwid_updated_at',
            'wc_updated_at',
            'sync_status',
            'sync_direction',
            'last_sync_at',
            'error_message',
        );

        $update_data   = array();
        $update_format = array();

        foreach ( $data as $key => $value ) {
            if ( in_array( $key, $allowed_fields, true ) ) {
                $update_data[ $key ] = $value;

                if ( in_array( $key, array( 'wc_id' ), true ) ) {
                    $update_format[] = '%d';
                } else {
                    $update_format[] = '%s';
                }
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $id ),
            $update_format,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Save mapping (create or update).
     *
     * @param string $entity_type Entity type.
     * @param int    $wc_id       WooCommerce ID.
     * @param int    $ecwid_id    Ecwid ID.
     * @param array  $extra_data  Additional data.
     * @return int|false Mapping ID or false on failure.
     */
    public function save( $entity_type, $wc_id, $ecwid_id, $extra_data = array() ) {
        $existing = $this->find_by_wc_id( $entity_type, $wc_id );

        $data = array_merge(
            array(
                'entity_type'   => $entity_type,
                'ecwid_id'      => $ecwid_id,
                'wc_id'         => $wc_id,
                'last_sync_at'  => current_time( 'mysql' ),
                'sync_status'   => 'synced',
            ),
            $extra_data
        );

        if ( $existing ) {
            $this->update( $existing->id, $data );
            return (int) $existing->id;
        }

        return $this->create( $data );
    }

    /**
     * Delete a mapping.
     *
     * @param int $id Mapping ID.
     * @return bool True on success, false on failure.
     */
    public function delete( $id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete mapping by WooCommerce ID.
     *
     * @param string $entity_type Entity type.
     * @param int    $wc_id       WooCommerce ID.
     * @return bool True on success, false on failure.
     */
    public function delete_by_wc_id( $entity_type, $wc_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $this->table_name,
            array(
                'entity_type' => $entity_type,
                'wc_id'       => $wc_id,
            ),
            array( '%s', '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete mapping by Ecwid ID.
     *
     * @param string $entity_type Entity type.
     * @param int    $ecwid_id    Ecwid ID.
     * @return bool True on success, false on failure.
     */
    public function delete_by_ecwid_id( $entity_type, $ecwid_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $this->table_name,
            array(
                'entity_type' => $entity_type,
                'ecwid_id'    => (string) $ecwid_id,
            ),
            array( '%s', '%s' )
        );

        return false !== $result;
    }

    /**
     * Get all mappings by entity type.
     *
     * @param string $entity_type Entity type.
     * @param array  $args        Query arguments.
     * @return array Array of mapping objects.
     */
    public function get_all( $entity_type, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'   => '',
            'limit'    => 100,
            'offset'   => 0,
            'order_by' => 'id',
            'order'    => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( 'entity_type = %s' );
        $values = array( $entity_type );

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'sync_status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );
        $order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'id ASC';

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $values
            )
        );
    }

    /**
     * Count mappings by entity type.
     *
     * @param string $entity_type Entity type.
     * @param string $status      Filter by status (optional).
     * @return int Count.
     */
    public function count( $entity_type, $status = '' ) {
        global $wpdb;

        if ( ! empty( $status ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE entity_type = %s AND sync_status = %s",
                    $entity_type,
                    $status
                )
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE entity_type = %s",
                $entity_type
            )
        );
    }

    /**
     * Get statistics for all entity types.
     *
     * @return array Statistics array.
     */
    public function get_statistics() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            "SELECT entity_type, sync_status, COUNT(*) as count FROM {$this->table_name} GROUP BY entity_type, sync_status"
        );

        $stats = array(
            'product'  => array( 'synced' => 0, 'pending' => 0, 'error' => 0, 'total' => 0 ),
            'order'    => array( 'synced' => 0, 'pending' => 0, 'error' => 0, 'total' => 0 ),
            'customer' => array( 'synced' => 0, 'pending' => 0, 'error' => 0, 'total' => 0 ),
            'category' => array( 'synced' => 0, 'pending' => 0, 'error' => 0, 'total' => 0 ),
        );

        foreach ( $results as $row ) {
            if ( isset( $stats[ $row->entity_type ] ) ) {
                $stats[ $row->entity_type ][ $row->sync_status ] = (int) $row->count;
                $stats[ $row->entity_type ]['total']            += (int) $row->count;
            }
        }

        return $stats;
    }

    /**
     * Mark mapping as error.
     *
     * @param int    $id            Mapping ID.
     * @param string $error_message Error message.
     * @return bool True on success.
     */
    public function mark_error( $id, $error_message ) {
        return $this->update( $id, array(
            'sync_status'   => 'error',
            'error_message' => $error_message,
            'last_sync_at'  => current_time( 'mysql' ),
        ) );
    }

    /**
     * Mark mapping as synced.
     *
     * @param int $id Mapping ID.
     * @return bool True on success.
     */
    public function mark_synced( $id ) {
        return $this->update( $id, array(
            'sync_status'   => 'synced',
            'error_message' => null,
            'last_sync_at'  => current_time( 'mysql' ),
        ) );
    }

    /**
     * Mark mapping as pending.
     *
     * @param int $id Mapping ID.
     * @return bool True on success.
     */
    public function mark_pending( $id ) {
        return $this->update( $id, array(
            'sync_status' => 'pending',
        ) );
    }

    /**
     * Get pending mappings for sync.
     *
     * @param string $entity_type Entity type.
     * @param int    $limit       Max number to return.
     * @return array Array of mapping objects.
     */
    public function get_pending( $entity_type, $limit = 50 ) {
        return $this->get_all( $entity_type, array(
            'status' => 'pending',
            'limit'  => $limit,
        ) );
    }

    /**
     * Get errored mappings.
     *
     * @param string $entity_type Entity type.
     * @param int    $limit       Max number to return.
     * @return array Array of mapping objects.
     */
    public function get_errors( $entity_type, $limit = 50 ) {
        return $this->get_all( $entity_type, array(
            'status' => 'error',
            'limit'  => $limit,
        ) );
    }

    /**
     * Clear all mappings for an entity type.
     *
     * @param string $entity_type Entity type.
     * @return int Number of rows deleted.
     */
    public function clear_all( $entity_type ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete(
            $this->table_name,
            array( 'entity_type' => $entity_type ),
            array( '%s' )
        );
    }
}
