<?php
/**
 * Ecwid API Client
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Api;

use Ecwid_WooCommerce\Utils\Logger;
use Ecwid_WooCommerce\Utils\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid REST API client.
 *
 * Handles all communication with Ecwid REST API v3.
 *
 * @since 1.0.0
 */
class Ecwid_Api {

    /**
     * API base URL.
     */
    const API_BASE_URL = 'https://app.ecwid.com/api/v3/';

    /**
     * API version.
     */
    const API_VERSION = 'v3';

    /**
     * Rate limit (requests per minute).
     */
    const RATE_LIMIT = 600;

    /**
     * Default timeout in seconds.
     */
    const DEFAULT_TIMEOUT = 30;

    /**
     * Single instance.
     *
     * @var Ecwid_Api|null
     */
    private static $instance = null;

    /**
     * Store ID.
     *
     * @var string
     */
    private $store_id;

    /**
     * Access token.
     *
     * @var string
     */
    private $access_token;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Last API response.
     *
     * @var array|null
     */
    private $last_response = null;

    /**
     * Last error message.
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Request count for rate limiting.
     *
     * @var int
     */
    private $request_count = 0;

    /**
     * Request window start time.
     *
     * @var int
     */
    private $request_window_start = 0;

    /**
     * Get single instance.
     *
     * @return Ecwid_Api
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
        $this->logger = Logger::get_instance();
        $this->load_credentials();
    }

    /**
     * Load API credentials from options.
     *
     * @return void
     */
    private function load_credentials() {
        $encryption = Encryption::get_instance();

        $this->store_id     = get_option( 'ecwid_wc_ecwid_store_id', '' );
        $this->access_token = $encryption->get_decrypted_option( 'ecwid_wc_ecwid_access_token', '' );
    }

    /**
     * Set API credentials.
     *
     * @param string $store_id     Store ID.
     * @param string $access_token Access token.
     * @return void
     */
    public function set_credentials( $store_id, $access_token ) {
        $this->store_id     = $store_id;
        $this->access_token = $access_token;
    }

    /**
     * Check if credentials are configured.
     *
     * @return bool
     */
    public function has_credentials() {
        return ! empty( $this->store_id ) && ! empty( $this->access_token );
    }

    /**
     * Test API connection.
     *
     * @return array{success: bool, message: string, data: array|null}
     */
    public function test_connection() {
        if ( ! $this->has_credentials() ) {
            return array(
                'success' => false,
                'message' => __( 'API credentials are not configured.', 'ecwid-woocommerce' ),
                'data'    => null,
            );
        }

        // Try to fetch store profile.
        $response = $this->get( 'profile' );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'data'    => null,
            );
        }

        // Validate response contains expected data.
        if ( ! isset( $response['generalInfo'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid API response. Please check your credentials.', 'ecwid-woocommerce' ),
                'data'    => null,
            );
        }

        $store_name = $response['generalInfo']['storeUrl'] ?? '';
        $store_id   = $response['generalInfo']['storeId'] ?? $this->store_id;

        return array(
            'success' => true,
            'message' => sprintf(
                /* translators: %s: Store ID */
                __( 'Successfully connected to Ecwid store #%s', 'ecwid-woocommerce' ),
                $store_id
            ),
            'data'    => array(
                'store_id'   => $store_id,
                'store_url'  => $store_name,
                'store_name' => $response['generalInfo']['starterSite']['ecwidSubdomain'] ?? '',
            ),
        );
    }

    /**
     * Get store profile.
     *
     * @return array|\WP_Error
     */
    public function get_profile() {
        return $this->get( 'profile' );
    }

    /**
     * Get products.
     *
     * @param array $params Query parameters.
     * @return array|\WP_Error
     */
    public function get_products( $params = array() ) {
        return $this->get( 'products', $params );
    }

    /**
     * Get single product.
     *
     * @param int $product_id Product ID.
     * @return array|\WP_Error
     */
    public function get_product( $product_id ) {
        return $this->get( 'products/' . $product_id );
    }

    /**
     * Get orders.
     *
     * @param array $params Query parameters.
     * @return array|\WP_Error
     */
    public function get_orders( $params = array() ) {
        return $this->get( 'orders', $params );
    }

    /**
     * Get single order.
     *
     * @param string $order_id Order ID.
     * @return array|\WP_Error
     */
    public function get_order( $order_id ) {
        return $this->get( 'orders/' . $order_id );
    }

    /**
     * Get customers.
     *
     * @param array $params Query parameters.
     * @return array|\WP_Error
     */
    public function get_customers( $params = array() ) {
        return $this->get( 'customers', $params );
    }

    /**
     * Get single customer.
     *
     * @param int $customer_id Customer ID.
     * @return array|\WP_Error
     */
    public function get_customer( $customer_id ) {
        return $this->get( 'customers/' . $customer_id );
    }

    /**
     * Get categories.
     *
     * @param array $params Query parameters.
     * @return array|\WP_Error
     */
    public function get_categories( $params = array() ) {
        return $this->get( 'categories', $params );
    }

    /**
     * Make a GET request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Query parameters.
     * @return array|\WP_Error
     */
    public function get( $endpoint, $params = array() ) {
        return $this->request( 'GET', $endpoint, $params );
    }

    /**
     * Make a POST request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request body data.
     * @return array|\WP_Error
     */
    public function post( $endpoint, $data = array() ) {
        return $this->request( 'POST', $endpoint, array(), $data );
    }

    /**
     * Make a PUT request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request body data.
     * @return array|\WP_Error
     */
    public function put( $endpoint, $data = array() ) {
        return $this->request( 'PUT', $endpoint, array(), $data );
    }

    /**
     * Make a DELETE request.
     *
     * @param string $endpoint API endpoint.
     * @return array|\WP_Error
     */
    public function delete( $endpoint ) {
        return $this->request( 'DELETE', $endpoint );
    }

    /**
     * Make an API request.
     *
     * @param string $method   HTTP method.
     * @param string $endpoint API endpoint.
     * @param array  $params   Query parameters.
     * @param array  $data     Request body data.
     * @return array|\WP_Error
     */
    private function request( $method, $endpoint, $params = array(), $data = array() ) {
        // Check credentials.
        if ( ! $this->has_credentials() ) {
            $error = new \WP_Error(
                'ecwid_api_no_credentials',
                __( 'API credentials are not configured.', 'ecwid-woocommerce' )
            );
            $this->last_error = $error->get_error_message();
            return $error;
        }

        // Check rate limit.
        if ( ! $this->check_rate_limit() ) {
            $error = new \WP_Error(
                'ecwid_api_rate_limit',
                __( 'API rate limit exceeded. Please wait a moment.', 'ecwid-woocommerce' )
            );
            $this->last_error = $error->get_error_message();
            return $error;
        }

        // Build URL.
        $url = self::API_BASE_URL . $this->store_id . '/' . ltrim( $endpoint, '/' );

        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        // Build request args.
        $args = array(
            'method'  => $method,
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        );

        if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        // Log request.
        $this->logger->debug(
            sprintf( 'Ecwid API %s request to %s', $method, $endpoint ),
            array(
                'url'    => $url,
                'params' => $params,
            ),
            'Ecwid_Api'
        );

        // Make request.
        $response = wp_remote_request( $url, $args );

        // Increment request count.
        $this->request_count++;

        // Handle errors.
        if ( is_wp_error( $response ) ) {
            $this->last_error = $response->get_error_message();
            $this->logger->error(
                'Ecwid API request failed: ' . $this->last_error,
                array( 'endpoint' => $endpoint ),
                'Ecwid_Api'
            );
            return $response;
        }

        // Parse response.
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        $this->last_response = array(
            'code' => $response_code,
            'body' => $response_body,
        );

        // Handle HTTP errors.
        if ( $response_code >= 400 ) {
            $error_message = $this->parse_error_response( $response_body, $response_code );
            $this->last_error = $error_message;

            $this->logger->error(
                'Ecwid API error: ' . $error_message,
                array(
                    'endpoint'      => $endpoint,
                    'response_code' => $response_code,
                    'response_body' => $response_body,
                ),
                'Ecwid_Api'
            );

            return new \WP_Error(
                'ecwid_api_error_' . $response_code,
                $error_message,
                array( 'status' => $response_code )
            );
        }

        // Decode JSON response.
        $decoded = json_decode( $response_body, true );

        if ( null === $decoded && ! empty( $response_body ) ) {
            $error = new \WP_Error(
                'ecwid_api_json_error',
                __( 'Failed to parse API response.', 'ecwid-woocommerce' )
            );
            $this->last_error = $error->get_error_message();
            return $error;
        }

        $this->logger->debug(
            sprintf( 'Ecwid API %s response from %s', $method, $endpoint ),
            array( 'response_code' => $response_code ),
            'Ecwid_Api'
        );

        return $decoded ?? array();
    }

    /**
     * Parse error response.
     *
     * @param string $body          Response body.
     * @param int    $response_code HTTP response code.
     * @return string
     */
    private function parse_error_response( $body, $response_code ) {
        $decoded = json_decode( $body, true );

        if ( isset( $decoded['errorMessage'] ) ) {
            return $decoded['errorMessage'];
        }

        if ( isset( $decoded['message'] ) ) {
            return $decoded['message'];
        }

        // Default error messages.
        $error_messages = array(
            400 => __( 'Bad request. Please check your request parameters.', 'ecwid-woocommerce' ),
            401 => __( 'Unauthorized. Please check your API credentials.', 'ecwid-woocommerce' ),
            403 => __( 'Forbidden. Your API token does not have permission for this action.', 'ecwid-woocommerce' ),
            404 => __( 'Not found. The requested resource does not exist.', 'ecwid-woocommerce' ),
            429 => __( 'Too many requests. API rate limit exceeded.', 'ecwid-woocommerce' ),
            500 => __( 'Internal server error. Please try again later.', 'ecwid-woocommerce' ),
            502 => __( 'Bad gateway. Please try again later.', 'ecwid-woocommerce' ),
            503 => __( 'Service unavailable. Please try again later.', 'ecwid-woocommerce' ),
        );

        return $error_messages[ $response_code ] ?? sprintf(
            /* translators: %d: HTTP response code */
            __( 'API error (HTTP %d)', 'ecwid-woocommerce' ),
            $response_code
        );
    }

    /**
     * Check rate limit.
     *
     * @return bool True if request can proceed, false if rate limited.
     */
    private function check_rate_limit() {
        $current_time = time();

        // Reset counter if window has passed.
        if ( $current_time - $this->request_window_start >= 60 ) {
            $this->request_count        = 0;
            $this->request_window_start = $current_time;
        }

        return $this->request_count < self::RATE_LIMIT;
    }

    /**
     * Get last error message.
     *
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Get last API response.
     *
     * @return array|null
     */
    public function get_last_response() {
        return $this->last_response;
    }

    /**
     * Validate store ID format.
     *
     * @param string $store_id Store ID to validate.
     * @return bool
     */
    public static function validate_store_id( $store_id ) {
        // Store ID should be numeric.
        return ! empty( $store_id ) && ctype_digit( (string) $store_id );
    }

    /**
     * Validate access token format.
     *
     * @param string $token Access token to validate.
     * @return bool
     */
    public static function validate_access_token( $token ) {
        // Token should be a non-empty string with reasonable length.
        return ! empty( $token ) && strlen( $token ) >= 10 && strlen( $token ) <= 500;
    }
}
