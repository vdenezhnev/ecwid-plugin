<?php
/**
 * Ecwid Admin
 *
 * Handles admin settings and pages
 *
 * @package Ecwid_WooCommerce_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ecwid Admin class
 */
class Ecwid_Admin {

    /**
     * Logger instance
     *
     * @var Ecwid_Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Ecwid_Logger();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_ecwid_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_ecwid_manual_sync', array( $this, 'ajax_manual_sync' ) );
        add_action( 'wp_ajax_ecwid_register_webhook', array( $this, 'ajax_register_webhook' ) );
        add_action( 'wp_ajax_ecwid_clear_logs', array( $this, 'ajax_clear_logs' ) );

        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . ECWID_SYNC_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );

        // Add Ecwid info to order meta box
        add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Ecwid Sync', 'ecwid-woocommerce-sync' ),
            __( 'Ecwid Sync', 'ecwid-woocommerce-sync' ),
            'manage_woocommerce',
            'ecwid-sync',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings
        register_setting( 'ecwid_sync_settings', 'ecwid_store_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'ecwid_sync_settings', 'ecwid_api_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        // Sync Settings
        register_setting( 'ecwid_sync_settings', 'ecwid_sync_enabled', array(
            'type'    => 'string',
            'default' => 'yes',
        ) );

        register_setting( 'ecwid_sync_settings', 'ecwid_webhook_enabled', array(
            'type'    => 'string',
            'default' => 'yes',
        ) );

        register_setting( 'ecwid_sync_settings', 'ecwid_sync_interval', array(
            'type'              => 'string',
            'default'           => 'hourly',
            'sanitize_callback' => array( $this, 'sanitize_interval' ),
        ) );

        register_setting( 'ecwid_sync_settings', 'ecwid_sync_from_date', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'ecwid_sync_settings', 'ecwid_create_customers', array(
            'type'    => 'string',
            'default' => 'yes',
        ) );

        // API Settings Section
        add_settings_section(
            'ecwid_api_settings',
            __( 'API Settings', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_api_section' ),
            'ecwid-sync'
        );

        add_settings_field(
            'ecwid_store_id',
            __( 'Store ID', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_store_id_field' ),
            'ecwid-sync',
            'ecwid_api_settings'
        );

        add_settings_field(
            'ecwid_api_token',
            __( 'API Token', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_api_token_field' ),
            'ecwid-sync',
            'ecwid_api_settings'
        );

        // Sync Settings Section
        add_settings_section(
            'ecwid_sync_settings_section',
            __( 'Sync Settings', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_sync_section' ),
            'ecwid-sync'
        );

        add_settings_field(
            'ecwid_sync_enabled',
            __( 'Enable Sync', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_sync_enabled_field' ),
            'ecwid-sync',
            'ecwid_sync_settings_section'
        );

        add_settings_field(
            'ecwid_webhook_enabled',
            __( 'Enable Webhooks', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_webhook_enabled_field' ),
            'ecwid-sync',
            'ecwid_sync_settings_section'
        );

        add_settings_field(
            'ecwid_sync_interval',
            __( 'Sync Interval', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_sync_interval_field' ),
            'ecwid-sync',
            'ecwid_sync_settings_section'
        );

        add_settings_field(
            'ecwid_create_customers',
            __( 'Create Customers', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_create_customers_field' ),
            'ecwid-sync',
            'ecwid_sync_settings_section'
        );
    }

    /**
     * Sanitize sync interval
     *
     * @param string $value Interval value.
     * @return string
     */
    public function sanitize_interval( $value ) {
        $valid_intervals = array_keys( Ecwid_Cron_Handler::get_available_intervals() );
        
        if ( in_array( $value, $valid_intervals, true ) ) {
            // Reschedule cron with new interval
            $cron_handler = new Ecwid_Cron_Handler( new Ecwid_Order_Sync( new Ecwid_API_Client() ) );
            $cron_handler->schedule_sync( $value );
            
            return $value;
        }
        
        return 'hourly';
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_ecwid-sync' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ecwid-admin-styles',
            ECWID_SYNC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ECWID_SYNC_VERSION
        );

        wp_enqueue_script(
            'ecwid-admin-scripts',
            ECWID_SYNC_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            ECWID_SYNC_VERSION,
            true
        );

        wp_localize_script( 'ecwid-admin-scripts', 'ecwidSync', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'ecwid_sync_nonce' ),
            'strings'   => array(
                'testing'     => __( 'Testing connection...', 'ecwid-woocommerce-sync' ),
                'syncing'     => __( 'Syncing orders...', 'ecwid-woocommerce-sync' ),
                'success'     => __( 'Success!', 'ecwid-woocommerce-sync' ),
                'error'       => __( 'Error:', 'ecwid-woocommerce-sync' ),
                'confirm'     => __( 'Are you sure?', 'ecwid-woocommerce-sync' ),
            ),
        ) );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
        ?>
        <div class="wrap ecwid-sync-admin">
            <h1><?php esc_html_e( 'Ecwid WooCommerce Sync', 'ecwid-woocommerce-sync' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ecwid-sync&tab=settings' ) ); ?>" 
                   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'ecwid-woocommerce-sync' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ecwid-sync&tab=sync' ) ); ?>" 
                   class="nav-tab <?php echo 'sync' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Sync', 'ecwid-woocommerce-sync' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ecwid-sync&tab=logs' ) ); ?>" 
                   class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Logs', 'ecwid-woocommerce-sync' ); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'sync':
                        $this->render_sync_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    default:
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'ecwid_sync_settings' );
            do_settings_sections( 'ecwid-sync' );
            submit_button();
            ?>
        </form>

        <div class="ecwid-webhook-info">
            <h3><?php esc_html_e( 'Webhook URL', 'ecwid-woocommerce-sync' ); ?></h3>
            <p><?php esc_html_e( 'Use this URL to configure webhooks in your Ecwid dashboard:', 'ecwid-woocommerce-sync' ); ?></p>
            <code><?php echo esc_url( Ecwid_Webhook_Handler::get_webhook_url() ); ?></code>
            <p>
                <button type="button" class="button" id="ecwid-register-webhook">
                    <?php esc_html_e( 'Register Webhook Automatically', 'ecwid-woocommerce-sync' ); ?>
                </button>
                <span id="ecwid-webhook-result"></span>
            </p>
        </div>
        <?php
    }

    /**
     * Render sync tab
     */
    private function render_sync_tab() {
        $last_sync  = get_option( 'ecwid_last_sync', '' );
        $next_sync  = wp_next_scheduled( 'ecwid_sync_orders_cron' );
        ?>
        <div class="ecwid-sync-status">
            <h3><?php esc_html_e( 'Sync Status', 'ecwid-woocommerce-sync' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Last Sync', 'ecwid-woocommerce-sync' ); ?></th>
                    <td>
                        <?php 
                        if ( $last_sync ) {
                            echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync ) ) );
                        } else {
                            esc_html_e( 'Never', 'ecwid-woocommerce-sync' );
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Next Scheduled Sync', 'ecwid-woocommerce-sync' ); ?></th>
                    <td>
                        <?php 
                        if ( $next_sync ) {
                            echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_sync ) );
                        } else {
                            esc_html_e( 'Not scheduled', 'ecwid-woocommerce-sync' );
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Manual Sync', 'ecwid-woocommerce-sync' ); ?></h3>
            
            <p>
                <button type="button" class="button button-primary" id="ecwid-test-connection">
                    <?php esc_html_e( 'Test Connection', 'ecwid-woocommerce-sync' ); ?>
                </button>
                <span id="ecwid-connection-result"></span>
            </p>

            <p>
                <button type="button" class="button button-primary" id="ecwid-manual-sync">
                    <?php esc_html_e( 'Sync Now', 'ecwid-woocommerce-sync' ); ?>
                </button>
                <span id="ecwid-sync-result"></span>
            </p>

            <h4><?php esc_html_e( 'Full Sync', 'ecwid-woocommerce-sync' ); ?></h4>
            <p class="description">
                <?php esc_html_e( 'Run a full sync from a specific date. This will import all orders from Ecwid.', 'ecwid-woocommerce-sync' ); ?>
            </p>
            
            <p>
                <label>
                    <?php esc_html_e( 'From Date:', 'ecwid-woocommerce-sync' ); ?>
                    <input type="date" id="ecwid-full-sync-date" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>">
                </label>
                <button type="button" class="button" id="ecwid-full-sync">
                    <?php esc_html_e( 'Run Full Sync', 'ecwid-woocommerce-sync' ); ?>
                </button>
            </p>
        </div>

        <?php $this->render_sync_stats(); ?>
        <?php
    }

    /**
     * Render sync statistics
     */
    private function render_sync_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_synced_orders';

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        if ( ! $table_exists ) {
            return;
        }

        $total_synced = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
        $synced_today = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(last_synced) = %s",
            gmdate( 'Y-m-d' )
        ) );

        ?>
        <div class="ecwid-sync-stats">
            <h3><?php esc_html_e( 'Statistics', 'ecwid-woocommerce-sync' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Total Synced Orders', 'ecwid-woocommerce-sync' ); ?></th>
                    <td><?php echo esc_html( number_format_i18n( $total_synced ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Synced Today', 'ecwid-woocommerce-sync' ); ?></th>
                    <td><?php echo esc_html( number_format_i18n( $synced_today ) ); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render logs tab
     */
    private function render_logs_tab() {
        $logs = $this->logger->get_logs( array( 'limit' => 100 ) );
        ?>
        <div class="ecwid-logs">
            <h3><?php esc_html_e( 'Sync Logs', 'ecwid-woocommerce-sync' ); ?></h3>
            
            <p>
                <button type="button" class="button" id="ecwid-clear-logs">
                    <?php esc_html_e( 'Clear Logs', 'ecwid-woocommerce-sync' ); ?>
                </button>
            </p>

            <?php if ( empty( $logs ) ) : ?>
                <p><?php esc_html_e( 'No logs found.', 'ecwid-woocommerce-sync' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;"><?php esc_html_e( 'Date', 'ecwid-woocommerce-sync' ); ?></th>
                            <th style="width: 80px;"><?php esc_html_e( 'Level', 'ecwid-woocommerce-sync' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'ecwid-woocommerce-sync' ); ?></th>
                            <th><?php esc_html_e( 'Context', 'ecwid-woocommerce-sync' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr class="log-<?php echo esc_attr( $log->log_type ); ?>">
                                <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log->created_at ) ) ); ?></td>
                                <td>
                                    <span class="log-level log-level-<?php echo esc_attr( $log->log_type ); ?>">
                                        <?php echo esc_html( strtoupper( $log->log_type ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log->message ); ?></td>
                                <td>
                                    <?php 
                                    if ( ! empty( $log->context ) ) {
                                        echo '<code>' . esc_html( $log->context ) . '</code>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render API section description
     */
    public function render_api_section() {
        echo '<p>' . esc_html__( 'Enter your Ecwid API credentials. You can find these in your Ecwid dashboard.', 'ecwid-woocommerce-sync' ) . '</p>';
    }

    /**
     * Render sync section description
     */
    public function render_sync_section() {
        echo '<p>' . esc_html__( 'Configure synchronization settings.', 'ecwid-woocommerce-sync' ) . '</p>';
    }

    /**
     * Render store ID field
     */
    public function render_store_id_field() {
        $value = get_option( 'ecwid_store_id', '' );
        ?>
        <input type="text" name="ecwid_store_id" id="ecwid_store_id" 
               value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e( 'Your Ecwid Store ID (numeric).', 'ecwid-woocommerce-sync' ); ?>
        </p>
        <?php
    }

    /**
     * Render API token field
     */
    public function render_api_token_field() {
        $value = get_option( 'ecwid_api_token', '' );
        ?>
        <input type="password" name="ecwid_api_token" id="ecwid_api_token" 
               value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e( 'Your Ecwid API access token.', 'ecwid-woocommerce-sync' ); ?>
        </p>
        <?php
    }

    /**
     * Render sync enabled field
     */
    public function render_sync_enabled_field() {
        $value = get_option( 'ecwid_sync_enabled', 'yes' );
        ?>
        <label>
            <input type="checkbox" name="ecwid_sync_enabled" value="yes" 
                   <?php checked( $value, 'yes' ); ?>>
            <?php esc_html_e( 'Enable automatic order synchronization', 'ecwid-woocommerce-sync' ); ?>
        </label>
        <?php
    }

    /**
     * Render webhook enabled field
     */
    public function render_webhook_enabled_field() {
        $value = get_option( 'ecwid_webhook_enabled', 'yes' );
        ?>
        <label>
            <input type="checkbox" name="ecwid_webhook_enabled" value="yes" 
                   <?php checked( $value, 'yes' ); ?>>
            <?php esc_html_e( 'Enable webhook notifications from Ecwid', 'ecwid-woocommerce-sync' ); ?>
        </label>
        <?php
    }

    /**
     * Render sync interval field
     */
    public function render_sync_interval_field() {
        $value     = get_option( 'ecwid_sync_interval', 'hourly' );
        $intervals = Ecwid_Cron_Handler::get_available_intervals();
        ?>
        <select name="ecwid_sync_interval" id="ecwid_sync_interval">
            <?php foreach ( $intervals as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'How often to check for new orders from Ecwid.', 'ecwid-woocommerce-sync' ); ?>
        </p>
        <?php
    }

    /**
     * Render create customers field
     */
    public function render_create_customers_field() {
        $value = get_option( 'ecwid_create_customers', 'yes' );
        ?>
        <label>
            <input type="checkbox" name="ecwid_create_customers" value="yes" 
                   <?php checked( $value, 'yes' ); ?>>
            <?php esc_html_e( 'Create WooCommerce customers from Ecwid orders', 'ecwid-woocommerce-sync' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'If enabled, new WooCommerce customer accounts will be created for Ecwid customers.', 'ecwid-woocommerce-sync' ); ?>
        </p>
        <?php
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=ecwid-sync' ),
            __( 'Settings', 'ecwid-woocommerce-sync' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'ecwid_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce-sync' ) ) );
        }

        $api    = new Ecwid_API_Client();
        $result = $api->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Connection successful!', 'ecwid-woocommerce-sync' ) ) );
    }

    /**
     * AJAX: Manual sync
     */
    public function ajax_manual_sync() {
        check_ajax_referer( 'ecwid_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce-sync' ) ) );
        }

        $full_sync = isset( $_POST['full_sync'] ) && 'true' === $_POST['full_sync'];
        $from_date = isset( $_POST['from_date'] ) ? sanitize_text_field( wp_unslash( $_POST['from_date'] ) ) : '';

        $api         = new Ecwid_API_Client();
        $order_sync  = new Ecwid_Order_Sync( $api );
        $cron_handler = new Ecwid_Cron_Handler( $order_sync );

        if ( $full_sync && ! empty( $from_date ) ) {
            $result = $cron_handler->run_full_sync( gmdate( 'c', strtotime( $from_date ) ) );
        } else {
            $result = $cron_handler->run_manual_sync();
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Sync completed successfully!', 'ecwid-woocommerce-sync' ),
            'result'  => $result,
        ) );
    }

    /**
     * AJAX: Register webhook
     */
    public function ajax_register_webhook() {
        check_ajax_referer( 'ecwid_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce-sync' ) ) );
        }

        $api         = new Ecwid_API_Client();
        $webhook_url = Ecwid_Webhook_Handler::get_webhook_url();
        $result      = $api->register_webhook( $webhook_url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Webhook registered successfully!', 'ecwid-woocommerce-sync' ) ) );
    }

    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer( 'ecwid_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ecwid-woocommerce-sync' ) ) );
        }

        $this->logger->clear_all_logs();

        wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'ecwid-woocommerce-sync' ) ) );
    }

    /**
     * Add order meta box
     */
    public function add_order_meta_box() {
        $screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'ecwid-order-info',
            __( 'Ecwid Order Info', 'ecwid-woocommerce-sync' ),
            array( $this, 'render_order_meta_box' ),
            $screen,
            'side',
            'default'
        );
    }

    /**
     * Render order meta box
     *
     * @param WP_Post|WC_Order $post_or_order Post object or order object.
     */
    public function render_order_meta_box( $post_or_order ) {
        $order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;

        if ( ! $order ) {
            return;
        }

        $ecwid_order_id = $order->get_meta( '_ecwid_order_id' );

        if ( ! $ecwid_order_id ) {
            echo '<p>' . esc_html__( 'This order was not synced from Ecwid.', 'ecwid-woocommerce-sync' ) . '</p>';
            return;
        }

        ?>
        <table class="ecwid-order-meta">
            <tr>
                <td><strong><?php esc_html_e( 'Ecwid Order ID:', 'ecwid-woocommerce-sync' ); ?></strong></td>
                <td><?php echo esc_html( $ecwid_order_id ); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'Order Number:', 'ecwid-woocommerce-sync' ); ?></strong></td>
                <td><?php echo esc_html( $order->get_meta( '_ecwid_order_number' ) ); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'Payment Status:', 'ecwid-woocommerce-sync' ); ?></strong></td>
                <td><?php echo esc_html( $order->get_meta( '_ecwid_payment_status' ) ); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'Fulfillment:', 'ecwid-woocommerce-sync' ); ?></strong></td>
                <td><?php echo esc_html( $order->get_meta( '_ecwid_fulfillment_status' ) ); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'Last Sync:', 'ecwid-woocommerce-sync' ); ?></strong></td>
                <td>
                    <?php
                    $last_sync = $order->get_meta( '_ecwid_last_sync' );
                    if ( $last_sync ) {
                        echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $last_sync ) ) );
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }
}
