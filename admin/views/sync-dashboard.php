<?php
/**
 * Sync Dashboard View
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$last_sync = get_option( 'ecwid_wc_last_sync', '' );
?>

<div class="wrap ecwid-wc-dashboard">
    <h1><?php esc_html_e( 'Sync Dashboard', 'ecwid-woocommerce' ); ?></h1>

    <div class="ecwid-wc-dashboard-header">
        <div class="ecwid-wc-sync-actions">
            <button type="button" id="ecwid-wc-start-sync" class="button button-primary button-large">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Start Sync', 'ecwid-woocommerce' ); ?>
            </button>
            <span class="ecwid-wc-last-sync">
                <?php
                if ( $last_sync ) {
                    printf(
                        /* translators: %s: Last sync date/time */
                        esc_html__( 'Last sync: %s', 'ecwid-woocommerce' ),
                        esc_html( $last_sync )
                    );
                } else {
                    esc_html_e( 'Never synced', 'ecwid-woocommerce' );
                }
                ?>
            </span>
        </div>
    </div>

    <div class="ecwid-wc-dashboard-grid">
        <!-- Products Card -->
        <div class="ecwid-wc-card ecwid-wc-stat-card">
            <div class="ecwid-wc-stat-icon">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="ecwid-wc-stat-content">
                <h3><?php esc_html_e( 'Products', 'ecwid-woocommerce' ); ?></h3>
                <div class="ecwid-wc-stat-numbers">
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value" id="products-synced">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Synced', 'ecwid-woocommerce' ); ?></span>
                    </div>
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value" id="products-pending">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Pending', 'ecwid-woocommerce' ); ?></span>
                    </div>
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value ecwid-wc-stat-error" id="products-failed">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Failed', 'ecwid-woocommerce' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Card -->
        <div class="ecwid-wc-card ecwid-wc-stat-card">
            <div class="ecwid-wc-stat-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="ecwid-wc-stat-content">
                <h3><?php esc_html_e( 'Orders', 'ecwid-woocommerce' ); ?></h3>
                <div class="ecwid-wc-stat-numbers">
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value" id="orders-synced">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Synced', 'ecwid-woocommerce' ); ?></span>
                    </div>
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value" id="orders-pending">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Pending', 'ecwid-woocommerce' ); ?></span>
                    </div>
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value ecwid-wc-stat-error" id="orders-failed">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Failed', 'ecwid-woocommerce' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customers Card -->
        <div class="ecwid-wc-card ecwid-wc-stat-card">
            <div class="ecwid-wc-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="ecwid-wc-stat-content">
                <h3><?php esc_html_e( 'Customers', 'ecwid-woocommerce' ); ?></h3>
                <div class="ecwid-wc-stat-numbers">
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value" id="customers-synced">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Synced', 'ecwid-woocommerce' ); ?></span>
                    </div>
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value" id="customers-pending">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Pending', 'ecwid-woocommerce' ); ?></span>
                    </div>
                    <div class="ecwid-wc-stat">
                        <span class="ecwid-wc-stat-value ecwid-wc-stat-error" id="customers-failed">0</span>
                        <span class="ecwid-wc-stat-label"><?php esc_html_e( 'Failed', 'ecwid-woocommerce' ); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Progress -->
    <div class="ecwid-wc-card ecwid-wc-progress-card" id="ecwid-wc-sync-progress" style="display: none;">
        <h3><?php esc_html_e( 'Sync Progress', 'ecwid-woocommerce' ); ?></h3>
        <div class="ecwid-wc-progress">
            <div class="ecwid-wc-progress-bar" id="ecwid-wc-progress-bar" style="width: 0%;"></div>
        </div>
        <p class="ecwid-wc-progress-text" id="ecwid-wc-progress-text">
            <?php esc_html_e( 'Preparing...', 'ecwid-woocommerce' ); ?>
        </p>
    </div>

    <!-- Recent Activity -->
    <div class="ecwid-wc-card">
        <h3><?php esc_html_e( 'Recent Activity', 'ecwid-woocommerce' ); ?></h3>
        <div id="ecwid-wc-recent-activity">
            <p class="ecwid-wc-no-activity">
                <?php esc_html_e( 'No recent activity.', 'ecwid-woocommerce' ); ?>
            </p>
        </div>
        <p class="ecwid-wc-view-all">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-logs' ) ); ?>">
                <?php esc_html_e( 'View all logs', 'ecwid-woocommerce' ); ?> →
            </a>
        </p>
    </div>
</div>
