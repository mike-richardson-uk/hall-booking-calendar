/**
 * Frontend JavaScript for Hall Booking Calendar
 * With Multi-step Wizard Navigation
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        var currentStep = 0;
        var requirePassword = $('.hbc-wizard-step[data-step="0"]').length > 0;
        var totalSteps = $('.hbc-wizard-step').length;

        // Initialize first step
        updateWizard();

        // Wizard navigation - Next button
        $(document).on('click', '.hbc-next-btn', function(e) {
            e.preventDefault();

            // Validate current step
            if (validateStep(currentStep)) {
                // Special handling for password step
                if (currentStep === 0 && requirePassword) {
                    verifyPassword(function(success) {
                        if (success) {
                            currentStep++;
                            updateWizard();
                        }
                    });
                } else {
                    currentStep++;
                    updateWizard();
                }
            }
        });

        // Wizard navigation - Previous button
        $(document).on('click', '.hbc-prev-btn', function(e) {
            e.preventDefault();
            currentStep--;
            updateWizard();
        });

        // Update wizard display
        function updateWizard() {
            // Hide all steps
            $('.hbc-wizard-step').removeClass('active');

            // Show current step
            $('.hbc-wizard-step[data-step="' + currentStep + '"]').addClass('active');

            // Update progress indicator
            $('.hbc-progress-step').removeClass('active completed');
            $('.hbc-progress-step').each(function() {
                var stepNum = parseInt($(this).data('step'));
                if (stepNum < currentStep) {
                    $(this).addClass('completed');
                } else if (stepNum === currentStep) {
                    $(this).addClass('active');
                }
            });

            // Update review step if it's the current step
            if (currentStep === totalSteps - 1) {
                updateReviewStep();
            }

            // Scroll to top of form
            $('html, body').animate({
                scrollTop: $('#hbc-booking-form').offset().top - 100
            }, 300);
        }

        // Validate current step
        function validateStep(step) {
            var currentStepEl = $('.hbc-wizard-step[data-step="' + step + '"]');
            var isValid = true;

            // Check required fields in current step
            currentStepEl.find('input[required], select[required], textarea[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error-field');
                } else {
                    $(this).removeClass('error-field');
                }
            });

            // Step-specific validation
            if (step === 1) {
                // Validate time range
                var startTime = $('#hbc_start_time').val();
                var endTime = $('#hbc_end_time').val();

                if (startTime && endTime && startTime >= endTime) {
                    showMessage('error', 'End time must be after start time.');
                    isValid = false;
                }
            }

            if (step === 3) {
                // Validate file if uploaded
                var fileInput = document.getElementById('hbc_file_upload');
                if (fileInput && fileInput.files.length > 0) {
                    if (!validateFile(fileInput.files[0])) {
                        isValid = false;
                    }
                }
            }

            if (!isValid) {
                showMessage('error', 'Please fill in all required fields correctly.');
            }

            return isValid;
        }

        // Verify password
        function verifyPassword(callback) {
            var password = $('#hbc_booking_password_input').val();

            // For password verification, we'll just continue for now
            // The actual verification happens on the server side
            callback(true);
        }

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

        // Update review step with form data
        function updateReviewStep() {
            // Room
            var roomText = $('#hbc_room_id option:selected').text();
            $('#review-room').text(roomText || 'N/A');

            // Date
            var date = $('#hbc_booking_date').val();
            if (date) {
                var dateObj = new Date(date + 'T00:00:00');
                $('#review-date').text(dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));
            }

            // Time
            var startTime = $('#hbc_start_time').val();
            var endTime = $('#hbc_end_time').val();
            if (startTime && endTime) {
                $('#review-time').text(formatTime(startTime) + ' - ' + formatTime(endTime));
            }

            // Name
            $('#review-name').text($('#hbc_user_name').val() || 'N/A');

            // Email
            $('#review-email').text($('#hbc_user_email').val() || 'N/A');

            // Group
            var groupText = $('#hbc_group_id option:selected').text();
            $('#review-group').text(groupText === '-- Select a Group (Optional) --' ? 'None' : groupText);

            // Purpose
            $('#review-purpose').text($('#hbc_purpose').val() || 'None');

            // Description
            $('#review-description').text($('#hbc_description').val() || 'None');

            // File
            var fileInput = document.getElementById('hbc_file_upload');
            if (fileInput && fileInput.files.length > 0) {
                $('#review-file').text(fileInput.files[0].name);
            } else {
                $('#review-file').text('No file attached');
            }

            // Recurring
            if ($('#hbc_is_recurring').is(':checked')) {
                var pattern = $('#hbc_recurrence_pattern option:selected').text();
                var endDate = $('#hbc_recurrence_end').val();
                $('#review-recurring').text('Yes - ' + pattern + ' until ' + endDate);
            } else if ($('#hbc_add_multiple_dates').is(':checked')) {
                var dateCount = $('.hbc-additional-date').filter(function() {
                    return $(this).val() !== '';
                }).length + 1; // +1 for main date
                $('#review-recurring').text('Multiple dates (' + dateCount + ' dates)');
            } else {
                $('#review-recurring').text('No');
            }
        }

        // Format time for display
        function formatTime(time) {
            var parts = time.split(':');
            var hours = parseInt(parts[0]);
            var minutes = parts[1];
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // 0 should be 12
            return hours + ':' + minutes + ' ' + ampm;
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

        // Open booking modal
        $('.hbc-book-btn').on('click', function(e) {
            e.preventDefault();
            var date = $(this).data('date');
            $('#hbc_booking_date').val(date);
            $('#hbc-booking-modal').fadeIn();
        });

        // Close modal
        $('.hbc-modal-close').on('click', function() {
            $('#hbc-booking-modal').fadeOut();
        });

        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).is('#hbc-booking-modal')) {
                $('#hbc-booking-modal').fadeOut();
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
            if (requirePassword) {
                formData.append('booking_password_input', $('#hbc_booking_password_input').val());
            }

            // Add file if uploaded
            var fileInput = document.getElementById('hbc_file_upload');
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

                        // Reset wizard to first step
                        currentStep = 0;
                        updateWizard();

                        // Close modal after 2 seconds
                        setTimeout(function() {
                            $('#hbc-booking-modal').fadeOut();
                            messageDiv.hide();
                            // Reload page to show updated calendar
                            location.reload();
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
