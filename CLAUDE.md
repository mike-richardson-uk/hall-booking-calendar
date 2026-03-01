# Hall Booking Calendar — Claude Code Guide

## Project overview

WordPress plugin (GPL-2.0+) that provides a room-booking calendar with recurring bookings, iCal subscriptions, and an admin management interface.

- **Plugin entry point:** `hall-booking-calendar.php`
- **All feature code lives in:** `includes/`
- **Frontend assets:** `assets/css/` and `assets/js/`
- **PHP ≥ 7.0, WordPress ≥ 5.0**
- **Global prefix:** `hbc_` (functions) / `HBC_` (constants/classes)

## Linting

Run all linters:

```bash
npm run lint
```

Individual linters:

```bash
npm run lint:js       # ESLint on assets/js/**
npm run lint:css      # Stylelint on assets/css/**
npm run lint:php      # PHPCS with WordPress Coding Standards (via composer)
```

Auto-fix where possible:

```bash
npm run lint:fix      # JS + CSS
composer run-script lint:fix   # PHP (phpcbf)
```

> There are no automated tests (`npm test` is a no-op).

## Key files

| File | Purpose |
|---|---|
| `hall-booking-calendar.php` | Plugin header, DB activation, rewrite rules, query-var handlers, `template_include` filter |
| `includes/frontend-calendar.php` | Shortcode handlers and all frontend view functions |
| `includes/booking-handler.php` | AJAX booking submission, validation, email notifications |
| `includes/admin-settings.php` | Settings page UI and save handler |
| `includes/admin-menu.php` | Admin menu registration |
| `includes/recurring-bookings.php` | Recurring date generation |
| `includes/calendar-subscription.php` | iCal feed generation |
| `includes/elementor-widget.php` | Elementor widget wrapping the shortcode |

## Shortcodes

- `[hall_booking_calendar]` — calendar/agenda/compact views
- `[hall_booking_form]` — standalone booking form

## Custom URL routes

| URL pattern | Query var(s) set |
|---|---|
| `/events/{group}/{YYYY-MM-DD}/{purpose}/` | `hbc_event_group`, `hbc_event_date`, `hbc_event_purpose` |
| `/calendar/{group-slug}/` | `hbc_group_agenda` |

Both routes resolve to the page containing `[hall_booking_calendar]` (found via `hbc_find_calendar_page_id()`). Flush rewrite rules after changing these patterns.

## WordPress options used

| Option key | Description |
|---|---|
| `hbc_hall_name` | Venue name used in email subjects/bodies (optional) |
| `hbc_booking_password` | wp_hash_password()-hashed booking password |
| `hbc_webmaster_email` | Notification recipient |
| `hbc_require_password` | `'1'` / `'0'` |
| `hbc_agenda_limit` | Bookings per page in agenda view (default 10) |
| `hbc_terms_conditions` | HTML terms shown on booking form |
| `hbc_booking_page_id` | ID of the auto-created booking-form page |
| `hbc_event_page_template` | Theme template file for `/events/…` pages |
| `hbc_group_agenda_template` | Theme template file for `/calendar/group/` pages |
| `hbc_book_in_template` | Theme template file for `/book/{token}/` pages |

## Database tables

All tables are created on plugin activation via `dbDelta()`. Upgrade additions run through `hbc_check_database_upgrade()` on `init`.

- `{prefix}hbc_rooms`
- `{prefix}hbc_groups`
- `{prefix}hbc_categories`
- `{prefix}hbc_bookings`
- `{prefix}hbc_booking_rooms` (multi-room junction, added v1.6.0)
- `{prefix}hbc_subscriptions`
- `{prefix}hbc_booking_forms` (member booking-in form config per booking, added v1.14.0)
- `{prefix}hbc_form_submissions` (member booking-in form submissions, added v1.14.0)

## Coding conventions

- Follow WordPress Coding Standards (enforced by `phpcs.xml`)
- Short array syntax `[]` is allowed
- All globals must be prefixed `hbc_` / `HBC_`
- Nonce verification and `current_user_can()` checks are required on every admin form action
- Sanitise inputs on save (`sanitize_text_field`, `sanitize_email`, `wp_kses_post`, etc.)
- Escape all output (`esc_html`, `esc_attr`, `esc_url`)
