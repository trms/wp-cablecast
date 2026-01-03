/**
 * Cablecast Shortcode JavaScript
 *
 * Handles interactive functionality for Cablecast shortcodes.
 */

(function() {
    'use strict';

    /**
     * Initialize when DOM is ready.
     */
    document.addEventListener('DOMContentLoaded', function() {
        initWeeklyGuide();
        initNowPlayingProgress();
        initCablecastHome();
    });

    /**
     * Initialize weekly guide functionality.
     */
    function initWeeklyGuide() {
        var guides = document.querySelectorAll('.cablecast-weekly-guide');

        guides.forEach(function(guide) {
            // Scroll to current program on load
            scrollToCurrentProgram(guide);

            // Handle channel switcher
            var switcher = guide.querySelector('#cablecast-channel-select');
            if (switcher) {
                switcher.addEventListener('change', function() {
                    var url = new URL(window.location.href);
                    url.searchParams.set('channel', this.value);
                    window.location.href = url.toString();
                });
            }
        });
    }

    /**
     * Scroll the weekly guide to show the current program.
     */
    function scrollToCurrentProgram(guide) {
        var currentProgram = guide.querySelector('.cablecast-weekly-guide__program--current');

        if (currentProgram) {
            // Find the day column containing the current program
            var dayColumn = currentProgram.closest('.cablecast-weekly-guide__day');
            var programs = dayColumn ? dayColumn.querySelector('.cablecast-weekly-guide__programs') : null;

            if (programs) {
                // Scroll the program into view within its container
                var programTop = currentProgram.offsetTop - programs.offsetTop;
                programs.scrollTop = Math.max(0, programTop - 50);
            }

            // On mobile, scroll the grid to show today's column
            var grid = guide.querySelector('.cablecast-weekly-guide__grid');
            if (grid && dayColumn) {
                var dayIndex = Array.from(grid.children).indexOf(dayColumn);
                if (dayIndex > 0 && window.innerWidth <= 640) {
                    // On mobile, scroll horizontally to today
                    grid.scrollLeft = dayColumn.offsetLeft;
                }
            }
        }
    }

    /**
     * Update progress bars for now playing shortcodes.
     */
    function initNowPlayingProgress() {
        var progressBars = document.querySelectorAll('.cablecast-now-playing__progress-bar');

        if (progressBars.length === 0) return;

        // Update progress every 30 seconds
        setInterval(function() {
            // Reload the page to update now playing info
            // In a more sophisticated implementation, this could use AJAX
            progressBars.forEach(function(bar) {
                var currentWidth = parseFloat(bar.style.width) || 0;
                // Estimate progress increase (assuming 30 min show, 30 sec update = ~1.67%)
                var newWidth = Math.min(100, currentWidth + 1.67);
                bar.style.width = newWidth + '%';
            });
        }, 30000);
    }

    /**
     * Initialize Cablecast Home functionality.
     */
    function initCablecastHome() {
        var homeContainers = document.querySelectorAll('.cablecast-home');

        homeContainers.forEach(function(container) {
            var tabs = container.querySelectorAll('.cablecast-home__channel-tab');

            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var channelId = this.dataset.channel;
                    var url = new URL(window.location.href);
                    url.searchParams.set('channel', channelId);
                    window.location.href = url.toString();
                });
            });
        });
    }

})();
