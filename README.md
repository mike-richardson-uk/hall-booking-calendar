# Hall Booking Calendar - WordPress Plugin

A comprehensive WordPress plugin for managing hall bookings with multiple rooms. Users can view availability and book rooms through an intuitive calendar interface.

## Features

- **Multi-Room Management**: Manage up to 3 rooms (expandable) with custom details
- **Interactive Calendar**: Visual calendar showing room availability
- **User-Friendly Booking**: Simple booking form with validation
- **Admin Dashboard**: Complete admin interface for managing rooms and bookings
- **Conflict Prevention**: Automatic checking for booking conflicts
- **Email Notifications**: Automated emails for booking confirmations
- **Status Management**: Pending, confirmed, and cancelled booking statuses
- **Responsive Design**: Works on desktop, tablet, and mobile devices

## Installation

1. Upload the `hall-booking-calendar` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create 3 default rooms during activation

## Usage

### Admin Interface

After activation, you'll find a new "Hall Booking" menu item in your WordPress admin:

- **Dashboard**: View booking statistics and recent bookings
- **Rooms**: Add, edit, or delete rooms
- **Bookings**: View and manage all bookings, update status

### Frontend Display

Use the following shortcode to display the booking calendar on any page or post:

```
[hall_booking_calendar]
```

This will display:
- An interactive monthly calendar
- Visual indicators showing room availability
- Booking buttons for available dates
- A booking form modal

### Room Management

1. Go to **Hall Booking → Rooms**
2. Click "Add New" to create a new room
3. Fill in:
   - Room Name
   - Description
   - Capacity
   - Status (Active/Inactive)
4. Click "Add Room"

### Managing Bookings

1. Go to **Hall Booking → Bookings**
2. View all bookings with filtering options
3. Click "View" to see booking details
4. Update booking status (Pending/Confirmed/Cancelled)
5. Delete bookings if needed

### User Booking Process

1. Users visit the page with the calendar shortcode
2. Click on a date to open the booking form
3. Fill in:
   - Select a room
   - Enter name and email
   - Choose date and time
   - Add purpose (optional)
4. Submit the booking
5. Receive confirmation email

## Database Tables

The plugin creates two tables:

- `wp_hbc_rooms`: Stores room information
- `wp_hbc_bookings`: Stores booking details

## Email Notifications

The plugin sends emails for:
- User booking confirmation (pending approval)
- Admin notification of new bookings

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- MySQL 5.6 or higher

## Customization

### Room Colors

Edit `/assets/css/frontend-style.css` to customize room colors:

```css
.hbc-room-1 { background: #3498db; }
.hbc-room-2 { background: #e74c3c; }
.hbc-room-3 { background: #2ecc71; }
```

### Business Hours

Modify the booking form validation in `/includes/booking-handler.php` to enforce business hours.

## Support

For issues, questions, or contributions, please visit:
https://github.com/slashzero/hall-calendar

## License

GPL-2.0+

## Changelog

### 1.0.0
- Initial release
- Multi-room calendar system
- Booking management
- Admin interface
- Email notifications
