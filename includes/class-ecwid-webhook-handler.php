<?php
/**
 * Ecwid Webhook Handler
 *
 * Handles incoming webhooks from Ecwid
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Webhook Handler class
 */
class Ecwid_Webhook_Handler {

    /**
     * Order sync instance
     *
     * @var Ecwid_Order_Sync
     */
    private $order_sync;

    /**
     * Logger instance
     *
     * @var Ecwid_Logger
     */
    private $logger;

    /**
     * Webhook endpoint
     *
     * @var string
     */
    const WEBHOOK_ENDPOINT = 'ecwid-webhook';

    /**
     * Constructor
     *
     * @param Ecwid_Order_Sync $order_sync Order sync instance.
     */
    public function __construct( Ecwid_Order_Sync $order_sync ) {
        $this->order_sync = $order_sync;
        $this->logger     = new Ecwid_Logger();

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register REST API endpoint
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Legacy query var approach for compatibility
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_legacy_webhook' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route(
            'ecwid-sync/v1',
            '/webhook',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_webhook' ),
                'permission_callback' => array( $this, 'verify_webhook' ),
            )
        );

        // Route for order updates from WooCommerce to Ecwid
        register_rest_route(
            'ecwid-sync/v1',
            '/sync-status/(?P<order_id>\d+)',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'sync_order_status_to_ecwid' ),
                'permission_callback' => array( $this, 'verify_internal_request' ),
                'args'                => array(
                    'order_id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );
    }

    /**
     * Add rewrite rules for legacy endpoint
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^' . self::WEBHOOK_ENDPOINT . '/?$',
            'index.php?' . self::WEBHOOK_ENDPOINT . '=1',
            'top'
        );
    }

    /**
     * Add query vars
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = self::WEBHOOK_ENDPOINT;
        return $vars;
    }

    /**
     * Handle legacy webhook endpoint
     */
    public function handle_legacy_webhook() {
        if ( get_query_var( self::WEBHOOK_ENDPOINT ) ) {
            $this->process_webhook_request();
            exit;
        }
    }

    /**
     * Verify webhook signature
     *
     * @param WP_REST_Request $request REST request.
     * @return bool|WP_Error
     */
    public function verify_webhook( $request ) {
        $webhook_enabled = get_option( 'ecwid_webhook_enabled', 'yes' );
        
        if ( 'yes' !== $webhook_enabled ) {
            return new WP_Error( 'webhook_disabled', __( 'Webhooks are disabled.', 'ecwid-woocommerce-sync' ), array( 'status' => 403 ) );
        }

        // Get the signature from headers
        $signature = $request->get_header( 'X-Ecwid-Webhook-Signature' );
        
        if ( empty( $signature ) ) {
            // Allow requests without signature in development mode
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $this->logger->warning( 'Webhook received without signature (allowed in debug mode)' );
                return true;
            }
            return new WP_Error( 'missing_signature', __( 'Missing webhook signature.', 'ecwid-woocommerce-sync' ), array( 'status' => 401 ) );
        }

        // Verify signature
        $secret    = get_option( 'ecwid_webhook_secret', '' );
        $body      = $request->get_body();
        $expected  = hash_hmac( 'sha256', $body, $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            $this->logger->error( 'Invalid webhook signature' );
            return new WP_Error( 'invalid_signature', __( 'Invalid webhook signature.', 'ecwid-woocommerce-sync' ), array( 'status' => 401 ) );
        }

        return true;
    }

    /**
     * Verify internal request
     *
     * @param WP_REST_Request $request REST request.
     * @return bool
     */
    public function verify_internal_request( $request ) {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Handle webhook request (REST API)
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_webhook( $request ) {
        $body = $request->get_json_params();

        if ( empty( $body ) ) {
            return new WP_Error( 'empty_body', __( 'Empty webhook body.', 'ecwid-woocommerce-sync' ), array( 'status' => 400 ) );
        }

        return $this->process_webhook_data( $body );
    }

    /**
     * Process webhook request (for legacy endpoint)
     */
    private function process_webhook_request() {
        $input = file_get_contents( 'php://input' );
        $body  = json_decode( $input, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $body ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid JSON body.', 'ecwid-woocommerce-sync' ) ), 400 );
        }

        // Verify signature
        $webhook_enabled = get_option( 'ecwid_webhook_enabled', 'yes' );
        
        if ( 'yes' !== $webhook_enabled ) {
            wp_send_json_error( array( 'message' => __( 'Webhooks are disabled.', 'ecwid-woocommerce-sync' ) ), 403 );
        }

        $signature = isset( $_SERVER['HTTP_X_ECWID_WEBHOOK_SIGNATURE'] ) 
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ECWID_WEBHOOK_SIGNATURE'] ) ) 
            : '';

        if ( empty( $signature ) && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing signature.', 'ecwid-woocommerce-sync' ) ), 401 );
        }

        if ( ! empty( $signature ) ) {
            $secret   = get_option( 'ecwid_webhook_secret', '' );
            $expected = hash_hmac( 'sha256', $input, $secret );

            if ( ! hash_equals( $expected, $signature ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid signature.', 'ecwid-woocommerce-sync' ) ), 401 );
            }
        }

        $result = $this->process_webhook_data( $body );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        wp_send_json_success( $result );
    }

    /**
     * Process webhook data
     *
     * @param array $data Webhook data.
     * @return array|WP_Error
     */
    private function process_webhook_data( $data ) {
        $this->logger->info( 'Webhook received', array(
            'event_type' => isset( $data['eventType'] ) ? $data['eventType'] : 'unknown',
        ) );

        if ( ! isset( $data['eventType'] ) ) {
            $this->logger->error( 'Webhook missing eventType' );
            return new WP_Error( 'missing_event_type', __( 'Missing event type.', 'ecwid-woocommerce-sync' ) );
        }

        $event_type = sanitize_text_field( $data['eventType'] );
        $entity_id  = isset( $data['entityId'] ) ? intval( $data['entityId'] ) : 0;

        switch ( $event_type ) {
            case 'order.created':
                return $this->handle_order_created( $entity_id, $data );

            case 'order.updated':
                return $this->handle_order_updated( $entity_id, $data );

            case 'order.deleted':
                return $this->handle_order_deleted( $entity_id, $data );

            case 'unfinished_order.created':
            case 'unfinished_order.updated':
                return $this->handle_unfinished_order( $entity_id, $data );

            default:
                $this->logger->info( 'Unhandled webhook event type', array( 'event_type' => $event_type ) );
                return array(
                    'status'  => 'ignored',
                    'message' => sprintf( __( 'Event type %s is not handled.', 'ecwid-woocommerce-sync' ), $event_type ),
                );
        }
    }

    /**
     * Handle order created event
     *
     * @param int   $order_id Ecwid order ID.
     * @param array $data     Event data.
     * @return array|WP_Error
     */
    private function handle_order_created( $order_id, $data ) {
        $this->logger->info( 'Processing order.created webhook', array( 'ecwid_order_id' => $order_id ) );

        // Import the order
        $result = $this->order_sync->import_order( $order_id );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed to import order from webhook', array(
                'ecwid_order_id' => $order_id,
                'error'          => $result->get_error_message(),
            ) );
            return $result;
        }

        $this->logger->info( 'Order imported successfully from webhook', array(
            'ecwid_order_id' => $order_id,
            'woo_order_id'   => $result,
        ) );

        return array(
            'status'       => 'success',
            'woo_order_id' => $result,
        );
    }

    /**
     * Handle order updated event
     *
     * @param int   $order_id Ecwid order ID.
     * @param array $data     Event data.
     * @return array|WP_Error
     */
    private function handle_order_updated( $order_id, $data ) {
        $this->logger->info( 'Processing order.updated webhook', array( 'ecwid_order_id' => $order_id ) );

        // Check if order exists in WooCommerce
        $woo_order_id = $this->order_sync->get_woo_order_id_by_ecwid_id( $order_id );

        if ( $woo_order_id ) {
            // Update existing order
            $result = $this->order_sync->update_order( $order_id );
        } else {
            // Import as new order
            $result = $this->order_sync->import_order( $order_id );
        }

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed to update order from webhook', array(
                'ecwid_order_id' => $order_id,
                'error'          => $result->get_error_message(),
            ) );
            return $result;
        }

        $this->logger->info( 'Order updated successfully from webhook', array(
            'ecwid_order_id' => $order_id,
            'woo_order_id'   => $result,
        ) );

        return array(
            'status'       => 'success',
            'woo_order_id' => $result,
        );
    }

    /**
     * Handle order deleted event
     *
     * @param int   $order_id Ecwid order ID.
     * @param array $data     Event data.
     * @return array
     */
    private function handle_order_deleted( $order_id, $data ) {
        $this->logger->info( 'Processing order.deleted webhook', array( 'ecwid_order_id' => $order_id ) );

        // Get WooCommerce order ID
        $woo_order_id = $this->order_sync->get_woo_order_id_by_ecwid_id( $order_id );

        if ( ! $woo_order_id ) {
            return array(
                'status'  => 'ignored',
                'message' => __( 'Order not found in WooCommerce.', 'ecwid-woocommerce-sync' ),
            );
        }

        // Mark as cancelled in WooCommerce
        $woo_order = wc_get_order( $woo_order_id );
        
        if ( $woo_order ) {
            $woo_order->update_status( 'cancelled', __( 'Order deleted in Ecwid.', 'ecwid-woocommerce-sync' ) );
            
            // Update sync record
            $this->order_sync->update_sync_record( $order_id, $woo_order_id, 'deleted' );

            $this->logger->info( 'Order cancelled in WooCommerce due to Ecwid deletion', array(
                'ecwid_order_id' => $order_id,
                'woo_order_id'   => $woo_order_id,
            ) );

            return array(
                'status'       => 'success',
                'woo_order_id' => $woo_order_id,
            );
        }

        return array(
            'status'  => 'error',
            'message' => __( 'Could not update WooCommerce order.', 'ecwid-woocommerce-sync' ),
        );
    }

    /**
     * Handle unfinished order events
     *
     * @param int   $order_id Ecwid order ID.
     * @param array $data     Event data.
     * @return array
     */
    private function handle_unfinished_order( $order_id, $data ) {
        $this->logger->info( 'Unfinished order event received', array(
            'ecwid_order_id' => $order_id,
            'event_type'     => $data['eventType'],
        ) );

        // Unfinished orders are typically abandoned carts - we may choose not to import these
        return array(
            'status'  => 'ignored',
            'message' => __( 'Unfinished orders are not imported.', 'ecwid-woocommerce-sync' ),
        );
    }

    /**
     * Sync order status to Ecwid
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function sync_order_status_to_ecwid( $request ) {
        $woo_order_id = intval( $request->get_param( 'order_id' ) );
        
        $result = $this->order_sync->sync_status_to_ecwid( $woo_order_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'status'  => 'success',
            'message' => __( 'Status synced to Ecwid.', 'ecwid-woocommerce-sync' ),
        ) );
    }

    /**
     * Get webhook URL
     *
     * @param string $type Type of URL ('rest' or 'legacy').
     * @return string
     */
    public static function get_webhook_url( $type = 'rest' ) {
        if ( 'legacy' === $type ) {
            return home_url( '/' . self::WEBHOOK_ENDPOINT . '/' );
        }

        return rest_url( 'ecwid-sync/v1/webhook' );
    }
}
