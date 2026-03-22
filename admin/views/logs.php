<?php
/**
 * Logs Page View
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

use Ecwid_WooCommerce\Utils\Logger;

$logger = Logger::get_instance();

// Get current filters.
$level    = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page = 50;

// Get logs.
$result = $logger->get_logs( array(
    'level'    => $level,
    'search'   => $search,
    'page'     => $paged,
    'per_page' => $per_page,
) );

$logs  = $result['logs'];
$total = $result['total'];
$pages = $result['pages'];

// Level labels.
$level_labels = array(
    'debug'   => __( 'Debug', 'ecwid-woocommerce' ),
    'info'    => __( 'Info', 'ecwid-woocommerce' ),
    'warning' => __( 'Warning', 'ecwid-woocommerce' ),
    'error'   => __( 'Error', 'ecwid-woocommerce' ),
);
?>

<div class="wrap ecwid-wc-logs">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Logs', 'ecwid-woocommerce' ); ?></h1>

    <div class="ecwid-wc-logs-actions">
        <button type="button" id="ecwid-wc-clear-logs" class="button">
            <?php esc_html_e( 'Clear Logs', 'ecwid-woocommerce' ); ?>
        </button>
    </div>

    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="ecwid-wc-logs-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr( $this->plugin_slug . '-logs' ); ?>">

            <select name="level">
                <option value=""><?php esc_html_e( 'All Levels', 'ecwid-woocommerce' ); ?></option>
                <?php foreach ( $level_labels as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $level, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                   placeholder="<?php esc_attr_e( 'Search logs...', 'ecwid-woocommerce' ); ?>">

            <?php submit_button( __( 'Filter', 'ecwid-woocommerce' ), 'secondary', 'filter', false ); ?>

            <?php if ( $level || $search ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-logs' ) ); ?>" class="button">
                    <?php esc_html_e( 'Clear Filters', 'ecwid-woocommerce' ); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Logs Table -->
    <?php if ( ! empty( $logs ) ) : ?>
        <table class="wp-list-table widefat fixed striped ecwid-wc-logs-table">
            <thead>
                <tr>
                    <th class="column-date"><?php esc_html_e( 'Date', 'ecwid-woocommerce' ); ?></th>
                    <th class="column-level"><?php esc_html_e( 'Level', 'ecwid-woocommerce' ); ?></th>
                    <th class="column-source"><?php esc_html_e( 'Source', 'ecwid-woocommerce' ); ?></th>
                    <th class="column-message"><?php esc_html_e( 'Message', 'ecwid-woocommerce' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr class="ecwid-wc-log-row ecwid-wc-log-<?php echo esc_attr( $log['level'] ); ?>">
                        <td class="column-date">
                            <?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log['created_at'] ) ) ); ?>
                        </td>
                        <td class="column-level">
                            <span class="ecwid-wc-log-level ecwid-wc-log-level-<?php echo esc_attr( $log['level'] ); ?>">
                                <?php echo esc_html( $level_labels[ $log['level'] ] ?? $log['level'] ); ?>
                            </span>
                        </td>
                        <td class="column-source">
                            <?php echo esc_html( $log['source'] ); ?>
                        </td>
                        <td class="column-message">
                            <?php echo esc_html( $log['message'] ); ?>
                            <?php if ( ! empty( $log['context'] ) ) : ?>
                                <button type="button" class="ecwid-wc-toggle-context button-link">
                                    <?php esc_html_e( 'Show details', 'ecwid-woocommerce' ); ?>
                                </button>
                                <pre class="ecwid-wc-log-context" style="display: none;"><?php echo esc_html( $log['context'] ); ?></pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        printf(
                            /* translators: %s: Number of items */
                            esc_html( _n( '%s item', '%s items', $total, 'ecwid-woocommerce' ) ),
                            number_format_i18n( $total )
                        );
                        ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                        echo wp_kses_post(
                            paginate_links( array(
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $pages,
                                'current'   => $paged,
                            ) )
                        );
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <div class="ecwid-wc-no-logs">
            <p><?php esc_html_e( 'No logs found.', 'ecwid-woocommerce' ); ?></p>
        </div>
    <?php endif; ?>
</div>
