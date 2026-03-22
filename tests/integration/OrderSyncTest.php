<?php
/**
 * Integration tests for Ecwid_Order_Sync
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class OrderSyncTest extends TestCase {

    /**
     * API client instance
     *
     * @var Ecwid_API_Client
     */
    private $api;

    /**
     * Order sync instance
     *
     * @var Ecwid_Order_Sync
     */
    private $order_sync;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        
        clear_mock_options();
        clear_mock_responses();
        
        set_mock_option( 'ecwid_store_id', '12345' );
        set_mock_option( 'ecwid_api_token', 'test_token_123' );
        set_mock_option( 'ecwid_create_customers', 'yes' );
        
        $this->api        = new Ecwid_API_Client();
        $this->order_sync = new Ecwid_Order_Sync( $this->api );
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
     * Get sample Ecwid order data
     */
    private function get_sample_order() {
        return array(
            'id'                => 1001,
            'orderNumber'       => '00001',
            'email'             => 'customer@example.com',
            'customerId'        => 501,
            'paymentStatus'     => 'PAID',
            'fulfillmentStatus' => 'AWAITING_PROCESSING',
            'paymentMethod'     => 'Stripe',
            'total'             => 99.99,
            'subtotal'          => 89.99,
            'tax'               => 5.00,
            'shippingAndHandling' => 5.00,
            'createDate'        => '2024-01-15T10:30:00Z',
            'billingPerson'     => array(
                'firstName'          => 'John',
                'lastName'           => 'Doe',
                'companyName'        => 'ACME Corp',
                'street'             => '123 Main St',
                'city'               => 'New York',
                'stateOrProvinceCode' => 'NY',
                'postalCode'         => '10001',
                'countryCode'        => 'US',
                'phone'              => '+1234567890',
            ),
            'shippingPerson'    => array(
                'firstName'          => 'John',
                'lastName'           => 'Doe',
                'street'             => '123 Main St',
                'city'               => 'New York',
                'stateOrProvinceCode' => 'NY',
                'postalCode'         => '10001',
                'countryCode'        => 'US',
            ),
            'shippingOption'    => array(
                'shippingMethodName' => 'Standard Shipping',
            ),
            'items'             => array(
                array(
                    'productId' => 101,
                    'name'      => 'Test Product',
                    'sku'       => 'TEST-001',
                    'quantity'  => 2,
                    'price'     => 44.99,
                ),
            ),
            'orderComments'     => 'Please deliver before 5pm',
        );
    }

    /**
     * Test import_order creates WooCommerce order
     */
    public function test_import_order_creates_woo_order() {
        $ecwid_order = $this->get_sample_order();
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders/1001',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $ecwid_order ),
            )
        );
        
        $result = $this->order_sync->import_order( 1001 );
        
        $this->assertIsInt( $result );
        $this->assertGreaterThan( 0, $result );
    }

    /**
     * Test import_order returns error for invalid order
     */
    public function test_import_order_returns_error_for_invalid() {
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders/9999',
            array(
                'response' => array( 'code' => 404 ),
                'body'     => json_encode( array( 'errorMessage' => 'Order not found' ) ),
            )
        );
        
        $result = $this->order_sync->import_order( 9999 );
        
        $this->assertInstanceOf( WP_Error::class, $result );
    }

    /**
     * Test sync_orders_since
     */
    public function test_sync_orders_since() {
        $orders_response = array(
            'items' => array(
                $this->get_sample_order(),
            ),
            'total' => 1,
        );
        
        $since_date = '2024-01-01T00:00:00Z';
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders?updatedFrom=' . urlencode( $since_date ) . '&limit=100&offset=0',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $orders_response ),
            )
        );
        
        $result = $this->order_sync->sync_orders_since( $since_date );
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'imported', $result );
        $this->assertArrayHasKey( 'updated', $result );
        $this->assertArrayHasKey( 'skipped', $result );
        $this->assertArrayHasKey( 'errors', $result );
    }

    /**
     * Test sync_all_orders
     */
    public function test_sync_all_orders() {
        $orders_response = array(
            'items' => array(
                $this->get_sample_order(),
            ),
            'total' => 1,
        );
        
        // Mock will return empty for pagination
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders?offset=0&limit=100',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $orders_response ),
            )
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders?offset=100&limit=100',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( array( 'items' => array() ) ),
            )
        );
        
        $result = $this->order_sync->sync_all_orders();
        
        $this->assertIsArray( $result );
    }

    /**
     * Test get_woo_order_id_by_ecwid_id returns false for unknown
     */
    public function test_get_woo_order_id_by_ecwid_id_returns_false() {
        $result = $this->order_sync->get_woo_order_id_by_ecwid_id( 99999 );
        
        $this->assertFalse( $result );
    }

    /**
     * Test get_ecwid_order_id_by_woo_id returns false for non-ecwid order
     */
    public function test_get_ecwid_order_id_by_woo_id_returns_false() {
        $result = $this->order_sync->get_ecwid_order_id_by_woo_id( 99999 );
        
        $this->assertFalse( $result );
    }

    /**
     * Test sync_status_to_ecwid returns error for non-ecwid order
     */
    public function test_sync_status_to_ecwid_returns_error_for_non_ecwid() {
        // Mock returns order without Ecwid metadata
        $result = $this->order_sync->sync_status_to_ecwid( 1 );
        
        $this->assertInstanceOf( WP_Error::class, $result );
    }

    /**
     * Test update_sync_record
     */
    public function test_update_sync_record() {
        // Should not throw exception
        $this->order_sync->update_sync_record( 1001, 1, 'synced' );
        
        $this->assertTrue( true );
    }

    /**
     * Test order with discount
     */
    public function test_order_with_discount() {
        $ecwid_order = $this->get_sample_order();
        $ecwid_order['discount'] = 10.00;
        $ecwid_order['couponDiscount'] = 5.00;
        $ecwid_order['discountCoupon'] = array(
            'code' => 'SAVE5',
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders/1001',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $ecwid_order ),
            )
        );
        
        $result = $this->order_sync->import_order( 1001 );
        
        $this->assertIsInt( $result );
    }

    /**
     * Test order with multiple items
     */
    public function test_order_with_multiple_items() {
        $ecwid_order = $this->get_sample_order();
        $ecwid_order['items'][] = array(
            'productId' => 102,
            'name'      => 'Another Product',
            'sku'       => 'TEST-002',
            'quantity'  => 1,
            'price'     => 29.99,
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders/1001',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $ecwid_order ),
            )
        );
        
        $result = $this->order_sync->import_order( 1001 );
        
        $this->assertIsInt( $result );
    }

    /**
     * Test process orders with empty array
     */
    public function test_sync_orders_with_empty_array() {
        $orders_response = array(
            'items' => array(),
            'total' => 0,
        );
        
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders?updatedFrom=2024-01-01T00%3A00%3A00Z&limit=100&offset=0',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $orders_response ),
            )
        );
        
        $result = $this->order_sync->sync_orders_since( '2024-01-01T00:00:00Z' );
        
        $this->assertEquals( 0, $result['imported'] );
        $this->assertEquals( 0, $result['updated'] );
        $this->assertEquals( 0, $result['errors'] );
    }
}
