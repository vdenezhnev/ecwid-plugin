<?php
/**
 * Settings Page View
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap ecwid-wc-settings">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="ecwid-wc-settings-container">
        <form method="post" action="options.php">
            <?php
            settings_fields( $this->plugin_slug );
            do_settings_sections( $this->plugin_slug );
            ?>

            <div class="ecwid-wc-settings-actions">
                <?php submit_button( __( 'Save Settings', 'ecwid-woocommerce' ), 'primary', 'submit', false ); ?>
                <button type="button" id="ecwid-wc-test-connection" class="button button-secondary">
                    <?php esc_html_e( 'Test Connection', 'ecwid-woocommerce' ); ?>
                </button>
            </div>
        </form>

        <div class="ecwid-wc-settings-sidebar">
            <div class="ecwid-wc-card">
                <h3><?php esc_html_e( 'Quick Links', 'ecwid-woocommerce' ); ?></h3>
                <ul>
                    <li>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-dashboard' ) ); ?>">
                            <?php esc_html_e( 'Sync Dashboard', 'ecwid-woocommerce' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-logs' ) ); ?>">
                            <?php esc_html_e( 'View Logs', 'ecwid-woocommerce' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://api-docs.ecwid.com/" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'Ecwid API Documentation', 'ecwid-woocommerce' ); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="ecwid-wc-card">
                <h3><?php esc_html_e( 'Getting Started', 'ecwid-woocommerce' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'Enter your Ecwid Store ID', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Enter your API Access Token', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Test the connection', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Configure sync settings', 'ecwid-woocommerce' ); ?></li>
                    <li><?php esc_html_e( 'Start syncing!', 'ecwid-woocommerce' ); ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>
