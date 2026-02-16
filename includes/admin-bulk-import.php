<?php
/**
 * Admin Bulk Import - CSV Import for Bookings
 *
 * Allows administrators to import multiple bookings at once via CSV file upload.
 * Supports both single and recurring bookings with comprehensive validation.
 *
 * CSV Format:
 * - Header row required (see example-bookings.csv)
 * - UTF-8 encoding recommended
 * - Date format: YYYY-MM-DD
 * - Time format: HH:MM (24-hour)
 *
 * @package Hall_Booking_Calendar
 * @since 1.3.1
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Handle bulk import form submission
 *
 * Processes uploaded CSV file and imports bookings in batch.
 * Validates each row and provides detailed success/error reporting.
 *
 * @since 1.3.1
 * @return void
 */
function hbc_handle_bulk_import() {
	// Check if form was submitted
	if (!isset($_POST['hbc_bulk_import']) || !isset($_POST['hbc_bulk_import_nonce'])) {
		return;
	}

	// Verify nonce for security
	if (!wp_verify_nonce($_POST['hbc_bulk_import_nonce'], 'hbc_bulk_import')) {
		wp_die(__('Security check failed', 'hall-booking-calendar'));
	}

	// Check user permissions
	if (!current_user_can('manage_options')) {
		wp_die(__('Unauthorized access', 'hall-booking-calendar'));
	}

	// Implement rate limiting: max 5 imports per 10 minutes per user
	$user_id = get_current_user_id();
	$rate_limit_key = 'hbc_csv_import_limit_' . $user_id;
	$import_count = get_transient($rate_limit_key);

	if ($import_count === false) {
		// First import in this time window
		set_transient($rate_limit_key, 1, 10 * MINUTE_IN_SECONDS);
	} else {
		$import_count = intval($import_count);
		if ($import_count >= 5) {
			add_settings_error('hbc_bulk_import', 'hbc_rate_limit', __('Import rate limit exceeded. Please wait a few minutes before importing again.', 'hall-booking-calendar'), 'error');
			return;
		}
		set_transient($rate_limit_key, $import_count + 1, 10 * MINUTE_IN_SECONDS);
	}

	// Check if file was uploaded
	if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
		add_settings_error('hbc_bulk_import', 'hbc_file_error', __('Failed to upload file. Please try again.', 'hall-booking-calendar'), 'error');
		return;
	}

	// Process the CSV file
	$result = hbc_process_csv_import($_FILES['csv_file']);

	// Display results
	if ($result['success']) {
		$message = sprintf(
			__('Import completed: %d bookings imported successfully, %d failed.', 'hall-booking-calendar'),
			$result['imported'],
			$result['failed']
		);
		add_settings_error('hbc_bulk_import', 'hbc_import_success', $message, 'updated');
	} else {
		add_settings_error('hbc_bulk_import', 'hbc_import_error', $result['message'], 'error');
	}

	// Store detailed results in transient for display
	if (!empty($result['errors'])) {
		set_transient('hbc_import_errors', $result['errors'], 300); // 5 minutes
	}
}
add_action('admin_init', 'hbc_handle_bulk_import');

/**
 * Process CSV file and import bookings
 *
 * Parses CSV file, validates each row, and creates bookings in database.
 * Checks for conflicts and validates all required fields.
 *
 * @since 1.3.1
 * @param array $file File array from $_FILES
 * @return array Results array with success status, counts, and error details {
 *     @type bool   $success  Whether import completed (even with some failures)
 *     @type int    $imported Count of successfully imported bookings
 *     @type int    $failed   Count of failed bookings
 *     @type string $message  Overall status message
 *     @type array  $errors   Array of error messages per row
 * }
 */
function hbc_process_csv_import($file) {
	global $wpdb;

	// Validate file type
	$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	if ($file_ext !== 'csv') {
		return array(
			'success' => false,
			'message' => __('Invalid file type. Please upload a CSV file.', 'hall-booking-calendar'),
			'imported' => 0,
			'failed' => 0,
			'errors' => array()
		);
	}

	// Open and read CSV file
	$handle = fopen($file['tmp_name'], 'r');
	if (!$handle) {
		return array(
			'success' => false,
			'message' => __('Failed to read CSV file.', 'hall-booking-calendar'),
			'imported' => 0,
			'failed' => 0,
			'errors' => array()
		);
	}

	$imported = 0;
	$failed = 0;
	$errors = array();
	$row_number = 0;

	// Read and validate header row
	$headers = fgetcsv($handle);
	if (!$headers || !hbc_validate_csv_headers($headers)) {
		fclose($handle);
		return array(
			'success' => false,
			'message' => __('Invalid CSV format. Please check the header row matches the required format.', 'hall-booking-calendar'),
			'imported' => 0,
			'failed' => 0,
			'errors' => array(__('Expected headers: room_id, user_name, user_email, booking_date, start_time, end_time, purpose, description, group_id, is_recurring, recurrence_pattern, recurrence_end_date', 'hall-booking-calendar'))
		);
	}

	// Process each data row
	while (($data = fgetcsv($handle)) !== false) {
		$row_number++;

		// Skip empty rows
		if (empty(array_filter($data))) {
			continue;
		}

		// Map CSV columns to associative array
		$booking_data = hbc_map_csv_row($headers, $data);

		// Validate and import booking
		$validation = hbc_validate_csv_booking($booking_data, $row_number);

		if ($validation['valid']) {
			// Attempt to create booking
			$import_result = hbc_import_single_booking($booking_data);

			if ($import_result['success']) {
				$imported++;
			} else {
				$failed++;
				$errors[] = sprintf(__('Row %d: %s', 'hall-booking-calendar'), $row_number, $import_result['message']);
			}
		} else {
			$failed++;
			$errors[] = sprintf(__('Row %d: %s', 'hall-booking-calendar'), $row_number, $validation['message']);
		}
	}

	fclose($handle);

	return array(
		'success' => true,
		'imported' => $imported,
		'failed' => $failed,
		'message' => sprintf(__('Import completed: %d succeeded, %d failed', 'hall-booking-calendar'), $imported, $failed),
		'errors' => $errors
	);
}

/**
 * Validate CSV header row
 *
 * Checks if CSV has all required column headers.
 *
 * Required headers (order doesn't matter):
 * - room_id
 * - user_name
 * - user_email
 * - booking_date
 * - start_time
 * - end_time
 * - purpose
 *
 * Optional headers:
 * - description
 * - group_id
 * - is_recurring (1 or 0)
 * - recurrence_pattern (daily, weekly, biweekly, monthly, monthly_weekday)
 * - recurrence_end_date
 *
 * @since 1.3.1
 * @param array $headers Array of header column names
 * @return bool True if headers are valid, false otherwise
 */
function hbc_validate_csv_headers($headers) {
	$required = array('room_id', 'user_name', 'user_email', 'booking_date', 'start_time', 'end_time', 'purpose');

	// Normalize headers (trim whitespace, lowercase)
	$headers = array_map('trim', $headers);
	$headers = array_map('strtolower', $headers);

	// Check all required headers are present
	foreach ($required as $field) {
		if (!in_array($field, $headers)) {
			return false;
		}
	}

	return true;
}

/**
 * Map CSV row to associative array
 *
 * Converts indexed array from CSV to associative array using headers.
 *
 * @since 1.3.1
 * @param array $headers Array of column headers
 * @param array $data    Array of row data values
 * @return array Associative array of column_name => value
 */
function hbc_map_csv_row($headers, $data) {
	// Normalize headers
	$headers = array_map('trim', $headers);
	$headers = array_map('strtolower', $headers);

	// Combine headers with data
	$mapped = array();
	foreach ($headers as $index => $header) {
		$mapped[$header] = isset($data[$index]) ? trim($data[$index]) : '';
	}

	return $mapped;
}

/**
 * Validate CSV booking data
 *
 * Validates a single booking row from CSV before import.
 *
 * Checks:
 * - Required fields are not empty
 * - Email format is valid
 * - Room exists and is active
 * - Group exists if specified
 * - Date format is valid (YYYY-MM-DD)
 * - Date is not in the past
 * - Time format is valid (HH:MM)
 * - End time is after start time
 * - Recurrence fields are valid if recurring
 *
 * @since 1.3.1
 * @param array $data       Associative array of booking data
 * @param int   $row_number Row number for error messages
 * @return array Validation result {
 *     @type bool   $valid   Whether the data is valid
 *     @type string $message Error message if invalid
 * }
 */
function hbc_validate_csv_booking($data, $row_number) {
	global $wpdb;
	$rooms_table = $wpdb->prefix . 'hbc_rooms';
	$groups_table = $wpdb->prefix . 'hbc_groups';

	// Required fields validation
	$required = array('room_id', 'user_name', 'user_email', 'booking_date', 'start_time', 'end_time', 'purpose');
	foreach ($required as $field) {
		if (empty($data[$field])) {
			return array(
				'valid' => false,
				'message' => sprintf(__('Missing required field: %s', 'hall-booking-calendar'), $field)
			);
		}
	}

	// Email validation
	if (!is_email($data['user_email'])) {
		return array(
			'valid' => false,
			'message' => __('Invalid email address', 'hall-booking-calendar')
		);
	}

	// Room validation
	$room = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $rooms_table WHERE id = %d AND status = 'active'",
		intval($data['room_id'])
	));
	if (!$room) {
		return array(
			'valid' => false,
			'message' => sprintf(__('Invalid or inactive room ID: %s', 'hall-booking-calendar'), $data['room_id'])
		);
	}

	// Group validation (if specified)
	if (!empty($data['group_id'])) {
		$group = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $groups_table WHERE id = %d AND status = 'active'",
			intval($data['group_id'])
		));
		if (!$group) {
			return array(
				'valid' => false,
				'message' => sprintf(__('Invalid or inactive group ID: %s', 'hall-booking-calendar'), $data['group_id'])
			);
		}
	}

	// Date validation
	$date_obj = DateTime::createFromFormat('Y-m-d', $data['booking_date']);
	if (!$date_obj || $date_obj->format('Y-m-d') !== $data['booking_date']) {
		return array(
			'valid' => false,
			'message' => sprintf(__('Invalid date format: %s (expected YYYY-MM-DD)', 'hall-booking-calendar'), $data['booking_date'])
		);
	}

	// Date not in past
	if (strtotime($data['booking_date']) < strtotime(date('Y-m-d'))) {
		return array(
			'valid' => false,
			'message' => __('Cannot import bookings with dates in the past', 'hall-booking-calendar')
		);
	}

	// Time validation
	if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['start_time'])) {
		return array(
			'valid' => false,
			'message' => sprintf(__('Invalid start time format: %s (expected HH:MM)', 'hall-booking-calendar'), $data['start_time'])
		);
	}

	if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['end_time'])) {
		return array(
			'valid' => false,
			'message' => sprintf(__('Invalid end time format: %s (expected HH:MM)', 'hall-booking-calendar'), $data['end_time'])
		);
	}

	// Validate end time is after start time
	if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
		return array(
			'valid' => false,
			'message' => __('End time must be after start time', 'hall-booking-calendar')
		);
	}

	// Recurring booking validation
	if (!empty($data['is_recurring']) && $data['is_recurring'] == '1') {
		$valid_patterns = array('daily', 'weekly', 'biweekly', 'monthly', 'monthly_weekday');
		if (empty($data['recurrence_pattern']) || !in_array($data['recurrence_pattern'], $valid_patterns)) {
			return array(
				'valid' => false,
				'message' => sprintf(__('Invalid recurrence pattern: %s', 'hall-booking-calendar'), $data['recurrence_pattern'])
			);
		}

		if (empty($data['recurrence_end_date'])) {
			return array(
				'valid' => false,
				'message' => __('Recurrence end date is required for recurring bookings', 'hall-booking-calendar')
			);
		}

		$end_date_obj = DateTime::createFromFormat('Y-m-d', $data['recurrence_end_date']);
		if (!$end_date_obj || $end_date_obj->format('Y-m-d') !== $data['recurrence_end_date']) {
			return array(
				'valid' => false,
				'message' => sprintf(__('Invalid recurrence end date format: %s', 'hall-booking-calendar'), $data['recurrence_end_date'])
			);
		}

		if (strtotime($data['recurrence_end_date']) <= strtotime($data['booking_date'])) {
			return array(
				'valid' => false,
				'message' => __('Recurrence end date must be after booking start date', 'hall-booking-calendar')
			);
		}
	}

	// All validations passed
	return array(
		'valid' => true,
		'message' => ''
	);
}

/**
 * Import a single booking from CSV data
 *
 * Creates a booking (single or recurring) from validated CSV row data.
 * Checks for conflicts before creating.
 *
 * @since 1.3.1
 * @param array $data Validated booking data from CSV
 * @return array Result array {
 *     @type bool   $success Whether booking was created successfully
 *     @type string $message Success or error message
 * }
 */
function hbc_import_single_booking($data) {
	global $wpdb;

	// Prepare booking data
	$booking_data = array(
		'room_id' => intval($data['room_id']),
		'group_id' => !empty($data['group_id']) ? intval($data['group_id']) : null,
		'user_id' => null, // Bulk imports don't associate with WP users
		'user_name' => sanitize_text_field($data['user_name']),
		'user_email' => sanitize_email($data['user_email']),
		'booking_date' => sanitize_text_field($data['booking_date']),
		'start_time' => sanitize_text_field($data['start_time']) . ':00', // Add seconds
		'end_time' => sanitize_text_field($data['end_time']) . ':00', // Add seconds
		'purpose' => sanitize_textarea_field($data['purpose']),
		'description' => !empty($data['description']) ? sanitize_textarea_field($data['description']) : '',
		'file_path' => '', // CSV import doesn't support file uploads
		'status' => 'confirmed' // Bulk imports are auto-confirmed
	);

	// Check if this is a recurring booking
	$is_recurring = !empty($data['is_recurring']) && $data['is_recurring'] == '1';

	if ($is_recurring) {
		// Create recurring bookings
		$pattern = sanitize_text_field($data['recurrence_pattern']);
		$end_date = sanitize_text_field($data['recurrence_end_date']);

		$result = hbc_create_recurring_bookings($booking_data, $pattern, $end_date, array());

		if ($result['success']) {
			return array(
				'success' => true,
				'message' => sprintf(__('%d recurring bookings created', 'hall-booking-calendar'), count($result['booking_ids']))
			);
		} else {
			return array(
				'success' => false,
				'message' => $result['message']
			);
		}
	} else {
		// Single booking - check for conflicts
		$conflict = hbc_check_booking_conflict(
			$booking_data['room_id'],
			$booking_data['booking_date'],
			$booking_data['start_time'],
			$booking_data['end_time']
		);

		if ($conflict) {
			return array(
				'success' => false,
				'message' => __('Booking conflict - room already booked for this time', 'hall-booking-calendar')
			);
		}

		// Insert booking
		$bookings_table = $wpdb->prefix . 'hbc_bookings';
		$booking_rooms_table = $wpdb->prefix . 'hbc_booking_rooms';
		$result = $wpdb->insert(
			$bookings_table,
			$booking_data,
			array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
		);

		if ($result) {
			$new_id = $wpdb->insert_id;
			// Insert into junction table for multi-room support
			$wpdb->insert($booking_rooms_table, array(
				'booking_id' => $new_id,
				'room_id' => $booking_data['room_id'],
			));
			return array(
				'success' => true,
				'message' => __('Booking created successfully', 'hall-booking-calendar')
			);
		} else {
			return array(
				'success' => false,
				'message' => __('Database error - failed to create booking', 'hall-booking-calendar')
			);
		}
	}
}

/**
 * Display bulk import admin page
 *
 * Shows upload form and instructions for CSV import.
 * Displays results and errors from previous import if available.
 *
 * @since 1.3.1
 * @return void Outputs HTML directly
 */
function hbc_admin_bulk_import_page() {
	// Get any import errors from transient
	$errors = get_transient('hbc_import_errors');
	if ($errors) {
		delete_transient('hbc_import_errors');
	}

	?>
	<div class="wrap">
		<h1><?php _e('Bulk Import Bookings', 'hall-booking-calendar'); ?></h1>

		<p><?php _e('Upload a CSV file to import multiple bookings at once. The CSV file must follow the required format.', 'hall-booking-calendar'); ?></p>

		<?php settings_errors('hbc_bulk_import'); ?>

		<?php if ($errors && !empty($errors)) : ?>
			<div class="notice notice-warning">
				<h3><?php _e('Import Errors:', 'hall-booking-calendar'); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<?php foreach ($errors as $error) : ?>
						<li><?php echo esc_html($error); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php _e('Upload CSV File', 'hall-booking-calendar'); ?></h2>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field('hbc_bulk_import', 'hbc_bulk_import_nonce'); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="csv_file"><?php _e('CSV File', 'hall-booking-calendar'); ?></label>
						</th>
						<td>
							<input type="file" id="csv_file" name="csv_file" accept=".csv" required>
							<p class="description">
								<?php _e('Select a CSV file to upload. Maximum file size: 2MB.', 'hall-booking-calendar'); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="hbc_bulk_import" class="button button-primary" value="<?php _e('Import Bookings', 'hall-booking-calendar'); ?>">
				</p>
			</form>
		</div>

		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php _e('CSV File Format', 'hall-booking-calendar'); ?></h2>

			<p><?php _e('Your CSV file must include the following columns (header row required):', 'hall-booking-calendar'); ?></p>

			<h3><?php _e('Required Columns:', 'hall-booking-calendar'); ?></h3>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><strong>room_id</strong> - <?php _e('The ID of the room (numeric)', 'hall-booking-calendar'); ?></li>
				<li><strong>user_name</strong> - <?php _e('Name of person making the booking', 'hall-booking-calendar'); ?></li>
				<li><strong>user_email</strong> - <?php _e('Email address (must be valid format)', 'hall-booking-calendar'); ?></li>
				<li><strong>booking_date</strong> - <?php _e('Date in YYYY-MM-DD format (e.g., 2026-01-15)', 'hall-booking-calendar'); ?></li>
				<li><strong>start_time</strong> - <?php _e('Start time in HH:MM format (24-hour, e.g., 09:00 or 14:30)', 'hall-booking-calendar'); ?></li>
				<li><strong>end_time</strong> - <?php _e('End time in HH:MM format (24-hour, e.g., 10:00 or 16:30)', 'hall-booking-calendar'); ?></li>
				<li><strong>purpose</strong> - <?php _e('Purpose of the booking', 'hall-booking-calendar'); ?></li>
			</ul>

			<h3><?php _e('Optional Columns:', 'hall-booking-calendar'); ?></h3>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><strong>description</strong> - <?php _e('Detailed description (optional)', 'hall-booking-calendar'); ?></li>
				<li><strong>group_id</strong> - <?php _e('Group ID if booking belongs to a group (numeric, optional)', 'hall-booking-calendar'); ?></li>
				<li><strong>is_recurring</strong> - <?php _e('1 for recurring booking, 0 or empty for single booking', 'hall-booking-calendar'); ?></li>
				<li><strong>recurrence_pattern</strong> - <?php _e('Required if is_recurring=1. Options: daily, weekly, biweekly, monthly, monthly_weekday', 'hall-booking-calendar'); ?></li>
				<li><strong>recurrence_end_date</strong> - <?php _e('Required if is_recurring=1. End date in YYYY-MM-DD format', 'hall-booking-calendar'); ?></li>
			</ul>

			<h3><?php _e('Important Notes:', 'hall-booking-calendar'); ?></h3>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php _e('Header row is required', 'hall-booking-calendar'); ?></li>
				<li><?php _e('UTF-8 encoding recommended', 'hall-booking-calendar'); ?></li>
				<li><?php _e('Dates cannot be in the past', 'hall-booking-calendar'); ?></li>
				<li><?php _e('All imported bookings are automatically set to "confirmed" status', 'hall-booking-calendar'); ?></li>
				<li><?php _e('Conflicts will be detected and conflicting bookings will be skipped', 'hall-booking-calendar'); ?></li>
				<li><?php _e('Room and group IDs must exist and be active', 'hall-booking-calendar'); ?></li>
			</ul>

			<h3><?php _e('Example CSV:', 'hall-booking-calendar'); ?></h3>
			<p>
				<a href="<?php echo HBC_PLUGIN_URL . 'example-bookings.csv'; ?>" class="button" download>
					<?php _e('Download Example CSV', 'hall-booking-calendar'); ?>
				</a>
			</p>
		</div>

		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php _e('Find Room and Group IDs', 'hall-booking-calendar'); ?></h2>

			<?php
			global $wpdb;
			$rooms_table = $wpdb->prefix . 'hbc_rooms';
			$groups_table = $wpdb->prefix . 'hbc_groups';

			$rooms = $wpdb->get_results("SELECT id, name FROM $rooms_table WHERE status = 'active' ORDER BY id ASC");
			$groups = $wpdb->get_results("SELECT id, name FROM $groups_table WHERE status = 'active' ORDER BY id ASC");
			?>

			<h3><?php _e('Available Rooms:', 'hall-booking-calendar'); ?></h3>
			<?php if ($rooms) : ?>
				<table class="wp-list-table widefat fixed striped" style="max-width: 500px;">
					<thead>
						<tr>
							<th><?php _e('Room ID', 'hall-booking-calendar'); ?></th>
							<th><?php _e('Room Name', 'hall-booking-calendar'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rooms as $room) : ?>
							<tr>
								<td><strong><?php echo esc_html($room->id); ?></strong></td>
								<td><?php echo esc_html($room->name); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php _e('No active rooms found.', 'hall-booking-calendar'); ?></p>
			<?php endif; ?>

			<h3 style="margin-top: 20px;"><?php _e('Available Groups:', 'hall-booking-calendar'); ?></h3>
			<?php if ($groups) : ?>
				<table class="wp-list-table widefat fixed striped" style="max-width: 500px;">
					<thead>
						<tr>
							<th><?php _e('Group ID', 'hall-booking-calendar'); ?></th>
							<th><?php _e('Group Name', 'hall-booking-calendar'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($groups as $group) : ?>
							<tr>
								<td><strong><?php echo esc_html($group->id); ?></strong></td>
								<td><?php echo esc_html($group->name); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php _e('No active groups found.', 'hall-booking-calendar'); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
