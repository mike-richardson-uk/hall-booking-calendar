<?php
/**
 * Admin CSV Export - Export bookings by date range
 *
 * @package Hall_Booking_Calendar
 * @since 1.10.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle CSV export download
 *
 * Runs early (admin_init) so headers can be sent before any output.
 */
function hbc_handle_csv_export() {
    if (!isset($_POST['hbc_export_csv']) || !isset($_POST['hbc_export_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['hbc_export_nonce'], 'hbc_export_csv')) {
        wp_die(__('Security check failed', 'hall-booking-calendar'));
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access', 'hall-booking-calendar'));
    }

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $categories_table = $wpdb->prefix . 'hbc_categories';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    $date_from = sanitize_text_field($_POST['export_date_from']);
    $date_to = sanitize_text_field($_POST['export_date_to']);
    $status_filter = isset($_POST['export_status']) ? sanitize_text_field($_POST['export_status']) : '';

    // Validate dates
    if (empty($date_from) || empty($date_to)) {
        add_settings_error('hbc_export', 'hbc_export_error', __('Please provide both start and end dates.', 'hall-booking-calendar'), 'error');
        return;
    }

    if ($date_from > $date_to) {
        add_settings_error('hbc_export', 'hbc_export_error', __('Start date must be before end date.', 'hall-booking-calendar'), 'error');
        return;
    }

    // Build query
    $where = "WHERE b.booking_date BETWEEN %s AND %s";
    $params = array($date_from, $date_to);

    if (!empty($status_filter)) {
        $where .= " AND b.status = %s";
        $params[] = $status_filter;
    }

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, r.name as room_name, g.name as group_name, c.name as category_name
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        LEFT JOIN $groups_table g ON b.group_id = g.id
        LEFT JOIN $categories_table c ON b.category_id = c.id
        $where
        ORDER BY b.booking_date ASC, b.start_time ASC",
        $params
    ));

    // Pre-fetch all room names via junction table
    $all_booking_rooms = array();
    if (!empty($bookings)) {
        $booking_ids = wp_list_pluck($bookings, 'id');
        $ids_placeholder = implode(',', array_map('intval', $booking_ids));
        $room_rows = $wpdb->get_results(
            "SELECT br.booking_id, r.name FROM $booking_rooms_table br
             INNER JOIN $rooms_table r ON br.room_id = r.id
             WHERE br.booking_id IN ($ids_placeholder) ORDER BY r.name ASC"
        );
        foreach ($room_rows as $row) {
            $all_booking_rooms[$row->booking_id][] = $row->name;
        }
    }

    // Generate CSV
    $filename = 'bookings-' . $date_from . '-to-' . $date_to . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, array(
        'ID',
        'Date',
        'Start Time',
        'End Time',
        'Room(s)',
        'Group',
        'Category',
        'Purpose',
        'Description',
        'User Name',
        'User Email',
        'Status',
        'Recurring',
        'Created At'
    ));

    // Data rows
    foreach ($bookings as $booking) {
        $rooms_display = isset($all_booking_rooms[$booking->id])
            ? implode('; ', $all_booking_rooms[$booking->id])
            : $booking->room_name;

        fputcsv($output, array(
            $booking->id,
            $booking->booking_date,
            $booking->start_time,
            $booking->end_time,
            $rooms_display,
            $booking->group_name ?: '',
            $booking->category_name ?: '',
            $booking->purpose ?: '',
            $booking->description ?: '',
            $booking->user_name,
            $booking->user_email,
            ucfirst($booking->status),
            $booking->is_recurring ? 'Yes' : 'No',
            $booking->created_at
        ));
    }

    fclose($output);
    exit;
}
add_action('admin_init', 'hbc_handle_csv_export');

/**
 * Display export page
 */
function hbc_admin_export_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Export Bookings', 'hall-booking-calendar'); ?></h1>

        <?php settings_errors('hbc_export'); ?>

        <div class="hbc-booking-details">
            <h2><?php _e('Export to CSV', 'hall-booking-calendar'); ?></h2>
            <p><?php _e('Select a date range to export bookings as a CSV file. The file can be opened in Excel, Google Sheets, or any spreadsheet application.', 'hall-booking-calendar'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('hbc_export_csv', 'hbc_export_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="export_date_from"><?php _e('Date From', 'hall-booking-calendar'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="export_date_from" name="export_date_from" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="export_date_to"><?php _e('Date To', 'hall-booking-calendar'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="export_date_to" name="export_date_to" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="export_status"><?php _e('Status Filter', 'hall-booking-calendar'); ?></label>
                        </th>
                        <td>
                            <select id="export_status" name="export_status">
                                <option value=""><?php _e('All Statuses', 'hall-booking-calendar'); ?></option>
                                <option value="pending"><?php _e('Pending', 'hall-booking-calendar'); ?></option>
                                <option value="confirmed"><?php _e('Confirmed', 'hall-booking-calendar'); ?></option>
                                <option value="cancelled"><?php _e('Cancelled', 'hall-booking-calendar'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="hbc_export_csv" class="button button-primary" value="<?php _e('Download CSV', 'hall-booking-calendar'); ?>">
                </p>
            </form>
        </div>
    </div>
    <?php
}
