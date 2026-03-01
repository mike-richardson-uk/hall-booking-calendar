# Hall Booking Calendar - WordPress Plugin

A comprehensive WordPress plugin for managing hall bookings with multiple rooms. Users can view availability and book rooms through an intuitive calendar interface.

## Features

- **Multi-Room Management**: Manage up to 3 rooms (expandable) with custom details
- **Group Organization**: Organize bookings by groups (departments, teams, event types, etc.)
- **Recurring Bookings**: Schedule repeating bookings (daily, weekly, biweekly, monthly, or every Nth weekday)
- **Multi-Date Bookings**: Book multiple specific dates in a single reservation
- **Calendar Subscriptions**: Subscribe to booking calendars via iCal/ICS feeds for Google Calendar, Outlook, Apple Calendar, etc.
- **Interactive Calendar**: Visual calendar showing room availability
- **Agenda View**: Paginated list view of upcoming bookings with group filtering
- **Single Booking Pages**: Dedicated page for each booking with full details
- **Group Filtering**: Filter calendar and bookings by specific groups
- **Password Protection**: Optional password requirement for booking submissions
- **File Attachments**: Upload PDF files (up to 5MB) with bookings
- **Description Field**: Add detailed descriptions to bookings
- **Simple Booking Form**: Clean single-page booking form with all fields visible
- **Email Notifications**: Automated emails to both user and webmaster for confirmations, plus acceptance emails on approval
- **Bulk Approve**: Approve multiple pending bookings at once from the admin bookings screen
- **Admin Dashboard**: Complete admin interface for managing rooms, groups, and bookings
- **Conflict Prevention**: Automatic checking for booking conflicts across all dates
- **Status Management**: Pending, confirmed, and cancelled booking statuses
- **Responsive Design**: Works on desktop, tablet, and mobile devices

## Installation

1. Upload the `hall-booking-calendar` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create 3 default rooms during activation

## Usage

### Admin Interface

After activation, you'll find a new "Hall Booking" menu item in your WordPress admin:

- **Dashboard**: View booking statistics, recent bookings, and bulk approve pending bookings
- **Rooms**: Add, edit, or delete rooms
- **Groups**: Add, edit, or delete groups for organizing bookings
- **Bookings**: View and manage all bookings, update status, view attached files
- **Subscriptions**: Create and manage calendar subscriptions (iCal feeds)
- **Settings**: Configure booking password protection and webmaster email

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
- Clickable booking indicators linking to booking details

#### Calendar View

The default calendar view displays a monthly grid showing room availability at a glance:

- **Monthly grid** — Each day cell shows colour-coded indicators for every room (coloured when booked, grey when available)
- **Navigation** — Previous/Next arrows to move between months
- **Room legend** — A colour key below the navigation shows each room name and capacity
- **Filters** — Dropdown menus let visitors filter by Group or Room
- **Booking indicators** — Click a coloured indicator to open that booking's detail page. Days with multiple bookings also show a "View" link
- **Book button** — Future available dates show a "Book" button that opens the booking form with the date pre-filled
- **Today highlighting** — The current date is visually highlighted

#### Shortcode Parameters

**Calendar View (default):**
```
[hall_booking_calendar]
```

**Agenda View (paginated list):**
```
[hall_booking_calendar view="agenda"]
```

**Compact Agenda View (abbreviated single-line-per-event list):**
```
[hall_booking_calendar view="compact"]
[hall_booking_calendar view="compact" items="5"]
[hall_booking_calendar view="compact" group="3" items="10"]
```
The `items` parameter controls how many events to display. If omitted, uses the Agenda View Limit from Settings.

**Display bookings for a specific group:**
```
[hall_booking_calendar view="calendar" group="3"]
[hall_booking_calendar view="agenda" group="3"]
```
Replace `3` with the group ID. When filtering by a specific group, only bookings for that group will be shown.

#### Compact Agenda View

The compact agenda displays a condensed, single-line-per-event list of upcoming bookings. Each line shows:
- **Date**: Abbreviated date (e.g., "Mon 6 Jan")
- **Time**: Start time
- **Purpose**: Truncated purpose text (primary identifier)
- **Room**: Room name(s)

Clicking any line opens the full booking detail page. Status is indicated via a colored left border (green = confirmed, amber = pending). Ideal for embedding in sidebars or summary pages.

#### Agenda View Features

The agenda view displays upcoming bookings in a list format with:
- **Pagination**: Configurable bookings per page (default 10) with page navigation
- **Group & Room Filters**: Dropdowns to filter bookings by group or room
- **Date Headers**: Bookings organized by date (e.g., "Monday, January 6, 2026")
- **Time Display**: Clear start and end times for each booking
- **Booking Details**: Purpose shown as the primary heading, with room name below
- **View Details Links**: Click to see full booking information on dedicated page
- **Upcoming Only**: Shows only future bookings (automatically excludes past dates)

#### Single Booking Pages

Each booking has a dedicated detail page accessible by clicking:
- Booking indicators on the calendar
- "View Details" button in the agenda view
- "View" link in date listings

The single booking page displays:
- Booking purpose as the page heading
- Date, time, and room details
- Description (if provided)
- Attached PDF file (if uploaded) with download link
- Link to view all future bookings for the same group
- Recurring series information (if part of a series) with links to other bookings
- Back button to return to calendar/agenda

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

### Settings Configuration

1. Go to **Hall Booking → Settings**
2. Configure:
   - **Webmaster Email**: Set email address for admin notifications (defaults to WordPress admin email)
   - **Password Protection**: Toggle to enable/disable password requirement
   - **Booking Password**: Set an optional password to protect booking submissions
   - **Agenda View Limit**: Set the number of bookings displayed per page in the agenda view (default: 10)
3. Click "Save Settings"

When password protection is enabled, users must enter the correct password to submit bookings.

### Managing Bookings

1. Go to **Hall Booking → Bookings**
2. View all bookings with filtering options
3. Click "View" to see booking details
4. Update booking status (Pending/Confirmed/Cancelled)
5. Use **Bulk Approve** to confirm multiple pending bookings at once by selecting checkboxes and clicking "Approve Selected"
6. Delete bookings if needed

When a booking is confirmed (either individually or via bulk approve), the booker automatically receives a confirmation email.

### Bulk Import Bookings

1. Go to **Hall Booking → Bulk Import**
2. Download the example CSV template
3. Fill in your booking data following the format
4. Upload the CSV file
5. Review import results (success/failures)

**CSV Format Requirements:**

Required columns:
- `room_id` - Room ID (numeric)
- `user_name` - Name of person booking
- `user_email` - Valid email address
- `booking_date` - Date in YYYY-MM-DD format
- `start_time` - Start time in HH:MM format (24-hour)
- `end_time` - End time in HH:MM format (24-hour)
- `purpose` - Purpose of booking

Optional columns:
- `description` - Detailed description
- `group_id` - Group ID (numeric)
- `is_recurring` - 1 for recurring, 0 for single booking
- `recurrence_pattern` - daily, weekly, biweekly, monthly, monthly_weekday
- `recurrence_end_date` - End date for recurring bookings (YYYY-MM-DD)

**Important Notes:**
- Header row is required
- All imported bookings are auto-confirmed
- Conflicts will be detected and skipped
- Room/Group IDs must exist and be active
- Dates cannot be in the past
- UTF-8 encoding recommended

See `example-bookings.csv` for a complete example.

### User Booking Process

1. Users visit the page with the calendar shortcode
2. Click "Book" on a date to open the full-page booking form
3. Fill in all required fields on the simple single-page form:

   **Authentication** (if password protection is enabled):
   - Enter the booking password

   **Room & Time**:
   - Select a room
   - Choose booking date (pre-filled if clicked from calendar)
   - Set start and end times

   **Your Information**:
   - Enter name and email
   - Select a group (optional)

   **Additional Details** (all optional):
   - Purpose of booking
   - Detailed description
   - Upload PDF file (max 5MB)
   - **For Recurring Bookings**: Check "Repeat this booking", select pattern (daily, weekly, biweekly, monthly), and set end date
   - **For Multi-Date Bookings**: Check "Book multiple specific dates" and add additional dates

4. Click "Submit Booking"
5. See success confirmation message on screen
6. Automatically redirected to calendar after 4 seconds
7. Both user and webmaster receive confirmation emails

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
- `wp_hbc_bookings`: Stores booking details with room/group associations, recurring patterns, descriptions, and file attachments
- `wp_hbc_subscriptions`: Stores calendar subscription tokens and filters

## Email Notifications

The plugin sends automated emails to both users and administrators:
- **User Email**: Booking confirmation with all details (pending approval)
- **Webmaster Email**: Admin notification of new bookings with complete information
- **Acceptance Email**: When a booking is confirmed, the booker receives a confirmation email with booking details
- **Combined Series Emails**: For recurring/multi-date bookings, one email includes all dates
- Email content includes: room, date/time, purpose, description, and PDF attachment link (if uploaded)

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

## Development

### Prerequisites

- Node.js 18+ and npm 9+ (for JavaScript/CSS linting)
- Composer (for PHP linting)

### Setup Development Environment

1. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

### Code Linting

This project uses automated code linting to maintain code quality and consistency:

- **PHP**: PHP_CodeSniffer (PHPCS) with WordPress Coding Standards
- **JavaScript**: ESLint with WordPress recommended configuration
- **CSS**: stylelint with WordPress standards

#### Run All Linters

Install dependencies first (`npm install` and `composer install`), then:

```bash
npm run lint
```

This runs JS, CSS, and PHP linters. Without installed dependencies, lint commands will not be available.

#### Run Individual Linters

**PHP Linting:**
```bash
npm run lint:php
# or directly with composer
composer run lint
```

**JavaScript Linting:**
```bash
npm run lint:js
```

**CSS Linting:**
```bash
npm run lint:css
```

#### Auto-fix Issues

**JavaScript and CSS:**
```bash
npm run lint:fix
```

**PHP:**
```bash
composer run lint:fix
```

### Testing

No automated test suite is configured yet. To add PHPUnit tests (e.g. for conflict detection, token validation):

1. Add `phpunit/phpunit` and `yoast/phpunit-polyfills` (or similar) as dev dependencies.
2. Add a `phpunit.xml.dist` in the project root and a `tests/` directory.
3. Run tests with `./vendor/bin/phpunit` or `composer run test` if configured.

### Configuration Files

- `.eslintrc.json` - ESLint configuration for JavaScript
- `.stylelintrc.json` - stylelint configuration for CSS
- `phpcs.xml` - PHP_CodeSniffer configuration
- `.editorconfig` - Editor configuration for consistent coding style

### Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Run linters and fix any issues before committing
4. Commit your changes with clear messages
5. Push to your fork and submit a pull request

## Support

For issues, questions, or contributions, please visit:
https://github.com/mike-richardson-uk/hall-booking-calendar

## License

GPL-2.0+

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full release history.
