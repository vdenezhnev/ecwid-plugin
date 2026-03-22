<?php
/**
 * Ecwid Order Sync
 *
 * Handles order synchronization between Ecwid and WooCommerce
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Order Sync class
 */
class Ecwid_Order_Sync {

    /**
     * API client instance
     *
     * @var Ecwid_API_Client
     */
    private $api;

    /**
     * Customer mapper instance
     *
     * @var Ecwid_Customer_Mapper
     */
    private $customer_mapper;

    /**
     * Payment mapper instance
     *
     * @var Ecwid_Payment_Mapper
     */
    private $payment_mapper;

    /**
     * Status mapper instance
     *
     * @var Ecwid_Status_Mapper
     */
    private $status_mapper;

    /**
     * Logger instance
     *
     * @var Ecwid_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Ecwid_API_Client $api API client instance.
     */
    public function __construct( Ecwid_API_Client $api ) {
        $this->api             = $api;
        $this->customer_mapper = new Ecwid_Customer_Mapper();
        $this->payment_mapper  = new Ecwid_Payment_Mapper();
        $this->status_mapper   = new Ecwid_Status_Mapper();
        $this->logger          = new Ecwid_Logger();

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into WooCommerce order status changes to sync back to Ecwid
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
    }

    /**
     * Sync orders since a specific date
     *
     * @param string $since_date ISO 8601 date string.
     * @return array|WP_Error
     */
    public function sync_orders_since( $since_date ) {
        $this->logger->info( 'Starting sync from date', array( 'since' => $since_date ) );

        $result = $this->api->get_orders_updated_since( $since_date );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->process_orders( $result['items'] ?? array() );
    }

    /**
     * Sync all orders
     *
     * @param string $from_date Start date (ISO 8601).
     * @return array|WP_Error
     */
    public function sync_all_orders( $from_date = '' ) {
        $this->logger->info( 'Starting full sync', array( 'from' => $from_date ) );

        $params = array();
        
        if ( ! empty( $from_date ) ) {
            $params['createdFrom'] = $from_date;
        }

        $result = $this->api->get_all_orders( $params );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->process_orders( $result['items'] ?? array() );
    }

    /**
     * Process orders
     *
     * @param array $orders Ecwid orders.
     * @return array
     */
    private function process_orders( $orders ) {
        $stats = array(
            'imported' => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => 0,
        );

        foreach ( $orders as $ecwid_order ) {
            $ecwid_order_id = $ecwid_order['id'] ?? 0;

            if ( ! $ecwid_order_id ) {
                $stats['skipped']++;
                continue;
            }

            // Check if order already exists
            $woo_order_id = $this->get_woo_order_id_by_ecwid_id( $ecwid_order_id );

            if ( $woo_order_id ) {
                // Update existing order
                $result = $this->update_existing_order( $woo_order_id, $ecwid_order );
                
                if ( is_wp_error( $result ) ) {
                    $stats['errors']++;
                    $this->logger->error( 'Failed to update order', array(
                        'ecwid_order_id' => $ecwid_order_id,
                        'error'          => $result->get_error_message(),
                    ) );
                } else {
                    $stats['updated']++;
                }
            } else {
                // Create new order
                $result = $this->create_woo_order( $ecwid_order );
                
                if ( is_wp_error( $result ) ) {
                    $stats['errors']++;
                    $this->logger->error( 'Failed to import order', array(
                        'ecwid_order_id' => $ecwid_order_id,
                        'error'          => $result->get_error_message(),
                    ) );
                } else {
                    $stats['imported']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Import single order
     *
     * @param int $ecwid_order_id Ecwid order ID.
     * @return int|WP_Error WooCommerce order ID or error.
     */
    public function import_order( $ecwid_order_id ) {
        // Get order from Ecwid API
        $ecwid_order = $this->api->get_order( $ecwid_order_id );

        if ( is_wp_error( $ecwid_order ) ) {
            return $ecwid_order;
        }

        // Check if already exists
        $existing_woo_id = $this->get_woo_order_id_by_ecwid_id( $ecwid_order_id );

        if ( $existing_woo_id ) {
            return $this->update_existing_order( $existing_woo_id, $ecwid_order );
        }

        return $this->create_woo_order( $ecwid_order );
    }

    /**
     * Update existing order
     *
     * @param int   $ecwid_order_id Ecwid order ID.
     * @return int|WP_Error WooCommerce order ID or error.
     */
    public function update_order( $ecwid_order_id ) {
        // Get order from Ecwid API
        $ecwid_order = $this->api->get_order( $ecwid_order_id );

        if ( is_wp_error( $ecwid_order ) ) {
            return $ecwid_order;
        }

        // Get WooCommerce order ID
        $woo_order_id = $this->get_woo_order_id_by_ecwid_id( $ecwid_order_id );

        if ( ! $woo_order_id ) {
            // Create if doesn't exist
            return $this->create_woo_order( $ecwid_order );
        }

        return $this->update_existing_order( $woo_order_id, $ecwid_order );
    }

    /**
     * Create WooCommerce order from Ecwid order data
     *
     * @param array $ecwid_order Ecwid order data.
     * @return int|WP_Error WooCommerce order ID or error.
     */
    private function create_woo_order( $ecwid_order ) {
        $ecwid_order_id = $ecwid_order['id'];

        $this->logger->info( 'Creating WooCommerce order', array( 'ecwid_order_id' => $ecwid_order_id ) );

        try {
            // Create order
            $woo_order = wc_create_order();

            if ( is_wp_error( $woo_order ) ) {
                return $woo_order;
            }

            // Map customer
            $customer_id = $this->customer_mapper->map_customer( $ecwid_order );
            
            if ( $customer_id ) {
                $woo_order->set_customer_id( $customer_id );
            }

            // Set billing address
            $this->set_order_addresses( $woo_order, $ecwid_order );

            // Add order items
            $this->add_order_items( $woo_order, $ecwid_order );

            // Set shipping
            $this->set_order_shipping( $woo_order, $ecwid_order );

            // Set payment method
            $payment_method = $this->payment_mapper->map_payment_method( $ecwid_order );
            $woo_order->set_payment_method( $payment_method['id'] );
            $woo_order->set_payment_method_title( $payment_method['title'] );

            // Set order status
            $woo_status = $this->status_mapper->ecwid_to_woo( 
                $ecwid_order['paymentStatus'] ?? 'INCOMPLETE',
                $ecwid_order['fulfillmentStatus'] ?? 'AWAITING_PROCESSING'
            );
            $woo_order->set_status( $woo_status, __( 'Order imported from Ecwid.', 'ecwid-woocommerce-sync' ) );

            // Set order dates
            if ( ! empty( $ecwid_order['createDate'] ) ) {
                $woo_order->set_date_created( strtotime( $ecwid_order['createDate'] ) );
            }

            // Set order totals
            $this->set_order_totals( $woo_order, $ecwid_order );

            // Store Ecwid metadata
            $this->set_ecwid_metadata( $woo_order, $ecwid_order );

            // Save order
            $woo_order->save();

            $woo_order_id = $woo_order->get_id();

            // Record sync
            $ecwid_customer_id = $ecwid_order['customerId'] ?? null;
            $this->record_sync( $ecwid_order_id, $woo_order_id, $ecwid_customer_id, $customer_id );

            $this->logger->info( 'WooCommerce order created', array(
                'ecwid_order_id' => $ecwid_order_id,
                'woo_order_id'   => $woo_order_id,
            ) );

            return $woo_order_id;

        } catch ( Exception $e ) {
            $this->logger->error( 'Exception creating order', array(
                'ecwid_order_id' => $ecwid_order_id,
                'error'          => $e->getMessage(),
            ) );
            return new WP_Error( 'order_creation_failed', $e->getMessage() );
        }
    }

    /**
     * Update existing WooCommerce order
     *
     * @param int   $woo_order_id WooCommerce order ID.
     * @param array $ecwid_order  Ecwid order data.
     * @return int|WP_Error WooCommerce order ID or error.
     */
    private function update_existing_order( $woo_order_id, $ecwid_order ) {
        $ecwid_order_id = $ecwid_order['id'];

        $this->logger->info( 'Updating WooCommerce order', array(
            'ecwid_order_id' => $ecwid_order_id,
            'woo_order_id'   => $woo_order_id,
        ) );

        $woo_order = wc_get_order( $woo_order_id );

        if ( ! $woo_order ) {
            return new WP_Error( 'order_not_found', __( 'WooCommerce order not found.', 'ecwid-woocommerce-sync' ) );
        }

        try {
            // Update status
            $woo_status = $this->status_mapper->ecwid_to_woo(
                $ecwid_order['paymentStatus'] ?? 'INCOMPLETE',
                $ecwid_order['fulfillmentStatus'] ?? 'AWAITING_PROCESSING'
            );

            $current_status = $woo_order->get_status();
            
            if ( $current_status !== $woo_status ) {
                $woo_order->set_status( $woo_status, __( 'Status updated from Ecwid.', 'ecwid-woocommerce-sync' ) );
            }

            // Update addresses if changed
            $this->set_order_addresses( $woo_order, $ecwid_order );

            // Update metadata
            $this->set_ecwid_metadata( $woo_order, $ecwid_order );

            // Save order
            $woo_order->save();

            // Update sync record
            $this->update_sync_record( $ecwid_order_id, $woo_order_id, 'synced' );

            $this->logger->info( 'WooCommerce order updated', array(
                'ecwid_order_id' => $ecwid_order_id,
                'woo_order_id'   => $woo_order_id,
            ) );

            return $woo_order_id;

        } catch ( Exception $e ) {
            $this->logger->error( 'Exception updating order', array(
                'ecwid_order_id' => $ecwid_order_id,
                'woo_order_id'   => $woo_order_id,
                'error'          => $e->getMessage(),
            ) );
            return new WP_Error( 'order_update_failed', $e->getMessage() );
        }
    }

    /**
     * Set order addresses
     *
     * @param WC_Order $woo_order   WooCommerce order.
     * @param array    $ecwid_order Ecwid order data.
     */
    private function set_order_addresses( $woo_order, $ecwid_order ) {
        // Billing address
        $billing = $ecwid_order['billingPerson'] ?? $ecwid_order['shippingPerson'] ?? array();

        if ( ! empty( $billing ) ) {
            $woo_order->set_billing_first_name( $billing['firstName'] ?? '' );
            $woo_order->set_billing_last_name( $billing['lastName'] ?? '' );
            $woo_order->set_billing_company( $billing['companyName'] ?? '' );
            $woo_order->set_billing_address_1( $billing['street'] ?? '' );
            $woo_order->set_billing_city( $billing['city'] ?? '' );
            $woo_order->set_billing_state( $billing['stateOrProvinceCode'] ?? $billing['stateOrProvinceName'] ?? '' );
            $woo_order->set_billing_postcode( $billing['postalCode'] ?? '' );
            $woo_order->set_billing_country( $billing['countryCode'] ?? '' );
            $woo_order->set_billing_email( $ecwid_order['email'] ?? '' );
            $woo_order->set_billing_phone( $billing['phone'] ?? '' );
        }

        // Shipping address
        $shipping = $ecwid_order['shippingPerson'] ?? array();

        if ( ! empty( $shipping ) ) {
            $woo_order->set_shipping_first_name( $shipping['firstName'] ?? '' );
            $woo_order->set_shipping_last_name( $shipping['lastName'] ?? '' );
            $woo_order->set_shipping_company( $shipping['companyName'] ?? '' );
            $woo_order->set_shipping_address_1( $shipping['street'] ?? '' );
            $woo_order->set_shipping_city( $shipping['city'] ?? '' );
            $woo_order->set_shipping_state( $shipping['stateOrProvinceCode'] ?? $shipping['stateOrProvinceName'] ?? '' );
            $woo_order->set_shipping_postcode( $shipping['postalCode'] ?? '' );
            $woo_order->set_shipping_country( $shipping['countryCode'] ?? '' );
        }
    }

    /**
     * Add order items
     *
     * @param WC_Order $woo_order   WooCommerce order.
     * @param array    $ecwid_order Ecwid order data.
     */
    private function add_order_items( $woo_order, $ecwid_order ) {
        $items = $ecwid_order['items'] ?? array();

        foreach ( $items as $item ) {
            $product_id = $this->find_woo_product_id( $item );

            if ( $product_id ) {
                $product = wc_get_product( $product_id );
                $woo_order->add_product( $product, $item['quantity'], array(
                    'subtotal' => $item['price'] * $item['quantity'],
                    'total'    => ( $item['price'] - ( $item['couponAmount'] ?? 0 ) ) * $item['quantity'],
                ) );
            } else {
                // Add as line item without product reference
                $order_item = new WC_Order_Item_Product();
                $order_item->set_name( $item['name'] ?? __( 'Product', 'ecwid-woocommerce-sync' ) );
                $order_item->set_quantity( $item['quantity'] );
                $order_item->set_subtotal( $item['price'] * $item['quantity'] );
                $order_item->set_total( ( $item['price'] - ( $item['couponAmount'] ?? 0 ) ) * $item['quantity'] );

                // Store Ecwid product data
                $order_item->add_meta_data( '_ecwid_product_id', $item['productId'] ?? '', true );
                $order_item->add_meta_data( '_ecwid_sku', $item['sku'] ?? '', true );

                $woo_order->add_item( $order_item );
            }
        }
    }

    /**
     * Find WooCommerce product ID by Ecwid item data
     *
     * @param array $item Ecwid order item.
     * @return int|false
     */
    private function find_woo_product_id( $item ) {
        // Try to find by SKU first
        if ( ! empty( $item['sku'] ) ) {
            $product_id = wc_get_product_id_by_sku( $item['sku'] );
            if ( $product_id ) {
                return $product_id;
            }
        }

        // Try to find by Ecwid product ID metadata
        if ( ! empty( $item['productId'] ) ) {
            global $wpdb;

            $product_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ecwid_product_id' AND meta_value = %s LIMIT 1",
                    $item['productId']
                )
            );

            if ( $product_id ) {
                return intval( $product_id );
            }
        }

        return false;
    }

    /**
     * Set order shipping
     *
     * @param WC_Order $woo_order   WooCommerce order.
     * @param array    $ecwid_order Ecwid order data.
     */
    private function set_order_shipping( $woo_order, $ecwid_order ) {
        $shipping_option = $ecwid_order['shippingOption'] ?? array();
        $shipping_cost   = $ecwid_order['shippingAndHandling'] ?? 0;

        if ( $shipping_cost > 0 || ! empty( $shipping_option ) ) {
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title( $shipping_option['shippingMethodName'] ?? __( 'Shipping', 'ecwid-woocommerce-sync' ) );
            $shipping_item->set_total( $shipping_cost );

            $woo_order->add_item( $shipping_item );
        }
    }

    /**
     * Set order totals
     *
     * @param WC_Order $woo_order   WooCommerce order.
     * @param array    $ecwid_order Ecwid order data.
     */
    private function set_order_totals( $woo_order, $ecwid_order ) {
        // Tax
        $tax = $ecwid_order['tax'] ?? 0;
        if ( $tax > 0 ) {
            $tax_item = new WC_Order_Item_Tax();
            $tax_item->set_rate_id( 0 );
            $tax_item->set_label( __( 'Tax', 'ecwid-woocommerce-sync' ) );
            $tax_item->set_tax_total( $tax );
            $tax_item->set_shipping_tax_total( 0 );
            $woo_order->add_item( $tax_item );
        }

        // Discount
        $discount = $ecwid_order['discount'] ?? 0;
        if ( $discount > 0 ) {
            $woo_order->set_discount_total( $discount );
        }

        // Coupon
        $coupon_discount = $ecwid_order['couponDiscount'] ?? 0;
        if ( $coupon_discount > 0 ) {
            $coupon = $ecwid_order['discountCoupon'] ?? array();
            $coupon_item = new WC_Order_Item_Coupon();
            $coupon_item->set_code( $coupon['code'] ?? 'ecwid_coupon' );
            $coupon_item->set_discount( $coupon_discount );
            $woo_order->add_item( $coupon_item );
        }

        // Calculate totals
        $woo_order->calculate_totals( false );
    }

    /**
     * Set Ecwid metadata on order
     *
     * @param WC_Order $woo_order   WooCommerce order.
     * @param array    $ecwid_order Ecwid order data.
     */
    private function set_ecwid_metadata( $woo_order, $ecwid_order ) {
        $woo_order->update_meta_data( '_ecwid_order_id', $ecwid_order['id'] );
        $woo_order->update_meta_data( '_ecwid_order_number', $ecwid_order['orderNumber'] ?? '' );
        $woo_order->update_meta_data( '_ecwid_payment_status', $ecwid_order['paymentStatus'] ?? '' );
        $woo_order->update_meta_data( '_ecwid_fulfillment_status', $ecwid_order['fulfillmentStatus'] ?? '' );
        $woo_order->update_meta_data( '_ecwid_payment_method', $ecwid_order['paymentMethod'] ?? '' );
        $woo_order->update_meta_data( '_ecwid_customer_id', $ecwid_order['customerId'] ?? '' );
        $woo_order->update_meta_data( '_ecwid_tracking_number', $ecwid_order['trackingNumber'] ?? '' );
        $woo_order->update_meta_data( '_ecwid_last_sync', gmdate( 'c' ) );

        // Store order notes if present
        if ( ! empty( $ecwid_order['orderComments'] ) ) {
            $woo_order->add_order_note(
                sprintf(
                    /* translators: %s: customer comments */
                    __( 'Customer note from Ecwid: %s', 'ecwid-woocommerce-sync' ),
                    sanitize_textarea_field( $ecwid_order['orderComments'] )
                ),
                false,
                true
            );
        }
    }

    /**
     * Record sync in database
     *
     * @param int      $ecwid_order_id    Ecwid order ID.
     * @param int      $woo_order_id      WooCommerce order ID.
     * @param int|null $ecwid_customer_id Ecwid customer ID.
     * @param int|null $woo_customer_id   WooCommerce customer ID.
     */
    private function record_sync( $ecwid_order_id, $woo_order_id, $ecwid_customer_id = null, $woo_customer_id = null ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_synced_orders';

        $wpdb->replace(
            $table_name,
            array(
                'ecwid_order_id'    => $ecwid_order_id,
                'woo_order_id'      => $woo_order_id,
                'ecwid_customer_id' => $ecwid_customer_id,
                'woo_customer_id'   => $woo_customer_id,
                'last_synced'       => current_time( 'mysql' ),
                'sync_status'       => 'synced',
            ),
            array( '%d', '%d', '%d', '%d', '%s', '%s' )
        );
    }

    /**
     * Update sync record
     *
     * @param int    $ecwid_order_id Ecwid order ID.
     * @param int    $woo_order_id   WooCommerce order ID.
     * @param string $status         Sync status.
     */
    public function update_sync_record( $ecwid_order_id, $woo_order_id, $status = 'synced' ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_synced_orders';

        $wpdb->update(
            $table_name,
            array(
                'last_synced'  => current_time( 'mysql' ),
                'sync_status'  => $status,
            ),
            array( 'ecwid_order_id' => $ecwid_order_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Get WooCommerce order ID by Ecwid order ID
     *
     * @param int $ecwid_order_id Ecwid order ID.
     * @return int|false
     */
    public function get_woo_order_id_by_ecwid_id( $ecwid_order_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_synced_orders';

        // Check sync table first
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT woo_order_id FROM $table_name WHERE ecwid_order_id = %d",
                $ecwid_order_id
            )
        );

        if ( $result ) {
            return intval( $result );
        }

        // Fallback to meta query
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ecwid_order_id' AND meta_value = %s LIMIT 1",
                $ecwid_order_id
            )
        );

        return $result ? intval( $result ) : false;
    }

    /**
     * Get Ecwid order ID by WooCommerce order ID
     *
     * @param int $woo_order_id WooCommerce order ID.
     * @return int|false
     */
    public function get_ecwid_order_id_by_woo_id( $woo_order_id ) {
        $order = wc_get_order( $woo_order_id );
        
        if ( ! $order ) {
            return false;
        }

        $ecwid_order_id = $order->get_meta( '_ecwid_order_id' );

        return $ecwid_order_id ? intval( $ecwid_order_id ) : false;
    }

    /**
     * Handle WooCommerce order status change
     *
     * @param int      $order_id   Order ID.
     * @param string   $old_status Old status.
     * @param string   $new_status New status.
     * @param WC_Order $order      Order object.
     */
    public function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
        // Check if this is an Ecwid synced order
        $ecwid_order_id = $order->get_meta( '_ecwid_order_id' );

        if ( ! $ecwid_order_id ) {
            return;
        }

        // Sync status back to Ecwid
        $this->sync_status_to_ecwid( $order_id );
    }

    /**
     * Sync order status to Ecwid
     *
     * @param int $woo_order_id WooCommerce order ID.
     * @return bool|WP_Error
     */
    public function sync_status_to_ecwid( $woo_order_id ) {
        $order = wc_get_order( $woo_order_id );

        if ( ! $order ) {
            return new WP_Error( 'order_not_found', __( 'Order not found.', 'ecwid-woocommerce-sync' ) );
        }

        $ecwid_order_id = $order->get_meta( '_ecwid_order_id' );

        if ( ! $ecwid_order_id ) {
            return new WP_Error( 'not_ecwid_order', __( 'Order is not synced from Ecwid.', 'ecwid-woocommerce-sync' ) );
        }

        // Map WooCommerce status to Ecwid status
        $woo_status   = $order->get_status();
        $ecwid_status = $this->status_mapper->woo_to_ecwid( $woo_status );

        // Update in Ecwid
        $result = $this->api->update_order_status( $ecwid_order_id, $ecwid_status );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed to sync status to Ecwid', array(
                'woo_order_id'   => $woo_order_id,
                'ecwid_order_id' => $ecwid_order_id,
                'error'          => $result->get_error_message(),
            ) );
            return $result;
        }

        // Update local metadata
        $order->update_meta_data( '_ecwid_fulfillment_status', $ecwid_status );
        $order->update_meta_data( '_ecwid_last_sync', gmdate( 'c' ) );
        $order->save();

        $this->logger->info( 'Status synced to Ecwid', array(
            'woo_order_id'   => $woo_order_id,
            'ecwid_order_id' => $ecwid_order_id,
            'status'         => $ecwid_status,
        ) );

        return true;
    }
}
