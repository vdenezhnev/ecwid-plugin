<?php
/**
 * Main Plugin Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * Handles plugin initialization, loading of components,
 * and orchestration of the plugin functionality.
 *
 * @since 1.0.0
 */
class Plugin {

    /**
     * Single instance of the class.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $plugin_slug = 'ecwid-woocommerce';

    /**
     * Admin handler instance.
     *
     * @var Admin|null
     */
    private $admin = null;

    /**
     * Sync hooks handler.
     *
     * @var Sync\Sync_Hooks|null
     */
    private $sync_hooks = null;

    /**
     * Get single instance of the class.
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * Private to enforce singleton pattern.
     */
    private function __construct() {
        $this->version = ECWID_WC_VERSION;
        $this->load_dependencies();
    }

    /**
     * Load required dependencies.
     *
     * @return void
     */
    private function load_dependencies() {
        // Load utility classes.
        require_once ECWID_WC_PLUGIN_DIR . 'includes/utils/class-logger.php';
        require_once ECWID_WC_PLUGIN_DIR . 'includes/utils/class-encryption.php';
        require_once ECWID_WC_PLUGIN_DIR . 'includes/utils/class-mapping-repository.php';

        // Load API classes.
        require_once ECWID_WC_PLUGIN_DIR . 'includes/api/class-ecwid-api.php';

        // Load mapper classes.
        require_once ECWID_WC_PLUGIN_DIR . 'includes/mappers/class-product-mapper.php';

        // Load sync classes.
        require_once ECWID_WC_PLUGIN_DIR . 'includes/sync/class-product-sync.php';
        require_once ECWID_WC_PLUGIN_DIR . 'includes/sync/class-sync-hooks.php';

        // Load admin classes if in admin context.
        if ( is_admin() ) {
            require_once ECWID_WC_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }

    /**
     * Run the plugin.
     *
     * @return void
     */
    public function run() {
        $this->register_hooks();
        $this->init_components();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Plugin action links.
        add_filter( 'plugin_action_links_' . ECWID_WC_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );

        // Declare WooCommerce HPOS compatibility.
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

        // Register custom cron schedules.
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
    }

    /**
     * Initialize plugin components.
     *
     * @return void
     */
    private function init_components() {
        // Initialize admin.
        if ( is_admin() ) {
            $this->admin = new Admin( $this->plugin_slug, $this->version );
        }

        // Initialize sync hooks.
        $this->sync_hooks = new Sync\Sync_Hooks();
        $this->sync_hooks->init();

        // Register cron handlers.
        add_action( 'ecwid_wc_process_queue', array( $this, 'process_sync_queue' ) );
        add_action( 'ecwid_wc_scheduled_sync', array( $this, 'run_scheduled_sync' ) );
        add_action( 'ecwid_wc_cleanup_logs', array( $this, 'cleanup_logs' ) );
    }

    /**
     * Process sync queue (cron handler).
     *
     * @return void
     */
    public function process_sync_queue() {
        if ( $this->sync_hooks ) {
            $processed = $this->sync_hooks->process_queue();
            Utils\Logger::get_instance()->debug(
                sprintf( 'Processed %d queue items', $processed ),
                array(),
                'Plugin'
            );
        }
    }

    /**
     * Run scheduled sync (cron handler).
     *
     * @return void
     */
    public function run_scheduled_sync() {
        $sync_products = get_option( 'ecwid_wc_sync_products', true );
        $sync_direction = get_option( 'ecwid_wc_sync_direction', 'ecwid_to_wc' );

        if ( ! $sync_products ) {
            return;
        }

        if ( in_array( $sync_direction, array( 'wc_to_ecwid', 'bidirectional' ), true ) ) {
            $product_sync = new Sync\Product_Sync();
            $result = $product_sync->export_all( array( 'limit' => 100 ) );

            Utils\Logger::get_instance()->info(
                sprintf(
                    'Scheduled sync completed: %d created, %d updated, %d errors',
                    $result['results']['created'],
                    $result['results']['updated'],
                    $result['results']['errors']
                ),
                array(),
                'Plugin'
            );
        }
    }

    /**
     * Cleanup old logs (cron handler).
     *
     * @return void
     */
    public function cleanup_logs() {
        $logger = Utils\Logger::get_instance();
        $deleted = $logger->cleanup();
        $logger->debug( sprintf( 'Cleaned up %d old log entries', $deleted ), array(), 'Plugin' );
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_action_links( $links ) {
        $plugin_links = array(
            sprintf(
                '<a href="%s">%s</a>',
                esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug ) ),
                esc_html__( 'Settings', 'ecwid-woocommerce' )
            ),
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
     *
     * @return void
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                ECWID_WC_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Modified cron schedules.
     */
    public function add_cron_schedules( $schedules ) {
        $schedules['ecwid_wc_five_minutes'] = array(
            'interval' => 300,
            'display'  => esc_html__( 'Every 5 Minutes', 'ecwid-woocommerce' ),
        );

        $schedules['ecwid_wc_fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => esc_html__( 'Every 15 Minutes', 'ecwid-woocommerce' ),
        );

        return $schedules;
    }

    /**
     * Get plugin version.
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get plugin slug.
     *
     * @return string
     */
    public function get_plugin_slug() {
        return $this->plugin_slug;
    }

    /**
     * Get admin handler instance.
     *
     * @return Admin|null
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Prevent cloning.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     *
     * @return void
     * @throws \Exception When trying to unserialize.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}
