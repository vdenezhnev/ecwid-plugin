<?php
/**
 * Integration tests for Ecwid_Webhook_Handler
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class WebhookHandlerTest extends TestCase {

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        
        clear_mock_options();
        clear_mock_responses();
        
        set_mock_option( 'ecwid_store_id', '12345' );
        set_mock_option( 'ecwid_api_token', 'test_token_123' );
        set_mock_option( 'ecwid_webhook_enabled', 'yes' );
        set_mock_option( 'ecwid_webhook_secret', 'test_secret_key' );
        set_mock_option( 'ecwid_create_customers', 'yes' );
    }

    /**
     * Tear down test fixtures
     */
    protected function tearDown(): void {
        clear_mock_options();
        clear_mock_responses();
        parent::tearDown();
    }

    /**
     * Test get_webhook_url returns REST URL
     */
    public function test_get_webhook_url_rest() {
        $url = Ecwid_Webhook_Handler::get_webhook_url( 'rest' );
        
        $this->assertStringContainsString( 'wp-json/ecwid-sync/v1/webhook', $url );
    }

    /**
     * Test get_webhook_url returns legacy URL
     */
    public function test_get_webhook_url_legacy() {
        $url = Ecwid_Webhook_Handler::get_webhook_url( 'legacy' );
        
        $this->assertStringContainsString( 'ecwid-webhook', $url );
    }

    /**
     * Test webhook handler initialization
     */
    public function test_webhook_handler_initialization() {
        $api         = new Ecwid_API_Client();
        $order_sync  = new Ecwid_Order_Sync( $api );
        $handler     = new Ecwid_Webhook_Handler( $order_sync );
        
        $this->assertInstanceOf( Ecwid_Webhook_Handler::class, $handler );
    }

    /**
     * Test add_rewrite_rules
     */
    public function test_add_rewrite_rules() {
        $api         = new Ecwid_API_Client();
        $order_sync  = new Ecwid_Order_Sync( $api );
        $handler     = new Ecwid_Webhook_Handler( $order_sync );
        
        // Should not throw exceptions
        $handler->add_rewrite_rules();
        
        $this->assertTrue( true );
    }

    /**
     * Test add_query_vars
     */
    public function test_add_query_vars() {
        $api         = new Ecwid_API_Client();
        $order_sync  = new Ecwid_Order_Sync( $api );
        $handler     = new Ecwid_Webhook_Handler( $order_sync );
        
        $vars = array( 'existing_var' );
        $result = $handler->add_query_vars( $vars );
        
        $this->assertContains( 'ecwid-webhook', $result );
        $this->assertContains( 'existing_var', $result );
    }

    /**
     * Test webhook endpoint constant
     */
    public function test_webhook_endpoint_constant() {
        $this->assertEquals( 'ecwid-webhook', Ecwid_Webhook_Handler::WEBHOOK_ENDPOINT );
    }
}
