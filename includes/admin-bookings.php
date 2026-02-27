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
        $category_id = isset($_POST['booking_category_id']) && $_POST['booking_category_id'] !== '' ? intval($_POST['booking_category_id']) : null;

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

        // Collect all editable fields
        $purpose = isset($_POST['booking_purpose']) ? sanitize_text_field($_POST['booking_purpose']) : '';
        $description = isset($_POST['booking_description']) ? sanitize_textarea_field($_POST['booking_description']) : '';
        $user_name = isset($_POST['booking_user_name']) ? sanitize_text_field($_POST['booking_user_name']) : '';
        $user_email = isset($_POST['booking_user_email']) ? sanitize_email($_POST['booking_user_email']) : '';
        $booking_date = isset($_POST['booking_date']) ? sanitize_text_field($_POST['booking_date']) : '';
        $start_time = isset($_POST['booking_start_time']) ? sanitize_text_field($_POST['booking_start_time']) : '';
        $end_time = isset($_POST['booking_end_time']) ? sanitize_text_field($_POST['booking_end_time']) : '';

        // Check if status is changing to confirmed (for acceptance email)
        $old_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $bookings_table WHERE id = %d", $booking_id));

        $update_data = array(
            'status' => $status,
            'group_id' => $group_id,
            'category_id' => $category_id,
            'purpose' => $purpose,
            'description' => $description,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'booking_date' => $booking_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
        );

        $wpdb->update(
            $bookings_table,
            $update_data,
            array('id' => $booking_id)
        );

        // Send acceptance email if status changed to confirmed
        if ($status === 'confirmed' && $old_status !== 'confirmed') {
            hbc_send_acceptance_notification($booking_id);
        }

        add_settings_error('hbc_messages', 'hbc_message', __('Booking updated successfully.', 'hall-booking-calendar'), 'updated');

        // Save booking-in form configuration
        if (!empty($_POST['enable_book_in']) && '1' === $_POST['enable_book_in']) {
            hbc_save_book_in_form_config($booking_id);
        } else {
            $bif_table = $wpdb->prefix . 'hbc_booking_forms';
            if ($wpdb->get_var("SHOW TABLES LIKE '$bif_table'") === $bif_table) {
                $wpdb->update($bif_table, array('enabled' => 0), array('booking_id' => $booking_id));
            }
        }
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

    // Filters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $group_filter = isset($_GET['group']) ? intval($_GET['group']) : 0;

    $categories_table = $wpdb->prefix . 'hbc_categories';

    $sql = "SELECT b.*, r.name as room_name, g.name as group_name, c.name as category_name
            FROM $bookings_table b
            LEFT JOIN $rooms_table r ON b.room_id = r.id
            LEFT JOIN $groups_table g ON b.group_id = g.id
            LEFT JOIN $categories_table c ON b.category_id = c.id";

    $where_clauses = array();
    if ($status_filter) {
        $where_clauses[] = $wpdb->prepare("b.status = %s", $status_filter);
    }
    if ($group_filter) {
        $where_clauses[] = $wpdb->prepare("b.group_id = %d", $group_filter);
    }
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
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
    <?php
    $all_groups = $wpdb->get_results("SELECT id, name FROM $groups_table WHERE status = 'active' ORDER BY name ASC");
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
            <select name="group" onchange="this.form.submit()">
                <option value=""><?php _e('All Groups', 'hall-booking-calendar'); ?></option>
                <?php foreach ($all_groups as $g) : ?>
                    <option value="<?php echo esc_attr($g->id); ?>" <?php selected($group_filter, $g->id); ?>><?php echo esc_html($g->name); ?></option>
                <?php endforeach; ?>
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
                <th><?php _e('Purpose', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Category', 'hall-booking-calendar'); ?></th>
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
                    <td><strong><?php echo $booking->purpose ? esc_html(wp_trim_words($booking->purpose, 10)) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'; ?></strong><br>
                        <small><?php echo $booking->group_name ? esc_html($booking->group_name) : '<em>' . __('No group', 'hall-booking-calendar') . '</em>'; ?></small>
                    </td>
                    <td><?php echo $booking->category_name ? esc_html($booking->category_name) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'; ?></td>
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
                    <td colspan="<?php echo $has_pending ? '11' : '10'; ?>"><?php _e('No bookings found.', 'hall-booking-calendar'); ?></td>
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

    $categories_table = $wpdb->prefix . 'hbc_categories';

    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, r.capacity, g.name as group_name, c.name as category_name
        FROM $bookings_table b
        LEFT JOIN $rooms_table r ON b.room_id = r.id
        LEFT JOIN $groups_table g ON b.group_id = g.id
        LEFT JOIN $categories_table c ON b.category_id = c.id
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
    $all_categories = $wpdb->get_results("SELECT * FROM $categories_table WHERE status = 'active' ORDER BY name ASC");

    // Fetch existing booking-in form config for this booking (own config only, not inherited)
    $booking_forms_table = $wpdb->prefix . 'hbc_booking_forms';
    $existing_bi_config  = null;
    if ($wpdb->get_var("SHOW TABLES LIKE '$booking_forms_table'") === $booking_forms_table) {
        $existing_bi_config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $booking_forms_table WHERE booking_id = %d",
            $booking_id
        ));
    }

    $bi_enabled          = $existing_bi_config && $existing_bi_config->enabled;
    $bi_emails           = ($existing_bi_config && $existing_bi_config->submission_emails)
                            ? (array) json_decode($existing_bi_config->submission_emails, true)
                            : array();
    $bi_include_meal     = $existing_bi_config && $existing_bi_config->include_meal_menu;
    $bi_meals            = ($existing_bi_config && $existing_bi_config->meal_options)
                            ? (array) json_decode($existing_bi_config->meal_options, true)
                            : array();
    $bi_token            = $existing_bi_config ? $existing_bi_config->book_in_token : '';
    $bi_bank_name        = $existing_bi_config ? $existing_bi_config->payment_bank_name        : '';
    $bi_sort_code        = $existing_bi_config ? $existing_bi_config->payment_sort_code        : '';
    $bi_account_number   = $existing_bi_config ? $existing_bi_config->payment_account_number   : '';
    $bi_ref_prefix       = $existing_bi_config ? $existing_bi_config->payment_reference_prefix : '';
    $bi_cheque_payable   = $existing_bi_config ? $existing_bi_config->payment_cheque_payable   : '';
    $bi_deadline         = $existing_bi_config ? $existing_bi_config->payment_deadline         : '';

    ?>
    <div class="hbc-booking-details">
        <h2><?php _e('Booking Details', 'hall-booking-calendar'); ?> #<?php echo esc_html($booking->id); ?></h2>

        <form method="post" id="hbc-edit-booking-form" action="<?php echo admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking_id); ?>">
            <?php wp_nonce_field('hbc_update_booking_status', 'hbc_booking_nonce'); ?>
            <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="booking_purpose"><?php _e('Purpose:', 'hall-booking-calendar'); ?></label></th>
                    <td><input type="text" name="booking_purpose" id="booking_purpose" value="<?php echo esc_attr($booking->purpose); ?>" class="regular-text large-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="booking_description"><?php _e('Description:', 'hall-booking-calendar'); ?></label></th>
                    <td><textarea name="booking_description" id="booking_description" rows="4" class="large-text"><?php echo esc_textarea($booking->description); ?></textarea></td>
                </tr>
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
                    <th scope="row"><label for="booking_category_id"><?php _e('Category:', 'hall-booking-calendar'); ?></label></th>
                    <td>
                        <select name="booking_category_id" id="booking_category_id">
                            <option value=""><?php _e('-- No Category --', 'hall-booking-calendar'); ?></option>
                            <?php foreach ($all_categories as $cat) : ?>
                                <option value="<?php echo esc_attr($cat->id); ?>" <?php selected($booking->category_id, $cat->id); ?>><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="booking_user_name"><?php _e('User Name:', 'hall-booking-calendar'); ?></label></th>
                    <td><input type="text" name="booking_user_name" id="booking_user_name" value="<?php echo esc_attr($booking->user_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="booking_user_email"><?php _e('User Email:', 'hall-booking-calendar'); ?></label></th>
                    <td><input type="email" name="booking_user_email" id="booking_user_email" value="<?php echo esc_attr($booking->user_email); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="booking_date"><?php _e('Booking Date:', 'hall-booking-calendar'); ?></label></th>
                    <td><input type="date" name="booking_date" id="booking_date" value="<?php echo esc_attr($booking->booking_date); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="booking_start_time"><?php _e('Start Time:', 'hall-booking-calendar'); ?></label></th>
                    <td><input type="time" name="booking_start_time" id="booking_start_time" value="<?php echo esc_attr($booking->start_time); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="booking_end_time"><?php _e('End Time:', 'hall-booking-calendar'); ?></label></th>
                    <td><input type="time" name="booking_end_time" id="booking_end_time" value="<?php echo esc_attr($booking->end_time); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Attached File:', 'hall-booking-calendar'); ?></th>
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
                    <th scope="row"><label for="booking_status"><?php _e('Status:', 'hall-booking-calendar'); ?></label></th>
                    <td>
                        <select name="booking_status" id="booking_status">
                            <option value="pending" <?php selected($booking->status, 'pending'); ?>><?php _e('Pending', 'hall-booking-calendar'); ?></option>
                            <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>><?php _e('Confirmed', 'hall-booking-calendar'); ?></option>
                            <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>><?php _e('Cancelled', 'hall-booking-calendar'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Created At:', 'hall-booking-calendar'); ?></th>
                    <td><?php echo esc_html(date('F j, Y g:i A', strtotime($booking->created_at))); ?></td>
                </tr>
            </table>

            <hr style="margin: 24px 0;">
            <h2><?php _e('Member Booking-In Form', 'hall-booking-calendar'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable:', 'hall-booking-calendar'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="hbc-admin-enable-book-in" name="enable_book_in" value="1" <?php checked($bi_enabled); ?>>
                            <?php _e('Enable member booking-in form for this event', 'hall-booking-calendar'); ?>
                        </label>
                        <p class="description"><?php _e('Allows members to book in via a dedicated URL. A "Book In" button will appear on the event detail page.', 'hall-booking-calendar'); ?></p>
                    </td>
                </tr>
                <?php if (!empty($bi_token)) : ?>
                <tr>
                    <th scope="row"><?php _e('Short URL:', 'hall-booking-calendar'); ?></th>
                    <td>
                        <code><?php echo esc_url(home_url('book/' . $bi_token . '/')); ?></code>
                        <p class="description"><?php _e('Share this link with members so they can book in directly.', 'hall-booking-calendar'); ?></p>
                        <?php
                        $short_url_admin = home_url('book/' . $bi_token . '/');
                        $qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . rawurlencode($short_url_admin);
                        ?>
                        <div style="margin-top:10px;">
                            <img src="<?php echo esc_url($qr_src); ?>" alt="<?php esc_attr_e('QR code for book-in URL', 'hall-booking-calendar'); ?>" width="160" height="160" style="display:block;border:1px solid #ddd;padding:4px;background:#fff;">
                            <p class="description" style="margin-top:4px;"><?php _e('Print or share this QR code so members can scan and book in.', 'hall-booking-calendar'); ?></p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <div id="hbc-admin-book-in-options" <?php echo $bi_enabled ? '' : 'style="display:none;"'; ?>>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Send submissions to:', 'hall-booking-calendar'); ?></th>
                        <td>
                            <div id="hbc-admin-book-in-emails">
                                <?php
                                $emails_to_render = !empty($bi_emails) ? $bi_emails : array('');
                                $first_only = count($emails_to_render) === 1;
                                foreach ($emails_to_render as $email) :
                                ?>
                                <div class="hbc-admin-email-entry" style="display:flex;gap:6px;margin-bottom:4px;">
                                    <input type="email" name="book_in_emails[]" class="regular-text" value="<?php echo esc_attr($email); ?>" placeholder="email@example.com">
                                    <button type="button" class="button hbc-admin-remove-email" <?php echo $first_only ? 'style="display:none;"' : ''; ?>>&times;</button>
                                </div>
                                <?php $first_only = false; endforeach; ?>
                            </div>
                            <button type="button" id="hbc-admin-add-email-btn" class="button"><?php _e('+ Add Another Email', 'hall-booking-calendar'); ?></button>
                            <p class="description"><?php _e('Completed booking-in forms will be emailed to these addresses.', 'hall-booking-calendar'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Meal Menu:', 'hall-booking-calendar'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="hbc-admin-include-meal-menu" name="include_meal_menu" value="1" <?php checked($bi_include_meal); ?>>
                                <?php _e('Include meal menu selection', 'hall-booking-calendar'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr id="hbc-admin-meal-builder-row" <?php echo $bi_include_meal ? '' : 'style="display:none;"'; ?>>
                        <th scope="row"><?php _e('Meal Options:', 'hall-booking-calendar'); ?></th>
                        <td>
                            <div id="hbc-admin-meal-items-list">
                                <?php foreach ($bi_meals as $meal) : ?>
                                <div class="hbc-admin-meal-item" style="display:flex;gap:6px;margin-bottom:6px;align-items:flex-start;">
                                    <input type="text" class="hbc-admin-meal-name" style="width:160px;" placeholder="<?php esc_attr_e('Meal name', 'hall-booking-calendar'); ?>" value="<?php echo esc_attr($meal['name']); ?>">
                                    <textarea class="hbc-admin-meal-desc" style="width:300px;" rows="4" placeholder="<?php esc_attr_e('Menu / description (optional)', 'hall-booking-calendar'); ?>"><?php echo esc_textarea(isset($meal['description']) ? $meal['description'] : ''); ?></textarea>
                                    <input type="number" class="hbc-admin-meal-price small-text" placeholder="<?php esc_attr_e('£ Price', 'hall-booking-calendar'); ?>" min="0" step="0.01" value="<?php echo esc_attr(isset($meal['price']) ? $meal['price'] : ''); ?>">
                                    <button type="button" class="button hbc-admin-remove-meal">&times;</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="book_in_meals_json" id="hbc-admin-meals-json" value="<?php echo esc_attr(wp_json_encode(!empty($bi_meals) ? $bi_meals : array())); ?>">
                            <button type="button" id="hbc-admin-add-meal-btn" class="button"><?php _e('+ Add Meal Option', 'hall-booking-calendar'); ?></button>
                            <p class="description"><?php _e('Name is required; description and price are optional.', 'hall-booking-calendar'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2"><h3 style="margin:0;"><?php _e('Payment Information', 'hall-booking-calendar'); ?></h3>
                            <p class="description" style="font-weight:normal;"><?php _e('Displayed on the booking-in form. Leave blank to omit.', 'hall-booking-calendar'); ?></p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hbc-admin-bank-name"><?php _e('Bank Name:', 'hall-booking-calendar'); ?></label></th>
                        <td><input type="text" id="hbc-admin-bank-name" name="payment_bank_name" class="regular-text" value="<?php echo esc_attr($bi_bank_name); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hbc-admin-sort-code"><?php _e('Sort Code:', 'hall-booking-calendar'); ?></label></th>
                        <td><input type="text" id="hbc-admin-sort-code" name="payment_sort_code" class="regular-text" value="<?php echo esc_attr($bi_sort_code); ?>" placeholder="00-00-00"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hbc-admin-account-number"><?php _e('Account Number:', 'hall-booking-calendar'); ?></label></th>
                        <td><input type="text" id="hbc-admin-account-number" name="payment_account_number" class="regular-text" value="<?php echo esc_attr($bi_account_number); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hbc-admin-ref-prefix"><?php _e('Reference Prefix:', 'hall-booking-calendar'); ?></label></th>
                        <td>
                            <input type="text" id="hbc-admin-ref-prefix" name="payment_reference_prefix" class="regular-text" value="<?php echo esc_attr($bi_ref_prefix); ?>" placeholder="<?php esc_attr_e('e.g. DINNER', 'hall-booking-calendar'); ?>">
                            <p class="description"><?php _e('Members will be asked to use this followed by their name as their payment reference.', 'hall-booking-calendar'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hbc-admin-cheque-payable"><?php _e('Cheques Payable To:', 'hall-booking-calendar'); ?></label></th>
                        <td><input type="text" id="hbc-admin-cheque-payable" name="payment_cheque_payable" class="regular-text" value="<?php echo esc_attr($bi_cheque_payable); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hbc-admin-payment-deadline"><?php _e('Payment Deadline:', 'hall-booking-calendar'); ?></label></th>
                        <td><input type="date" id="hbc-admin-payment-deadline" name="payment_deadline" value="<?php echo esc_attr($bi_deadline); ?>"></td>
                    </tr>
                </table>
            </div><!-- /#hbc-admin-book-in-options -->

            <br>
            <input type="submit" name="hbc_update_booking_status" class="button button-primary" value="<?php _e('Update Booking', 'hall-booking-calendar'); ?>">
        </form>

        <?php
        // ── Attendee Submissions ──────────────────────────────────────────────
        $subs_table_name = $wpdb->prefix . 'hbc_form_submissions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$subs_table_name'") === $subs_table_name) :
            $submissions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $subs_table_name WHERE booking_id = %d ORDER BY submitted_at ASC",
                $booking_id
            ));
            if (!empty($submissions)) :
        ?>
        <div class="hbc-booking-details" style="margin-top:24px;">
            <h2><?php _e('Booking-In Submissions', 'hall-booking-calendar'); ?> <span style="font-weight:normal;font-size:14px;">(<?php echo count($submissions); ?>)</span></h2>

            <form method="post" style="margin-bottom:12px;">
                <?php wp_nonce_field('hbc_export_book_in_' . $booking_id, 'hbc_book_in_export_nonce'); ?>
                <input type="hidden" name="hbc_book_in_export_booking_id" value="<?php echo esc_attr($booking_id); ?>">
                <button type="submit" name="hbc_export_book_in_csv" class="button button-secondary">
                    &#8681; <?php _e('Download Attendee List (CSV)', 'hall-booking-calendar'); ?>
                </button>
            </form>

            <table class="wp-list-table widefat fixed striped" style="font-size:13px;">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Attendance', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Membership', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Meal', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Dietary', 'hall-booking-calendar'); ?></th>
                        <th><?php _e('Submitted', 'hall-booking-calendar'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $att_labels = array(
                        'attending_dinner'    => __('With dinner', 'hall-booking-calendar'),
                        'attending_no_dinner' => __('No dinner', 'hall-booking-calendar'),
                        'not_attending'       => __('Not attending', 'hall-booking-calendar'),
                    );
                    foreach ($submissions as $s) :
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($s->full_name); ?></strong><br>
                            <small><?php echo esc_html($s->email); ?></small>
                        </td>
                        <td><?php echo esc_html(isset($att_labels[$s->attendance_type]) ? $att_labels[$s->attendance_type] : $s->attendance_type); ?></td>
                        <td>
                            <?php echo 'member' === $s->membership_type ? esc_html__('Member', 'hall-booking-calendar') : esc_html__('Guest', 'hall-booking-calendar'); ?>
                            <?php if (!empty($s->lodge_name)) : ?>
                                <br><small><?php echo esc_html($s->lodge_name); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo !empty($s->meal_choice) ? esc_html($s->meal_choice) : '&mdash;'; ?>
                            <?php if ($s->vegetarian_alternative) : ?>
                                <br><small><?php _e('Vegetarian alt.', 'hall-booking-calendar'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo !empty($s->dietary_requirements) ? esc_html(wp_trim_words($s->dietary_requirements, 8)) : '&mdash;'; ?></td>
                        <td><small><?php echo esc_html(date('M j, Y g:i A', strtotime($s->submitted_at))); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; endif; ?>

        <p>
            <a href="<?php echo admin_url('admin.php?page=hall-booking-bookings'); ?>" class="button"><?php _e('Back to Bookings', 'hall-booking-calendar'); ?></a>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {

        // Enable/disable booking-in options section
        $('#hbc-admin-enable-book-in').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc-admin-book-in-options').slideDown(200);
            } else {
                $('#hbc-admin-book-in-options').slideUp(200);
            }
        });

        // Show/hide meal builder row
        $('#hbc-admin-include-meal-menu').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc-admin-meal-builder-row').show();
            } else {
                $('#hbc-admin-meal-builder-row').hide();
            }
        });

        // Add email recipient row
        $('#hbc-admin-add-email-btn').on('click', function() {
            var row = '<div class="hbc-admin-email-entry" style="display:flex;gap:6px;margin-bottom:4px;">' +
                '<input type="email" name="book_in_emails[]" class="regular-text" placeholder="email@example.com">' +
                '<button type="button" class="button hbc-admin-remove-email">&times;</button>' +
                '</div>';
            $('#hbc-admin-book-in-emails').append(row);
            updateRemoveEmailButtons();
        });

        // Remove email recipient row
        $(document).on('click', '.hbc-admin-remove-email', function() {
            $(this).closest('.hbc-admin-email-entry').remove();
            updateRemoveEmailButtons();
        });

        function updateRemoveEmailButtons() {
            var rows = $('#hbc-admin-book-in-emails .hbc-admin-email-entry');
            rows.find('.hbc-admin-remove-email').show();
            if (rows.length === 1) {
                rows.find('.hbc-admin-remove-email').hide();
            }
        }

        // Add meal option row
        $('#hbc-admin-add-meal-btn').on('click', function() {
            var row = '<div class="hbc-admin-meal-item" style="display:flex;gap:6px;margin-bottom:6px;align-items:flex-start;">' +
                '<input type="text" class="hbc-admin-meal-name" style="width:160px;" placeholder="<?php echo esc_js(__('Meal name', 'hall-booking-calendar')); ?>">' +
                '<textarea class="hbc-admin-meal-desc" style="width:300px;" rows="4" placeholder="<?php echo esc_js(__('Menu / description (optional)', 'hall-booking-calendar')); ?>"></textarea>' +
                '<input type="number" class="hbc-admin-meal-price small-text" placeholder="<?php echo esc_js(__('£ Price', 'hall-booking-calendar')); ?>" min="0" step="0.01">' +
                '<button type="button" class="button hbc-admin-remove-meal">&times;</button>' +
                '</div>';
            $('#hbc-admin-meal-items-list').append(row);
        });

        // Remove meal option row
        $(document).on('click', '.hbc-admin-remove-meal', function() {
            $(this).closest('.hbc-admin-meal-item').remove();
        });

        // Serialize meal rows to JSON hidden field before form submits
        $('#hbc-edit-booking-form').on('submit', function() {
            var meals = [];
            $('#hbc-admin-meal-items-list .hbc-admin-meal-item').each(function() {
                var name = $(this).find('.hbc-admin-meal-name').val().trim();
                if (name) {
                    meals.push({
                        name:        name,
                        description: $(this).find('.hbc-admin-meal-desc').val().trim(),
                        price:       parseFloat($(this).find('.hbc-admin-meal-price').val()) || 0
                    });
                }
            });
            $('#hbc-admin-meals-json').val(JSON.stringify(meals));
        });
    });
    </script>
    <?php
}
