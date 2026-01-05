<?php
/**
 * Booking Handler - Process booking requests and validations
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle booking submission via AJAX
 */
function hbc_handle_booking_submission() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hbc_booking_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'hall-booking-calendar')));
        return;
    }

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Sanitize input
    $room_id = intval($_POST['room_id']);
    $group_id = isset($_POST['group_id']) && !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $user_name = sanitize_text_field($_POST['user_name']);
    $user_email = sanitize_email($_POST['user_email']);
    $booking_date = sanitize_text_field($_POST['booking_date']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $purpose = sanitize_textarea_field($_POST['purpose']);

    // Validate required fields
    if (empty($room_id) || empty($user_name) || empty($user_email) || empty($booking_date) || empty($start_time) || empty($end_time)) {
        wp_send_json_error(array('message' => __('Please fill in all required fields.', 'hall-booking-calendar')));
        return;
    }

    // Validate email
    if (!is_email($user_email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'hall-booking-calendar')));
        return;
    }

    // Validate room exists and is active
    $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rooms_table WHERE id = %d AND status = 'active'", $room_id));
    if (!$room) {
        wp_send_json_error(array('message' => __('Invalid room selected.', 'hall-booking-calendar')));
        return;
    }

    // Validate group if provided
    if ($group_id) {
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $groups_table WHERE id = %d AND status = 'active'", $group_id));
        if (!$group) {
            wp_send_json_error(array('message' => __('Invalid group selected.', 'hall-booking-calendar')));
            return;
        }
    }

    // Validate date is not in the past
    if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
        wp_send_json_error(array('message' => __('Cannot book a date in the past.', 'hall-booking-calendar')));
        return;
    }

    // Validate time range
    if (strtotime($start_time) >= strtotime($end_time)) {
        wp_send_json_error(array('message' => __('End time must be after start time.', 'hall-booking-calendar')));
        return;
    }

    // Check for booking conflicts
    $conflict = hbc_check_booking_conflict($room_id, $booking_date, $start_time, $end_time);
    if ($conflict) {
        wp_send_json_error(array('message' => __('This room is already booked for the selected time. Please choose a different time or room.', 'hall-booking-calendar')));
        return;
    }

    // Get current user ID if logged in
    $user_id = is_user_logged_in() ? get_current_user_id() : null;

    // Insert booking
    $result = $wpdb->insert(
        $bookings_table,
        array(
            'room_id' => $room_id,
            'group_id' => $group_id,
            'user_id' => $user_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'booking_date' => $booking_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'purpose' => $purpose,
            'status' => 'pending'
        ),
        array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    if ($result) {
        // Send notification email (optional)
        hbc_send_booking_notification($wpdb->insert_id);

        wp_send_json_success(array(
            'message' => __('Your booking has been submitted successfully! You will receive a confirmation email once it is approved.', 'hall-booking-calendar'),
            'booking_id' => $wpdb->insert_id
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to submit booking. Please try again.', 'hall-booking-calendar')));
    }
}
add_action('wp_ajax_hbc_submit_booking', 'hbc_handle_booking_submission');
add_action('wp_ajax_nopriv_hbc_submit_booking', 'hbc_handle_booking_submission');

/**
 * Check for booking conflicts
 */
function hbc_check_booking_conflict($room_id, $booking_date, $start_time, $end_time, $exclude_booking_id = 0) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table
        WHERE room_id = %d
        AND booking_date = %s
        AND status != 'cancelled'
        AND id != %d
        AND (
            (start_time < %s AND end_time > %s)
            OR (start_time < %s AND end_time > %s)
            OR (start_time >= %s AND end_time <= %s)
        )",
        $room_id,
        $booking_date,
        $exclude_booking_id,
        $end_time,
        $start_time,
        $end_time,
        $end_time,
        $start_time,
        $end_time
    );

    $conflicts = $wpdb->get_var($query);

    return $conflicts > 0;
}

/**
 * Get available time slots for a room on a specific date
 */
function hbc_get_available_slots() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hbc_booking_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'hall-booking-calendar')));
        return;
    }

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $room_id = intval($_POST['room_id']);
    $booking_date = sanitize_text_field($_POST['booking_date']);

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT start_time, end_time FROM $bookings_table
        WHERE room_id = %d
        AND booking_date = %s
        AND status != 'cancelled'
        ORDER BY start_time ASC",
        $room_id,
        $booking_date
    ));

    $booked_slots = array();
    foreach ($bookings as $booking) {
        $booked_slots[] = array(
            'start' => $booking->start_time,
            'end' => $booking->end_time
        );
    }

    wp_send_json_success(array('booked_slots' => $booked_slots));
}
add_action('wp_ajax_hbc_get_available_slots', 'hbc_get_available_slots');
add_action('wp_ajax_nopriv_hbc_get_available_slots', 'hbc_get_available_slots');

/**
 * Send booking notification email
 */
function hbc_send_booking_notification($booking_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        WHERE b.id = %d",
        $booking_id
    ));

    if (!$booking) {
        return;
    }

    // Email to user
    $to = $booking->user_email;
    $subject = __('Booking Confirmation - Hall Booking Calendar', 'hall-booking-calendar');
    $message = sprintf(
        __("Dear %s,\n\nThank you for your booking request.\n\nBooking Details:\n- Room: %s\n- Date: %s\n- Time: %s - %s\n- Purpose: %s\n\nYour booking is currently pending approval. You will receive another email once it is confirmed.\n\nThank you!", 'hall-booking-calendar'),
        $booking->user_name,
        $booking->room_name,
        date('F j, Y', strtotime($booking->booking_date)),
        date('g:i A', strtotime($booking->start_time)),
        date('g:i A', strtotime($booking->end_time)),
        $booking->purpose
    );

    wp_mail($to, $subject, $message);

    // Email to admin
    $admin_email = get_option('admin_email');
    $admin_subject = __('New Booking Request - Hall Booking Calendar', 'hall-booking-calendar');
    $admin_message = sprintf(
        __("A new booking request has been submitted:\n\nBooking Details:\n- Room: %s\n- User: %s (%s)\n- Date: %s\n- Time: %s - %s\n- Purpose: %s\n\nPlease review and approve the booking in the admin panel.", 'hall-booking-calendar'),
        $booking->room_name,
        $booking->user_name,
        $booking->user_email,
        date('F j, Y', strtotime($booking->booking_date)),
        date('g:i A', strtotime($booking->start_time)),
        date('g:i A', strtotime($booking->end_time)),
        $booking->purpose
    );

    wp_mail($admin_email, $admin_subject, $admin_message);
}
