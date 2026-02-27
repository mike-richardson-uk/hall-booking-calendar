# Changelog

All notable changes to the Hall Booking Calendar plugin will be documented in this file.

## 1.19.0
- **Self-service booking edit link**: The confirmation email sent to the booker now includes a personal edit link (`/edit-booking/{token}/`). Clicking it opens a simple, login-free form where the booker can update the event name, description, date, time, and their contact details. A conflict check prevents double-bookings when the date or time is changed. The venue administrator is notified by email of any changes
- **Admin self-service links panel**: The booking detail page in Hall Booking → Bookings now shows a "Self-Service Links" section displaying the edit link and cancel link for the booking, so admins can copy and re-share them if needed
- **DB upgrade**: `edit_token varchar(64) UNIQUE` column added to `hbc_bookings` automatically on plugin init

## 1.18.0
- **Admin dashboard analytics**: Monthly booking bar chart (last 6 months), room usage horizontal bars (top 5), status breakdown (confirmed/pending/cancelled), quick-action cards with dashicons and a pending-count alert badge
- **Booking cancellation by booker**: A signed cancellation URL is included in booking confirmation emails. Visiting the URL presents a theme-integrated confirmation page; submitting it cancels the booking and notifies the webmaster. Cancellation tokens are stored in a new `cancellation_token` column on `hbc_bookings` (DB upgrade applied automatically)
- **Inline conflict warning**: A debounced AJAX check fires when the booker changes the room, date, or times on the booking form. A warning message is shown immediately if a conflict is detected — before submission
- **QR codes**: A QR code for the member book-in URL is shown on the single event detail page and on the admin booking detail page, generated via api.qrserver.com
- **Book-in CSV export**: A "Download Attendee CSV" button on the admin booking detail page exports all form submissions for that event as a UTF-8 CSV (Excel-compatible BOM), including name, email, attendance, lodge, meal choice, dietary requirements, and more
- **Recurring series indicator**: Calendar booking pills and single booking headers now display a ↻ icon when the booking belongs to a recurring series
- **Mobile calendar**: At viewports ≤ 480 px, booking pills collapse to small coloured dots to keep the calendar usable on phones
- **Status badges**: Single booking header displays a colour-coded pill badge (confirmed = green, pending = amber, cancelled = red)
- **Event/group template dropdowns**: Admin settings now also lists all PHP files in the active theme root, not just files with a `Template Name:` header, so any custom theme file can be selected as the event or group-agenda template

## 1.17.0
- **Lodge Name conditional field**: The Lodge Name field on the member booking-in form is now hidden by default and only shown (and required) when the booker selects "Guest" as their attendance type
- **Larger meal description field**: The meal description input in both the admin meal builder and the frontend booking form has been changed from a single-line text input to a four-row textarea for easier entry of longer descriptions
- **Vegetarian alternative checkbox**: A "I would like a vegetarian alternative" checkbox is added to the meal selection section of the booking-in form. The preference is stored in a new `vegetarian_alternative` column on `hbc_form_submissions` (DB upgrade applied automatically) and included in submission notification emails

## 1.16.0
- **Admin booking-in form editing**: The booking detail/edit page in Hall Booking → Bookings now includes a full "Member Booking-In Form" section. Admins can enable the form, manage notification email recipients, configure the meal menu, set payment information, and view the generated short URL — all from the same page without needing to go back through the original booking form

## 1.15.0
- **Short booking-in URL**: When a booking-in form is enabled, a unique short URL (e.g. `/book/X6fGh/`) is automatically generated and stored. The short URL is included in the confirmation email sent to the original hall booking submitter so they can share it directly with members
- **Token routing**: The `/book/{token}/` URL resolves to the member-facing booking-in form for the associated event, with canonical redirect suppression so WordPress does not redirect the URL away

## 1.14.0
- **Member booking-in form**: When creating a hall booking, organisers can now enable a member booking-in form for that event via a new "Member Booking-In Form" section on the booking form. Options include one or more submission email addresses, an optional meal menu (name, description, price per option), and payment information (bank transfer details, cheque payable to, payment deadline)
- **Member-facing form**: A "Book In for This Event" button appears on the event detail page for any booking with the form enabled. Members complete a form collecting personal details, masonic information (rank, attendance type, membership, lodge name), meal selection (shown only when attending with dinner), and additional comments. Submissions are emailed to the configured recipients
- **Recurring series support**: Booking-in form config is stored on the parent booking and automatically applies to all events in the same recurring series

## 1.13.0
- **Per-page theme templates**: Admins can now choose a WordPress theme template for each type of plugin-generated URL — individual event pages (`/events/group/date/purpose/`) and group agenda pages (`/calendar/group-name/`) — via two new dropdowns in Hall Booking → Settings. Leaving either dropdown on Default continues to use the calendar page's own template

## 1.12.1
- **Calendar UX tweaks**: Ensured all day cells in the detailed calendar grid are equal-width and scroll correctly when many bookings are present
- **Booking availability button**: The calendar “Book” button is now disabled and replaced with a “Fully booked” label on days where every visible room is already booked
- **Group description display**: Fixed double-escaped apostrophes and other characters in group names/descriptions so text like “group's meeting” renders correctly in the Groups admin screen

## 1.12.0
- **Detailed calendar layout**: New Google Calendar–style day cells that show one pill per booking with time, purpose, and room list for quick visual scanning
- **Layout toggle option**: Added `layout` shortcode attribute for the calendar view (`layout="detailed"` for the new pills view, `layout="simple"` for the original room-availability indicators). Layout can also be switched per-request via `?layout=simple` or `?layout=detailed`
- **Shortcode documentation updates**: Updated the Settings → Usage Instructions panel to document the new `layout` attribute alongside existing `view`, `group`, `room`, and `items` options

## 1.11.0
- **New event URL format**: Changed pretty event URLs from `/events/YYYY-MM-DD/group/purpose/` to `/events/group/YYYY-MM-DD/purpose/` (e.g., `/events/scouts/2026-03-15/weekly-meeting/`), putting the group first for better readability
- **Group agenda pages**: New `/calendar/group-slug/` URLs show an agenda view of upcoming events for a specific group (e.g., `/calendar/scouts/`). Booking detail pages link to the group agenda via "View all upcoming bookings"
- **Dashboard booking details**: Dashboard recent bookings table now shows Purpose/Group column and a View button linking to the full booking detail/edit page, so admins can review pending bookings before approving
- **Fix pretty event URLs**: Prevent WordPress canonical redirect from redirecting custom URLs to the home page
- **Fallback for missing calendar page**: Event URL handler now properly returns a 404 if no calendar page is found

## 1.10.0
- **Event categories**: New `hbc_categories` table with admin CRUD interface (Hall Booking > Categories). Categories like Rehearsal, Social Event, Meeting, etc. are required when booking
- **Category on booking form**: Event Category is a required dropdown field; validated on both client and server side
- **Category in admin**: Category column added to bookings list, editable in booking detail view, included in email notifications and single booking detail pages
- **CSV export**: New admin page (Hall Booking > Export) to download bookings as CSV filtered by date range and status. Includes group and category names, user details, and booking info
- **Terms and conditions**: Admin-configurable WYSIWYG editor in Settings displayed above a required checkbox on the booking form. Leave blank to disable
- **Auto-flush rewrite rules**: Rewrite rules are now automatically flushed when the plugin version changes

## 1.9.1
- **Readable event URL dates**: Changed date format in event URLs from `YYYYMMDD` to `YYYY-MM-DD` for readability (e.g., `/events/scouts/2026-03-15/weekly-meeting/`)

## 1.9.0
- **Pretty event URLs**: Individual bookings now use SEO-friendly URLs under `/events/group/YYYY-MM-DD/purpose/` (e.g., `/events/scouts/2026-03-15/weekly-meeting/`) instead of query parameters
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
