<?php
/**
 * Unit tests for Ecwid_Payment_Mapper
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class PaymentMapperTest extends TestCase {

    /**
     * Payment mapper instance
     *
     * @var Ecwid_Payment_Mapper
     */
    private $mapper;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        clear_mock_options();
        $this->mapper = new Ecwid_Payment_Mapper();
    }

    /**
     * Tear down test fixtures
     */
    protected function tearDown(): void {
        clear_mock_options();
        parent::tearDown();
    }

    /**
     * Test PayPal payment mapping
     */
    public function test_paypal_mapping() {
        $order = array( 'paymentMethod' => 'PayPal' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'paypal', $result['id'] );
        $this->assertEquals( 'PayPal', $result['title'] );
    }

    /**
     * Test Stripe payment mapping
     */
    public function test_stripe_mapping() {
        $order = array( 'paymentMethod' => 'Stripe' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'stripe', $result['id'] );
    }

    /**
     * Test Cash on Delivery mapping
     */
    public function test_cod_mapping() {
        $order = array( 'paymentMethod' => 'CashOnDelivery' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'cod', $result['id'] );
    }

    /**
     * Test Cash payment mapping
     */
    public function test_cash_mapping() {
        $order = array( 'paymentMethod' => 'Cash' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'cod', $result['id'] );
    }

    /**
     * Test Bank Transfer mapping
     */
    public function test_bank_transfer_mapping() {
        $order = array( 'paymentMethod' => 'BankTransfer' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'bacs', $result['id'] );
    }

    /**
     * Test Wire Transfer mapping
     */
    public function test_wire_transfer_mapping() {
        $order = array( 'paymentMethod' => 'WireTransfer' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'bacs', $result['id'] );
    }

    /**
     * Test Check payment mapping
     */
    public function test_check_mapping() {
        $order = array( 'paymentMethod' => 'Check' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'cheque', $result['id'] );
    }

    /**
     * Test Square payment mapping
     */
    public function test_square_mapping() {
        $order = array( 'paymentMethod' => 'Square' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'square', $result['id'] );
    }

    /**
     * Test unknown payment method defaults to other
     */
    public function test_unknown_payment_defaults_to_other() {
        $order = array( 'paymentMethod' => 'UnknownMethod123' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'other', $result['id'] );
        $this->assertEquals( 'UnknownMethod123', $result['title'] );
    }

    /**
     * Test payment module takes priority over method
     */
    public function test_payment_module_priority() {
        $order = array(
            'paymentMethod' => 'CustomMethod',
            'paymentModule' => 'Stripe',
        );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'stripe', $result['id'] );
    }

    /**
     * Test get_payment_status for PAID
     */
    public function test_get_payment_status_paid() {
        $order = array( 'paymentStatus' => 'PAID' );
        $result = $this->mapper->get_payment_status( $order );
        
        $this->assertTrue( $result['paid'] );
        $this->assertEquals( 'processing', $result['status'] );
    }

    /**
     * Test get_payment_status for AWAITING_PAYMENT
     */
    public function test_get_payment_status_awaiting() {
        $order = array( 'paymentStatus' => 'AWAITING_PAYMENT' );
        $result = $this->mapper->get_payment_status( $order );
        
        $this->assertFalse( $result['paid'] );
        $this->assertEquals( 'pending', $result['status'] );
    }

    /**
     * Test get_payment_status for CANCELLED
     */
    public function test_get_payment_status_cancelled() {
        $order = array( 'paymentStatus' => 'CANCELLED' );
        $result = $this->mapper->get_payment_status( $order );
        
        $this->assertFalse( $result['paid'] );
        $this->assertEquals( 'cancelled', $result['status'] );
    }

    /**
     * Test get_payment_status for REFUNDED
     */
    public function test_get_payment_status_refunded() {
        $order = array( 'paymentStatus' => 'REFUNDED' );
        $result = $this->mapper->get_payment_status( $order );
        
        $this->assertFalse( $result['paid'] );
        $this->assertEquals( 'refunded', $result['status'] );
    }

    /**
     * Test get_transaction_details
     */
    public function test_get_transaction_details() {
        $order = array(
            'externalTransactionId' => 'txn_123456',
            'paymentMethod'         => 'Stripe',
            'paymentModule'         => 'stripe',
            'paymentStatus'         => 'PAID',
            'paymentMessage'        => 'Payment successful',
        );
        
        $result = $this->mapper->get_transaction_details( $order );
        
        $this->assertEquals( 'txn_123456', $result['transaction_id'] );
        $this->assertEquals( 'Stripe', $result['payment_method'] );
        $this->assertEquals( 'stripe', $result['payment_module'] );
        $this->assertEquals( 'PAID', $result['payment_status'] );
    }

    /**
     * Test register_mapping
     */
    public function test_register_mapping() {
        $this->mapper->register_mapping( 'MyCustomPayment', 'custom_gateway', 'My Custom Gateway' );
        
        $order = array( 'paymentMethod' => 'MyCustomPayment' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'custom_gateway', $result['id'] );
        $this->assertEquals( 'My Custom Gateway', $result['title'] );
    }

    /**
     * Test get_all_mappings
     */
    public function test_get_all_mappings() {
        $mappings = $this->mapper->get_all_mappings();
        
        $this->assertIsArray( $mappings );
        $this->assertArrayHasKey( 'PayPal', $mappings );
        $this->assertArrayHasKey( 'Stripe', $mappings );
        $this->assertArrayHasKey( 'Cash', $mappings );
    }

    /**
     * Test partial match for payment method
     */
    public function test_partial_match() {
        $order = array( 'paymentMethod' => 'PayPal Express Checkout' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'paypal', $result['id'] );
    }

    /**
     * Test AfterPay mapping
     */
    public function test_afterpay_mapping() {
        $order = array( 'paymentMethod' => 'AfterPay' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'afterpay', $result['id'] );
    }

    /**
     * Test Klarna mapping
     */
    public function test_klarna_mapping() {
        $order = array( 'paymentMethod' => 'Klarna' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'klarna', $result['id'] );
    }

    /**
     * Test Google Pay mapping
     */
    public function test_google_pay_mapping() {
        $order = array( 'paymentMethod' => 'GooglePay' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'gpay', $result['id'] );
    }

    /**
     * Test Apple Pay mapping
     */
    public function test_apple_pay_mapping() {
        $order = array( 'paymentMethod' => 'ApplePay' );
        $result = $this->mapper->map_payment_method( $order );
        
        $this->assertEquals( 'applepay', $result['id'] );
    }
}
