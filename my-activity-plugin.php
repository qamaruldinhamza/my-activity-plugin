<?php
/*
Plugin Name: My Activity Plugin
Plugin URI: http://
Description: A brief description of the Plugin.
Version: 1.0
Author: Qamar Ul DIn Hamza
Author URI: http://
License: GPL2
*/


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('MY_ACTIVITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MY_ACTIVITY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes
require_once __DIR__ . '/vendor/autoload.php';

// Include necessary files
require_once MY_ACTIVITY_PLUGIN_DIR . 'includes/class-activity-widget.php';

// Activation & Deactivation Hooks
register_activation_hook(__FILE__, 'my_activity_plugin_activate');
register_deactivation_hook(__FILE__, 'my_activity_plugin_deactivate');

/**
 * Function to run on plugin activation
 */
function my_activity_plugin_activate() {
    require_once MY_ACTIVITY_PLUGIN_DIR . 'includes/class-activity-widget.php';
    Qamaruldinhamza\MyActivityPlugin\ActivityWidget::create_activity_table();
}

/**
 * Function to run on plugin deactivation
 */
function my_activity_plugin_deactivate() {
    // Optional: Clean up scheduled tasks or cache
}

/**
 * Initialize the plugin
 */
function my_activity_plugin_init() {
    if (is_admin()) {
        new Qamaruldinhamza\MyActivityPlugin\ActivityWidget();
    }
}
add_action('plugins_loaded', 'my_activity_plugin_init');

// Register uninstall hook to ensure our uninstall logic runs.
register_uninstall_hook( __FILE__, 'my_activity_plugin_uninstall' );

function my_activity_plugin_uninstall() {
    $uninstall_file = plugin_dir_path( __FILE__ ) . 'uninstall.php';
    if ( file_exists( $uninstall_file ) ) {
        include_once $uninstall_file;
    }
}