<?php
/**
 * Order Mapper Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Mappers;

use Ecwid_WooCommerce\Utils\Logger;
use Ecwid_WooCommerce\Utils\Mapping_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Maps order data between Ecwid and WooCommerce formats.
 *
 * @since 1.0.0
 */
class Order_Mapper {

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Mapping repository.
     *
     * @var Mapping_Repository
     */
    private $mapping_repo;

    /**
     * Payment status mapping from Ecwid to WooCommerce.
     *
     * @var array
     */
    private const PAYMENT_STATUS_MAP = array(
        'AWAITING_PAYMENT' => 'pending',
        'PAID'             => 'processing',
        'CANCELLED'        => 'cancelled',
        'REFUNDED'         => 'refunded',
        'PARTIALLY_REFUNDED' => 'refunded',
        'INCOMPLETE'       => 'failed',
    );

    /**
     * Fulfillment status mapping from Ecwid to WooCommerce.
     *
     * @var array
     */
    private const FULFILLMENT_STATUS_MAP = array(
        'AWAITING_PROCESSING' => 'processing',
        'PROCESSING'          => 'processing',
        'SHIPPED'             => 'completed',
        'DELIVERED'           => 'completed',
        'WILL_NOT_DELIVER'    => 'cancelled',
        'RETURNED'            => 'refunded',
        'READY_FOR_PICKUP'    => 'on-hold',
    );

    /**
     * WooCommerce to Ecwid payment status mapping.
     *
     * @var array
     */
    private const WC_TO_ECWID_PAYMENT_STATUS = array(
        'pending'    => 'AWAITING_PAYMENT',
        'processing' => 'PAID',
        'on-hold'    => 'AWAITING_PAYMENT',
        'completed'  => 'PAID',
        'cancelled'  => 'CANCELLED',
        'refunded'   => 'REFUNDED',
        'failed'     => 'INCOMPLETE',
    );

    /**
     * WooCommerce to Ecwid fulfillment status mapping.
     *
     * @var array
     */
    private const WC_TO_ECWID_FULFILLMENT_STATUS = array(
        'pending'    => 'AWAITING_PROCESSING',
        'processing' => 'PROCESSING',
        'on-hold'    => 'AWAITING_PROCESSING',
        'completed'  => 'SHIPPED',
        'cancelled'  => 'WILL_NOT_DELIVER',
        'refunded'   => 'RETURNED',
        'failed'     => 'WILL_NOT_DELIVER',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger       = Logger::get_instance();
        $this->mapping_repo = Mapping_Repository::get_instance();
    }

    /**
     * Convert Ecwid order to WooCommerce format.
     *
     * @param array $ecwid_order Ecwid order data.
     * @param array $options     Mapping options.
     * @return array WooCommerce order data.
     */
    public function ecwid_to_wc( $ecwid_order, $options = array() ) {
        $defaults = array(
            'create_customer' => true,
            'map_products'    => true,
        );
        $options = wp_parse_args( $options, $defaults );

        $wc_data = array(
            'status'      => $this->map_status_to_wc( $ecwid_order ),
            'currency'    => $ecwid_order['currency'] ?? get_woocommerce_currency(),
            'customer_note' => $ecwid_order['orderComments'] ?? '',
        );

        // Customer ID mapping.
        if ( ! empty( $ecwid_order['customerId'] ) ) {
            $customer_mapping = $this->mapping_repo->find_by_ecwid_id( 'customer', $ecwid_order['customerId'] );
            if ( $customer_mapping ) {
                $wc_data['customer_id'] = (int) $customer_mapping->wc_id;
            }
        }

        // Map billing address.
        $this->map_billing_address( $ecwid_order, $wc_data );

        // Map shipping address.
        $this->map_shipping_address( $ecwid_order, $wc_data );

        // Map line items.
        if ( $options['map_products'] && ! empty( $ecwid_order['items'] ) ) {
            $this->map_line_items( $ecwid_order['items'], $wc_data );
        }

        // Map shipping lines.
        if ( ! empty( $ecwid_order['shippingOption'] ) ) {
            $this->map_shipping_lines( $ecwid_order['shippingOption'], $wc_data );
        }

        // Map coupon lines.
        if ( ! empty( $ecwid_order['discountCoupon'] ) ) {
            $this->map_coupon_lines( $ecwid_order['discountCoupon'], $wc_data );
        }

        // Map payment method.
        $this->map_payment_method( $ecwid_order, $wc_data );

        // Map meta data.
        $wc_data['meta_data'] = array(
            array(
                'key'   => '_ecwid_order_id',
                'value' => (string) ( $ecwid_order['id'] ?? '' ),
            ),
            array(
                'key'   => '_ecwid_order_number',
                'value' => $ecwid_order['orderNumber'] ?? '',
            ),
        );

        // Add private admin notes as meta.
        if ( ! empty( $ecwid_order['privateAdminNotes'] ) ) {
            $wc_data['meta_data'][] = array(
                'key'   => '_ecwid_admin_notes',
                'value' => $ecwid_order['privateAdminNotes'],
            );
        }

        // Add tracking number if present.
        if ( ! empty( $ecwid_order['trackingNumber'] ) ) {
            $wc_data['meta_data'][] = array(
                'key'   => '_ecwid_tracking_number',
                'value' => $ecwid_order['trackingNumber'],
            );
        }

        // Add IP address.
        if ( ! empty( $ecwid_order['ipAddress'] ) ) {
            $wc_data['meta_data'][] = array(
                'key'   => '_customer_ip_address',
                'value' => $ecwid_order['ipAddress'],
            );
        }

        // Allow filtering.
        $wc_data = apply_filters( 'ecwid_wc_order_to_wc', $wc_data, $ecwid_order, $options );

        $this->logger->debug(
            sprintf( 'Mapped Ecwid order #%s to WC format', $ecwid_order['id'] ?? 'unknown' ),
            array( 'order_number' => $ecwid_order['orderNumber'] ?? '' ),
            'Order_Mapper'
        );

        return $wc_data;
    }

    /**
     * Convert WooCommerce order to Ecwid format (for status updates).
     *
     * @param \WC_Order $order   WooCommerce order.
     * @param array     $options Mapping options.
     * @return array Ecwid order data.
     */
    public function wc_to_ecwid( $order, $options = array() ) {
        $wc_status = $order->get_status();

        $ecwid_data = array(
            'paymentStatus'     => self::WC_TO_ECWID_PAYMENT_STATUS[ $wc_status ] ?? 'AWAITING_PAYMENT',
            'fulfillmentStatus' => self::WC_TO_ECWID_FULFILLMENT_STATUS[ $wc_status ] ?? 'AWAITING_PROCESSING',
        );

        // Allow filtering.
        $ecwid_data = apply_filters( 'ecwid_wc_order_to_ecwid', $ecwid_data, $order, $options );

        return $ecwid_data;
    }

    /**
     * Map Ecwid order status to WooCommerce status.
     *
     * Uses combined payment and fulfillment status logic.
     *
     * @param array $ecwid_order Ecwid order data.
     * @return string WooCommerce order status.
     */
    public function map_status_to_wc( $ecwid_order ) {
        $payment_status     = $ecwid_order['paymentStatus'] ?? 'AWAITING_PAYMENT';
        $fulfillment_status = $ecwid_order['fulfillmentStatus'] ?? 'AWAITING_PROCESSING';

        // Payment status takes precedence for certain statuses.
        switch ( $payment_status ) {
            case 'AWAITING_PAYMENT':
            case 'INCOMPLETE':
                return self::PAYMENT_STATUS_MAP[ $payment_status ];

            case 'CANCELLED':
            case 'REFUNDED':
            case 'PARTIALLY_REFUNDED':
                return self::PAYMENT_STATUS_MAP[ $payment_status ];
        }

        // For paid orders, use fulfillment status.
        if ( 'PAID' === $payment_status ) {
            return self::FULFILLMENT_STATUS_MAP[ $fulfillment_status ] ?? 'processing';
        }

        // Fallback.
        return 'pending';
    }

    /**
     * Map WooCommerce status to Ecwid statuses.
     *
     * @param string $wc_status WooCommerce order status.
     * @return array{paymentStatus: string, fulfillmentStatus: string}
     */
    public function map_status_to_ecwid( $wc_status ) {
        return array(
            'paymentStatus'     => self::WC_TO_ECWID_PAYMENT_STATUS[ $wc_status ] ?? 'AWAITING_PAYMENT',
            'fulfillmentStatus' => self::WC_TO_ECWID_FULFILLMENT_STATUS[ $wc_status ] ?? 'AWAITING_PROCESSING',
        );
    }

    /**
     * Map billing address from Ecwid to WooCommerce.
     *
     * @param array $ecwid_order Ecwid order data.
     * @param array $wc_data     WooCommerce data (by reference).
     * @return void
     */
    private function map_billing_address( $ecwid_order, &$wc_data ) {
        $billing = $ecwid_order['billingPerson'] ?? array();
        $email   = $ecwid_order['email'] ?? '';

        if ( empty( $billing ) && empty( $email ) ) {
            return;
        }

        // Split name into first and last.
        $name_parts = $this->split_name( $billing['name'] ?? '' );

        // Split street into address_1 and address_2.
        $address_parts = $this->split_address( $billing['street'] ?? '' );

        $wc_data['billing'] = array(
            'first_name' => $name_parts['first_name'],
            'last_name'  => $name_parts['last_name'],
            'company'    => $billing['companyName'] ?? '',
            'address_1'  => $address_parts['address_1'],
            'address_2'  => $address_parts['address_2'],
            'city'       => $billing['city'] ?? '',
            'state'      => $billing['stateOrProvinceCode'] ?? '',
            'postcode'   => $billing['postalCode'] ?? '',
            'country'    => $billing['countryCode'] ?? '',
            'email'      => $email,
            'phone'      => $billing['phone'] ?? '',
        );
    }

    /**
     * Map shipping address from Ecwid to WooCommerce.
     *
     * @param array $ecwid_order Ecwid order data.
     * @param array $wc_data     WooCommerce data (by reference).
     * @return void
     */
    private function map_shipping_address( $ecwid_order, &$wc_data ) {
        $shipping = $ecwid_order['shippingPerson'] ?? array();

        if ( empty( $shipping ) ) {
            // Use billing as shipping if not specified.
            if ( ! empty( $wc_data['billing'] ) ) {
                $wc_data['shipping'] = array(
                    'first_name' => $wc_data['billing']['first_name'],
                    'last_name'  => $wc_data['billing']['last_name'],
                    'company'    => $wc_data['billing']['company'],
                    'address_1'  => $wc_data['billing']['address_1'],
                    'address_2'  => $wc_data['billing']['address_2'],
                    'city'       => $wc_data['billing']['city'],
                    'state'      => $wc_data['billing']['state'],
                    'postcode'   => $wc_data['billing']['postcode'],
                    'country'    => $wc_data['billing']['country'],
                );
            }
            return;
        }

        $name_parts    = $this->split_name( $shipping['name'] ?? '' );
        $address_parts = $this->split_address( $shipping['street'] ?? '' );

        $wc_data['shipping'] = array(
            'first_name' => $name_parts['first_name'],
            'last_name'  => $name_parts['last_name'],
            'company'    => $shipping['companyName'] ?? '',
            'address_1'  => $address_parts['address_1'],
            'address_2'  => $address_parts['address_2'],
            'city'       => $shipping['city'] ?? '',
            'state'      => $shipping['stateOrProvinceCode'] ?? '',
            'postcode'   => $shipping['postalCode'] ?? '',
            'country'    => $shipping['countryCode'] ?? '',
        );
    }

    /**
     * Map line items from Ecwid to WooCommerce.
     *
     * @param array $ecwid_items Ecwid line items.
     * @param array $wc_data     WooCommerce data (by reference).
     * @return void
     */
    private function map_line_items( $ecwid_items, &$wc_data ) {
        $wc_data['line_items'] = array();

        foreach ( $ecwid_items as $item ) {
            $line_item = array(
                'name'     => $item['name'] ?? '',
                'quantity' => (int) ( $item['quantity'] ?? 1 ),
                'subtotal' => (string) ( $item['price'] ?? 0 ),
                'total'    => (string) ( $item['price'] ?? 0 ),
            );

            // Map product ID.
            if ( ! empty( $item['productId'] ) ) {
                $product_mapping = $this->mapping_repo->find_by_ecwid_id( 'product', $item['productId'] );
                if ( $product_mapping ) {
                    $line_item['product_id'] = (int) $product_mapping->wc_id;
                }
            }

            // Map variation ID.
            if ( ! empty( $item['combinationId'] ) ) {
                $variation_mapping = $this->mapping_repo->find_by_ecwid_id( 'variation', $item['combinationId'] );
                if ( $variation_mapping ) {
                    $line_item['variation_id'] = (int) $variation_mapping->wc_id;
                }
            }

            // Map selected options as meta data.
            if ( ! empty( $item['selectedOptions'] ) ) {
                $line_item['meta_data'] = array();
                foreach ( $item['selectedOptions'] as $option ) {
                    $line_item['meta_data'][] = array(
                        'key'   => $option['name'] ?? '',
                        'value' => $option['value'] ?? '',
                    );
                }
            }

            // Add SKU as meta.
            if ( ! empty( $item['sku'] ) ) {
                if ( ! isset( $line_item['meta_data'] ) ) {
                    $line_item['meta_data'] = array();
                }
                $line_item['meta_data'][] = array(
                    'key'   => '_ecwid_sku',
                    'value' => $item['sku'],
                );
            }

            // Tax handling.
            if ( isset( $item['tax'] ) ) {
                $line_item['total_tax'] = (string) $item['tax'];
            }

            $wc_data['line_items'][] = $line_item;
        }
    }

    /**
     * Map shipping lines from Ecwid to WooCommerce.
     *
     * @param array $shipping_option Ecwid shipping option.
     * @param array $wc_data         WooCommerce data (by reference).
     * @return void
     */
    private function map_shipping_lines( $shipping_option, &$wc_data ) {
        $shipping_line = array(
            'method_title' => $shipping_option['shippingMethodName'] ?? __( 'Shipping', 'ecwid-woocommerce' ),
            'total'        => (string) ( $shipping_option['shippingRate'] ?? 0 ),
        );

        // Determine method ID based on fulfillment type.
        $fulfillment_type = $shipping_option['fulfillmentType'] ?? '';
        $is_pickup        = ! empty( $shipping_option['isPickup'] );

        if ( $is_pickup || 'pickup' === strtolower( $fulfillment_type ) ) {
            $shipping_line['method_id'] = 'local_pickup';
        } else {
            $shipping_line['method_id'] = 'flat_rate';
        }

        $wc_data['shipping_lines'] = array( $shipping_line );
    }

    /**
     * Map coupon lines from Ecwid to WooCommerce.
     *
     * @param array $discount_coupon Ecwid discount coupon.
     * @param array $wc_data         WooCommerce data (by reference).
     * @return void
     */
    private function map_coupon_lines( $discount_coupon, &$wc_data ) {
        if ( empty( $discount_coupon['code'] ) ) {
            return;
        }

        $wc_data['coupon_lines'] = array(
            array(
                'code'     => $discount_coupon['code'],
                'discount' => (string) ( $discount_coupon['discount'] ?? 0 ),
            ),
        );
    }

    /**
     * Map payment method from Ecwid to WooCommerce.
     *
     * @param array $ecwid_order Ecwid order data.
     * @param array $wc_data     WooCommerce data (by reference).
     * @return void
     */
    private function map_payment_method( $ecwid_order, &$wc_data ) {
        $payment_module = $ecwid_order['paymentModule'] ?? '';
        $payment_method = $ecwid_order['paymentMethod'] ?? '';

        // Map common payment methods.
        $method_map = array(
            'PayPal'     => 'paypal',
            'Stripe'     => 'stripe',
            'Square'     => 'square',
            'CUSTOM'     => 'cod',
            'CASH'       => 'cod',
            'CARD'       => 'stripe',
        );

        $wc_data['payment_method'] = $method_map[ $payment_module ] ?? 'other';
        $wc_data['payment_method_title'] = $payment_method ?: $payment_module;

        // Mark as paid if already paid in Ecwid.
        if ( 'PAID' === ( $ecwid_order['paymentStatus'] ?? '' ) ) {
            $wc_data['set_paid'] = true;
        }
    }

    /**
     * Split full name into first and last name.
     *
     * @param string $full_name Full name.
     * @return array{first_name: string, last_name: string}
     */
    private function split_name( $full_name ) {
        $full_name = trim( $full_name );

        if ( empty( $full_name ) ) {
            return array(
                'first_name' => '',
                'last_name'  => '',
            );
        }

        $parts = explode( ' ', $full_name, 2 );

        return array(
            'first_name' => $parts[0],
            'last_name'  => $parts[1] ?? '',
        );
    }

    /**
     * Split street address into address_1 and address_2.
     *
     * @param string $street Street address.
     * @return array{address_1: string, address_2: string}
     */
    private function split_address( $street ) {
        $street = trim( $street );

        if ( empty( $street ) ) {
            return array(
                'address_1' => '',
                'address_2' => '',
            );
        }

        // Split by newline.
        $parts = preg_split( '/[\r\n]+/', $street, 2 );

        return array(
            'address_1' => trim( $parts[0] ?? '' ),
            'address_2' => trim( $parts[1] ?? '' ),
        );
    }

    /**
     * Validate Ecwid order data.
     *
     * @param array $data Ecwid order data.
     * @return array{valid: bool, errors: array}
     */
    public function validate_ecwid_order( $data ) {
        $errors = array();

        // Required: email or billing person.
        if ( empty( $data['email'] ) && empty( $data['billingPerson'] ) ) {
            $errors[] = __( 'Order must have email or billing information.', 'ecwid-woocommerce' );
        }

        // Required: at least one line item.
        if ( empty( $data['items'] ) ) {
            $errors[] = __( 'Order must have at least one line item.', 'ecwid-woocommerce' );
        }

        // Validate email format.
        if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
            $errors[] = __( 'Invalid email address.', 'ecwid-woocommerce' );
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Get Ecwid order status display name.
     *
     * @param string $payment_status     Ecwid payment status.
     * @param string $fulfillment_status Ecwid fulfillment status.
     * @return string Display name.
     */
    public function get_ecwid_status_display( $payment_status, $fulfillment_status ) {
        $payment_labels = array(
            'AWAITING_PAYMENT' => __( 'Awaiting Payment', 'ecwid-woocommerce' ),
            'PAID'             => __( 'Paid', 'ecwid-woocommerce' ),
            'CANCELLED'        => __( 'Cancelled', 'ecwid-woocommerce' ),
            'REFUNDED'         => __( 'Refunded', 'ecwid-woocommerce' ),
            'PARTIALLY_REFUNDED' => __( 'Partially Refunded', 'ecwid-woocommerce' ),
            'INCOMPLETE'       => __( 'Incomplete', 'ecwid-woocommerce' ),
        );

        $fulfillment_labels = array(
            'AWAITING_PROCESSING' => __( 'Awaiting Processing', 'ecwid-woocommerce' ),
            'PROCESSING'          => __( 'Processing', 'ecwid-woocommerce' ),
            'SHIPPED'             => __( 'Shipped', 'ecwid-woocommerce' ),
            'DELIVERED'           => __( 'Delivered', 'ecwid-woocommerce' ),
            'WILL_NOT_DELIVER'    => __( 'Will Not Deliver', 'ecwid-woocommerce' ),
            'RETURNED'            => __( 'Returned', 'ecwid-woocommerce' ),
            'READY_FOR_PICKUP'    => __( 'Ready for Pickup', 'ecwid-woocommerce' ),
        );

        $payment_label     = $payment_labels[ $payment_status ] ?? $payment_status;
        $fulfillment_label = $fulfillment_labels[ $fulfillment_status ] ?? $fulfillment_status;

        return sprintf( '%s / %s', $payment_label, $fulfillment_label );
    }
}
