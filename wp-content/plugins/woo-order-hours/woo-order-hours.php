<?php
/**
 * Plugin Name: Woo Order Hours
 * Plugin URI:  https://example.com/woo-order-hours
 * Description: Restrict WooCommerce ordering to configured business hours with cache-safe notices and checkout enforcement.
 * Version:     1.0.0
 * Author:      GPT Dev
 * Text Domain: woo-order-hours
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * WC requires at least: 8.0
 * WC tested up to: 8.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WOH_VERSION', '1.0.0' );
define( 'WOH_PLUGIN_FILE', __FILE__ );
define( 'WOH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WOH_PLUGIN_DIR . 'includes/helpers.php';
require_once WOH_PLUGIN_DIR . 'includes/class-settings.php';
require_once WOH_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once WOH_PLUGIN_DIR . 'includes/class-frontend.php';
require_once WOH_PLUGIN_DIR . 'includes/class-checkout-guard.php';

/**
 * Run on activation.
 */
function woh_activate_plugin(): void {
    if ( ! get_option( 'woo_order_hours_settings' ) ) {
        update_option( 'woo_order_hours_settings', woh_get_default_settings() );
    }
    woh_touch_cache_buster();
}
register_activation_hook( __FILE__, 'woh_activate_plugin' );

add_action(
    'plugins_loaded',
    static function (): void {
        load_plugin_textdomain( 'woo-order-hours', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        if ( class_exists( 'WooCommerce', false ) ) {
            WOH_Settings::init();
            WOH_Frontend::init();
            WOH_Checkout_Guard::init();
        }
    }
);

add_action(
    'update_option_woo_order_hours_settings',
    static function (): void {
        WOH_Scheduler::reset_instance();
        woh_touch_cache_buster();
    }
);
