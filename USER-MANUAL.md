# Hall Booking System — User Manual

---

## Contents

- [For Meeting Bookers](#for-meeting-bookers)
- [For Administrators](#for-administrators)

---

# For Meeting Bookers

This section is for anyone who books rooms in the hall — for example, a lodge secretary arranging a meeting.

---

## Making a Booking

1. Visit the calendar page on the hall website.
2. Find the date you want and click the **Book** button on that day.
3. Fill in the booking form:
   - **Your name and email address** — so the hall can contact you.
   - **Room(s)** — tick the room or rooms you need.
   - **Date, start time, and end time** — the times your booking covers.
   - **Purpose** — a short description of the event (e.g. *Lodge meeting*, *Committee meeting*).
   - **Group** — select your lodge or organisation if shown.
   - **Description** — any additional notes (optional).
   - **Recurring booking** — if your meeting happens regularly, tick *Repeat this booking* and choose a pattern (weekly, fortnightly, monthly, etc.) and an end date.
   - **Password** — if the hall requires one, you will be asked to enter it.
   - **Terms and conditions** — tick to confirm you agree, if shown.
4. Click **Submit Booking**.

If there is a conflict with an existing booking, a warning will appear before you submit — choose a different time or room.

Your booking is submitted as **Pending** and must be approved by the hall administrator before it is confirmed. You will receive a second email once it is approved.

---

## Your Confirmation Email

After submitting, you will receive an email confirming your booking details. It also contains two important personal links:

| Link | What it does |
|---|---|
| **Update your booking** | Opens a page where you can change the event details yourself — no login needed. |
| **Cancel this booking** | Opens a page where you can cancel the booking — no login needed. |

**Keep this email.** These links are unique to your booking and are the easiest way to make changes.

---

## Updating Your Booking

If you need to change the event name, date, time, or your contact details:

1. Open the confirmation email and click **Update your booking**.
2. The page shows your current booking details in a simple form.
3. Make your changes and click **Save Changes**.

The hall administrator will automatically receive an email showing what changed.

**Notes:**
- The room cannot be changed through this form. Contact the hall directly if you need a different room.
- If you change the date or time, the system will check for conflicts and warn you if the room is already taken.
- The rooms and current status are shown on the page for reference but cannot be changed here.
- At the bottom of the form there is also a link to **Cancel this booking instead** if you no longer need it.

---

## Cancelling Your Booking

1. Open the confirmation email and click **Cancel this booking**.
2. The page shows your booking details so you can confirm you have the right one.
3. Click **Yes, Cancel My Booking**.

The hall administrator is notified automatically. If you change your mind, click **No, Keep My Booking**.

---

## Member Booking-In Form

For some events (such as lodge dinners), the organiser may set up a **booking-in form** so that individual members can register their attendance and meal choice.

If this has been set up for your event:

- A link to the booking-in form will be included in your confirmation email.
- A QR code will appear on the event's page on the website — members can scan this with their phone to go directly to the form.
- Share the link or QR code with your members so they can book in.

Members fill in their name, contact details, masonic information, attendance type, and (if a dinner menu is set up) their meal choice.

---

# For Administrators

This section is for the hall administrator who manages bookings through the WordPress admin area.

---

## The Dashboard

Go to **Hall Booking → Dashboard** for an at-a-glance overview:

- **Stat boxes** — bookings today, this week, this month, and totals by status (pending, confirmed, all time).
- **Quick actions** — one-click buttons for the most common tasks. If there are pending bookings awaiting approval, the Pending Approval button shows a count.
- **Monthly chart** — booking volume over the past six months.
- **Top rooms** — the five most-booked rooms.
- **Status breakdown** — confirmed, pending, and cancelled totals.

---

## Rooms

Go to **Hall Booking → Rooms** to add, edit, or deactivate rooms.

Each room has a **name**, **description**, and **capacity**. Capacity is shown on the calendar's room legend and on the booking form.

---

## Groups

Go to **Hall Booking → Groups** to manage groups (lodges, clubs, committees, etc.).

Bookers select a group when making a booking. You can filter the calendar and bookings list by group.

---

## Managing Bookings

### Viewing the Bookings List

Go to **Hall Booking → Bookings** to see all bookings. Use the dropdowns at the top to filter by **status** (Pending / Confirmed / Cancelled) or **group**.

Each row shows the purpose, room, user, date, time, any attached file, and status. Click **View** to open the full booking detail.

### Approving a Booking

When a new booking is submitted it arrives as **Pending**.

**To approve one booking:**
1. Click **View** on the booking.
2. Change the **Status** dropdown to *Confirmed*.
3. Click **Update Booking**.

The booker automatically receives a confirmation email.

**To approve several bookings at once:**
1. On the bookings list, tick the checkboxes next to the pending bookings you want to approve (or use *Select All Pending*).
2. Click **Approve Selected**.

### Editing a Booking

Open any booking and edit the fields directly — purpose, description, room(s), group, category, date, time, contact details, status, and attached file. Click **Update Booking** to save.

### Attaching or Replacing a File

On the booking detail page, scroll to **Attached File**:

- If no file is attached, use the file input to upload a PDF (max 5 MB).
- If a file is already attached, you can download it, tick **Remove this file** to delete it, or upload a new PDF to replace it.

### Self-Service Links

Below the main booking fields, the **Self-Service Links** section shows the personal edit and cancel links that were sent to the booker in their confirmation email.

Use this section if a booker has lost their email and needs the links re-sent — copy the appropriate link and send it to them directly.

### Deleting a Booking

On the bookings list, click **Delete** next to the booking. You will be asked to confirm. This permanently removes the booking record.

---

## Member Booking-In Forms

A booking-in form lets individual members register their attendance for a specific event.

### Setting Up a Booking-In Form

1. Open the booking detail page.
2. Scroll to **Member Booking-In Form** and tick *Enable member booking-in form for this event*.
3. Enter at least one email address to receive submission notifications.
4. Optionally tick **Include meal menu selection** and use **+ Add Meal Option** to add courses or choices (name, description, price).
5. Optionally fill in payment details (bank transfer information, payment deadline, etc.). Leave any field blank to omit it from the form.
6. Click **Update Booking**.

Once enabled, a **Short URL** and **QR code** appear on the page. Share these with the organiser so they can distribute them to members.

For a recurring series, configure the form on the parent booking — it applies automatically to all dates in the series.

### Viewing Attendee Submissions

On the booking detail page, scroll past the main form to the **Booking-In Submissions** section. This appears once at least one member has submitted the form. It shows a summary table of all responses.

### Downloading the Attendee List

In the Booking-In Submissions section, click **Download Attendee List (CSV)**. This downloads a spreadsheet-compatible file including name, email, attendance type, membership type, lodge name, meal choice, vegetarian preference, dietary requirements, and submission time.

---

## Calendar Subscriptions (iCal)

Go to **Hall Booking → Subscriptions** to manage iCal feeds.

Subscriptions can be filtered by group or room. The feed URL can be added to Google Calendar, Apple Calendar, Outlook, or any iCal-compatible application to keep an external calendar up to date automatically.

---

## Bulk CSV Import

Go to **Hall Booking → Bulk Import** to create multiple bookings at once from a CSV file.

Required columns: `room_id`, `user_name`, `user_email`, `booking_date`, `start_time`, `end_time`, `purpose`

Optional columns: `description`, `group_id`, `is_recurring`, `recurrence_pattern`, `recurrence_end_date`

Imported bookings are confirmed automatically (no approval step). The page shows a reference table of room and group IDs.

---

## Export

Go to **Hall Booking → Export** to download all bookings as a CSV, filtered by date range and status. The export includes group and category names, user details, and all booking fields.

---

## Settings

Go to **Hall Booking → Settings** to configure the following:

| Setting | Description |
|---|---|
| **Hall Name** | Your venue's name. When set it replaces the generic "Hall Booking" label in all outbound email subjects and bodies, e.g. *[Ely Masonic Hall Booking Confirmation]*. |
| **Booking Notifications Email** | The email address that receives booking notifications and cancellation alerts. Defaults to the WordPress admin email. |
| **Require password** | Tick to require bookers to enter a password when submitting a booking. Set the password in the field below. |
| **Booking password** | The password bookers must enter (only used when the above is ticked). Leave blank to keep the current password. |
| **Agenda view limit** | How many bookings to show per page in the agenda view (default: 10). |
| **Terms and conditions** | HTML text shown above a required checkbox on the booking form. Leave blank to disable. |
| **Event Page Template** | The theme template used for individual event pages (`/events/group/date/purpose/`). |
| **Group Agenda Template** | The theme template used for group agenda pages (`/calendar/group-name/`). |
| **Book-In Page Template** | The theme template used for member book-in form pages (`/book/{token}/`). |
| **Booking Page** | Shows the auto-created page that contains the booking form. |

The **Usage Instructions** section at the bottom of the Settings page contains shortcode reference documentation and details on all plugin features, organised into tabs.

---

## The Calendar on the Website

The calendar is added to a page using the shortcode `[hall_booking_calendar]`. It supports several views:

| View | Shortcode |
|---|---|
| Monthly calendar (default) | `[hall_booking_calendar]` |
| Agenda (upcoming list) | `[hall_booking_calendar view="agenda"]` |
| Compact list | `[hall_booking_calendar view="compact" items="5"]` |

Visitors can filter by group or room using the dropdowns on the page. On mobile phones, the monthly calendar collapses booking pills to small coloured dots to keep the grid usable.

The booking form can also be placed on any page with `[hall_booking_form]`.
