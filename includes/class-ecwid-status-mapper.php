<?php
/**
 * Ecwid Status Mapper
 *
 * Maps order statuses between Ecwid and WooCommerce
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Status Mapper class
 */
class Ecwid_Status_Mapper {

    /**
     * Ecwid payment statuses
     */
    const ECWID_PAYMENT_AWAITING     = 'AWAITING_PAYMENT';
    const ECWID_PAYMENT_PAID         = 'PAID';
    const ECWID_PAYMENT_CANCELLED    = 'CANCELLED';
    const ECWID_PAYMENT_REFUNDED     = 'REFUNDED';
    const ECWID_PAYMENT_INCOMPLETE   = 'INCOMPLETE';

    /**
     * Ecwid fulfillment statuses
     */
    const ECWID_FULFILLMENT_AWAITING    = 'AWAITING_PROCESSING';
    const ECWID_FULFILLMENT_PROCESSING  = 'PROCESSING';
    const ECWID_FULFILLMENT_SHIPPED     = 'SHIPPED';
    const ECWID_FULFILLMENT_DELIVERED   = 'DELIVERED';
    const ECWID_FULFILLMENT_WILL_NOT    = 'WILL_NOT_DELIVER';
    const ECWID_FULFILLMENT_RETURNED    = 'RETURNED';
    const ECWID_FULFILLMENT_READY       = 'READY_FOR_PICKUP';
    const ECWID_FULFILLMENT_OUT         = 'OUT_FOR_DELIVERY';

    /**
     * Status mappings cache
     *
     * @var array
     */
    private $mappings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_mappings();
    }

    /**
     * Initialize status mappings
     */
    private function init_mappings() {
        // Get custom mappings from options or use defaults
        $custom_mappings = get_option( 'ecwid_status_mappings', array() );

        // Default Ecwid to WooCommerce mappings
        $this->mappings = wp_parse_args( $custom_mappings, array(
            // Payment + Fulfillment combinations
            'PAID_AWAITING_PROCESSING'  => 'processing',
            'PAID_PROCESSING'           => 'processing',
            'PAID_SHIPPED'              => 'completed',
            'PAID_DELIVERED'            => 'completed',
            'PAID_READY_FOR_PICKUP'     => 'processing',
            'PAID_OUT_FOR_DELIVERY'     => 'completed',
            'PAID_WILL_NOT_DELIVER'     => 'cancelled',
            'PAID_RETURNED'             => 'refunded',

            'AWAITING_PAYMENT_AWAITING_PROCESSING' => 'pending',
            'AWAITING_PAYMENT_PROCESSING'          => 'on-hold',

            'CANCELLED_*'               => 'cancelled',
            'REFUNDED_*'                => 'refunded',
            'INCOMPLETE_*'              => 'pending',

            // Simple fulfillment mappings (fallback)
            'SHIPPED'                   => 'completed',
            'DELIVERED'                 => 'completed',
            'PROCESSING'                => 'processing',
            'AWAITING_PROCESSING'       => 'processing',
            'WILL_NOT_DELIVER'          => 'cancelled',
            'RETURNED'                  => 'refunded',
            'READY_FOR_PICKUP'          => 'processing',
            'OUT_FOR_DELIVERY'          => 'completed',
        ) );

        // Allow filtering
        $this->mappings = apply_filters( 'ecwid_status_mappings', $this->mappings );
    }

    /**
     * Map Ecwid statuses to WooCommerce status
     *
     * @param string $payment_status     Ecwid payment status.
     * @param string $fulfillment_status Ecwid fulfillment status.
     * @return string WooCommerce order status (without 'wc-' prefix).
     */
    public function ecwid_to_woo( $payment_status, $fulfillment_status ) {
        // Try combined key first
        $combined_key = $payment_status . '_' . $fulfillment_status;

        if ( isset( $this->mappings[ $combined_key ] ) ) {
            return $this->mappings[ $combined_key ];
        }

        // Try payment status with wildcard
        $payment_wildcard = $payment_status . '_*';
        
        if ( isset( $this->mappings[ $payment_wildcard ] ) ) {
            return $this->mappings[ $payment_wildcard ];
        }

        // Try fulfillment status only
        if ( isset( $this->mappings[ $fulfillment_status ] ) ) {
            return $this->mappings[ $fulfillment_status ];
        }

        // Determine by payment status logic
        switch ( $payment_status ) {
            case self::ECWID_PAYMENT_PAID:
                return $this->get_fulfillment_based_status( $fulfillment_status );

            case self::ECWID_PAYMENT_AWAITING:
            case self::ECWID_PAYMENT_INCOMPLETE:
                return 'pending';

            case self::ECWID_PAYMENT_CANCELLED:
                return 'cancelled';

            case self::ECWID_PAYMENT_REFUNDED:
                return 'refunded';

            default:
                return 'on-hold';
        }
    }

    /**
     * Get WooCommerce status based on fulfillment status (for paid orders)
     *
     * @param string $fulfillment_status Ecwid fulfillment status.
     * @return string WooCommerce order status.
     */
    private function get_fulfillment_based_status( $fulfillment_status ) {
        switch ( $fulfillment_status ) {
            case self::ECWID_FULFILLMENT_SHIPPED:
            case self::ECWID_FULFILLMENT_DELIVERED:
            case self::ECWID_FULFILLMENT_OUT:
                return 'completed';

            case self::ECWID_FULFILLMENT_WILL_NOT:
                return 'cancelled';

            case self::ECWID_FULFILLMENT_RETURNED:
                return 'refunded';

            case self::ECWID_FULFILLMENT_AWAITING:
            case self::ECWID_FULFILLMENT_PROCESSING:
            case self::ECWID_FULFILLMENT_READY:
            default:
                return 'processing';
        }
    }

    /**
     * Map WooCommerce status to Ecwid fulfillment status
     *
     * @param string $woo_status WooCommerce order status.
     * @return string Ecwid fulfillment status.
     */
    public function woo_to_ecwid( $woo_status ) {
        // Remove 'wc-' prefix if present
        $woo_status = str_replace( 'wc-', '', $woo_status );

        $reverse_mappings = array(
            'pending'    => self::ECWID_FULFILLMENT_AWAITING,
            'processing' => self::ECWID_FULFILLMENT_PROCESSING,
            'on-hold'    => self::ECWID_FULFILLMENT_AWAITING,
            'completed'  => self::ECWID_FULFILLMENT_SHIPPED,
            'cancelled'  => self::ECWID_FULFILLMENT_WILL_NOT,
            'refunded'   => self::ECWID_FULFILLMENT_RETURNED,
            'failed'     => self::ECWID_FULFILLMENT_WILL_NOT,
        );

        // Allow filtering
        $reverse_mappings = apply_filters( 'ecwid_woo_to_ecwid_status_mappings', $reverse_mappings );

        return $reverse_mappings[ $woo_status ] ?? self::ECWID_FULFILLMENT_PROCESSING;
    }

    /**
     * Get all Ecwid payment statuses
     *
     * @return array
     */
    public static function get_ecwid_payment_statuses() {
        return array(
            self::ECWID_PAYMENT_AWAITING   => __( 'Awaiting Payment', 'ecwid-woocommerce-sync' ),
            self::ECWID_PAYMENT_PAID       => __( 'Paid', 'ecwid-woocommerce-sync' ),
            self::ECWID_PAYMENT_CANCELLED  => __( 'Cancelled', 'ecwid-woocommerce-sync' ),
            self::ECWID_PAYMENT_REFUNDED   => __( 'Refunded', 'ecwid-woocommerce-sync' ),
            self::ECWID_PAYMENT_INCOMPLETE => __( 'Incomplete', 'ecwid-woocommerce-sync' ),
        );
    }

    /**
     * Get all Ecwid fulfillment statuses
     *
     * @return array
     */
    public static function get_ecwid_fulfillment_statuses() {
        return array(
            self::ECWID_FULFILLMENT_AWAITING   => __( 'Awaiting Processing', 'ecwid-woocommerce-sync' ),
            self::ECWID_FULFILLMENT_PROCESSING => __( 'Processing', 'ecwid-woocommerce-sync' ),
            self::ECWID_FULFILLMENT_SHIPPED    => __( 'Shipped', 'ecwid-woocommerce-sync' ),
            self::ECWID_FULFILLMENT_DELIVERED  => __( 'Delivered', 'ecwid-woocommerce-sync' ),
            self::ECWID_FULFILLMENT_WILL_NOT   => __( 'Will Not Deliver', 'ecwid-woocommerce-sync' ),
            self::ECWID_FULFILLMENT_RETURNED   => __( 'Returned', 'ecwid-woocommerce-sync' ),
            self::ECWID_FULFILLMENT_READY      => __( 'Ready for Pickup', 'ecwid-woocommerce-sync' ),
            self::ECWID_FULFILLMENT_OUT        => __( 'Out for Delivery', 'ecwid-woocommerce-sync' ),
        );
    }

    /**
     * Get all WooCommerce order statuses
     *
     * @return array
     */
    public static function get_woo_order_statuses() {
        if ( function_exists( 'wc_get_order_statuses' ) ) {
            return wc_get_order_statuses();
        }

        return array(
            'wc-pending'    => __( 'Pending payment', 'ecwid-woocommerce-sync' ),
            'wc-processing' => __( 'Processing', 'ecwid-woocommerce-sync' ),
            'wc-on-hold'    => __( 'On hold', 'ecwid-woocommerce-sync' ),
            'wc-completed'  => __( 'Completed', 'ecwid-woocommerce-sync' ),
            'wc-cancelled'  => __( 'Cancelled', 'ecwid-woocommerce-sync' ),
            'wc-refunded'   => __( 'Refunded', 'ecwid-woocommerce-sync' ),
            'wc-failed'     => __( 'Failed', 'ecwid-woocommerce-sync' ),
        );
    }

    /**
     * Save custom status mappings
     *
     * @param array $mappings Custom mappings.
     */
    public function save_custom_mappings( $mappings ) {
        update_option( 'ecwid_status_mappings', $mappings );
        $this->init_mappings();
    }

    /**
     * Get current mappings
     *
     * @return array
     */
    public function get_mappings() {
        return $this->mappings;
    }

    /**
     * Check if order should be marked as paid
     *
     * @param string $payment_status Ecwid payment status.
     * @return bool
     */
    public function is_paid_status( $payment_status ) {
        return in_array( $payment_status, array(
            self::ECWID_PAYMENT_PAID,
        ), true );
    }

    /**
     * Check if order is completed
     *
     * @param string $fulfillment_status Ecwid fulfillment status.
     * @return bool
     */
    public function is_completed_status( $fulfillment_status ) {
        return in_array( $fulfillment_status, array(
            self::ECWID_FULFILLMENT_SHIPPED,
            self::ECWID_FULFILLMENT_DELIVERED,
            self::ECWID_FULFILLMENT_OUT,
        ), true );
    }
}
