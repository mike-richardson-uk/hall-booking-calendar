<?php
/**
 * Admin Settings Page
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle settings form submission
 */
function hbc_handle_settings_save() {
    if (!isset($_POST['hbc_save_settings']) || !isset($_POST['hbc_settings_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['hbc_settings_nonce'], 'hbc_save_settings')) {
        wp_die(__('Security check failed', 'hall-booking-calendar'));
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access', 'hall-booking-calendar'));
    }

    // Save booking password with secure hashing
    if (isset($_POST['hbc_booking_password']) && !empty($_POST['hbc_booking_password'])) {
        $booking_password = sanitize_text_field($_POST['hbc_booking_password']);
        // Hash the password for secure storage
        $hashed_password = wp_hash_password($booking_password);
        update_option('hbc_booking_password', $hashed_password);
    }

    // Save webmaster email
    $webmaster_email = isset($_POST['hbc_webmaster_email']) ? sanitize_email($_POST['hbc_webmaster_email']) : get_option('admin_email');
    update_option('hbc_webmaster_email', $webmaster_email);

    // Save password requirement setting
    $require_password = isset($_POST['hbc_require_password']) ? '1' : '0';
    update_option('hbc_require_password', $require_password);

    add_settings_error('hbc_settings', 'hbc_settings_updated', __('Settings saved successfully.', 'hall-booking-calendar'), 'updated');
}
add_action('admin_init', 'hbc_handle_settings_save');

/**
 * Display settings page
 */
function hbc_admin_settings_page() {
    // Don't retrieve the hashed password for display (security best practice)
    $password_is_set = !empty(get_option('hbc_booking_password', ''));
    $webmaster_email = get_option('hbc_webmaster_email', get_option('admin_email'));
    $require_password = get_option('hbc_require_password', '0');

    ?>
    <div class="wrap">
        <h1><?php _e('Hall Booking Settings', 'hall-booking-calendar'); ?></h1>

        <?php settings_errors('hbc_settings'); ?>

        <form method="post" action="">
            <?php wp_nonce_field('hbc_save_settings', 'hbc_settings_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="hbc_webmaster_email"><?php _e('Webmaster Email', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="hbc_webmaster_email" name="hbc_webmaster_email" value="<?php echo esc_attr($webmaster_email); ?>" class="regular-text" required>
                        <p class="description"><?php _e('Email address to receive booking notifications. Defaults to admin email.', 'hall-booking-calendar'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="hbc_require_password"><?php _e('Password Protection', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="hbc_require_password" name="hbc_require_password" value="1" <?php checked($require_password, '1'); ?>>
                            <?php _e('Require password to make bookings', 'hall-booking-calendar'); ?>
                        </label>
                        <p class="description"><?php _e('Enable this to require users to enter a password before booking.', 'hall-booking-calendar'); ?></p>
                    </td>
                </tr>

                <tr id="hbc_password_field_row" style="<?php echo $require_password == '1' ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="hbc_booking_password"><?php _e('Booking Password', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="hbc_booking_password" name="hbc_booking_password" value="" class="regular-text" placeholder="<?php echo $password_is_set ? esc_attr__('Enter new password to change', 'hall-booking-calendar') : esc_attr__('Enter password', 'hall-booking-calendar'); ?>">
                        <p class="description">
                            <?php if ($password_is_set) : ?>
                                <span style="color: green;">✓ <?php _e('Password is currently set and encrypted.', 'hall-booking-calendar'); ?></span><br>
                            <?php endif; ?>
                            <?php _e('Users must enter this password to make a booking. Password is securely hashed and encrypted.', 'hall-booking-calendar'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('Upload Directory', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <?php
                        $upload_dir = wp_upload_dir();
                        $hbc_upload_dir = $upload_dir['basedir'] . '/hall-bookings';
                        $hbc_upload_url = $upload_dir['baseurl'] . '/hall-bookings';
                        // Show relative path instead of full filesystem path
                        $relative_path = str_replace(ABSPATH, '', $hbc_upload_dir);
                        ?>
                        <code><?php echo esc_html($relative_path); ?></code>
                        <p class="description">
                            <?php _e('Booking files (PDF only) are uploaded to this directory.', 'hall-booking-calendar'); ?>
                            <?php if (is_writable($hbc_upload_dir)) : ?>
                                <br><span style="color: green;">✓ <?php _e('Directory is writable', 'hall-booking-calendar'); ?></span>
                            <?php else : ?>
                                <br><span style="color: red;">✗ <?php _e('Directory is not writable', 'hall-booking-calendar'); ?></span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="hbc_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'hall-booking-calendar'); ?>">
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#hbc_require_password').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc_password_field_row').slideDown();
            } else {
                $('#hbc_password_field_row').slideUp();
            }
        });
    });
    </script>
    <?php
}
