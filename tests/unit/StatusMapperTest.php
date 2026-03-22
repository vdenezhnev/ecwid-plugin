<?php
/**
 * Unit tests for Ecwid_Status_Mapper
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class StatusMapperTest extends TestCase {

    /**
     * Status mapper instance
     *
     * @var Ecwid_Status_Mapper
     */
    private $mapper;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        clear_mock_options();
        $this->mapper = new Ecwid_Status_Mapper();
    }

    /**
     * Tear down test fixtures
     */
    protected function tearDown(): void {
        clear_mock_options();
        parent::tearDown();
    }

    /**
     * Test paid + awaiting processing maps to processing
     */
    public function test_paid_awaiting_processing_maps_to_processing() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'AWAITING_PROCESSING' );
        $this->assertEquals( 'processing', $result );
    }

    /**
     * Test paid + shipped maps to completed
     */
    public function test_paid_shipped_maps_to_completed() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'SHIPPED' );
        $this->assertEquals( 'completed', $result );
    }

    /**
     * Test paid + delivered maps to completed
     */
    public function test_paid_delivered_maps_to_completed() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'DELIVERED' );
        $this->assertEquals( 'completed', $result );
    }

    /**
     * Test awaiting payment maps to pending
     */
    public function test_awaiting_payment_maps_to_pending() {
        $result = $this->mapper->ecwid_to_woo( 'AWAITING_PAYMENT', 'AWAITING_PROCESSING' );
        $this->assertEquals( 'pending', $result );
    }

    /**
     * Test cancelled maps to cancelled
     */
    public function test_cancelled_maps_to_cancelled() {
        $result = $this->mapper->ecwid_to_woo( 'CANCELLED', 'AWAITING_PROCESSING' );
        $this->assertEquals( 'cancelled', $result );
    }

    /**
     * Test refunded maps to refunded
     */
    public function test_refunded_maps_to_refunded() {
        $result = $this->mapper->ecwid_to_woo( 'REFUNDED', 'SHIPPED' );
        $this->assertEquals( 'refunded', $result );
    }

    /**
     * Test paid + will not deliver maps to cancelled
     */
    public function test_paid_will_not_deliver_maps_to_cancelled() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'WILL_NOT_DELIVER' );
        $this->assertEquals( 'cancelled', $result );
    }

    /**
     * Test paid + returned maps to refunded
     */
    public function test_paid_returned_maps_to_refunded() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'RETURNED' );
        $this->assertEquals( 'refunded', $result );
    }

    /**
     * Test incomplete maps to pending
     */
    public function test_incomplete_maps_to_pending() {
        $result = $this->mapper->ecwid_to_woo( 'INCOMPLETE', 'AWAITING_PROCESSING' );
        $this->assertEquals( 'pending', $result );
    }

    /**
     * Test paid + processing maps to processing
     */
    public function test_paid_processing_maps_to_processing() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'PROCESSING' );
        $this->assertEquals( 'processing', $result );
    }

    /**
     * Test paid + out for delivery maps to completed
     */
    public function test_paid_out_for_delivery_maps_to_completed() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'OUT_FOR_DELIVERY' );
        $this->assertEquals( 'completed', $result );
    }

    /**
     * Test paid + ready for pickup maps to processing
     */
    public function test_paid_ready_for_pickup_maps_to_processing() {
        $result = $this->mapper->ecwid_to_woo( 'PAID', 'READY_FOR_PICKUP' );
        $this->assertEquals( 'processing', $result );
    }

    /**
     * Test WooCommerce to Ecwid: pending maps to AWAITING_PROCESSING
     */
    public function test_woo_pending_maps_to_awaiting_processing() {
        $result = $this->mapper->woo_to_ecwid( 'pending' );
        $this->assertEquals( 'AWAITING_PROCESSING', $result );
    }

    /**
     * Test WooCommerce to Ecwid: processing maps to PROCESSING
     */
    public function test_woo_processing_maps_to_processing() {
        $result = $this->mapper->woo_to_ecwid( 'processing' );
        $this->assertEquals( 'PROCESSING', $result );
    }

    /**
     * Test WooCommerce to Ecwid: completed maps to SHIPPED
     */
    public function test_woo_completed_maps_to_shipped() {
        $result = $this->mapper->woo_to_ecwid( 'completed' );
        $this->assertEquals( 'SHIPPED', $result );
    }

    /**
     * Test WooCommerce to Ecwid: cancelled maps to WILL_NOT_DELIVER
     */
    public function test_woo_cancelled_maps_to_will_not_deliver() {
        $result = $this->mapper->woo_to_ecwid( 'cancelled' );
        $this->assertEquals( 'WILL_NOT_DELIVER', $result );
    }

    /**
     * Test WooCommerce to Ecwid: refunded maps to RETURNED
     */
    public function test_woo_refunded_maps_to_returned() {
        $result = $this->mapper->woo_to_ecwid( 'refunded' );
        $this->assertEquals( 'RETURNED', $result );
    }

    /**
     * Test WooCommerce to Ecwid: on-hold maps to AWAITING_PROCESSING
     */
    public function test_woo_on_hold_maps_to_awaiting_processing() {
        $result = $this->mapper->woo_to_ecwid( 'on-hold' );
        $this->assertEquals( 'AWAITING_PROCESSING', $result );
    }

    /**
     * Test WooCommerce to Ecwid: failed maps to WILL_NOT_DELIVER
     */
    public function test_woo_failed_maps_to_will_not_deliver() {
        $result = $this->mapper->woo_to_ecwid( 'failed' );
        $this->assertEquals( 'WILL_NOT_DELIVER', $result );
    }

    /**
     * Test WooCommerce to Ecwid: with wc- prefix
     */
    public function test_woo_to_ecwid_with_prefix() {
        $result = $this->mapper->woo_to_ecwid( 'wc-completed' );
        $this->assertEquals( 'SHIPPED', $result );
    }

    /**
     * Test get_ecwid_payment_statuses returns array
     */
    public function test_get_ecwid_payment_statuses() {
        $statuses = Ecwid_Status_Mapper::get_ecwid_payment_statuses();
        
        $this->assertIsArray( $statuses );
        $this->assertArrayHasKey( 'PAID', $statuses );
        $this->assertArrayHasKey( 'AWAITING_PAYMENT', $statuses );
        $this->assertArrayHasKey( 'CANCELLED', $statuses );
        $this->assertArrayHasKey( 'REFUNDED', $statuses );
    }

    /**
     * Test get_ecwid_fulfillment_statuses returns array
     */
    public function test_get_ecwid_fulfillment_statuses() {
        $statuses = Ecwid_Status_Mapper::get_ecwid_fulfillment_statuses();
        
        $this->assertIsArray( $statuses );
        $this->assertArrayHasKey( 'AWAITING_PROCESSING', $statuses );
        $this->assertArrayHasKey( 'PROCESSING', $statuses );
        $this->assertArrayHasKey( 'SHIPPED', $statuses );
        $this->assertArrayHasKey( 'DELIVERED', $statuses );
    }

    /**
     * Test is_paid_status
     */
    public function test_is_paid_status() {
        $this->assertTrue( $this->mapper->is_paid_status( 'PAID' ) );
        $this->assertFalse( $this->mapper->is_paid_status( 'AWAITING_PAYMENT' ) );
        $this->assertFalse( $this->mapper->is_paid_status( 'CANCELLED' ) );
    }

    /**
     * Test is_completed_status
     */
    public function test_is_completed_status() {
        $this->assertTrue( $this->mapper->is_completed_status( 'SHIPPED' ) );
        $this->assertTrue( $this->mapper->is_completed_status( 'DELIVERED' ) );
        $this->assertTrue( $this->mapper->is_completed_status( 'OUT_FOR_DELIVERY' ) );
        $this->assertFalse( $this->mapper->is_completed_status( 'PROCESSING' ) );
        $this->assertFalse( $this->mapper->is_completed_status( 'AWAITING_PROCESSING' ) );
    }

    /**
     * Test get_mappings returns array
     */
    public function test_get_mappings() {
        $mappings = $this->mapper->get_mappings();
        
        $this->assertIsArray( $mappings );
        $this->assertNotEmpty( $mappings );
    }
}
