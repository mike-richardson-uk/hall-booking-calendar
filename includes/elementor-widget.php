<?php
/**
 * Elementor Widget for Hall Booking Calendar
 *
 * Provides Elementor integration allowing users to add the booking calendar
 * to their Elementor-built pages with visual controls.
 *
 * @package Hall_Booking_Calendar
 * @since 1.4.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Hall Booking Calendar Elementor Widget
 *
 * @since 1.4.0
 */
class HBC_Elementor_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     *
     * @since 1.4.0
     * @return string Widget name
     */
    public function get_name() {
        return 'hall-booking-calendar';
    }

    /**
     * Get widget title
     *
     * @since 1.4.0
     * @return string Widget title
     */
    public function get_title() {
        return __('Hall Booking Calendar', 'hall-booking-calendar');
    }

    /**
     * Get widget icon
     *
     * @since 1.4.0
     * @return string Widget icon
     */
    public function get_icon() {
        return 'eicon-calendar';
    }

    /**
     * Get widget categories
     *
     * @since 1.4.0
     * @return array Widget categories
     */
    public function get_categories() {
        return array('general');
    }

    /**
     * Get widget keywords
     *
     * @since 1.4.0
     * @return array Widget keywords
     */
    public function get_keywords() {
        return array('booking', 'calendar', 'hall', 'room', 'reservation');
    }

    /**
     * Register widget controls
     *
     * Adds controls to the Elementor editor panel for configuring
     * the calendar widget appearance and behavior.
     *
     * @since 1.4.0
     * @return void
     */
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Calendar Settings', 'hall-booking-calendar'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        // View Type Control
        $this->add_control(
            'view',
            array(
                'label' => __('View Type', 'hall-booking-calendar'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'calendar',
                'options' => array(
                    'calendar' => __('Calendar View', 'hall-booking-calendar'),
                    'agenda' => __('Agenda View', 'hall-booking-calendar'),
                ),
                'description' => __('Choose how to display bookings: calendar grid or list view.', 'hall-booking-calendar'),
            )
        );

        // Group Filter Control
        $this->add_control(
            'group',
            array(
                'label' => __('Group Filter', 'hall-booking-calendar'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => $this->get_group_options(),
                'description' => __('Filter bookings by a specific group or show all.', 'hall-booking-calendar'),
            )
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            array(
                'label' => __('Calendar Style', 'hall-booking-calendar'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        // Calendar Spacing
        $this->add_responsive_control(
            'calendar_spacing',
            array(
                'label' => __('Spacing', 'hall-booking-calendar'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 20,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .hbc-calendar-container' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        // Calendar Border Radius
        $this->add_control(
            'calendar_border_radius',
            array(
                'label' => __('Border Radius', 'hall-booking-calendar'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ),
                    '%' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .hbc-calendar-day' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Get group options for dropdown
     *
     * Retrieves all active groups from database for the group filter control.
     *
     * @since 1.4.0
     * @return array Group options
     */
    protected function get_group_options() {
        global $wpdb;
        $groups_table = $wpdb->prefix . 'hbc_groups';

        $options = array('all' => __('All Groups', 'hall-booking-calendar'));

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $groups_table));
        if (!$table_exists) {
            return $options;
        }

        $groups = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM $groups_table WHERE status = %s ORDER BY name ASC",
            'active'
        ));

        if ($groups) {
            foreach ($groups as $group) {
                $options[$group->id] = esc_html($group->name);
            }
        }

        return $options;
    }

    /**
     * Render widget output on the frontend
     *
     * Uses the existing shortcode functionality to render the calendar.
     *
     * @since 1.4.0
     * @return void
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Build shortcode attributes
        $atts = array(
            'view' => sanitize_text_field($settings['view']),
            'group' => sanitize_text_field($settings['group']),
        );

        // Output the calendar using the existing shortcode function
        if (function_exists('hbc_calendar_shortcode')) {
            echo hbc_calendar_shortcode($atts);
        } else {
            echo '<div class="elementor-alert elementor-alert-warning">';
            echo esc_html__('Hall Booking Calendar plugin is not properly activated.', 'hall-booking-calendar');
            echo '</div>';
        }
    }

    /**
     * Render widget output in the Elementor editor
     *
     * Provides a live preview in the Elementor editor panel.
     *
     * @since 1.4.0
     * @return void
     */
    protected function content_template() {
        ?>
        <#
        var viewText = settings.view === 'calendar' ? '<?php echo esc_js(__('Calendar View', 'hall-booking-calendar')); ?>' : '<?php echo esc_js(__('Agenda View', 'hall-booking-calendar')); ?>';
        #>
        <div class="elementor-shortcode">
            <div style="padding: 20px; background: #f5f5f5; border: 2px dashed #ddd; border-radius: 5px; text-align: center;">
                <span class="eicon-calendar" style="font-size: 48px; color: #71d7f7;"></span>
                <h3><?php esc_html_e('Hall Booking Calendar', 'hall-booking-calendar'); ?></h3>
                <p>
                    <strong><?php esc_html_e('View:', 'hall-booking-calendar'); ?></strong> {{{ viewText }}}<br>
                    <strong><?php esc_html_e('Group:', 'hall-booking-calendar'); ?></strong> {{{ settings.group === 'all' ? '<?php echo esc_js(__('All Groups', 'hall-booking-calendar')); ?>' : settings.group }}}
                </p>
                <p style="color: #999; font-size: 12px;">
                    <?php esc_html_e('Calendar will be displayed here on the frontend', 'hall-booking-calendar'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
