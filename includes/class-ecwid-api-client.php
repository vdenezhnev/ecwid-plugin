<?php
/**
 * Ecwid API Client
 *
 * Handles all communication with Ecwid REST API
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid API Client class
 */
class Ecwid_API_Client {

    /**
     * Ecwid API base URL
     *
     * @var string
     */
    private $api_base = 'https://app.ecwid.com/api/v3';

    /**
     * Store ID
     *
     * @var string
     */
    private $store_id;

    /**
     * API Token
     *
     * @var string
     */
    private $api_token;

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
        $this->store_id  = get_option( 'ecwid_store_id', '' );
        $this->api_token = get_option( 'ecwid_api_token', '' );
        $this->logger    = new Ecwid_Logger();
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->store_id ) && ! empty( $this->api_token );
    }

    /**
     * Set credentials
     *
     * @param string $store_id  Store ID.
     * @param string $api_token API Token.
     */
    public function set_credentials( $store_id, $api_token ) {
        $this->store_id  = $store_id;
        $this->api_token = $api_token;
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint.
     * @param string $method   HTTP method.
     * @param array  $data     Request data.
     * @return array|WP_Error
     */
    private function request( $endpoint, $method = 'GET', $data = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'Ecwid API is not configured.', 'ecwid-woocommerce-sync' ) );
        }

        $url = sprintf( '%s/%s/%s', $this->api_base, $this->store_id, ltrim( $endpoint, '/' ) );

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        );

        if ( 'GET' === $method && ! empty( $data ) ) {
            $url = add_query_arg( $data, $url );
        } elseif ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $this->logger->debug( 'API Request', array(
            'url'    => $url,
            'method' => $method,
        ) );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'API Request Failed', array(
                'error' => $response->get_error_message(),
            ) );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 ) {
            $error_message = isset( $data['errorMessage'] ) ? $data['errorMessage'] : __( 'Unknown API error', 'ecwid-woocommerce-sync' );
            $this->logger->error( 'API Error Response', array(
                'code'    => $code,
                'message' => $error_message,
            ) );
            return new WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
        }

        return $data;
    }

    /**
     * Get orders
     *
     * @param array $params Query parameters.
     * @return array|WP_Error
     */
    public function get_orders( $params = array() ) {
        $defaults = array(
            'limit'  => 100,
            'offset' => 0,
        );

        $params = wp_parse_args( $params, $defaults );

        return $this->request( 'orders', 'GET', $params );
    }

    /**
     * Get single order
     *
     * @param int $order_id Ecwid order ID.
     * @return array|WP_Error
     */
    public function get_order( $order_id ) {
        return $this->request( 'orders/' . intval( $order_id ) );
    }

    /**
     * Get orders by date range
     *
     * @param string $from_date From date (ISO 8601).
     * @param string $to_date   To date (ISO 8601).
     * @param int    $limit     Limit.
     * @param int    $offset    Offset.
     * @return array|WP_Error
     */
    public function get_orders_by_date( $from_date, $to_date = '', $limit = 100, $offset = 0 ) {
        $params = array(
            'createdFrom' => $from_date,
            'limit'       => $limit,
            'offset'      => $offset,
        );

        if ( ! empty( $to_date ) ) {
            $params['createdTo'] = $to_date;
        }

        return $this->request( 'orders', 'GET', $params );
    }

    /**
     * Get orders updated since
     *
     * @param string $since_date Updated since date (ISO 8601).
     * @param int    $limit      Limit.
     * @param int    $offset     Offset.
     * @return array|WP_Error
     */
    public function get_orders_updated_since( $since_date, $limit = 100, $offset = 0 ) {
        $params = array(
            'updatedFrom' => $since_date,
            'limit'       => $limit,
            'offset'      => $offset,
        );

        return $this->request( 'orders', 'GET', $params );
    }

    /**
     * Update order status
     *
     * @param int    $order_id      Ecwid order ID.
     * @param string $status        New status.
     * @param string $tracking_info Tracking information.
     * @return array|WP_Error
     */
    public function update_order_status( $order_id, $status, $tracking_info = '' ) {
        $data = array(
            'fulfillmentStatus' => $status,
        );

        if ( ! empty( $tracking_info ) ) {
            $data['trackingNumber'] = $tracking_info;
        }

        return $this->request( 'orders/' . intval( $order_id ), 'PUT', $data );
    }

    /**
     * Get customer
     *
     * @param int $customer_id Ecwid customer ID.
     * @return array|WP_Error
     */
    public function get_customer( $customer_id ) {
        return $this->request( 'customers/' . intval( $customer_id ) );
    }

    /**
     * Search customers by email
     *
     * @param string $email Customer email.
     * @return array|WP_Error
     */
    public function get_customer_by_email( $email ) {
        return $this->request( 'customers', 'GET', array( 'email' => sanitize_email( $email ) ) );
    }

    /**
     * Get store profile
     *
     * @return array|WP_Error
     */
    public function get_store_profile() {
        return $this->request( 'profile' );
    }

    /**
     * Test API connection
     *
     * @return bool|WP_Error
     */
    public function test_connection() {
        $result = $this->get_store_profile();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    /**
     * Get all orders (handles pagination)
     *
     * @param array $params Base query parameters.
     * @return array|WP_Error
     */
    public function get_all_orders( $params = array() ) {
        $all_orders = array();
        $offset     = 0;
        $limit      = 100;

        do {
            $params['offset'] = $offset;
            $params['limit']  = $limit;

            $result = $this->get_orders( $params );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            if ( empty( $result['items'] ) ) {
                break;
            }

            $all_orders = array_merge( $all_orders, $result['items'] );
            $offset    += $limit;

            // Prevent infinite loops
            if ( $offset > 10000 ) {
                break;
            }

        } while ( count( $result['items'] ) === $limit );

        return array(
            'items' => $all_orders,
            'total' => count( $all_orders ),
        );
    }

    /**
     * Register webhook
     *
     * @param string $url    Webhook URL.
     * @param array  $events Events to subscribe to.
     * @return array|WP_Error
     */
    public function register_webhook( $url, $events = array() ) {
        if ( empty( $events ) ) {
            $events = array(
                'order.created',
                'order.updated',
                'order.deleted',
            );
        }

        $data = array(
            'url'        => $url,
            'eventTypes' => $events,
        );

        return $this->request( 'webhooks', 'POST', $data );
    }

    /**
     * Delete webhook
     *
     * @param int $webhook_id Webhook ID.
     * @return array|WP_Error
     */
    public function delete_webhook( $webhook_id ) {
        return $this->request( 'webhooks/' . intval( $webhook_id ), 'DELETE' );
    }

    /**
     * Get registered webhooks
     *
     * @return array|WP_Error
     */
    public function get_webhooks() {
        return $this->request( 'webhooks' );
    }
}
