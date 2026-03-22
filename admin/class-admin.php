<?php
/**
 * Admin Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce;

use Ecwid_WooCommerce\Utils\Encryption;
use Ecwid_WooCommerce\Utils\Logger;
use Ecwid_WooCommerce\Api\Ecwid_Api;

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
     * Encryption instance.
     *
     * @var Encryption
     */
    private $encryption;

    /**
     * Constructor.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $version     Plugin version.
     */
    public function __construct( $plugin_slug, $version ) {
        $this->plugin_slug = $plugin_slug;
        $this->version     = $version;
        $this->encryption  = Encryption::get_instance();

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
        add_action( 'wp_ajax_ecwid_wc_save_credentials', array( $this, 'ajax_save_credentials' ) );
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

        // Store ID - standard field (not encrypted).
        register_setting( $this->plugin_slug, 'ecwid_wc_ecwid_store_id', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_store_id' ),
        ) );

        add_settings_field(
            'ecwid_wc_ecwid_store_id',
            __( 'Ecwid Store ID', 'ecwid-woocommerce' ),
            array( $this, 'render_store_id_field' ),
            $this->plugin_slug,
            'ecwid_wc_api_settings'
        );

        // Access Token - encrypted field.
        register_setting( $this->plugin_slug, 'ecwid_wc_ecwid_access_token', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_access_token' ),
        ) );

        add_settings_field(
            'ecwid_wc_ecwid_access_token',
            __( 'Ecwid Access Token', 'ecwid-woocommerce' ),
            array( $this, 'render_access_token_field' ),
            $this->plugin_slug,
            'ecwid_wc_api_settings'
        );

        // Connection status field (display only).
        add_settings_field(
            'ecwid_wc_connection_status',
            __( 'Connection Status', 'ecwid-woocommerce' ),
            array( $this, 'render_connection_status_field' ),
            $this->plugin_slug,
            'ecwid_wc_api_settings'
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
                    'manual'                   => __( 'Manual', 'ecwid-woocommerce' ),
                    'ecwid_wc_fifteen_minutes' => __( 'Every 15 minutes', 'ecwid-woocommerce' ),
                    'hourly'                   => __( 'Hourly', 'ecwid-woocommerce' ),
                    'daily'                    => __( 'Daily', 'ecwid-woocommerce' ),
                ),
            )
        );
    }

    /**
     * Sanitize Store ID.
     *
     * @param string $value Store ID value.
     * @return string
     */
    public function sanitize_store_id( $value ) {
        $value = sanitize_text_field( $value );

        // Validate format.
        if ( ! empty( $value ) && ! Ecwid_Api::validate_store_id( $value ) ) {
            add_settings_error(
                'ecwid_wc_ecwid_store_id',
                'invalid_store_id',
                __( 'Store ID must be a numeric value.', 'ecwid-woocommerce' ),
                'error'
            );
            return get_option( 'ecwid_wc_ecwid_store_id', '' );
        }

        return $value;
    }

    /**
     * Sanitize and encrypt Access Token.
     *
     * @param string $value Access token value.
     * @return string
     */
    public function sanitize_access_token( $value ) {
        $value = sanitize_text_field( $value );

        // If empty, keep existing value.
        if ( empty( $value ) ) {
            return get_option( 'ecwid_wc_ecwid_access_token', '' );
        }

        // If the value looks like it's already encrypted, return as-is.
        if ( $this->encryption->is_encrypted( $value ) ) {
            return $value;
        }

        // Validate format.
        if ( ! Ecwid_Api::validate_access_token( $value ) ) {
            add_settings_error(
                'ecwid_wc_ecwid_access_token',
                'invalid_access_token',
                __( 'Access token appears to be invalid.', 'ecwid-woocommerce' ),
                'error'
            );
            return get_option( 'ecwid_wc_ecwid_access_token', '' );
        }

        // Encrypt the token.
        $encrypted = $this->encryption->encrypt( $value );

        if ( false === $encrypted ) {
            add_settings_error(
                'ecwid_wc_ecwid_access_token',
                'encryption_failed',
                __( 'Failed to encrypt access token.', 'ecwid-woocommerce' ),
                'error'
            );
            return get_option( 'ecwid_wc_ecwid_access_token', '' );
        }

        // Clear connection status cache when credentials change.
        delete_transient( 'ecwid_wc_connection_status' );

        return $encrypted;
    }

    /**
     * Render Store ID field.
     *
     * @return void
     */
    public function render_store_id_field() {
        $value = get_option( 'ecwid_wc_ecwid_store_id', '' );
        ?>
        <input type="text" 
               id="ecwid_wc_ecwid_store_id" 
               name="ecwid_wc_ecwid_store_id" 
               value="<?php echo esc_attr( $value ); ?>" 
               class="regular-text"
               placeholder="<?php esc_attr_e( 'e.g., 12345678', 'ecwid-woocommerce' ); ?>"
               pattern="[0-9]+"
               title="<?php esc_attr_e( 'Store ID must be numeric', 'ecwid-woocommerce' ); ?>" />
        <p class="description">
            <?php esc_html_e( 'Your Ecwid Store ID (numeric). Find it in Ecwid Control Panel > Settings > Store Profile.', 'ecwid-woocommerce' ); ?>
        </p>
        <?php
    }

    /**
     * Render Access Token field.
     *
     * @return void
     */
    public function render_access_token_field() {
        $encrypted_value = get_option( 'ecwid_wc_ecwid_access_token', '' );
        $has_token       = ! empty( $encrypted_value );
        $masked_value    = '';

        if ( $has_token ) {
            $decrypted    = $this->encryption->decrypt( $encrypted_value );
            $masked_value = $this->encryption->mask( $decrypted, 4 );
        }
        ?>
        <div class="ecwid-wc-token-field">
            <input type="password" 
                   id="ecwid_wc_ecwid_access_token" 
                   name="ecwid_wc_ecwid_access_token" 
                   value="" 
                   class="regular-text"
                   placeholder="<?php echo $has_token ? esc_attr( $masked_value ) : esc_attr__( 'Enter your access token', 'ecwid-woocommerce' ); ?>"
                   autocomplete="new-password" />
            <button type="button" class="button ecwid-wc-toggle-password" title="<?php esc_attr_e( 'Show/Hide', 'ecwid-woocommerce' ); ?>">
                <span class="dashicons dashicons-visibility"></span>
            </button>
        </div>
        <?php if ( $has_token ) : ?>
            <p class="description ecwid-wc-token-status">
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php esc_html_e( 'Token is stored (encrypted). Leave blank to keep current token.', 'ecwid-woocommerce' ); ?>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e( 'Your Ecwid API access token. Get it from Ecwid Control Panel > Apps > My Apps.', 'ecwid-woocommerce' ); ?>
            </p>
        <?php endif; ?>
        <?php if ( ! $this->encryption->is_encryption_available() ) : ?>
            <p class="description" style="color: #d63638;">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e( 'Warning: OpenSSL extension is not available. Tokens will be stored with basic encoding only.', 'ecwid-woocommerce' ); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render Connection Status field.
     *
     * @return void
     */
    public function render_connection_status_field() {
        $store_id  = get_option( 'ecwid_wc_ecwid_store_id', '' );
        $has_token = ! empty( get_option( 'ecwid_wc_ecwid_access_token', '' ) );

        if ( empty( $store_id ) || ! $has_token ) {
            ?>
            <span class="ecwid-wc-status ecwid-wc-status-not-configured">
                <span class="dashicons dashicons-minus"></span>
                <?php esc_html_e( 'Not configured', 'ecwid-woocommerce' ); ?>
            </span>
            <?php
            return;
        }

        // Check cached status.
        $cached_status = get_transient( 'ecwid_wc_connection_status' );
        ?>
        <div id="ecwid-wc-connection-status-container">
            <?php if ( false !== $cached_status ) : ?>
                <?php if ( $cached_status['success'] ) : ?>
                    <span class="ecwid-wc-status ecwid-wc-status-connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html( $cached_status['message'] ); ?>
                    </span>
                <?php else : ?>
                    <span class="ecwid-wc-status ecwid-wc-status-error">
                        <span class="dashicons dashicons-warning"></span>
                        <?php echo esc_html( $cached_status['message'] ); ?>
                    </span>
                <?php endif; ?>
            <?php else : ?>
                <span class="ecwid-wc-status ecwid-wc-status-unknown">
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php esc_html_e( 'Unknown', 'ecwid-woocommerce' ); ?>
                </span>
            <?php endif; ?>
            <button type="button" id="ecwid-wc-test-connection" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Test Connection', 'ecwid-woocommerce' ); ?>
            </button>
        </div>
        <?php
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
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ecwid_wc_admin' ),
                'strings' => array(
                    'syncing'       => __( 'Syncing...', 'ecwid-woocommerce' ),
                    'syncComplete'  => __( 'Sync completed!', 'ecwid-woocommerce' ),
                    'syncError'     => __( 'Sync failed. Please check logs.', 'ecwid-woocommerce' ),
                    'testing'       => __( 'Testing...', 'ecwid-woocommerce' ),
                    'connected'     => __( 'Connection successful!', 'ecwid-woocommerce' ),
                    'connectError'  => __( 'Connection failed.', 'ecwid-woocommerce' ),
                    'confirm'       => __( 'Are you sure?', 'ecwid-woocommerce' ),
                    'saving'        => __( 'Saving...', 'ecwid-woocommerce' ),
                    'saved'         => __( 'Saved!', 'ecwid-woocommerce' ),
                    'saveError'     => __( 'Failed to save.', 'ecwid-woocommerce' ),
                    'validating'    => __( 'Validating credentials...', 'ecwid-woocommerce' ),
                    'invalidStoreId' => __( 'Store ID must be numeric.', 'ecwid-woocommerce' ),
                    'invalidToken'  => __( 'Access token is required.', 'ecwid-woocommerce' ),
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
        ?>
        <p><?php esc_html_e( 'Enter your Ecwid API credentials. You can find these in your Ecwid Control Panel.', 'ecwid-woocommerce' ); ?></p>
        <div class="ecwid-wc-api-help">
            <details>
                <summary><?php esc_html_e( 'How to get your API credentials', 'ecwid-woocommerce' ); ?></summary>
                <ol>
                    <li><?php esc_html_e( 'Log in to your Ecwid Control Panel', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Go to Settings > API', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Your Store ID is displayed at the top', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Create a new API token or use an existing one', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Make sure the token has read access to products, orders, and customers', 'ecwid-woocommerce' ); ?></li>
                </ol>
            </details>
        </div>
        <?php
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
     * AJAX handler for testing API connection.
     *
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'ecwid_wc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce' ) ) );
        }

        // Get credentials - either from request or from saved options.
        $store_id     = isset( $_POST['store_id'] ) ? sanitize_text_field( wp_unslash( $_POST['store_id'] ) ) : '';
        $access_token = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';

        // If not provided in request, use saved values.
        if ( empty( $store_id ) ) {
            $store_id = get_option( 'ecwid_wc_ecwid_store_id', '' );
        }

        if ( empty( $access_token ) ) {
            $encrypted_token = get_option( 'ecwid_wc_ecwid_access_token', '' );
            $access_token    = $this->encryption->decrypt( $encrypted_token );
        }

        // Validate inputs.
        if ( empty( $store_id ) || empty( $access_token ) ) {
            wp_send_json_error( array(
                'message' => __( 'API credentials are not configured.', 'ecwid-woocommerce' ),
            ) );
        }

        if ( ! Ecwid_Api::validate_store_id( $store_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Store ID must be numeric.', 'ecwid-woocommerce' ),
            ) );
        }

        if ( ! Ecwid_Api::validate_access_token( $access_token ) ) {
            wp_send_json_error( array(
                'message' => __( 'Access token appears to be invalid.', 'ecwid-woocommerce' ),
            ) );
        }

        // Create API client and test connection.
        $api = Ecwid_Api::get_instance();
        $api->set_credentials( $store_id, $access_token );

        $result = $api->test_connection();

        // Cache the result.
        set_transient( 'ecwid_wc_connection_status', $result, HOUR_IN_SECONDS );

        // Log the result.
        $logger = Logger::get_instance();
        if ( $result['success'] ) {
            $logger->info( 'API connection test successful', $result['data'], 'Admin' );
            wp_send_json_success( $result );
        } else {
            $logger->warning( 'API connection test failed: ' . $result['message'], array(), 'Admin' );
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX handler for saving credentials.
     *
     * @return void
     */
    public function ajax_save_credentials() {
        check_ajax_referer( 'ecwid_wc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce' ) ) );
        }

        $store_id     = isset( $_POST['store_id'] ) ? sanitize_text_field( wp_unslash( $_POST['store_id'] ) ) : '';
        $access_token = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';

        // Validate Store ID.
        if ( ! empty( $store_id ) && ! Ecwid_Api::validate_store_id( $store_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Store ID must be numeric.', 'ecwid-woocommerce' ),
            ) );
        }

        // Save Store ID.
        update_option( 'ecwid_wc_ecwid_store_id', $store_id );

        // Save Access Token (encrypted).
        if ( ! empty( $access_token ) ) {
            if ( ! Ecwid_Api::validate_access_token( $access_token ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Access token appears to be invalid.', 'ecwid-woocommerce' ),
                ) );
            }

            $encrypted = $this->encryption->encrypt( $access_token );
            if ( false === $encrypted ) {
                wp_send_json_error( array(
                    'message' => __( 'Failed to encrypt access token.', 'ecwid-woocommerce' ),
                ) );
            }
            update_option( 'ecwid_wc_ecwid_access_token', $encrypted );
        }

        // Clear connection status cache.
        delete_transient( 'ecwid_wc_connection_status' );

        wp_send_json_success( array(
            'message' => __( 'Credentials saved successfully.', 'ecwid-woocommerce' ),
        ) );
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

        $logger = Logger::get_instance();
        $logger->clear_all();

        wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'ecwid-woocommerce' ) ) );
    }
}
