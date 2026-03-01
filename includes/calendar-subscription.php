<?php
/**
 * Calendar Subscription Handler - iCal/ICS Feed Generation
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Generate subscription token for a user
 *
 * @param int $user_id User ID
 * @param int $group_id Optional group ID to filter
 * @param int $room_id Optional room ID to filter
 * @return string Token
 */
function hbc_generate_subscription_token($user_id = null, $group_id = null, $room_id = null) {
    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'hbc_subscriptions';

    $token = wp_generate_password(40, false);

    $wpdb->insert($subscriptions_table, array(
        'user_id' => $user_id,
        'token' => $token,
        'group_id' => $group_id,
        'room_id' => $room_id,
        'status' => 'active'
    ));

    return $token;
}

/**
 * Handle iCal feed request
 */
function hbc_handle_ical_feed() {
    if (!isset($_GET['hbc_ical']) || !isset($_GET['token'])) {
        return;
    }

    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'hbc_subscriptions';
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Check if tables exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$subscriptions_table'");
    if (!$table_exists) {
        wp_die(__('Hall Booking Calendar is not properly activated. Please activate the plugin.', 'hall-booking-calendar'));
    }

    $token = sanitize_text_field($_GET['token']);

    // Verify token and check expiration (90 days from creation)
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $subscriptions_table WHERE token = %s AND status = 'active'",
        $token
    ));

    if (!$subscription) {
        wp_die(__('Invalid or expired subscription token.', 'hall-booking-calendar'));
    }

    // Check if token has expired (90 days from creation)
    $created_time = strtotime($subscription->created_at);
    $expiry_time = strtotime('+90 days', $created_time);
    if (time() > $expiry_time) {
        // Auto-expire the token
        $wpdb->update($subscriptions_table, array('status' => 'inactive'), array('id' => $subscription->id));
        wp_die(__('This subscription token has expired. Please create a new subscription.', 'hall-booking-calendar'));
    }

    // Implement rate limiting: max 100 requests per hour per token
    $rate_limit_key = 'hbc_token_rate_limit_' . md5($token);
    $request_count = get_transient($rate_limit_key);

    if ($request_count === false) {
        // First request in this hour
        set_transient($rate_limit_key, 1, HOUR_IN_SECONDS);
    } else {
        $request_count = intval($request_count);
        if ($request_count >= 100) {
            wp_die(__('Rate limit exceeded. Please try again later.', 'hall-booking-calendar'), 'Too Many Requests', array('response' => 429));
        }
        set_transient($rate_limit_key, $request_count + 1, HOUR_IN_SECONDS);
    }

    // Update last accessed time
    $wpdb->update($subscriptions_table, array('last_accessed' => current_time('mysql')), array('id' => $subscription->id));

    // Build query for bookings with proper parameterization
    $conditions = array("b.status IN ('confirmed', 'pending')");
    $params = array();

    // Add filters based on subscription settings
    if ($subscription->group_id) {
        $conditions[] = 'b.group_id = %d';
        $params[] = $subscription->group_id;
    }

    if ($subscription->room_id) {
        $conditions[] = 'b.room_id = %d';
        $params[] = $subscription->room_id;
    }

    // Get future bookings (from 1 month ago to 6 months ahead)
    $conditions[] = "b.booking_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    $conditions[] = "b.booking_date <= DATE_ADD(NOW(), INTERVAL 6 MONTH)";

    $where_clause = implode(' AND ', $conditions);

    $sql = "SELECT b.*, r.name as room_name, g.name as group_name
            FROM $bookings_table b
            LEFT JOIN $rooms_table r ON b.room_id = r.id
            LEFT JOIN $groups_table g ON b.group_id = g.id
            WHERE {$where_clause}
            ORDER BY b.booking_date ASC, b.start_time ASC";

    // Use prepared statement if we have parameters
    if (!empty($params)) {
        $bookings = $wpdb->get_results($wpdb->prepare($sql, $params));
    } else {
        $bookings = $wpdb->get_results($sql);
    }

    // Generate iCal content
    $ical_content = hbc_generate_ical_content($bookings);

    // Set headers for iCal file
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="hall-bookings.ics"');

    echo $ical_content;
    exit;
}
add_action('init', 'hbc_handle_ical_feed');

/**
 * Generate iCal/ICS content
 *
 * @param array $bookings Array of booking objects
 * @return string iCal formatted content
 */
function hbc_generate_ical_content($bookings) {
    $site_name = get_bloginfo('name');
    $site_url = get_site_url();

    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//" . $site_name . "//Hall Booking Calendar//EN\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";
    $ical .= "X-WR-CALNAME:Hall Bookings\r\n";
    $ical .= "X-WR-TIMEZONE:UTC\r\n";

    foreach ($bookings as $booking) {
        $ical .= hbc_generate_ical_event($booking, $site_url);
    }

    $ical .= "END:VCALENDAR\r\n";

    return $ical;
}

/**
 * Generate single iCal event
 *
 * @param object $booking Booking object
 * @param string $site_url Site URL
 * @return string iCal event
 */
function hbc_generate_ical_event($booking, $site_url) {
    global $wpdb;
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    // Get all room names for this booking
    $room_names = $wpdb->get_col($wpdb->prepare(
        "SELECT r.name FROM $booking_rooms_table br
         INNER JOIN $rooms_table r ON br.room_id = r.id
         WHERE br.booking_id = %d ORDER BY r.name ASC",
        $booking->id
    ));
    $rooms_display = !empty($room_names) ? implode(', ', $room_names) : $booking->room_name;

    $event = "BEGIN:VEVENT\r\n";
    $event .= "UID:hbc-booking-" . $booking->id . "@" . parse_url($site_url, PHP_URL_HOST) . "\r\n";

    // Format dates in iCal format (YYYYMMDDTHHMMSS)
    $start_datetime = date('Ymd\THis', strtotime($booking->booking_date . ' ' . $booking->start_time));
    $end_datetime = date('Ymd\THis', strtotime($booking->booking_date . ' ' . $booking->end_time));
    $created_datetime = date('Ymd\THis', strtotime($booking->created_at));

    $event .= "DTSTART:" . $start_datetime . "\r\n";
    $event .= "DTEND:" . $end_datetime . "\r\n";
    $event .= "DTSTAMP:" . $created_datetime . "\r\n";

    // Summary (title)
    $summary = $rooms_display;
    if ($booking->group_name) {
        $summary .= ' - ' . $booking->group_name;
    }
    $event .= "SUMMARY:" . hbc_escape_ical_string($summary) . "\r\n";

    // Description
    $description = "Booked by: " . $booking->user_name . "\\n";
    $description .= "Email: " . $booking->user_email . "\\n";
    if ($booking->purpose) {
        $description .= "Purpose: " . $booking->purpose . "\\n";
    }
    $description .= "Status: " . ucfirst($booking->status);
    $event .= "DESCRIPTION:" . hbc_escape_ical_string($description) . "\r\n";

    // Location
    $event .= "LOCATION:" . hbc_escape_ical_string($rooms_display) . "\r\n";

    // Status
    $status = ($booking->status == 'confirmed') ? 'CONFIRMED' : 'TENTATIVE';
    $event .= "STATUS:" . $status . "\r\n";

    // Organizer
    $event .= "ORGANIZER;CN=" . hbc_escape_ical_string($booking->user_name) . ":mailto:" . $booking->user_email . "\r\n";

    $event .= "END:VEVENT\r\n";

    return $event;
}

/**
 * Escape string for iCal format
 *
 * @param string $string String to escape
 * @return string Escaped string
 */
function hbc_escape_ical_string($string) {
    $string = str_replace(array("\r\n", "\n", "\r"), "\\n", $string);
    $string = str_replace(',', '\\,', $string);
    $string = str_replace(';', '\\;', $string);
    return $string;
}

/**
 * Get subscription URL for user
 *
 * @param string $token Subscription token
 * @return string Subscription URL
 */
function hbc_get_subscription_url($token) {
    return add_query_arg(array(
        'hbc_ical' => '1',
        'token' => $token
    ), home_url('/'));
}

/**
 * Display subscription management in admin
 */
function hbc_admin_subscriptions_page() {
    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'hbc_subscriptions';
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    // Handle subscription revocation
    if (isset($_GET['action']) && $_GET['action'] === 'revoke' && isset($_GET['sub_id']) && check_admin_referer('hbc_revoke_sub_' . intval($_GET['sub_id']))) {
        $sub_id = intval($_GET['sub_id']);
        $user_id = get_current_user_id();

        // Ensure user can only revoke their own subscriptions
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $subscriptions_table WHERE id = %d AND user_id = %d",
            $sub_id,
            $user_id
        ));

        if ($subscription) {
            $wpdb->update($subscriptions_table, array('status' => 'inactive'), array('id' => $sub_id));
            add_settings_error('hbc_messages', 'hbc_message', __('Subscription revoked successfully.', 'hall-booking-calendar'), 'updated');
        } else {
            add_settings_error('hbc_messages', 'hbc_message', __('Unable to revoke subscription.', 'hall-booking-calendar'), 'error');
        }

        // Redirect to remove query params
        wp_redirect(admin_url('admin.php?page=hbc-subscriptions'));
        exit;
    }

    // Handle new subscription creation
    if (isset($_POST['hbc_create_subscription']) && current_user_can('manage_options') && check_admin_referer('hbc_create_subscription', 'hbc_sub_nonce')) {
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $group_id = !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;
        $room_id = !empty($_POST['room_id']) ? intval($_POST['room_id']) : null;

        $token = hbc_generate_subscription_token($user_id, $group_id, $room_id);
        $subscription_url = hbc_get_subscription_url($token);

        add_settings_error('hbc_messages', 'hbc_message', __('Subscription created successfully. This token will expire in 90 days.', 'hall-booking-calendar'), 'updated');
    }

    // Get groups and rooms for filter
    $groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM $groups_table WHERE status = %s ORDER BY name ASC", 'active'));
    $rooms = $wpdb->get_results($wpdb->prepare("SELECT * FROM $rooms_table WHERE status = %s ORDER BY name ASC", 'active'));

    // Get existing subscriptions for current user
    $user_subscriptions = array();
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $user_subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, g.name as group_name, r.name as room_name
            FROM $subscriptions_table s
            LEFT JOIN $groups_table g ON s.group_id = g.id
            LEFT JOIN $rooms_table r ON s.room_id = r.id
            WHERE s.user_id = %d
            ORDER BY s.created_at DESC",
            $user_id
        ));
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Calendar Subscriptions', 'hall-booking-calendar'); ?></h1>

        <?php settings_errors('hbc_messages'); ?>

        <div class="hbc-subscription-info">
            <p><?php _e('Create a calendar subscription to sync bookings with your calendar application (Google Calendar, Outlook, Apple Calendar, etc.).', 'hall-booking-calendar'); ?></p>
            <p><strong><?php _e('Security Note:', 'hall-booking-calendar'); ?></strong> <?php _e('Tokens expire after 90 days and are rate-limited to 100 requests per hour. Keep your subscription URL private and revoke tokens if compromised.', 'hall-booking-calendar'); ?></p>
        </div>

        <div class="hbc-create-subscription">
            <h2><?php _e('Create New Subscription', 'hall-booking-calendar'); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field('hbc_create_subscription', 'hbc_sub_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="group_id"><?php _e('Filter by Group (Optional)', 'hall-booking-calendar'); ?></label></th>
                        <td>
                            <select id="group_id" name="group_id">
                                <option value=""><?php _e('All Groups', 'hall-booking-calendar'); ?></option>
                                <?php foreach ($groups as $group) : ?>
                                    <option value="<?php echo esc_attr($group->id); ?>"><?php echo esc_html($group->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="room_id"><?php _e('Filter by Room (Optional)', 'hall-booking-calendar'); ?></label></th>
                        <td>
                            <select id="room_id" name="room_id">
                                <option value=""><?php _e('All Rooms', 'hall-booking-calendar'); ?></option>
                                <?php foreach ($rooms as $room) : ?>
                                    <option value="<?php echo esc_attr($room->id); ?>"><?php echo esc_html($room->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Create a filtered subscription to see only specific groups or rooms.', 'hall-booking-calendar'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="hbc_create_subscription" class="button button-primary" value="<?php _e('Create Subscription', 'hall-booking-calendar'); ?>">
                </p>
            </form>
        </div>

        <?php if (!empty($user_subscriptions)) : ?>
        <div class="hbc-existing-subscriptions">
            <h2><?php _e('Your Subscriptions', 'hall-booking-calendar'); ?></h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Status', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Filter', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('URL', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Created', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Expires', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Last Accessed', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Actions', 'hall-booking-calendar'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_subscriptions as $sub) :
                        $created_time = strtotime($sub->created_at);
                        $expiry_time = strtotime('+90 days', $created_time);
                        $is_expired = (time() > $expiry_time) || ($sub->status === 'inactive');
                        $days_until_expiry = ceil(($expiry_time - time()) / DAY_IN_SECONDS);
                    ?>
                    <tr<?php echo $is_expired ? ' style="' . esc_attr('opacity: 0.5;') . '"' : ''; ?>>
                        <td>
                            <?php if ($is_expired) : ?>
                                <span style="color: red;">● <?php _e('Expired', 'hall-booking-calendar'); ?></span>
                            <?php elseif ($days_until_expiry <= 7) : ?>
                                <span style="color: orange;">● <?php printf(__('Expires in %d days', 'hall-booking-calendar'), $days_until_expiry); ?></span>
                            <?php else : ?>
                                <span style="color: green;">● <?php _e('Active', 'hall-booking-calendar'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($sub->group_name && $sub->room_name) {
                                echo esc_html($sub->group_name . ' - ' . $sub->room_name);
                            } elseif ($sub->group_name) {
                                echo esc_html($sub->group_name);
                            } elseif ($sub->room_name) {
                                echo esc_html($sub->room_name);
                            } else {
                                _e('All Bookings', 'hall-booking-calendar');
                            }
                            ?>
                        </td>
                        <td>
                            <?php if (!$is_expired) : ?>
                                <input type="text" readonly value="<?php echo esc_url(hbc_get_subscription_url($sub->token)); ?>" class="regular-text" onclick="this.select();">
                                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_url(hbc_get_subscription_url($sub->token)); ?>');"><?php _e('Copy', 'hall-booking-calendar'); ?></button>
                            <?php else : ?>
                                <em><?php _e('Token expired', 'hall-booking-calendar'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('F j, Y', strtotime($sub->created_at))); ?></td>
                        <td><?php echo esc_html(date('F j, Y', $expiry_time)); ?></td>
                        <td><?php echo $sub->last_accessed ? esc_html(date('F j, Y g:i A', strtotime($sub->last_accessed))) : __('Never', 'hall-booking-calendar'); ?></td>
                        <td>
                            <?php if (!$is_expired) : ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hbc-subscriptions&action=revoke&sub_id=' . $sub->id), 'hbc_revoke_sub_' . $sub->id); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to revoke this subscription? This cannot be undone.', 'hall-booking-calendar'); ?>');"><?php _e('Revoke', 'hall-booking-calendar'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="hbc-subscription-instructions">
                <h3><?php _e('How to Subscribe', 'hall-booking-calendar'); ?></h3>
                <ul>
                    <li><strong><?php _e('Google Calendar:', 'hall-booking-calendar'); ?></strong> <?php _e('Copy the URL, go to Google Calendar > Settings > Add calendar > From URL, paste the URL.', 'hall-booking-calendar'); ?></li>
                    <li><strong><?php _e('Apple Calendar:', 'hall-booking-calendar'); ?></strong> <?php _e('Copy the URL, go to File > New Calendar Subscription, paste the URL.', 'hall-booking-calendar'); ?></li>
                    <li><strong><?php _e('Outlook:', 'hall-booking-calendar'); ?></strong> <?php _e('Copy the URL, go to Calendar > Add calendar > Subscribe from web, paste the URL.', 'hall-booking-calendar'); ?></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
