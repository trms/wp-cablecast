jQuery(document).ready(function($) {
    $('#schedule_date').on('change', function() {
        var selectedDate = $(this).val();
        var data = {
            action: 'cablecast_update_schedule',
            schedule_date: selectedDate,
            post_id: $('#schedule_date').data('post-id')
        };

        $.post(cablecast_ajax.ajax_url, data, function(response) {
            $('.schedule-container').html(response);
        });
    });
});
