<?php
/**
 * Plugin Name: ioTecPay
 * Plugin URI: https://github.com/iotec-io/iotec-pay-wordpress-plugin
 * Description: Accept Airtel Money and MTN MoMo payments in Uganda on your WordPress site.
 * Version: 1.0.0
 * Author: ioTec Limited
 * Author URI: https://iotec.io
 * Developer: ioTec
 * Developer URI: https://iotec.io/
 * Text Domain: iotec
 * Domain Path: /languages
 *
 * Requires at least: 5.8
 * Tested up to: 6.1.1
 * Requires PHP: 7.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Uninstall the plugin.
 * Runs uninstall.php automatically when the user deletes the plugin
 * This clears up ioTec user data from the database before deleting the plugin
 */
register_uninstall_hook(__FILE__, array('iotec', 'iotec_uninstall'));

// Add a custom link to ioTec settings on the plugins list page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'iotec_action_links');
function iotec_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'iotec') . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}

// Include iotec-functions.php, use require_once to stop the script if iotec-functions.php is not found
require_once plugin_dir_path(__FILE__) . 'includes/iotec-functions.php';