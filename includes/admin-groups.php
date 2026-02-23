<?php
/**
 * Admin Groups Management
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle group operations
 */
function hbc_handle_group_operations() {
    global $wpdb;
    $groups_table = $wpdb->prefix . 'hbc_groups';

    // Add group
    if (isset($_POST['hbc_add_group']) && check_admin_referer('hbc_add_group', 'hbc_group_nonce')) {
        $wpdb->insert($groups_table, array(
            'name' => sanitize_text_field(wp_unslash($_POST['group_name'])),
            'description' => sanitize_textarea_field(wp_unslash($_POST['group_description'])),
            'status' => sanitize_text_field(wp_unslash($_POST['group_status']))
        ));

        if ($wpdb->insert_id) {
            add_settings_error('hbc_messages', 'hbc_message', __('Group added successfully.', 'hall-booking-calendar'), 'updated');
        }
    }

    // Update group
    if (isset($_POST['hbc_update_group']) && check_admin_referer('hbc_update_group', 'hbc_group_nonce')) {
        $group_id = intval($_POST['group_id']);
        $wpdb->update(
            $groups_table,
            array(
                'name' => sanitize_text_field(wp_unslash($_POST['group_name'])),
                'description' => sanitize_textarea_field(wp_unslash($_POST['group_description'])),
                'status' => sanitize_text_field(wp_unslash($_POST['group_status']))
            ),
            array('id' => $group_id)
        );

        add_settings_error('hbc_messages', 'hbc_message', __('Group updated successfully.', 'hall-booking-calendar'), 'updated');
    }

    // Delete group
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['group_id']) && check_admin_referer('hbc_delete_group_' . $_GET['group_id'])) {
        $group_id = intval($_GET['group_id']);
        $wpdb->delete($groups_table, array('id' => $group_id));
        add_settings_error('hbc_messages', 'hbc_message', __('Group deleted successfully.', 'hall-booking-calendar'), 'updated');
    }
}
add_action('admin_init', 'hbc_handle_group_operations');

/**
 * Groups page
 */
function hbc_admin_groups_page() {
    global $wpdb;
    $groups_table = $wpdb->prefix . 'hbc_groups';

    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

    ?>
    <div class="wrap">
        <h1><?php _e('Hall Booking - Groups', 'hall-booking-calendar'); ?>
            <?php if ($action == 'list') : ?>
                <a href="<?php echo admin_url('admin.php?page=hall-booking-groups&action=add'); ?>" class="page-title-action"><?php _e('Add New', 'hall-booking-calendar'); ?></a>
            <?php endif; ?>
        </h1>

        <?php settings_errors('hbc_messages'); ?>

        <?php if ($action == 'list') : ?>
            <?php hbc_display_groups_list(); ?>
        <?php elseif ($action == 'add') : ?>
            <?php hbc_display_group_form(); ?>
        <?php elseif ($action == 'edit' && $group_id) : ?>
            <?php hbc_display_group_form($group_id); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display groups list
 */
function hbc_display_groups_list() {
    global $wpdb;
    $groups_table = $wpdb->prefix . 'hbc_groups';
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $groups = $wpdb->get_results("SELECT * FROM $groups_table ORDER BY id ASC");

    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Name', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Description', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Bookings', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Status', 'hall-booking-calendar'); ?></th>
                <th><?php _e('Actions', 'hall-booking-calendar'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($groups) : ?>
                <?php foreach ($groups as $group) : ?>
                <?php
                    // Count bookings for this group
                    $booking_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $bookings_table WHERE group_id = %d",
                        $group->id
                    ));
                ?>
                <tr>
                    <td><?php echo esc_html($group->id); ?></td>
                    <td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=hall-booking-bookings&group=' . $group->id)); ?>"><?php echo esc_html(wp_unslash($group->name)); ?></a></strong></td>
                    <td><?php echo esc_html(wp_unslash($group->description)); ?></td>
                    <td><?php echo esc_html($booking_count); ?></td>
                    <td><span class="hbc-status hbc-status-<?php echo esc_attr($group->status); ?>"><?php echo esc_html(ucfirst($group->status)); ?></span></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=hall-booking-groups&action=edit&group_id=' . $group->id); ?>" class="button button-small"><?php _e('Edit', 'hall-booking-calendar'); ?></a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hall-booking-groups&action=delete&group_id=' . $group->id), 'hbc_delete_group_' . $group->id); ?>" class="button button-small" onclick="return confirm('<?php _e('Are you sure you want to delete this group? This will not delete associated bookings.', 'hall-booking-calendar'); ?>')"><?php _e('Delete', 'hall-booking-calendar'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6"><?php _e('No groups found.', 'hall-booking-calendar'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Display group form
 */
function hbc_display_group_form($group_id = 0) {
    global $wpdb;
    $groups_table = $wpdb->prefix . 'hbc_groups';

    $group = null;
    if ($group_id) {
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $groups_table WHERE id = %d", $group_id));
    }

    $is_edit = $group ? true : false;
    ?>
    <form method="post" action="<?php echo admin_url('admin.php?page=hall-booking-groups'); ?>">
        <?php wp_nonce_field($is_edit ? 'hbc_update_group' : 'hbc_add_group', 'hbc_group_nonce'); ?>

        <?php if ($is_edit) : ?>
            <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="group_name"><?php _e('Group Name', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <input type="text" id="group_name" name="group_name" class="regular-text" value="<?php echo $group ? esc_attr(wp_unslash($group->name)) : ''; ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="group_description"><?php _e('Description', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <textarea id="group_description" name="group_description" class="large-text" rows="4"><?php echo $group ? esc_textarea(wp_unslash($group->description)) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="group_status"><?php _e('Status', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <select id="group_status" name="group_status">
                        <option value="active" <?php echo ($group && $group->status == 'active') ? 'selected' : ''; ?>><?php _e('Active', 'hall-booking-calendar'); ?></option>
                        <option value="inactive" <?php echo ($group && $group->status == 'inactive') ? 'selected' : ''; ?>><?php _e('Inactive', 'hall-booking-calendar'); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="<?php echo $is_edit ? 'hbc_update_group' : 'hbc_add_group'; ?>" class="button button-primary" value="<?php echo $is_edit ? __('Update Group', 'hall-booking-calendar') : __('Add Group', 'hall-booking-calendar'); ?>">
            <a href="<?php echo admin_url('admin.php?page=hall-booking-groups'); ?>" class="button"><?php _e('Cancel', 'hall-booking-calendar'); ?></a>
        </p>
    </form>
    <?php
}
