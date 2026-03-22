<?php
/**
 * Plugin Name: Ecwid WooCommerce Sync
 * Plugin URI: https://github.com/vdenezhnev/ecwid-plugin
 * Description: Синхронизация заказов Ecwid с WooCommerce - импорт заказов, обновление статусов, маппинг клиентов и оплат
 * Version: 1.0.0
 * Author: Ecwid Plugin Team
 * Author URI: https://github.com/vdenezhnev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ecwid-woocommerce-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'ECWID_SYNC_VERSION', '1.0.0' );
define( 'ECWID_SYNC_PLUGIN_FILE', __FILE__ );
define( 'ECWID_SYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECWID_SYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ECWID_SYNC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class Ecwid_WooCommerce_Sync {

    /**
     * Single instance of the class
     *
     * @var Ecwid_WooCommerce_Sync
     */
    private static $instance = null;

    /**
     * API client instance
     *
     * @var Ecwid_API_Client
     */
    public $api;

    /**
     * Order sync instance
     *
     * @var Ecwid_Order_Sync
     */
    public $order_sync;

    /**
     * Webhook handler instance
     *
     * @var Ecwid_Webhook_Handler
     */
    public $webhook_handler;

    /**
     * Cron handler instance
     *
     * @var Ecwid_Cron_Handler
     */
    public $cron_handler;

    /**
     * Get single instance of the class
     *
     * @return Ecwid_WooCommerce_Sync
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-api-client.php';
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-order-sync.php';
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-webhook-handler.php';
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-cron-handler.php';
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-customer-mapper.php';
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-payment-mapper.php';
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-status-mapper.php';
        require_once ECWID_SYNC_PLUGIN_DIR . 'includes/class-ecwid-logger.php';

        // Admin classes
        if ( is_admin() ) {
            require_once ECWID_SYNC_PLUGIN_DIR . 'admin/class-ecwid-admin.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook( ECWID_SYNC_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( ECWID_SYNC_PLUGIN_FILE, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Initialize components
        $this->api             = new Ecwid_API_Client();
        $this->order_sync      = new Ecwid_Order_Sync( $this->api );
        $this->webhook_handler = new Ecwid_Webhook_Handler( $this->order_sync );
        $this->cron_handler    = new Ecwid_Cron_Handler( $this->order_sync );

        // Initialize admin
        if ( is_admin() ) {
            new Ecwid_Admin();
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ecwid-woocommerce-sync',
            false,
            dirname( ECWID_SYNC_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Schedule cron events
        if ( ! wp_next_scheduled( 'ecwid_sync_orders_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'ecwid_sync_orders_cron' );
        }

        // Set default options
        $default_options = array(
            'ecwid_store_id'         => '',
            'ecwid_api_token'        => '',
            'ecwid_webhook_secret'   => wp_generate_password( 32, false ),
            'ecwid_sync_interval'    => 'hourly',
            'ecwid_sync_enabled'     => 'yes',
            'ecwid_webhook_enabled'  => 'yes',
            'ecwid_last_sync'        => '',
            'ecwid_sync_from_date'   => '',
        );

        foreach ( $default_options as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }

        // Flush rewrite rules for webhook endpoint
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'ecwid_sync_orders_cron' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for tracking synced orders
        $table_name = $wpdb->prefix . 'ecwid_synced_orders';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ecwid_order_id bigint(20) UNSIGNED NOT NULL,
            woo_order_id bigint(20) UNSIGNED NOT NULL,
            ecwid_customer_id bigint(20) UNSIGNED DEFAULT NULL,
            woo_customer_id bigint(20) UNSIGNED DEFAULT NULL,
            last_synced datetime DEFAULT NULL,
            sync_status varchar(50) DEFAULT 'synced',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ecwid_order_id (ecwid_order_id),
            KEY woo_order_id (woo_order_id),
            KEY sync_status (sync_status)
        ) $charset_collate;";

        // Table for sync logs
        $log_table = $wpdb->prefix . 'ecwid_sync_logs';

        $sql .= "CREATE TABLE IF NOT EXISTS $log_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            log_type varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_type (log_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin link */
                    esc_html__( 'Ecwid WooCommerce Sync requires %s to be installed and active.', 'ecwid-woocommerce-sync' ),
                    '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Returns the main instance of Ecwid_WooCommerce_Sync
 *
 * @return Ecwid_WooCommerce_Sync
 */
function ecwid_woo_sync() {
    return Ecwid_WooCommerce_Sync::instance();
}

// Initialize plugin
ecwid_woo_sync();
