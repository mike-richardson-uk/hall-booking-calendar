/**
 * Admin JavaScript for Hall Booking Calendar
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Add any admin-specific JavaScript here

        // Confirm deletion
        $('.hbc-delete-btn').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });

        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 5000);
    });

})(jQuery);
