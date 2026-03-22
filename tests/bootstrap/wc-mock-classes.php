<?php
/**
 * WooCommerce Mock Classes for Testing
 *
 * @package Ecwid_WooCommerce_Sync
 */

/**
 * Mock WC_Order class
 */
if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {
        protected $id = 0;
        protected $data = array();
        protected $meta_data = array();
        protected $items = array();
        protected $status = 'pending';
        
        public function __construct( $order_id = 0 ) {
            $this->id = $order_id;
        }
        
        public function get_id() {
            return $this->id;
        }
        
        public function set_id( $id ) {
            $this->id = $id;
        }
        
        public function get_status() {
            return $this->status;
        }
        
        public function set_status( $status, $note = '' ) {
            $this->status = str_replace( 'wc-', '', $status );
            return true;
        }
        
        public function update_status( $status, $note = '' ) {
            return $this->set_status( $status, $note );
        }
        
        public function get_meta( $key, $single = true ) {
            if ( $single ) {
                return $this->meta_data[ $key ] ?? '';
            }
            return isset( $this->meta_data[ $key ] ) ? array( $this->meta_data[ $key ] ) : array();
        }
        
        public function update_meta_data( $key, $value ) {
            $this->meta_data[ $key ] = $value;
        }
        
        public function set_customer_id( $id ) {
            $this->data['customer_id'] = $id;
        }
        
        public function set_billing_first_name( $value ) { $this->data['billing_first_name'] = $value; }
        public function set_billing_last_name( $value ) { $this->data['billing_last_name'] = $value; }
        public function set_billing_company( $value ) { $this->data['billing_company'] = $value; }
        public function set_billing_address_1( $value ) { $this->data['billing_address_1'] = $value; }
        public function set_billing_city( $value ) { $this->data['billing_city'] = $value; }
        public function set_billing_state( $value ) { $this->data['billing_state'] = $value; }
        public function set_billing_postcode( $value ) { $this->data['billing_postcode'] = $value; }
        public function set_billing_country( $value ) { $this->data['billing_country'] = $value; }
        public function set_billing_email( $value ) { $this->data['billing_email'] = $value; }
        public function set_billing_phone( $value ) { $this->data['billing_phone'] = $value; }
        
        public function set_shipping_first_name( $value ) { $this->data['shipping_first_name'] = $value; }
        public function set_shipping_last_name( $value ) { $this->data['shipping_last_name'] = $value; }
        public function set_shipping_company( $value ) { $this->data['shipping_company'] = $value; }
        public function set_shipping_address_1( $value ) { $this->data['shipping_address_1'] = $value; }
        public function set_shipping_city( $value ) { $this->data['shipping_city'] = $value; }
        public function set_shipping_state( $value ) { $this->data['shipping_state'] = $value; }
        public function set_shipping_postcode( $value ) { $this->data['shipping_postcode'] = $value; }
        public function set_shipping_country( $value ) { $this->data['shipping_country'] = $value; }
        
        public function set_payment_method( $value ) { $this->data['payment_method'] = $value; }
        public function set_payment_method_title( $value ) { $this->data['payment_method_title'] = $value; }
        public function set_transaction_id( $value ) { $this->data['transaction_id'] = $value; }
        public function set_date_created( $value ) { $this->data['date_created'] = $value; }
        public function set_discount_total( $value ) { $this->data['discount_total'] = $value; }
        
        public function add_product( $product, $qty = 1, $args = array() ) {
            $item = new WC_Order_Item_Product();
            $item->set_name( $product->get_name() );
            $item->set_quantity( $qty );
            $this->items[] = $item;
            return $item;
        }
        
        public function add_item( $item ) {
            $this->items[] = $item;
            return true;
        }
        
        public function add_order_note( $note, $is_customer_note = false, $added_by_user = false ) {
            return 1;
        }
        
        public function calculate_totals( $and_taxes = true ) {
            return true;
        }
        
        public function save() {
            if ( ! $this->id ) {
                $this->id = rand( 1, 10000 );
            }
            return $this->id;
        }
        
        public function get_data() {
            return $this->data;
        }
    }
}

/**
 * Mock WC_Order_Item_Product class
 */
if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
    class WC_Order_Item_Product {
        protected $data = array();
        protected $meta_data = array();
        
        public function set_name( $value ) { $this->data['name'] = $value; }
        public function set_quantity( $value ) { $this->data['quantity'] = $value; }
        public function set_subtotal( $value ) { $this->data['subtotal'] = $value; }
        public function set_total( $value ) { $this->data['total'] = $value; }
        
        public function add_meta_data( $key, $value, $unique = false ) {
            $this->meta_data[ $key ] = $value;
        }
    }
}

/**
 * Mock WC_Order_Item_Shipping class
 */
if ( ! class_exists( 'WC_Order_Item_Shipping' ) ) {
    class WC_Order_Item_Shipping {
        protected $data = array();
        
        public function set_method_title( $value ) { $this->data['method_title'] = $value; }
        public function set_total( $value ) { $this->data['total'] = $value; }
    }
}

/**
 * Mock WC_Order_Item_Tax class
 */
if ( ! class_exists( 'WC_Order_Item_Tax' ) ) {
    class WC_Order_Item_Tax {
        protected $data = array();
        
        public function set_rate_id( $value ) { $this->data['rate_id'] = $value; }
        public function set_label( $value ) { $this->data['label'] = $value; }
        public function set_tax_total( $value ) { $this->data['tax_total'] = $value; }
        public function set_shipping_tax_total( $value ) { $this->data['shipping_tax_total'] = $value; }
    }
}

/**
 * Mock WC_Order_Item_Coupon class
 */
if ( ! class_exists( 'WC_Order_Item_Coupon' ) ) {
    class WC_Order_Item_Coupon {
        protected $data = array();
        
        public function set_code( $value ) { $this->data['code'] = $value; }
        public function set_discount( $value ) { $this->data['discount'] = $value; }
    }
}

/**
 * Mock WC_Product class
 */
if ( ! class_exists( 'WC_Product' ) ) {
    class WC_Product {
        protected $id = 0;
        protected $data = array();
        
        public function __construct( $product_id = 0 ) {
            $this->id = $product_id;
            $this->data['name'] = 'Test Product';
        }
        
        public function get_id() {
            return $this->id;
        }
        
        public function get_name() {
            return $this->data['name'];
        }
    }
}

/**
 * Mock WC_Customer class
 */
if ( ! class_exists( 'WC_Customer' ) ) {
    class WC_Customer {
        protected $id = 0;
        protected $data = array();
        
        public function __construct( $customer_id = 0 ) {
            $this->id = $customer_id;
        }
        
        public function get_id() {
            return $this->id;
        }
        
        public function set_billing_first_name( $value ) { $this->data['billing_first_name'] = $value; }
        public function set_billing_last_name( $value ) { $this->data['billing_last_name'] = $value; }
        public function set_billing_company( $value ) { $this->data['billing_company'] = $value; }
        public function set_billing_address_1( $value ) { $this->data['billing_address_1'] = $value; }
        public function set_billing_city( $value ) { $this->data['billing_city'] = $value; }
        public function set_billing_state( $value ) { $this->data['billing_state'] = $value; }
        public function set_billing_postcode( $value ) { $this->data['billing_postcode'] = $value; }
        public function set_billing_country( $value ) { $this->data['billing_country'] = $value; }
        public function set_billing_email( $value ) { $this->data['billing_email'] = $value; }
        public function set_billing_phone( $value ) { $this->data['billing_phone'] = $value; }
        
        public function set_shipping_first_name( $value ) { $this->data['shipping_first_name'] = $value; }
        public function set_shipping_last_name( $value ) { $this->data['shipping_last_name'] = $value; }
        public function set_shipping_company( $value ) { $this->data['shipping_company'] = $value; }
        public function set_shipping_address_1( $value ) { $this->data['shipping_address_1'] = $value; }
        public function set_shipping_city( $value ) { $this->data['shipping_city'] = $value; }
        public function set_shipping_state( $value ) { $this->data['shipping_state'] = $value; }
        public function set_shipping_postcode( $value ) { $this->data['shipping_postcode'] = $value; }
        public function set_shipping_country( $value ) { $this->data['shipping_country'] = $value; }
        
        public function set_first_name( $value ) { $this->data['first_name'] = $value; }
        public function set_last_name( $value ) { $this->data['last_name'] = $value; }
        public function set_display_name( $value ) { $this->data['display_name'] = $value; }
        
        public function save() {
            if ( ! $this->id ) {
                $this->id = rand( 1, 10000 );
            }
            return $this->id;
        }
    }
}

/**
 * Mock wc_create_order function
 */
if ( ! function_exists( 'wc_create_order' ) ) {
    function wc_create_order( $args = array() ) {
        return new WC_Order();
    }
}

/**
 * Mock wc_get_order function
 */
if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order( $order_id ) {
        if ( ! $order_id ) {
            return false;
        }
        $order = new WC_Order( $order_id );
        return $order;
    }
}

/**
 * Mock wc_get_product function
 */
if ( ! function_exists( 'wc_get_product' ) ) {
    function wc_get_product( $product_id ) {
        if ( ! $product_id ) {
            return false;
        }
        return new WC_Product( $product_id );
    }
}

/**
 * Mock wc_get_product_id_by_sku function
 */
if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
    function wc_get_product_id_by_sku( $sku ) {
        return 0;
    }
}

/**
 * Mock wc_create_new_customer function
 */
if ( ! function_exists( 'wc_create_new_customer' ) ) {
    function wc_create_new_customer( $email, $username = '', $password = '', $args = array() ) {
        return rand( 1, 10000 );
    }
}

/**
 * Mock wc_get_order_statuses function
 */
if ( ! function_exists( 'wc_get_order_statuses' ) ) {
    function wc_get_order_statuses() {
        return array(
            'wc-pending'    => 'Pending payment',
            'wc-processing' => 'Processing',
            'wc-on-hold'    => 'On hold',
            'wc-completed'  => 'Completed',
            'wc-cancelled'  => 'Cancelled',
            'wc-refunded'   => 'Refunded',
            'wc-failed'     => 'Failed',
        );
    }
}

/**
 * Mock wc_get_logger function
 */
if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() {
        return new class {
            public function log( $level, $message, $context = array() ) {
                // No-op for testing
            }
        };
    }
}
