<?php
/**
 * Plugin Name: Hall Booking Calendar
 * Plugin URI: https://github.com/mike-richardson-uk/hall-booking-calendar
 * Description: A WordPress plugin to manage a hall calendar with 3 rooms, recurring bookings, and calendar subscriptions.
 * Version: 1.20.0
 * Author: Mike Richardson
 * Author URI: https://github.com/mike-richardson-uk/hall-booking-calendar
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
define('HBC_VERSION', '1.20.0');
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

    // Create booking-in form configuration table
    $booking_forms_table = $wpdb->prefix . 'hbc_booking_forms';
    $sql_booking_forms = "CREATE TABLE IF NOT EXISTS $booking_forms_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        booking_id mediumint(9) NOT NULL,
        enabled tinyint(1) DEFAULT 1,
        submission_emails text,
        include_meal_menu tinyint(1) DEFAULT 0,
        meal_options text,
        payment_bank_name varchar(255),
        payment_sort_code varchar(50),
        payment_account_number varchar(50),
        payment_reference_prefix varchar(100),
        payment_cheque_payable varchar(255),
        payment_deadline date,
        book_in_token varchar(12),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY booking_id (booking_id),
        UNIQUE KEY book_in_token (book_in_token)
    ) $charset_collate;";

    // Create member booking-in submissions table
    $form_submissions_table = $wpdb->prefix . 'hbc_form_submissions';
    $sql_form_submissions = "CREATE TABLE IF NOT EXISTS $form_submissions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        booking_id mediumint(9) NOT NULL,
        full_name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(100),
        masonic_rank varchar(100),
        attendance_type varchar(50),
        membership_type varchar(50),
        lodge_name varchar(255),
        meal_choice varchar(255),
        vegetarian_alternative tinyint(1) DEFAULT 0,
        dietary_requirements text,
        additional_comments text,
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY booking_id (booking_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_rooms);
    dbDelta($sql_groups);
    dbDelta($sql_bookings);
    dbDelta($sql_booking_rooms);
    dbDelta($sql_categories);
    dbDelta($sql_subscriptions);
    dbDelta($sql_booking_forms);
    dbDelta($sql_form_submissions);

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

    // Booking-in form tables (v1.14.0)
    hbc_upgrade_book_in_tables();

    // Cancellation token column (v1.18.0)
    hbc_upgrade_cancellation_token();

    // Edit token column (v1.19.0)
    hbc_upgrade_edit_token();
}

/**
 * Add edit_token column to hbc_bookings if missing
 *
 * Enables bookers to edit their own bookings via a signed URL included
 * in the confirmation email — no WordPress login required.
 *
 * @since 1.19.0
 * @return void
 */
function hbc_upgrade_edit_token() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $columns = $wpdb->get_col("DESCRIBE $bookings_table", 0);
    if (!in_array('edit_token', $columns)) {
        $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN edit_token varchar(64) NULL UNIQUE AFTER cancellation_token");
    }
}

/**
 * Add cancellation_token column to hbc_bookings if missing
 *
 * Enables bookers to cancel their own confirmed or pending bookings via
 * a signed URL included in the confirmation email.
 *
 * @since 1.18.0
 * @return void
 */
function hbc_upgrade_cancellation_token() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $columns = $wpdb->get_col("DESCRIBE $bookings_table", 0);
    if (!in_array('cancellation_token', $columns)) {
        $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN cancellation_token varchar(64) NULL UNIQUE AFTER status");
    }
}

/**
 * Create booking-in form tables if they don't yet exist
 *
 * @since 1.14.0
 * @return void
 */
function hbc_upgrade_book_in_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $booking_forms_table    = $wpdb->prefix . 'hbc_booking_forms';
    $form_submissions_table = $wpdb->prefix . 'hbc_form_submissions';

    $forms_exists       = $wpdb->get_var("SHOW TABLES LIKE '$booking_forms_table'") === $booking_forms_table;
    $submissions_exists = $wpdb->get_var("SHOW TABLES LIKE '$form_submissions_table'") === $form_submissions_table;

    if (!$forms_exists || !$submissions_exists) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        if (!$forms_exists) {
            $sql_booking_forms = "CREATE TABLE IF NOT EXISTS $booking_forms_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                booking_id mediumint(9) NOT NULL,
                enabled tinyint(1) DEFAULT 1,
                submission_emails text,
                include_meal_menu tinyint(1) DEFAULT 0,
                meal_options text,
                payment_bank_name varchar(255),
                payment_sort_code varchar(50),
                payment_account_number varchar(50),
                payment_reference_prefix varchar(100),
                payment_cheque_payable varchar(255),
                payment_deadline date,
                book_in_token varchar(12),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY booking_id (booking_id),
                UNIQUE KEY book_in_token (book_in_token)
            ) $charset_collate;";
            dbDelta($sql_booking_forms);
        }

        if (!$submissions_exists) {
            $sql_form_submissions = "CREATE TABLE IF NOT EXISTS $form_submissions_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                booking_id mediumint(9) NOT NULL,
                full_name varchar(255) NOT NULL,
                email varchar(255) NOT NULL,
                phone varchar(100),
                masonic_rank varchar(100),
                attendance_type varchar(50),
                membership_type varchar(50),
                lodge_name varchar(255),
                meal_choice varchar(255),
                vegetarian_alternative tinyint(1) DEFAULT 0,
                dietary_requirements text,
                additional_comments text,
                submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY booking_id (booking_id)
            ) $charset_collate;";
            dbDelta($sql_form_submissions);
        }
    }

    // Add book_in_token column to existing tables that pre-date v1.15.0
    if ($forms_exists) {
        $columns = $wpdb->get_col("DESCRIBE $booking_forms_table", 0);
        if (!in_array('book_in_token', $columns)) {
            $wpdb->query("ALTER TABLE $booking_forms_table ADD COLUMN book_in_token varchar(12) NULL UNIQUE AFTER payment_deadline");
        }
    }

    // Add vegetarian_alternative column to existing tables that pre-date v1.17.0
    if ($submissions_exists) {
        $sub_columns = $wpdb->get_col("DESCRIBE $form_submissions_table", 0);
        if (!in_array('vegetarian_alternative', $sub_columns)) {
            $wpdb->query("ALTER TABLE $form_submissions_table ADD COLUMN vegetarian_alternative tinyint(1) DEFAULT 0 AFTER meal_choice");
        }
    }
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
 * Allowed HTML tags for meal option descriptions (simple formatting)
 *
 * @since 1.20.0
 * @return array Allowed tags for wp_kses
 */
function hbc_allowed_meal_description_tags() {
    return array(
        'b'      => array(),
        'strong' => array(),
        'i'      => array(),
        'em'     => array(),
        'u'      => array(),
        'br'     => array(),
    );
}

/**
 * Sanitize meal description HTML for storage (bold, italic, underline, br only)
 *
 * @since 1.20.0
 * @param string $html Raw description from form
 * @return string Sanitized HTML
 */
function hbc_sanitize_meal_description_html($html) {
    return wp_kses($html, hbc_allowed_meal_description_tags());
}

/**
 * Get wp_editor settings for meal description (WYSIWYG - bold, italic, underline only)
 *
 * @since 1.20.0
 * @return array Settings for wp_editor / wp.editor.initialize
 */
function hbc_get_meal_editor_settings() {
    return array(
        'teeny'         => true,
        'media_buttons' => false,
        'textarea_rows' => 4,
        'textarea_name' => 'hbc_meal_desc_temp',
        'quicktags'     => false,
        'tinymce'       => array(
            'toolbar1' => 'bold,italic,underline',
            'toolbar2' => '',
            'plugins'  => '',
        ),
        'editor_class'  => 'hbc-meal-editor',
    );
}

/**
 * Restrict teeny editor buttons to bold, italic, underline for meal editors
 *
 * @since 1.20.0
 * @param array  $buttons   Button list
 * @param string $editor_id Editor ID
 * @return array Filtered buttons
 */
function hbc_meal_editor_teeny_buttons($buttons, $editor_id) {
    if (strpos($editor_id, 'hbc_admin_meal_desc_') === 0 || strpos($editor_id, 'hbc_meal_desc_') === 0) {
        return array('bold', 'italic', 'underline');
    }
    return $buttons;
}
add_filter('teeny_mce_buttons', 'hbc_meal_editor_teeny_buttons', 10, 2);

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
 * Maps /events/group-slug/YYYY-MM-DD/purpose-slug/ to custom query vars
 * so individual bookings can be accessed via SEO-friendly URLs.
 * Also maps /calendar/group-slug/ to a group agenda view.
 *
 * @since 1.10.0
 * @return void
 */
function hbc_register_event_rewrite_rules() {
    // Single event: /events/group-slug/YYYY-MM-DD/purpose-slug/
    add_rewrite_rule(
        'events/([^/]+)/([0-9]{4}-[0-9]{2}-[0-9]{2})/([^/]+)/?$',
        'index.php?hbc_event_group=$matches[1]&hbc_event_date=$matches[2]&hbc_event_purpose=$matches[3]',
        'top'
    );

    // Group agenda: /calendar/group-slug/
    add_rewrite_rule(
        'calendar/([^/]+)/?$',
        'index.php?hbc_group_agenda=$matches[1]',
        'top'
    );

    // Short booking-in URL: /book/{token}/
    add_rewrite_rule(
        'book/([^/]+)/?$',
        'index.php?hbc_book_in_token=$matches[1]',
        'top'
    );

    // Booking cancellation URL: /cancel-booking/{token}/
    add_rewrite_rule(
        'cancel-booking/([^/]+)/?$',
        'index.php?hbc_cancel_token=$matches[1]',
        'top'
    );

    // Booking self-edit URL: /edit-booking/{token}/
    add_rewrite_rule(
        'edit-booking/([^/]+)/?$',
        'index.php?hbc_edit_token=$matches[1]',
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
    $vars[] = 'hbc_group_agenda';
    $vars[] = 'hbc_book_in_token';
    $vars[] = 'hbc_cancel_token';
    $vars[] = 'hbc_edit_token';
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
 * Handle group agenda URLs (/calendar/group-slug/)
 *
 * Resolves the group slug to a group ID, then loads the calendar page
 * with the agenda view filtered to that group.
 *
 * @since 1.11.0
 * @param WP_Query $query The main query
 * @return void
 */
function hbc_handle_group_agenda_query($query) {
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    $group_slug = $query->get('hbc_group_agenda');
    if (empty($group_slug)) {
        return;
    }

    global $wpdb;
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Find group by matching sanitize_title(name) against the slug
    $groups = $wpdb->get_results("SELECT id, name FROM $groups_table WHERE status = 'active'");
    $group_id = 0;
    $group_name = '';
    foreach ($groups as $group) {
        if (sanitize_title($group->name) === $group_slug) {
            $group_id = intval($group->id);
            $group_name = $group->name;
            break;
        }
    }

    if (!$group_id) {
        $query->set_404();
        return;
    }

    // Set query params so the shortcode renders the agenda for this group
    $_GET['view'] = 'agenda';
    $_GET['group_filter'] = $group_id;
    $_GET['hbc_group_name'] = $group_name;

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
}
add_action('pre_get_posts', 'hbc_handle_group_agenda_query');

/**
 * Handle short booking-in URLs (/book/{token}/)
 *
 * Resolves the token stored in hbc_booking_forms to the corresponding
 * booking ID, then loads the calendar page with the booking-in form displayed.
 *
 * @since 1.15.0
 * @param WP_Query $query The main query
 * @return void
 */
function hbc_handle_book_in_token_query($query) {
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    $token = $query->get('hbc_book_in_token');
    if (empty($token)) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'hbc_booking_forms';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        $query->set_404();
        return;
    }

    $booking_id = $wpdb->get_var($wpdb->prepare(
        "SELECT booking_id FROM $table WHERE book_in_token = %s AND enabled = 1",
        sanitize_text_field(wp_unslash($token))
    ));

    if (!$booking_id) {
        $query->set_404();
        return;
    }

    $_GET['hbc_book_in'] = '1';
    $_GET['booking_id']  = intval($booking_id);

    $page_id = hbc_find_calendar_page_id();
    if ($page_id) {
        $query->set('page_id', $page_id);
        $query->is_page     = true;
        $query->is_singular = true;
        $query->is_home     = false;
        $query->is_archive  = false;
    } else {
        $query->set_404();
    }
}
add_action('pre_get_posts', 'hbc_handle_book_in_token_query');

/**
 * Render the booking cancellation confirmation page
 *
 * Intercepts /cancel-booking/{token}/ URLs, looks up the booking, and either
 * renders a confirmation form (GET) or processes the cancellation (POST).
 * Uses the active theme's header and footer so it inherits the site design.
 *
 * @since 1.18.0
 * @return void
 */
/**
 * Handle self-service booking edit pages (/edit-booking/{token}/)
 *
 * Renders a simple, user-friendly form that lets a booker update their
 * booking's purpose, description, date, time, and contact details without
 * needing to log into WordPress.
 *
 * @since 1.19.0
 * @return void
 */
function hbc_handle_edit_booking_page() {
    $token = get_query_var('hbc_edit_token');
    if (empty($token)) {
        return;
    }

    global $wpdb;
    $bookings_table      = $wpdb->prefix . 'hbc_bookings';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';
    $rooms_table         = $wpdb->prefix . 'hbc_rooms';

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, g.name as group_name
         FROM $bookings_table b
         LEFT JOIN {$wpdb->prefix}hbc_groups g ON b.group_id = g.id
         WHERE b.edit_token = %s",
        sanitize_text_field($token)
    ));

    $saved   = false;
    $errors  = array();

    if (!$booking) {
        $errors[] = __('This link is invalid or has expired.', 'hall-booking-calendar');
    } elseif ('cancelled' === $booking->status) {
        $errors[] = __('This booking has been cancelled and can no longer be edited.', 'hall-booking-calendar');
    }

    // Get room names for this booking
    $room_names = array();
    if ($booking) {
        $room_names = $wpdb->get_col($wpdb->prepare(
            "SELECT r.name FROM $booking_rooms_table br
             INNER JOIN $rooms_table r ON br.room_id = r.id
             WHERE br.booking_id = %d ORDER BY r.name ASC",
            $booking->id
        ));
        if (empty($room_names) && !empty($booking->room_id)) {
            $room_names = array($wpdb->get_var($wpdb->prepare("SELECT name FROM $rooms_table WHERE id = %d", $booking->room_id)));
        }
    }

    // Handle POST
    $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    if (empty($errors) && 'post' === $request_method && isset($_POST['hbc_edit_booking_submit'])) {
        if (!isset($_POST['hbc_edit_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hbc_edit_nonce'])), 'hbc_edit_booking_' . $token)) {
            $errors[] = __('Security check failed. Please try again.', 'hall-booking-calendar');
        } else {
            $new_purpose     = sanitize_text_field(wp_unslash($_POST['purpose'] ?? ''));
            $new_description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
            $new_date        = sanitize_text_field(wp_unslash($_POST['booking_date'] ?? ''));
            $new_start       = sanitize_text_field(wp_unslash($_POST['start_time'] ?? ''));
            $new_end         = sanitize_text_field(wp_unslash($_POST['end_time'] ?? ''));
            $new_name        = sanitize_text_field(wp_unslash($_POST['user_name'] ?? ''));
            $new_email       = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));

            // Basic validation
            if (empty($new_purpose)) {
                $errors[] = __('Please enter an event name.', 'hall-booking-calendar');
            }
            if (empty($new_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
                $errors[] = __('Please enter a valid date.', 'hall-booking-calendar');
            }
            if (empty($new_start) || empty($new_end)) {
                $errors[] = __('Please enter start and end times.', 'hall-booking-calendar');
            } elseif ($new_start >= $new_end) {
                $errors[] = __('End time must be after start time.', 'hall-booking-calendar');
            }

            // Conflict check for each room if date or time changed
            if (empty($errors)) {
                $date_time_changed = ($new_date !== $booking->booking_date || $new_start !== $booking->start_time || $new_end !== $booking->end_time);
                if ($date_time_changed) {
                    $booking_room_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT room_id FROM $booking_rooms_table WHERE booking_id = %d",
                        $booking->id
                    ));
                    if (empty($booking_room_ids) && !empty($booking->room_id)) {
                        $booking_room_ids = array($booking->room_id);
                    }
                    $conflicting = array();
                    foreach ($booking_room_ids as $rid) {
                        if (hbc_check_booking_conflict(intval($rid), $new_date, $new_start, $new_end, $booking->id)) {
                            $rname = $wpdb->get_var($wpdb->prepare("SELECT name FROM $rooms_table WHERE id = %d", $rid));
                            $conflicting[] = $rname ? $rname : __('a room', 'hall-booking-calendar');
                        }
                    }
                    if (!empty($conflicting)) {
                        $errors[] = sprintf(
                            __('The new date/time conflicts with an existing booking in: %s. Please choose a different time.', 'hall-booking-calendar'),
                            implode(', ', $conflicting)
                        );
                    }
                }
            }

            if (empty($errors)) {
                $old_details = sprintf(
                    __("Purpose: %s\nDate: %s\nTime: %s – %s", 'hall-booking-calendar'),
                    $booking->purpose,
                    date('F j, Y', strtotime($booking->booking_date)),
                    date('g:i A', strtotime($booking->start_time)),
                    date('g:i A', strtotime($booking->end_time))
                );

                $wpdb->update(
                    $bookings_table,
                    array(
                        'purpose'      => $new_purpose,
                        'description'  => $new_description,
                        'booking_date' => $new_date,
                        'start_time'   => $new_start,
                        'end_time'     => $new_end,
                        'user_name'    => $new_name,
                        'user_email'   => $new_email,
                    ),
                    array('id' => $booking->id)
                );

                // Notify webmaster
                $webmaster_email = sanitize_email(get_option('hbc_webmaster_email', get_option('admin_email')));
                $safe_name       = str_replace(array("\r", "\n"), '', $new_name);
                wp_mail(
                    $webmaster_email,
                    sprintf(__('Booking Updated by User: %s — Hall Booking Calendar', 'hall-booking-calendar'), $safe_name),
                    sprintf(
                        __("A booking has been updated by the original booker.\n\nUser: %s (%s)\n\nPrevious details:\n%s\n\nNew details:\nPurpose: %s\nDate: %s\nTime: %s – %s\n\nAdmin: %s", 'hall-booking-calendar'),
                        $safe_name,
                        sanitize_email($new_email),
                        $old_details,
                        $new_purpose,
                        date('F j, Y', strtotime($new_date)),
                        date('g:i A', strtotime($new_start)),
                        date('g:i A', strtotime($new_end)),
                        admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking->id)
                    )
                );

                // Refresh booking object for display
                $booking->purpose      = $new_purpose;
                $booking->description  = $new_description;
                $booking->booking_date = $new_date;
                $booking->start_time   = $new_start;
                $booking->end_time     = $new_end;
                $booking->user_name    = $new_name;
                $booking->user_email   = $new_email;

                $saved = true;
            }
        }
    }

    // Render using active theme
    get_header();
    ?>
    <div class="hbc-edit-page" style="max-width:680px;margin:40px auto;padding:0 20px;">
        <h1><?php esc_html_e('Update Your Booking', 'hall-booking-calendar'); ?></h1>

        <?php if (!empty($errors)) : ?>
            <div class="hbc-edit-notice hbc-edit-notice--error">
                <?php foreach ($errors as $err) : ?>
                    <p><?php echo esc_html($err); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($saved) : ?>
            <div class="hbc-edit-notice hbc-edit-notice--success">
                <p><strong><?php esc_html_e('Your booking has been updated successfully.', 'hall-booking-calendar'); ?></strong></p>
                <p><?php esc_html_e('The venue administrator has been notified of the changes.', 'hall-booking-calendar'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($booking && 'cancelled' !== $booking->status) : ?>

        <?php if (!$saved) : ?>
        <p><?php esc_html_e('Use this form to update your booking details. If you need to change the room, please contact us directly.', 'hall-booking-calendar'); ?></p>
        <?php endif; ?>

        <div class="hbc-edit-summary">
            <p><strong><?php esc_html_e('Room(s):', 'hall-booking-calendar'); ?></strong>
                <?php echo esc_html(!empty($room_names) ? implode(', ', $room_names) : __('(unknown)', 'hall-booking-calendar')); ?>
            </p>
            <?php if (!empty($booking->group_name)) : ?>
            <p><strong><?php esc_html_e('Group:', 'hall-booking-calendar'); ?></strong>
                <?php echo esc_html($booking->group_name); ?>
            </p>
            <?php endif; ?>
            <p><strong><?php esc_html_e('Status:', 'hall-booking-calendar'); ?></strong>
                <?php echo esc_html(ucfirst($booking->status)); ?>
            </p>
        </div>

        <form method="post" class="hbc-edit-form">
            <?php wp_nonce_field('hbc_edit_booking_' . $token, 'hbc_edit_nonce'); ?>

            <div class="hbc-edit-field">
                <label for="hbc-edit-purpose"><strong><?php esc_html_e('Event Name', 'hall-booking-calendar'); ?></strong></label>
                <input type="text" id="hbc-edit-purpose" name="purpose" value="<?php echo esc_attr($booking->purpose); ?>" required>
            </div>

            <div class="hbc-edit-field">
                <label for="hbc-edit-description"><strong><?php esc_html_e('Additional Notes', 'hall-booking-calendar'); ?></strong></label>
                <textarea id="hbc-edit-description" name="description" rows="4"><?php echo esc_textarea($booking->description); ?></textarea>
            </div>

            <div class="hbc-edit-row">
                <div class="hbc-edit-field">
                    <label for="hbc-edit-date"><strong><?php esc_html_e('Date', 'hall-booking-calendar'); ?></strong></label>
                    <input type="date" id="hbc-edit-date" name="booking_date" value="<?php echo esc_attr($booking->booking_date); ?>" required>
                </div>
                <div class="hbc-edit-field">
                    <label for="hbc-edit-start"><strong><?php esc_html_e('Start Time', 'hall-booking-calendar'); ?></strong></label>
                    <input type="time" id="hbc-edit-start" name="start_time" value="<?php echo esc_attr($booking->start_time); ?>" required>
                </div>
                <div class="hbc-edit-field">
                    <label for="hbc-edit-end"><strong><?php esc_html_e('End Time', 'hall-booking-calendar'); ?></strong></label>
                    <input type="time" id="hbc-edit-end" name="end_time" value="<?php echo esc_attr($booking->end_time); ?>" required>
                </div>
            </div>

            <div class="hbc-edit-row">
                <div class="hbc-edit-field">
                    <label for="hbc-edit-name"><strong><?php esc_html_e('Your Name', 'hall-booking-calendar'); ?></strong></label>
                    <input type="text" id="hbc-edit-name" name="user_name" value="<?php echo esc_attr($booking->user_name); ?>" required>
                </div>
                <div class="hbc-edit-field">
                    <label for="hbc-edit-email"><strong><?php esc_html_e('Your Email', 'hall-booking-calendar'); ?></strong></label>
                    <input type="email" id="hbc-edit-email" name="user_email" value="<?php echo esc_attr($booking->user_email); ?>" required>
                </div>
            </div>

            <div class="hbc-edit-actions">
                <button type="submit" name="hbc_edit_booking_submit" value="1" class="hbc-edit-submit-btn">
                    <?php esc_html_e('Save Changes', 'hall-booking-calendar'); ?>
                </button>
                <?php
                $cancel_token_val = $wpdb->get_var($wpdb->prepare("SELECT cancellation_token FROM $bookings_table WHERE id = %d", $booking->id));
                if (!empty($cancel_token_val)) :
                ?>
                <a href="<?php echo esc_url(home_url('cancel-booking/' . $cancel_token_val . '/')); ?>" class="hbc-edit-cancel-link">
                    <?php esc_html_e('Cancel this booking instead', 'hall-booking-calendar'); ?>
                </a>
                <?php endif; ?>
            </div>
        </form>

        <?php endif; ?>
    </div>
    <?php
    get_footer();
    exit;
}
add_action('template_redirect', 'hbc_handle_edit_booking_page');

function hbc_handle_cancellation_page() {
    $token = get_query_var('hbc_cancel_token');
    if (empty($token)) {
        return;
    }

    global $wpdb;
    $bookings_table      = $wpdb->prefix . 'hbc_bookings';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';
    $rooms_table         = $wpdb->prefix . 'hbc_rooms';

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, g.name as group_name
         FROM $bookings_table b
         LEFT JOIN {$wpdb->prefix}hbc_groups g ON b.group_id = g.id
         WHERE b.cancellation_token = %s",
        sanitize_text_field($token)
    ));

    $cancelled   = false;
    $error       = '';
    $already_done = false;

    if (!$booking) {
        $error = __('This cancellation link is invalid or has already been used.', 'hall-booking-calendar');
    } elseif ('cancelled' === $booking->status) {
        $already_done = true;
    } elseif ('confirmed' !== $booking->status && 'pending' !== $booking->status) {
        $error = __('This booking cannot be cancelled.', 'hall-booking-calendar');
    }

    // Handle POST confirmation
    $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    if (!$error && !$already_done && 'post' === $request_method && isset($_POST['hbc_confirm_cancel'])) {
        if (!isset($_POST['hbc_cancel_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hbc_cancel_nonce'])), 'hbc_cancel_booking_' . $token)) {
            $error = __('Security check failed. Please try again.', 'hall-booking-calendar');
        } else {
            $wpdb->update(
                $bookings_table,
                array('status' => 'cancelled'),
                array('id' => $booking->id)
            );
            $cancelled = true;

            // Notify the webmaster
            $webmaster_email = sanitize_email(get_option('hbc_webmaster_email', get_option('admin_email')));
            $safe_name       = str_replace(array("\r", "\n"), '', $booking->user_name);
            wp_mail(
                $webmaster_email,
                sprintf(__('Booking Cancelled by User: %s — Hall Booking Calendar', 'hall-booking-calendar'), $safe_name),
                sprintf(
                    __("The following booking has been cancelled by the user:\n\nUser: %s (%s)\nPurpose: %s\nDate: %s\nTime: %s – %s\n\nAdmin: %s", 'hall-booking-calendar'),
                    $safe_name,
                    sanitize_email($booking->user_email),
                    $booking->purpose,
                    date('F j, Y', strtotime($booking->booking_date)),
                    date('g:i A', strtotime($booking->start_time)),
                    date('g:i A', strtotime($booking->end_time)),
                    admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking->id)
                )
            );
        }
    }

    // Get room names for display
    $room_names = array();
    if ($booking) {
        $room_names = $wpdb->get_col($wpdb->prepare(
            "SELECT r.name FROM $booking_rooms_table br
             INNER JOIN $rooms_table r ON br.room_id = r.id
             WHERE br.booking_id = %d ORDER BY r.name ASC",
            $booking->id
        ));
        if (empty($room_names) && !empty($booking->room_id)) {
            $room_names = array($wpdb->get_var($wpdb->prepare("SELECT name FROM $rooms_table WHERE id = %d", $booking->room_id)));
        }
    }

    // Render the page using the active theme
    get_header();
    ?>
    <div class="hbc-cancel-page" style="max-width:680px;margin:40px auto;padding:0 20px;">
        <h1><?php esc_html_e('Cancel Booking', 'hall-booking-calendar'); ?></h1>

        <?php if ($error) : ?>
            <div class="hbc-cancel-notice hbc-cancel-notice--error">
                <p><?php echo esc_html($error); ?></p>
            </div>

        <?php elseif ($already_done) : ?>
            <div class="hbc-cancel-notice hbc-cancel-notice--info">
                <p><?php esc_html_e('This booking has already been cancelled.', 'hall-booking-calendar'); ?></p>
            </div>

        <?php elseif ($cancelled) : ?>
            <div class="hbc-cancel-notice hbc-cancel-notice--success">
                <h2><?php esc_html_e('Booking Cancelled', 'hall-booking-calendar'); ?></h2>
                <p><?php esc_html_e('Your booking has been cancelled successfully. The venue administrator has been notified.', 'hall-booking-calendar'); ?></p>
            </div>

        <?php else : ?>
            <div class="hbc-cancel-details">
                <h2><?php esc_html_e('Are you sure you want to cancel this booking?', 'hall-booking-calendar'); ?></h2>
                <table class="hbc-cancel-table">
                    <tr><th><?php esc_html_e('Purpose', 'hall-booking-calendar'); ?></th><td><?php echo esc_html($booking->purpose); ?></td></tr>
                    <tr><th><?php esc_html_e('Date', 'hall-booking-calendar'); ?></th><td><?php echo esc_html(date('l, F j, Y', strtotime($booking->booking_date))); ?></td></tr>
                    <tr><th><?php esc_html_e('Time', 'hall-booking-calendar'); ?></th><td><?php echo esc_html(date('g:i A', strtotime($booking->start_time)) . ' – ' . date('g:i A', strtotime($booking->end_time))); ?></td></tr>
                    <?php if (!empty($room_names)) : ?>
                    <tr><th><?php esc_html_e('Room(s)', 'hall-booking-calendar'); ?></th><td><?php echo esc_html(implode(', ', $room_names)); ?></td></tr>
                    <?php endif; ?>
                    <tr><th><?php esc_html_e('Status', 'hall-booking-calendar'); ?></th><td><?php echo esc_html(ucfirst($booking->status)); ?></td></tr>
                </table>

                <form method="post" style="margin-top:24px;">
                    <?php wp_nonce_field('hbc_cancel_booking_' . $token, 'hbc_cancel_nonce'); ?>
                    <button type="submit" name="hbc_confirm_cancel" value="1" class="hbc-cancel-confirm-btn">
                        <?php esc_html_e('Yes, Cancel My Booking', 'hall-booking-calendar'); ?>
                    </button>
                    <a href="<?php echo esc_url(hbc_get_calendar_page_url()); ?>" class="hbc-cancel-back-btn">
                        <?php esc_html_e('No, Keep My Booking', 'hall-booking-calendar'); ?>
                    </a>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
    get_footer();
    exit;
}
add_action('template_redirect', 'hbc_handle_cancellation_page');

/**
 * Prevent WordPress canonical redirect from redirecting custom URLs
 *
 * When we serve a page (e.g. the calendar page) at a custom URL like /events/...
 * or /calendar/group-slug/, WordPress detects the URL doesn't match the page's
 * real permalink and tries to redirect. This filter disables that redirect.
 *
 * @since 1.10.1
 * @param string $redirect_url The URL WordPress wants to redirect to
 * @return string|false The redirect URL or false to cancel
 */
function hbc_disable_canonical_redirect_for_events($redirect_url) {
    if (get_query_var('hbc_event_date') || get_query_var('hbc_group_agenda') || get_query_var('hbc_book_in_token') || get_query_var('hbc_cancel_token') || get_query_var('hbc_edit_token')) {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'hbc_disable_canonical_redirect_for_events');

/**
 * Add body class on event or booking pages (used to hide wp-block-cover)
 *
 * @since 1.20.0
 * @param array $classes Body classes
 * @return array
 */
function hbc_event_booking_body_class($classes) {
    if (is_admin()) {
        return $classes;
    }
    $is_event_or_booking = get_query_var('hbc_event_date')
        || get_query_var('hbc_group_agenda')
        || get_query_var('hbc_book_in_token')
        || get_query_var('hbc_cancel_token')
        || get_query_var('hbc_edit_token');
    if ($is_event_or_booking) {
        $classes[] = 'hbc-event-booking-page';
        return $classes;
    }
    $page_id = hbc_find_calendar_page_id();
    if ($page_id && is_page() && (int) get_queried_object_id() === (int) $page_id) {
        $classes[] = 'hbc-event-booking-page';
        return $classes;
    }
    $queried = get_queried_object();
    if ($queried instanceof WP_Post && $queried->post_name === 'events') {
        $classes[] = 'hbc-event-booking-page';
    }
    return $classes;
}
add_filter('body_class', 'hbc_event_booking_body_class');

/**
 * Apply configured page template for plugin-generated URLs
 *
 * When a visitor accesses /events/... or /calendar/group-slug/, allows the
 * admin-configured theme template to be used instead of the calendar page's
 * own template.
 *
 * @since 1.12.0
 * @param string $template The resolved template file path
 * @return string The template file path to use
 */
function hbc_apply_page_template($template) {
    if (is_admin()) {
        return $template;
    }

    $chosen = '';

    if (get_query_var('hbc_event_date')) {
        $chosen = get_option('hbc_event_page_template', '');
    } elseif (get_query_var('hbc_group_agenda')) {
        $chosen = get_option('hbc_group_agenda_template', '');
    } elseif (get_query_var('hbc_book_in_token')) {
        $chosen = get_option('hbc_book_in_template', '');
    }

    if (!empty($chosen)) {
        $located = locate_template($chosen);
        if ($located) {
            return $located;
        }
    }

    return $template;
}
add_filter('template_include', 'hbc_apply_page_template', 99);

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

    return home_url("events/{$group_slug}/{$date_part}/{$purpose_slug}/");
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

    // Localize script for AJAX and meal editor settings (WYSIWYG)
    wp_localize_script('hbc-frontend-script', 'hbc_ajax', array(
        'ajax_url'            => admin_url('admin-ajax.php'),
        'nonce'               => wp_create_nonce('hbc_booking_nonce'),
        'book_in_nonce'       => wp_create_nonce('hbc_book_in_nonce'),
        'meal_editor_settings' => hbc_get_meal_editor_settings(),
    ));
}
add_action('wp_enqueue_scripts', 'hbc_frontend_enqueue_scripts');
