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
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

    // Bulk approve pending bookings
    if (isset($_POST['hbc_bulk_approve']) && check_admin_referer('hbc_bulk_approve_bookings', 'hbc_bulk_nonce')) {
        $booking_ids = isset($_POST['bulk_booking_ids']) && is_array($_POST['bulk_booking_ids']) ? array_map('intval', $_POST['bulk_booking_ids']) : array();
        $booking_ids = array_filter($booking_ids);

        if (!empty($booking_ids)) {
            $approved_count = 0;
            foreach ($booking_ids as $bid) {
                $current_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $bookings_table WHERE id = %d", $bid));
                if ($current_status === 'pending') {
                    $wpdb->update($bookings_table, array('status' => 'confirmed'), array('id' => $bid));
                    // Send confirmation email to booker
                    hbc_send_acceptance_notification($bid);
                    $approved_count++;
                }
            }
            add_settings_error('hbc_messages', 'hbc_message', sprintf(__('%d booking(s) approved successfully.', 'hall-booking-calendar'), $approved_count), 'updated');
        } else {
            add_settings_error('hbc_messages', 'hbc_message', __('No bookings selected for approval.', 'hall-booking-calendar'), 'error');
        }
    }

    // Update booking status, group, and rooms
    if (isset($_POST['hbc_update_booking_status']) && check_admin_referer('hbc_update_booking_status', 'hbc_booking_nonce')) {
        $booking_id = intval($_POST['booking_id']);
        $status = sanitize_text_field($_POST['booking_status']);
        $group_id = isset($_POST['booking_group_id']) && $_POST['booking_group_id'] !== '' ? intval($_POST['booking_group_id']) : null;

        // Update rooms via junction table if room checkboxes were submitted
        if (isset($_POST['booking_room_ids']) && is_array($_POST['booking_room_ids'])) {
            $new_room_ids = array_map('intval', $_POST['booking_room_ids']);
            $new_room_ids = array_unique(array_filter($new_room_ids));

            if (!empty($new_room_ids)) {
                // Delete old room associations
                $wpdb->delete($booking_rooms_table, array('booking_id' => $booking_id));

                // Insert new room associations
                foreach ($new_room_ids as $rid) {
                    $wpdb->insert($booking_rooms_table, array(
                        'booking_id' => $booking_id,
                        'room_id' => $rid,
                    ));
                }

                // Update the primary room_id in bookings table for backward compat
                $wpdb->update(
                    $bookings_table,
                    array('room_id' => $new_room_ids[0]),
                    array('id' => $booking_id)
                );
            }
        }

        // Check if status is changing to confirmed (for acceptance email)
        $old_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $bookings_table WHERE id = %d", $booking_id));

        $wpdb->update(
            $bookings_table,
            array('status' => $status, 'group_id' => $group_id),
            array('id' => $booking_id)
        );

        // Send acceptance email if status changed to confirmed
        if ($status === 'confirmed' && $old_status !== 'confirmed') {
            hbc_send_acceptance_notification($booking_id);
        }

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
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

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

    // Pre-fetch all room names for bookings via junction table
    $booking_ids = wp_list_pluck($bookings, 'id');
    $all_booking_rooms = array();
    if (!empty($booking_ids)) {
        $ids_placeholder = implode(',', array_map('intval', $booking_ids));
        $room_rows = $wpdb->get_results(
            "SELECT br.booking_id, r.name FROM $booking_rooms_table br
             INNER JOIN $rooms_table r ON br.room_id = r.id
             WHERE br.booking_id IN ($ids_placeholder)
             ORDER BY r.name ASC"
        );
        foreach ($room_rows as $row) {
            $all_booking_rooms[$row->booking_id][] = $row->name;
        }
    }

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

    <?php
    // Check if there are any pending bookings for the bulk approve button
    $has_pending = false;
    if ($bookings) {
        foreach ($bookings as $bk) {
            if ($bk->status === 'pending') {
                $has_pending = true;
                break;
            }
        }
    }
    ?>

    <form method="post" action="" id="hbc-bulk-approve-form">
        <?php wp_nonce_field('hbc_bulk_approve_bookings', 'hbc_bulk_nonce'); ?>

        <?php if ($has_pending) : ?>
            <div class="hbc-bulk-actions" style="margin-bottom: 10px;">
                <button type="submit" name="hbc_bulk_approve" class="button button-primary" onclick="return confirm('<?php _e('Are you sure you want to approve all selected bookings?', 'hall-booking-calendar'); ?>');">
                    <?php _e('Approve Selected', 'hall-booking-calendar'); ?>
                </button>
                <label style="margin-left: 10px;">
                    <input type="checkbox" id="hbc-select-all-pending"> <?php _e('Select All Pending', 'hall-booking-calendar'); ?>
                </label>
            </div>
        <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <?php if ($has_pending) : ?>
                    <th style="width: 30px;"><input type="checkbox" id="hbc-select-all-checkbox" title="<?php _e('Select all', 'hall-booking-calendar'); ?>"></th>
                <?php endif; ?>
                <th><?php _e('ID', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Description', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Room', 'hall-booking-calendar'); ?></th>
                <th><?php _e('User', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Date', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Time', 'hall-booking-calendar'); ?></th>
                <th><?php _e('File', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Status', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Actions', 'hall-booking-calendar'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($bookings) : ?>
                <?php foreach ($bookings as $booking) : ?>
                <tr>
                    <?php if ($has_pending) : ?>
                        <td>
                            <?php if ($booking->status === 'pending') : ?>
                                <input type="checkbox" name="bulk_booking_ids[]" value="<?php echo esc_attr($booking->id); ?>" class="hbc-bulk-checkbox">
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td><?php echo esc_html($booking->id); ?></td>
                    <td><strong><?php echo $booking->description ? esc_html(wp_trim_words($booking->description, 10)) : ($booking->purpose ? esc_html(wp_trim_words($booking->purpose, 10)) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'); ?></strong><br>
                        <small><?php echo $booking->group_name ? esc_html($booking->group_name) : '<em>' . __('No group', 'hall-booking-calendar') . '</em>'; ?></small>
                    </td>
                    <td><?php
                        $rooms_display = isset($all_booking_rooms[$booking->id]) ? implode(', ', $all_booking_rooms[$booking->id]) : esc_html($booking->room_name);
                        echo esc_html($rooms_display);
                    ?></td>
                    <td><?php echo esc_html($booking->user_name); ?><br>
                        <small><?php echo esc_html($booking->user_email); ?></small>
                    </td>
                    <td><?php echo esc_html(date('M j, Y', strtotime($booking->booking_date))); ?></td>
                    <td><?php echo esc_html(date('g:i A', strtotime($booking->start_time)) . ' - ' . date('g:i A', strtotime($booking->end_time))); ?></td>
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
                    <td colspan="<?php echo $has_pending ? '10' : '9'; ?>"><?php _e('No bookings found.', 'hall-booking-calendar'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($has_pending) : ?>
        <div class="hbc-bulk-actions" style="margin-top: 10px;">
            <button type="submit" name="hbc_bulk_approve" class="button button-primary" onclick="return confirm('<?php _e('Are you sure you want to approve all selected bookings?', 'hall-booking-calendar'); ?>');">
                <?php _e('Approve Selected', 'hall-booking-calendar'); ?>
            </button>
        </div>
    <?php endif; ?>

    </form>

    <script>
    jQuery(document).ready(function($) {
        // Select all checkboxes
        $('#hbc-select-all-checkbox').on('change', function() {
            $('.hbc-bulk-checkbox').prop('checked', $(this).is(':checked'));
        });
        // Select all pending shortcut
        $('#hbc-select-all-pending').on('change', function() {
            $('.hbc-bulk-checkbox').prop('checked', $(this).is(':checked'));
            $('#hbc-select-all-checkbox').prop('checked', $(this).is(':checked'));
        });
        // Update select-all when individual checkboxes change
        $('.hbc-bulk-checkbox').on('change', function() {
            var total = $('.hbc-bulk-checkbox').length;
            var checked = $('.hbc-bulk-checkbox:checked').length;
            $('#hbc-select-all-checkbox').prop('checked', total === checked);
            $('#hbc-select-all-pending').prop('checked', total === checked);
        });
    });
    </script>
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
    $booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';

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

    // Get all rooms for this booking from junction table
    $booking_room_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.name, r.capacity FROM $booking_rooms_table br
         INNER JOIN $rooms_table r ON br.room_id = r.id
         WHERE br.booking_id = %d ORDER BY r.name ASC",
        $booking_id
    ));
    $booking_room_ids = wp_list_pluck($booking_room_rows, 'id');

    // Get all active rooms and groups for the dropdowns
    $all_rooms = $wpdb->get_results("SELECT * FROM $rooms_table WHERE status = 'active' ORDER BY id ASC");
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
                <th><?php _e('Description:', 'hall-booking-calendar'); ?></th>
                <td><?php echo $booking->description ? nl2br(esc_html($booking->description)) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'; ?></td>
            </tr>
            <tr>
                <th><?php _e('Purpose:', 'hall-booking-calendar'); ?></th>
                <td><?php echo esc_html($booking->purpose); ?></td>
            </tr>
            <tr>
                <th><?php _e('Room(s):', 'hall-booking-calendar'); ?></th>
                <td>
                    <?php if (!empty($booking_room_rows)) : ?>
                        <?php foreach ($booking_room_rows as $br) : ?>
                            <?php echo esc_html($br->name); ?> (<?php _e('Capacity:', 'hall-booking-calendar'); ?> <?php echo esc_html($br->capacity); ?>)<br>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?php echo esc_html($booking->room_name); ?> (<?php _e('Capacity:', 'hall-booking-calendar'); ?> <?php echo esc_html($booking->capacity); ?>)
                    <?php endif; ?>
                </td>
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
                    <th scope="row"><?php _e('Room(s):', 'hall-booking-calendar'); ?></th>
                    <td>
                        <?php foreach ($all_rooms as $ar) : ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="booking_room_ids[]" value="<?php echo esc_attr($ar->id); ?>" <?php checked(in_array($ar->id, $booking_room_ids)); ?>>
                                <?php echo esc_html($ar->name . ' (Capacity: ' . $ar->capacity . ')'); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
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
