<?php
/**
 * Unit tests for Ecwid_API_Client
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase {

    /**
     * API client instance
     *
     * @var Ecwid_API_Client
     */
    private $api;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        
        clear_mock_options();
        clear_mock_responses();
        
        set_mock_option( 'ecwid_store_id', '12345' );
        set_mock_option( 'ecwid_api_token', 'test_token_123' );
        
        $this->api = new Ecwid_API_Client();
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
     * Test API client initialization
     */
    public function test_api_client_initialization() {
        $this->assertInstanceOf( Ecwid_API_Client::class, $this->api );
    }

    /**
     * Test is_configured returns true when credentials are set
     */
    public function test_is_configured_returns_true_with_credentials() {
        $this->assertTrue( $this->api->is_configured() );
    }

    /**
     * Test is_configured returns false without credentials
     */
    public function test_is_configured_returns_false_without_credentials() {
        clear_mock_options();
        $api = new Ecwid_API_Client();
        
        $this->assertFalse( $api->is_configured() );
    }

    /**
     * Test set_credentials method
     */
    public function test_set_credentials() {
        clear_mock_options();
        $api = new Ecwid_API_Client();
        
        $this->assertFalse( $api->is_configured() );
        
        $api->set_credentials( '99999', 'new_token' );
        
        $this->assertTrue( $api->is_configured() );
    }

    /**
     * Test get_orders returns array on success
     */
    public function test_get_orders_returns_array() {
        $mock_orders = array(
            'items' => array(
                array(
                    'id'     => 1001,
                    'total'  => 99.99,
                    'status' => 'PAID',
                ),
            ),
            'total' => 1,
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders?limit=100&offset=0',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_orders ),
            )
        );
        
        $result = $this->api->get_orders();
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'items', $result );
        $this->assertCount( 1, $result['items'] );
    }

    /**
     * Test get_order returns single order
     */
    public function test_get_single_order() {
        $mock_order = array(
            'id'              => 1001,
            'orderNumber'     => '00001',
            'total'           => 99.99,
            'paymentStatus'   => 'PAID',
            'fulfillmentStatus' => 'SHIPPED',
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders/1001',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_order ),
            )
        );
        
        $result = $this->api->get_order( 1001 );
        
        $this->assertIsArray( $result );
        $this->assertEquals( 1001, $result['id'] );
        $this->assertEquals( 'PAID', $result['paymentStatus'] );
    }

    /**
     * Test get_orders returns error when not configured
     */
    public function test_get_orders_returns_error_when_not_configured() {
        clear_mock_options();
        $api = new Ecwid_API_Client();
        
        $result = $api->get_orders();
        
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'not_configured', $result->get_error_code() );
    }

    /**
     * Test get_orders_by_date
     */
    public function test_get_orders_by_date() {
        $from_date = '2024-01-01T00:00:00Z';
        $to_date   = '2024-01-31T23:59:59Z';
        
        $mock_orders = array(
            'items' => array(),
            'total' => 0,
        );
        
        $expected_url = 'https://app.ecwid.com/api/v3/12345/orders?createdFrom=' . urlencode( $from_date ) 
                      . '&limit=100&offset=0&createdTo=' . urlencode( $to_date );
        
        set_mock_response(
            $expected_url,
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_orders ),
            )
        );
        
        $result = $this->api->get_orders_by_date( $from_date, $to_date );
        
        $this->assertIsArray( $result );
    }

    /**
     * Test get_customer
     */
    public function test_get_customer() {
        $mock_customer = array(
            'id'    => 501,
            'email' => 'test@example.com',
            'name'  => 'John Doe',
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/customers/501',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_customer ),
            )
        );
        
        $result = $this->api->get_customer( 501 );
        
        $this->assertIsArray( $result );
        $this->assertEquals( 501, $result['id'] );
        $this->assertEquals( 'test@example.com', $result['email'] );
    }

    /**
     * Test get_store_profile
     */
    public function test_get_store_profile() {
        $mock_profile = array(
            'generalInfo' => array(
                'storeUrl'  => 'https://store.example.com',
                'storeName' => 'Test Store',
            ),
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/profile',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_profile ),
            )
        );
        
        $result = $this->api->get_store_profile();
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'generalInfo', $result );
    }

    /**
     * Test test_connection success
     */
    public function test_connection_success() {
        $mock_profile = array(
            'generalInfo' => array(
                'storeName' => 'Test Store',
            ),
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/profile',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_profile ),
            )
        );
        
        $result = $this->api->test_connection();
        
        $this->assertTrue( $result );
    }

    /**
     * Test update_order_status
     */
    public function test_update_order_status() {
        $mock_response = array(
            'updateCount' => 1,
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders/1001',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_response ),
            )
        );
        
        $result = $this->api->update_order_status( 1001, 'SHIPPED' );
        
        $this->assertIsArray( $result );
    }

    /**
     * Test get_webhooks
     */
    public function test_get_webhooks() {
        $mock_webhooks = array(
            array(
                'id'         => 1,
                'url'        => 'https://example.com/webhook',
                'eventTypes' => array( 'order.created' ),
            ),
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/webhooks',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_webhooks ),
            )
        );
        
        $result = $this->api->get_webhooks();
        
        $this->assertIsArray( $result );
    }

    /**
     * Test register_webhook
     */
    public function test_register_webhook() {
        $mock_response = array(
            'id' => 123,
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/webhooks',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $mock_response ),
            )
        );
        
        $result = $this->api->register_webhook( 'https://example.com/webhook' );
        
        $this->assertIsArray( $result );
        $this->assertEquals( 123, $result['id'] );
    }
}
