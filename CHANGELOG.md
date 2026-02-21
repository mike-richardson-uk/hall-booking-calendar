# Changelog

All notable changes to the Hall Booking Calendar plugin will be documented in this file.

## 1.9.1
- **Readable event URL dates**: Changed date format in event URLs from `YYYYMMDD` to `YYYY-MM-DD` for readability (e.g., `/events/2026-03-15/scouts/weekly-meeting/`)

## 1.9.0
- **Pretty event URLs**: Individual bookings now use SEO-friendly URLs under `/events/YYYY-MM-DD/group/purpose/` (e.g., `/events/2026-03-15/scouts/weekly-meeting/`) instead of query parameters
- **WordPress rewrite rules**: Registered custom rewrite rules and query vars for the `/events/` URL prefix
- **Slug resolver**: Booking slugs are automatically generated from date, group name, and purpose; resolved back to booking IDs for display

## 1.8.1
- **Tabbed settings page**: Usage instructions section now uses tabs (Shortcodes, Views, Bookings, Administration) for easier navigation
- **Changelog file**: Extracted changelog into a dedicated CHANGELOG.md

## 1.8.0
- **Streamlined agenda view**: Purpose is now the primary heading in the agenda listing, with room name shown below. Removed "Booked by", group, and status fields for a cleaner display
- **Streamlined booking detail page**: Removed status badge and "Details" heading for a cleaner layout
- **Group filter on Bookings admin page**: Filter bookings by group alongside the existing status filter
- **Linked group names**: Group names on the Groups admin page now link directly to their bookings
- **Calendar View documentation**: Added Calendar View usage instructions to both the admin Settings page and the README
- Updated README and Settings page to reflect current agenda and booking detail display

## 1.7.5
- **Compact Agenda View**: New `view="compact"` shortcode option displaying an abbreviated single-line-per-event list
- Optional `items` parameter to control the number of events shown (e.g., `[hall_booking_calendar view="compact" items="5"]`)
- Supports `group` filtering like other views
- Status-colored left border indicators (green=confirmed, amber=pending)
- Responsive layout for mobile devices
- **Editable Bookings**: All booking fields (purpose, description, user, date, time) are now editable from the admin booking detail screen

## 1.7.1
- **Dashboard Bulk Approve**: Select and approve multiple pending bookings directly from the dashboard page with checkboxes, select-all, and "Approve Selected" button

## 1.7.0
- **Bulk Approve**: Approve multiple pending bookings at once from the admin bookings screen with select-all functionality
- **Acceptance Email**: Automatic confirmation email sent to bookers when their booking is approved (individually or via bulk approve)
- **Configurable Agenda Limit**: New setting to control the number of bookings per page in the agenda view (default: 10)
- Updated Settings page with Agenda View Limit option
- Updated approval workflow documentation in admin settings

## 1.3.1
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

## 1.3.0
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

## 1.2.0
- **Recurring Bookings**: Create repeating bookings with multiple patterns (daily, weekly, biweekly, monthly, monthly weekday)
- **Multi-Date Bookings**: Book multiple specific dates in a single reservation
- **Calendar Subscriptions**: iCal/ICS feed generation for syncing with external calendars
- Added subscription management interface with token-based access
- Support for filtering subscriptions by group or room
- Enhanced database schema with recurring patterns and series tracking
- Conflict detection across all dates in recurring/multi-date bookings
- Frontend UI for recurring and multi-date booking options
- Booking series management (future ability to edit/delete series)

## 1.1.0
- Added group management system for organizing bookings
- Added Groups admin interface for creating and managing groups
- Added group selection to booking form (optional field)
- Added group filtering to calendar display via shortcode parameter
- Added group filter dropdown on frontend calendar
- Display group information in admin bookings list and details
- Updated dashboard to show active groups count
- Default groups created on activation

## 1.0.0
- Initial release
- Multi-room calendar system
- Booking management
- Admin interface
- Email notifications
