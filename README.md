# Hall Booking Calendar - WordPress Plugin

A comprehensive WordPress plugin for managing hall bookings with multiple rooms. Users can view availability and book rooms through an intuitive calendar interface.

## Features

- **Multi-Room Management**: Manage up to 3 rooms (expandable) with custom details
- **Group Organization**: Organize bookings by groups (departments, teams, event types, etc.)
- **Recurring Bookings**: Schedule repeating bookings (daily, weekly, biweekly, monthly, or every Nth weekday)
- **Multi-Date Bookings**: Book multiple specific dates in a single reservation
- **Calendar Subscriptions**: Subscribe to booking calendars via iCal/ICS feeds for Google Calendar, Outlook, Apple Calendar, etc.
- **Interactive Calendar**: Visual calendar showing room availability
- **Group Filtering**: Filter calendar and bookings by specific groups
- **User-Friendly Booking**: Simple booking form with validation and group selection
- **Admin Dashboard**: Complete admin interface for managing rooms, groups, and bookings
- **Conflict Prevention**: Automatic checking for booking conflicts across all dates
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
- **Groups**: Add, edit, or delete groups for organizing bookings
- **Bookings**: View and manage all bookings, update status
- **Subscriptions**: Create and manage calendar subscriptions (iCal feeds)

### Frontend Display

Use the following shortcode to display the booking calendar on any page or post:

```
[hall_booking_calendar]
```

This will display:
- An interactive monthly calendar
- Visual indicators showing room availability
- Group filter dropdown (when showing all bookings)
- Booking buttons for available dates
- A booking form modal

#### Shortcode Parameters

**Display all bookings (default):**
```
[hall_booking_calendar]
```

**Display bookings for a specific group:**
```
[hall_booking_calendar group="3"]
```
Replace `3` with the group ID. When filtering by a specific group, only bookings for that group will be shown on the calendar.

### Room Management

1. Go to **Hall Booking → Rooms**
2. Click "Add New" to create a new room
3. Fill in:
   - Room Name
   - Description
   - Capacity
   - Status (Active/Inactive)
4. Click "Add Room"

### Group Management

1. Go to **Hall Booking → Groups**
2. Click "Add New" to create a new group
3. Fill in:
   - Group Name (e.g., "Marketing Department", "Training Sessions")
   - Description
   - Status (Active/Inactive)
4. Click "Add Group"

Groups help organize bookings by category, department, team, or event type. Users can optionally select a group when making a booking.

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
   - Select a group (optional)
   - Enter name and email
   - Choose date and time
   - **For Recurring Bookings**: Check "Repeat this booking", select pattern (daily, weekly, biweekly, monthly), and set end date
   - **For Multi-Date Bookings**: Check "Book multiple specific dates" and add additional dates
   - Add purpose (optional)
4. Submit the booking
5. Receive confirmation email

**Recurring Booking Patterns:**
- **Daily**: Every day until end date
- **Weekly**: Same day each week
- **Every 2 Weeks**: Biweekly on the same day
- **Monthly (Same Date)**: Same date each month (e.g., 15th of each month)
- **Monthly (Same Weekday)**: Same weekday position each month (e.g., third Wednesday)

### Calendar Subscriptions

1. Go to **Hall Booking → Subscriptions**
2. Optionally filter by group or room
3. Click "Create Subscription" to generate an iCal feed URL
4. Copy the subscription URL
5. Add to your calendar application:
   - **Google Calendar**: Settings → Add calendar → From URL
   - **Apple Calendar**: File → New Calendar Subscription
   - **Outlook**: Calendar → Add calendar → Subscribe from web

Your calendar app will automatically sync and display all bookings from the hall calendar.

## Database Tables

The plugin creates four tables:

- `wp_hbc_rooms`: Stores room information (name, description, capacity)
- `wp_hbc_groups`: Stores group information for organizing bookings
- `wp_hbc_bookings`: Stores booking details with room/group associations and recurring patterns
- `wp_hbc_subscriptions`: Stores calendar subscription tokens and filters

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

### 1.2.0
- **Recurring Bookings**: Create repeating bookings with multiple patterns (daily, weekly, biweekly, monthly, monthly weekday)
- **Multi-Date Bookings**: Book multiple specific dates in a single reservation
- **Calendar Subscriptions**: iCal/ICS feed generation for syncing with external calendars
- Added subscription management interface with token-based access
- Support for filtering subscriptions by group or room
- Enhanced database schema with recurring patterns and series tracking
- Conflict detection across all dates in recurring/multi-date bookings
- Frontend UI for recurring and multi-date booking options
- Booking series management (future ability to edit/delete series)

### 1.1.0
- Added group management system for organizing bookings
- Added Groups admin interface for creating and managing groups
- Added group selection to booking form (optional field)
- Added group filtering to calendar display via shortcode parameter
- Added group filter dropdown on frontend calendar
- Display group information in admin bookings list and details
- Updated dashboard to show active groups count
- Default groups created on activation

### 1.0.0
- Initial release
- Multi-room calendar system
- Booking management
- Admin interface
- Email notifications
