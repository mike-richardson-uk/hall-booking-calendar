<?php
/**
 * Frontend Calendar Display
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register shortcode for calendar display
 *
 * Main shortcode handler that routes to different views based on parameters and URL args.
 *
 * Shortcode: [hall_booking_calendar view="calendar|agenda" group="all|{group_id}"]
 *
 * Views:
 * - calendar: Monthly calendar grid showing room availability (default)
 * - agenda: Paginated list of upcoming bookings
 *
 * URL parameters handled:
 * - ?booking_id=X: Shows single booking detail page
 * - ?action=book: Shows full-page booking form
 * - ?month=X&year=Y: Navigate to specific month in calendar view
 * - ?group_filter=X: Filter bookings by group
 *
 * @since 1.0.0
 * @param array $atts Shortcode attributes {
 *     @type string $view  View type: 'calendar' or 'agenda'. Default 'calendar'.
 *     @type string $group Group filter: 'all' or group ID. Default 'all'.
 * }
 * @return string HTML output for the requested view
 */
function hbc_calendar_shortcode($atts) {
    $atts = shortcode_atts(array(
        'view' => 'calendar',
        'group' => 'all',
        'room' => 'all',
        'items' => ''
    ), $atts);

    ob_start();

    // Check if viewing single booking detail page
    if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
        echo hbc_display_single_booking(intval($_GET['booking_id']));
    }
    // Check if showing full-page booking form
    elseif (isset($_GET['action']) && $_GET['action'] === 'book') {
        echo hbc_display_booking_page();
    }
    // Compact agenda view - abbreviated single-line-per-event list
    elseif ($atts['view'] == 'compact') {
        $items = !empty($atts['items']) ? intval($atts['items']) : 0;
        echo hbc_display_compact_agenda($atts['group'], $items);
    }
    // Agenda view - list of upcoming bookings
    elseif ($atts['view'] == 'agenda') {
        echo hbc_display_agenda($atts['group']);
    }
    // Calendar view (default) - monthly grid
    elseif ($atts['view'] == 'calendar') {
        hbc_display_calendar($atts['group'], $atts['room']);
    }
    // Standalone booking form (fallback, rarely used)
    else {
        hbc_display_booking_form();
    }

    return ob_get_clean();
}
add_shortcode('hall_booking_calendar', 'hbc_calendar_shortcode');

/**
 * Register shortcode for direct booking form access
 *
 * Shortcode: [hall_booking_form group="" room=""]
 *
 * Displays the booking form directly without requiring the calendar view.
 * Optionally pre-selects a group or room.
 *
 * @since 1.5.0
 * @param array $atts Shortcode attributes {
 *     @type string $group Group ID to pre-select. Default ''.
 *     @type string $room  Room ID to pre-select. Default ''.
 * }
 * @return string HTML output for the booking form
 */
function hbc_booking_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'group' => '',
        'room' => ''
    ), $atts);

    ob_start();

    // Check if viewing a single booking detail (linked from confirmation)
    if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
        echo hbc_display_single_booking(intval($_GET['booking_id']));
    } else {
        // Get pre-selected date if provided via URL
        $selected_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        ?>
        <div class="hbc-booking-page">
            <div class="hbc-booking-page-header">
                <h2><?php _e('Book a Room', 'hall-booking-calendar'); ?></h2>
            </div>
            <div class="hbc-booking-page-content">
                <?php hbc_render_booking_form($selected_date, $atts['group'], $atts['room']); ?>
            </div>
        </div>
        <?php
    }

    return ob_get_clean();
}
add_shortcode('hall_booking_form', 'hbc_booking_form_shortcode');

/**
 * Display calendar view
 *
 * Renders a monthly calendar grid showing room availability and bookings.
 * Each day shows color-coded indicators for each room (booked or available).
 * Users can navigate between months and filter by group.
 *
 * Features:
 * - Month navigation (prev/next)
 * - Room legend with capacity
 * - Group filter dropdown
 * - Visual availability indicators per room
 * - Clickable bookings to view details
 * - Highlights today's date
 * - Grays out past dates
 * - "Book" buttons for available dates
 *
 * @since 1.0.0
 * @param string $group_filter Group ID to filter by, or 'all' for no filter. Default 'all'.
 * @return void Outputs HTML directly
 */
function hbc_display_calendar($group_filter = 'all', $room_filter = 'all') {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    // Check if tables exist - safety check for activation issues
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$rooms_table'");
    if (!$table_exists) {
        return '<div class="hbc-error"><p>' . __('Hall Booking Calendar plugin is not properly activated. Please activate the plugin first.', 'hall-booking-calendar') . '</p></div>';
    }

    // Get current month and year from URL or use current date
    $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    // Get group filter from URL if not set via shortcode
    if ($group_filter === 'all' && isset($_GET['group_filter'])) {
        $group_filter = sanitize_text_field($_GET['group_filter']);
    }

    // Get room filter from URL if not set via shortcode
    if ($room_filter === 'all' && isset($_GET['room_filter'])) {
        $room_filter = sanitize_text_field($_GET['room_filter']);
    }

    // Get all active rooms
    $rooms = $wpdb->get_results("SELECT * FROM $rooms_table WHERE status = 'active' ORDER BY id ASC");

    // Get all active groups for the filter dropdown
    $groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE status = 'active' ORDER BY name ASC");

    // Determine which rooms to display in the calendar grid
    $display_rooms = $rooms;
    if ($room_filter !== 'all' && is_numeric($room_filter)) {
        $display_rooms = array_filter($rooms, function($room) use ($room_filter) {
            return $room->id == intval($room_filter);
        });
    }

    // Get bookings for the current month using junction table for multi-room support
    $first_day = date('Y-m-01', strtotime("$current_year-$current_month-01"));
    $last_day = date('Y-m-t', strtotime("$current_year-$current_month-01"));
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    // Build query with group and room filters
    $where_clauses = array("b.booking_date BETWEEN %s AND %s", "b.status != 'cancelled'");
    $params = array($first_day, $last_day);

    if ($group_filter !== 'all' && is_numeric($group_filter)) {
        $where_clauses[] = "b.group_id = %d";
        $params[] = intval($group_filter);
    }

    if ($room_filter !== 'all' && is_numeric($room_filter)) {
        $where_clauses[] = "br.room_id = %d";
        $params[] = intval($room_filter);
    }

    $where = 'WHERE ' . implode(' AND ', $where_clauses);

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, br.room_id as br_room_id FROM $bookings_table b
         INNER JOIN $booking_rooms_table br ON b.id = br.booking_id
         $where",
        $params
    ));

    // Organize bookings by date and room (using junction table room_id)
    $bookings_by_date = array();
    foreach ($bookings as $booking) {
        $date_key = $booking->booking_date;
        $room_key = $booking->br_room_id;
        if (!isset($bookings_by_date[$date_key])) {
            $bookings_by_date[$date_key] = array();
        }
        if (!isset($bookings_by_date[$date_key][$room_key])) {
            $bookings_by_date[$date_key][$room_key] = array();
        }
        // Avoid duplicate booking entries for same room (shouldn't happen, but safety)
        $already_added = false;
        foreach ($bookings_by_date[$date_key][$room_key] as $existing) {
            if ($existing->id === $booking->id) {
                $already_added = true;
                break;
            }
        }
        if (!$already_added) {
            $bookings_by_date[$date_key][$room_key][] = $booking;
        }
    }

    // Build base URL preserving current month/year
    $base_url = remove_query_arg(array('group_filter', 'room_filter'));

    ?>
    <div class="hbc-calendar-container">
        <div class="hbc-calendar-header">
            <div class="hbc-calendar-nav">
                <a href="<?php echo add_query_arg(array('month' => ($current_month == 1 ? 12 : $current_month - 1), 'year' => ($current_month == 1 ? $current_year - 1 : $current_year))); ?>" class="hbc-nav-btn">&laquo; <?php _e('Previous', 'hall-booking-calendar'); ?></a>
                <h2><?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?></h2>
                <a href="<?php echo add_query_arg(array('month' => ($current_month == 12 ? 1 : $current_month + 1), 'year' => ($current_month == 12 ? $current_year + 1 : $current_year))); ?>" class="hbc-nav-btn"><?php _e('Next', 'hall-booking-calendar'); ?> &raquo;</a>
            </div>

            <div class="hbc-calendar-filters">
                <?php if ($groups) : ?>
                <div class="hbc-calendar-filter">
                    <label for="hbc-cal-group-filter"><?php _e('Group:', 'hall-booking-calendar'); ?></label>
                    <select id="hbc-cal-group-filter" onchange="if(this.value) window.location.href=this.value;">
                        <option value="<?php echo esc_url(remove_query_arg('group_filter')); ?>"><?php _e('All Groups', 'hall-booking-calendar'); ?></option>
                        <?php foreach ($groups as $group) : ?>
                            <option value="<?php echo esc_url(add_query_arg('group_filter', $group->id)); ?>" <?php selected($group_filter, $group->id); ?>>
                                <?php echo esc_html($group->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($rooms) : ?>
                <div class="hbc-calendar-filter">
                    <label for="hbc-cal-room-filter"><?php _e('Room:', 'hall-booking-calendar'); ?></label>
                    <select id="hbc-cal-room-filter" onchange="if(this.value) window.location.href=this.value;">
                        <option value="<?php echo esc_url(remove_query_arg('room_filter')); ?>"><?php _e('All Rooms', 'hall-booking-calendar'); ?></option>
                        <?php foreach ($rooms as $room) : ?>
                            <option value="<?php echo esc_url(add_query_arg('room_filter', $room->id)); ?>" <?php selected($room_filter, $room->id); ?>>
                                <?php echo esc_html($room->name); ?> (<?php echo esc_html($room->capacity); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="hbc-rooms-legend">
            <h3><?php _e('Rooms:', 'hall-booking-calendar'); ?></h3>
            <ul>
                <?php foreach ($display_rooms as $room) : ?>
                    <li><span class="hbc-room-color hbc-room-<?php echo esc_attr($room->id); ?>"></span> <?php echo esc_html($room->name); ?> (<?php _e('Capacity:', 'hall-booking-calendar'); ?> <?php echo esc_html($room->capacity); ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="hbc-calendar-grid">
            <div class="hbc-calendar-day-header"><?php _e('Sunday', 'hall-booking-calendar'); ?></div>
            <div class="hbc-calendar-day-header"><?php _e('Monday', 'hall-booking-calendar'); ?></div>
            <div class="hbc-calendar-day-header"><?php _e('Tuesday', 'hall-booking-calendar'); ?></div>
            <div class="hbc-calendar-day-header"><?php _e('Wednesday', 'hall-booking-calendar'); ?></div>
            <div class="hbc-calendar-day-header"><?php _e('Thursday', 'hall-booking-calendar'); ?></div>
            <div class="hbc-calendar-day-header"><?php _e('Friday', 'hall-booking-calendar'); ?></div>
            <div class="hbc-calendar-day-header"><?php _e('Saturday', 'hall-booking-calendar'); ?></div>

            <?php
            $days_in_month = date('t', strtotime("$current_year-$current_month-01"));
            $first_day_of_week = date('w', strtotime("$current_year-$current_month-01"));

            // Empty cells before the first day
            for ($i = 0; $i < $first_day_of_week; $i++) {
                echo '<div class="hbc-calendar-day hbc-empty"></div>';
            }

            // Days of the month
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                $today_class = (date('Y-m-d') == $date) ? 'hbc-today' : '';
                $past_class = (strtotime($date) < strtotime(date('Y-m-d'))) ? 'hbc-past' : '';

                echo '<div class="hbc-calendar-day ' . $today_class . ' ' . $past_class . '">';
                echo '<div class="hbc-day-number">' . $day . '</div>';

                if (isset($bookings_by_date[$date])) {
                    echo '<div class="hbc-day-bookings">';
                    foreach ($display_rooms as $room) {
                        if (isset($bookings_by_date[$date][$room->id])) {
                            $count = count($bookings_by_date[$date][$room->id]);
                            $first_booking = $bookings_by_date[$date][$room->id][0];
                            $booking_url = add_query_arg('booking_id', $first_booking->id);
                            echo '<a href="' . esc_url($booking_url) . '" class="hbc-booking-indicator hbc-room-' . esc_attr($room->id) . '" title="' . esc_attr($room->name . ': ' . $count . ' booking(s) - Click to view') . '"></a>';
                        } else {
                            echo '<div class="hbc-booking-indicator hbc-available" title="' . esc_attr($room->name . ': Available') . '"></div>';
                        }
                    }
                    echo '</div>';

                    // Add view bookings link for days with bookings
                    $day_bookings_count = 0;
                    foreach ($bookings_by_date[$date] as $room_bookings) {
                        $day_bookings_count += count($room_bookings);
                    }
                    if ($day_bookings_count > 0) {
                        $first_booking_id = $bookings_by_date[$date][array_key_first($bookings_by_date[$date])][0]->id;
                        echo '<a href="' . esc_url(add_query_arg('booking_id', $first_booking_id)) . '" class="hbc-view-bookings-link" title="' . sprintf(__('%d booking(s) on this day', 'hall-booking-calendar'), $day_bookings_count) . '">' . __('View', 'hall-booking-calendar') . '</a>';
                    }
                } else {
                    echo '<div class="hbc-day-bookings">';
                    foreach ($display_rooms as $room) {
                        echo '<div class="hbc-booking-indicator hbc-available" title="' . esc_attr($room->name . ': Available') . '"></div>';
                    }
                    echo '</div>';
                }

                if (!$past_class) {
                    $book_url = add_query_arg(array('action' => 'book', 'date' => $date));
                    echo '<a href="' . esc_url($book_url) . '" class="hbc-book-btn">' . __('Book', 'hall-booking-calendar') . '</a>';
                }

                echo '</div>';
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Display booking page (full page version)
 */
function hbc_display_booking_page() {
    // Get pre-selected date if provided
    $selected_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';

    ob_start();
    ?>
    <div class="hbc-booking-page">
        <div class="hbc-booking-page-header">
            <h2><?php _e('Book a Room', 'hall-booking-calendar'); ?></h2>
            <a href="<?php echo esc_url(remove_query_arg(array('action', 'date'))); ?>" class="hbc-back-to-calendar">
                &larr; <?php _e('Back to Calendar', 'hall-booking-calendar'); ?>
            </a>
        </div>

        <div class="hbc-booking-page-content">
            <?php hbc_render_booking_form($selected_date); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Display booking form (legacy - for shortcode use)
 */
function hbc_display_booking_form() {
    ?>
    <div class="hbc-booking-form-container">
        <h2><?php _e('Book a Room', 'hall-booking-calendar'); ?></h2>
        <?php hbc_render_booking_form(); ?>
    </div>
    <?php
}

/**
 * Render booking form
 */
function hbc_render_booking_form($selected_date = '', $preselect_group = '', $preselect_room = '') {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Check if tables exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$rooms_table'");
    if (!$table_exists) {
        echo '<div class="hbc-error"><p>' . __('Hall Booking Calendar plugin is not properly activated. Please activate the plugin first.', 'hall-booking-calendar') . '</p></div>';
        return;
    }

    $rooms = $wpdb->get_results($wpdb->prepare("SELECT * FROM $rooms_table WHERE status = %s ORDER BY id ASC", 'active'));
    $groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM $groups_table WHERE status = %s ORDER BY name ASC", 'active'));

    $current_user = wp_get_current_user();
    $require_password = get_option('hbc_require_password', '0');

    // Validate and sanitize the selected date
    if (!empty($selected_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
        $date_value = $selected_date;
    } else {
        $date_value = '';
    }
    ?>
    <div class="hbc-form-notice">
        <p><strong>📅 Book a Hall Room</strong></p>
        <p>Please fill out the form below to submit your booking request. All fields marked with <span class="required">*</span> are required. You will see a confirmation message after submitting.</p>
    </div>

    <form id="hbc-booking-form" method="post" enctype="multipart/form-data">

        <div class="hbc-form-message"></div>

        <?php if ($require_password == '1') : ?>
        <!-- Password Verification -->
        <div class="hbc-form-section">
            <h3><?php _e('Authentication', 'hall-booking-calendar'); ?></h3>
            <div class="hbc-form-row">
                <label for="hbc_booking_password_input"><?php _e('Booking Password:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
                <input type="password" id="hbc_booking_password_input" name="booking_password_input" required>
                <p class="description"><?php _e('Enter the booking password to make a reservation.', 'hall-booking-calendar'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Room, Date, and Time -->
        <div class="hbc-form-section">
            <h3><?php _e('Room & Time', 'hall-booking-calendar'); ?></h3>

            <div class="hbc-form-row">
                <label><?php _e('Select Room(s):', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
                <div class="hbc-room-checkboxes" id="hbc_room_checkboxes">
                    <?php
                    $preselect_rooms = array();
                    if (!empty($preselect_room)) {
                        $preselect_rooms = array_map('intval', explode(',', $preselect_room));
                    }
                    foreach ($rooms as $room) : ?>
                        <label class="hbc-room-checkbox-label">
                            <input type="checkbox" name="room_ids[]" value="<?php echo esc_attr($room->id); ?>" <?php checked(in_array($room->id, $preselect_rooms)); ?>>
                            <span class="hbc-room-checkbox-color hbc-room-<?php echo esc_attr($room->id); ?>"></span>
                            <?php echo esc_html($room->name . ' (Capacity: ' . $room->capacity . ')'); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><?php _e('You can select multiple rooms for the same booking.', 'hall-booking-calendar'); ?></p>
            </div>

            <div class="hbc-form-row">
                <label for="hbc_booking_date"><?php _e('Booking Date:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
                <input type="date" id="hbc_booking_date" name="booking_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo esc_attr($date_value); ?>" required>
            </div>

            <div class="hbc-form-row hbc-form-row-half">
                <div>
                    <label for="hbc_start_time"><?php _e('Start Time:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
                    <input type="time" id="hbc_start_time" name="start_time" required>
                </div>
                <div>
                    <label for="hbc_end_time"><?php _e('End Time:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
                    <input type="time" id="hbc_end_time" name="end_time" required>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="hbc-form-section">
            <h3><?php _e('Your Information', 'hall-booking-calendar'); ?></h3>

            <div class="hbc-form-row">
                <label for="hbc_user_name"><?php _e('Your Name:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
                <input type="text" id="hbc_user_name" name="user_name" value="<?php echo esc_attr($current_user->display_name); ?>" required>
            </div>

            <div class="hbc-form-row">
                <label for="hbc_user_email"><?php _e('Your Email:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
                <input type="email" id="hbc_user_email" name="user_email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
            </div>

            <div class="hbc-form-row">
                <label for="hbc_group_id"><?php _e('Select Group:', 'hall-booking-calendar'); ?></label>
                <select id="hbc_group_id" name="group_id">
                    <option value=""><?php _e('-- Select a Group (Optional) --', 'hall-booking-calendar'); ?></option>
                    <?php foreach ($groups as $group) : ?>
                        <option value="<?php echo esc_attr($group->id); ?>" <?php selected($preselect_group, $group->id); ?>><?php echo esc_html($group->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="hbc-form-section">
            <h3><?php _e('Additional Details', 'hall-booking-calendar'); ?></h3>

            <div class="hbc-form-row">
                <label for="hbc_purpose"><?php _e('Purpose of Booking:', 'hall-booking-calendar'); ?></label>
                <textarea id="hbc_purpose" name="purpose" rows="3"></textarea>
            </div>

            <div class="hbc-form-row">
                <label for="hbc_description"><?php _e('Description:', 'hall-booking-calendar'); ?></label>
                <textarea id="hbc_description" name="description" rows="4" placeholder="<?php _e('Add any additional details about your booking...', 'hall-booking-calendar'); ?>"></textarea>
            </div>

            <div class="hbc-form-row">
                <label for="hbc_file_upload"><?php _e('Attach File (PDF only):', 'hall-booking-calendar'); ?></label>
                <input type="file" id="hbc_file_upload" name="booking_file" accept=".pdf,application/pdf">
                <p class="description"><?php _e('Maximum file size: 5MB. PDF files only.', 'hall-booking-calendar'); ?></p>
                <div class="hbc-file-error" style="display: none; color: red;"></div>
            </div>

            <!-- Recurring/Multi-date Booking Options -->
            <div class="hbc-form-row hbc-recurring-section">
                <label>
                    <input type="checkbox" id="hbc_is_recurring" name="is_recurring" value="1">
                    <?php _e('Repeat this booking', 'hall-booking-calendar'); ?>
                </label>
            </div>

            <div id="hbc-recurring-options" class="hbc-recurring-options" style="display: none;">
                <div class="hbc-form-row">
                    <label for="hbc_recurrence_pattern"><?php _e('Repeat:', 'hall-booking-calendar'); ?></label>
                    <select id="hbc_recurrence_pattern" name="recurrence_pattern">
                        <option value=""><?php _e('Select Pattern', 'hall-booking-calendar'); ?></option>
                        <option value="daily"><?php _e('Daily', 'hall-booking-calendar'); ?></option>
                        <option value="weekly"><?php _e('Weekly', 'hall-booking-calendar'); ?></option>
                        <option value="biweekly"><?php _e('Every 2 Weeks', 'hall-booking-calendar'); ?></option>
                        <option value="monthly"><?php _e('Monthly (Same Date)', 'hall-booking-calendar'); ?></option>
                        <option value="monthly_weekday"><?php _e('Monthly (Same Weekday)', 'hall-booking-calendar'); ?></option>
                    </select>
                    <p class="description"><?php _e('Example: "Monthly (Same Weekday)" means every third Wednesday', 'hall-booking-calendar'); ?></p>
                </div>

                <div class="hbc-form-row">
                    <label for="hbc_recurrence_end"><?php _e('Repeat Until:', 'hall-booking-calendar'); ?></label>
                    <input type="date" id="hbc_recurrence_end" name="recurrence_end" min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="hbc-form-row">
                <label>
                    <input type="checkbox" id="hbc_add_multiple_dates" value="1">
                    <?php _e('Book multiple specific dates', 'hall-booking-calendar'); ?>
                </label>
            </div>

            <div id="hbc-multiple-dates-section" class="hbc-multiple-dates" style="display: none;">
                <div class="hbc-form-row">
                    <label><?php _e('Additional Dates:', 'hall-booking-calendar'); ?></label>
                    <div id="hbc-additional-dates-container">
                        <input type="date" class="hbc-additional-date" name="additional_dates[]" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="button" id="hbc-add-date-btn" class="button"><?php _e('+ Add Another Date', 'hall-booking-calendar'); ?></button>
                    <p class="description"><?php _e('Same time will be used for all selected dates', 'hall-booking-calendar'); ?></p>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="hbc-form-actions">
            <button type="submit" class="hbc-submit-btn button button-primary"><?php _e('Submit Booking', 'hall-booking-calendar'); ?></button>
        </div>

    </form>
    <?php
}

/**
 * Display agenda view of upcoming bookings
 */
function hbc_display_agenda($group_filter = 'all') {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    // Check if tables exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
    if (!$table_exists) {
        return '<div class="hbc-error"><p>' . __('Hall Booking Calendar plugin is not properly activated. Please activate the plugin first.', 'hall-booking-calendar') . '</p></div>';
    }

    // Get current page
    $paged = isset($_GET['booking_page']) ? max(1, intval($_GET['booking_page'])) : 1;
    $per_page = intval(get_option('hbc_agenda_limit', 10));
    if ($per_page < 1) {
        $per_page = 10;
    }
    $offset = ($paged - 1) * $per_page;

    // Get group filter from URL if not set
    if ($group_filter === 'all' && isset($_GET['group_filter'])) {
        $group_filter = sanitize_text_field($_GET['group_filter']);
    }

    // Get room filter from URL
    $room_filter = isset($_GET['room_filter']) ? sanitize_text_field($_GET['room_filter']) : 'all';

    // Get all active groups and rooms for the filter dropdowns
    $groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE status = 'active' ORDER BY name ASC");
    $rooms = $wpdb->get_results("SELECT * FROM $rooms_table WHERE status = 'active' ORDER BY id ASC");

    // Build query for upcoming bookings
    $today = date('Y-m-d');
    $where = "WHERE b.booking_date >= %s AND b.status != 'cancelled'";
    $params = array($today);

    if ($group_filter !== 'all' && is_numeric($group_filter)) {
        $where .= " AND b.group_id = %d";
        $params[] = intval($group_filter);
    }

    if ($room_filter !== 'all' && is_numeric($room_filter)) {
        $where .= " AND b.room_id = %d";
        $params[] = intval($room_filter);
    }

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM $bookings_table b $where";
    $total_bookings = $wpdb->get_var($wpdb->prepare($count_sql, $params));
    $total_pages = ceil($total_bookings / $per_page);

    // Get bookings for current page
    $sql = "SELECT b.*, r.name as room_name, r.capacity, g.name as group_name
            FROM $bookings_table b
            LEFT JOIN $rooms_table r ON b.room_id = r.id
            LEFT JOIN $groups_table g ON b.group_id = g.id
            $where
            ORDER BY b.booking_date ASC, b.start_time ASC
            LIMIT %d OFFSET %d";

    $params[] = $per_page;
    $params[] = $offset;

    $bookings = $wpdb->get_results($wpdb->prepare($sql, $params));

    // Pre-fetch all room names per booking via junction table
    $agenda_booking_rooms = array();
    $agenda_booking_ids = wp_list_pluck($bookings, 'id');
    if (!empty($agenda_booking_ids)) {
        $ids_placeholder = implode(',', array_map('intval', $agenda_booking_ids));
        $room_rows = $wpdb->get_results(
            "SELECT br.booking_id, r.name FROM $booking_rooms_table br
             INNER JOIN $rooms_table r ON br.room_id = r.id
             WHERE br.booking_id IN ($ids_placeholder) ORDER BY r.name ASC"
        );
        foreach ($room_rows as $row) {
            $agenda_booking_rooms[$row->booking_id][] = $row->name;
        }
    }

    ob_start();
    ?>
    <div class="hbc-agenda-container">
        <div class="hbc-agenda-header">
            <h2><?php _e('Upcoming Bookings', 'hall-booking-calendar'); ?></h2>

            <div class="hbc-agenda-filters">
                <div class="hbc-agenda-filter">
                    <label for="hbc-agenda-group-filter"><?php _e('Group:', 'hall-booking-calendar'); ?></label>
                    <select id="hbc-agenda-group-filter" onchange="if(this.value) window.location.href=this.value;">
                        <option value="<?php echo esc_url(remove_query_arg(array('group_filter', 'booking_page'))); ?>"><?php _e('All Groups', 'hall-booking-calendar'); ?></option>
                        <?php foreach ($groups as $group) : ?>
                            <option value="<?php echo esc_url(add_query_arg(array('group_filter' => $group->id, 'booking_page' => 1))); ?>" <?php selected($group_filter, $group->id); ?>>
                                <?php echo esc_html($group->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="hbc-agenda-filter">
                    <label for="hbc-agenda-room-filter"><?php _e('Room:', 'hall-booking-calendar'); ?></label>
                    <select id="hbc-agenda-room-filter" onchange="if(this.value) window.location.href=this.value;">
                        <option value="<?php echo esc_url(remove_query_arg(array('room_filter', 'booking_page'))); ?>"><?php _e('All Rooms', 'hall-booking-calendar'); ?></option>
                        <?php foreach ($rooms as $room) : ?>
                            <option value="<?php echo esc_url(add_query_arg(array('room_filter' => $room->id, 'booking_page' => 1))); ?>" <?php selected($room_filter, $room->id); ?>>
                                <?php echo esc_html($room->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($bookings) : ?>
            <div class="hbc-agenda-list">
                <?php 
                $current_date = '';
                foreach ($bookings as $booking) : 
                    $booking_date = date('Y-m-d', strtotime($booking->booking_date));
                    
                    // Show date header when date changes
                    if ($booking_date !== $current_date) :
                        $current_date = $booking_date;
                        ?>
                        <div class="hbc-agenda-date-header">
                            <h3><?php echo date('l, F j, Y', strtotime($booking->booking_date)); ?></h3>
                        </div>
                    <?php endif; ?>

                    <div class="hbc-agenda-item" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                        <div class="hbc-agenda-time">
                            <span class="hbc-time-start"><?php echo date('g:i A', strtotime($booking->start_time)); ?></span>
                            <span class="hbc-time-separator">-</span>
                            <span class="hbc-time-end"><?php echo date('g:i A', strtotime($booking->end_time)); ?></span>
                        </div>
                        
                        <div class="hbc-agenda-details">
                            <h4 class="hbc-agenda-purpose-title"><?php echo esc_html($booking->purpose ? $booking->purpose : __('No purpose specified', 'hall-booking-calendar')); ?></h4>
                            <div class="hbc-agenda-meta">
                                <span class="hbc-agenda-room-info"><?php
                                    $agenda_rooms_display = isset($agenda_booking_rooms[$booking->id]) ? implode(', ', $agenda_booking_rooms[$booking->id]) : $booking->room_name;
                                    echo esc_html($agenda_rooms_display);
                                ?></span>
                            </div>
                        </div>

                        <div class="hbc-agenda-actions">
                            <a href="<?php echo esc_url(add_query_arg('booking_id', $booking->id)); ?>" class="hbc-view-booking-btn">
                                <?php _e('View Details', 'hall-booking-calendar'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1) : ?>
                <div class="hbc-agenda-pagination">
                    <?php if ($paged > 1) : ?>
                        <a href="<?php echo esc_url(add_query_arg('booking_page', $paged - 1)); ?>" class="hbc-page-btn hbc-prev">
                            &laquo; <?php _e('Previous', 'hall-booking-calendar'); ?>
                        </a>
                    <?php endif; ?>

                    <span class="hbc-page-numbers">
                        <?php 
                        for ($i = 1; $i <= $total_pages; $i++) :
                            if ($i == $paged) :
                                echo '<span class="hbc-page-number current">' . $i . '</span>';
                            else :
                                echo '<a href="' . esc_url(add_query_arg('booking_page', $i)) . '" class="hbc-page-number">' . $i . '</a>';
                            endif;
                        endfor;
                        ?>
                    </span>

                    <?php if ($paged < $total_pages) : ?>
                        <a href="<?php echo esc_url(add_query_arg('booking_page', $paged + 1)); ?>" class="hbc-page-btn hbc-next">
                            <?php _e('Next', 'hall-booking-calendar'); ?> &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <div class="hbc-agenda-empty">
                <p><?php _e('No upcoming bookings found.', 'hall-booking-calendar'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Display compact agenda view - abbreviated single-line-per-event list
 *
 * @since 1.7.5
 * @param string $group_filter Group ID to filter by, or 'all' for no filter.
 * @param int    $items        Number of items to display. 0 uses the agenda limit setting.
 * @return string HTML output
 */
function hbc_display_compact_agenda($group_filter = 'all', $items = 0) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
    if (!$table_exists) {
        return '<div class="hbc-error"><p>' . __('Hall Booking Calendar plugin is not properly activated.', 'hall-booking-calendar') . '</p></div>';
    }

    $limit = $items > 0 ? $items : intval(get_option('hbc_agenda_limit', 10));
    if ($limit < 1) {
        $limit = 10;
    }

    $today = date('Y-m-d');
    $where = "WHERE b.booking_date >= %s AND b.status != 'cancelled'";
    $params = array($today);

    if ($group_filter !== 'all' && is_numeric($group_filter)) {
        $where .= " AND b.group_id = %d";
        $params[] = intval($group_filter);
    }

    $sql = "SELECT b.*, r.name as room_name, g.name as group_name
            FROM $bookings_table b
            LEFT JOIN $rooms_table r ON b.room_id = r.id
            LEFT JOIN $groups_table g ON b.group_id = g.id
            $where
            ORDER BY b.booking_date ASC, b.start_time ASC
            LIMIT %d";
    $params[] = $limit;

    $bookings = $wpdb->get_results($wpdb->prepare($sql, $params));

    // Pre-fetch room names via junction table
    $compact_booking_rooms = array();
    $compact_ids = wp_list_pluck($bookings, 'id');
    if (!empty($compact_ids)) {
        $ids_placeholder = implode(',', array_map('intval', $compact_ids));
        $room_rows = $wpdb->get_results(
            "SELECT br.booking_id, r.name FROM $booking_rooms_table br
             INNER JOIN $rooms_table r ON br.room_id = r.id
             WHERE br.booking_id IN ($ids_placeholder) ORDER BY r.name ASC"
        );
        foreach ($room_rows as $row) {
            $compact_booking_rooms[$row->booking_id][] = $row->name;
        }
    }

    ob_start();
    ?>
    <div class="hbc-compact-agenda">
        <?php if ($bookings) : ?>
            <ul class="hbc-compact-list">
                <?php foreach ($bookings as $booking) :
                    $rooms_display = isset($compact_booking_rooms[$booking->id]) ? implode(', ', $compact_booking_rooms[$booking->id]) : $booking->room_name;
                    $date_display = date('D j M', strtotime($booking->booking_date));
                    $time_display = date('g:i A', strtotime($booking->start_time));
                    $purpose_display = $booking->purpose ? $booking->purpose : __('No purpose', 'hall-booking-calendar');
                    $booking_url = add_query_arg('booking_id', $booking->id);
                ?>
                    <li class="hbc-compact-item hbc-status-<?php echo esc_attr($booking->status); ?>">
                        <a href="<?php echo esc_url($booking_url); ?>">
                            <span class="hbc-compact-date"><?php echo esc_html($date_display); ?></span>
                            <span class="hbc-compact-time"><?php echo esc_html($time_display); ?></span>
                            <span class="hbc-compact-purpose"><?php echo esc_html(wp_trim_words($purpose_display, 8, '...')); ?></span>
                            <span class="hbc-compact-room"><?php echo esc_html($rooms_display); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="hbc-compact-empty"><?php _e('No upcoming bookings.', 'hall-booking-calendar'); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Display single booking detail page
 */
function hbc_display_single_booking($booking_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    // Check if tables exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
    if (!$table_exists) {
        return '<div class="hbc-error"><p>' . __('Hall Booking Calendar plugin is not properly activated. Please activate the plugin first.', 'hall-booking-calendar') . '</p></div>';
    }

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, r.capacity, r.description as room_description, g.name as group_name, g.id as group_id
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        LEFT JOIN $groups_table g ON b.group_id = g.id
        WHERE b.id = %d",
        $booking_id
    ));

    if (!$booking) {
        return '<div class="hbc-error"><p>' . __('Booking not found.', 'hall-booking-calendar') . '</p></div>';
    }

    // Get all rooms for this booking from junction table
    $single_booking_rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT r.name, r.capacity FROM $booking_rooms_table br
         INNER JOIN $rooms_table r ON br.room_id = r.id
         WHERE br.booking_id = %d ORDER BY r.name ASC",
        $booking_id
    ));
    $single_rooms_display = !empty($single_booking_rooms) ? implode(', ', wp_list_pluck($single_booking_rooms, 'name')) : $booking->room_name;

    // Get other bookings in the same series if applicable
    $series_bookings = array();
    if (!empty($booking->series_id)) {
        $series_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, r.name as room_name
            FROM $bookings_table b
            LEFT JOIN $rooms_table r ON b.room_id = r.id
            WHERE b.series_id = %s AND b.id != %d
            ORDER BY b.booking_date ASC, b.start_time ASC",
            $booking->series_id,
            $booking_id
        ));
    }

    ob_start();
    ?>
    <div class="hbc-single-booking">
        <div class="hbc-single-header">
            <h2><?php echo esc_html($booking->purpose); ?></h2>
            <span class="hbc-single-status hbc-status-<?php echo esc_attr($booking->status); ?>">
                <?php echo esc_html(ucfirst($booking->status)); ?>
            </span>
        </div>

        <div class="hbc-single-content">
            <div class="hbc-single-section hbc-date-time">
                <h3><?php _e('Details', 'hall-booking-calendar'); ?></h3>
                <div class="hbc-single-info">
                    <div class="hbc-info-row">
                        <span class="hbc-info-label"><?php _e('Room:', 'hall-booking-calendar'); ?></span>
                        <span class="hbc-info-value"><?php echo esc_html($single_rooms_display); ?></span>
                    </div>
                    <div class="hbc-info-row">
                        <span class="hbc-info-label"><?php _e('Date:', 'hall-booking-calendar'); ?></span>
                        <span class="hbc-info-value"><?php echo date('l, F j, Y', strtotime($booking->booking_date)); ?></span>
                    </div>
                    <div class="hbc-info-row">
                        <span class="hbc-info-label"><?php _e('Time:', 'hall-booking-calendar'); ?></span>
                        <span class="hbc-info-value">
                            <?php echo date('g:i A', strtotime($booking->start_time)); ?> -
                            <?php echo date('g:i A', strtotime($booking->end_time)); ?>
                        </span>
                    </div>
                    <?php if (!empty($booking->description)) : ?>
                    <div class="hbc-info-row">
                        <span class="hbc-info-label"><?php _e('Description:', 'hall-booking-calendar'); ?></span>
                        <span class="hbc-info-value"><?php echo esc_html($booking->description); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($booking->group_name && $booking->group_id) : ?>
            <div class="hbc-single-section hbc-group-bookings">
                <h3><?php _e('View All Future Bookings', 'hall-booking-calendar'); ?></h3>
                <p>
                    <a href="<?php echo esc_url(add_query_arg(array('view' => 'agenda', 'group_filter' => $booking->group_id), remove_query_arg('booking_id'))); ?>" class="hbc-view-group-bookings-btn">
                        <?php printf(__('View all upcoming bookings for %s', 'hall-booking-calendar'), esc_html($booking->group_name)); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>

            <?php if (!empty($series_bookings)) : ?>
            <div class="hbc-single-section hbc-series-info">
                <h3><?php _e('Part of Recurring Series', 'hall-booking-calendar'); ?></h3>
                <p class="hbc-series-description">
                    <?php 
                    printf(
                        __('This booking is part of a recurring series with %d other booking(s).', 'hall-booking-calendar'),
                        count($series_bookings)
                    ); 
                    ?>
                </p>
                <div class="hbc-series-list">
                    <?php foreach (array_slice($series_bookings, 0, 5) as $series_booking) : ?>
                        <div class="hbc-series-item">
                            <span class="hbc-series-date"><?php echo date('M j, Y', strtotime($series_booking->booking_date)); ?></span>
                            <span class="hbc-series-time">
                                <?php echo date('g:i A', strtotime($series_booking->start_time)); ?>
                            </span>
                            <a href="<?php echo esc_url(add_query_arg('booking_id', $series_booking->id)); ?>" class="hbc-series-link">
                                <?php _e('View', 'hall-booking-calendar'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($series_bookings) > 5) : ?>
                        <p class="hbc-series-more">
                            <?php printf(__('... and %d more', 'hall-booking-calendar'), count($series_bookings) - 5); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="hbc-single-actions">
            <a href="<?php echo esc_url(remove_query_arg('booking_id')); ?>" class="hbc-back-btn button">
                &larr; <?php _e('Back to Calendar', 'hall-booking-calendar'); ?>
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
