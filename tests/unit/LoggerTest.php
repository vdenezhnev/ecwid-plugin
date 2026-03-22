<?php
/**
 * Unit tests for Ecwid_Logger
 *
 * @package Ecwid_WooCommerce_Sync
 */

use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase {

    /**
     * Logger instance
     *
     * @var Ecwid_Logger
     */
    private $logger;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->logger = new Ecwid_Logger();
    }

    /**
     * Test logger initialization
     */
    public function test_logger_initialization() {
        $this->assertInstanceOf( Ecwid_Logger::class, $this->logger );
    }

    /**
     * Test log method accepts all levels
     */
    public function test_log_accepts_all_levels() {
        // These should not throw exceptions
        $this->logger->log( 'debug', 'Debug message' );
        $this->logger->log( 'info', 'Info message' );
        $this->logger->log( 'warning', 'Warning message' );
        $this->logger->log( 'error', 'Error message' );
        
        $this->assertTrue( true );
    }

    /**
     * Test debug method
     */
    public function test_debug_method() {
        $this->logger->debug( 'Debug test', array( 'key' => 'value' ) );
        $this->assertTrue( true );
    }

    /**
     * Test info method
     */
    public function test_info_method() {
        $this->logger->info( 'Info test', array( 'data' => 123 ) );
        $this->assertTrue( true );
    }

    /**
     * Test warning method
     */
    public function test_warning_method() {
        $this->logger->warning( 'Warning test' );
        $this->assertTrue( true );
    }

    /**
     * Test error method
     */
    public function test_error_method() {
        $this->logger->error( 'Error test', array( 'error_code' => 500 ) );
        $this->assertTrue( true );
    }

    /**
     * Test log with context
     */
    public function test_log_with_context() {
        $context = array(
            'order_id'   => 123,
            'status'     => 'processing',
            'customer'   => 'test@example.com',
        );
        
        $this->logger->info( 'Order processed', $context );
        $this->assertTrue( true );
    }

    /**
     * Test get_logs returns array
     */
    public function test_get_logs_returns_array() {
        $logs = $this->logger->get_logs();
        
        $this->assertIsArray( $logs );
    }

    /**
     * Test get_logs with filters
     */
    public function test_get_logs_with_filters() {
        $logs = $this->logger->get_logs( array(
            'log_type' => 'error',
            'limit'    => 10,
            'offset'   => 0,
        ) );
        
        $this->assertIsArray( $logs );
    }

    /**
     * Test clear_old_logs
     */
    public function test_clear_old_logs() {
        $this->logger->clear_old_logs( 30 );
        $this->assertTrue( true );
    }

    /**
     * Test clear_all_logs
     */
    public function test_clear_all_logs() {
        $this->logger->clear_all_logs();
        $this->assertTrue( true );
    }

    /**
     * Test log constants exist
     */
    public function test_log_constants_exist() {
        $this->assertEquals( 'debug', Ecwid_Logger::LEVEL_DEBUG );
        $this->assertEquals( 'info', Ecwid_Logger::LEVEL_INFO );
        $this->assertEquals( 'warning', Ecwid_Logger::LEVEL_WARNING );
        $this->assertEquals( 'error', Ecwid_Logger::LEVEL_ERROR );
    }
}
