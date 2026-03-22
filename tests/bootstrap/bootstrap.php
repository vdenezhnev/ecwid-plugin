<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package Ecwid_WooCommerce_Sync
 */

// Define test constants
define( 'ECWID_SYNC_TESTING', true );
define( 'ABSPATH', dirname( __FILE__ ) . '/../../' );
define( 'ECWID_SYNC_PLUGIN_DIR', dirname( __FILE__ ) . '/../../' );
define( 'ECWID_SYNC_PLUGIN_URL', 'http://example.com/wp-content/plugins/ecwid-woocommerce-sync/' );
define( 'ECWID_SYNC_PLUGIN_BASENAME', 'ecwid-woocommerce-sync/ecwid-woocommerce-sync.php' );
define( 'ECWID_SYNC_VERSION', '1.0.0' );

// Load WordPress mock functions
require_once dirname( __FILE__ ) . '/wp-mock-functions.php';

// Load WooCommerce mock classes
require_once dirname( __FILE__ ) . '/wc-mock-classes.php';

// Load plugin classes
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-logger.php';
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-api-client.php';
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-customer-mapper.php';
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-payment-mapper.php';
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-status-mapper.php';
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-order-sync.php';
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-webhook-handler.php';
require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-cron-handler.php';
