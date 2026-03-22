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

        // Load API classes.
        require_once ECWID_WC_PLUGIN_DIR . 'includes/api/class-ecwid-api.php';

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
