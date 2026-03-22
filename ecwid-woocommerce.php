<?php
/**
 * Plugin Name:       Ecwid WooCommerce Integration
 * Plugin URI:        https://github.com/vdenezhnev/ecwid-plugin
 * Description:       Двусторонняя синхронизация данных между Ecwid и WooCommerce. Импорт/экспорт продуктов, заказов и клиентов.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Ecwid Plugin Team
 * Author URI:        https://github.com/vdenezhnev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ecwid-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   8.0
 *
 * @package Ecwid_WooCommerce
 */

namespace Ecwid_WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 */
define( 'ECWID_WC_VERSION', '1.0.0' );

/**
 * Plugin file path.
 */
define( 'ECWID_WC_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 */
define( 'ECWID_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'ECWID_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'ECWID_WC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 */
define( 'ECWID_WC_MIN_PHP_VERSION', '7.4' );

/**
 * Minimum WordPress version required.
 */
define( 'ECWID_WC_MIN_WP_VERSION', '5.0' );

/**
 * Minimum WooCommerce version required.
 */
define( 'ECWID_WC_MIN_WC_VERSION', '5.0' );

/**
 * Check PHP version and display admin notice if not met.
 *
 * @return bool
 */
function ecwid_wc_check_php_version() {
    if ( version_compare( PHP_VERSION, ECWID_WC_MIN_PHP_VERSION, '<' ) ) {
        add_action( 'admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required PHP version, 2: Current PHP version */
                    esc_html__( 'Ecwid WooCommerce Integration requires PHP version %1$s or higher. You are running version %2$s.', 'ecwid-woocommerce' ),
                    ECWID_WC_MIN_PHP_VERSION,
                    PHP_VERSION
                )
            );
        });
        return false;
    }
    return true;
}

/**
 * Check WordPress version and display admin notice if not met.
 *
 * @return bool
 */
function ecwid_wc_check_wp_version() {
    global $wp_version;
    if ( version_compare( $wp_version, ECWID_WC_MIN_WP_VERSION, '<' ) ) {
        add_action( 'admin_notices', function() {
            global $wp_version;
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required WordPress version, 2: Current WordPress version */
                    esc_html__( 'Ecwid WooCommerce Integration requires WordPress version %1$s or higher. You are running version %2$s.', 'ecwid-woocommerce' ),
                    ECWID_WC_MIN_WP_VERSION,
                    $wp_version
                )
            );
        });
        return false;
    }
    return true;
}

/**
 * Check WooCommerce availability and version.
 *
 * @return bool
 */
function ecwid_wc_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__( 'Ecwid WooCommerce Integration requires WooCommerce to be installed and activated.', 'ecwid-woocommerce' )
            );
        });
        return false;
    }

    if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, ECWID_WC_MIN_WC_VERSION, '<' ) ) {
        add_action( 'admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
                    esc_html__( 'Ecwid WooCommerce Integration requires WooCommerce version %1$s or higher. You are running version %2$s.', 'ecwid-woocommerce' ),
                    ECWID_WC_MIN_WC_VERSION,
                    WC_VERSION
                )
            );
        });
        return false;
    }

    return true;
}

/**
 * Load plugin text domain for translations.
 *
 * @return void
 */
function ecwid_wc_load_textdomain() {
    load_plugin_textdomain(
        'ecwid-woocommerce',
        false,
        dirname( ECWID_WC_PLUGIN_BASENAME ) . '/languages'
    );
}
add_action( 'init', __NAMESPACE__ . '\\ecwid_wc_load_textdomain' );

/**
 * Activation hook callback.
 *
 * @return void
 */
function ecwid_wc_activate() {
    require_once ECWID_WC_PLUGIN_DIR . 'includes/class-activator.php';
    Activator::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\ecwid_wc_activate' );

/**
 * Deactivation hook callback.
 *
 * @return void
 */
function ecwid_wc_deactivate() {
    require_once ECWID_WC_PLUGIN_DIR . 'includes/class-deactivator.php';
    Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\ecwid_wc_deactivate' );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function ecwid_wc_init() {
    // Check requirements.
    if ( ! ecwid_wc_check_php_version() ) {
        return;
    }

    if ( ! ecwid_wc_check_wp_version() ) {
        return;
    }

    // WooCommerce check runs after plugins are loaded.
    add_action( 'plugins_loaded', function() {
        if ( ! ecwid_wc_check_woocommerce() ) {
            return;
        }

        // Load the main plugin class.
        require_once ECWID_WC_PLUGIN_DIR . 'includes/class-plugin.php';

        // Initialize the plugin.
        $plugin = Plugin::get_instance();
        $plugin->run();
    });
}

// Start the plugin.
ecwid_wc_init();
