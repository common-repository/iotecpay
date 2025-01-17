<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// ioTec settings to remove
$option_name = 'woocommerce_iotec_settings';

delete_option($option_name);

// for site options in Multisite
delete_site_option($option_name);

// drop a custom database table
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mytable" );