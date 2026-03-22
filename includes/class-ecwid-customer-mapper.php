<?php
/**
 * Ecwid Customer Mapper
 *
 * Maps Ecwid customers to WooCommerce customers
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Customer Mapper class
 */
class Ecwid_Customer_Mapper {

    /**
     * Logger instance
     *
     * @var Ecwid_Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Ecwid_Logger();
    }

    /**
     * Map Ecwid customer to WooCommerce customer
     *
     * @param array $ecwid_order Ecwid order data.
     * @return int|false WooCommerce customer ID or false.
     */
    public function map_customer( $ecwid_order ) {
        $email = $ecwid_order['email'] ?? '';
        
        if ( empty( $email ) ) {
            return false;
        }

        $ecwid_customer_id = $ecwid_order['customerId'] ?? null;

        // First, try to find by Ecwid customer ID
        if ( $ecwid_customer_id ) {
            $woo_customer_id = $this->find_by_ecwid_id( $ecwid_customer_id );
            
            if ( $woo_customer_id ) {
                return $woo_customer_id;
            }
        }

        // Try to find by email
        $user = get_user_by( 'email', $email );

        if ( $user ) {
            // Link Ecwid customer ID to existing user
            if ( $ecwid_customer_id ) {
                update_user_meta( $user->ID, '_ecwid_customer_id', $ecwid_customer_id );
            }
            return $user->ID;
        }

        // Option to create new customer
        $create_customers = get_option( 'ecwid_create_customers', 'yes' );

        if ( 'yes' === $create_customers ) {
            return $this->create_customer( $ecwid_order );
        }

        return false;
    }

    /**
     * Find WooCommerce customer by Ecwid customer ID
     *
     * @param int $ecwid_customer_id Ecwid customer ID.
     * @return int|false
     */
    private function find_by_ecwid_id( $ecwid_customer_id ) {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_ecwid_customer_id' AND meta_value = %s LIMIT 1",
                $ecwid_customer_id
            )
        );

        return $result ? intval( $result ) : false;
    }

    /**
     * Create WooCommerce customer from Ecwid order data
     *
     * @param array $ecwid_order Ecwid order data.
     * @return int|false WooCommerce customer ID or false.
     */
    private function create_customer( $ecwid_order ) {
        $email = $ecwid_order['email'] ?? '';

        if ( empty( $email ) ) {
            return false;
        }

        // Get billing/shipping info
        $billing = $ecwid_order['billingPerson'] ?? $ecwid_order['shippingPerson'] ?? array();
        $shipping = $ecwid_order['shippingPerson'] ?? array();

        $first_name = $billing['firstName'] ?? '';
        $last_name  = $billing['lastName'] ?? '';

        // Generate username from email
        $username = sanitize_user( current( explode( '@', $email ) ), true );

        // Ensure unique username
        $username = $this->ensure_unique_username( $username );

        // Generate random password
        $password = wp_generate_password( 12, true );

        try {
            // Create user
            $customer_id = wc_create_new_customer( $email, $username, $password );

            if ( is_wp_error( $customer_id ) ) {
                $this->logger->error( 'Failed to create customer', array(
                    'email' => $email,
                    'error' => $customer_id->get_error_message(),
                ) );
                return false;
            }

            // Update user meta
            $customer = new WC_Customer( $customer_id );

            // Billing address
            $customer->set_billing_first_name( $first_name );
            $customer->set_billing_last_name( $last_name );
            $customer->set_billing_company( $billing['companyName'] ?? '' );
            $customer->set_billing_address_1( $billing['street'] ?? '' );
            $customer->set_billing_city( $billing['city'] ?? '' );
            $customer->set_billing_state( $billing['stateOrProvinceCode'] ?? $billing['stateOrProvinceName'] ?? '' );
            $customer->set_billing_postcode( $billing['postalCode'] ?? '' );
            $customer->set_billing_country( $billing['countryCode'] ?? '' );
            $customer->set_billing_email( $email );
            $customer->set_billing_phone( $billing['phone'] ?? '' );

            // Shipping address
            $customer->set_shipping_first_name( $shipping['firstName'] ?? $first_name );
            $customer->set_shipping_last_name( $shipping['lastName'] ?? $last_name );
            $customer->set_shipping_company( $shipping['companyName'] ?? '' );
            $customer->set_shipping_address_1( $shipping['street'] ?? '' );
            $customer->set_shipping_city( $shipping['city'] ?? '' );
            $customer->set_shipping_state( $shipping['stateOrProvinceCode'] ?? $shipping['stateOrProvinceName'] ?? '' );
            $customer->set_shipping_postcode( $shipping['postalCode'] ?? '' );
            $customer->set_shipping_country( $shipping['countryCode'] ?? '' );

            // Name
            $customer->set_first_name( $first_name );
            $customer->set_last_name( $last_name );
            $customer->set_display_name( trim( $first_name . ' ' . $last_name ) );

            $customer->save();

            // Store Ecwid customer ID
            if ( ! empty( $ecwid_order['customerId'] ) ) {
                update_user_meta( $customer_id, '_ecwid_customer_id', $ecwid_order['customerId'] );
            }

            $this->logger->info( 'Customer created', array(
                'woo_customer_id'   => $customer_id,
                'ecwid_customer_id' => $ecwid_order['customerId'] ?? null,
                'email'             => $email,
            ) );

            return $customer_id;

        } catch ( Exception $e ) {
            $this->logger->error( 'Exception creating customer', array(
                'email' => $email,
                'error' => $e->getMessage(),
            ) );
            return false;
        }
    }

    /**
     * Ensure unique username
     *
     * @param string $username Base username.
     * @return string Unique username.
     */
    private function ensure_unique_username( $username ) {
        $original_username = $username;
        $suffix = 1;

        while ( username_exists( $username ) ) {
            $username = $original_username . $suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * Get WooCommerce customer ID by Ecwid customer ID
     *
     * @param int $ecwid_customer_id Ecwid customer ID.
     * @return int|false
     */
    public function get_woo_customer_id( $ecwid_customer_id ) {
        return $this->find_by_ecwid_id( $ecwid_customer_id );
    }

    /**
     * Get Ecwid customer ID by WooCommerce customer ID
     *
     * @param int $woo_customer_id WooCommerce customer ID.
     * @return int|false
     */
    public function get_ecwid_customer_id( $woo_customer_id ) {
        $ecwid_id = get_user_meta( $woo_customer_id, '_ecwid_customer_id', true );
        return $ecwid_id ? intval( $ecwid_id ) : false;
    }

    /**
     * Link WooCommerce customer to Ecwid customer
     *
     * @param int $woo_customer_id   WooCommerce customer ID.
     * @param int $ecwid_customer_id Ecwid customer ID.
     */
    public function link_customer( $woo_customer_id, $ecwid_customer_id ) {
        update_user_meta( $woo_customer_id, '_ecwid_customer_id', $ecwid_customer_id );

        $this->logger->info( 'Customer linked', array(
            'woo_customer_id'   => $woo_customer_id,
            'ecwid_customer_id' => $ecwid_customer_id,
        ) );
    }

    /**
     * Sync customer data from Ecwid
     *
     * @param int   $woo_customer_id WooCommerce customer ID.
     * @param array $ecwid_customer  Ecwid customer data.
     * @return bool
     */
    public function sync_customer_data( $woo_customer_id, $ecwid_customer ) {
        try {
            $customer = new WC_Customer( $woo_customer_id );

            if ( ! $customer->get_id() ) {
                return false;
            }

            // Update basic info
            if ( ! empty( $ecwid_customer['name'] ) ) {
                $name_parts = explode( ' ', $ecwid_customer['name'], 2 );
                $customer->set_first_name( $name_parts[0] );
                $customer->set_last_name( $name_parts[1] ?? '' );
            }

            // Update billing address
            if ( ! empty( $ecwid_customer['billingPerson'] ) ) {
                $billing = $ecwid_customer['billingPerson'];
                $customer->set_billing_first_name( $billing['firstName'] ?? '' );
                $customer->set_billing_last_name( $billing['lastName'] ?? '' );
                $customer->set_billing_company( $billing['companyName'] ?? '' );
                $customer->set_billing_address_1( $billing['street'] ?? '' );
                $customer->set_billing_city( $billing['city'] ?? '' );
                $customer->set_billing_state( $billing['stateOrProvinceCode'] ?? '' );
                $customer->set_billing_postcode( $billing['postalCode'] ?? '' );
                $customer->set_billing_country( $billing['countryCode'] ?? '' );
                $customer->set_billing_phone( $billing['phone'] ?? '' );
            }

            // Update shipping address
            if ( ! empty( $ecwid_customer['shippingAddresses'] ) && is_array( $ecwid_customer['shippingAddresses'] ) ) {
                $shipping = $ecwid_customer['shippingAddresses'][0];
                $customer->set_shipping_first_name( $shipping['firstName'] ?? '' );
                $customer->set_shipping_last_name( $shipping['lastName'] ?? '' );
                $customer->set_shipping_company( $shipping['companyName'] ?? '' );
                $customer->set_shipping_address_1( $shipping['street'] ?? '' );
                $customer->set_shipping_city( $shipping['city'] ?? '' );
                $customer->set_shipping_state( $shipping['stateOrProvinceCode'] ?? '' );
                $customer->set_shipping_postcode( $shipping['postalCode'] ?? '' );
                $customer->set_shipping_country( $shipping['countryCode'] ?? '' );
            }

            $customer->save();

            // Update last sync time
            update_user_meta( $woo_customer_id, '_ecwid_customer_last_sync', gmdate( 'c' ) );

            return true;

        } catch ( Exception $e ) {
            $this->logger->error( 'Failed to sync customer data', array(
                'woo_customer_id' => $woo_customer_id,
                'error'           => $e->getMessage(),
            ) );
            return false;
        }
    }
}
