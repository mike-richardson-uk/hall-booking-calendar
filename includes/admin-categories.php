<?php
/**
 * Admin Categories Management
 *
 * @since 1.10.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle category operations
 */
function hbc_handle_category_operations() {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'hbc_categories';

    // Add category
    if (isset($_POST['hbc_add_category']) && check_admin_referer('hbc_add_category', 'hbc_category_nonce')) {
        $wpdb->insert($categories_table, array(
            'name' => sanitize_text_field($_POST['category_name']),
            'description' => sanitize_textarea_field($_POST['category_description']),
            'status' => sanitize_text_field($_POST['category_status'])
        ));

        if ($wpdb->insert_id) {
            add_settings_error('hbc_messages', 'hbc_message', __('Category added successfully.', 'hall-booking-calendar'), 'updated');
        }
    }

    // Update category
    if (isset($_POST['hbc_update_category']) && check_admin_referer('hbc_update_category', 'hbc_category_nonce')) {
        $category_id = intval($_POST['category_id']);
        $wpdb->update(
            $categories_table,
            array(
                'name' => sanitize_text_field($_POST['category_name']),
                'description' => sanitize_textarea_field($_POST['category_description']),
                'status' => sanitize_text_field($_POST['category_status'])
            ),
            array('id' => $category_id)
        );

        add_settings_error('hbc_messages', 'hbc_message', __('Category updated successfully.', 'hall-booking-calendar'), 'updated');
    }

    // Delete category
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['category_id']) && check_admin_referer('hbc_delete_category_' . $_GET['category_id'])) {
        $category_id = intval($_GET['category_id']);
        $wpdb->delete($categories_table, array('id' => $category_id));
        add_settings_error('hbc_messages', 'hbc_message', __('Category deleted successfully.', 'hall-booking-calendar'), 'updated');
    }
}
add_action('admin_init', 'hbc_handle_category_operations');

/**
 * Categories page
 */
function hbc_admin_categories_page() {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'hbc_categories';

    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

    ?>
    <div class="wrap">
        <h1><?php _e('Hall Booking - Event Categories', 'hall-booking-calendar'); ?>
            <?php if ($action == 'list') : ?>
                <a href="<?php echo admin_url('admin.php?page=hall-booking-categories&action=add'); ?>" class="page-title-action"><?php _e('Add New', 'hall-booking-calendar'); ?></a>
            <?php endif; ?>
        </h1>

        <?php settings_errors('hbc_messages'); ?>

        <?php if ($action == 'list') : ?>
            <?php hbc_display_categories_list(); ?>
        <?php elseif ($action == 'add') : ?>
            <?php hbc_display_category_form(); ?>
        <?php elseif ($action == 'edit' && $category_id) : ?>
            <?php hbc_display_category_form($category_id); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display categories list
 */
function hbc_display_categories_list() {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'hbc_categories';
    $bookings_table = $wpdb->prefix . 'hbc_bookings';

    $categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY id ASC");

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
            <?php if ($categories) : ?>
                <?php foreach ($categories as $category) : ?>
                <?php
                    $booking_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $bookings_table WHERE category_id = %d",
                        $category->id
                    ));
                ?>
                <tr>
                    <td><?php echo esc_html($category->id); ?></td>
                    <td><strong><?php echo esc_html($category->name); ?></strong></td>
                    <td><?php echo esc_html($category->description); ?></td>
                    <td><?php echo esc_html($booking_count); ?></td>
                    <td><span class="hbc-status hbc-status-<?php echo esc_attr($category->status); ?>"><?php echo esc_html(ucfirst($category->status)); ?></span></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=hall-booking-categories&action=edit&category_id=' . $category->id); ?>" class="button button-small"><?php _e('Edit', 'hall-booking-calendar'); ?></a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hall-booking-categories&action=delete&category_id=' . $category->id), 'hbc_delete_category_' . $category->id); ?>" class="button button-small" onclick="return confirm('<?php _e('Are you sure you want to delete this category? This will not delete associated bookings.', 'hall-booking-calendar'); ?>')"><?php _e('Delete', 'hall-booking-calendar'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6"><?php _e('No categories found.', 'hall-booking-calendar'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Display category form
 */
function hbc_display_category_form($category_id = 0) {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'hbc_categories';

    $category = null;
    if ($category_id) {
        $category = $wpdb->get_row($wpdb->prepare("SELECT * FROM $categories_table WHERE id = %d", $category_id));
    }

    $is_edit = $category ? true : false;
    ?>
    <form method="post" action="<?php echo admin_url('admin.php?page=hall-booking-categories'); ?>">
        <?php wp_nonce_field($is_edit ? 'hbc_update_category' : 'hbc_add_category', 'hbc_category_nonce'); ?>

        <?php if ($is_edit) : ?>
            <input type="hidden" name="category_id" value="<?php echo esc_attr($category->id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="category_name"><?php _e('Category Name', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <input type="text" id="category_name" name="category_name" class="regular-text" value="<?php echo $category ? esc_attr($category->name) : ''; ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="category_description"><?php _e('Description', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <textarea id="category_description" name="category_description" class="large-text" rows="4"><?php echo $category ? esc_textarea($category->description) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="category_status"><?php _e('Status', 'hall-booking-calendar'); ?></label></th>
                <td>
                    <select id="category_status" name="category_status">
                        <option value="active" <?php echo ($category && $category->status == 'active') ? 'selected' : ''; ?>><?php _e('Active', 'hall-booking-calendar'); ?></option>
                        <option value="inactive" <?php echo ($category && $category->status == 'inactive') ? 'selected' : ''; ?>><?php _e('Inactive', 'hall-booking-calendar'); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="<?php echo $is_edit ? 'hbc_update_category' : 'hbc_add_category'; ?>" class="button button-primary" value="<?php echo $is_edit ? __('Update Category', 'hall-booking-calendar') : __('Add Category', 'hall-booking-calendar'); ?>">
            <a href="<?php echo admin_url('admin.php?page=hall-booking-categories'); ?>" class="button"><?php _e('Cancel', 'hall-booking-calendar'); ?></a>
        </p>
    </form>
    <?php
}
