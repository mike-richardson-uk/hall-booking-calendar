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
- **Email Notifications**: Automated emails to both user and webmaster for confirmations
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

- **Dashboard**: View booking statistics and recent bookings
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

#### Shortcode Parameters

**Calendar View (default):**
```
[hall_booking_calendar]
```

**Agenda View (paginated list):**
```
[hall_booking_calendar view="agenda"]
```

**Display bookings for a specific group:**
```
[hall_booking_calendar view="calendar" group="3"]
[hall_booking_calendar view="agenda" group="3"]
```
Replace `3` with the group ID. When filtering by a specific group, only bookings for that group will be shown.

#### Agenda View Features

The agenda view displays upcoming bookings in a list format with:
- **Pagination**: 10 bookings per page with page navigation
- **Group Filter**: Dropdown to filter bookings by group
- **Date Headers**: Bookings organized by date (e.g., "Monday, January 6, 2026")
- **Time Display**: Clear start and end times for each booking
- **Booking Details**: Room name, purpose, booked by, group, and status
- **View Details Links**: Click to see full booking information on dedicated page
- **Upcoming Only**: Shows only future bookings (automatically excludes past dates)

#### Single Booking Pages

Each booking has a dedicated detail page accessible by clicking:
- Booking indicators on the calendar
- "View Details" button in the agenda view
- "View" link in date listings

The single booking page displays:
- Complete booking information (date, time, room, purpose, description)
- User contact details
- Attached PDF file (if uploaded) with download link
- Recurring series information (if part of a series)
- Links to other bookings in the series
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
   - **Booking Password**: Set an optional password to protect booking submissions
   - **Require Password**: Toggle to enable/disable password requirement
   - **Webmaster Email**: Set email address for admin notifications (defaults to WordPress admin email)
3. Click "Save Settings"

When password protection is enabled, users must enter the correct password to submit bookings.

### Managing Bookings

1. Go to **Hall Booking → Bookings**
2. View all bookings with filtering options
3. Click "View" to see booking details
4. Update booking status (Pending/Confirmed/Cancelled)
5. Delete bookings if needed

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

```bash
npm run lint
```

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
https://github.com/slashzero/hall-calendar

## License

GPL-2.0+

## Changelog

### 1.3.1 (Development)
- **Bulk Import**: CSV file upload for batch booking creation
  - Import multiple bookings at once via CSV file
  - Support for both single and recurring bookings in bulk
  - Comprehensive validation with detailed error reporting
  - Conflict detection before import
  - Auto-confirmation of imported bookings
  - Example CSV template included
  - Room and Group ID reference tables in admin
- **Code Documentation**: Comprehensive PHPDoc blocks throughout plugin
  - Added @since, @param, @return tags to all functions
  - Documented complex logic with inline comments
  - Improved code readability for maintainability
- **Code Linting**: Added comprehensive code linting setup
  - PHP_CodeSniffer (PHPCS) with WordPress Coding Standards
  - ESLint with WordPress recommended configuration for JavaScript
  - stylelint with WordPress standards for CSS
  - EditorConfig for consistent coding styles across editors
- Added `package.json` for npm dependencies
- Added `composer.json` for PHP development dependencies
- Created configuration files: `.eslintrc.json`, `.stylelintrc.json`, `phpcs.xml`, `.editorconfig`
- Added `.gitignore` to exclude dependencies and build files
- Updated documentation with development setup and linting instructions

### 1.3.0
- **Agenda View**: Paginated list view of upcoming bookings with group filtering (10 per page)
- **Single Booking Pages**: Dedicated detail page for each booking with full information
- **Password Protection**: Optional password requirement for booking submissions (configurable in Settings)
- **File Attachments**: Upload PDF files (up to 5MB) with bookings, stored in wp-content/uploads/hall-bookings
- **Description Field**: Add detailed descriptions to bookings
- **Settings Page**: Admin interface to configure booking password and webmaster email
- **Simple Booking Form**: Replaced multi-step wizard with clean single-page form
- **Full-Page Booking**: Booking form now opens on dedicated page instead of modal
- **Enhanced Email Notifications**: Emails sent to both user and webmaster with complete booking details
- **Combined Series Emails**: Recurring/multi-date bookings combined in single email
- **Improved Feedback**: Enhanced success/error messages with animations and loading states
- **Database Migration**: Automatic schema updates for existing installations
- **Clickable Calendar**: Booking indicators link directly to booking detail pages
- **Pantheon.io Compatibility**: File uploads use WordPress upload directory structure
- File download links in admin bookings list and single booking pages
- Better mobile responsiveness for all new features

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
