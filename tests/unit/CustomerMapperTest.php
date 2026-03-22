<?php
/**
 * Unit tests for Ecwid_Customer_Mapper
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class CustomerMapperTest extends TestCase {

    /**
     * Customer mapper instance
     *
     * @var Ecwid_Customer_Mapper
     */
    private $mapper;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        clear_mock_options();
        set_mock_option( 'ecwid_create_customers', 'yes' );
        $this->mapper = new Ecwid_Customer_Mapper();
    }

    /**
     * Tear down test fixtures
     */
    protected function tearDown(): void {
        clear_mock_options();
        parent::tearDown();
    }

    /**
     * Test map_customer returns false for empty email
     */
    public function test_map_customer_returns_false_for_empty_email() {
        $order = array(
            'customerId' => 123,
        );
        
        $result = $this->mapper->map_customer( $order );
        
        $this->assertFalse( $result );
    }

    /**
     * Test map_customer with valid email creates customer
     */
    public function test_map_customer_creates_customer_with_valid_email() {
        $order = array(
            'email'      => 'newuser@example.com',
            'customerId' => 123,
            'billingPerson' => array(
                'firstName' => 'John',
                'lastName'  => 'Doe',
            ),
        );
        
        $result = $this->mapper->map_customer( $order );
        
        $this->assertIsInt( $result );
        $this->assertGreaterThan( 0, $result );
    }

    /**
     * Test map_customer respects create_customers option
     */
    public function test_map_customer_respects_create_customers_option() {
        set_mock_option( 'ecwid_create_customers', 'no' );
        
        $mapper = new Ecwid_Customer_Mapper();
        
        $order = array(
            'email'      => 'newuser2@example.com',
            'customerId' => 456,
        );
        
        $result = $mapper->map_customer( $order );
        
        $this->assertFalse( $result );
    }

    /**
     * Test link_customer
     */
    public function test_link_customer() {
        // This should not throw any exceptions
        $this->mapper->link_customer( 1, 100 );
        
        $this->assertTrue( true );
    }

    /**
     * Test get_woo_customer_id returns false for non-existent
     */
    public function test_get_woo_customer_id_returns_false() {
        $result = $this->mapper->get_woo_customer_id( 99999 );
        
        $this->assertFalse( $result );
    }

    /**
     * Test get_ecwid_customer_id returns false for non-existent
     */
    public function test_get_ecwid_customer_id_returns_false() {
        $result = $this->mapper->get_ecwid_customer_id( 99999 );
        
        $this->assertFalse( $result );
    }

    /**
     * Test sync_customer_data returns false for invalid customer
     */
    public function test_sync_customer_data_returns_false_for_invalid() {
        $result = $this->mapper->sync_customer_data( 0, array() );
        
        $this->assertFalse( $result );
    }

    /**
     * Test map_customer uses shipping person if billing is empty
     */
    public function test_map_customer_uses_shipping_person() {
        $order = array(
            'email'          => 'shipping@example.com',
            'customerId'     => 789,
            'shippingPerson' => array(
                'firstName' => 'Jane',
                'lastName'  => 'Smith',
                'street'    => '123 Main St',
                'city'      => 'New York',
            ),
        );
        
        $result = $this->mapper->map_customer( $order );
        
        $this->assertIsInt( $result );
        $this->assertGreaterThan( 0, $result );
    }

    /**
     * Test sync_customer_data with valid customer
     */
    public function test_sync_customer_data_with_valid_customer() {
        $ecwid_customer = array(
            'name' => 'John Doe',
            'billingPerson' => array(
                'firstName' => 'John',
                'lastName'  => 'Doe',
                'street'    => '456 Oak Ave',
                'city'      => 'Los Angeles',
            ),
        );
        
        $result = $this->mapper->sync_customer_data( 1, $ecwid_customer );
        
        $this->assertTrue( $result );
    }

    /**
     * Test sync_customer_data with shipping addresses
     */
    public function test_sync_customer_data_with_shipping_addresses() {
        $ecwid_customer = array(
            'name' => 'Jane Smith',
            'shippingAddresses' => array(
                array(
                    'firstName' => 'Jane',
                    'lastName'  => 'Smith',
                    'street'    => '789 Pine Rd',
                    'city'      => 'Chicago',
                ),
            ),
        );
        
        $result = $this->mapper->sync_customer_data( 2, $ecwid_customer );
        
        $this->assertTrue( $result );
    }
}
