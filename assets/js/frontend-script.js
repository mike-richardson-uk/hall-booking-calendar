/**
 * Frontend JavaScript for Hall Booking Calendar
 */

(function($) {
    'use strict';

    $(document).ready(function() {

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

            // Validate times
            var startTime = $('#hbc_start_time').val();
            var endTime = $('#hbc_end_time').val();

            if (startTime && endTime && startTime >= endTime) {
                showMessage('error', 'End time must be after start time.');
                return;
            }

            // Disable submit button
            submitBtn.prop('disabled', true).text('Submitting...');
            messageDiv.hide().removeClass('success error');

            // Get form data
            var formData = {
                action: 'hbc_submit_booking',
                nonce: hbc_ajax.nonce,
                room_id: $('#hbc_room_id').val(),
                user_name: $('#hbc_user_name').val(),
                user_email: $('#hbc_user_email').val(),
                booking_date: $('#hbc_booking_date').val(),
                start_time: startTime,
                end_time: endTime,
                purpose: $('#hbc_purpose').val()
            };

            // Submit via AJAX
            $.ajax({
                url: hbc_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        form[0].reset();

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
        $('#hbc_booking_date').attr('min', today);

        // Validate date input
        $('#hbc_booking_date').on('change', function() {
            var selectedDate = $(this).val();
            if (selectedDate < today) {
                alert('Please select a date that is today or in the future.');
                $(this).val('');
            }
        });

    });

})(jQuery);
