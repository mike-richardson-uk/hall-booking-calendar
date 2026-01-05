<?php
/**
 * Admin Rooms Management
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle room operations
 */
function hbc_handle_room_operations() {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    // Add room
    if (isset($_POST['hbc_add_room']) && check_admin_referer('hbc_add_room', 'hbc_room_nonce')) {
        $wpdb->insert($rooms_table, array(
            'name' => sanitize_text_field($_POST['room_name']),
            'description' => sanitize_textarea_field($_POST['room_description']),
            'capacity' => intval($_POST['room_capacity']),
            'status' => sanitize_text_field($_POST['room_status'])
        ));

        if ($wpdb->insert_id) {
            add_settings_error('hbc_messages', 'hbc_message', __('Room added successfully.', 'hall-booking-calendar'), 'updated');
        }
    }

    // Update room
    if (isset($_POST['hbc_update_room']) && check_admin_referer('hbc_update_room', 'hbc_room_nonce')) {
        $room_id = intval($_POST['room_id']);
        $wpdb->update(
            $rooms_table,
            array(
                'name' => sanitize_text_field($_POST['room_name']),
                'description' => sanitize_textarea_field($_POST['room_description']),
                'capacity' => intval($_POST['room_capacity']),
                'status' => sanitize_text_field($_POST['room_status'])
            ),
            array('id' => $room_id)
        );

        add_settings_error('hbc_messages', 'hbc_message', __('Room updated successfully.', 'hall-booking-calendar'), 'updated');
    }

    // Delete room
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['room_id']) && check_admin_referer('hbc_delete_room_' . $_GET['room_id'])) {
        $room_id = intval($_GET['room_id']);
        $wpdb->delete($rooms_table, array('id' => $room_id));
        add_settings_error('hbc_messages', 'hbc_message', __('Room deleted successfully.', 'hall-booking-calendar'), 'updated');
    }
}
add_action('admin_init', 'hbc_handle_room_operations');

/**
 * Rooms page
 */
function hbc_admin_rooms_page() {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

    ?>
    <div class="wrap">
        <h1><?php _e('Hall Booking - Rooms', 'hall-booking-calendar'); ?>
            <?php if ($action == 'list') : ?>
                <a href="<?php echo admin_url('admin.php?page=hall-booking-rooms&action=add'); ?>" class="page-title-action"><?php _e('Add New', 'hall-booking-calendar'); ?></a>
            <?php endif; ?>
        </h1>

        <?php settings_errors('hbc_messages'); ?>

        <?php if ($action == 'list') : ?>
            <?php hbc_display_rooms_list(); ?>
        <?php elseif ($action == 'add') : ?>
            <?php hbc_display_room_form(); ?>
        <?php elseif ($action == 'edit' && $room_id) : ?>
            <?php hbc_display_room_form($room_id); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display rooms list
 */
function hbc_display_rooms_list() {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    $rooms = $wpdb->get_results("SELECT * FROM $rooms_table ORDER BY id ASC");

    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Name', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Description', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Capacity', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Status', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Actions', 'hall-booking-calendar'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rooms) : ?>
                <?php foreach ($rooms as $room) : ?>
                <tr>
                    <td><?php echo esc_html($room->id); ?></td>
                    <td><strong><?php echo esc_html($room->name); ?></strong></td>
                    <td><?php echo esc_html($room->description); ?></td>
                    <td><?php echo esc_html($room->capacity); ?></td>
                    <td><span class="hbc-status hbc-status-<?php echo esc_attr($room->status); ?>"><?php echo esc_html(ucfirst($room->status)); ?></span></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=hall-booking-rooms&action=edit&room_id=' . $room->id); ?>" class="button button-small"><?php _e('Edit', 'hall-booking-calendar'); ?></a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hall-booking-rooms&action=delete&room_id=' . $room->id), 'hbc_delete_room_' . $room->id); ?>" class="button button-small" onclick="return confirm('<?php _e('Are you sure you want to delete this room?', 'hall-booking-calendar'); ?>')"><?php _e('Delete', 'hall-booking-calendar'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6"><?php _e('No rooms found.', 'hall-booking-calendar'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Display room form
 */
function hbc_display_room_form($room_id = 0) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'hbc_rooms';

    $room = null;
    if ($room_id) {
        $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rooms_table WHERE id = %d", $room_id));
    }

    $is_edit = $room ? true : false;
    ?>
    <form method="post" action="<?php echo admin_url('admin.php?page=hall-booking-rooms'); ?>">
        <?php wp_nonce_field($is_edit ? 'hbc_update_room' : 'hbc_add_room', 'hbc_room_nonce'); ?>

        <?php if ($is_edit) : ?>
            <input type="hidden" name="room_id" value="<?php echo esc_attr($room->id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="room_name"><?php _e('Room Name', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <input type="text" id="room_name" name="room_name" class="regular-text" value="<?php echo $room ? esc_attr($room->name) : ''; ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="room_description"><?php _e('Description', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <textarea id="room_description" name="room_description" class="large-text" rows="4"><?php echo $room ? esc_textarea($room->description) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="room_capacity"><?php _e('Capacity', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <input type="number" id="room_capacity" name="room_capacity" min="1" value="<?php echo $room ? esc_attr($room->capacity) : '10'; ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="room_status"><?php _e('Status', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <select id="room_status" name="room_status">
                        <option value="active" <?php echo ($room && $room->status == 'active') ? 'selected' : ''; ?>><?php _e('Active', 'hall-booking-calendar'); ?></option>
                        <option value="inactive" <?php echo ($room && $room->status == 'inactive') ? 'selected' : ''; ?>><?php _e('Inactive', 'hall-booking-calendar'); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="<?php echo $is_edit ? 'hbc_update_room' : 'hbc_add_room'; ?>" class="button button-primary" value="<?php echo $is_edit ? __('Update Room', 'hall-booking-calendar') : __('Add Room', 'hall-booking-calendar'); ?>">
            <a href="<?php echo admin_url('admin.php?page=hall-booking-rooms'); ?>" class="button"><?php _e('Cancel', 'hall-booking-calendar'); ?></a>
        </p>
    </form>
    <?php
}
