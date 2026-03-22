<?php
/**
 * Order Sync Service
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Sync;

use Ecwid_WooCommerce\Api\Ecwid_Api;
use Ecwid_WooCommerce\Mappers\Order_Mapper;
use Ecwid_WooCommerce\Mappers\Customer_Mapper;
use Ecwid_WooCommerce\Utils\Logger;
use Ecwid_WooCommerce\Utils\Mapping_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles order synchronization between Ecwid and WooCommerce.
 *
 * @since 1.0.0
 */
class Order_Sync {

    /**
     * API client.
     *
     * @var Ecwid_Api
     */
    private $api;

    /**
     * Order mapper.
     *
     * @var Order_Mapper
     */
    private $order_mapper;

    /**
     * Customer mapper.
     *
     * @var Customer_Mapper
     */
    private $customer_mapper;

    /**
     * Mapping repository.
     *
     * @var Mapping_Repository
     */
    private $mapping_repo;

    /**
     * Logger.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Batch size for processing.
     *
     * @var int
     */
    private $batch_size;

    /**
     * Sync results.
     *
     * @var array
     */
    private $results = array(
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors'  => 0,
    );

    /**
     * Error details.
     *
     * @var array
     */
    private $error_details = array();

    /**
     * Last sync timestamp.
     *
     * @var string
     */
    private $last_sync_time;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api              = Ecwid_Api::get_instance();
        $this->order_mapper     = new Order_Mapper();
        $this->customer_mapper  = new Customer_Mapper();
        $this->mapping_repo     = Mapping_Repository::get_instance();
        $this->logger           = Logger::get_instance();
        $this->batch_size       = (int) get_option( 'ecwid_wc_sync_batch_size', 50 );
        $this->last_sync_time   = get_option( 'ecwid_wc_orders_last_sync', '' );
    }

    /**
     * Import a single Ecwid order to WooCommerce.
     *
     * @param array $ecwid_order Ecwid order data.
     * @param array $options     Import options.
     * @return array{success: bool, wc_id: int|null, message: string}
     */
    public function import_order( $ecwid_order, $options = array() ) {
        $defaults = array(
            'create_customer' => true,
            'update_existing' => true,
            'set_status'      => true,
        );
        $options = wp_parse_args( $options, $defaults );

        $ecwid_id = $ecwid_order['id'] ?? null;

        if ( ! $ecwid_id ) {
            return array(
                'success' => false,
                'wc_id'   => null,
                'message' => __( 'Invalid order data: missing ID.', 'ecwid-woocommerce' ),
            );
        }

        $this->logger->debug(
            sprintf( 'Starting import of Ecwid order #%s', $ecwid_id ),
            array( 'order_number' => $ecwid_order['orderNumber'] ?? '' ),
            'Order_Sync'
        );

        // Validate order data.
        $validation = $this->order_mapper->validate_ecwid_order( $ecwid_order );
        if ( ! $validation['valid'] ) {
            $error_msg = implode( ' ', $validation['errors'] );
            $this->logger->warning(
                sprintf( 'Validation failed for Ecwid order #%s: %s', $ecwid_id, $error_msg ),
                array(),
                'Order_Sync'
            );

            return array(
                'success' => false,
                'wc_id'   => null,
                'message' => $error_msg,
            );
        }

        // Check if order already exists.
        $mapping = $this->mapping_repo->find_by_ecwid_id( 'order', $ecwid_id );

        if ( $mapping && ! empty( $mapping->wc_id ) ) {
            if ( $options['update_existing'] ) {
                return $this->update_wc_order( (int) $mapping->wc_id, $ecwid_order, $options );
            } else {
                $this->results['skipped']++;
                return array(
                    'success' => true,
                    'wc_id'   => (int) $mapping->wc_id,
                    'message' => __( 'Order already exists.', 'ecwid-woocommerce' ),
                );
            }
        }

        // Create new order.
        return $this->create_wc_order( $ecwid_order, $options );
    }

    /**
     * Create a new WooCommerce order from Ecwid data.
     *
     * @param array $ecwid_order Ecwid order data.
     * @param array $options     Import options.
     * @return array{success: bool, wc_id: int|null, message: string}
     */
    private function create_wc_order( $ecwid_order, $options ) {
        $ecwid_id = $ecwid_order['id'];

        try {
            // Create or find customer.
            $customer_id = 0;
            if ( $options['create_customer'] && ! empty( $ecwid_order['customerId'] ) ) {
                $customer_id = $this->import_customer_for_order( $ecwid_order );
            }

            // Convert to WC format.
            $wc_data = $this->order_mapper->ecwid_to_wc( $ecwid_order, $options );

            if ( $customer_id ) {
                $wc_data['customer_id'] = $customer_id;
            }

            // Create order.
            $order = wc_create_order( $wc_data );

            if ( is_wp_error( $order ) ) {
                $this->handle_error( $ecwid_id, $order );
                return array(
                    'success' => false,
                    'wc_id'   => null,
                    'message' => $order->get_error_message(),
                );
            }

            $wc_id = $order->get_id();

            // Set billing address.
            if ( ! empty( $wc_data['billing'] ) ) {
                $order->set_address( $wc_data['billing'], 'billing' );
            }

            // Set shipping address.
            if ( ! empty( $wc_data['shipping'] ) ) {
                $order->set_address( $wc_data['shipping'], 'shipping' );
            }

            // Add line items.
            if ( ! empty( $wc_data['line_items'] ) ) {
                $this->add_line_items( $order, $wc_data['line_items'] );
            }

            // Add shipping lines.
            if ( ! empty( $wc_data['shipping_lines'] ) ) {
                $this->add_shipping_lines( $order, $wc_data['shipping_lines'] );
            }

            // Add coupon lines.
            if ( ! empty( $wc_data['coupon_lines'] ) ) {
                $this->add_coupon_lines( $order, $wc_data['coupon_lines'] );
            }

            // Set payment method.
            if ( ! empty( $wc_data['payment_method'] ) ) {
                $order->set_payment_method( $wc_data['payment_method'] );
                $order->set_payment_method_title( $wc_data['payment_method_title'] ?? '' );
            }

            // Set currency.
            if ( ! empty( $wc_data['currency'] ) ) {
                $order->set_currency( $wc_data['currency'] );
            }

            // Set customer note.
            if ( ! empty( $wc_data['customer_note'] ) ) {
                $order->set_customer_note( $wc_data['customer_note'] );
            }

            // Add meta data.
            if ( ! empty( $wc_data['meta_data'] ) ) {
                foreach ( $wc_data['meta_data'] as $meta ) {
                    $order->update_meta_data( $meta['key'], $meta['value'] );
                }
            }

            // Calculate totals.
            $order->calculate_totals();

            // Set status.
            if ( $options['set_status'] && ! empty( $wc_data['status'] ) ) {
                $order->set_status( $wc_data['status'], __( 'Order imported from Ecwid.', 'ecwid-woocommerce' ) );
            }

            // Set dates.
            if ( ! empty( $ecwid_order['createDate'] ) ) {
                $order->set_date_created( $this->parse_date( $ecwid_order['createDate'] ) );
            }

            // Mark as paid if applicable.
            if ( ! empty( $wc_data['set_paid'] ) ) {
                $order->set_date_paid( current_time( 'timestamp' ) );
            }

            $order->save();

            // Save mapping.
            $this->mapping_repo->save( 'order', $wc_id, $ecwid_id, array(
                'sync_direction'  => 'ecwid_to_wc',
                'ecwid_updated_at' => $ecwid_order['updateDate'] ?? null,
            ) );

            $this->results['created']++;

            $this->logger->info(
                sprintf( 'Created WC order #%d from Ecwid order #%s', $wc_id, $ecwid_id ),
                array( 'order_number' => $ecwid_order['orderNumber'] ?? '' ),
                'Order_Sync'
            );

            return array(
                'success' => true,
                'wc_id'   => $wc_id,
                'message' => sprintf( __( 'Order created (ID: %d)', 'ecwid-woocommerce' ), $wc_id ),
            );

        } catch ( \Exception $e ) {
            $this->handle_error( $ecwid_id, new \WP_Error( 'order_create_failed', $e->getMessage() ) );
            return array(
                'success' => false,
                'wc_id'   => null,
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Update an existing WooCommerce order from Ecwid data.
     *
     * @param int   $wc_id       WooCommerce order ID.
     * @param array $ecwid_order Ecwid order data.
     * @param array $options     Import options.
     * @return array{success: bool, wc_id: int|null, message: string}
     */
    private function update_wc_order( $wc_id, $ecwid_order, $options ) {
        $ecwid_id = $ecwid_order['id'];

        try {
            $order = wc_get_order( $wc_id );

            if ( ! $order ) {
                // Order was deleted, recreate.
                $this->mapping_repo->delete_by_wc_id( 'order', $wc_id );
                return $this->create_wc_order( $ecwid_order, $options );
            }

            // Update status.
            if ( $options['set_status'] ) {
                $new_status = $this->order_mapper->map_status_to_wc( $ecwid_order );
                $current_status = $order->get_status();

                if ( $new_status !== $current_status ) {
                    $order->set_status( $new_status, __( 'Status updated from Ecwid.', 'ecwid-woocommerce' ) );
                }
            }

            // Update tracking number if present.
            if ( ! empty( $ecwid_order['trackingNumber'] ) ) {
                $order->update_meta_data( '_ecwid_tracking_number', $ecwid_order['trackingNumber'] );
            }

            // Update private notes.
            if ( ! empty( $ecwid_order['privateAdminNotes'] ) ) {
                $order->update_meta_data( '_ecwid_admin_notes', $ecwid_order['privateAdminNotes'] );
            }

            // Update date modified.
            if ( ! empty( $ecwid_order['updateDate'] ) ) {
                $order->set_date_modified( $this->parse_date( $ecwid_order['updateDate'] ) );
            }

            $order->save();

            // Update mapping.
            $mapping = $this->mapping_repo->find_by_wc_id( 'order', $wc_id );
            if ( $mapping ) {
                $this->mapping_repo->mark_synced( $mapping->id );
            }

            $this->results['updated']++;

            $this->logger->info(
                sprintf( 'Updated WC order #%d from Ecwid order #%s', $wc_id, $ecwid_id ),
                array(),
                'Order_Sync'
            );

            return array(
                'success' => true,
                'wc_id'   => $wc_id,
                'message' => sprintf( __( 'Order updated (ID: %d)', 'ecwid-woocommerce' ), $wc_id ),
            );

        } catch ( \Exception $e ) {
            $this->handle_error( $ecwid_id, new \WP_Error( 'order_update_failed', $e->getMessage() ) );
            return array(
                'success' => false,
                'wc_id'   => $wc_id,
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Import customer for order.
     *
     * @param array $ecwid_order Ecwid order data.
     * @return int Customer ID (0 for guest).
     */
    private function import_customer_for_order( $ecwid_order ) {
        $ecwid_customer_id = $ecwid_order['customerId'] ?? null;

        if ( ! $ecwid_customer_id ) {
            return 0;
        }

        // Check if customer is already mapped.
        $mapping = $this->mapping_repo->find_by_ecwid_id( 'customer', $ecwid_customer_id );
        if ( $mapping && ! empty( $mapping->wc_id ) ) {
            return (int) $mapping->wc_id;
        }

        // Fetch customer details from Ecwid.
        $customer_data = $this->api->get_customer( $ecwid_customer_id );

        if ( is_wp_error( $customer_data ) ) {
            $this->logger->warning(
                sprintf( 'Could not fetch Ecwid customer #%s: %s', $ecwid_customer_id, $customer_data->get_error_message() ),
                array(),
                'Order_Sync'
            );

            // Try to create customer from order data.
            $customer_from_order = array(
                'id'    => $ecwid_customer_id,
                'email' => $ecwid_order['email'] ?? '',
                'name'  => $ecwid_order['billingPerson']['name'] ?? '',
            );

            if ( ! empty( $ecwid_order['billingPerson'] ) ) {
                $customer_from_order['billingPerson'] = $ecwid_order['billingPerson'];
            }

            if ( ! empty( $ecwid_order['shippingPerson'] ) ) {
                $customer_from_order['shippingAddresses'] = array( $ecwid_order['shippingPerson'] );
            }

            $customer_data = $customer_from_order;
        }

        // Create or find WC customer.
        $wc_customer_id = $this->customer_mapper->find_or_create_wc_customer( $customer_data );

        if ( $wc_customer_id ) {
            // Save mapping.
            $this->mapping_repo->save( 'customer', $wc_customer_id, $ecwid_customer_id, array(
                'sync_direction' => 'ecwid_to_wc',
            ) );
        }

        return $wc_customer_id ?: 0;
    }

    /**
     * Add line items to order.
     *
     * @param \WC_Order $order      WooCommerce order.
     * @param array     $line_items Line items data.
     * @return void
     */
    private function add_line_items( $order, $line_items ) {
        foreach ( $line_items as $item_data ) {
            $item = new \WC_Order_Item_Product();

            $item->set_name( $item_data['name'] ?? '' );
            $item->set_quantity( $item_data['quantity'] ?? 1 );
            $item->set_subtotal( $item_data['subtotal'] ?? 0 );
            $item->set_total( $item_data['total'] ?? 0 );

            if ( ! empty( $item_data['product_id'] ) ) {
                $product = wc_get_product( $item_data['product_id'] );
                if ( $product ) {
                    $item->set_product( $product );
                }
            }

            if ( ! empty( $item_data['variation_id'] ) ) {
                $item->set_variation_id( $item_data['variation_id'] );
            }

            if ( ! empty( $item_data['total_tax'] ) ) {
                $item->set_total_tax( $item_data['total_tax'] );
            }

            // Add meta data.
            if ( ! empty( $item_data['meta_data'] ) ) {
                foreach ( $item_data['meta_data'] as $meta ) {
                    $item->add_meta_data( $meta['key'], $meta['value'] );
                }
            }

            $order->add_item( $item );
        }
    }

    /**
     * Add shipping lines to order.
     *
     * @param \WC_Order $order          WooCommerce order.
     * @param array     $shipping_lines Shipping lines data.
     * @return void
     */
    private function add_shipping_lines( $order, $shipping_lines ) {
        foreach ( $shipping_lines as $shipping_data ) {
            $item = new \WC_Order_Item_Shipping();

            $item->set_method_title( $shipping_data['method_title'] ?? '' );
            $item->set_method_id( $shipping_data['method_id'] ?? 'flat_rate' );
            $item->set_total( $shipping_data['total'] ?? 0 );

            $order->add_item( $item );
        }
    }

    /**
     * Add coupon lines to order.
     *
     * @param \WC_Order $order        WooCommerce order.
     * @param array     $coupon_lines Coupon lines data.
     * @return void
     */
    private function add_coupon_lines( $order, $coupon_lines ) {
        foreach ( $coupon_lines as $coupon_data ) {
            $coupon_code = $coupon_data['code'] ?? '';
            if ( empty( $coupon_code ) ) {
                continue;
            }

            $item = new \WC_Order_Item_Coupon();
            $item->set_code( $coupon_code );
            $item->set_discount( $coupon_data['discount'] ?? 0 );

            $order->add_item( $item );
        }
    }

    /**
     * Import orders from Ecwid with date filter.
     *
     * @param array $params Query parameters.
     * @return array{success: bool, results: array, errors: array}
     */
    public function import_orders( $params = array() ) {
        $this->reset_results();

        $defaults = array(
            'limit'           => $this->batch_size,
            'offset'          => 0,
            'updatedFrom'     => $this->last_sync_time,
            'create_customer' => true,
        );

        $params = wp_parse_args( $params, $defaults );

        $this->logger->info(
            'Starting order import from Ecwid',
            array( 'params' => $params ),
            'Order_Sync'
        );

        // Fetch orders from Ecwid.
        $response = $this->api->get_orders( $params );

        if ( is_wp_error( $response ) ) {
            $this->logger->error(
                'Failed to fetch orders from Ecwid: ' . $response->get_error_message(),
                array(),
                'Order_Sync'
            );

            return array(
                'success' => false,
                'results' => $this->results,
                'errors'  => array( $response->get_error_message() ),
            );
        }

        $orders = $response['items'] ?? array();

        if ( empty( $orders ) ) {
            $this->logger->info( 'No orders to import', array(), 'Order_Sync' );
            return array(
                'success' => true,
                'results' => $this->results,
                'errors'  => array(),
            );
        }

        $this->logger->info(
            sprintf( 'Found %d orders to import', count( $orders ) ),
            array(),
            'Order_Sync'
        );

        // Process each order.
        foreach ( $orders as $ecwid_order ) {
            $result = $this->import_order( $ecwid_order, array(
                'create_customer' => $params['create_customer'],
            ) );

            if ( ! $result['success'] ) {
                $this->error_details[] = array(
                    'ecwid_id' => $ecwid_order['id'] ?? 'unknown',
                    'message'  => $result['message'],
                );
            }

            // Check time limit.
            if ( $this->is_time_limit_reached() ) {
                $this->logger->warning(
                    'Order import stopped due to time limit',
                    array(),
                    'Order_Sync'
                );
                break;
            }
        }

        // Update last sync time.
        update_option( 'ecwid_wc_orders_last_sync', current_time( 'mysql' ) );

        $this->logger->info(
            sprintf(
                'Order import completed: %d created, %d updated, %d skipped, %d errors',
                $this->results['created'],
                $this->results['updated'],
                $this->results['skipped'],
                $this->results['errors']
            ),
            array(),
            'Order_Sync'
        );

        return array(
            'success' => empty( $this->error_details ),
            'results' => $this->results,
            'errors'  => $this->error_details,
        );
    }

    /**
     * Import new orders only (since last sync).
     *
     * @return array{success: bool, results: array, errors: array}
     */
    public function import_new_orders() {
        return $this->import_orders( array(
            'updatedFrom' => $this->last_sync_time,
        ) );
    }

    /**
     * Import all orders (full sync).
     *
     * @param int $limit Maximum orders to import.
     * @return array{success: bool, results: array, errors: array}
     */
    public function import_all_orders( $limit = 100 ) {
        return $this->import_orders( array(
            'limit'       => $limit,
            'updatedFrom' => '',
        ) );
    }

    /**
     * Sync order status from Ecwid to WooCommerce.
     *
     * @param string $ecwid_order_id Ecwid order ID.
     * @return array{success: bool, message: string}
     */
    public function sync_order_status( $ecwid_order_id ) {
        // Fetch order from Ecwid.
        $ecwid_order = $this->api->get_order( $ecwid_order_id );

        if ( is_wp_error( $ecwid_order ) ) {
            return array(
                'success' => false,
                'message' => $ecwid_order->get_error_message(),
            );
        }

        // Find WC order.
        $mapping = $this->mapping_repo->find_by_ecwid_id( 'order', $ecwid_order_id );

        if ( ! $mapping || empty( $mapping->wc_id ) ) {
            // Order not found, import it.
            return $this->import_order( $ecwid_order );
        }

        // Update status only.
        return $this->update_wc_order( (int) $mapping->wc_id, $ecwid_order, array(
            'set_status'      => true,
            'update_existing' => true,
        ) );
    }

    /**
     * Update Ecwid order status from WooCommerce.
     *
     * @param int    $wc_order_id WooCommerce order ID.
     * @param string $new_status  New WC status.
     * @return array{success: bool, message: string}
     */
    public function update_ecwid_status( $wc_order_id, $new_status ) {
        $mapping = $this->mapping_repo->find_by_wc_id( 'order', $wc_order_id );

        if ( ! $mapping || empty( $mapping->ecwid_id ) ) {
            return array(
                'success' => false,
                'message' => __( 'Order not found in Ecwid mapping.', 'ecwid-woocommerce' ),
            );
        }

        $ecwid_id = $mapping->ecwid_id;
        $statuses = $this->order_mapper->map_status_to_ecwid( $new_status );

        $response = $this->api->update_order( $ecwid_id, $statuses );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $this->mapping_repo->mark_synced( $mapping->id );

        $this->logger->info(
            sprintf( 'Updated Ecwid order #%s status from WC order #%d', $ecwid_id, $wc_order_id ),
            array( 'status' => $new_status ),
            'Order_Sync'
        );

        return array(
            'success' => true,
            'message' => __( 'Order status updated in Ecwid.', 'ecwid-woocommerce' ),
        );
    }

    /**
     * Parse date string.
     *
     * @param string $date_string Date string.
     * @return int|null Timestamp or null.
     */
    private function parse_date( $date_string ) {
        if ( empty( $date_string ) ) {
            return null;
        }

        $timestamp = strtotime( $date_string );
        return $timestamp !== false ? $timestamp : null;
    }

    /**
     * Handle error.
     *
     * @param string    $ecwid_id Ecwid order ID.
     * @param \WP_Error $error    WP_Error object.
     * @return void
     */
    private function handle_error( $ecwid_id, $error ) {
        $this->results['errors']++;

        $mapping = $this->mapping_repo->find_by_ecwid_id( 'order', $ecwid_id );
        if ( $mapping ) {
            $this->mapping_repo->mark_error( $mapping->id, $error->get_error_message() );
        }

        $this->logger->error(
            sprintf( 'Error for Ecwid order #%s: %s', $ecwid_id, $error->get_error_message() ),
            array( 'error_code' => $error->get_error_code() ),
            'Order_Sync'
        );
    }

    /**
     * Reset sync results.
     *
     * @return void
     */
    private function reset_results() {
        $this->results = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
        );
        $this->error_details = array();
    }

    /**
     * Check if time limit is reached.
     *
     * @return bool
     */
    private function is_time_limit_reached() {
        $max_execution_time = (int) ini_get( 'max_execution_time' );

        if ( 0 === $max_execution_time ) {
            return false;
        }

        $time_elapsed = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];
        return $time_elapsed > ( $max_execution_time - 10 );
    }

    /**
     * Get sync results.
     *
     * @return array
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * Get error details.
     *
     * @return array
     */
    public function get_errors() {
        return $this->error_details;
    }

    /**
     * Get last sync time.
     *
     * @return string
     */
    public function get_last_sync_time() {
        return $this->last_sync_time;
    }
}
