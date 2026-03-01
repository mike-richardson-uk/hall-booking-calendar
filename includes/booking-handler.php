<?php
/**
 * Booking Handler - Process booking requests and validations
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Return the configured hall name, or an empty string if not set.
 * Use this wherever emails reference the venue so that the name is consistent.
 */
function hbc_get_hall_name() {
    return trim((string) get_option('hbc_hall_name', ''));
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
    $category_id = isset($_POST['category_id']) && !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
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

    // Validate category (required)
    $categories_table = $wpdb->prefix . 'hbc_categories';
    if (!$category_id) {
        wp_send_json_error(array('message' => __('Please select an event category.', 'hall-booking-calendar')));
        return;
    }
    $category = $wpdb->get_row($wpdb->prepare("SELECT * FROM $categories_table WHERE id = %d AND status = 'active'", $category_id));
    if (!$category) {
        wp_send_json_error(array('message' => __('Invalid category selected.', 'hall-booking-calendar')));
        return;
    }

    // Validate terms acceptance if T&C are configured
    $terms_text = get_option('hbc_terms_conditions', '');
    if (!empty($terms_text)) {
        $accept_terms = isset($_POST['accept_terms']) && $_POST['accept_terms'] == '1';
        if (!$accept_terms) {
            wp_send_json_error(array('message' => __('You must accept the terms and conditions.', 'hall-booking-calendar')));
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
        'category_id' => $category_id,
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
            // Save booking-in form config against the parent booking
            hbc_save_book_in_form_config($result['parent_id']);

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
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
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
            // Generate cancellation and edit tokens for the booker (outside transaction)
            hbc_generate_and_store_cancel_token($new_booking_id);
            hbc_generate_and_store_edit_token($new_booking_id);
        } else {
            $wpdb->query('ROLLBACK');
        }

        if ($result) {
            // Save booking-in form config if enabled
            hbc_save_book_in_form_config($new_booking_id);

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
 * Get book-in form configuration for a booking
 *
 * Checks the booking's own config first, then falls back to the parent
 * booking's config so recurring series members only need one config entry.
 *
 * @since 1.14.0
 * @param int $booking_id
 * @return object|null Row from hbc_booking_forms, or null if not found/table missing
 */
function hbc_get_book_in_form($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'hbc_booking_forms';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return null;
    }

    $config = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE booking_id = %d AND enabled = 1",
        $booking_id
    ));

    if ($config) {
        return $config;
    }

    // Fall back to parent booking config for recurring series
    $parent_id = $wpdb->get_var($wpdb->prepare(
        "SELECT parent_booking_id FROM {$wpdb->prefix}hbc_bookings WHERE id = %d",
        $booking_id
    ));

    if ($parent_id) {
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE booking_id = %d AND enabled = 1",
            $parent_id
        ));
    }

    return $config;
}

/**
 * Save booking-in form configuration after a booking is created
 *
 * Reads form config from the current POST request and stores it against
 * the given booking ID. Only runs when enable_book_in is set.
 *
 * @since 1.14.0
 * @param int $booking_id Booking ID to attach the config to
 * @return void
 */
function hbc_save_book_in_form_config($booking_id) {
    if (empty($_POST['enable_book_in']) || '1' !== $_POST['enable_book_in']) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'hbc_booking_forms';

    // Collect and validate email recipients
    $raw_emails = isset($_POST['book_in_emails']) && is_array($_POST['book_in_emails'])
        ? $_POST['book_in_emails']
        : array();
    $emails = array_values(array_filter(array_map('sanitize_email', $raw_emails), 'is_email'));

    if (empty($emails)) {
        return; // At least one valid email is required
    }

    // Collect meal options from JSON payload (decode first; do not sanitize the JSON string
    // or HTML in description will be stripped before we can apply hbc_sanitize_meal_description_html)
    $include_meal_menu = (!empty($_POST['include_meal_menu']) && '1' === $_POST['include_meal_menu']) ? 1 : 0;
    $meal_options = array();
    if ($include_meal_menu && !empty($_POST['book_in_meals_json'])) {
        $raw_meals = json_decode(wp_unslash($_POST['book_in_meals_json']), true);
        if (is_array($raw_meals)) {
            foreach ($raw_meals as $meal) {
                if (!empty($meal['name'])) {
                    $meal_options[] = array(
                        'name'        => sanitize_text_field($meal['name']),
                        'description' => hbc_sanitize_meal_description_html(isset($meal['description']) ? $meal['description'] : ''),
                        'price'       => isset($meal['price']) && is_numeric($meal['price']) ? abs(floatval($meal['price'])) : 0,
                    );
                }
            }
        }
    }

    // Preserve existing token so the short URL stays stable across edits
    $existing_token = $wpdb->get_var($wpdb->prepare(
        "SELECT book_in_token FROM $table WHERE booking_id = %d",
        $booking_id
    ));

    if (empty($existing_token)) {
        // Generate a unique 8-character alphanumeric token
        do {
            $token = wp_generate_password(8, false);
            $taken = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE book_in_token = %s",
                $token
            ));
        } while ($taken > 0);
    } else {
        $token = $existing_token;
    }

    $wpdb->replace(
        $table,
        array(
            'booking_id'               => $booking_id,
            'enabled'                  => 1,
            'submission_emails'        => wp_json_encode($emails),
            'include_meal_menu'        => $include_meal_menu,
            'meal_options'             => wp_json_encode($meal_options),
            'payment_bank_name'        => sanitize_text_field(isset($_POST['payment_bank_name']) ? wp_unslash($_POST['payment_bank_name']) : ''),
            'payment_sort_code'        => sanitize_text_field(isset($_POST['payment_sort_code']) ? wp_unslash($_POST['payment_sort_code']) : ''),
            'payment_account_number'   => sanitize_text_field(isset($_POST['payment_account_number']) ? wp_unslash($_POST['payment_account_number']) : ''),
            'payment_reference_prefix' => sanitize_text_field(isset($_POST['payment_reference_prefix']) ? wp_unslash($_POST['payment_reference_prefix']) : ''),
            'payment_cheque_payable'   => sanitize_text_field(isset($_POST['payment_cheque_payable']) ? wp_unslash($_POST['payment_cheque_payable']) : ''),
            'payment_deadline'         => sanitize_text_field(isset($_POST['payment_deadline']) ? wp_unslash($_POST['payment_deadline']) : ''),
            'book_in_token'            => $token,
        ),
        array('%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
}

/**
 * Handle member booking-in form submission via AJAX
 *
 * @since 1.14.0
 * @return void Sends JSON response and exits
 */
function hbc_handle_book_in_submission() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hbc_book_in_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'hall-booking-calendar')));
        return;
    }

    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    if (!$booking_id) {
        wp_send_json_error(array('message' => __('Invalid event.', 'hall-booking-calendar')));
        return;
    }

    $form_config = hbc_get_book_in_form($booking_id);
    if (!$form_config) {
        wp_send_json_error(array('message' => __('Booking-in is not available for this event.', 'hall-booking-calendar')));
        return;
    }

    // Sanitise all fields
    $full_name         = sanitize_text_field(wp_unslash(isset($_POST['bi_full_name']) ? $_POST['bi_full_name'] : ''));
    $email             = sanitize_email(isset($_POST['bi_email']) ? $_POST['bi_email'] : '');
    $phone             = sanitize_text_field(wp_unslash(isset($_POST['bi_phone']) ? $_POST['bi_phone'] : ''));
    $masonic_rank      = sanitize_text_field(wp_unslash(isset($_POST['bi_masonic_rank']) ? $_POST['bi_masonic_rank'] : ''));
    $attendance_type   = sanitize_text_field(wp_unslash(isset($_POST['bi_attendance_type']) ? $_POST['bi_attendance_type'] : ''));
    $membership_type   = sanitize_text_field(wp_unslash(isset($_POST['bi_membership_type']) ? $_POST['bi_membership_type'] : ''));
    $lodge_name        = sanitize_text_field(wp_unslash(isset($_POST['bi_lodge_name']) ? $_POST['bi_lodge_name'] : ''));
    $meal_choice       = sanitize_text_field(wp_unslash(isset($_POST['bi_meal_choice']) ? $_POST['bi_meal_choice'] : ''));
    $vegetarian_alt    = (!empty($_POST['bi_vegetarian_alt']) && '1' === $_POST['bi_vegetarian_alt']) ? 1 : 0;
    $dietary           = sanitize_textarea_field(wp_unslash(isset($_POST['bi_dietary_requirements']) ? $_POST['bi_dietary_requirements'] : ''));
    $comments          = sanitize_textarea_field(wp_unslash(isset($_POST['bi_additional_comments']) ? $_POST['bi_additional_comments'] : ''));

    // Validate required fields (lodge_name only required for guests)
    if (empty($full_name) || empty($email) || empty($phone) || empty($masonic_rank) || empty($attendance_type) || empty($membership_type)) {
        wp_send_json_error(array('message' => __('Please fill in all required fields.', 'hall-booking-calendar')));
        return;
    }
    if ('guest' === $membership_type && empty($lodge_name)) {
        wp_send_json_error(array('message' => __('Please enter the Lodge Name.', 'hall-booking-calendar')));
        return;
    }

    if (!is_email($email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'hall-booking-calendar')));
        return;
    }

    $valid_attendance = array('attending_dinner', 'attending_no_dinner', 'not_attending');
    if (!in_array($attendance_type, $valid_attendance, true)) {
        wp_send_json_error(array('message' => __('Please select a valid attendance type.', 'hall-booking-calendar')));
        return;
    }

    if (!in_array($membership_type, array('member', 'guest'), true)) {
        wp_send_json_error(array('message' => __('Please select a valid membership type.', 'hall-booking-calendar')));
        return;
    }

    // Save submission
    global $wpdb;
    $result = $wpdb->insert(
        $wpdb->prefix . 'hbc_form_submissions',
        array(
            'booking_id'           => $booking_id,
            'full_name'            => $full_name,
            'email'                => $email,
            'phone'                => $phone,
            'masonic_rank'         => $masonic_rank,
            'attendance_type'      => $attendance_type,
            'membership_type'      => $membership_type,
            'lodge_name'             => $lodge_name,
            'meal_choice'            => $meal_choice,
            'vegetarian_alternative' => $vegetarian_alt,
            'dietary_requirements'   => $dietary,
            'additional_comments'    => $comments,
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
    );

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to save your booking. Please try again.', 'hall-booking-calendar')));
        return;
    }

    // Fetch event details for the notification email
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name
         FROM {$wpdb->prefix}hbc_bookings b
         LEFT JOIN {$wpdb->prefix}hbc_rooms r ON b.room_id = r.id
         WHERE b.id = %d",
        $booking_id
    ));

    $attendance_labels = array(
        'attending_dinner'    => __('Attending, with dinner', 'hall-booking-calendar'),
        'attending_no_dinner' => __('Attending, but not having dinner', 'hall-booking-calendar'),
        'not_attending'       => __('Not attending', 'hall-booking-calendar'),
    );

    $event_line = $booking
        ? sprintf(
            "Event: %s\nDate: %s\nTime: %s - %s\n\n",
            $booking->purpose,
            date('l, F j, Y', strtotime($booking->booking_date)),
            date('g:i A', strtotime($booking->start_time)),
            date('g:i A', strtotime($booking->end_time))
          )
        : '';

    $safe_name = str_replace(array("\r", "\n", "%0a", "%0d"), '', $full_name);
    $subject   = sprintf(__('Booking-In: %s', 'hall-booking-calendar'), $safe_name);

    $body  = $event_line;
    $body .= "Member Details\n--------------\n";
    $body .= sprintf(__("Full Name: %s\n", 'hall-booking-calendar'), $safe_name);
    $body .= sprintf(__("Email: %s\n", 'hall-booking-calendar'), $email);
    $body .= sprintf(__("Phone: %s\n", 'hall-booking-calendar'), $phone);
    $body .= "\nMasonic Information\n-------------------\n";
    $body .= sprintf(__("Masonic Rank: %s\n", 'hall-booking-calendar'), $masonic_rank);
    $body .= sprintf(__("Attendance: %s\n", 'hall-booking-calendar'), isset($attendance_labels[$attendance_type]) ? $attendance_labels[$attendance_type] : $attendance_type);
    $body .= sprintf(__("Membership: %s\n", 'hall-booking-calendar'), 'member' === $membership_type ? __('Member', 'hall-booking-calendar') : __('Guest', 'hall-booking-calendar'));
    if ('guest' === $membership_type && !empty($lodge_name)) {
        $body .= sprintf(__("Lodge Name: %s\n", 'hall-booking-calendar'), $lodge_name);
    }
    if (!empty($meal_choice)) {
        $body .= sprintf(__("\nMeal Choice: %s\n", 'hall-booking-calendar'), $meal_choice);
        if ($vegetarian_alt) {
            $body .= __("Vegetarian Alternative: Yes\n", 'hall-booking-calendar');
        }
    }
    if (!empty($dietary)) {
        $body .= sprintf(__("Dietary Requirements: %s\n", 'hall-booking-calendar'), $dietary);
    }
    if (!empty($comments)) {
        $body .= sprintf(__("\nAdditional Comments: %s\n", 'hall-booking-calendar'), $comments);
    }

    // Send to all configured recipients
    $recipients = json_decode($form_config->submission_emails, true);
    if (is_array($recipients)) {
        foreach ($recipients as $recipient) {
            $recipient = sanitize_email($recipient);
            if (is_email($recipient)) {
                wp_mail($recipient, $subject, $body);
            }
        }
    }

    wp_send_json_success(array(
        'message' => __('Thank you! Your booking-in has been received. We look forward to seeing you.', 'hall-booking-calendar'),
    ));
}
add_action('wp_ajax_hbc_submit_book_in', 'hbc_handle_book_in_submission');
add_action('wp_ajax_nopriv_hbc_submit_book_in', 'hbc_handle_book_in_submission');

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

    $categories_table = $wpdb->prefix . 'hbc_categories';

    // Fetch booking with joined room, group, and category names
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, g.name as group_name, c.name as category_name
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        LEFT JOIN $groups_table g ON b.group_id = g.id
        LEFT JOIN $categories_table c ON b.category_id = c.id
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
        $booking_details .= sprintf(__("- Date: %s\n", 'hall-booking-calendar'), date_i18n('l j F Y', strtotime($bk->booking_date)));
        $booking_details .= sprintf(__("- Time: %s - %s\n", 'hall-booking-calendar'), date('g:i A', strtotime($bk->start_time)), date('g:i A', strtotime($bk->end_time)));
        if (!empty($bk->group_name)) {
            $booking_details .= sprintf(__("- Group: %s\n", 'hall-booking-calendar'), $bk->group_name);
        }
        if (!empty($bk->category_name)) {
            $booking_details .= sprintf(__("- Category: %s\n", 'hall-booking-calendar'), $bk->category_name);
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

    // Build subject-line date and purpose (strip CRLF to prevent header injection)
    $safe_purpose = !empty($booking->purpose)
        ? str_replace(array("\r", "\n", "%0a", "%0d"), '', $booking->purpose)
        : '';
    $subject_date = $booking_count > 1
        ? date_i18n('l j F Y', strtotime($bookings[0]->booking_date)) . '+'
        : date_i18n('l j F Y', strtotime($booking->booking_date));
    $hall_name       = hbc_get_hall_name();
    $confirm_label   = !empty($hall_name) ? $hall_name . ' Booking Confirmation' : 'Hall Booking Confirmation';
    $subject = !empty($safe_purpose)
        ? sprintf('[%s] %s — %s', $confirm_label, $subject_date, $safe_purpose)
        : sprintf('[%s] %s', $confirm_label, $subject_date);

    $at_hall = !empty($hall_name) ? ' at ' . $hall_name : '';
    $message = sprintf(
        __("Dear %s,\n\nThank you for your booking request%s.\n\n%s\nYour booking%s currently pending approval. You will receive another email once it is confirmed.\n\nThank you!", 'hall-booking-calendar'),
        $safe_user_name,
        $at_hall,
        $booking_details,
        $booking_count > 1 ? 's are' : ' is'
    );

    // Append the short booking-in URL if one has been configured for this booking
    $form_config = hbc_get_book_in_form($booking_id);
    if ($form_config && !empty($form_config->book_in_token)) {
        $book_in_url = home_url('book/' . $form_config->book_in_token . '/');
        $message .= "\n\n" . __("Members can book in for this event using the following link:", 'hall-booking-calendar') . "\n" . $book_in_url;
    }

    // Append edit and cancellation links so the booker can self-serve
    $tokens = $wpdb->get_row($wpdb->prepare(
        "SELECT edit_token, cancellation_token FROM $bookings_table WHERE id = %d",
        $booking_id
    ));
    if (!empty($tokens->edit_token)) {
        $edit_url = home_url('edit-booking/' . $tokens->edit_token . '/');
        $message .= "\n\n" . __("Need to make changes? Update your booking here:", 'hall-booking-calendar') . "\n" . $edit_url;
    }
    if (!empty($tokens->cancellation_token)) {
        $cancel_url = home_url('cancel-booking/' . $tokens->cancellation_token . '/');
        $message .= "\n\n" . __("Need to cancel? Use this link:", 'hall-booking-calendar') . "\n" . $cancel_url;
    }

    wp_mail($to, $subject, $message);

    // Email to webmaster (from settings)
    $webmaster_email = sanitize_email(get_option('hbc_webmaster_email', get_option('admin_email')));
    $new_booking_label = !empty($hall_name) ? 'New ' . $hall_name . ' Booking' : 'New Hall Booking';
    $webmaster_subject = !empty($safe_purpose)
        ? sprintf('[%s] %s — %s', $new_booking_label, $subject_date, $safe_purpose)
        : sprintf('[%s] %s', $new_booking_label, $subject_date);

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

    // Append attendee CSV download link to webmaster email if book-in is configured
    $submissions_count = 0;
    $form_config_wm = hbc_get_book_in_form($booking_id);
    if ($form_config_wm) {
        $subs_table      = $wpdb->prefix . 'hbc_form_submissions';
        $submissions_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $subs_table WHERE booking_id = %d",
            $booking_id
        ));
    }
    if ($submissions_count > 0) {
        $csv_url          = admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking->id);
        $webmaster_message .= "\n\n" . sprintf(
            __("This event has %d booking-in submission(s). Download the attendee list from the booking detail page:\n%s", 'hall-booking-calendar'),
            $submissions_count,
            $csv_url
        );
    }

    wp_mail($webmaster_email, $webmaster_subject, $webmaster_message);
}

/**
 * Generate and store a unique cancellation token for a booking
 *
 * Called after a booking is inserted. Uses wp_generate_password for a
 * cryptographically random 40-character alphanumeric token.
 *
 * @since 1.18.0
 * @param int $booking_id
 * @return string|false The generated token or false if the column doesn't exist
 */
function hbc_generate_and_store_cancel_token($booking_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $columns = $wpdb->get_col("DESCRIBE $bookings_table", 0);
    if (!in_array('cancellation_token', $columns)) {
        return false;
    }

    $token = wp_generate_password(40, false, false);
    $wpdb->update(
        $bookings_table,
        array('cancellation_token' => $token),
        array('id' => $booking_id),
        array('%s'),
        array('%d')
    );
    return $token;
}

/**
 * Generate and store a self-service edit token for a booking
 *
 * @since 1.19.0
 * @param int $booking_id
 * @return string|false The generated token or false if the column doesn't exist
 */
function hbc_generate_and_store_edit_token($booking_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $columns = $wpdb->get_col("DESCRIBE $bookings_table", 0);
    if (!in_array('edit_token', $columns)) {
        return false;
    }

    $token = wp_generate_password(40, false, false);
    $wpdb->update(
        $bookings_table,
        array('edit_token' => $token),
        array('id' => $booking_id),
        array('%s'),
        array('%d')
    );
    return $token;
}

/**
 * Send acceptance notification email to the booker
 *
 * Sent when an admin confirms (approves) a booking. Notifies the user
 * that their booking has been accepted.
 *
 * @since 1.7.0
 * @param int $booking_id The ID of the approved booking
 * @return void
 */
function hbc_send_acceptance_notification($booking_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    $categories_table = $wpdb->prefix . 'hbc_categories';

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, g.name as group_name, c.name as category_name
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        LEFT JOIN $groups_table g ON b.group_id = g.id
        LEFT JOIN $categories_table c ON b.category_id = c.id
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
    $rooms_display = !empty($booking_room_names) ? implode(', ', $booking_room_names) : $booking->room_name;

    $to = sanitize_email($booking->user_email);
    $safe_user_name = str_replace(array("\r", "\n", "%0a", "%0d"), '', $booking->user_name);

    $hall_name = hbc_get_hall_name();
    $safe_purpose_conf = !empty($booking->purpose)
        ? str_replace(array("\r", "\n", "%0a", "%0d"), '', $booking->purpose)
        : '';
    $subject_date_conf  = date_i18n('l j F Y', strtotime($booking->booking_date));
    $confirmed_label    = !empty($hall_name) ? $hall_name . ' Booking Confirmed' : 'Hall Booking Confirmed';
    $subject = !empty($safe_purpose_conf)
        ? sprintf('[%s] %s — %s', $confirmed_label, $subject_date_conf, $safe_purpose_conf)
        : sprintf('[%s] %s', $confirmed_label, $subject_date_conf);

    $booking_details = sprintf(__("- Room(s): %s\n", 'hall-booking-calendar'), $rooms_display);
    $booking_details .= sprintf(__("- Date: %s\n", 'hall-booking-calendar'), date_i18n('l j F Y', strtotime($booking->booking_date)));
    $booking_details .= sprintf(__("- Time: %s - %s\n", 'hall-booking-calendar'), date('g:i A', strtotime($booking->start_time)), date('g:i A', strtotime($booking->end_time)));
    if (!empty($booking->group_name)) {
        $booking_details .= sprintf(__("- Group: %s\n", 'hall-booking-calendar'), $booking->group_name);
    }
    if (!empty($booking->category_name)) {
        $booking_details .= sprintf(__("- Category: %s\n", 'hall-booking-calendar'), $booking->category_name);
    }
    if (!empty($booking->purpose)) {
        $booking_details .= sprintf(__("- Purpose: %s\n", 'hall-booking-calendar'), $booking->purpose);
    }

    $at_hall_conf = !empty($hall_name) ? ' at ' . $hall_name : '';
    $message = sprintf(
        __("Dear %s,\n\nGreat news! Your booking%s has been confirmed.\n\n%s\nIf you need to make any changes, please contact us.\n\nThank you!", 'hall-booking-calendar'),
        $safe_user_name,
        $at_hall_conf,
        $booking_details
    );

    wp_mail($to, $subject, $message);
}

/**
 * Inline conflict check AJAX handler
 *
 * Called from the booking form as the user fills in times and rooms, so
 * conflicts are surfaced before form submission rather than after.
 *
 * Expects POST: nonce, room_ids[], booking_date, start_time, end_time
 *
 * @since 1.18.0
 * @return void Sends JSON response
 */
function hbc_check_booking_conflicts_inline() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hbc_booking_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'hall-booking-calendar')));
        return;
    }

    $room_ids     = isset($_POST['room_ids']) && is_array($_POST['room_ids']) ? array_map('intval', $_POST['room_ids']) : array();
    $booking_date = sanitize_text_field(isset($_POST['booking_date']) ? $_POST['booking_date'] : '');
    $start_time   = sanitize_text_field(isset($_POST['start_time']) ? $_POST['start_time'] : '');
    $end_time     = sanitize_text_field(isset($_POST['end_time']) ? $_POST['end_time'] : '');

    if (empty($room_ids) || empty($booking_date) || empty($start_time) || empty($end_time)) {
        wp_send_json_success(array('has_conflict' => false));
        return;
    }

    // Basic time sanity check
    if ($start_time >= $end_time) {
        wp_send_json_success(array('has_conflict' => false));
        return;
    }

    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    $conflicting_rooms = array();
    foreach ($room_ids as $rid) {
        if (hbc_check_booking_conflict($rid, $booking_date, $start_time, $end_time)) {
            $room_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $rooms_table WHERE id = %d", $rid));
            if ($room_name) {
                $conflicting_rooms[] = $room_name;
            }
        }
    }

    if (!empty($conflicting_rooms)) {
        wp_send_json_success(array(
            'has_conflict'      => true,
            'conflicting_rooms' => $conflicting_rooms,
            'message'           => sprintf(
                __('Conflict: %s already has a booking that overlaps this time slot.', 'hall-booking-calendar'),
                implode(', ', $conflicting_rooms)
            ),
        ));
    } else {
        wp_send_json_success(array('has_conflict' => false));
    }
}
add_action('wp_ajax_hbc_check_conflicts_inline', 'hbc_check_booking_conflicts_inline');
add_action('wp_ajax_nopriv_hbc_check_conflicts_inline', 'hbc_check_booking_conflicts_inline');

/**
 * Export book-in form submissions for a booking as a CSV file
 *
 * Triggered by a form POST from the admin booking detail page.
 * Runs on admin_init so headers can be sent before any output.
 *
 * @since 1.18.0
 * @return void
 */
function hbc_export_book_in_submissions_csv() {
    if (!isset($_POST['hbc_export_book_in_csv']) || !isset($_POST['hbc_book_in_export_nonce'])) {
        return;
    }

    $booking_id = intval($_POST['hbc_book_in_export_booking_id']);
    if (!$booking_id) {
        return;
    }

    if (!wp_verify_nonce($_POST['hbc_book_in_export_nonce'], 'hbc_export_book_in_' . $booking_id)) {
        wp_die(__('Security check failed', 'hall-booking-calendar'));
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access', 'hall-booking-calendar'));
    }

    global $wpdb;
    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hbc_form_submissions WHERE booking_id = %d ORDER BY submitted_at ASC",
        $booking_id
    ));

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT purpose, booking_date FROM {$wpdb->prefix}hbc_bookings WHERE id = %d",
        $booking_id
    ));

    $filename = 'attendees-booking-' . $booking_id;
    if ($booking) {
        $filename .= '-' . sanitize_title($booking->purpose) . '-' . $booking->booking_date;
    }
    $filename .= '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

    fputcsv($out, array(
        'Full Name', 'Email', 'Phone', 'Masonic Rank',
        'Attendance', 'Membership', 'Lodge Name',
        'Meal Choice', 'Vegetarian Alternative', 'Dietary Requirements',
        'Additional Comments', 'Submitted At',
    ));

    $attendance_labels = array(
        'attending_dinner'    => 'Attending with dinner',
        'attending_no_dinner' => 'Attending without dinner',
        'not_attending'       => 'Not attending',
    );

    foreach ($submissions as $s) {
        fputcsv($out, array(
            $s->full_name,
            $s->email,
            $s->phone,
            $s->masonic_rank,
            isset($attendance_labels[$s->attendance_type]) ? $attendance_labels[$s->attendance_type] : $s->attendance_type,
            'member' === $s->membership_type ? 'Member' : 'Guest',
            $s->lodge_name,
            $s->meal_choice,
            $s->vegetarian_alternative ? 'Yes' : 'No',
            $s->dietary_requirements,
            $s->additional_comments,
            $s->submitted_at,
        ));
    }

    fclose($out);
    exit;
}
add_action('admin_init', 'hbc_export_book_in_submissions_csv');
