<?php
/**
 * Sync Hooks Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Sync;

use Ecwid_WooCommerce\Utils\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce hooks for real-time synchronization.
 *
 * @since 1.0.0
 */
class Sync_Hooks {

    /**
     * Product sync instance.
     *
     * @var Product_Sync
     */
    private $product_sync;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Whether sync is enabled.
     *
     * @var bool
     */
    private $sync_enabled;

    /**
     * Sync direction.
     *
     * @var string
     */
    private $sync_direction;

    /**
     * Flag to prevent recursive syncs.
     *
     * @var bool
     */
    private static $syncing = false;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->product_sync   = new Product_Sync();
        $this->logger         = Logger::get_instance();
        $this->sync_enabled   = (bool) get_option( 'ecwid_wc_sync_products', true );
        $this->sync_direction = get_option( 'ecwid_wc_sync_direction', 'ecwid_to_wc' );
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init() {
        // Only register hooks if sync is enabled and direction includes WC to Ecwid.
        if ( ! $this->should_sync_to_ecwid() ) {
            return;
        }

        // Product hooks.
        add_action( 'woocommerce_new_product', array( $this, 'on_product_created' ), 10, 2 );
        add_action( 'woocommerce_update_product', array( $this, 'on_product_updated' ), 10, 2 );
        add_action( 'woocommerce_delete_product', array( $this, 'on_product_deleted' ), 10, 1 );
        add_action( 'woocommerce_trash_product', array( $this, 'on_product_trashed' ), 10, 1 );
        add_action( 'untrashed_post', array( $this, 'on_product_untrashed' ), 10, 1 );

        // Stock hooks.
        add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_changed' ), 10, 1 );
        add_action( 'woocommerce_variation_set_stock', array( $this, 'on_variation_stock_changed' ), 10, 1 );

        // Variation hooks.
        add_action( 'woocommerce_save_product_variation', array( $this, 'on_variation_saved' ), 10, 2 );

        // Status hooks.
        add_action( 'woocommerce_product_set_visibility', array( $this, 'on_visibility_changed' ), 10, 2 );

        // AJAX handler for manual sync.
        add_action( 'wp_ajax_ecwid_wc_sync_product', array( $this, 'ajax_sync_product' ) );

        $this->logger->debug( 'Sync hooks initialized', array(), 'Sync_Hooks' );
    }

    /**
     * Check if should sync to Ecwid.
     *
     * @return bool
     */
    private function should_sync_to_ecwid() {
        if ( ! $this->sync_enabled ) {
            return false;
        }

        return in_array( $this->sync_direction, array( 'wc_to_ecwid', 'bidirectional' ), true );
    }

    /**
     * Check if we can sync (not already syncing and has credentials).
     *
     * @return bool
     */
    private function can_sync() {
        if ( self::$syncing ) {
            return false;
        }

        $store_id     = get_option( 'ecwid_wc_ecwid_store_id', '' );
        $access_token = get_option( 'ecwid_wc_ecwid_access_token', '' );

        return ! empty( $store_id ) && ! empty( $access_token );
    }

    /**
     * Handle product creation.
     *
     * @param int         $product_id Product ID.
     * @param \WC_Product $product    Product object.
     * @return void
     */
    public function on_product_created( $product_id, $product = null ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        // Skip variations - they're handled with parent.
        if ( ! $product ) {
            $product = wc_get_product( $product_id );
        }

        if ( ! $product || $product->is_type( 'variation' ) ) {
            return;
        }

        // Skip if not published.
        if ( 'publish' !== $product->get_status() ) {
            return;
        }

        $this->schedule_sync( $product_id, 'create' );
    }

    /**
     * Handle product update.
     *
     * @param int         $product_id Product ID.
     * @param \WC_Product $product    Product object.
     * @return void
     */
    public function on_product_updated( $product_id, $product = null ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        if ( ! $product ) {
            $product = wc_get_product( $product_id );
        }

        if ( ! $product || $product->is_type( 'variation' ) ) {
            return;
        }

        $this->schedule_sync( $product_id, 'update' );
    }

    /**
     * Handle product deletion.
     *
     * @param int $product_id Product ID.
     * @return void
     */
    public function on_product_deleted( $product_id ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        // Check if delete sync is enabled.
        if ( ! get_option( 'ecwid_wc_delete_sync', false ) ) {
            return;
        }

        $this->schedule_sync( $product_id, 'delete' );
    }

    /**
     * Handle product trashed.
     *
     * @param int $product_id Product ID.
     * @return void
     */
    public function on_product_trashed( $product_id ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        // Disable in Ecwid instead of deleting.
        self::$syncing = true;

        try {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $mapping_repo = \Ecwid_WooCommerce\Utils\Mapping_Repository::get_instance();
                $mapping = $mapping_repo->find_by_wc_id( 'product', $product_id );

                if ( $mapping && ! empty( $mapping->ecwid_id ) ) {
                    $api = \Ecwid_WooCommerce\Api\Ecwid_Api::get_instance();
                    $api->update_product( (int) $mapping->ecwid_id, array( 'enabled' => false ) );

                    $this->logger->info(
                        sprintf( 'Disabled Ecwid product #%d (WC product #%d trashed)', $mapping->ecwid_id, $product_id ),
                        array(),
                        'Sync_Hooks'
                    );
                }
            }
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Handle product untrashed.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function on_product_untrashed( $post_id ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }

        // Re-enable in Ecwid.
        self::$syncing = true;

        try {
            $mapping_repo = \Ecwid_WooCommerce\Utils\Mapping_Repository::get_instance();
            $mapping = $mapping_repo->find_by_wc_id( 'product', $post_id );

            if ( $mapping && ! empty( $mapping->ecwid_id ) ) {
                $api = \Ecwid_WooCommerce\Api\Ecwid_Api::get_instance();
                $api->update_product( (int) $mapping->ecwid_id, array( 'enabled' => true ) );

                $this->logger->info(
                    sprintf( 'Re-enabled Ecwid product #%d (WC product #%d untrashed)', $mapping->ecwid_id, $post_id ),
                    array(),
                    'Sync_Hooks'
                );
            }
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Handle stock change.
     *
     * @param \WC_Product $product Product object.
     * @return void
     */
    public function on_stock_changed( $product ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        if ( ! $product || $product->is_type( 'variation' ) ) {
            return;
        }

        $this->schedule_sync( $product->get_id(), 'stock' );
    }

    /**
     * Handle variation stock change.
     *
     * @param \WC_Product_Variation $variation Variation object.
     * @return void
     */
    public function on_variation_stock_changed( $variation ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        // Sync parent product to update variations.
        $parent_id = $variation->get_parent_id();
        if ( $parent_id ) {
            $this->schedule_sync( $parent_id, 'update' );
        }
    }

    /**
     * Handle variation saved.
     *
     * @param int $variation_id Variation ID.
     * @param int $loop         Loop index.
     * @return void
     */
    public function on_variation_saved( $variation_id, $loop ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        $variation = wc_get_product( $variation_id );
        if ( $variation ) {
            $parent_id = $variation->get_parent_id();
            if ( $parent_id ) {
                $this->schedule_sync( $parent_id, 'update' );
            }
        }
    }

    /**
     * Handle visibility change.
     *
     * @param int    $product_id Product ID.
     * @param string $visibility New visibility.
     * @return void
     */
    public function on_visibility_changed( $product_id, $visibility ) {
        if ( ! $this->can_sync() ) {
            return;
        }

        $this->schedule_sync( $product_id, 'update' );
    }

    /**
     * Schedule a sync operation.
     *
     * @param int    $product_id Product ID.
     * @param string $action     Action type (create, update, delete, stock).
     * @return void
     */
    private function schedule_sync( $product_id, $action ) {
        $sync_interval = get_option( 'ecwid_wc_sync_interval', 'hourly' );

        // If realtime sync, do it now.
        if ( 'realtime' === $sync_interval || defined( 'DOING_CRON' ) ) {
            $this->do_sync( $product_id, $action );
        } else {
            // Schedule for later.
            $this->queue_sync( $product_id, $action );
        }
    }

    /**
     * Perform sync immediately.
     *
     * @param int    $product_id Product ID.
     * @param string $action     Action type.
     * @return void
     */
    private function do_sync( $product_id, $action ) {
        self::$syncing = true;

        try {
            switch ( $action ) {
                case 'create':
                case 'update':
                    $this->product_sync->export_product( $product_id );
                    break;

                case 'delete':
                    $this->product_sync->delete_product( $product_id );
                    break;

                case 'stock':
                    $product = wc_get_product( $product_id );
                    if ( $product && $product->get_manage_stock() ) {
                        $this->product_sync->sync_stock( $product_id, $product->get_stock_quantity() );
                    }
                    break;
            }
        } catch ( \Exception $e ) {
            $this->logger->error(
                sprintf( 'Sync error for product #%d: %s', $product_id, $e->getMessage() ),
                array( 'action' => $action ),
                'Sync_Hooks'
            );
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Queue sync for later processing.
     *
     * @param int    $product_id Product ID.
     * @param string $action     Action type.
     * @return void
     */
    private function queue_sync( $product_id, $action ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_wc_queue';

        // Check if already queued.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE entity_type = 'product' AND entity_id = %s AND status = 'pending'",
                (string) $product_id
            )
        );

        if ( $existing ) {
            // Update action if needed (e.g., create + update = create).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                array( 'action' => $action ),
                array( 'id' => $existing ),
                array( '%s' ),
                array( '%d' )
            );
        } else {
            // Insert new queue item.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $table_name,
                array(
                    'action'        => $action,
                    'entity_type'   => 'product',
                    'entity_id'     => (string) $product_id,
                    'source_system' => 'woocommerce',
                    'priority'      => 10,
                    'status'        => 'pending',
                    'created_at'    => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
            );
        }
    }

    /**
     * AJAX handler for manual product sync.
     *
     * @return void
     */
    public function ajax_sync_product() {
        check_ajax_referer( 'ecwid_wc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'ecwid-woocommerce' ) ) );
        }

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'ecwid-woocommerce' ) ) );
        }

        $result = $this->product_sync->export_product( $product );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * Process queued sync items.
     *
     * @param int $limit Max items to process.
     * @return int Number of items processed.
     */
    public function process_queue( $limit = 50 ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_wc_queue';

        // Get pending items.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE entity_type = 'product' AND status = 'pending' 
                ORDER BY priority ASC, created_at ASC 
                LIMIT %d",
                $limit
            )
        );

        if ( empty( $items ) ) {
            return 0;
        }

        $processed = 0;

        foreach ( $items as $item ) {
            // Mark as processing.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                array(
                    'status'     => 'processing',
                    'started_at' => current_time( 'mysql' ),
                    'attempts'   => $item->attempts + 1,
                ),
                array( 'id' => $item->id ),
                array( '%s', '%s', '%d' ),
                array( '%d' )
            );

            try {
                $this->do_sync( (int) $item->entity_id, $item->action );

                // Mark as completed.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $table_name,
                    array(
                        'status'       => 'completed',
                        'completed_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $item->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );

                $processed++;

            } catch ( \Exception $e ) {
                $new_status = $item->attempts >= $item->max_attempts ? 'failed' : 'pending';

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $table_name,
                    array(
                        'status'        => $new_status,
                        'error_message' => $e->getMessage(),
                    ),
                    array( 'id' => $item->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );

                $this->logger->error(
                    sprintf( 'Queue processing error for product #%s: %s', $item->entity_id, $e->getMessage() ),
                    array(),
                    'Sync_Hooks'
                );
            }
        }

        return $processed;
    }
}
