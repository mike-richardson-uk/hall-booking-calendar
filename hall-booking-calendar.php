<?php
/**
 * Plugin Name: Hall Booking Calendar
 * Plugin URI: https://github.com/slashzero/hall-calendar
 * Description: A WordPress plugin to manage a hall calendar with 3 rooms, recurring bookings, and calendar subscriptions.
 * Version: 1.10.1
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
define('HBC_VERSION', '1.10.1');
define('HBC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HBC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HBC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook - Creates database tables and sets up default data
 *
 * Creates four tables: rooms, groups, bookings, and subscriptions.
 * Also inserts default rooms and groups if none exist.
 * Sets up upload directory for booking files.
 *
 * @since 1.0.0
 * @return void
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
        category_id mediumint(9),
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

    // Create booking_rooms junction table for multi-room bookings
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';
    $sql_booking_rooms = "CREATE TABLE IF NOT EXISTS $booking_rooms_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        booking_id mediumint(9) NOT NULL,
        room_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY booking_room (booking_id, room_id),
        KEY booking_id (booking_id),
        KEY room_id (room_id)
    ) $charset_collate;";

    // Create event categories table
    $categories_table = $wpdb->prefix . 'hbc_categories';
    $sql_categories = "CREATE TABLE IF NOT EXISTS $categories_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        description text,
        status enum('active','inactive') DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
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
    dbDelta($sql_booking_rooms);
    dbDelta($sql_categories);
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

    // Insert default categories if none exist
    $existing_categories = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
    if ($existing_categories == 0) {
        $wpdb->insert($categories_table, array(
            'name' => 'Rehearsal',
            'description' => 'Music, drama, or dance rehearsals',
            'status' => 'active'
        ));
        $wpdb->insert($categories_table, array(
            'name' => 'Social Event',
            'description' => 'Parties, gatherings, and social occasions',
            'status' => 'active'
        ));
        $wpdb->insert($categories_table, array(
            'name' => 'Meeting',
            'description' => 'Business or committee meetings',
            'status' => 'active'
        ));
        $wpdb->insert($categories_table, array(
            'name' => 'Class / Workshop',
            'description' => 'Educational classes and workshops',
            'status' => 'active'
        ));
        $wpdb->insert($categories_table, array(
            'name' => 'Private Hire',
            'description' => 'Private bookings and functions',
            'status' => 'active'
        ));
    }

    // Add plugin version option
    add_option('hbc_version', HBC_VERSION);

    // Add default settings
    add_option('hbc_booking_password', '');
    add_option('hbc_webmaster_email', get_option('admin_email'));

    // Create a booking page with the shortcode if one doesn't already exist
    $existing_page_id = get_option('hbc_booking_page_id', 0);
    $existing_page = $existing_page_id ? get_post($existing_page_id) : null;
    if (!$existing_page || $existing_page->post_status === 'trash') {
        $page_id = wp_insert_post(array(
            'post_title'   => __('Book a Room', 'hall-booking-calendar'),
            'post_content' => '[hall_booking_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_option('hbc_booking_page_id', $page_id);
        }
    }

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

// Flush rewrite rules after activation to register event URL rules
function hbc_flush_rewrite_rules_on_activation() {
    hbc_register_event_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'hbc_flush_rewrite_rules_on_activation');

// Flush rewrite rules when plugin version changes (e.g. after update)
function hbc_maybe_flush_rewrite_rules() {
    $stored_version = get_option('hbc_version', '0');
    if (version_compare($stored_version, HBC_VERSION, '<')) {
        hbc_register_event_rewrite_rules();
        flush_rewrite_rules();
        update_option('hbc_version', HBC_VERSION);
    }
}
add_action('init', 'hbc_maybe_flush_rewrite_rules');

/**
 * Check and upgrade database schema if needed
 *
 * Checks for missing columns in the bookings table (description, file_path)
 * and adds them if they don't exist. Runs on every plugin load to ensure
 * database schema is up to date when upgrading from older versions.
 *
 * Also handles security upgrades like password hashing migration.
 *
 * @since 1.3.0
 * @return void
 */
function hbc_check_database_upgrade() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
    if (!$table_exists) {
        return; // Table doesn't exist yet, activation will handle it
    }

    // Get current columns
    $columns = $wpdb->get_col("DESCRIBE $bookings_table", 0);

    // Check if description column exists
    if (!in_array('description', $columns)) {
        $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN description text AFTER purpose");
    }

    // Check if file_path column exists
    if (!in_array('file_path', $columns)) {
        $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN file_path varchar(255) AFTER description");
    }

    // Check if category_id column exists
    if (!in_array('category_id', $columns)) {
        $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN category_id mediumint(9) AFTER group_id");
    }

    // Categories table upgrade (v1.10.0)
    hbc_upgrade_categories_table();

    // Multi-room upgrade: Create booking_rooms junction table and migrate data (v1.6.0)
    hbc_upgrade_multi_room_support();

    // Security upgrade: Hash plain-text passwords (v1.4.0 upgrade)
    hbc_upgrade_password_security();
}

/**
 * Upgrade to multi-room booking support
 *
 * Creates the booking_rooms junction table if needed and migrates
 * existing single-room bookings into the junction table.
 *
 * @since 1.6.0
 * @return void
 */
function hbc_upgrade_multi_room_support() {
    global $wpdb;

    // Check if migration has already been done
    if (get_option('hbc_multi_room_migrated', false)) {
        return;
    }

    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $charset_collate = $wpdb->get_charset_collate();

    // Create the junction table if it doesn't exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$booking_rooms_table'") === $booking_rooms_table;
    if (!$table_exists) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE IF NOT EXISTS $booking_rooms_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) NOT NULL,
            room_id mediumint(9) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY booking_room (booking_id, room_id),
            KEY booking_id (booking_id),
            KEY room_id (room_id)
        ) $charset_collate;";
        dbDelta($sql);
    }

    // Migrate existing bookings: copy room_id into junction table
    $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $booking_rooms_table");
    if ($existing_count == 0) {
        $wpdb->query(
            "INSERT IGNORE INTO $booking_rooms_table (booking_id, room_id)
             SELECT id, room_id FROM $bookings_table WHERE room_id IS NOT NULL AND room_id > 0"
        );
    }

    update_option('hbc_multi_room_migrated', true);
}

/**
 * Upgrade password security by hashing plain-text passwords
 *
 * Migrates plain-text booking passwords to hashed format.
 * Only runs once per installation using a flag in options.
 *
 * @since 1.4.0
 * @return void
 */
function hbc_upgrade_password_security() {
    // Check if upgrade has already been done
    if (get_option('hbc_password_hashed', false)) {
        return;
    }

    // Get the current password
    $password = get_option('hbc_booking_password', '');

    // If password exists and doesn't look like a hash (hashes are 60+ chars starting with $)
    if (!empty($password) && strlen($password) < 60 && substr($password, 0, 1) !== '$') {
        // Hash the plain-text password
        $hashed_password = wp_hash_password($password);
        update_option('hbc_booking_password', $hashed_password);
    }

    // Set flag to prevent running this upgrade again
    update_option('hbc_password_hashed', true);
}

/**
 * Upgrade to add categories table for existing installations
 *
 * @since 1.10.0
 * @return void
 */
function hbc_upgrade_categories_table() {
    global $wpdb;

    $categories_table = $wpdb->prefix . 'hbc_categories';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'") === $categories_table;

    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE IF NOT EXISTS $categories_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql);

        // Insert default categories
        $wpdb->insert($categories_table, array('name' => 'Rehearsal', 'description' => 'Music, drama, or dance rehearsals', 'status' => 'active'));
        $wpdb->insert($categories_table, array('name' => 'Social Event', 'description' => 'Parties, gatherings, and social occasions', 'status' => 'active'));
        $wpdb->insert($categories_table, array('name' => 'Meeting', 'description' => 'Business or committee meetings', 'status' => 'active'));
        $wpdb->insert($categories_table, array('name' => 'Class / Workshop', 'description' => 'Educational classes and workshops', 'status' => 'active'));
        $wpdb->insert($categories_table, array('name' => 'Private Hire', 'description' => 'Private bookings and functions', 'status' => 'active'));
    }
}

add_action('plugins_loaded', 'hbc_check_database_upgrade');

/**
 * Plugin deactivation hook
 *
 * Reserved for future cleanup tasks if needed.
 * Currently doesn't remove tables or data to preserve user data.
 *
 * @since 1.0.0
 * @return void
 */
function hbc_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'hbc_deactivate');

/**
 * Load plugin textdomain for translations
 *
 * Loads translation files from the /languages directory.
 *
 * @since 1.0.0
 * @return void
 */
function hbc_load_textdomain() {
    load_plugin_textdomain('hall-booking-calendar', false, dirname(HBC_PLUGIN_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'hbc_load_textdomain');

/**
 * Check if Elementor is active and register widget
 *
 * @since 1.4.0
 * @return void
 */
function hbc_register_elementor_widget() {
    // Check if Elementor is installed and activated
    if (!did_action('elementor/loaded')) {
        return;
    }

    // Register the widget
    add_action('elementor/widgets/register', 'hbc_register_elementor_widget_class');

    // Enqueue widget scripts
    add_action('elementor/frontend/after_enqueue_scripts', 'hbc_enqueue_elementor_scripts');
}
add_action('plugins_loaded', 'hbc_register_elementor_widget');

/**
 * Register Elementor widget class
 *
 * @since 1.4.0
 * @param object $widgets_manager Elementor widgets manager
 * @return void
 */
function hbc_register_elementor_widget_class($widgets_manager) {
    require_once HBC_PLUGIN_DIR . 'includes/elementor-widget.php';

    if (class_exists('HBC_Elementor_Widget')) {
        $widgets_manager->register(new HBC_Elementor_Widget());
    }
}

/**
 * Enqueue Elementor widget scripts
 *
 * Ensures calendar scripts are loaded when widget is used in Elementor.
 *
 * @since 1.4.0
 * @return void
 */
function hbc_enqueue_elementor_scripts() {
    // Scripts are already enqueued by the frontend-calendar.php shortcode handler
    // This function is here for future Elementor-specific script needs
}

/**
 * Register rewrite rules for pretty event URLs
 *
 * Maps /events/YYYY-MM-DD/group-slug/purpose-slug/ to custom query vars
 * so individual bookings can be accessed via SEO-friendly URLs.
 *
 * @since 1.10.0
 * @return void
 */
function hbc_register_event_rewrite_rules() {
    add_rewrite_rule(
        'events/([0-9]{4}-[0-9]{2}-[0-9]{2})/([^/]+)/([^/]+)/?$',
        'index.php?hbc_event_date=$matches[1]&hbc_event_group=$matches[2]&hbc_event_purpose=$matches[3]',
        'top'
    );
}
add_action('init', 'hbc_register_event_rewrite_rules');

/**
 * Register custom query vars for event URLs
 *
 * @since 1.10.0
 * @param array $vars Existing query vars
 * @return array Modified query vars
 */
function hbc_register_event_query_vars($vars) {
    $vars[] = 'hbc_event_date';
    $vars[] = 'hbc_event_group';
    $vars[] = 'hbc_event_purpose';
    return $vars;
}
add_filter('query_vars', 'hbc_register_event_query_vars');

/**
 * Handle event URLs by resolving slug to booking and loading the calendar page
 *
 * @since 1.10.0
 * @param WP_Query $query The main query
 * @return void
 */
function hbc_handle_event_query($query) {
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    $event_date = $query->get('hbc_event_date');
    if (empty($event_date)) {
        return;
    }

    $group_slug = $query->get('hbc_event_group');
    $purpose_slug = $query->get('hbc_event_purpose');

    $booking_id = hbc_resolve_booking_from_slug($event_date, $group_slug, $purpose_slug);

    if ($booking_id) {
        $_GET['booking_id'] = $booking_id;

        // Find the page that contains the calendar shortcode
        $page_id = hbc_find_calendar_page_id();
        if ($page_id) {
            $query->set('page_id', $page_id);
            $query->is_page = true;
            $query->is_singular = true;
            $query->is_home = false;
            $query->is_archive = false;
        } else {
            $query->set_404();
        }
    } else {
        $query->set_404();
    }
}
add_action('pre_get_posts', 'hbc_handle_event_query');

/**
 * Prevent WordPress canonical redirect from redirecting event URLs
 *
 * When we serve a page (e.g. the calendar page) at a custom URL like /events/...
 * WordPress detects the URL doesn't match the page's real permalink and tries to
 * redirect. This filter disables that redirect for our custom event URLs.
 *
 * @since 1.10.1
 * @param string $redirect_url The URL WordPress wants to redirect to
 * @return string|false The redirect URL or false to cancel
 */
function hbc_disable_canonical_redirect_for_events($redirect_url) {
    if (get_query_var('hbc_event_date')) {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'hbc_disable_canonical_redirect_for_events');

/**
 * Find the page ID that contains the calendar or booking form shortcode
 *
 * Checks for [hall_booking_calendar] first, then falls back to the booking
 * form page stored in options.
 *
 * @since 1.10.0
 * @return int|false Page ID or false if not found
 */
function hbc_find_calendar_page_id() {
    // Try cached value first
    $cached = get_transient('hbc_calendar_page_id');
    if ($cached) {
        return intval($cached);
    }

    global $wpdb;

    // Look for a page with the calendar shortcode
    $page_id = $wpdb->get_var(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_content LIKE '%[hall_booking_calendar%'
         AND post_status = 'publish' AND post_type = 'page'
         LIMIT 1"
    );

    if (!$page_id) {
        // Fall back to the booking form page
        $page_id = get_option('hbc_booking_page_id', 0);
    }

    if ($page_id) {
        set_transient('hbc_calendar_page_id', $page_id, HOUR_IN_SECONDS);
    }

    return $page_id ? intval($page_id) : false;
}

/**
 * Resolve an event URL slug to a booking ID
 *
 * @since 1.10.0
 * @param string $date_str    Date in YYYY-MM-DD format
 * @param string $group_slug  Slugified group name
 * @param string $purpose_slug Slugified booking purpose
 * @return int Booking ID or 0 if not found
 */
function hbc_resolve_booking_from_slug($date_str, $group_slug, $purpose_slug) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Parse YYYY-MM-DD date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
        return 0;
    }
    $parts = explode('-', $date_str);
    $year = intval($parts[0]);
    $month = intval($parts[1]);
    $day = intval($parts[2]);

    if (!checkdate($month, $day, $year)) {
        return 0;
    }

    $full_date = $date_str;

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.id, b.purpose, g.name as group_name
         FROM $bookings_table b
         LEFT JOIN $groups_table g ON b.group_id = g.id
         WHERE b.booking_date = %s AND b.status != 'cancelled'
         ORDER BY b.start_time ASC",
        $full_date
    ));

    foreach ($bookings as $booking) {
        $b_group_slug = sanitize_title($booking->group_name ?: 'general');
        $b_purpose_slug = sanitize_title($booking->purpose ?: 'booking');

        if ($b_group_slug === $group_slug && $b_purpose_slug === $purpose_slug) {
            return intval($booking->id);
        }
    }

    return 0;
}

/**
 * Generate a pretty event URL for a booking
 *
 * @since 1.10.0
 * @param object $booking Booking object with booking_date, group_name, and purpose
 * @return string The event URL
 */
function hbc_get_event_url($booking) {
    $date_part = date('Y-m-d', strtotime($booking->booking_date));
    $group_name = isset($booking->group_name) ? $booking->group_name : '';
    $group_slug = sanitize_title($group_name ?: 'general');
    $purpose_slug = sanitize_title($booking->purpose ?: 'booking');

    return home_url("events/{$date_part}/{$group_slug}/{$purpose_slug}/");
}

/**
 * Get the URL of the page containing the calendar shortcode
 *
 * @since 1.10.0
 * @return string Calendar page URL or home URL as fallback
 */
function hbc_get_calendar_page_url() {
    $page_id = hbc_find_calendar_page_id();
    if ($page_id) {
        return get_permalink($page_id);
    }
    return home_url('/');
}

// Include admin functions
require_once HBC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-settings.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-rooms.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-groups.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-bookings.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-categories.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-bulk-import.php';
require_once HBC_PLUGIN_DIR . 'includes/admin-export.php';
require_once HBC_PLUGIN_DIR . 'includes/recurring-bookings.php';
require_once HBC_PLUGIN_DIR . 'includes/calendar-subscription.php';
require_once HBC_PLUGIN_DIR . 'includes/frontend-calendar.php';
require_once HBC_PLUGIN_DIR . 'includes/booking-handler.php';

/**
 * Enqueue admin styles and scripts
 *
 * Only loads assets on Hall Booking admin pages to avoid conflicts.
 *
 * @since 1.0.0
 * @param string $hook The current admin page hook
 * @return void
 */
function hbc_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'hall-booking') === false) {
        return;
    }

    wp_enqueue_style('hbc-admin-style', HBC_PLUGIN_URL . 'assets/css/admin-style.css', array(), HBC_VERSION);
    wp_enqueue_script('hbc-admin-script', HBC_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), HBC_VERSION, true);
}
add_action('admin_enqueue_scripts', 'hbc_admin_enqueue_scripts');

/**
 * Enqueue frontend styles and scripts
 *
 * Loads on all frontend pages. Includes AJAX configuration for booking submissions.
 *
 * @since 1.0.0
 * @return void
 */
function hbc_frontend_enqueue_scripts() {
    wp_enqueue_style('hbc-frontend-style', HBC_PLUGIN_URL . 'assets/css/frontend-style.css', array(), HBC_VERSION);
    wp_enqueue_script('hbc-frontend-script', HBC_PLUGIN_URL . 'assets/js/frontend-script.js', array('jquery'), HBC_VERSION, true);

    // Localize script for AJAX - provides URL and nonce for secure AJAX requests
    wp_localize_script('hbc-frontend-script', 'hbc_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hbc_booking_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'hbc_frontend_enqueue_scripts');
