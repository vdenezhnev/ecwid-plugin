<?php
/**
 * Admin Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin functionality.
 *
 * Registers admin menus, settings pages, and admin scripts/styles.
 *
 * @since 1.0.0
 */
class Admin {

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Constructor.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $version     Plugin version.
     */
    public function __construct( $plugin_slug, $version ) {
        $this->plugin_slug = $plugin_slug;
        $this->version     = $version;

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_ecwid_wc_start_sync', array( $this, 'ajax_start_sync' ) );
        add_action( 'wp_ajax_ecwid_wc_get_sync_status', array( $this, 'ajax_get_sync_status' ) );
        add_action( 'wp_ajax_ecwid_wc_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_ecwid_wc_test_connection', array( $this, 'ajax_test_connection' ) );
    }

    /**
     * Add admin menu items.
     *
     * @return void
     */
    public function add_admin_menu() {
        // Main menu.
        add_menu_page(
            __( 'Ecwid WooCommerce', 'ecwid-woocommerce' ),
            __( 'Ecwid Sync', 'ecwid-woocommerce' ),
            'manage_woocommerce',
            $this->plugin_slug,
            array( $this, 'render_settings_page' ),
            'dashicons-update',
            56
        );

        // Settings submenu.
        add_submenu_page(
            $this->plugin_slug,
            __( 'Settings', 'ecwid-woocommerce' ),
            __( 'Settings', 'ecwid-woocommerce' ),
            'manage_woocommerce',
            $this->plugin_slug,
            array( $this, 'render_settings_page' )
        );

        // Sync Dashboard submenu.
        add_submenu_page(
            $this->plugin_slug,
            __( 'Sync Dashboard', 'ecwid-woocommerce' ),
            __( 'Sync Dashboard', 'ecwid-woocommerce' ),
            'manage_woocommerce',
            $this->plugin_slug . '-dashboard',
            array( $this, 'render_dashboard_page' )
        );

        // Logs submenu.
        add_submenu_page(
            $this->plugin_slug,
            __( 'Logs', 'ecwid-woocommerce' ),
            __( 'Logs', 'ecwid-woocommerce' ),
            'manage_woocommerce',
            $this->plugin_slug . '-logs',
            array( $this, 'render_logs_page' )
        );
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings() {
        // Ecwid API settings section.
        add_settings_section(
            'ecwid_wc_api_settings',
            __( 'Ecwid API Settings', 'ecwid-woocommerce' ),
            array( $this, 'render_api_section' ),
            $this->plugin_slug
        );

        // Store ID.
        register_setting( $this->plugin_slug, 'ecwid_wc_ecwid_store_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        add_settings_field(
            'ecwid_wc_ecwid_store_id',
            __( 'Ecwid Store ID', 'ecwid-woocommerce' ),
            array( $this, 'render_text_field' ),
            $this->plugin_slug,
            'ecwid_wc_api_settings',
            array(
                'id'          => 'ecwid_wc_ecwid_store_id',
                'description' => __( 'Your Ecwid Store ID (numeric).', 'ecwid-woocommerce' ),
            )
        );

        // Access Token.
        register_setting( $this->plugin_slug, 'ecwid_wc_ecwid_access_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        add_settings_field(
            'ecwid_wc_ecwid_access_token',
            __( 'Ecwid Access Token', 'ecwid-woocommerce' ),
            array( $this, 'render_password_field' ),
            $this->plugin_slug,
            'ecwid_wc_api_settings',
            array(
                'id'          => 'ecwid_wc_ecwid_access_token',
                'description' => __( 'Your Ecwid API access token.', 'ecwid-woocommerce' ),
            )
        );

        // Sync settings section.
        add_settings_section(
            'ecwid_wc_sync_settings',
            __( 'Sync Settings', 'ecwid-woocommerce' ),
            array( $this, 'render_sync_section' ),
            $this->plugin_slug
        );

        // Sync Direction.
        register_setting( $this->plugin_slug, 'ecwid_wc_sync_direction', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'ecwid_to_wc',
        ) );

        add_settings_field(
            'ecwid_wc_sync_direction',
            __( 'Sync Direction', 'ecwid-woocommerce' ),
            array( $this, 'render_select_field' ),
            $this->plugin_slug,
            'ecwid_wc_sync_settings',
            array(
                'id'      => 'ecwid_wc_sync_direction',
                'options' => array(
                    'ecwid_to_wc'   => __( 'Ecwid to WooCommerce', 'ecwid-woocommerce' ),
                    'wc_to_ecwid'   => __( 'WooCommerce to Ecwid', 'ecwid-woocommerce' ),
                    'bidirectional' => __( 'Bidirectional', 'ecwid-woocommerce' ),
                ),
            )
        );

        // Sync Products.
        register_setting( $this->plugin_slug, 'ecwid_wc_sync_products', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ) );

        add_settings_field(
            'ecwid_wc_sync_products',
            __( 'Sync Products', 'ecwid-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            $this->plugin_slug,
            'ecwid_wc_sync_settings',
            array(
                'id'          => 'ecwid_wc_sync_products',
                'description' => __( 'Enable product synchronization.', 'ecwid-woocommerce' ),
            )
        );

        // Sync Orders.
        register_setting( $this->plugin_slug, 'ecwid_wc_sync_orders', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ) );

        add_settings_field(
            'ecwid_wc_sync_orders',
            __( 'Sync Orders', 'ecwid-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            $this->plugin_slug,
            'ecwid_wc_sync_settings',
            array(
                'id'          => 'ecwid_wc_sync_orders',
                'description' => __( 'Enable order synchronization.', 'ecwid-woocommerce' ),
            )
        );

        // Sync Customers.
        register_setting( $this->plugin_slug, 'ecwid_wc_sync_customers', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ) );

        add_settings_field(
            'ecwid_wc_sync_customers',
            __( 'Sync Customers', 'ecwid-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            $this->plugin_slug,
            'ecwid_wc_sync_settings',
            array(
                'id'          => 'ecwid_wc_sync_customers',
                'description' => __( 'Enable customer synchronization.', 'ecwid-woocommerce' ),
            )
        );

        // Sync Interval.
        register_setting( $this->plugin_slug, 'ecwid_wc_sync_interval', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'hourly',
        ) );

        add_settings_field(
            'ecwid_wc_sync_interval',
            __( 'Sync Interval', 'ecwid-woocommerce' ),
            array( $this, 'render_select_field' ),
            $this->plugin_slug,
            'ecwid_wc_sync_settings',
            array(
                'id'      => 'ecwid_wc_sync_interval',
                'options' => array(
                    'manual'                  => __( 'Manual', 'ecwid-woocommerce' ),
                    'ecwid_wc_fifteen_minutes' => __( 'Every 15 minutes', 'ecwid-woocommerce' ),
                    'hourly'                  => __( 'Hourly', 'ecwid-woocommerce' ),
                    'daily'                   => __( 'Daily', 'ecwid-woocommerce' ),
                ),
            )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Only load on our plugin pages.
        if ( strpos( $hook, $this->plugin_slug ) === false ) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_slug . '-admin',
            ECWID_WC_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            $this->plugin_slug . '-admin',
            ECWID_WC_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_slug . '-admin',
            'ecwidWcAdmin',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'ecwid_wc_admin' ),
                'strings'       => array(
                    'syncing'     => __( 'Syncing...', 'ecwid-woocommerce' ),
                    'syncComplete' => __( 'Sync completed!', 'ecwid-woocommerce' ),
                    'syncError'   => __( 'Sync failed. Please check logs.', 'ecwid-woocommerce' ),
                    'testing'     => __( 'Testing connection...', 'ecwid-woocommerce' ),
                    'connected'   => __( 'Connection successful!', 'ecwid-woocommerce' ),
                    'connectError' => __( 'Connection failed.', 'ecwid-woocommerce' ),
                    'confirm'     => __( 'Are you sure?', 'ecwid-woocommerce' ),
                ),
            )
        );
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function display_admin_notices() {
        // Welcome notice after activation.
        if ( get_transient( 'ecwid_wc_activated' ) ) {
            delete_transient( 'ecwid_wc_activated' );
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__( 'Ecwid WooCommerce Integration activated successfully!', 'ecwid-woocommerce' ),
                esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug ) ),
                esc_html__( 'Configure settings', 'ecwid-woocommerce' )
            );
        }

        // Check if API credentials are set.
        $current_screen = get_current_screen();
        if ( $current_screen && strpos( $current_screen->id, $this->plugin_slug ) !== false ) {
            $store_id     = get_option( 'ecwid_wc_ecwid_store_id' );
            $access_token = get_option( 'ecwid_wc_ecwid_access_token' );

            if ( empty( $store_id ) || empty( $access_token ) ) {
                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    esc_html__( 'Please configure your Ecwid API credentials to start syncing.', 'ecwid-woocommerce' )
                );
            }
        }
    }

    /**
     * Render API settings section.
     *
     * @return void
     */
    public function render_api_section() {
        echo '<p>' . esc_html__( 'Enter your Ecwid API credentials. You can find these in your Ecwid Control Panel under Apps > My Apps.', 'ecwid-woocommerce' ) . '</p>';
    }

    /**
     * Render sync settings section.
     *
     * @return void
     */
    public function render_sync_section() {
        echo '<p>' . esc_html__( 'Configure how data should be synchronized between Ecwid and WooCommerce.', 'ecwid-woocommerce' ) . '</p>';
    }

    /**
     * Render text input field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_text_field( $args ) {
        $value = get_option( $args['id'], '' );
        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
            esc_attr( $args['id'] ),
            esc_attr( $value )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render password input field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_password_field( $args ) {
        $value = get_option( $args['id'], '' );
        printf(
            '<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
            esc_attr( $args['id'] ),
            esc_attr( $value )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render select field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_select_field( $args ) {
        $value = get_option( $args['id'], '' );
        printf( '<select id="%1$s" name="%1$s">', esc_attr( $args['id'] ) );
        foreach ( $args['options'] as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $value, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render checkbox field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_checkbox_field( $args ) {
        $value = get_option( $args['id'], false );
        printf(
            '<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s />',
            esc_attr( $args['id'] ),
            checked( $value, true, false )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<label for="%s"> %s</label>', esc_attr( $args['id'] ), esc_html( $args['description'] ) );
        }
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        include ECWID_WC_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        include ECWID_WC_PLUGIN_DIR . 'admin/views/sync-dashboard.php';
    }

    /**
     * Render logs page.
     *
     * @return void
     */
    public function render_logs_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        include ECWID_WC_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * AJAX handler for starting sync.
     *
     * @return void
     */
    public function ajax_start_sync() {
        check_ajax_referer( 'ecwid_wc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce' ) ) );
        }

        // TODO: Implement actual sync logic.
        wp_send_json_success( array( 'message' => __( 'Sync started.', 'ecwid-woocommerce' ) ) );
    }

    /**
     * AJAX handler for getting sync status.
     *
     * @return void
     */
    public function ajax_get_sync_status() {
        check_ajax_referer( 'ecwid_wc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce' ) ) );
        }

        // TODO: Implement actual status retrieval.
        $status = array(
            'products'  => array( 'synced' => 0, 'pending' => 0, 'failed' => 0 ),
            'orders'    => array( 'synced' => 0, 'pending' => 0, 'failed' => 0 ),
            'customers' => array( 'synced' => 0, 'pending' => 0, 'failed' => 0 ),
            'last_sync' => get_option( 'ecwid_wc_last_sync', __( 'Never', 'ecwid-woocommerce' ) ),
        );

        wp_send_json_success( $status );
    }

    /**
     * AJAX handler for clearing logs.
     *
     * @return void
     */
    public function ajax_clear_logs() {
        check_ajax_referer( 'ecwid_wc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce' ) ) );
        }

        $logger = Utils\Logger::get_instance();
        $logger->clear_all();

        wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'ecwid-woocommerce' ) ) );
    }

    /**
     * AJAX handler for testing API connection.
     *
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'ecwid_wc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce' ) ) );
        }

        // TODO: Implement actual connection test.
        $store_id     = get_option( 'ecwid_wc_ecwid_store_id' );
        $access_token = get_option( 'ecwid_wc_ecwid_access_token' );

        if ( empty( $store_id ) || empty( $access_token ) ) {
            wp_send_json_error( array( 'message' => __( 'API credentials are not configured.', 'ecwid-woocommerce' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Connection successful!', 'ecwid-woocommerce' ) ) );
    }
}
