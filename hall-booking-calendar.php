<?php
/**
 * Plugin Name: Hall Booking Calendar
 * Plugin URI: https://github.com/slashzero/hall-calendar
 * Description: A WordPress plugin to manage a hall calendar with 3 rooms, recurring bookings, and calendar subscriptions.
 * Version: 1.3.0
 * Author: Hall Calendar Team
 * Author URI: https://github.com/slashzero
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: hall-booking-calendar
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('HBC_VERSION', '1.3.0');
define('HBC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HBC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HBC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Activation hook
 */
function hbc_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Create rooms table
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $sql_rooms = "CREATE TABLE IF NOT EXISTS $rooms_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        description text,
        capacity int(11) DEFAULT 0,
        status enum('active','inactive') DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Create groups table
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $sql_groups = "CREATE TABLE IF NOT EXISTS $groups_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        description text,
        status enum('active','inactive') DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Create bookings table
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $sql_bookings = "CREATE TABLE IF NOT EXISTS $bookings_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        room_id mediumint(9) NOT NULL,
        group_id mediumint(9),
        user_id bigint(20) UNSIGNED,
        user_name varchar(100) NOT NULL,
        user_email varchar(100) NOT NULL,
        booking_date date NOT NULL,
        start_time time NOT NULL,
        end_time time NOT NULL,
        purpose text,
        description text,
        file_path varchar(255),
        status enum('pending','confirmed','cancelled') DEFAULT 'pending',
        series_id varchar(50),
        is_recurring tinyint(1) DEFAULT 0,
        recurrence_pattern varchar(50),
        recurrence_end_date date,
        parent_booking_id mediumint(9),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY room_id (room_id),
        KEY group_id (group_id),
        KEY booking_date (booking_date),
        KEY series_id (series_id),
        KEY parent_booking_id (parent_booking_id)
    ) $charset_collate;";

    // Create calendar subscriptions table
    $subscriptions_table = $wpdb->prefix . 'hbc_subscriptions';
    $sql_subscriptions = "CREATE TABLE IF NOT EXISTS $subscriptions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED,
        token varchar(100) NOT NULL UNIQUE,
        group_id mediumint(9),
        room_id mediumint(9),
        status enum('active','inactive') DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_accessed datetime,
        PRIMARY KEY  (id),
        KEY token (token),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_rooms);
    dbDelta($sql_groups);
    dbDelta($sql_bookings);
    dbDelta($sql_subscriptions);

    // Insert default 3 rooms if none exist
    $existing_rooms = $wpdb->get_var("SELECT COUNT(*) FROM $rooms_table");
    if ($existing_rooms == 0) {
        $wpdb->insert($rooms_table, array(
            'name' => 'Conference Room A',
            'description' => 'Large conference room with projector and whiteboard',
            'capacity' => 20,
            'status' => 'active'
        ));
        $wpdb->insert($rooms_table, array(
            'name' => 'Conference Room B',
            'description' => 'Medium meeting room with video conferencing',
            'capacity' => 10,
            'status' => 'active'
        ));
        $wpdb->insert($rooms_table, array(
            'name' => 'Conference Room C',
            'description' => 'Small meeting room for team discussions',
            'capacity' => 6,
            'status' => 'active'
        ));
    }

    // Insert default groups if none exist
    $existing_groups = $wpdb->get_var("SELECT COUNT(*) FROM $groups_table");
    if ($existing_groups == 0) {
        $wpdb->insert($groups_table, array(
            'name' => 'Department Meetings',
            'description' => 'Regular departmental meetings and discussions',
            'status' => 'active'
        ));
        $wpdb->insert($groups_table, array(
            'name' => 'Training Sessions',
            'description' => 'Employee training and workshops',
            'status' => 'active'
        ));
        $wpdb->insert($groups_table, array(
            'name' => 'Client Meetings',
            'description' => 'External client meetings and presentations',
            'status' => 'active'
        ));
    }

    // Add plugin version option
    add_option('hbc_version', HBC_VERSION);

    // Add default settings
    add_option('hbc_booking_password', '');
    add_option('hbc_webmaster_email', get_option('admin_email'));

    // Create uploads directory for booking files
    $upload_dir = wp_upload_dir();
    $hbc_upload_dir = $upload_dir['basedir'] . '/hall-bookings';
    if (!file_exists($hbc_upload_dir)) {
        wp_mkdir_p($hbc_upload_dir);
        // Create .htaccess to protect uploaded files
        $htaccess_content = "Options -Indexes\n<Files *.pdf>\n    Order Allow,Deny\n    Allow from all\n</Files>";
        file_put_contents($hbc_upload_dir . '/.htaccess', $htaccess_content);
    }
}
register_activation_hook(__FILE__, 'hbc_activate');

/**
 * Deactivation hook
 */
function hbc_deactivate() {
    // Clean up tasks if needed
}
register_deactivation_hook(__FILE__, 'hbc_deactivate');

/**
 * Load plugin textdomain
 */
function hbc_load_textdomain() {
    load_plugin_textdomain('hall-booking-calendar', false, dirname(HBC_PLUGIN_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'hbc_load_textdomain');

// Include admin functions
require_once HBC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-settings.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-rooms.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-groups.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-bookings.php';
require_once HBC_PLUGIN_DIR . 'includes/recurring-bookings.php';
require_once HBC_PLUGIN_DIR . 'includes/calendar-subscription.php';
require_once HBC_PLUGIN_DIR . 'includes/frontend-calendar.php';
require_once HBC_PLUGIN_DIR . 'includes/booking-handler.php';

/**
 * Enqueue admin styles and scripts
 */
function hbc_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'hall-booking') === false) {
        return;
    }

    wp_enqueue_style('hbc-admin-style', HBC_PLUGIN_URL . 'assets/css/admin-style.css', array(), HBC_VERSION);
    wp_enqueue_script('hbc-admin-script', HBC_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), HBC_VERSION, true);
}
add_action('admin_enqueue_scripts', 'hbc_admin_enqueue_scripts');

/**
 * Enqueue frontend styles and scripts
 */
function hbc_frontend_enqueue_scripts() {
    wp_enqueue_style('hbc-frontend-style', HBC_PLUGIN_URL . 'assets/css/frontend-style.css', array(), HBC_VERSION);
    wp_enqueue_script('hbc-frontend-script', HBC_PLUGIN_URL . 'assets/js/frontend-script.js', array('jquery'), HBC_VERSION, true);

    // Localize script for AJAX
    wp_localize_script('hbc-frontend-script', 'hbc_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hbc_booking_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'hbc_frontend_enqueue_scripts');
