/**
 * Cablecast Chapters Shortcode JavaScript
 *
 * Handles chapter click events and postMessage communication with VOD player.
 */
(function() {
    'use strict';

    // Debug mode - set to true for verbose logging
    const DEBUG = false;

    function log() {
        if (DEBUG) {
            console.log.apply(console, ['[Cablecast Chapters]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    function warn() {
        console.warn.apply(console, ['[Cablecast Chapters]'].concat(Array.prototype.slice.call(arguments)));
    }

    // State per chapter container
    var chapterContainers = [];

    /**
     * Initialize when DOM is ready.
     */
    document.addEventListener('DOMContentLoaded', function() {
        log('DOMContentLoaded - initializing chapters');
        initChapters();
    });

    /**
     * Initialize chapters functionality.
     */
    function initChapters() {
        var containers = document.querySelectorAll('.cablecast-chapters');

        log('Found', containers.length, 'chapter container(s)');

        if (containers.length === 0) {
            log('No chapter containers found, exiting');
            return;
        }

        containers.forEach(function(container, idx) {
            log('Setting up container', idx, container);
            var state = setupChapterContainer(container);
            if (state) {
                chapterContainers.push(state);
                log('Container', idx, 'setup complete. State:', state);
            }
        });

        // Listen for timeupdate messages from the player
        log('Adding message listener for player events');
        window.addEventListener('message', handlePlayerMessage);
    }

    /**
     * Set up a chapter container with click handlers.
     * Returns state object for this container.
     */
    function setupChapterContainer(container) {
        var showId = container.dataset.showId;
        var playerSelector = container.dataset.playerSelector;

        log('Setting up container for show:', showId, 'playerSelector:', playerSelector);

        // Find the target iframe
        var iframe = findPlayerIframe(container, playerSelector);
        if (!iframe) {
            warn('No player iframe found for show', showId);
            // Still allow display, just without interactivity
        } else {
            log('Found iframe:', iframe.src);
        }

        // Cache chapter elements and offsets
        var chapterElements = container.querySelectorAll('.cablecast-chapters__item');
        var chapterOffsets = [];

        log('Found', chapterElements.length, 'chapter elements');

        chapterElements.forEach(function(item, index) {
            var offset = parseInt(item.dataset.offset, 10);
            chapterOffsets.push(offset);
            log('Chapter', index, '- offset:', offset, 'title:', item.querySelector('.cablecast-chapters__title')?.textContent);

            var button = item.querySelector('.cablecast-chapters__button');
            if (button) {
                log('Found button for chapter', index);
                if (iframe) {
                    button.addEventListener('click', function(e) {
                        log('Chapter', index, 'clicked! Offset:', offset);
                        seekToChapter(iframe, offset, chapterElements, index);
                    });
                    log('Click handler attached for chapter', index);
                } else {
                    warn('No iframe available, click handler NOT attached for chapter', index);
                }
            } else {
                warn('No button found for chapter', index);
            }
        });

        return {
            container: container,
            iframe: iframe,
            chapterElements: chapterElements,
            chapterOffsets: chapterOffsets,
            currentChapterIndex: -1
        };
    }

    /**
     * Find the player iframe.
     *
     * Strategy:
     * 1. If playerSelector is specified, use that
     * 2. Look for iframe in .cablecast-show__vod or .cablecast-vod-player
     * 3. Fall back to first iframe on the page containing expected player domains
     */
    function findPlayerIframe(container, playerSelector) {
        var iframe = null;

        log('findPlayerIframe - playerSelector:', playerSelector);

        if (playerSelector) {
            log('Trying playerSelector + iframe:', playerSelector + ' iframe');
            iframe = document.querySelector(playerSelector + ' iframe');
            if (iframe) {
                log('Found iframe via playerSelector + iframe');
                return iframe;
            }

            // Try the selector directly if it's targeting an iframe
            log('Trying playerSelector directly:', playerSelector);
            iframe = document.querySelector(playerSelector);
            if (iframe && iframe.tagName === 'IFRAME') {
                log('Found iframe via direct playerSelector');
                return iframe;
            }
        }

        // Look for VOD player containers near the chapter list
        log('Looking for .cablecast-show__vod or .cablecast-vod-player containers');
        var vodContainer = document.querySelector('.cablecast-show__vod, .cablecast-vod-player');
        if (vodContainer) {
            log('Found VOD container:', vodContainer.className);
            iframe = vodContainer.querySelector('iframe');
            if (iframe) {
                log('Found iframe in VOD container:', iframe.src);
                return iframe;
            }
        }

        // Fall back to any iframe that looks like a Cablecast player
        log('Falling back to scanning all iframes');
        var iframes = document.querySelectorAll('iframe');
        log('Found', iframes.length, 'total iframes on page');

        for (var i = 0; i < iframes.length; i++) {
            var src = iframes[i].src || '';
            log('Iframe', i, 'src:', src);
            // Match common Cablecast/Tightrope player URLs
            if (src.indexOf('cablecast') !== -1 ||
                src.indexOf('trms') !== -1 ||
                src.indexOf('public.') !== -1 ||
                src.indexOf('watch') !== -1 ||
                src.indexOf('vod-embed') !== -1 ||
                src.indexOf('watch-vod-embed') !== -1) {
                log('Iframe', i, 'matches Cablecast pattern');
                return iframes[i];
            }
        }

        warn('No matching iframe found');
        return null;
    }

    /**
     * Seek to a chapter.
     */
    function seekToChapter(iframe, offset, chapterElements, index) {
        log('seekToChapter called - offset:', offset, 'index:', index);

        if (!iframe || !iframe.contentWindow) {
            warn('Cannot seek - no player iframe or contentWindow');
            return;
        }

        if (typeof offset !== 'number') {
            warn('Cannot seek - offset is not a number:', offset);
            return;
        }

        // Send postMessage to player
        var message = {
            type: 'player-cue',
            value: offset
        };
        log('Sending postMessage to iframe:', message);
        log('Iframe src:', iframe.src);

        try {
            iframe.contentWindow.postMessage(message, '*');
            log('postMessage sent successfully');
        } catch (e) {
            warn('Error sending postMessage:', e);
        }

        // Update current chapter highlight immediately for responsive feel
        updateCurrentChapterUI(chapterElements, index);
    }

    /**
     * Handle messages from the player (timeupdate, etc).
     */
    function handlePlayerMessage(event) {
        var data = event.data;

        // Validate message structure
        if (!data || typeof data !== 'object') return;

        // Log all messages for debugging (be selective to avoid noise)
        if (data.message || data.type) {
            log('Received postMessage:', data);
        }

        if (data.message === 'timeupdate' && typeof data.value === 'number') {
            log('timeupdate received:', data.value);
            updateAllChapterHighlights(data.value);
        }
    }

    /**
     * Update chapter highlighting for all containers based on current playback time.
     */
    function updateAllChapterHighlights(currentTime) {
        chapterContainers.forEach(function(state) {
            updateChapterHighlightForContainer(state, currentTime);
        });
    }

    /**
     * Update chapter highlight for a specific container.
     */
    function updateChapterHighlightForContainer(state, currentTime) {
        if (!state.chapterElements || state.chapterOffsets.length === 0) return;

        // Find the current chapter (last chapter whose offset is <= current time)
        var newIndex = -1;
        for (var i = state.chapterOffsets.length - 1; i >= 0; i--) {
            if (state.chapterOffsets[i] <= currentTime) {
                newIndex = i;
                break;
            }
        }

        if (newIndex !== state.currentChapterIndex) {
            log('Chapter changed from', state.currentChapterIndex, 'to', newIndex);
            updateCurrentChapterUI(state.chapterElements, newIndex);
            state.currentChapterIndex = newIndex;
        }
    }

    /**
     * Update the visual highlighting of the current chapter.
     */
    function updateCurrentChapterUI(chapterElements, newIndex) {
        // Remove current class from all
        chapterElements.forEach(function(el) {
            el.classList.remove('cablecast-chapters__item--current');
        });

        // Add current class to new
        if (newIndex >= 0 && chapterElements[newIndex]) {
            chapterElements[newIndex].classList.add('cablecast-chapters__item--current');

            // Optionally scroll into view if needed
            scrollChapterIntoView(chapterElements[newIndex]);
        }
    }

    /**
     * Scroll chapter into view if it's outside the visible area.
     */
    function scrollChapterIntoView(element) {
        // Only scroll if parent has a fixed height/overflow
        var parent = element.closest('.cablecast-chapters__list');
        if (!parent) return;

        var parentStyle = window.getComputedStyle(parent);
        if (parentStyle.overflowY !== 'auto' && parentStyle.overflowY !== 'scroll') {
            return;
        }

        var parentRect = parent.getBoundingClientRect();
        var elementRect = element.getBoundingClientRect();

        // Check if element is outside visible area
        if (elementRect.top < parentRect.top || elementRect.bottom > parentRect.bottom) {
            element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

})();
