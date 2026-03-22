<?php
/**
 * Settings Page View
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

use Ecwid_WooCommerce\Utils\Encryption;

$encryption = Encryption::get_instance();
$has_token  = ! empty( get_option( 'ecwid_wc_ecwid_access_token', '' ) );
$store_id   = get_option( 'ecwid_wc_ecwid_store_id', '' );
?>

<div class="wrap ecwid-wc-settings">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="ecwid-wc-settings-container">
        <div class="ecwid-wc-settings-main">
            <form method="post" action="options.php" id="ecwid-wc-settings-form">
                <?php
                settings_fields( $this->plugin_slug );
                do_settings_sections( $this->plugin_slug );
                ?>

                <div class="ecwid-wc-settings-actions">
                    <?php submit_button( __( 'Save Settings', 'ecwid-woocommerce' ), 'primary', 'submit', false ); ?>
                </div>
            </form>
        </div>

        <div class="ecwid-wc-settings-sidebar">
            <!-- Quick Links -->
            <div class="ecwid-wc-card">
                <h3><?php esc_html_e( 'Quick Links', 'ecwid-woocommerce' ); ?></h3>
                <ul class="ecwid-wc-quick-links">
                    <li>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-dashboard' ) ); ?>">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php esc_html_e( 'Sync Dashboard', 'ecwid-woocommerce' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-logs' ) ); ?>">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php esc_html_e( 'View Logs', 'ecwid-woocommerce' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://api-docs.ecwid.com/" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e( 'Ecwid API Documentation', 'ecwid-woocommerce' ); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Getting Started -->
            <div class="ecwid-wc-card">
                <h3><?php esc_html_e( 'Getting Started', 'ecwid-woocommerce' ); ?></h3>
                <ol class="ecwid-wc-steps">
                    <li class="<?php echo ! empty( $store_id ) ? 'completed' : ''; ?>">
                        <?php esc_html_e( 'Enter your Ecwid Store ID', 'ecwid-woocommerce' ); ?>
                        <?php if ( ! empty( $store_id ) ) : ?>
                            <span class="dashicons dashicons-yes"></span>
                        <?php endif; ?>
                    </li>
                    <li class="<?php echo $has_token ? 'completed' : ''; ?>">
                        <?php esc_html_e( 'Enter your API Access Token', 'ecwid-woocommerce' ); ?>
                        <?php if ( $has_token ) : ?>
                            <span class="dashicons dashicons-yes"></span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <?php esc_html_e( 'Test the connection', 'ecwid-woocommerce' ); ?>
                    </li>
                    <li>
                        <?php esc_html_e( 'Configure sync settings', 'ecwid-woocommerce' ); ?>
                    </li>
                    <li>
                        <?php esc_html_e( 'Start syncing!', 'ecwid-woocommerce' ); ?>
                    </li>
                </ol>
            </div>

            <!-- Security Info -->
            <div class="ecwid-wc-card ecwid-wc-security-info">
                <h3>
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e( 'Security', 'ecwid-woocommerce' ); ?>
                </h3>
                <p>
                    <?php if ( $encryption->is_encryption_available() ) : ?>
                        <span class="ecwid-wc-security-badge ecwid-wc-security-good">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e( 'AES-256 Encryption Active', 'ecwid-woocommerce' ); ?>
                        </span>
                        <br>
                        <small><?php esc_html_e( 'Your API credentials are encrypted before storage.', 'ecwid-woocommerce' ); ?></small>
                    <?php else : ?>
                        <span class="ecwid-wc-security-badge ecwid-wc-security-warning">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e( 'Basic Encoding Only', 'ecwid-woocommerce' ); ?>
                        </span>
                        <br>
                        <small><?php esc_html_e( 'Enable OpenSSL extension for stronger encryption.', 'ecwid-woocommerce' ); ?></small>
                    <?php endif; ?>
                </p>
            </div>

            <!-- System Status -->
            <div class="ecwid-wc-card">
                <h3><?php esc_html_e( 'System Status', 'ecwid-woocommerce' ); ?></h3>
                <table class="ecwid-wc-status-table">
                    <tr>
                        <td><?php esc_html_e( 'PHP Version', 'ecwid-woocommerce' ); ?></td>
                        <td>
                            <?php
                            $php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
                            echo $php_ok ? '<span class="dashicons dashicons-yes" style="color:#46b450;"></span>' : '<span class="dashicons dashicons-no" style="color:#d63638;"></span>';
                            echo esc_html( PHP_VERSION );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'WooCommerce', 'ecwid-woocommerce' ); ?></td>
                        <td>
                            <?php
                            $wc_ok = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '5.0', '>=' );
                            echo $wc_ok ? '<span class="dashicons dashicons-yes" style="color:#46b450;"></span>' : '<span class="dashicons dashicons-no" style="color:#d63638;"></span>';
                            echo defined( 'WC_VERSION' ) ? esc_html( WC_VERSION ) : esc_html__( 'Not installed', 'ecwid-woocommerce' );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'OpenSSL', 'ecwid-woocommerce' ); ?></td>
                        <td>
                            <?php
                            $ssl_ok = extension_loaded( 'openssl' );
                            echo $ssl_ok ? '<span class="dashicons dashicons-yes" style="color:#46b450;"></span>' : '<span class="dashicons dashicons-warning" style="color:#dba617;"></span>';
                            echo $ssl_ok ? esc_html__( 'Enabled', 'ecwid-woocommerce' ) : esc_html__( 'Disabled', 'ecwid-woocommerce' );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'cURL', 'ecwid-woocommerce' ); ?></td>
                        <td>
                            <?php
                            $curl_ok = function_exists( 'curl_version' );
                            echo $curl_ok ? '<span class="dashicons dashicons-yes" style="color:#46b450;"></span>' : '<span class="dashicons dashicons-no" style="color:#d63638;"></span>';
                            echo $curl_ok ? esc_html__( 'Enabled', 'ecwid-woocommerce' ) : esc_html__( 'Disabled', 'ecwid-woocommerce' );
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
