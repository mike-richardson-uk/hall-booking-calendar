<?php
/**
 * Recurring Bookings Handler
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Generate dates for recurring booking
 *
 * @param string $start_date Starting date
 * @param string $end_date End date for recurrence
 * @param string $pattern Recurrence pattern (weekly, biweekly, monthly, etc.)
 * @param array $additional_dates Additional specific dates for multi-date bookings
 * @return array Array of dates
 */
function hbc_generate_recurring_dates($start_date, $end_date, $pattern, $additional_dates = array()) {
    $dates = array();

    // Always include the start date
    $dates[] = $start_date;

    // Add any additional specific dates
    if (!empty($additional_dates)) {
        foreach ($additional_dates as $date) {
            if (!in_array($date, $dates)) {
                $dates[] = $date;
            }
        }
    }

    // If no pattern or end date, return just the dates we have
    if (empty($pattern) || empty($end_date)) {
        sort($dates);
        return $dates;
    }

    $current = strtotime($start_date);
    $end = strtotime($end_date);

    switch ($pattern) {
        case 'daily':
            while ($current <= $end) {
                $current = strtotime('+1 day', $current);
                if ($current <= $end) {
                    $date = date('Y-m-d', $current);
                    if (!in_array($date, $dates)) {
                        $dates[] = $date;
                    }
                }
            }
            break;

        case 'weekly':
            while ($current <= $end) {
                $current = strtotime('+1 week', $current);
                if ($current <= $end) {
                    $date = date('Y-m-d', $current);
                    if (!in_array($date, $dates)) {
                        $dates[] = $date;
                    }
                }
            }
            break;

        case 'biweekly':
            while ($current <= $end) {
                $current = strtotime('+2 weeks', $current);
                if ($current <= $end) {
                    $date = date('Y-m-d', $current);
                    if (!in_array($date, $dates)) {
                        $dates[] = $date;
                    }
                }
            }
            break;

        case 'monthly':
            // Same day each month
            while ($current <= $end) {
                $current = strtotime('+1 month', $current);
                if ($current <= $end) {
                    $date = date('Y-m-d', $current);
                    if (!in_array($date, $dates)) {
                        $dates[] = $date;
                    }
                }
            }
            break;

        case 'monthly_weekday':
            // e.g., "every third Wednesday"
            $day_of_week = date('l', strtotime($start_date));
            $week_of_month = ceil(date('j', strtotime($start_date)) / 7);

            $month = date('n', strtotime($start_date));
            $year = date('Y', strtotime($start_date));

            while (strtotime("$year-$month-01") <= $end) {
                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }

                // Find the Nth occurrence of the day in this month
                $first_day_of_month = strtotime("$year-$month-01");
                $first_occurrence = strtotime("first $day_of_week", $first_day_of_month);

                // If first day of month is the target day, use it
                if (date('l', $first_day_of_month) == $day_of_week) {
                    $first_occurrence = $first_day_of_month;
                }

                $target_date = strtotime('+' . ($week_of_month - 1) . ' weeks', $first_occurrence);

                // Make sure we're still in the same month
                if (date('n', $target_date) == $month && $target_date <= $end) {
                    $date = date('Y-m-d', $target_date);
                    if (!in_array($date, $dates)) {
                        $dates[] = $date;
                    }
                }
            }
            break;
    }

    sort($dates);
    return $dates;
}

/**
 * Create multiple bookings from recurring pattern
 *
 * @param array $booking_data Booking data
 * @param string $pattern Recurrence pattern
 * @param string $recurrence_end End date for recurrence
 * @param array $additional_dates Additional dates for multi-date bookings
 * @return array Result array with success status and booking IDs
 */
function hbc_create_recurring_bookings($booking_data, $pattern = '', $recurrence_end = '', $additional_dates = array()) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $start_date = $booking_data['booking_date'];
    $series_id = uniqid('series_', true);

    // Generate all dates
    $dates = hbc_generate_recurring_dates($start_date, $recurrence_end, $pattern, $additional_dates);

    $booking_ids = array();
    $conflicts = array();
    $is_recurring = !empty($pattern) && !empty($recurrence_end);

    // Check for conflicts on all dates first
    foreach ($dates as $date) {
        if (hbc_check_booking_conflict($booking_data['room_id'], $date, $booking_data['start_time'], $booking_data['end_time'])) {
            $conflicts[] = $date;
        }
    }

    if (!empty($conflicts)) {
        return array(
            'success' => false,
            'message' => sprintf(__('Booking conflicts found on the following dates: %s', 'hall-booking-calendar'), implode(', ', $conflicts)),
            'conflicts' => $conflicts
        );
    }

    // Create first booking (parent)
    $parent_data = array_merge($booking_data, array(
        'series_id' => $series_id,
        'is_recurring' => $is_recurring ? 1 : 0,
        'recurrence_pattern' => $pattern,
        'recurrence_end_date' => $recurrence_end,
        'parent_booking_id' => null
    ));

    $format = array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d');

    $result = $wpdb->insert($bookings_table, $parent_data, $format);

    if (!$result) {
        return array(
            'success' => false,
            'message' => __('Failed to create booking.', 'hall-booking-calendar')
        );
    }

    $parent_id = $wpdb->insert_id;
    $booking_ids[] = $parent_id;

    // Create child bookings for remaining dates
    $remaining_dates = array_slice($dates, 1); // Skip first date (already created)

    foreach ($remaining_dates as $date) {
        $child_data = array_merge($booking_data, array(
            'booking_date' => $date,
            'series_id' => $series_id,
            'is_recurring' => 0, // Only parent is marked as recurring
            'recurrence_pattern' => null,
            'recurrence_end_date' => null,
            'parent_booking_id' => $parent_id
        ));

        $result = $wpdb->insert($bookings_table, $child_data, $format);

        if ($result) {
            $booking_ids[] = $wpdb->insert_id;
        }
    }

    return array(
        'success' => true,
        'message' => sprintf(__('%d bookings created successfully.', 'hall-booking-calendar'), count($booking_ids)),
        'booking_ids' => $booking_ids,
        'parent_id' => $parent_id,
        'series_id' => $series_id
    );
}

/**
 * Delete recurring booking series
 *
 * @param int $booking_id Booking ID (can be parent or child)
 * @param string $scope 'single', 'future', or 'all'
 * @return bool Success status
 */
function hbc_delete_recurring_booking($booking_id, $scope = 'single') {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings_table WHERE id = %d", $booking_id));

    if (!$booking) {
        return false;
    }

    switch ($scope) {
        case 'single':
            // Delete only this booking
            return $wpdb->delete($bookings_table, array('id' => $booking_id));

        case 'future':
            // Delete this and all future bookings in the series
            if ($booking->series_id) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $bookings_table WHERE series_id = %s AND booking_date >= %s",
                    $booking->series_id,
                    $booking->booking_date
                ));
            }
            return true;

        case 'all':
            // Delete entire series
            if ($booking->series_id) {
                $wpdb->delete($bookings_table, array('series_id' => $booking->series_id));
            } else {
                $wpdb->delete($bookings_table, array('id' => $booking_id));
            }
            return true;
    }

    return false;
}

/**
 * Update recurring booking series
 *
 * @param int $booking_id Booking ID
 * @param array $data Updated data
 * @param string $scope 'single', 'future', or 'all'
 * @return bool Success status
 */
function hbc_update_recurring_booking($booking_id, $data, $scope = 'single') {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings_table WHERE id = %d", $booking_id));

    if (!$booking) {
        return false;
    }

    switch ($scope) {
        case 'single':
            // Update only this booking
            return $wpdb->update($bookings_table, $data, array('id' => $booking_id));

        case 'future':
            // Update this and all future bookings in the series
            if ($booking->series_id) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $bookings_table SET " . hbc_build_update_query($data) . " WHERE series_id = %s AND booking_date >= %s",
                    $booking->series_id,
                    $booking->booking_date
                ));
            }
            return true;

        case 'all':
            // Update entire series
            if ($booking->series_id) {
                foreach ($data as $key => $value) {
                    $wpdb->update($bookings_table, array($key => $value), array('series_id' => $booking->series_id));
                }
            } else {
                $wpdb->update($bookings_table, $data, array('id' => $booking_id));
            }
            return true;
    }

    return false;
}

/**
 * Helper function to build UPDATE query
 */
function hbc_build_update_query($data) {
    $updates = array();
    foreach ($data as $key => $value) {
        $updates[] = "`$key` = '" . esc_sql($value) . "'";
    }
    return implode(', ', $updates);
}
