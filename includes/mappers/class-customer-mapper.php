<?php
/**
 * Customer Mapper Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Mappers;

use Ecwid_WooCommerce\Utils\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Maps customer data between Ecwid and WooCommerce formats.
 *
 * @since 1.0.0
 */
class Customer_Mapper {

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Customer group to WP role mapping.
     *
     * @var array
     */
    private const GROUP_ROLE_MAP = array(
        'General'   => 'customer',
        'Wholesale' => 'wholesale_customer',
        'VIP'       => 'vip_customer',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger = Logger::get_instance();
    }

    /**
     * Convert Ecwid customer to WooCommerce format.
     *
     * @param array $ecwid_customer Ecwid customer data.
     * @param array $options        Mapping options.
     * @return array WooCommerce customer data.
     */
    public function ecwid_to_wc( $ecwid_customer, $options = array() ) {
        $defaults = array(
            'create_user'     => true,
            'update_existing' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        // Split name into first and last.
        $name_parts = $this->split_name( $ecwid_customer['name'] ?? '' );

        $wc_data = array(
            'email'      => $ecwid_customer['email'] ?? '',
            'first_name' => $name_parts['first_name'],
            'last_name'  => $name_parts['last_name'],
            'username'   => $this->generate_username( $ecwid_customer['email'] ?? '' ),
        );

        // Map role from customer group.
        $wc_data['role'] = $this->map_group_to_role( $ecwid_customer );

        // Map billing address.
        $this->map_billing_address( $ecwid_customer, $wc_data );

        // Map shipping address.
        $this->map_shipping_address( $ecwid_customer, $wc_data );

        // Map meta data.
        $wc_data['meta_data'] = array(
            array(
                'key'   => '_ecwid_customer_id',
                'value' => (string) ( $ecwid_customer['id'] ?? '' ),
            ),
        );

        // Tax exempt status.
        if ( isset( $ecwid_customer['taxExempt'] ) ) {
            $wc_data['meta_data'][] = array(
                'key'   => 'is_vat_exempt',
                'value' => $ecwid_customer['taxExempt'] ? 'yes' : 'no',
            );
        }

        // Tax ID.
        if ( ! empty( $ecwid_customer['taxId'] ) ) {
            $wc_data['meta_data'][] = array(
                'key'   => '_ecwid_tax_id',
                'value' => $ecwid_customer['taxId'],
            );
            $wc_data['meta_data'][] = array(
                'key'   => '_ecwid_tax_id_valid',
                'value' => ! empty( $ecwid_customer['taxIdValid'] ) ? 'yes' : 'no',
            );
        }

        // Marketing consent.
        if ( isset( $ecwid_customer['acceptMarketing'] ) ) {
            $wc_data['meta_data'][] = array(
                'key'   => '_ecwid_accept_marketing',
                'value' => $ecwid_customer['acceptMarketing'] ? 'yes' : 'no',
            );
        }

        // Customer group.
        if ( ! empty( $ecwid_customer['customerGroupId'] ) ) {
            $wc_data['meta_data'][] = array(
                'key'   => '_ecwid_customer_group_id',
                'value' => $ecwid_customer['customerGroupId'],
            );
        }

        // Allow filtering.
        $wc_data = apply_filters( 'ecwid_wc_customer_to_wc', $wc_data, $ecwid_customer, $options );

        $this->logger->debug(
            sprintf( 'Mapped Ecwid customer #%s to WC format', $ecwid_customer['id'] ?? 'unknown' ),
            array( 'email' => $ecwid_customer['email'] ?? '' ),
            'Customer_Mapper'
        );

        return $wc_data;
    }

    /**
     * Convert WooCommerce customer to Ecwid format.
     *
     * @param \WC_Customer $customer WooCommerce customer.
     * @param array        $options  Mapping options.
     * @return array Ecwid customer data.
     */
    public function wc_to_ecwid( $customer, $options = array() ) {
        $ecwid_data = array(
            'email' => $customer->get_email(),
            'name'  => trim( $customer->get_first_name() . ' ' . $customer->get_last_name() ),
        );

        // Billing person.
        $billing_first = $customer->get_billing_first_name();
        $billing_last  = $customer->get_billing_last_name();

        if ( $billing_first || $billing_last ) {
            $ecwid_data['billingPerson'] = array(
                'name'               => trim( $billing_first . ' ' . $billing_last ),
                'companyName'        => $customer->get_billing_company(),
                'street'             => $this->join_address(
                    $customer->get_billing_address_1(),
                    $customer->get_billing_address_2()
                ),
                'city'               => $customer->get_billing_city(),
                'countryCode'        => $customer->get_billing_country(),
                'stateOrProvinceCode' => $customer->get_billing_state(),
                'postalCode'         => $customer->get_billing_postcode(),
                'phone'              => $customer->get_billing_phone(),
            );
        }

        // Shipping addresses.
        $shipping_first = $customer->get_shipping_first_name();
        $shipping_last  = $customer->get_shipping_last_name();

        if ( $shipping_first || $shipping_last ) {
            $ecwid_data['shippingAddresses'] = array(
                array(
                    'name'               => trim( $shipping_first . ' ' . $shipping_last ),
                    'companyName'        => $customer->get_shipping_company(),
                    'street'             => $this->join_address(
                        $customer->get_shipping_address_1(),
                        $customer->get_shipping_address_2()
                    ),
                    'city'               => $customer->get_shipping_city(),
                    'countryCode'        => $customer->get_shipping_country(),
                    'stateOrProvinceCode' => $customer->get_shipping_state(),
                    'postalCode'         => $customer->get_shipping_postcode(),
                ),
            );
        }

        // Tax exempt.
        $is_vat_exempt = $customer->get_meta( 'is_vat_exempt' );
        if ( $is_vat_exempt ) {
            $ecwid_data['taxExempt'] = 'yes' === $is_vat_exempt;
        }

        // Allow filtering.
        $ecwid_data = apply_filters( 'ecwid_wc_customer_to_ecwid', $ecwid_data, $customer, $options );

        $this->logger->debug(
            sprintf( 'Mapped WC customer #%d to Ecwid format', $customer->get_id() ),
            array( 'email' => $customer->get_email() ),
            'Customer_Mapper'
        );

        return $ecwid_data;
    }

    /**
     * Map billing address from Ecwid to WooCommerce.
     *
     * @param array $ecwid_customer Ecwid customer data.
     * @param array $wc_data        WooCommerce data (by reference).
     * @return void
     */
    private function map_billing_address( $ecwid_customer, &$wc_data ) {
        $billing = $ecwid_customer['billingPerson'] ?? array();

        if ( empty( $billing ) ) {
            return;
        }

        $name_parts    = $this->split_name( $billing['name'] ?? '' );
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
            'phone'      => $billing['phone'] ?? '',
            'email'      => $ecwid_customer['email'] ?? '',
        );
    }

    /**
     * Map shipping address from Ecwid to WooCommerce.
     *
     * @param array $ecwid_customer Ecwid customer data.
     * @param array $wc_data        WooCommerce data (by reference).
     * @return void
     */
    private function map_shipping_address( $ecwid_customer, &$wc_data ) {
        $shipping_addresses = $ecwid_customer['shippingAddresses'] ?? array();

        if ( empty( $shipping_addresses ) ) {
            return;
        }

        // Use first shipping address.
        $shipping = $shipping_addresses[0];

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
     * Map customer group to WordPress role.
     *
     * @param array $ecwid_customer Ecwid customer data.
     * @return string WordPress role.
     */
    private function map_group_to_role( $ecwid_customer ) {
        $group_name = $ecwid_customer['customerGroupName'] ?? 'General';

        // Check if custom role exists.
        $custom_role = self::GROUP_ROLE_MAP[ $group_name ] ?? null;

        if ( $custom_role && get_role( $custom_role ) ) {
            return $custom_role;
        }

        // Default to customer role.
        return 'customer';
    }

    /**
     * Generate username from email.
     *
     * @param string $email Email address.
     * @return string Username.
     */
    private function generate_username( $email ) {
        if ( empty( $email ) ) {
            return '';
        }

        // Use email part before @.
        $parts    = explode( '@', $email );
        $username = sanitize_user( $parts[0], true );

        // Ensure uniqueness.
        $original = $username;
        $counter  = 1;

        while ( username_exists( $username ) ) {
            $username = $original . $counter;
            $counter++;
        }

        return $username;
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

        $parts = preg_split( '/[\r\n]+/', $street, 2 );

        return array(
            'address_1' => trim( $parts[0] ?? '' ),
            'address_2' => trim( $parts[1] ?? '' ),
        );
    }

    /**
     * Join address lines.
     *
     * @param string $address_1 Address line 1.
     * @param string $address_2 Address line 2.
     * @return string Combined address.
     */
    private function join_address( $address_1, $address_2 ) {
        $parts = array_filter( array( $address_1, $address_2 ) );
        return implode( "\n", $parts );
    }

    /**
     * Validate Ecwid customer data.
     *
     * @param array $data Ecwid customer data.
     * @return array{valid: bool, errors: array}
     */
    public function validate_ecwid_customer( $data ) {
        $errors = array();

        // Required: email.
        if ( empty( $data['email'] ) ) {
            $errors[] = __( 'Customer email is required.', 'ecwid-woocommerce' );
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
     * Find or create WooCommerce customer from Ecwid data.
     *
     * @param array $ecwid_customer Ecwid customer data.
     * @param bool  $create_if_missing Create if not found.
     * @return int|null WooCommerce customer ID or null.
     */
    public function find_or_create_wc_customer( $ecwid_customer, $create_if_missing = true ) {
        $email = $ecwid_customer['email'] ?? '';

        if ( empty( $email ) ) {
            return null;
        }

        // Try to find existing user by email.
        $existing_user = get_user_by( 'email', $email );

        if ( $existing_user ) {
            return $existing_user->ID;
        }

        // Create new customer if allowed.
        if ( ! $create_if_missing ) {
            return null;
        }

        $wc_data = $this->ecwid_to_wc( $ecwid_customer );

        // Generate password.
        $password = wp_generate_password();

        // Create user.
        $user_id = wc_create_new_customer( $email, $wc_data['username'], $password );

        if ( is_wp_error( $user_id ) ) {
            $this->logger->error(
                sprintf( 'Failed to create customer: %s', $user_id->get_error_message() ),
                array( 'email' => $email ),
                'Customer_Mapper'
            );
            return null;
        }

        // Update customer data.
        $customer = new \WC_Customer( $user_id );

        if ( ! empty( $wc_data['first_name'] ) ) {
            $customer->set_first_name( $wc_data['first_name'] );
        }

        if ( ! empty( $wc_data['last_name'] ) ) {
            $customer->set_last_name( $wc_data['last_name'] );
        }

        // Set billing address.
        if ( ! empty( $wc_data['billing'] ) ) {
            $billing = $wc_data['billing'];
            $customer->set_billing_first_name( $billing['first_name'] ?? '' );
            $customer->set_billing_last_name( $billing['last_name'] ?? '' );
            $customer->set_billing_company( $billing['company'] ?? '' );
            $customer->set_billing_address_1( $billing['address_1'] ?? '' );
            $customer->set_billing_address_2( $billing['address_2'] ?? '' );
            $customer->set_billing_city( $billing['city'] ?? '' );
            $customer->set_billing_state( $billing['state'] ?? '' );
            $customer->set_billing_postcode( $billing['postcode'] ?? '' );
            $customer->set_billing_country( $billing['country'] ?? '' );
            $customer->set_billing_phone( $billing['phone'] ?? '' );
            $customer->set_billing_email( $billing['email'] ?? $email );
        }

        // Set shipping address.
        if ( ! empty( $wc_data['shipping'] ) ) {
            $shipping = $wc_data['shipping'];
            $customer->set_shipping_first_name( $shipping['first_name'] ?? '' );
            $customer->set_shipping_last_name( $shipping['last_name'] ?? '' );
            $customer->set_shipping_company( $shipping['company'] ?? '' );
            $customer->set_shipping_address_1( $shipping['address_1'] ?? '' );
            $customer->set_shipping_address_2( $shipping['address_2'] ?? '' );
            $customer->set_shipping_city( $shipping['city'] ?? '' );
            $customer->set_shipping_state( $shipping['state'] ?? '' );
            $customer->set_shipping_postcode( $shipping['postcode'] ?? '' );
            $customer->set_shipping_country( $shipping['country'] ?? '' );
        }

        // Save meta data.
        if ( ! empty( $wc_data['meta_data'] ) ) {
            foreach ( $wc_data['meta_data'] as $meta ) {
                $customer->update_meta_data( $meta['key'], $meta['value'] );
            }
        }

        $customer->save();

        $this->logger->info(
            sprintf( 'Created WC customer #%d from Ecwid customer', $user_id ),
            array( 'email' => $email ),
            'Customer_Mapper'
        );

        return $user_id;
    }

    /**
     * Update WooCommerce customer from Ecwid data.
     *
     * @param int   $wc_customer_id   WooCommerce customer ID.
     * @param array $ecwid_customer   Ecwid customer data.
     * @return bool Success.
     */
    public function update_wc_customer( $wc_customer_id, $ecwid_customer ) {
        try {
            $customer = new \WC_Customer( $wc_customer_id );

            if ( ! $customer->get_id() ) {
                return false;
            }

            $wc_data = $this->ecwid_to_wc( $ecwid_customer );

            // Update basic info.
            if ( ! empty( $wc_data['first_name'] ) ) {
                $customer->set_first_name( $wc_data['first_name'] );
            }

            if ( ! empty( $wc_data['last_name'] ) ) {
                $customer->set_last_name( $wc_data['last_name'] );
            }

            // Update billing address.
            if ( ! empty( $wc_data['billing'] ) ) {
                $billing = $wc_data['billing'];
                $customer->set_billing_first_name( $billing['first_name'] ?? '' );
                $customer->set_billing_last_name( $billing['last_name'] ?? '' );
                $customer->set_billing_company( $billing['company'] ?? '' );
                $customer->set_billing_address_1( $billing['address_1'] ?? '' );
                $customer->set_billing_address_2( $billing['address_2'] ?? '' );
                $customer->set_billing_city( $billing['city'] ?? '' );
                $customer->set_billing_state( $billing['state'] ?? '' );
                $customer->set_billing_postcode( $billing['postcode'] ?? '' );
                $customer->set_billing_country( $billing['country'] ?? '' );
                $customer->set_billing_phone( $billing['phone'] ?? '' );
            }

            // Update shipping address.
            if ( ! empty( $wc_data['shipping'] ) ) {
                $shipping = $wc_data['shipping'];
                $customer->set_shipping_first_name( $shipping['first_name'] ?? '' );
                $customer->set_shipping_last_name( $shipping['last_name'] ?? '' );
                $customer->set_shipping_company( $shipping['company'] ?? '' );
                $customer->set_shipping_address_1( $shipping['address_1'] ?? '' );
                $customer->set_shipping_address_2( $shipping['address_2'] ?? '' );
                $customer->set_shipping_city( $shipping['city'] ?? '' );
                $customer->set_shipping_state( $shipping['state'] ?? '' );
                $customer->set_shipping_postcode( $shipping['postcode'] ?? '' );
                $customer->set_shipping_country( $shipping['country'] ?? '' );
            }

            // Update meta data.
            if ( ! empty( $wc_data['meta_data'] ) ) {
                foreach ( $wc_data['meta_data'] as $meta ) {
                    $customer->update_meta_data( $meta['key'], $meta['value'] );
                }
            }

            $customer->save();

            $this->logger->info(
                sprintf( 'Updated WC customer #%d from Ecwid', $wc_customer_id ),
                array(),
                'Customer_Mapper'
            );

            return true;

        } catch ( \Exception $e ) {
            $this->logger->error(
                sprintf( 'Failed to update customer #%d: %s', $wc_customer_id, $e->getMessage() ),
                array(),
                'Customer_Mapper'
            );
            return false;
        }
    }
}
