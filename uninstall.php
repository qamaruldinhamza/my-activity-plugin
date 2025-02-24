<?php

/**
 * Uninstall script for My Activity Plugin.
 *
 * This file is executed when the plugin is uninstalled.
 * It removes the custom user activity table from the database.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Define the table name based on the WordPress database prefix.
$table_name = $wpdb->prefix . 'user_activity';

// Drop the custom table if it exists.
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");