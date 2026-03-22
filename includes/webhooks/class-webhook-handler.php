<?php
/**
 * Webhook Handler Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Webhooks;

use Ecwid_WooCommerce\Api\Ecwid_Api;
use Ecwid_WooCommerce\Sync\Order_Sync;
use Ecwid_WooCommerce\Sync\Product_Sync;
use Ecwid_WooCommerce\Utils\Logger;
use Ecwid_WooCommerce\Utils\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Handles incoming webhooks from Ecwid.
 *
 * @since 1.0.0
 */
class Webhook_Handler {

    /**
     * Webhook endpoint slug.
     *
     * @var string
     */
    const ENDPOINT_SLUG = 'ecwid-wc-webhook';

    /**
     * Supported webhook events.
     *
     * @var array
     */
    const SUPPORTED_EVENTS = array(
        'order.created',
        'order.updated',
        'order.deleted',
        'product.created',
        'product.updated',
        'product.deleted',
        'inventory.updated',
        'profile.updated',
    );

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * API instance.
     *
     * @var Ecwid_Api
     */
    private $api;

    /**
     * Webhook secret.
     *
     * @var string
     */
    private $webhook_secret;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->api    = Ecwid_Api::get_instance();

        $encryption           = Encryption::get_instance();
        $this->webhook_secret = $encryption->get_decrypted_option( 'ecwid_wc_webhook_secret', '' );
    }

    /**
     * Initialize webhook handling.
     *
     * @return void
     */
    public function init() {
        // Register REST API endpoint.
        add_action( 'rest_api_init', array( $this, 'register_endpoint' ) );

        // Register rewrite rules for legacy endpoint.
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'handle_legacy_endpoint' ) );

        // Add query vars.
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
    }

    /**
     * Register REST API endpoint for webhooks.
     *
     * @return void
     */
    public function register_endpoint() {
        register_rest_route( 'ecwid-wc/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Add rewrite rules for legacy endpoint.
     *
     * @return void
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^' . self::ENDPOINT_SLUG . '/?$',
            'index.php?' . self::ENDPOINT_SLUG . '=1',
            'top'
        );
    }

    /**
     * Add query vars.
     *
     * @param array $vars Query vars.
     * @return array Modified query vars.
     */
    public function add_query_vars( $vars ) {
        $vars[] = self::ENDPOINT_SLUG;
        return $vars;
    }

    /**
     * Handle legacy endpoint.
     *
     * @return void
     */
    public function handle_legacy_endpoint() {
        if ( ! get_query_var( self::ENDPOINT_SLUG ) ) {
            return;
        }

        $this->handle_webhook( new \WP_REST_Request( 'POST' ) );
        exit;
    }

    /**
     * Handle incoming webhook.
     *
     * @param \WP_REST_Request $request REST request.
     * @return \WP_REST_Response Response.
     */
    public function handle_webhook( $request ) {
        // Get raw body.
        $body = file_get_contents( 'php://input' );

        if ( empty( $body ) ) {
            $this->logger->warning( 'Webhook received with empty body', array(), 'Webhook_Handler' );
            return new \WP_REST_Response( array( 'error' => 'Empty body' ), 400 );
        }

        // Parse JSON.
        $payload = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->logger->warning( 'Webhook received with invalid JSON', array(), 'Webhook_Handler' );
            return new \WP_REST_Response( array( 'error' => 'Invalid JSON' ), 400 );
        }

        // Verify webhook signature.
        if ( ! $this->verify_signature( $body, $request ) ) {
            $this->logger->warning( 'Webhook signature verification failed', array(), 'Webhook_Handler' );
            return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
        }

        // Get event type.
        $event_type = $payload['eventType'] ?? '';

        if ( empty( $event_type ) ) {
            $this->logger->warning( 'Webhook received without event type', array(), 'Webhook_Handler' );
            return new \WP_REST_Response( array( 'error' => 'Missing event type' ), 400 );
        }

        $this->logger->info(
            sprintf( 'Webhook received: %s', $event_type ),
            array( 'payload_keys' => array_keys( $payload ) ),
            'Webhook_Handler'
        );

        // Process webhook.
        $result = $this->process_webhook( $event_type, $payload );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( array(
                'error'   => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ), 500 );
        }

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => $result,
        ), 200 );
    }

    /**
     * Verify webhook signature.
     *
     * @param string           $body    Raw request body.
     * @param \WP_REST_Request $request REST request.
     * @return bool True if valid.
     */
    private function verify_signature( $body, $request ) {
        // If no secret configured, skip verification (not recommended).
        if ( empty( $this->webhook_secret ) ) {
            $this->logger->warning(
                'Webhook signature verification skipped - no secret configured',
                array(),
                'Webhook_Handler'
            );
            return true;
        }

        // Get signature from header.
        $signature = '';

        if ( $request instanceof \WP_REST_Request ) {
            $signature = $request->get_header( 'X-Ecwid-Webhook-Signature' );
        } else {
            $signature = isset( $_SERVER['HTTP_X_ECWID_WEBHOOK_SIGNATURE'] )
                ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ECWID_WEBHOOK_SIGNATURE'] ) )
                : '';
        }

        if ( empty( $signature ) ) {
            return false;
        }

        // Calculate expected signature.
        $expected = base64_encode( hash_hmac( 'sha256', $body, $this->webhook_secret, true ) );

        return hash_equals( $expected, $signature );
    }

    /**
     * Process webhook by event type.
     *
     * @param string $event_type Event type.
     * @param array  $payload    Webhook payload.
     * @return string|\WP_Error Success message or error.
     */
    private function process_webhook( $event_type, $payload ) {
        // Check if event is supported.
        if ( ! in_array( $event_type, self::SUPPORTED_EVENTS, true ) ) {
            $this->logger->info(
                sprintf( 'Ignoring unsupported webhook event: %s', $event_type ),
                array(),
                'Webhook_Handler'
            );
            return 'Event ignored';
        }

        // Route to appropriate handler.
        switch ( $event_type ) {
            case 'order.created':
            case 'order.updated':
                return $this->handle_order_webhook( $payload );

            case 'order.deleted':
                return $this->handle_order_deleted( $payload );

            case 'product.created':
            case 'product.updated':
                return $this->handle_product_webhook( $payload );

            case 'product.deleted':
                return $this->handle_product_deleted( $payload );

            case 'inventory.updated':
                return $this->handle_inventory_webhook( $payload );

            case 'profile.updated':
                return $this->handle_profile_webhook( $payload );

            default:
                return 'Event not handled';
        }
    }

    /**
     * Handle order created/updated webhook.
     *
     * @param array $payload Webhook payload.
     * @return string|\WP_Error Success message or error.
     */
    private function handle_order_webhook( $payload ) {
        $order_id = $payload['entityId'] ?? $payload['orderId'] ?? null;

        if ( ! $order_id ) {
            return new \WP_Error( 'missing_order_id', 'Order ID not found in payload' );
        }

        // Check sync direction.
        $sync_direction = get_option( 'ecwid_wc_sync_direction', 'ecwid_to_wc' );
        $sync_orders    = get_option( 'ecwid_wc_sync_orders', true );

        if ( ! $sync_orders || 'wc_to_ecwid' === $sync_direction ) {
            return 'Order sync disabled or wrong direction';
        }

        // Fetch full order data from Ecwid.
        $ecwid_order = $this->api->get_order( $order_id );

        if ( is_wp_error( $ecwid_order ) ) {
            return $ecwid_order;
        }

        // Import/update order.
        $order_sync = new Order_Sync();
        $result     = $order_sync->import_order( $ecwid_order );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'order_sync_failed', $result['message'] );
        }

        return sprintf( 'Order synced: WC ID %d', $result['wc_id'] );
    }

    /**
     * Handle order deleted webhook.
     *
     * @param array $payload Webhook payload.
     * @return string|\WP_Error Success message or error.
     */
    private function handle_order_deleted( $payload ) {
        $order_id = $payload['entityId'] ?? $payload['orderId'] ?? null;

        if ( ! $order_id ) {
            return new \WP_Error( 'missing_order_id', 'Order ID not found in payload' );
        }

        // Find mapping.
        $mapping_repo = \Ecwid_WooCommerce\Utils\Mapping_Repository::get_instance();
        $mapping      = $mapping_repo->find_by_ecwid_id( 'order', $order_id );

        if ( ! $mapping ) {
            return 'Order not found in mapping';
        }

        // Get WC order.
        $wc_order = wc_get_order( $mapping->wc_id );

        if ( $wc_order ) {
            // Update status to cancelled instead of deleting.
            $wc_order->update_status( 'cancelled', __( 'Order deleted in Ecwid.', 'ecwid-woocommerce' ) );
            $wc_order->save();
        }

        // Remove mapping.
        $mapping_repo->delete( $mapping->id );

        $this->logger->info(
            sprintf( 'Handled order deleted webhook for Ecwid order #%s', $order_id ),
            array(),
            'Webhook_Handler'
        );

        return 'Order deletion handled';
    }

    /**
     * Handle product created/updated webhook.
     *
     * @param array $payload Webhook payload.
     * @return string|\WP_Error Success message or error.
     */
    private function handle_product_webhook( $payload ) {
        $product_id = $payload['entityId'] ?? $payload['productId'] ?? null;

        if ( ! $product_id ) {
            return new \WP_Error( 'missing_product_id', 'Product ID not found in payload' );
        }

        // Check sync direction.
        $sync_direction = get_option( 'ecwid_wc_sync_direction', 'ecwid_to_wc' );
        $sync_products  = get_option( 'ecwid_wc_sync_products', true );

        if ( ! $sync_products || 'wc_to_ecwid' === $sync_direction ) {
            return 'Product sync disabled or wrong direction';
        }

        $this->logger->info(
            sprintf( 'Received product webhook for Ecwid product #%s', $product_id ),
            array(),
            'Webhook_Handler'
        );

        // Queue for import (would need Product import implementation).
        // For now, just log it.
        return sprintf( 'Product webhook received for ID %s', $product_id );
    }

    /**
     * Handle product deleted webhook.
     *
     * @param array $payload Webhook payload.
     * @return string|\WP_Error Success message or error.
     */
    private function handle_product_deleted( $payload ) {
        $product_id = $payload['entityId'] ?? $payload['productId'] ?? null;

        if ( ! $product_id ) {
            return new \WP_Error( 'missing_product_id', 'Product ID not found in payload' );
        }

        // Find mapping.
        $mapping_repo = \Ecwid_WooCommerce\Utils\Mapping_Repository::get_instance();
        $mapping      = $mapping_repo->find_by_ecwid_id( 'product', $product_id );

        if ( ! $mapping ) {
            return 'Product not found in mapping';
        }

        // Get WC product.
        $wc_product = wc_get_product( $mapping->wc_id );

        if ( $wc_product ) {
            // Move to trash instead of deleting.
            $wc_product->set_status( 'trash' );
            $wc_product->save();
        }

        // Remove mapping.
        $mapping_repo->delete( $mapping->id );

        $this->logger->info(
            sprintf( 'Handled product deleted webhook for Ecwid product #%s', $product_id ),
            array(),
            'Webhook_Handler'
        );

        return 'Product deletion handled';
    }

    /**
     * Handle inventory updated webhook.
     *
     * @param array $payload Webhook payload.
     * @return string|\WP_Error Success message or error.
     */
    private function handle_inventory_webhook( $payload ) {
        $product_id = $payload['entityId'] ?? $payload['productId'] ?? null;
        $quantity   = $payload['quantity'] ?? null;

        if ( ! $product_id ) {
            return new \WP_Error( 'missing_product_id', 'Product ID not found in payload' );
        }

        // Find mapping.
        $mapping_repo = \Ecwid_WooCommerce\Utils\Mapping_Repository::get_instance();
        $mapping      = $mapping_repo->find_by_ecwid_id( 'product', $product_id );

        if ( ! $mapping ) {
            return 'Product not found in mapping';
        }

        // Get WC product.
        $wc_product = wc_get_product( $mapping->wc_id );

        if ( ! $wc_product ) {
            return 'WooCommerce product not found';
        }

        // Update stock.
        if ( null !== $quantity && $wc_product->get_manage_stock() ) {
            $wc_product->set_stock_quantity( (int) $quantity );
            $wc_product->save();

            $this->logger->info(
                sprintf( 'Updated stock for WC product #%d to %d', $mapping->wc_id, $quantity ),
                array(),
                'Webhook_Handler'
            );
        }

        return 'Inventory updated';
    }

    /**
     * Handle profile updated webhook.
     *
     * @param array $payload Webhook payload.
     * @return string Success message.
     */
    private function handle_profile_webhook( $payload ) {
        // Clear cached store info.
        delete_transient( 'ecwid_wc_store_profile' );
        delete_transient( 'ecwid_wc_connection_status' );

        $this->logger->info( 'Store profile updated webhook received', array(), 'Webhook_Handler' );

        return 'Profile cache cleared';
    }

    /**
     * Get webhook URL.
     *
     * @return string Webhook URL.
     */
    public static function get_webhook_url() {
        return rest_url( 'ecwid-wc/v1/webhook' );
    }

    /**
     * Get legacy webhook URL.
     *
     * @return string Legacy webhook URL.
     */
    public static function get_legacy_webhook_url() {
        return home_url( '/' . self::ENDPOINT_SLUG . '/' );
    }

    /**
     * Register webhooks with Ecwid.
     *
     * @param array $events Event types to register.
     * @return array{success: bool, webhook_ids: array, errors: array}
     */
    public function register_webhooks( $events = array() ) {
        if ( empty( $events ) ) {
            $events = array( 'order.created', 'order.updated' );
        }

        $webhook_url = self::get_webhook_url();
        $results     = array(
            'success'     => true,
            'webhook_ids' => array(),
            'errors'      => array(),
        );

        foreach ( $events as $event ) {
            $response = $this->api->create_webhook( array(
                'eventType' => $event,
                'url'       => $webhook_url,
            ) );

            if ( is_wp_error( $response ) ) {
                $results['success']  = false;
                $results['errors'][] = array(
                    'event'   => $event,
                    'message' => $response->get_error_message(),
                );
            } else {
                $results['webhook_ids'][] = $response['id'] ?? '';
            }
        }

        return $results;
    }

    /**
     * Unregister all webhooks.
     *
     * @return array{success: bool, deleted: int, errors: array}
     */
    public function unregister_webhooks() {
        $results = array(
            'success' => true,
            'deleted' => 0,
            'errors'  => array(),
        );

        // Get existing webhooks.
        $webhooks = $this->api->get_webhooks();

        if ( is_wp_error( $webhooks ) ) {
            $results['success']  = false;
            $results['errors'][] = $webhooks->get_error_message();
            return $results;
        }

        $webhook_url = self::get_webhook_url();

        foreach ( $webhooks as $webhook ) {
            // Only delete our webhooks.
            if ( isset( $webhook['url'] ) && strpos( $webhook['url'], $webhook_url ) !== false ) {
                $response = $this->api->delete_webhook( $webhook['id'] );

                if ( is_wp_error( $response ) ) {
                    $results['errors'][] = $response->get_error_message();
                } else {
                    $results['deleted']++;
                }
            }
        }

        return $results;
    }

    /**
     * Generate webhook secret.
     *
     * @return string Generated secret.
     */
    public static function generate_secret() {
        return wp_generate_password( 32, true, true );
    }
}
