<?php
/**
 * Admin Menu Functions
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register admin menu and submenus for Hall Booking Calendar
 *
 * Creates a top-level menu with the following submenus:
 * - Dashboard (statistics and recent bookings)
 * - Rooms (manage available rooms)
 * - Groups (organize bookings by category)
 * - Bookings (view and manage all bookings)
 * - Subscriptions (calendar feed management)
 * - Bulk Import (CSV upload for batch booking creation)
 * - Settings (configure password protection and emails)
 *
 * @since 1.0.0
 * @return void
 */
function hbc_add_admin_menu() {
    add_menu_page(
        __('Hall Booking Calendar', 'hall-booking-calendar'),
        __('Hall Booking', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-calendar',
        'hbc_admin_dashboard_page',
        'dashicons-calendar-alt',
        30
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Dashboard', 'hall-booking-calendar'),
        __('Dashboard', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-calendar',
        'hbc_admin_dashboard_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Rooms', 'hall-booking-calendar'),
        __('Rooms', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-rooms',
        'hbc_admin_rooms_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Groups', 'hall-booking-calendar'),
        __('Groups', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-groups',
        'hbc_admin_groups_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Bookings', 'hall-booking-calendar'),
        __('Bookings', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-bookings',
        'hbc_admin_bookings_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Calendar Subscriptions', 'hall-booking-calendar'),
        __('Subscriptions', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-subscriptions',
        'hbc_admin_subscriptions_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Bulk Import', 'hall-booking-calendar'),
        __('Bulk Import', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-bulk-import',
        'hbc_admin_bulk_import_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Settings', 'hall-booking-calendar'),
        __('Settings', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-settings',
        'hbc_admin_settings_page'
    );
}
add_action('admin_menu', 'hbc_add_admin_menu');

/**
 * Display the main dashboard page
 *
 * Shows key statistics:
 * - Active rooms count
 * - Active groups count
 * - Total bookings
 * - Pending bookings requiring approval
 * - Today's bookings
 *
 * Also displays a table of the 10 most recent bookings.
 *
 * @since 1.0.0
 * @return void
 */
function hbc_admin_dashboard_page() {
    global $wpdb;

    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    // Fetch dashboard statistics
    $total_rooms = $wpdb->get_var("SELECT COUNT(*) FROM $rooms_table WHERE status = 'active'");
    $total_groups = $wpdb->get_var("SELECT COUNT(*) FROM $groups_table WHERE status = 'active'");
    $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
    $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status = 'pending'");
    $today_bookings = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s",
        date('Y-m-d')
    ));

    ?>
    <div class="wrap">
        <h1><?php _e('Hall Booking Calendar - Dashboard', 'hall-booking-calendar'); ?></h1>

        <div class="hbc-dashboard-stats">
            <div class="hbc-stat-box">
                <h3><?php echo esc_html($total_rooms); ?></h3>
                <p><?php _e('Active Rooms', 'hall-booking-calendar'); ?></p>
            </div>
            <div class="hbc-stat-box">
                <h3><?php echo esc_html($total_groups); ?></h3>
                <p><?php _e('Active Groups', 'hall-booking-calendar'); ?></p>
            </div>
            <div class="hbc-stat-box">
                <h3><?php echo esc_html($total_bookings); ?></h3>
                <p><?php _e('Total Bookings', 'hall-booking-calendar'); ?></p>
            </div>
            <div class="hbc-stat-box">
                <h3><?php echo esc_html($pending_bookings); ?></h3>
                <p><?php _e('Pending Bookings', 'hall-booking-calendar'); ?></p>
            </div>
            <div class="hbc-stat-box">
                <h3><?php echo esc_html($today_bookings); ?></h3>
                <p><?php _e('Today\'s Bookings', 'hall-booking-calendar'); ?></p>
            </div>
        </div>

        <div class="hbc-recent-bookings">
            <h2><?php _e('Recent Bookings', 'hall-booking-calendar'); ?></h2>
            <?php
            $recent_bookings = $wpdb->get_results(
                "SELECT b.*, r.name as room_name
                FROM $bookings_table b
                LEFT JOIN $rooms_table r ON b.room_id = r.id
                ORDER BY b.created_at DESC
                LIMIT 10"
            );

            if ($recent_bookings) {
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Room', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('User', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Date', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Time', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Status', 'hall-booking-calendar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $booking) : ?>
                        <tr>
                            <td><?php echo esc_html($booking->room_name); ?></td>
                            <td><?php echo esc_html($booking->user_name); ?></td>
                            <td><?php echo esc_html(date('F j, Y', strtotime($booking->booking_date))); ?></td>
                            <td><?php echo esc_html(date('g:i A', strtotime($booking->start_time)) . ' - ' . date('g:i A', strtotime($booking->end_time))); ?></td>
                            <td><span class="hbc-status hbc-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html(ucfirst($booking->status)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            } else {
                echo '<p>' . __('No bookings found.', 'hall-booking-calendar') . '</p>';
            }
            ?>
        </div>
    </div>
    <?php
}
