/**
 * Frontend JavaScript for Hall Booking Calendar
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Validate file upload
        function validateFile(file) {
            var errorDiv = $('.hbc-file-error');
            errorDiv.hide().text('');

            // Check file type
            if (file.type !== 'application/pdf') {
                errorDiv.text('Only PDF files are allowed.').show();
                return false;
            }

            // Check file size (5MB max)
            var maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                errorDiv.text('File size must be less than 5MB.').show();
                return false;
            }

            return true;
        }

        // Toggle recurring options
        $('#hbc_is_recurring').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc-recurring-options').slideDown();
                $('#hbc_add_multiple_dates').prop('checked', false);
                $('#hbc-multiple-dates-section').slideUp();
            } else {
                $('#hbc-recurring-options').slideUp();
            }
        });

        // Toggle multiple dates section
        $('#hbc_add_multiple_dates').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc-multiple-dates-section').slideDown();
                $('#hbc_is_recurring').prop('checked', false);
                $('#hbc-recurring-options').slideUp();
            } else {
                $('#hbc-multiple-dates-section').slideUp();
            }
        });

        // Add additional date field
        $('#hbc-add-date-btn').on('click', function() {
            var today = new Date().toISOString().split('T')[0];
            var newDateField = $('<input type="date" class="hbc-additional-date" name="additional_dates[]" min="' + today + '">');
            $('#hbc-additional-dates-container').append(newDateField);
        });

        // File upload change event
        $('#hbc_file_upload').on('change', function() {
            if (this.files.length > 0) {
                validateFile(this.files[0]);
            }
        });

        // Get available slots when room and date are selected
        $('#hbc_room_id, #hbc_booking_date').on('change', function() {
            var roomId = $('#hbc_room_id').val();
            var bookingDate = $('#hbc_booking_date').val();

            if (roomId && bookingDate) {
                checkAvailability(roomId, bookingDate);
            }
        });

        // Check availability function
        function checkAvailability(roomId, bookingDate) {
            $.ajax({
                url: hbc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hbc_get_available_slots',
                    nonce: hbc_ajax.nonce,
                    room_id: roomId,
                    booking_date: bookingDate
                },
                success: function(response) {
                    if (response.success) {
                        // You could display available/booked slots here
                        console.log('Booked slots:', response.data.booked_slots);
                    }
                }
            });
        }

        // Handle booking form submission
        $('#hbc-booking-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var submitBtn = form.find('.hbc-submit-btn');
            var messageDiv = form.find('.hbc-form-message');

            // Validate time range
            var startTime = $('#hbc_start_time').val();
            var endTime = $('#hbc_end_time').val();
            if (startTime && endTime && startTime >= endTime) {
                showMessage('error', 'End time must be after start time.');
                return;
            }

            // Validate file if uploaded
            var fileInput = document.getElementById('hbc_file_upload');
            if (fileInput && fileInput.files.length > 0) {
                if (!validateFile(fileInput.files[0])) {
                    return;
                }
            }

            // Disable submit button
            submitBtn.prop('disabled', true).text('Submitting...');
            messageDiv.hide().removeClass('success error');

            // Prepare form data
            var formData = new FormData();
            formData.append('action', 'hbc_submit_booking');
            formData.append('nonce', hbc_ajax.nonce);
            formData.append('room_id', $('#hbc_room_id').val());
            formData.append('group_id', $('#hbc_group_id').val());
            formData.append('user_name', $('#hbc_user_name').val());
            formData.append('user_email', $('#hbc_user_email').val());
            formData.append('booking_date', $('#hbc_booking_date').val());
            formData.append('start_time', $('#hbc_start_time').val());
            formData.append('end_time', $('#hbc_end_time').val());
            formData.append('purpose', $('#hbc_purpose').val());
            formData.append('description', $('#hbc_description').val());
            formData.append('is_recurring', $('#hbc_is_recurring').is(':checked') ? '1' : '0');
            formData.append('recurrence_pattern', $('#hbc_recurrence_pattern').val());
            formData.append('recurrence_end', $('#hbc_recurrence_end').val());

            // Add password if required
            var passwordInput = $('#hbc_booking_password_input');
            if (passwordInput.length > 0) {
                formData.append('booking_password_input', passwordInput.val());
            }

            // Add file if uploaded
            if (fileInput && fileInput.files.length > 0) {
                formData.append('booking_file', fileInput.files[0]);
            }

            // Collect additional dates if multiple dates option is checked
            if ($('#hbc_add_multiple_dates').is(':checked')) {
                $('.hbc-additional-date').each(function(index) {
                    var dateVal = $(this).val();
                    if (dateVal) {
                        formData.append('additional_dates[' + index + ']', dateVal);
                    }
                });
            }

            // Submit via AJAX
            $.ajax({
                url: hbc_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        form[0].reset();

                        // Redirect back to calendar after 2 seconds
                        setTimeout(function() {
                            // Remove action and date parameters to return to calendar
                            var url = window.location.href.split('?')[0];
                            window.location.href = url;
                        }, 2000);
                    } else {
                        showMessage('error', response.data.message);
                    }
                },
                error: function() {
                    showMessage('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Submit Booking');
                }
            });
        });

        // Show message function
        function showMessage(type, message) {
            var messageDiv = $('.hbc-form-message');
            messageDiv
                .removeClass('success error')
                .addClass(type)
                .html(message)
                .show();

            // Scroll to message
            $('html, body').animate({
                scrollTop: messageDiv.offset().top - 100
            }, 500);
        }

        // Set minimum date to today
        var today = new Date().toISOString().split('T')[0];
        $('#hbc_booking_date, .hbc-additional-date').attr('min', today);

        // Validate date input
        $(document).on('change', '#hbc_booking_date, .hbc-additional-date', function() {
            var selectedDate = $(this).val();
            if (selectedDate < today) {
                alert('Please select a date that is today or in the future.');
                $(this).val('');
            }
        });

    });

})(jQuery);
