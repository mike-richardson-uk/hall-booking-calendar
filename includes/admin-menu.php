<?php
/**
 * Admin Menu Functions
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register admin menu and submenus for Hall Booking Calendar
 *
 * Creates a top-level menu with the following submenus:
 * - Dashboard (statistics and recent bookings)
 * - Rooms (manage available rooms)
 * - Groups (organize bookings by category)
 * - Bookings (view and manage all bookings)
 * - Subscriptions (calendar feed management)
 * - Bulk Import (CSV upload for batch booking creation)
 * - Settings (configure password protection and emails)
 *
 * @since 1.0.0
 * @return void
 */
function hbc_add_admin_menu() {
    add_menu_page(
        __('Hall Booking Calendar', 'hall-booking-calendar'),
        __('Hall Booking', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-calendar',
        'hbc_admin_dashboard_page',
        'dashicons-calendar-alt',
        30
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Dashboard', 'hall-booking-calendar'),
        __('Dashboard', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-calendar',
        'hbc_admin_dashboard_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Rooms', 'hall-booking-calendar'),
        __('Rooms', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-rooms',
        'hbc_admin_rooms_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Groups', 'hall-booking-calendar'),
        __('Groups', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-groups',
        'hbc_admin_groups_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Event Categories', 'hall-booking-calendar'),
        __('Categories', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-categories',
        'hbc_admin_categories_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Bookings', 'hall-booking-calendar'),
        __('Bookings', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-bookings',
        'hbc_admin_bookings_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Calendar Subscriptions', 'hall-booking-calendar'),
        __('Subscriptions', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-subscriptions',
        'hbc_admin_subscriptions_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Bulk Import', 'hall-booking-calendar'),
        __('Bulk Import', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-bulk-import',
        'hbc_admin_bulk_import_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Export', 'hall-booking-calendar'),
        __('Export', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-export',
        'hbc_admin_export_page'
    );

    add_submenu_page(
        'hall-booking-calendar',
        __('Settings', 'hall-booking-calendar'),
        __('Settings', 'hall-booking-calendar'),
        'manage_options',
        'hall-booking-settings',
        'hbc_admin_settings_page'
    );
}
add_action('admin_menu', 'hbc_add_admin_menu');

/**
 * Display the main dashboard page
 *
 * Shows key statistics:
 * - Active rooms count
 * - Active groups count
 * - Total bookings
 * - Pending bookings requiring approval
 * - Today's bookings
 *
 * Also displays a table of the 10 most recent bookings.
 *
 * @since 1.0.0
 * @return void
 */
function hbc_admin_dashboard_page() {
    global $wpdb;

    $rooms_table      = $wpdb->prefix . 'hbc_rooms';
    $groups_table     = $wpdb->prefix . 'hbc_groups';
    $bookings_table   = $wpdb->prefix . 'hbc_bookings';
    $categories_table = $wpdb->prefix . 'hbc_categories';

    // ── Core statistics ───────────────────────────────────────────────────────
    $total_rooms      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $rooms_table WHERE status = 'active'");
    $total_groups     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $groups_table WHERE status = 'active'");
    $total_categories = (int) $wpdb->get_var("SELECT COUNT(*) FROM $categories_table WHERE status = 'active'");
    $total_bookings   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
    $pending_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status = 'pending'");
    $confirmed_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status = 'confirmed'");
    $cancelled_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status = 'cancelled'");
    $today            = date('Y-m-d');
    $week_start       = date('Y-m-d', strtotime('monday this week'));
    $week_end         = date('Y-m-d', strtotime('sunday this week'));
    $today_bookings   = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND status != 'cancelled'", $today));
    $week_bookings    = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table WHERE booking_date BETWEEN %s AND %s AND status != 'cancelled'",
        $week_start, $week_end));
    $month_bookings   = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table WHERE booking_date BETWEEN %s AND %s AND status != 'cancelled'",
        date('Y-m-01'), date('Y-m-t')));

    // ── Monthly trend: last 6 months ─────────────────────────────────────────
    $monthly_data = array();
    for ($i = 5; $i >= 0; $i--) {
        $month_ts    = strtotime("-$i months", strtotime(date('Y-m-01')));
        $month_label = date('M', $month_ts);
        $month_from  = date('Y-m-01', $month_ts);
        $month_to    = date('Y-m-t', $month_ts);
        $count       = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE booking_date BETWEEN %s AND %s AND status != 'cancelled'",
            $month_from, $month_to
        ));
        $monthly_data[] = array('label' => $month_label, 'count' => $count);
    }
    $max_monthly = max(1, ...array_column($monthly_data, 'count'));

    // ── Bookings by room (top 5) ─────────────────────────────────────────────
    $room_stats = $wpdb->get_results(
        "SELECT r.name, COUNT(br.booking_id) as cnt
         FROM {$wpdb->prefix}hbc_booking_rooms br
         INNER JOIN $rooms_table r ON br.room_id = r.id
         INNER JOIN $bookings_table b ON br.booking_id = b.id
         WHERE b.status != 'cancelled'
         GROUP BY r.id ORDER BY cnt DESC LIMIT 5"
    );
    $max_room_count = max(1, ...array_map(function ($r) { return (int) $r->cnt; }, $room_stats ?: array((object) array('cnt' => 1))));

    ?>
    <div class="wrap">
        <h1><?php _e('Hall Booking Calendar', 'hall-booking-calendar'); ?></h1>

        <?php settings_errors('hbc_messages'); ?>

        <!-- Quick Actions -->
        <div class="hbc-quick-actions">
            <a href="<?php echo esc_url(hbc_get_calendar_page_url() ? add_query_arg('action', 'book', hbc_get_calendar_page_url()) : admin_url('admin.php?page=hall-booking-bookings')); ?>" class="hbc-action-card hbc-action-book">
                <span class="hbc-action-icon dashicons dashicons-plus-alt2"></span>
                <span class="hbc-action-label"><?php _e('New Booking', 'hall-booking-calendar'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=hall-booking-bookings'); ?>" class="hbc-action-card hbc-action-list">
                <span class="hbc-action-icon dashicons dashicons-list-view"></span>
                <span class="hbc-action-label"><?php _e('All Bookings', 'hall-booking-calendar'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=hall-booking-bookings&status=pending'); ?>" class="hbc-action-card hbc-action-pending<?php echo $pending_bookings ? ' hbc-action-card--alert' : ''; ?>">
                <span class="hbc-action-icon dashicons dashicons-clock"></span>
                <span class="hbc-action-label"><?php _e('Pending Approval', 'hall-booking-calendar'); ?><?php if ($pending_bookings) : ?> <span class="hbc-badge"><?php echo esc_html($pending_bookings); ?></span><?php endif; ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=hall-booking-rooms'); ?>" class="hbc-action-card hbc-action-rooms">
                <span class="hbc-action-icon dashicons dashicons-building"></span>
                <span class="hbc-action-label"><?php _e('Manage Rooms', 'hall-booking-calendar'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=hall-booking-export'); ?>" class="hbc-action-card hbc-action-export">
                <span class="hbc-action-icon dashicons dashicons-download"></span>
                <span class="hbc-action-label"><?php _e('Export CSV', 'hall-booking-calendar'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=hall-booking-settings'); ?>" class="hbc-action-card hbc-action-settings">
                <span class="hbc-action-icon dashicons dashicons-admin-settings"></span>
                <span class="hbc-action-label"><?php _e('Settings', 'hall-booking-calendar'); ?></span>
            </a>
        </div>

        <!-- Statistics row -->
        <div class="hbc-dashboard-stats">
            <div class="hbc-stat-box hbc-stat-today">
                <div class="hbc-stat-number"><?php echo esc_html($today_bookings); ?></div>
                <div class="hbc-stat-label"><?php _e('Today', 'hall-booking-calendar'); ?></div>
            </div>
            <div class="hbc-stat-box hbc-stat-week">
                <div class="hbc-stat-number"><?php echo esc_html($week_bookings); ?></div>
                <div class="hbc-stat-label"><?php _e('This Week', 'hall-booking-calendar'); ?></div>
            </div>
            <div class="hbc-stat-box hbc-stat-month">
                <div class="hbc-stat-number"><?php echo esc_html($month_bookings); ?></div>
                <div class="hbc-stat-label"><?php _e('This Month', 'hall-booking-calendar'); ?></div>
            </div>
            <div class="hbc-stat-box hbc-stat-pending">
                <div class="hbc-stat-number"><?php echo esc_html($pending_bookings); ?></div>
                <div class="hbc-stat-label"><?php _e('Pending', 'hall-booking-calendar'); ?></div>
            </div>
            <div class="hbc-stat-box hbc-stat-confirmed">
                <div class="hbc-stat-number"><?php echo esc_html($confirmed_count); ?></div>
                <div class="hbc-stat-label"><?php _e('Confirmed', 'hall-booking-calendar'); ?></div>
            </div>
            <div class="hbc-stat-box hbc-stat-total">
                <div class="hbc-stat-number"><?php echo esc_html($total_bookings); ?></div>
                <div class="hbc-stat-label"><?php _e('Total Bookings', 'hall-booking-calendar'); ?></div>
            </div>
        </div>

        <!-- Analytics: monthly trend + room breakdown -->
        <div class="hbc-analytics-row">
            <div class="hbc-analytics-chart-box">
                <h2><?php _e('Bookings — Last 6 Months', 'hall-booking-calendar'); ?></h2>
                <div class="hbc-bar-chart">
                    <?php foreach ($monthly_data as $m) :
                        $bar_pct = $max_monthly > 0 ? round(($m['count'] / $max_monthly) * 100) : 0;
                    ?>
                    <div class="hbc-bar-col">
                        <div class="hbc-bar-wrap">
                            <div class="hbc-bar" style="height:<?php echo esc_attr($bar_pct); ?>%;" title="<?php echo esc_attr($m['count']); ?>">
                                <span class="hbc-bar-count"><?php echo esc_html($m['count']); ?></span>
                            </div>
                        </div>
                        <div class="hbc-bar-label"><?php echo esc_html($m['label']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hbc-analytics-side">
                <?php if (!empty($room_stats)) : ?>
                <div class="hbc-analytics-room-box">
                    <h2><?php _e('Bookings by Room', 'hall-booking-calendar'); ?></h2>
                    <ul class="hbc-room-stats-list">
                        <?php foreach ($room_stats as $rs) :
                            $room_pct = round(($rs->cnt / $max_room_count) * 100);
                        ?>
                        <li class="hbc-room-stat-item">
                            <span class="hbc-room-stat-name"><?php echo esc_html($rs->name); ?></span>
                            <div class="hbc-room-stat-bar-wrap">
                                <div class="hbc-room-stat-bar" style="width:<?php echo esc_attr($room_pct); ?>%;"></div>
                            </div>
                            <span class="hbc-room-stat-count"><?php echo esc_html($rs->cnt); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="hbc-analytics-status-box">
                    <h2><?php _e('Status Breakdown', 'hall-booking-calendar'); ?></h2>
                    <ul class="hbc-status-stats-list">
                        <li><span class="hbc-status hbc-status-confirmed"><?php _e('Confirmed', 'hall-booking-calendar'); ?></span> <strong><?php echo esc_html($confirmed_count); ?></strong></li>
                        <li><span class="hbc-status hbc-status-pending"><?php _e('Pending', 'hall-booking-calendar'); ?></span> <strong><?php echo esc_html($pending_bookings); ?></strong></li>
                        <li><span class="hbc-status hbc-status-cancelled"><?php _e('Cancelled', 'hall-booking-calendar'); ?></span> <strong><?php echo esc_html($cancelled_count); ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="hbc-recent-bookings">
            <h2><?php _e('Recent Bookings', 'hall-booking-calendar'); ?></h2>
            <?php
            $groups_table = $wpdb->prefix . 'hbc_groups';
            $recent_bookings = $wpdb->get_results(
                "SELECT b.*, r.name as room_name, g.name as group_name
                FROM $bookings_table b
                LEFT JOIN $rooms_table r ON b.room_id = r.id
                LEFT JOIN $groups_table g ON b.group_id = g.id
                ORDER BY b.created_at DESC
                LIMIT 10"
            );

            if ($recent_bookings) {
                // Check if any recent bookings are pending
                $has_pending = false;
                foreach ($recent_bookings as $bk) {
                    if ($bk->status === 'pending') {
                        $has_pending = true;
                        break;
                    }
                }
                ?>
                <form method="post" action="" id="hbc-dashboard-bulk-approve-form">
                    <?php wp_nonce_field('hbc_bulk_approve_bookings', 'hbc_bulk_nonce'); ?>

                    <?php if ($has_pending) : ?>
                        <div class="hbc-bulk-actions" style="margin-bottom: 10px;">
                            <button type="submit" name="hbc_bulk_approve" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to approve all selected bookings?', 'hall-booking-calendar'); ?>');">
                                <?php _e('Approve Selected', 'hall-booking-calendar'); ?>
                            </button>
                            <label style="margin-left: 10px;">
                                <input type="checkbox" id="hbc-dashboard-select-all-pending"> <?php _e('Select All Pending', 'hall-booking-calendar'); ?>
                            </label>
                        </div>
                    <?php endif; ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <?php if ($has_pending) : ?>
                                <th style="width: 30px;"><input type="checkbox" id="hbc-dashboard-select-all" title="<?php esc_attr_e('Select all', 'hall-booking-calendar'); ?>"></th>
                            <?php endif; ?>
                            <th><?php _e('Purpose', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Room', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('User', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Date', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Time', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Status', 'hall-booking-calendar'); ?></th>
                            <th><?php _e('Actions', 'hall-booking-calendar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $booking) : ?>
                        <tr>
                            <?php if ($has_pending) : ?>
                                <td>
                                    <?php if ($booking->status === 'pending') : ?>
                                        <input type="checkbox" name="bulk_booking_ids[]" value="<?php echo esc_attr($booking->id); ?>" class="hbc-dashboard-bulk-checkbox">
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <strong><?php echo $booking->purpose ? esc_html(wp_trim_words($booking->purpose, 8)) : '<em>' . __('None', 'hall-booking-calendar') . '</em>'; ?></strong><br>
                                <small><?php echo $booking->group_name ? esc_html($booking->group_name) : '<em>' . __('No group', 'hall-booking-calendar') . '</em>'; ?></small>
                            </td>
                            <td><?php echo esc_html($booking->room_name); ?></td>
                            <td><?php echo esc_html($booking->user_name); ?></td>
                            <td><?php echo esc_html(date('M j, Y', strtotime($booking->booking_date))); ?></td>
                            <td><?php echo esc_html(date('g:i A', strtotime($booking->start_time)) . ' - ' . date('g:i A', strtotime($booking->end_time))); ?></td>
                            <td><span class="hbc-status hbc-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html(ucfirst($booking->status)); ?></span></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=hall-booking-bookings&action=view&booking_id=' . $booking->id); ?>" class="button button-small"><?php _e('View', 'hall-booking-calendar'); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($has_pending) : ?>
                    <div class="hbc-bulk-actions" style="margin-top: 10px;">
                        <button type="submit" name="hbc_bulk_approve" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to approve all selected bookings?', 'hall-booking-calendar'); ?>');">
                            <?php _e('Approve Selected', 'hall-booking-calendar'); ?>
                        </button>
                    </div>
                <?php endif; ?>

                </form>

                <script>
                jQuery(document).ready(function($) {
                    $('#hbc-dashboard-select-all').on('change', function() {
                        $('.hbc-dashboard-bulk-checkbox').prop('checked', $(this).is(':checked'));
                    });
                    $('#hbc-dashboard-select-all-pending').on('change', function() {
                        $('.hbc-dashboard-bulk-checkbox').prop('checked', $(this).is(':checked'));
                        $('#hbc-dashboard-select-all').prop('checked', $(this).is(':checked'));
                    });
                    $('.hbc-dashboard-bulk-checkbox').on('change', function() {
                        var total = $('.hbc-dashboard-bulk-checkbox').length;
                        var checked = $('.hbc-dashboard-bulk-checkbox:checked').length;
                        $('#hbc-dashboard-select-all').prop('checked', total === checked);
                        $('#hbc-dashboard-select-all-pending').prop('checked', total === checked);
                    });
                });
                </script>
                <?php
            } else {
                echo '<p>' . __('No bookings found.', 'hall-booking-calendar') . '</p>';
            }
            ?>
        </div>
    </div>
    <?php
}
