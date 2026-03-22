<?php
/**
 * Ecwid Payment Mapper
 *
 * Maps Ecwid payment methods to WooCommerce payment methods
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Payment Mapper class
 */
class Ecwid_Payment_Mapper {

    /**
     * Payment method mappings
     *
     * @var array
     */
    private $payment_mappings = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_mappings();
    }

    /**
     * Initialize payment mappings
     */
    private function init_mappings() {
        // Default mappings from Ecwid to WooCommerce
        $this->payment_mappings = array(
            // Ecwid payment method => WooCommerce payment method
            'PayPal'             => array(
                'id'    => 'paypal',
                'title' => 'PayPal',
            ),
            'Stripe'             => array(
                'id'    => 'stripe',
                'title' => 'Credit Card (Stripe)',
            ),
            'stripe'             => array(
                'id'    => 'stripe',
                'title' => 'Credit Card (Stripe)',
            ),
            'CreditCard'         => array(
                'id'    => 'stripe',
                'title' => 'Credit Card',
            ),
            'Square'             => array(
                'id'    => 'square',
                'title' => 'Square',
            ),
            'Cash'               => array(
                'id'    => 'cod',
                'title' => __( 'Cash on Delivery', 'ecwid-woocommerce-sync' ),
            ),
            'CashOnDelivery'     => array(
                'id'    => 'cod',
                'title' => __( 'Cash on Delivery', 'ecwid-woocommerce-sync' ),
            ),
            'BankTransfer'       => array(
                'id'    => 'bacs',
                'title' => __( 'Bank Transfer', 'ecwid-woocommerce-sync' ),
            ),
            'WireTransfer'       => array(
                'id'    => 'bacs',
                'title' => __( 'Wire Transfer', 'ecwid-woocommerce-sync' ),
            ),
            'Check'              => array(
                'id'    => 'cheque',
                'title' => __( 'Check Payment', 'ecwid-woocommerce-sync' ),
            ),
            'Cheque'             => array(
                'id'    => 'cheque',
                'title' => __( 'Check Payment', 'ecwid-woocommerce-sync' ),
            ),
            'ManualPayment'      => array(
                'id'    => 'other',
                'title' => __( 'Manual Payment', 'ecwid-woocommerce-sync' ),
            ),
            'PhoneOrder'         => array(
                'id'    => 'other',
                'title' => __( 'Phone Order', 'ecwid-woocommerce-sync' ),
            ),
            'AfterPay'           => array(
                'id'    => 'afterpay',
                'title' => 'AfterPay',
            ),
            'Klarna'             => array(
                'id'    => 'klarna',
                'title' => 'Klarna',
            ),
            'GooglePay'          => array(
                'id'    => 'gpay',
                'title' => 'Google Pay',
            ),
            'ApplePay'           => array(
                'id'    => 'applepay',
                'title' => 'Apple Pay',
            ),
        );

        // Allow filtering of payment mappings
        $this->payment_mappings = apply_filters( 'ecwid_payment_method_mappings', $this->payment_mappings );
    }

    /**
     * Map Ecwid payment method to WooCommerce payment method
     *
     * @param array $ecwid_order Ecwid order data.
     * @return array Payment method data with 'id' and 'title'.
     */
    public function map_payment_method( $ecwid_order ) {
        $payment_method = $ecwid_order['paymentMethod'] ?? '';
        $payment_module = $ecwid_order['paymentModule'] ?? '';

        // Try to match by payment module first (more specific)
        if ( ! empty( $payment_module ) && isset( $this->payment_mappings[ $payment_module ] ) ) {
            return $this->payment_mappings[ $payment_module ];
        }

        // Then try payment method
        if ( ! empty( $payment_method ) && isset( $this->payment_mappings[ $payment_method ] ) ) {
            return $this->payment_mappings[ $payment_method ];
        }

        // Check for partial matches
        $payment_check = strtolower( $payment_method . $payment_module );

        foreach ( $this->payment_mappings as $key => $value ) {
            if ( stripos( $payment_check, strtolower( $key ) ) !== false ) {
                return $value;
            }
        }

        // Default to 'other' payment method
        return array(
            'id'    => 'other',
            'title' => ! empty( $payment_method ) ? $payment_method : __( 'Ecwid Payment', 'ecwid-woocommerce-sync' ),
        );
    }

    /**
     * Get payment status details
     *
     * @param array $ecwid_order Ecwid order data.
     * @return array Payment status details.
     */
    public function get_payment_status( $ecwid_order ) {
        $payment_status = $ecwid_order['paymentStatus'] ?? 'INCOMPLETE';

        $status_map = array(
            'AWAITING_PAYMENT' => array(
                'paid'       => false,
                'status'     => 'pending',
                'message'    => __( 'Awaiting payment', 'ecwid-woocommerce-sync' ),
            ),
            'PAID'             => array(
                'paid'       => true,
                'status'     => 'processing',
                'message'    => __( 'Payment received', 'ecwid-woocommerce-sync' ),
            ),
            'CANCELLED'        => array(
                'paid'       => false,
                'status'     => 'cancelled',
                'message'    => __( 'Payment cancelled', 'ecwid-woocommerce-sync' ),
            ),
            'REFUNDED'         => array(
                'paid'       => false,
                'status'     => 'refunded',
                'message'    => __( 'Payment refunded', 'ecwid-woocommerce-sync' ),
            ),
            'PARTIALLY_REFUNDED' => array(
                'paid'       => true,
                'status'     => 'processing',
                'message'    => __( 'Partially refunded', 'ecwid-woocommerce-sync' ),
            ),
            'INCOMPLETE'       => array(
                'paid'       => false,
                'status'     => 'pending',
                'message'    => __( 'Payment incomplete', 'ecwid-woocommerce-sync' ),
            ),
        );

        return $status_map[ $payment_status ] ?? $status_map['INCOMPLETE'];
    }

    /**
     * Get transaction details from order
     *
     * @param array $ecwid_order Ecwid order data.
     * @return array Transaction details.
     */
    public function get_transaction_details( $ecwid_order ) {
        return array(
            'transaction_id'  => $ecwid_order['externalTransactionId'] ?? '',
            'payment_method'  => $ecwid_order['paymentMethod'] ?? '',
            'payment_module'  => $ecwid_order['paymentModule'] ?? '',
            'payment_status'  => $ecwid_order['paymentStatus'] ?? '',
            'payment_message' => $ecwid_order['paymentMessage'] ?? '',
            'payment_params'  => $ecwid_order['paymentParams'] ?? array(),
        );
    }

    /**
     * Store payment details as order meta
     *
     * @param WC_Order $woo_order   WooCommerce order.
     * @param array    $ecwid_order Ecwid order data.
     */
    public function store_payment_meta( $woo_order, $ecwid_order ) {
        $transaction = $this->get_transaction_details( $ecwid_order );

        // Store transaction ID
        if ( ! empty( $transaction['transaction_id'] ) ) {
            $woo_order->set_transaction_id( $transaction['transaction_id'] );
        }

        // Store Ecwid-specific payment data
        $woo_order->update_meta_data( '_ecwid_payment_method', $transaction['payment_method'] );
        $woo_order->update_meta_data( '_ecwid_payment_module', $transaction['payment_module'] );
        $woo_order->update_meta_data( '_ecwid_payment_status', $transaction['payment_status'] );
        $woo_order->update_meta_data( '_ecwid_payment_message', $transaction['payment_message'] );

        // Store refund info if applicable
        if ( ! empty( $ecwid_order['refundedAmount'] ) ) {
            $woo_order->update_meta_data( '_ecwid_refunded_amount', $ecwid_order['refundedAmount'] );
        }

        if ( ! empty( $ecwid_order['refunds'] ) ) {
            $woo_order->update_meta_data( '_ecwid_refunds', wp_json_encode( $ecwid_order['refunds'] ) );
        }
    }

    /**
     * Register a custom payment mapping
     *
     * @param string $ecwid_method   Ecwid payment method name.
     * @param string $woo_method_id  WooCommerce payment method ID.
     * @param string $woo_method_title WooCommerce payment method title.
     */
    public function register_mapping( $ecwid_method, $woo_method_id, $woo_method_title ) {
        $this->payment_mappings[ $ecwid_method ] = array(
            'id'    => $woo_method_id,
            'title' => $woo_method_title,
        );
    }

    /**
     * Get all payment mappings
     *
     * @return array
     */
    public function get_all_mappings() {
        return $this->payment_mappings;
    }

    /**
     * Save custom mappings to database
     *
     * @param array $mappings Custom mappings.
     */
    public function save_custom_mappings( $mappings ) {
        update_option( 'ecwid_custom_payment_mappings', $mappings );
    }

    /**
     * Load custom mappings from database
     */
    public function load_custom_mappings() {
        $custom_mappings = get_option( 'ecwid_custom_payment_mappings', array() );

        if ( ! empty( $custom_mappings ) && is_array( $custom_mappings ) ) {
            foreach ( $custom_mappings as $ecwid_method => $woo_method ) {
                if ( isset( $woo_method['id'] ) && isset( $woo_method['title'] ) ) {
                    $this->payment_mappings[ $ecwid_method ] = $woo_method;
                }
            }
        }
    }
}
