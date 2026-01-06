# Elementor Widget - Hall Booking Calendar

**Added in Version:** 1.4.0

The Hall Booking Calendar plugin now includes native Elementor integration, allowing you to easily add the booking calendar to any Elementor-built page.

---

## Features

✅ **Drag & Drop Integration** - Add the calendar widget directly in Elementor's visual editor
✅ **Live Preview** - See widget settings in real-time while editing
✅ **Customizable Controls** - Configure view type and group filters visually
✅ **Style Controls** - Adjust spacing and border radius from Elementor
✅ **Automatic Detection** - Widget only loads when Elementor is active

---

## Requirements

- **WordPress:** 5.0 or higher
- **Elementor:** 3.0 or higher (Free or Pro)
- **Hall Booking Calendar:** 1.4.0 or higher

---

## How to Use

### Adding the Widget

1. **Edit a Page with Elementor**
   - Go to Pages → Edit with Elementor (on any page)
   - Or create a new page and click "Edit with Elementor"

2. **Find the Widget**
   - In the left sidebar, search for "Hall Booking Calendar"
   - Or look under the "General" category
   - The widget has a calendar icon 📅

3. **Drag to Page**
   - Drag the widget to your desired location
   - Drop it into any section or column

4. **Configure Settings**
   - Click on the widget to open settings panel
   - Adjust the options as needed (see below)

5. **Publish**
   - Click "Update" or "Publish" to save your changes

---

## Widget Settings

### Content Tab

#### Calendar Settings

**View Type**
- **Calendar View** (Default) - Monthly grid showing room availability
- **Agenda View** - List view of upcoming bookings

**Group Filter**
- **All Groups** (Default) - Show bookings from all groups
- **Specific Group** - Filter by a single group (dropdown of active groups)

### Style Tab

#### Calendar Style

**Spacing**
- Controls margin above and below the calendar
- Range: 0-100px
- Default: 20px
- Responsive: Different values for desktop, tablet, mobile

**Border Radius**
- Rounds the corners of calendar day cells
- Range: 0-50px or 0-50%
- Applied to each calendar day square

---

## Widget Settings Explained

### When to Use Calendar View
- Best for: Seeing availability at a glance
- Shows: Month grid with all rooms
- Interaction: Click dates to book or view details
- Perfect for: Main booking page, availability overview

### When to Use Agenda View
- Best for: Seeing upcoming bookings in detail
- Shows: Paginated list of bookings
- Interaction: Browse bookings chronologically
- Perfect for: Event lists, booking history

### Group Filtering
- **All Groups**: Shows all bookings regardless of group
- **Specific Group**: Only shows bookings from selected group
  - Useful for department-specific pages
  - Creates focused booking views
  - Example: "Training Room Bookings" page

---

## Examples

### Example 1: Main Booking Page
```
Settings:
- View Type: Calendar View
- Group Filter: All Groups
- Spacing: 30px
```

### Example 2: Department Page
```
Settings:
- View Type: Agenda View
- Group Filter: Department Meetings
- Spacing: 20px
```

### Example 3: Training Schedule
```
Settings:
- View Type: Calendar View
- Group Filter: Training Sessions
- Border Radius: 10px
```

---

## Advanced Usage

### Combining with Elementor Features

**Tabs Widget**
Create tabbed views:
- Tab 1: Calendar View
- Tab 2: Agenda View
- Tab 3: Booking Form

**Toggle Widget**
Create collapsible sections:
- Main content visible
- Calendar hidden until toggled

**Columns**
Side-by-side layouts:
- Column 1: Booking info (text)
- Column 2: Calendar widget

**Sections with Background**
Enhance visual appeal:
- Add background color/image to section
- Place calendar widget inside
- Adjust spacing for perfect alignment

---

## Troubleshooting

### Widget Not Appearing?

**Check Elementor is Active**
```
Plugins → Installed Plugins → Elementor
Ensure it's activated
```

**Check Plugin Version**
```
Plugins → Installed Plugins → Hall Booking Calendar
Must be version 1.4.0 or higher
```

**Clear Elementor Cache**
```
Elementor → Tools → Regenerate CSS
Elementor → Tools → Sync Library
```

### Widget Shows Error Message?

**"Hall Booking Calendar plugin is not properly activated"**

Solution:
1. Deactivate Hall Booking Calendar
2. Reactivate it
3. Check that database tables were created
4. Refresh Elementor editor

### Calendar Not Displaying?

**Check Plugin Tables**
- Go to WordPress Dashboard → Hall Booking Calendar → Rooms
- Ensure at least one room exists
- Check that rooms are set to "Active" status

**Check Permissions**
- Ensure current user has appropriate permissions
- Try with an administrator account

### Styling Issues?

**CSS Conflicts**
- Check theme compatibility
- Try disabling other plugins temporarily
- Use browser inspector to identify conflicts

**Clear All Caches**
- Clear WordPress cache (if using caching plugin)
- Clear Elementor cache
- Clear browser cache
- Test in incognito/private mode

---

## Developer Notes

### Widget Class
- Class: `HBC_Elementor_Widget`
- File: `includes/elementor-widget.php`
- Extends: `\Elementor\Widget_Base`

### Hooks Available
- `elementor/widgets/register` - Widget registration
- `elementor/frontend/after_enqueue_scripts` - Script enqueuing

### Customization
Developers can extend the widget by:
- Filtering `get_group_options()` output
- Adding custom style controls
- Modifying render output

### Code Example
```php
// Add custom control to widget
add_action('elementor/element/hall-booking-calendar/content_section/before_section_end', function($element, $args) {
    $element->add_control(
        'custom_setting',
        [
            'label' => __('Custom Setting', 'your-domain'),
            'type' => \Elementor\Controls_Manager::TEXT,
        ]
    );
}, 10, 2);
```

---

## Compatibility

### Tested With
- ✅ Elementor 3.0+
- ✅ Elementor Pro 3.0+
- ✅ WordPress 5.0+
- ✅ PHP 7.0+

### Theme Compatibility
- ✅ Twenty Twenty-Three
- ✅ Astra
- ✅ GeneratePress
- ✅ OceanWP
- ✅ Most standard WordPress themes

### Page Builder Compatibility
- ✅ Elementor (Native support)
- ✅ Classic Editor (via shortcode)
- ✅ Gutenberg (via shortcode block)

---

## Shortcode Alternative

If you prefer using shortcodes, the traditional method still works:

```
[hall_booking_calendar view="calendar" group="all"]
```

The Elementor widget uses the same underlying shortcode functionality, providing a visual interface for the same features.

---

## Support

For issues specific to Elementor integration:

1. **Check this documentation** first
2. **Check Elementor requirements** (version, PHP, WordPress)
3. **Test with default theme** (Twenty Twenty-Three)
4. **Disable other plugins** temporarily
5. **Report issue** with details about your setup

---

## Changelog

### Version 1.4.0
- ✅ Initial Elementor widget release
- ✅ Calendar and Agenda view support
- ✅ Group filtering
- ✅ Style controls for spacing and border radius
- ✅ Live preview in editor
- ✅ Automatic Elementor detection

---

## Future Enhancements

Planned features for future versions:
- Additional style controls (colors, fonts)
- Template selection
- Advanced filtering options
- Booking form integration
- Custom date range selector

---

**Need Help?**

- 📖 Plugin Documentation: See main README.md
- 🐛 Report Issues: GitHub Issues
- 💡 Feature Requests: GitHub Discussions

---

**Enjoy building with Elementor! 🎨**
