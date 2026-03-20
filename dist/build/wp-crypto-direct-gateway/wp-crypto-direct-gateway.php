<?php
/**
 * Plugin Name: WP Crypto Direct Gateway
 * Description: Direct crypto payment gateway for WordPress and WooCommerce with QR-based checkout, wallet copy actions, automatic payment watching, and branded payment instructions.
 * Version: 0.2.0
 * Author: WP Crypto Direct Gateway
 * Text Domain: wp-crypto-direct-gateway
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WCDG_VERSION', '0.2.0');
define('WCDG_PLUGIN_FILE', __FILE__);
define('WCDG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCDG_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-database.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-crypto.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-payment-requests.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-rates.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-settings.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-blockchain-watcher.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-admin-payments-page.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-rest-controller.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-shortcodes.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-woocommerce-gateway.php';
require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-plugin.php';

register_activation_hook(WCDG_PLUGIN_FILE, array('WCDG_Database', 'activate'));
register_activation_hook(WCDG_PLUGIN_FILE, array('WCDG_Blockchain_Watcher', 'activate'));
register_deactivation_hook(WCDG_PLUGIN_FILE, array('WCDG_Blockchain_Watcher', 'deactivate'));

function wcdg_plugin(): WCDG_Plugin
{
    static $instance = null;

    if ($instance === null) {
        $instance = new WCDG_Plugin();
    }

    return $instance;
}

add_action('plugins_loaded', 'wcdg_plugin');