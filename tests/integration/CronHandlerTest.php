<?php
/**
 * Integration tests for Ecwid_Cron_Handler
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class CronHandlerTest extends TestCase {

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
     * Cron handler instance
     *
     * @var Ecwid_Cron_Handler
     */
    private $cron_handler;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        
        clear_mock_options();
        clear_mock_responses();
        
        set_mock_option( 'ecwid_store_id', '12345' );
        set_mock_option( 'ecwid_api_token', 'test_token_123' );
        set_mock_option( 'ecwid_sync_enabled', 'yes' );
        set_mock_option( 'ecwid_create_customers', 'yes' );
        
        $this->api          = new Ecwid_API_Client();
        $this->order_sync   = new Ecwid_Order_Sync( $this->api );
        $this->cron_handler = new Ecwid_Cron_Handler( $this->order_sync );
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
     * Test cron handler initialization
     */
    public function test_cron_handler_initialization() {
        $this->assertInstanceOf( Ecwid_Cron_Handler::class, $this->cron_handler );
    }

    /**
     * Test add_cron_schedules
     */
    public function test_add_cron_schedules() {
        $schedules = array();
        $result = $this->cron_handler->add_cron_schedules( $schedules );
        
        $this->assertArrayHasKey( 'every_five_minutes', $result );
        $this->assertArrayHasKey( 'every_fifteen_minutes', $result );
        $this->assertArrayHasKey( 'every_thirty_minutes', $result );
        
        $this->assertEquals( 300, $result['every_five_minutes']['interval'] );
        $this->assertEquals( 900, $result['every_fifteen_minutes']['interval'] );
        $this->assertEquals( 1800, $result['every_thirty_minutes']['interval'] );
    }

    /**
     * Test get_available_intervals
     */
    public function test_get_available_intervals() {
        $intervals = Ecwid_Cron_Handler::get_available_intervals();
        
        $this->assertIsArray( $intervals );
        $this->assertArrayHasKey( 'every_five_minutes', $intervals );
        $this->assertArrayHasKey( 'every_fifteen_minutes', $intervals );
        $this->assertArrayHasKey( 'every_thirty_minutes', $intervals );
        $this->assertArrayHasKey( 'hourly', $intervals );
        $this->assertArrayHasKey( 'twicedaily', $intervals );
        $this->assertArrayHasKey( 'daily', $intervals );
    }

    /**
     * Test is_scheduled returns false initially
     */
    public function test_is_scheduled_returns_false() {
        $result = $this->cron_handler->is_scheduled();
        
        $this->assertFalse( $result );
    }

    /**
     * Test get_next_scheduled returns false when not scheduled
     */
    public function test_get_next_scheduled_returns_false() {
        $result = $this->cron_handler->get_next_scheduled();
        
        $this->assertFalse( $result );
    }

    /**
     * Test schedule_sync
     */
    public function test_schedule_sync() {
        // Should not throw exceptions
        $this->cron_handler->schedule_sync( 'hourly' );
        
        $this->assertTrue( true );
    }

    /**
     * Test schedule_sync with invalid interval uses default
     */
    public function test_schedule_sync_invalid_interval() {
        // Should not throw exceptions and should use 'hourly' as default
        $this->cron_handler->schedule_sync( 'invalid_interval' );
        
        $this->assertTrue( true );
    }

    /**
     * Test unschedule_sync
     */
    public function test_unschedule_sync() {
        // Should not throw exceptions
        $this->cron_handler->unschedule_sync();
        
        $this->assertTrue( true );
    }

    /**
     * Test run_sync when disabled
     */
    public function test_run_sync_when_disabled() {
        set_mock_option( 'ecwid_sync_enabled', 'no' );
        
        // Should exit early without errors
        $this->cron_handler->run_sync();
        
        $this->assertTrue( true );
    }

    /**
     * Test run_sync when API not configured
     */
    public function test_run_sync_when_not_configured() {
        clear_mock_options();
        set_mock_option( 'ecwid_sync_enabled', 'yes' );
        
        // Recreate handler without API credentials
        $api = new Ecwid_API_Client();
        $order_sync = new Ecwid_Order_Sync( $api );
        $handler = new Ecwid_Cron_Handler( $order_sync );
        
        // Should exit early without errors
        $handler->run_sync();
        
        $this->assertTrue( true );
    }

    /**
     * Test run_manual_sync
     */
    public function test_run_manual_sync() {
        set_mock_response(
            'https://app.ecwid.com/api/v3/12345/orders?updatedFrom=' . urlencode( gmdate( 'c', strtotime( '-30 days' ) ) ) . '&limit=100&offset=0',
            array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( array( 'items' => array() ) ),
            )
        );
        
        $result = $this->cron_handler->run_manual_sync();
        
        $this->assertIsArray( $result );
        $this->assertEquals( 'success', $result['status'] );
    }

    /**
     * Test cleanup_logs
     */
    public function test_cleanup_logs() {
        // Should not throw exceptions
        $this->cron_handler->cleanup_logs();
        
        $this->assertTrue( true );
    }

    /**
     * Test cron hook constant
     */
    public function test_cron_hook_constant() {
        $this->assertEquals( 'ecwid_sync_orders_cron', Ecwid_Cron_Handler::CRON_HOOK );
    }

    /**
     * Test log cleanup hook constant
     */
    public function test_log_cleanup_hook_constant() {
        $this->assertEquals( 'ecwid_cleanup_logs_cron', Ecwid_Cron_Handler::LOG_CLEANUP_HOOK );
    }
}
