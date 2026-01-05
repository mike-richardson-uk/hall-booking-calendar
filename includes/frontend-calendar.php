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
 */
function hbc_calendar_shortcode($atts) {
    $atts = shortcode_atts(array(
        'view' => 'calendar',
        'group' => 'all'
    ), $atts);

    ob_start();

    if ($atts['view'] == 'calendar') {
        hbc_display_calendar($atts['group']);
    } else {
        hbc_display_booking_form();
    }

    return ob_get_clean();
}
add_shortcode('hall_booking_calendar', 'hbc_calendar_shortcode');

/**
 * Display calendar view
 */
function hbc_display_calendar($group_filter = 'all') {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    // Check if tables exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$rooms_table'");
    if (!$table_exists) {
        return '<div class="hbc-error"><p>' . __('Hall Booking Calendar plugin is not properly activated. Please activate the plugin first.', 'hall-booking-calendar') . '</p></div>';
    }

    // Get current month and year
    $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    // Get all active rooms
    $rooms = $wpdb->get_results("SELECT * FROM $rooms_table WHERE status = 'active' ORDER BY id ASC");

    // Get all active groups for the filter dropdown
    $groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE status = 'active' ORDER BY name ASC");

    // Get bookings for the current month
    $first_day = date('Y-m-01', strtotime("$current_year-$current_month-01"));
    $last_day = date('Y-m-t', strtotime("$current_year-$current_month-01"));

    // Build query based on group filter
    if ($group_filter !== 'all' && is_numeric($group_filter)) {
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $bookings_table WHERE booking_date BETWEEN %s AND %s AND status != 'cancelled' AND group_id = %d",
            $first_day,
            $last_day,
            intval($group_filter)
        ));
    } else {
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $bookings_table WHERE booking_date BETWEEN %s AND %s AND status != 'cancelled'",
            $first_day,
            $last_day
        ));
    }

    // Organize bookings by date and room
    $bookings_by_date = array();
    foreach ($bookings as $booking) {
        $date_key = $booking->booking_date;
        if (!isset($bookings_by_date[$date_key])) {
            $bookings_by_date[$date_key] = array();
        }
        if (!isset($bookings_by_date[$date_key][$booking->room_id])) {
            $bookings_by_date[$date_key][$booking->room_id] = array();
        }
        $bookings_by_date[$date_key][$booking->room_id][] = $booking;
    }

    ?>
    <div class="hbc-calendar-container">
        <div class="hbc-calendar-header">
            <div class="hbc-calendar-nav">
                <a href="<?php echo add_query_arg(array('month' => ($current_month == 1 ? 12 : $current_month - 1), 'year' => ($current_month == 1 ? $current_year - 1 : $current_year))); ?>" class="hbc-nav-btn">&laquo; <?php _e('Previous', 'hall-booking-calendar'); ?></a>
                <h2><?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?></h2>
                <a href="<?php echo add_query_arg(array('month' => ($current_month == 12 ? 1 : $current_month + 1), 'year' => ($current_month == 12 ? $current_year + 1 : $current_year))); ?>" class="hbc-nav-btn"><?php _e('Next', 'hall-booking-calendar'); ?> &raquo;</a>
            </div>

            <?php if ($group_filter == 'all' && $groups) : ?>
            <div class="hbc-group-filter">
                <label for="hbc-group-filter-select"><?php _e('Filter by Group:', 'hall-booking-calendar'); ?></label>
                <select id="hbc-group-filter-select" onchange="if(this.value) window.location.href=this.value;">
                    <option value="<?php echo get_permalink(); ?>"><?php _e('All Groups', 'hall-booking-calendar'); ?></option>
                    <?php foreach ($groups as $group) : ?>
                        <option value="<?php echo esc_url(add_query_arg('group_filter', $group->id)); ?>"><?php echo esc_html($group->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php elseif ($group_filter !== 'all') : ?>
            <div class="hbc-group-filter">
                <?php
                $current_group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $groups_table WHERE id = %d", intval($group_filter)));
                if ($current_group) {
                    echo '<p class="hbc-group-label">' . sprintf(__('Showing bookings for: %s', 'hall-booking-calendar'), '<strong>' . esc_html($current_group->name) . '</strong>') . '</p>';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="hbc-rooms-legend">
            <h3><?php _e('Available Rooms:', 'hall-booking-calendar'); ?></h3>
            <ul>
                <?php foreach ($rooms as $room) : ?>
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
                    foreach ($rooms as $room) {
                        if (isset($bookings_by_date[$date][$room->id])) {
                            $count = count($bookings_by_date[$date][$room->id]);
                            echo '<div class="hbc-booking-indicator hbc-room-' . esc_attr($room->id) . '" title="' . esc_attr($room->name . ': ' . $count . ' booking(s)') . '"></div>';
                        } else {
                            echo '<div class="hbc-booking-indicator hbc-available" title="' . esc_attr($room->name . ': Available') . '"></div>';
                        }
                    }
                    echo '</div>';
                } else {
                    echo '<div class="hbc-day-bookings">';
                    foreach ($rooms as $room) {
                        echo '<div class="hbc-booking-indicator hbc-available" title="' . esc_attr($room->name . ': Available') . '"></div>';
                    }
                    echo '</div>';
                }

                if (!$past_class) {
                    echo '<a href="#" class="hbc-book-btn" data-date="' . esc_attr($date) . '">' . __('Book', 'hall-booking-calendar') . '</a>';
                }

                echo '</div>';
            }
            ?>
        </div>

        <div class="hbc-booking-form-modal" id="hbc-booking-modal" style="display: none;">
            <div class="hbc-modal-content">
                <span class="hbc-modal-close">&times;</span>
                <h2><?php _e('Book a Room', 'hall-booking-calendar'); ?></h2>
                <?php hbc_render_booking_form(); ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Display booking form
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
function hbc_render_booking_form() {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Check if tables exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$rooms_table'");
    if (!$table_exists) {
        echo '<div class="hbc-error"><p>' . __('Hall Booking Calendar plugin is not properly activated. Please activate the plugin first.', 'hall-booking-calendar') . '</p></div>';
        return;
    }

    $rooms = $wpdb->get_results("SELECT * FROM $rooms_table WHERE status = 'active' ORDER BY id ASC");
    $groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE status = 'active' ORDER BY name ASC");

    $current_user = wp_get_current_user();
    ?>
    <form id="hbc-booking-form" method="post">
        <div class="hbc-form-row">
            <label for="hbc_room_id"><?php _e('Select Room:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
            <select id="hbc_room_id" name="room_id" required>
                <option value=""><?php _e('-- Select a Room --', 'hall-booking-calendar'); ?></option>
                <?php foreach ($rooms as $room) : ?>
                    <option value="<?php echo esc_attr($room->id); ?>"><?php echo esc_html($room->name . ' (Capacity: ' . $room->capacity . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="hbc-form-row">
            <label for="hbc_group_id"><?php _e('Select Group:', 'hall-booking-calendar'); ?></label>
            <select id="hbc_group_id" name="group_id">
                <option value=""><?php _e('-- Select a Group (Optional) --', 'hall-booking-calendar'); ?></option>
                <?php foreach ($groups as $group) : ?>
                    <option value="<?php echo esc_attr($group->id); ?>"><?php echo esc_html($group->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="hbc-form-row">
            <label for="hbc_user_name"><?php _e('Your Name:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
            <input type="text" id="hbc_user_name" name="user_name" value="<?php echo esc_attr($current_user->display_name); ?>" required>
        </div>

        <div class="hbc-form-row">
            <label for="hbc_user_email"><?php _e('Your Email:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
            <input type="email" id="hbc_user_email" name="user_email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
        </div>

        <div class="hbc-form-row">
            <label for="hbc_booking_date"><?php _e('Booking Date:', 'hall-booking-calendar'); ?> <span class="required">*</span></label>
            <input type="date" id="hbc_booking_date" name="booking_date" min="<?php echo date('Y-m-d'); ?>" required>
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

        <div class="hbc-form-row">
            <label for="hbc_purpose"><?php _e('Purpose of Booking:', 'hall-booking-calendar'); ?></label>
            <textarea id="hbc_purpose" name="purpose" rows="4"></textarea>
        </div>

        <div class="hbc-form-message"></div>

        <div class="hbc-form-row">
            <button type="submit" class="hbc-submit-btn"><?php _e('Submit Booking', 'hall-booking-calendar'); ?></button>
        </div>
    </form>
    <?php
}
