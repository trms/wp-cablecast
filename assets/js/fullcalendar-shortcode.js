/**
 * Cablecast FullCalendar Shortcode
 *
 * Initializes FullCalendar instances for the cablecast_schedule_calendar shortcode.
 */
document.addEventListener('DOMContentLoaded', function() {
    if (!window.cablecastCalendars || !window.FullCalendar) {
        return;
    }

    window.cablecastCalendars.forEach(function(config) {
        var calendarEl = document.getElementById(config.calendarId);
        if (!calendarEl) {
            return;
        }

        // Build header toolbar configuration
        var headerToolbar = false;
        if (config.showHeader || config.showNav) {
            headerToolbar = {
                left: config.showNav ? 'prev,next today' : '',
                center: 'title',
                right: config.showHeader ? 'timeGridWeek,timeGridDay,dayGridMonth,listWeek' : ''
            };
        }

        // Initialize FullCalendar
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: config.initialView,
            height: config.height === 'auto' ? 'auto' : parseInt(config.height),
            headerToolbar: headerToolbar,
            nowIndicator: true,
            navLinks: true,
            eventClick: function(info) {
                // Navigate to show page when clicking an event
                if (info.event.url) {
                    info.jsEvent.preventDefault();
                    window.location.href = info.event.url;
                }
            },
            events: function(fetchInfo, successCallback, failureCallback) {
                // Fetch events via AJAX
                var url = config.ajaxUrl +
                    '?action=cablecast_calendar_events' +
                    '&channel_id=' + encodeURIComponent(config.channelId) +
                    '&start=' + encodeURIComponent(fetchInfo.startStr) +
                    '&end=' + encodeURIComponent(fetchInfo.endStr) +
                    '&nonce=' + encodeURIComponent(config.nonce);

                fetch(url)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        successCallback(data);
                    })
                    .catch(function(error) {
                        console.error('Error fetching calendar events:', error);
                        failureCallback(error);
                    });
            },
            // View-specific options
            views: {
                timeGridWeek: {
                    slotMinTime: '06:00:00',
                    slotMaxTime: '24:00:00',
                    slotDuration: '00:30:00',
                    allDaySlot: false
                },
                timeGridDay: {
                    slotMinTime: '06:00:00',
                    slotMaxTime: '24:00:00',
                    slotDuration: '00:30:00',
                    allDaySlot: false
                },
                listWeek: {
                    noEventsContent: 'No programs scheduled'
                }
            },
            // Custom button text
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day',
                list: 'List'
            },
            // Event display
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                meridiem: 'short'
            }
        });

        calendar.render();
    });
});
