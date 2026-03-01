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

        // ── Inline conflict warning ───────────────────────────────────────────
        // Trigger a lightweight conflict check whenever the user changes rooms,
        // date, or times. Shows a yellow warning banner without blocking submit.

        var conflictCheckTimer = null;

        function triggerConflictCheck() {
            clearTimeout(conflictCheckTimer);
            conflictCheckTimer = setTimeout(function() {
                var roomIds = [];
                $('input[name="room_ids[]"]:checked').each(function() {
                    roomIds.push($(this).val());
                });
                var bookingDate = $('#hbc_booking_date').val();
                var startTime   = $('#hbc_start_time').val();
                var endTime     = $('#hbc_end_time').val();

                var $warning = $('#hbc-conflict-warning');

                if (!roomIds.length || !bookingDate || !startTime || !endTime || startTime >= endTime) {
                    $warning.hide();
                    return;
                }

                var data = {
                    action:       'hbc_check_conflicts_inline',
                    nonce:        hbc_ajax.nonce,
                    booking_date: bookingDate,
                    start_time:   startTime,
                    end_time:     endTime
                };
                roomIds.forEach(function(id, i) {
                    data['room_ids[' + i + ']'] = id;
                });

                $.post(hbc_ajax.ajax_url, data, function(response) {
                    if (response.success && response.data.has_conflict) {
                        $warning.html(
                            '<strong>&#9888; ' + response.data.message + '</strong>'
                        ).show();
                    } else {
                        $warning.hide();
                    }
                });
            }, 400);
        }

        $(document).on('change', 'input[name="room_ids[]"], #hbc_booking_date', triggerConflictCheck);
        $(document).on('change blur', '#hbc_start_time, #hbc_end_time', triggerConflictCheck);

        // Handle booking form submission
        $('#hbc-booking-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var submitBtn = form.find('.hbc-submit-btn');
            var messageDiv = form.find('.hbc-form-message');

            // Validate at least one room is selected
            var selectedRooms = $('input[name="room_ids[]"]:checked');
            if (selectedRooms.length === 0) {
                showMessage('error', 'Please select at least one room.');
                return;
            }

            // Validate category is selected
            var categorySelect = $('#hbc_category_id');
            if (categorySelect.length > 0 && !categorySelect.val()) {
                showMessage('error', 'Please select an event category.');
                return;
            }

            // Validate terms acceptance if checkbox exists
            var termsCheck = $('#hbc_accept_terms');
            if (termsCheck.length > 0 && !termsCheck.is(':checked')) {
                showMessage('error', 'You must accept the terms and conditions.');
                return;
            }

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

            // Disable submit button and show loading state
            submitBtn.prop('disabled', true).text('Submitting Booking...').addClass('loading');
            messageDiv.hide().removeClass('success error');

            // Prepare form data
            var formData = new FormData();
            formData.append('action', 'hbc_submit_booking');
            formData.append('nonce', hbc_ajax.nonce);

            // Append all selected room IDs
            $('input[name="room_ids[]"]:checked').each(function(index) {
                formData.append('room_ids[' + index + ']', $(this).val());
            });

            formData.append('group_id', $('#hbc_group_id').val());
            formData.append('category_id', $('#hbc_category_id').val());
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

            // Add terms acceptance
            var termsCheckbox = $('#hbc_accept_terms');
            if (termsCheckbox.length > 0) {
                formData.append('accept_terms', termsCheckbox.is(':checked') ? '1' : '0');
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

            // Append booking-in form config if enabled
            if ($('#hbc_enable_book_in').is(':checked')) {
                formData.append('enable_book_in', '1');
                $('input[name="book_in_emails[]"]').each(function(i) {
                    var emailVal = $.trim($(this).val());
                    if (emailVal) {
                        formData.append('book_in_emails[' + i + ']', emailVal);
                    }
                });
                if ($('#hbc_include_meal_menu').is(':checked')) {
                    formData.append('include_meal_menu', '1');
                    var meals = [];
                    $('#hbc-meal-items-list .hbc-meal-item-row').each(function() {
                        var mealName = $.trim($(this).find('.hbc-meal-name-input').val());
                        if (mealName) {
                            meals.push({
                                name:        mealName,
                                description: $.trim($(this).find('.hbc-meal-desc-input').val()),
                                price:       $(this).find('.hbc-meal-price-input').val()
                            });
                        }
                    });
                    formData.append('book_in_meals_json', JSON.stringify(meals));
                }
                formData.append('payment_bank_name',       $('#hbc_payment_bank_name').val());
                formData.append('payment_sort_code',        $('#hbc_payment_sort_code').val());
                formData.append('payment_account_number',   $('#hbc_payment_account_number').val());
                formData.append('payment_reference_prefix', $('#hbc_payment_reference_prefix').val());
                formData.append('payment_cheque_payable',   $('#hbc_payment_cheque_payable').val());
                formData.append('payment_deadline',         $('#hbc_payment_deadline').val());
            }

            // Submit via AJAX
            $.ajax({
                url: hbc_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Booking response:', response);
                    if (response.success) {
                        showMessage('success', response.data.message);
                        form[0].reset();

                        // Redirect back to calendar after 4 seconds
                        setTimeout(function() {
                            // Remove action and date parameters to return to calendar
                            var url = window.location.href.split('?')[0];
                            window.location.href = url;
                        }, 4000);
                    } else {
                        showMessage('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error, xhr.responseText);
                    showMessage('error', 'Something went wrong submitting your booking. Please try again, or contact us directly if the problem continues.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Submit Booking').removeClass('loading');
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

        // ---------------------------------------------------------------
        // Booking-In Form – room booking form setup section
        // ---------------------------------------------------------------

        // Toggle the book-in options panel
        $('#hbc_enable_book_in').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc-book-in-options').slideDown();
            } else {
                $('#hbc-book-in-options').slideUp();
            }
        });

        // Toggle the meal menu builder
        $('#hbc_include_meal_menu').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hbc-meal-menu-builder').slideDown();
            } else {
                $('#hbc-meal-menu-builder').slideUp();
            }
        });

        // Add email recipient row
        $('#hbc-add-email-btn').on('click', function() {
            var newRow = $('<div class="hbc-email-entry" style="display:flex;gap:6px;margin-bottom:4px;">' +
                '<input type="email" name="book_in_emails[]" class="regular-text" placeholder="email@example.com">' +
                '<button type="button" class="button hbc-remove-email">&times;</button>' +
                '</div>');
            $('#hbc-book-in-emails').append(newRow);
            $('#hbc-book-in-emails .hbc-remove-email').show();
        });

        // Remove email recipient row
        $(document).on('click', '.hbc-remove-email', function() {
            $(this).closest('.hbc-email-entry').remove();
            if ($('#hbc-book-in-emails .hbc-email-entry').length === 1) {
                $('#hbc-book-in-emails .hbc-remove-email').hide();
            }
        });

        // Add meal option row
        $('#hbc-add-meal-btn').on('click', function() {
            var idx = $('#hbc-meal-items-list .hbc-meal-item-row').length;
            var row = $(
                '<div class="hbc-meal-item-row" style="display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap;align-items:flex-start;">' +
                '<input type="text" class="hbc-meal-name-input" placeholder="Meal name *" style="flex:2;min-width:120px;">' +
                '<textarea class="hbc-meal-desc-input" placeholder="Menu / description (optional)" style="flex:3;min-width:160px;" rows="4"></textarea>' +
                '<input type="number" class="hbc-meal-price-input" placeholder="Price (£)" min="0" step="0.01" style="width:90px;">' +
                '<button type="button" class="button hbc-remove-meal">&times;</button>' +
                '</div>'
            );
            $('#hbc-meal-items-list').append(row);
        });

        // Remove meal option row
        $(document).on('click', '.hbc-remove-meal', function() {
            $(this).closest('.hbc-meal-item-row').remove();
        });

        // ---------------------------------------------------------------
        // Booking-In Form – member-facing form submission
        // ---------------------------------------------------------------

        // Show/hide dinner-only sections based on attendance selection
        $(document).on('change', 'input[name="bi_attendance_type"]', function() {
            if ($(this).val() === 'attending_dinner') {
                $('.hbc-bi-dinner-only').slideDown();
            } else {
                $('.hbc-bi-dinner-only').slideUp();
            }
        });

        // Show Lodge Name only when Guest is selected
        $(document).on('change', 'input[name="bi_membership_type"]', function() {
            if ($(this).val() === 'guest') {
                $('#hbc-bi-lodge-name-row').slideDown();
            } else {
                $('#hbc-bi-lodge-name-row').slideUp();
            }
        });

        // Submit the booking-in form via AJAX
        $(document).on('submit', '#hbc-book-in-form', function(e) {
            e.preventDefault();

            var form      = $(this);
            var submitBtn = form.find('.hbc-submit-btn');

            if (!$('input[name="bi_attendance_type"]:checked').length) {
                showBookInMessage('error', 'Please select an attendance type.');
                return;
            }
            if (!$('input[name="bi_membership_type"]:checked').length) {
                showBookInMessage('error', 'Please select a membership type.');
                return;
            }
            if ($('input[name="bi_membership_type"]:checked').val() === 'guest' && !$.trim($('#hbc_bi_lodge_name').val())) {
                showBookInMessage('error', 'Please enter the Lodge Name.');
                return;
            }

            submitBtn.prop('disabled', true).text('Submitting...').addClass('loading');

            $.ajax({
                url:  hbc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action:                 'hbc_submit_book_in',
                    nonce:                  hbc_ajax.book_in_nonce,
                    booking_id:             $('input[name="booking_id"]', form).val(),
                    bi_full_name:           $('#hbc_bi_full_name').val(),
                    bi_email:               $('#hbc_bi_email').val(),
                    bi_phone:               $('#hbc_bi_phone').val(),
                    bi_masonic_rank:        $('#hbc_bi_masonic_rank').val(),
                    bi_attendance_type:     $('input[name="bi_attendance_type"]:checked').val(),
                    bi_membership_type:     $('input[name="bi_membership_type"]:checked').val(),
                    bi_lodge_name:          $('#hbc_bi_lodge_name').val(),
                    bi_meal_choice:         $('input[name="bi_meal_choice"]:checked').val() || '',
                    bi_vegetarian_alt:      $('#hbc_bi_vegetarian_alt').is(':checked') ? '1' : '0',
                    bi_dietary_requirements: $('#hbc_bi_dietary').val(),
                    bi_additional_comments: $('#hbc_bi_comments').val()
                },
                success: function(response) {
                    if (response.success) {
                        showBookInMessage('success', response.data.message);
                        form[0].reset();
                        $('.hbc-bi-dinner-only').hide();
                    } else {
                        showBookInMessage('error', response.data.message);
                    }
                },
                error: function() {
                    showBookInMessage('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Confirm Booking').removeClass('loading');
                }
            });
        });

        function showBookInMessage(type, message) {
            var messageDiv = $('#hbc-book-in-form .hbc-form-message');
            messageDiv.removeClass('success error').addClass(type).html(message).show();
            $('html, body').animate({ scrollTop: messageDiv.offset().top - 100 }, 400);
        }

    });

})(jQuery);
