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

    // Save agenda limit
    $agenda_limit = isset($_POST['hbc_agenda_limit']) ? intval($_POST['hbc_agenda_limit']) : 10;
    if ($agenda_limit < 1) {
        $agenda_limit = 10;
    }
    update_option('hbc_agenda_limit', $agenda_limit);

    // Save terms and conditions (allow HTML for links etc.)
    if (isset($_POST['hbc_terms_conditions'])) {
        $terms_text = wp_kses_post($_POST['hbc_terms_conditions']);
        update_option('hbc_terms_conditions', $terms_text);
    }

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
    $agenda_limit = get_option('hbc_agenda_limit', 10);
    $terms_conditions = get_option('hbc_terms_conditions', '');

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
                        <label for="hbc_agenda_limit"><?php _e('Agenda View Limit', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="hbc_agenda_limit" name="hbc_agenda_limit" value="<?php echo esc_attr($agenda_limit); ?>" class="small-text" min="1" max="100">
                        <p class="description"><?php _e('Number of bookings to display per page in the agenda view. Default: 10.', 'hall-booking-calendar'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="hbc_terms_conditions"><?php _e('Terms and Conditions', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <textarea id="hbc_terms_conditions" name="hbc_terms_conditions" class="large-text" rows="8"><?php echo esc_textarea($terms_conditions); ?></textarea>
                        <p class="description"><?php _e('HTML is allowed (links, bold, lists, etc.). This text is displayed above a required checkbox on the booking form. Leave blank to disable the terms and conditions requirement.', 'hall-booking-calendar'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('Booking Page', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <?php
                        $booking_page_id = get_option('hbc_booking_page_id', 0);
                        $booking_page = $booking_page_id ? get_post($booking_page_id) : null;
                        if ($booking_page && $booking_page->post_status === 'publish') :
                            $page_url = get_permalink($booking_page_id);
                        ?>
                            <a href="<?php echo esc_url($page_url); ?>" target="_blank"><?php echo esc_html($booking_page->post_title); ?></a>
                            (<a href="<?php echo esc_url(get_edit_post_link($booking_page_id)); ?>"><?php _e('Edit', 'hall-booking-calendar'); ?></a>)
                            <p class="description"><?php _e('This page was created automatically and contains the booking form. You can edit or move it as needed.', 'hall-booking-calendar'); ?></p>
                        <?php else : ?>
                            <span style="color: #999;"><?php _e('No booking page found.', 'hall-booking-calendar'); ?></span>
                            <p class="description"><?php _e('Create a page with the shortcode <code>[hall_booking_form]</code> to add a standalone booking form.', 'hall-booking-calendar'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('Shortcodes', 'hall-booking-calendar'); ?></label>
                    </th>
                    <td>
                        <code>[hall_booking_calendar]</code>
                        <p class="description"><?php _e('Displays the full calendar with booking links. Attributes: <code>view="calendar|agenda|compact"</code>, <code>group="all|{id}"</code>, <code>items="{count}"</code> (compact view only)', 'hall-booking-calendar'); ?></p>
                        <br>
                        <code>[hall_booking_form]</code>
                        <p class="description"><?php _e('Displays the booking form directly. Attributes: <code>group="{id}"</code>, <code>room="{id}"</code>', 'hall-booking-calendar'); ?></p>
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

        <hr>

        <h2><?php _e('Usage Instructions', 'hall-booking-calendar'); ?></h2>

        <nav class="nav-tab-wrapper hbc-instructions-tabs">
            <a href="#hbc-tab-shortcodes" class="nav-tab nav-tab-active"><?php _e('Shortcodes', 'hall-booking-calendar'); ?></a>
            <a href="#hbc-tab-views" class="nav-tab"><?php _e('Views', 'hall-booking-calendar'); ?></a>
            <a href="#hbc-tab-bookings" class="nav-tab"><?php _e('Bookings', 'hall-booking-calendar'); ?></a>
            <a href="#hbc-tab-admin" class="nav-tab"><?php _e('Administration', 'hall-booking-calendar'); ?></a>
        </nav>

        <div class="hbc-instructions">

            <div id="hbc-tab-shortcodes" class="hbc-tab-panel hbc-tab-panel-active">
                <table class="form-table">
                    <tr>
                        <th><code>[hall_booking_calendar]</code></th>
                        <td>
                            <p><?php _e('Displays the monthly calendar grid with room availability and booking links.', 'hall-booking-calendar'); ?></p>
                            <p><strong><?php _e('Attributes:', 'hall-booking-calendar'); ?></strong></p>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <li><code>view="calendar"</code> &mdash; <?php _e('Monthly calendar grid (default).', 'hall-booking-calendar'); ?></li>
                                <li><code>view="agenda"</code> &mdash; <?php _e('Paginated list of upcoming bookings with group and room filters.', 'hall-booking-calendar'); ?></li>
                                <li><code>view="compact"</code> &mdash; <?php _e('Abbreviated single-line-per-event list. Great for sidebars and summary pages.', 'hall-booking-calendar'); ?></li>
                                <li><code>group="all"</code> &mdash; <?php _e('Show all groups (default). Use a group ID to restrict to one group.', 'hall-booking-calendar'); ?></li>
                                <li><code>items="{count}"</code> &mdash; <?php _e('Number of events to show in compact view. Defaults to Agenda View Limit setting.', 'hall-booking-calendar'); ?></li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th><code>[hall_booking_form]</code></th>
                        <td>
                            <p><?php _e('Displays a standalone booking form. Useful for a dedicated booking page.', 'hall-booking-calendar'); ?></p>
                            <p><strong><?php _e('Attributes:', 'hall-booking-calendar'); ?></strong></p>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <li><code>group="{id}"</code> &mdash; <?php _e('Pre-select a group in the form.', 'hall-booking-calendar'); ?></li>
                                <li><code>room="{id}"</code> &mdash; <?php _e('Pre-select a room in the form.', 'hall-booking-calendar'); ?></li>
                            </ul>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="hbc-tab-views" class="hbc-tab-panel">
                <h3><?php _e('Calendar View', 'hall-booking-calendar'); ?></h3>
                <p><?php _e('The default calendar view displays a monthly grid showing room availability at a glance. Each day cell shows colour-coded indicators for each room &mdash; coloured when booked, grey when available. Use the shortcode:', 'hall-booking-calendar'); ?></p>
                <p><code>[hall_booking_calendar]</code></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('<strong>Navigation</strong> &mdash; Use the Previous/Next arrows to move between months.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('<strong>Room legend</strong> &mdash; A colour key below the navigation shows each room name and capacity.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('<strong>Filters</strong> &mdash; Dropdown menus let visitors filter by Group or Room.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('<strong>Booking indicators</strong> &mdash; Click a coloured indicator to view that booking&rsquo;s detail page. Days with multiple bookings also show a &ldquo;View&rdquo; link.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('<strong>Book button</strong> &mdash; Future available dates show a &ldquo;Book&rdquo; button that opens the booking form with the date pre-filled.', 'hall-booking-calendar'); ?></li>
                </ul>
                <p><?php _e('To restrict the calendar to a specific group, pass the group ID:', 'hall-booking-calendar'); ?></p>
                <p><code>[hall_booking_calendar group="1"]</code></p>

                <hr>

                <h3><?php _e('Agenda View', 'hall-booking-calendar'); ?></h3>
                <p><?php printf(__('The agenda view shows a paginated list of upcoming bookings (%d per page, configurable above). Visitors can filter by group or room using the dropdown menus at the top of the view. Use the shortcode below to embed the agenda view on any page:', 'hall-booking-calendar'), intval(get_option('hbc_agenda_limit', 10))); ?></p>
                <p><code>[hall_booking_calendar view="agenda"]</code></p>
                <p><?php _e('To restrict the agenda to a specific group, pass the group ID:', 'hall-booking-calendar'); ?></p>
                <p><code>[hall_booking_calendar view="agenda" group="1"]</code></p>

                <hr>

                <h3><?php _e('Compact Agenda View', 'hall-booking-calendar'); ?></h3>
                <p><?php _e('The compact view shows an abbreviated single-line-per-event list. Each line displays the date, time, purpose, and room. Clicking a line opens the full booking detail. Use the shortcode:', 'hall-booking-calendar'); ?></p>
                <p><code>[hall_booking_calendar view="compact"]</code></p>
                <p><?php _e('Control the number of events with the <code>items</code> parameter:', 'hall-booking-calendar'); ?></p>
                <p><code>[hall_booking_calendar view="compact" items="5"]</code></p>
                <p><?php printf(__('If <code>items</code> is omitted, the Agenda View Limit setting (%d) is used.', 'hall-booking-calendar'), intval(get_option('hbc_agenda_limit', 10))); ?></p>
            </div>

            <div id="hbc-tab-bookings" class="hbc-tab-panel">
                <h3><?php _e('Booking Approval Workflow', 'hall-booking-calendar'); ?></h3>
                <ol style="margin-left: 20px;">
                    <li><?php _e('A user submits a booking through the calendar or booking form.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('The booking is created with a <strong>Pending</strong> status.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('Both the user and the webmaster (approver) receive an email notification. The approver email includes a direct link to review the booking.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('The approver clicks the link in the email (or navigates to <strong>Hall Booking &rarr; Bookings</strong> in the admin panel) and changes the status to <strong>Confirmed</strong> or <strong>Cancelled</strong>.', 'hall-booking-calendar'); ?></li>
                    <li><?php _e('When a booking is confirmed, the booker automatically receives a confirmation email.', 'hall-booking-calendar'); ?></li>
                </ol>
                <p><?php _e('To filter pending bookings, use the status dropdown on the Bookings admin page. You can also use the <strong>Bulk Approve</strong> feature to approve multiple pending bookings at once.', 'hall-booking-calendar'); ?></p>

                <hr>

                <h3><?php _e('Recurring Bookings', 'hall-booking-calendar'); ?></h3>
                <p><?php _e('When creating a booking, users can enable the "Repeat this booking" option. Supported patterns: Daily, Weekly, Every 2 Weeks, Monthly (Same Date), and Monthly (Same Weekday). All dates in a series are sent as a single notification email.', 'hall-booking-calendar'); ?></p>
            </div>

            <div id="hbc-tab-admin" class="hbc-tab-panel">
                <h3><?php _e('Rooms &amp; Groups', 'hall-booking-calendar'); ?></h3>
                <p><?php _e('Manage rooms under <strong>Hall Booking &rarr; Rooms</strong> and groups under <strong>Hall Booking &rarr; Groups</strong>. Each room has a name, description, and capacity. Groups allow you to organise bookings by department, team, or purpose.', 'hall-booking-calendar'); ?></p>

                <hr>

                <h3><?php _e('Calendar Subscriptions (iCal)', 'hall-booking-calendar'); ?></h3>
                <p><?php _e('Users can subscribe to booking calendars using iCal feeds. Manage subscriptions under <strong>Hall Booking &rarr; Subscriptions</strong>. Feeds can be filtered by group or room and are compatible with Google Calendar, Apple Calendar, and Outlook.', 'hall-booking-calendar'); ?></p>

                <hr>

                <h3><?php _e('Bulk CSV Import', 'hall-booking-calendar'); ?></h3>
                <p><?php _e('Import multiple bookings at once via <strong>Hall Booking &rarr; Bulk Import</strong>. The CSV must include these columns:', 'hall-booking-calendar'); ?></p>
                <p><code>room_id, user_name, user_email, booking_date, start_time, end_time, purpose</code></p>
                <p><?php _e('Optional columns: <code>description</code>, <code>group_id</code>, <code>is_recurring</code>, <code>recurrence_pattern</code>, <code>recurrence_end_date</code>.', 'hall-booking-calendar'); ?></p>
            </div>

        </div>
    </div>

    <style>
        .hbc-instructions-tabs {
            margin-top: 10px;
        }
        .hbc-tab-panel {
            display: none;
            padding: 12px 0;
        }
        .hbc-tab-panel-active {
            display: block;
        }
        .hbc-tab-panel h3:first-child {
            margin-top: 0.5em;
        }
        .hbc-tab-panel hr {
            margin: 1.5em 0;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#hbc_require_password').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc_password_field_row').slideDown();
            } else {
                $('#hbc_password_field_row').slideUp();
            }
        });

        // Tab switching for usage instructions
        $('.hbc-instructions-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $('.hbc-instructions-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.hbc-tab-panel').removeClass('hbc-tab-panel-active');
            $(target).addClass('hbc-tab-panel-active');
        });
    });
    </script>
    <?php
}
