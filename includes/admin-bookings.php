<?php
/**
 * Admin Bookings Management
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle booking operations
 */
function hbc_handle_booking_operations() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    // Update booking status and group
    if (isset($_POST['hbc_update_booking_status']) && check_admin_referer('hbc_update_booking_status', 'hbc_booking_nonce')) {
        $booking_id = intval($_POST['booking_id']);
        $status = sanitize_text_field($_POST['booking_status']);
        $group_id = isset($_POST['booking_group_id']) && $_POST['booking_group_id'] !== '' ? intval($_POST['booking_group_id']) : null;

        $wpdb->update(
            $bookings_table,
            array('status' => $status, 'group_id' => $group_id),
            array('id' => $booking_id)
        );

        add_settings_error('hbc_messages', 'hbc_message', __('Booking updated successfully.', 'hall-booking-calendar'), 'updated');
    }

    // Delete booking
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['booking_id']) && check_admin_referer('hbc_delete_booking_' . $_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        $wpdb->delete($bookings_table, array('id' => $booking_id));
        add_settings_error('hbc_messages', 'hbc_message', __('Booking deleted successfully.', 'hall-booking-calendar'), 'updated');
    }
}
add_action('admin_init', 'hbc_handle_booking_operations');

/**
 * Bookings page
 */
function hbc_admin_bookings_page() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

    ?>
    <div class="wrap">
        <h1><?php _e('Hall Booking - Bookings', 'hall-booking-calendar'); ?></h1>

        <?php settings_errors('hbc_messages'); ?>

        <?php if ($action == 'list') : ?>
            <?php hbc_display_bookings_list(); ?>
        <?php elseif ($action == 'view' && $booking_id) : ?>
            <?php hbc_display_booking_details($booking_id); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display bookings list
 */
function hbc_display_bookings_list() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Filter by status
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    $sql = "SELECT b.*, r.name as room_name, g.name as group_name
            FROM $bookings_table b
            LEFT JOIN $rooms_table r ON b.room_id = r.id
            LEFT JOIN $groups_table g ON b.group_id = g.id";

    if ($status_filter) {
        $sql .= $wpdb->prepare(" WHERE b.status = %s", $status_filter);
    }

    $sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

    $bookings = $wpdb->get_results($sql);

    ?>
    <div class="hbc-filter-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="hall-booking-bookings">
            <select name="status" onchange="this.form.submit()">
                <option value=""><?php _e('All Statuses', 'hall-booking-calendar'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'hall-booking-calendar'); ?></option>
                <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php _e('Confirmed', 'hall-booking-calendar'); ?></option>
                <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php _e('Cancelled', 'hall-booking-calendar'); ?></option>
            </select>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Room', 'hall-booking-calendar'); ?></th>
                <th><?php _e('User', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Date', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Time', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Purpose', 'hall-booking-calendar'); ?></th>
                <th><?php _e('File', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Status', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Actions', 'hall-booking-calendar'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($bookings) : ?>
                <?php foreach ($bookings as $booking) : ?>
                <tr>
                    <td><?php echo esc_html($booking->id); ?></td>
                    <td><strong><?php echo esc_html($booking->room_name); ?></strong><br>
                        <small><?php echo $booking->group_name ? esc_html($booking->group_name) : '<em>' . __('No group', 'hall-booking-calendar') . '</em>'; ?></small>
                    </td>
                    <td><?php echo esc_html($booking->user_name); ?><br>
                        <small><?php echo esc_html($booking->user_email); ?></small>
                    </td>
                    <td><?php echo esc_html(date('M j, Y', strtotime($booking->booking_date))); ?></td>
                    <td><?php echo esc_html(date('g:i A', strtotime($booking->start_time)) . ' - ' . date('g:i A', strtotime($booking->end_time))); ?></td>
                    <td><?php echo $booking->purpose ? esc_html(wp_trim_words($booking->purpose, 10)) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'; ?></td>
                    <td>
                        <?php if (!empty($booking->file_path)) : ?>
                            <?php
                            $upload_dir = wp_upload_dir();
                            $file_url = $upload_dir['baseurl'] . '/' . $booking->file_path;
                            ?>
                            <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="button button-small">📄 <?php _e('View PDF', 'hall-booking-calendar'); ?></a>
                        <?php else : ?>
                            <em><?php _e('None', 'hall-booking-calendar'); ?></em>
                        <?php endif; ?>
                    </td>
                    <td><span class="hbc-status hbc-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html(ucfirst($booking->status)); ?></span></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking->id); ?>" class="button button-small"><?php _e('View', 'hall-booking-calendar'); ?></a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hall-booking-bookings&action=delete&booking_id=' . $booking->id), 'hbc_delete_booking_' . $booking->id); ?>" class="button button-small" onclick="return confirm('<?php _e('Are you sure you want to delete this booking?', 'hall-booking-calendar'); ?>')"><?php _e('Delete', 'hall-booking-calendar'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="9"><?php _e('No bookings found.', 'hall-booking-calendar'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Display booking details
 */
function hbc_display_booking_details($booking_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hbc_bookings';
    $rooms_table = $wpdb->prefix . 'hbc_rooms';
    $groups_table = $wpdb->prefix . 'hbc_groups';

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, r.capacity, g.name as group_name
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        LEFT JOIN $groups_table g ON b.group_id = g.id
        WHERE b.id = %d",
        $booking_id
    ));

    if (!$booking) {
        echo '<p>' . __('Booking not found.', 'hall-booking-calendar') . '</p>';
        return;
    }

    // Get all active groups for the dropdown
    $all_groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE status = 'active' ORDER BY name ASC");

    ?>
    <div class="hbc-booking-details">
        <h2><?php _e('Booking Details', 'hall-booking-calendar'); ?></h2>

        <table class="form-table">
            <tr>
                <th><?php _e('Booking ID:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html($booking->id); ?></td>
            </tr>
            <tr>
                <th><?php _e('Room:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html($booking->room_name); ?> (<?php _e('Capacity:', 'hall-booking-calendar'); ?> <?php echo esc_html($booking->capacity); ?>)</td>
            </tr>
            <tr>
                <th><?php _e('Group:', 'hall-booking-calendar'); ?></th>
                <td><?php echo $booking->group_name ? esc_html($booking->group_name) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'; ?></td>
            </tr>
            <tr>
                <th><?php _e('User Name:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html($booking->user_name); ?></td>
            </tr>
            <tr>
                <th><?php _e('User Email:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html($booking->user_email); ?></td>
            </tr>
            <tr>
                <th><?php _e('Booking Date:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html(date('F j, Y', strtotime($booking->booking_date))); ?></td>
            </tr>
            <tr>
                <th><?php _e('Time:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html(date('g:i A', strtotime($booking->start_time)) . ' - ' . date('g:i A', strtotime($booking->end_time))); ?></td>
            </tr>
            <tr>
                <th><?php _e('Purpose:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html($booking->purpose); ?></td>
            </tr>
            <tr>
                <th><?php _e('Description:', 'hall-booking-calendar'); ?></th>
                <td><?php echo $booking->description ? nl2br(esc_html($booking->description)) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'; ?></td>
            </tr>
            <tr>
                <th><?php _e('Attached File:', 'hall-booking-calendar'); ?></th>
                <td>
                    <?php if (!empty($booking->file_path)) : ?>
                        <?php
                        $upload_dir = wp_upload_dir();
                        $file_url = $upload_dir['baseurl'] . '/' . $booking->file_path;
                        $file_name = basename($booking->file_path);
                        ?>
                        <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="button button-secondary">📄 <?php echo esc_html($file_name); ?></a>
                    <?php else : ?>
                        <em><?php _e('No file attached', 'hall-booking-calendar'); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Status:', 'hall-booking-calendar'); ?></th>
                <td><span class="hbc-status hbc-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html(ucfirst($booking->status)); ?></span></td>
            </tr>
            <tr>
                <th><?php _e('Created At:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html(date('F j, Y g:i A', strtotime($booking->created_at))); ?></td>
            </tr>
        </table>

        <h3><?php _e('Update Booking', 'hall-booking-calendar'); ?></h3>
        <form method="post" action="<?php echo admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking_id); ?>">
            <?php wp_nonce_field('hbc_update_booking_status', 'hbc_booking_nonce'); ?>
            <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="booking_group_id"><?php _e('Group:', 'hall-booking-calendar'); ?></label></th>
                    <td>
                        <select name="booking_group_id" id="booking_group_id">
                            <option value=""><?php _e('-- No Group --', 'hall-booking-calendar'); ?></option>
                            <?php foreach ($all_groups as $group) : ?>
                                <option value="<?php echo esc_attr($group->id); ?>" <?php selected($booking->group_id, $group->id); ?>><?php echo esc_html($group->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="booking_status"><?php _e('Status:', 'hall-booking-calendar'); ?></label></th>
                    <td>
                        <select name="booking_status" id="booking_status">
                            <option value="pending" <?php selected($booking->status, 'pending'); ?>><?php _e('Pending', 'hall-booking-calendar'); ?></option>
                            <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>><?php _e('Confirmed', 'hall-booking-calendar'); ?></option>
                            <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>><?php _e('Cancelled', 'hall-booking-calendar'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <input type="submit" name="hbc_update_booking_status" class="button button-primary" value="<?php _e('Update Booking', 'hall-booking-calendar'); ?>">
        </form>

        <p>
            <a href="<?php echo admin_url('admin.php?page=hall-booking-bookings'); ?>" class="button"><?php _e('Back to Bookings', 'hall-booking-calendar'); ?></a>
        </p>
    </div>
    <?php
}
