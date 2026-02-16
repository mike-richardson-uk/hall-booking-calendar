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
 *
 * Processes new booking requests from the frontend form. Validates all input,
 * checks for conflicts, handles file uploads, and creates single or recurring bookings.
 * Sends email notifications to both user and webmaster upon success.
 *
 * Validation includes:
 * - Nonce verification for security
 * - Password check if enabled
 * - Required field validation
 * - Email format validation
 * - Room and group existence check
 * - Date not in past check
 * - Time range validation
 * - Booking conflict detection
 *
 * @since 1.0.0
 * @return void Sends JSON response and exits
 */
function hbc_handle_booking_submission() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hbc_booking_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'hall-booking-calendar')));
        return;
    }

    // Verify password if password protection is enabled
    $require_password = get_option('hbc_require_password', '0');
    if ($require_password == '1') {
        $hashed_password = get_option('hbc_booking_password', '');
        $submitted_password = isset($_POST['booking_password_input']) ? $_POST['booking_password_input'] : '';

        // Use timing-safe password verification
        if (!wp_check_password($submitted_password, $hashed_password)) {
            wp_send_json_error(array('message' => __('Incorrect password. Please try again.', 'hall-booking-calendar')));
            return;
        }
    }

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Sanitize input
    $room_ids = isset($_POST['room_ids']) && is_array($_POST['room_ids']) ? array_map('intval', $_POST['room_ids']) : array();
    // Backward compatibility: accept single room_id if room_ids not provided
    if (empty($room_ids) && isset($_POST['room_id']) && !empty($_POST['room_id'])) {
        $room_ids = array(intval($_POST['room_id']));
    }
    $room_ids = array_unique(array_filter($room_ids));
    // Use first room as primary room_id for backward compatibility
    $room_id = !empty($room_ids) ? $room_ids[0] : 0;
    $group_id = isset($_POST['group_id']) && !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $user_name = sanitize_text_field($_POST['user_name']);
    $user_email = sanitize_email($_POST['user_email']);
    $booking_date = sanitize_text_field($_POST['booking_date']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $purpose = sanitize_textarea_field($_POST['purpose']);
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

    // Recurring booking options
    $is_recurring = isset($_POST['is_recurring']) && $_POST['is_recurring'] == '1';
    $recurrence_pattern = isset($_POST['recurrence_pattern']) ? sanitize_text_field($_POST['recurrence_pattern']) : '';
    $recurrence_end = isset($_POST['recurrence_end']) ? sanitize_text_field($_POST['recurrence_end']) : '';

    // Multi-date booking options
    $additional_dates = isset($_POST['additional_dates']) && is_array($_POST['additional_dates']) ? array_map('sanitize_text_field', $_POST['additional_dates']) : array();

    // Handle file upload
    $file_path = '';
    if (isset($_FILES['booking_file']) && $_FILES['booking_file']['error'] === UPLOAD_ERR_OK) {
        $upload_result = hbc_handle_file_upload($_FILES['booking_file']);
        if ($upload_result['success']) {
            $file_path = $upload_result['file_path'];
        } else {
            wp_send_json_error(array('message' => $upload_result['message']));
            return;
        }
    }

    // Validate required fields
    if (empty($room_ids) || empty($user_name) || empty($user_email) || empty($booking_date) || empty($start_time) || empty($end_time)) {
        wp_send_json_error(array('message' => __('Please fill in all required fields.', 'hall-booking-calendar')));
        return;
    }

    // Validate email
    if (!is_email($user_email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'hall-booking-calendar')));
        return;
    }

    // Validate all selected rooms exist and are active
    foreach ($room_ids as $rid) {
        $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rooms_table WHERE id = %d AND status = 'active'", $rid));
        if (!$room) {
            wp_send_json_error(array('message' => sprintf(__('Invalid room selected (ID: %d).', 'hall-booking-calendar'), $rid)));
            return;
        }
    }

    // Validate group if provided
    if ($group_id) {
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $groups_table WHERE id = %d AND status = 'active'", $group_id));
        if (!$group) {
            wp_send_json_error(array('message' => __('Invalid group selected.', 'hall-booking-calendar')));
            return;
        }
    }

    // Validate date is not in the past using DateTime for robustness
    try {
        $booking_datetime = new DateTime($booking_date, wp_timezone());
        $today = new DateTime('today', wp_timezone());

        if ($booking_datetime < $today) {
            wp_send_json_error(array('message' => __('Cannot book a date in the past.', 'hall-booking-calendar')));
            return;
        }

        // Validate time format
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $start_time) ||
            !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $end_time)) {
            wp_send_json_error(array('message' => __('Invalid time format.', 'hall-booking-calendar')));
            return;
        }

        // Validate time range using DateTime for proper comparison
        $start_dt = DateTime::createFromFormat('H:i:s', $start_time . ':00', wp_timezone());
        $end_dt = DateTime::createFromFormat('H:i:s', $end_time . ':00', wp_timezone());

        if (!$start_dt || !$end_dt) {
            wp_send_json_error(array('message' => __('Invalid time format.', 'hall-booking-calendar')));
            return;
        }

        if ($start_dt >= $end_dt) {
            wp_send_json_error(array('message' => __('End time must be after start time.', 'hall-booking-calendar')));
            return;
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => __('Invalid date or time format.', 'hall-booking-calendar')));
        return;
    }

    // Get current user ID if logged in
    $user_id = is_user_logged_in() ? get_current_user_id() : null;

    // Prepare booking data
    $booking_data = array(
        'room_id' => $room_id,
        'group_id' => $group_id,
        'user_id' => $user_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'booking_date' => $booking_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'purpose' => $purpose,
        'description' => $description,
        'file_path' => $file_path,
        'status' => 'pending'
    );

    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    // Handle recurring or multi-date bookings
    if ($is_recurring || !empty($additional_dates)) {
        $result = hbc_create_recurring_bookings($booking_data, $recurrence_pattern, $recurrence_end, $additional_dates, $room_ids);

        if ($result['success']) {
            // Send notification email for parent booking
            hbc_send_booking_notification($result['parent_id']);

            wp_send_json_success(array(
                'message' => $result['message'],
                'booking_ids' => $result['booking_ids']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    } else {
        // Single booking - use database transaction to prevent race conditions
        // Start transaction
        $wpdb->query('START TRANSACTION');

        // Check for conflicts on ALL selected rooms
        $conflict_rooms = array();
        foreach ($room_ids as $rid) {
            $conflict = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table b
                INNER JOIN $booking_rooms_table br ON b.id = br.booking_id
                WHERE br.room_id = %d
                AND b.booking_date = %s
                AND b.status != 'cancelled'
                AND (
                    (b.start_time < %s AND b.end_time > %s)
                    OR (b.start_time < %s AND b.end_time > %s)
                    OR (b.start_time >= %s AND b.end_time <= %s)
                )
                FOR UPDATE",
                $rid,
                $booking_date,
                $end_time,
                $start_time,
                $end_time,
                $end_time,
                $start_time,
                $end_time
            ));
            if ($conflict > 0) {
                $room_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $rooms_table WHERE id = %d", $rid));
                $conflict_rooms[] = $room_name;
            }
        }

        if (!empty($conflict_rooms)) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => sprintf(
                __('The following room(s) are already booked for the selected time: %s. Please choose a different time or room.', 'hall-booking-calendar'),
                implode(', ', $conflict_rooms)
            )));
            return;
        }

        // Insert single booking
        $result = $wpdb->insert(
            $bookings_table,
            $booking_data,
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            $new_booking_id = $wpdb->insert_id;
            // Insert into booking_rooms junction table
            foreach ($room_ids as $rid) {
                $wpdb->insert($booking_rooms_table, array(
                    'booking_id' => $new_booking_id,
                    'room_id' => $rid,
                ));
            }
            $wpdb->query('COMMIT');
        } else {
            $wpdb->query('ROLLBACK');
        }

        if ($result) {
            // Send notification email
            hbc_send_booking_notification($new_booking_id);

            wp_send_json_success(array(
                'message' => __('Your booking has been submitted successfully! You will receive a confirmation email once it is approved.', 'hall-booking-calendar'),
                'booking_id' => $new_booking_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to submit booking. Please try again.', 'hall-booking-calendar')));
        }
    }
}
add_action('wp_ajax_hbc_submit_booking', 'hbc_handle_booking_submission');
add_action('wp_ajax_nopriv_hbc_submit_booking', 'hbc_handle_booking_submission');

/**
 * Check for booking conflicts
 *
 * Determines if a proposed booking conflicts with existing bookings for the same room.
 * A conflict occurs when time ranges overlap. Uses the booking_rooms junction table
 * for multi-room aware conflict detection.
 *
 * @since 1.0.0
 * @param int    $room_id           Room ID to check
 * @param string $booking_date      Date in Y-m-d format
 * @param string $start_time        Start time in H:i:s format
 * @param string $end_time          End time in H:i:s format
 * @param int    $exclude_booking_id Optional. Booking ID to exclude (for updates). Default 0.
 * @return bool True if conflict exists, false otherwise
 */
function hbc_check_booking_conflict($room_id, $booking_date, $start_time, $end_time, $exclude_booking_id = 0) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    // Check via junction table for multi-room aware conflict detection
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table b
        INNER JOIN $booking_rooms_table br ON b.id = br.booking_id
        WHERE br.room_id = %d
        AND b.booking_date = %s
        AND b.status != 'cancelled'
        AND b.id != %d
        AND (
            (b.start_time < %s AND b.end_time > %s)
            OR (b.start_time < %s AND b.end_time > %s)
            OR (b.start_time >= %s AND b.end_time <= %s)
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
 * Get available time slots for a room on a specific date via AJAX
 *
 * Returns all existing bookings for a room on a given date so the frontend
 * can display which time slots are already taken.
 *
 * @since 1.0.0
 * @return void Sends JSON response with booked slots array
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
 * Handle file upload for booking attachments
 *
 * Validates and uploads PDF files attached to bookings.
 * Files are stored in wp-content/uploads/hall-bookings/ directory.
 *
 * Validation:
 * - Only PDF files allowed
 * - Maximum file size: 5MB
 * - Generates unique filename using timestamp
 *
 * @since 1.3.0
 * @param array $file File array from $_FILES
 * @return array Array with 'success' boolean and either 'file_path' or 'message'
 */
function hbc_handle_file_upload($file) {
    // Validate file type - only PDFs allowed for security
    $allowed_type = 'application/pdf';
    $file_type = $file['type'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file_type !== $allowed_type && $file_ext !== 'pdf') {
        return array(
            'success' => false,
            'message' => __('Only PDF files are allowed.', 'hall-booking-calendar')
        );
    }

    // Validate file size (5MB max to prevent abuse)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        return array(
            'success' => false,
            'message' => __('File size must be less than 5MB.', 'hall-booking-calendar')
        );
    }

    // Additional MIME type verification using file content
    if (function_exists('mime_content_type')) {
        $real_mime = mime_content_type($file['tmp_name']);
        if ($real_mime !== 'application/pdf') {
            return array(
                'success' => false,
                'message' => __('File content does not match PDF format.', 'hall-booking-calendar')
            );
        }
    }

    // Verify PDF signature (first 5 bytes should be %PDF-)
    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle) {
        $signature = fread($handle, 5);
        fclose($handle);

        if ($signature !== '%PDF-') {
            return array(
                'success' => false,
                'message' => __('Invalid PDF file format.', 'hall-booking-calendar')
            );
        }
    }

    // Set up upload directory
    $upload_dir = wp_upload_dir();
    $hbc_upload_dir = $upload_dir['basedir'] . '/hall-bookings';

    // Ensure directory exists
    if (!file_exists($hbc_upload_dir)) {
        wp_mkdir_p($hbc_upload_dir);
    }

    // Generate unique filename
    $filename = time() . '_' . sanitize_file_name($file['name']);
    $file_path = $hbc_upload_dir . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Return relative path from uploads directory
        return array(
            'success' => true,
            'file_path' => 'hall-bookings/' . $filename
        );
    } else {
        return array(
            'success' => false,
            'message' => __('Failed to upload file. Please try again.', 'hall-booking-calendar')
        );
    }
}

/**
 * Send booking notification emails
 *
 * Sends two emails:
 * 1. Confirmation email to the user who made the booking
 * 2. Notification email to the webmaster for approval
 *
 * For recurring/multi-date bookings, combines all dates into a single email
 * rather than sending separate emails for each date.
 *
 * Email includes: room name, date(s), time(s), group, purpose, description,
 * and link to attached file if present.
 *
 * @since 1.0.0
 * @param int $booking_id The ID of the booking (parent booking for series)
 * @return void
 */
function hbc_send_booking_notification($booking_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    // Fetch booking with joined room and group names
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, g.name as group_name
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        LEFT JOIN $groups_table g ON b.group_id = g.id
        WHERE b.id = %d",
        $booking_id
    ));

    if (!$booking) {
        return;
    }

    // Get all room names for this booking from the junction table
    $booking_room_names = $wpdb->get_col($wpdb->prepare(
        "SELECT r.name FROM $booking_rooms_table br
         INNER JOIN $rooms_table r ON br.room_id = r.id
         WHERE br.booking_id = %d
         ORDER BY r.name ASC",
        $booking_id
    ));
    $all_rooms_display = !empty($booking_room_names) ? implode(', ', $booking_room_names) : $booking->room_name;

    // Check if this is part of a recurring series
    $bookings = array($booking);
    if (!empty($booking->series_id)) {
        // Get all bookings in the series for a combined email
        // This provides better user experience than multiple individual emails
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, r.name as room_name, g.name as group_name
            FROM $bookings_table b
            LEFT JOIN $rooms_table r ON b.room_id = r.id
            LEFT JOIN $groups_table g ON b.group_id = g.id
            WHERE b.series_id = %s
            ORDER BY b.booking_date ASC, b.start_time ASC",
            $booking->series_id
        ));
    }

    // Build booking details
    $booking_details = '';
    $booking_count = count($bookings);

    if ($booking_count > 1) {
        $booking_details .= sprintf(__("Total Bookings in Series: %d\n\n", 'hall-booking-calendar'), $booking_count);
    }

    foreach ($bookings as $idx => $bk) {
        if ($booking_count > 1) {
            $booking_details .= sprintf(__("Booking #%d:\n", 'hall-booking-calendar'), $idx + 1);
        }
        // Get all room names for this booking
        $bk_room_names = $wpdb->get_col($wpdb->prepare(
            "SELECT r.name FROM $booking_rooms_table br
             INNER JOIN $rooms_table r ON br.room_id = r.id
             WHERE br.booking_id = %d ORDER BY r.name ASC",
            $bk->id
        ));
        $bk_rooms_display = !empty($bk_room_names) ? implode(', ', $bk_room_names) : $bk->room_name;
        $booking_details .= sprintf(__("- Room(s): %s\n", 'hall-booking-calendar'), $bk_rooms_display);
        $booking_details .= sprintf(__("- Date: %s\n", 'hall-booking-calendar'), date('F j, Y', strtotime($bk->booking_date)));
        $booking_details .= sprintf(__("- Time: %s - %s\n", 'hall-booking-calendar'), date('g:i A', strtotime($bk->start_time)), date('g:i A', strtotime($bk->end_time)));
        if (!empty($bk->group_name)) {
            $booking_details .= sprintf(__("- Group: %s\n", 'hall-booking-calendar'), $bk->group_name);
        }
        if (!empty($bk->purpose)) {
            $booking_details .= sprintf(__("- Purpose: %s\n", 'hall-booking-calendar'), $bk->purpose);
        }
        if (!empty($bk->description)) {
            $booking_details .= sprintf(__("- Description: %s\n", 'hall-booking-calendar'), $bk->description);
        }
        if (!empty($bk->file_path)) {
            $upload_dir = wp_upload_dir();
            $file_url = $upload_dir['baseurl'] . '/' . $bk->file_path;
            $booking_details .= sprintf(__("- Attached File: %s\n", 'hall-booking-calendar'), $file_url);
        }
        $booking_details .= "\n";
    }

    // Email to user - sanitize email headers to prevent injection
    $to = sanitize_email($booking->user_email);
    // Strip CRLF characters from user name to prevent email header injection
    $safe_user_name = str_replace(array("\r", "\n", "%0a", "%0d"), '', $booking->user_name);

    $subject = $booking_count > 1
        ? __('Booking Confirmation - Multiple Bookings - Hall Booking Calendar', 'hall-booking-calendar')
        : __('Booking Confirmation - Hall Booking Calendar', 'hall-booking-calendar');

    $message = sprintf(
        __("Dear %s,\n\nThank you for your booking request.\n\n%s\nYour booking%s currently pending approval. You will receive another email once it is confirmed.\n\nThank you!", 'hall-booking-calendar'),
        $safe_user_name,
        $booking_details,
        $booking_count > 1 ? 's are' : ' is'
    );

    wp_mail($to, $subject, $message);

    // Email to webmaster (from settings)
    $webmaster_email = sanitize_email(get_option('hbc_webmaster_email', get_option('admin_email')));
    $webmaster_subject = $booking_count > 1
        ? __('New Booking Request - Multiple Bookings - Hall Booking Calendar', 'hall-booking-calendar')
        : __('New Booking Request - Hall Booking Calendar', 'hall-booking-calendar');

    // Build direct approval link to the admin booking detail page
    $approval_url = admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking->id);

    // Build link to pending bookings list for quick overview
    $pending_url = admin_url('admin.php?page=hall-booking-bookings&status=pending');

    $webmaster_message = sprintf(
        __("A new booking request has been submitted:\n\nUser: %s (%s)\n\n%s\nReview and approve this booking:\n%s\n\nView all pending bookings:\n%s", 'hall-booking-calendar'),
        $safe_user_name,
        sanitize_email($booking->user_email),
        $booking_details,
        $approval_url,
        $pending_url
    );

    wp_mail($webmaster_email, $webmaster_subject, $webmaster_message);
}
