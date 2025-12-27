<?php
/**
 * Cablecast Shortcodes
 *
 * Provides shortcodes for displaying schedule and show information
 * from Cablecast data synced to WordPress.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default filler keywords for schedule filtering.
 */
define('CABLECAST_DEFAULT_FILLER_KEYWORDS', [
    'color bars',
    'colorbars',
    'slate',
    'test pattern',
    'off air',
    'sign off',
    'station id',
    'programming break',
    'technical difficulties'
]);

/**
 * Track which shortcodes have been used on the current page
 * to conditionally enqueue assets.
 */
global $cablecast_shortcodes_used;
$cablecast_shortcodes_used = [];

/**
 * Register all Cablecast shortcodes.
 */
function cablecast_register_shortcodes() {
    add_shortcode('cablecast_schedule', 'cablecast_schedule_shortcode');
    add_shortcode('cablecast_now_playing', 'cablecast_now_playing_shortcode');
    add_shortcode('cablecast_weekly_guide', 'cablecast_weekly_guide_shortcode');
    add_shortcode('cablecast_shows', 'cablecast_shows_shortcode');
    add_shortcode('cablecast_show', 'cablecast_show_shortcode');
    add_shortcode('cablecast_vod_player', 'cablecast_vod_player_shortcode');
    add_shortcode('cablecast_chapters', 'cablecast_chapters_shortcode');
    add_shortcode('cablecast_producers', 'cablecast_producers_shortcode');
    add_shortcode('cablecast_series', 'cablecast_series_shortcode');
    add_shortcode('cablecast_schedule_calendar', 'cablecast_schedule_calendar_shortcode');
    add_shortcode('cablecast_upcoming_runs', 'cablecast_upcoming_runs_shortcode');
    add_shortcode('cablecast_categories', 'cablecast_categories_shortcode');
    add_shortcode('cablecast_home', 'cablecast_home_shortcode');
}
add_action('init', 'cablecast_register_shortcodes');

// Register AJAX handlers for FullCalendar
add_action('wp_ajax_cablecast_calendar_events', 'cablecast_calendar_events_ajax');
add_action('wp_ajax_nopriv_cablecast_calendar_events', 'cablecast_calendar_events_ajax');

/**
 * Enqueue shortcode assets conditionally.
 */
function cablecast_enqueue_shortcode_assets() {
    global $cablecast_shortcodes_used;

    // Only enqueue if shortcodes were used
    if (empty($cablecast_shortcodes_used)) {
        return;
    }

    $options = get_option('cablecast_options');
    $styles_enabled = !isset($options['shortcode_styles']) || $options['shortcode_styles'];

    if ($styles_enabled) {
        wp_enqueue_style(
            'cablecast-shortcodes',
            plugins_url('../assets/css/shortcodes.css', __FILE__),
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/shortcodes.css')
        );

        // Enqueue home page CSS if home shortcode was used
        if (in_array('home', $cablecast_shortcodes_used)) {
            wp_enqueue_style(
                'cablecast-home',
                plugins_url('../assets/css/cablecast-home.css', __FILE__),
                ['cablecast-shortcodes'],
                filemtime(plugin_dir_path(__FILE__) . '../assets/css/cablecast-home.css')
            );
        }
    }

    // Enqueue JS if weekly guide was used
    if (in_array('weekly_guide', $cablecast_shortcodes_used)) {
        wp_enqueue_script(
            'cablecast-shortcodes',
            plugins_url('../assets/js/shortcodes.js', __FILE__),
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/shortcodes.js'),
            true
        );
    }

    // Enqueue JS if chapters shortcode was used
    if (in_array('chapters', $cablecast_shortcodes_used)) {
        wp_enqueue_script(
            'cablecast-chapters',
            plugins_url('../assets/js/chapters.js', __FILE__),
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/chapters.js'),
            true
        );
    }
}
add_action('wp_footer', 'cablecast_enqueue_shortcode_assets', 5);
add_action('admin_footer', 'cablecast_enqueue_shortcode_assets', 5);

/**
 * Mark a shortcode as used for conditional asset loading.
 */
function cablecast_mark_shortcode_used($shortcode) {
    global $cablecast_shortcodes_used;
    if (!in_array($shortcode, $cablecast_shortcodes_used)) {
        $cablecast_shortcodes_used[] = $shortcode;
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if category colors are enabled.
 *
 * @return bool
 */
function cablecast_category_colors_enabled() {
    $options = get_option('cablecast_options');
    return !empty($options['enable_category_colors']);
}

/**
 * Get the color for a show based on its category.
 *
 * @param int $show_post_id WordPress post ID for the show
 * @return string|null Hex color or null if no color assigned
 */
function cablecast_get_show_category_color($show_post_id) {
    if (!cablecast_category_colors_enabled()) {
        return null;
    }

    $options = get_option('cablecast_options');
    $category_colors = isset($options['category_colors']) ? $options['category_colors'] : [];

    if (empty($category_colors)) {
        return null;
    }

    $terms = get_the_terms($show_post_id, 'category');
    if (!$terms || is_wp_error($terms)) {
        return null;
    }

    // Return the first matching color
    foreach ($terms as $term) {
        if (isset($category_colors[$term->slug])) {
            return $category_colors[$term->slug];
        }
    }

    return null;
}

/**
 * Check if a show title indicates filler content.
 *
 * @param string $title Show title
 * @return bool
 */
function cablecast_is_filler($title) {
    $options = get_option('cablecast_options');

    // Get filler keywords from settings or use defaults
    if (!empty($options['filler_keywords'])) {
        $keywords = array_map('trim', explode(',', $options['filler_keywords']));
    } else {
        $keywords = CABLECAST_DEFAULT_FILLER_KEYWORDS;
    }

    $title_lower = strtolower($title);

    foreach ($keywords as $keyword) {
        $keyword = strtolower(trim($keyword));
        if (empty($keyword)) continue;

        if ($title_lower === $keyword || strpos($title_lower, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Get channel post ID from Cablecast channel ID.
 *
 * @param int $cablecast_channel_id Cablecast channel ID
 * @return int|null WordPress post ID or null
 */
function cablecast_get_channel_post_id($cablecast_channel_id) {
    global $wpdb;

    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = 'cablecast_channel_id' AND meta_value = %s
         LIMIT 1",
        $cablecast_channel_id
    ));

    return $post_id ? (int) $post_id : null;
}

/**
 * Get all channels.
 *
 * @return array Array of channel posts
 */
function cablecast_get_all_channels() {
    return get_posts([
        'post_type' => 'cablecast_channel',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ]);
}

/**
 * Format a runtime (in seconds) to human-readable format.
 *
 * @param int $seconds Runtime in seconds
 * @return string Formatted runtime (e.g., "1h 30m" or "45m")
 */
function cablecast_format_runtime($seconds) {
    if (!$seconds || $seconds <= 0) {
        return '';
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    return $minutes . 'm';
}

/**
 * Get the show post from a schedule item.
 *
 * @param object $schedule_item Schedule item from database
 * @return WP_Post|null
 */
function cablecast_get_show_from_schedule($schedule_item) {
    if (!empty($schedule_item->show_post_id)) {
        return get_post($schedule_item->show_post_id);
    }
    return null;
}

// ============================================================================
// SCHEDULE SHORTCODE
// ============================================================================

/**
 * [cablecast_schedule] - Display schedule for a channel.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_schedule_shortcode($atts) {
    cablecast_mark_shortcode_used('schedule');

    $atts = shortcode_atts([
        'channel' => '',
        'date' => '',
        'mode' => 'all', // all, remaining, next
        'count' => 20,
        'show_descriptions' => 'true',
        'exclude_filler' => 'false',
        'show_thumbnails' => 'true',
        'class' => '',
    ], $atts, 'cablecast_schedule');

    // Validate channel
    $channel_id = absint($atts['channel']);
    if (!$channel_id) {
        return '<p class="cablecast-error">' . __('Please specify a channel ID.', 'cablecast') . '</p>';
    }

    // Get Cablecast channel ID from post meta
    $cablecast_channel_id = get_post_meta($channel_id, 'cablecast_channel_id', true);
    if (!$cablecast_channel_id) {
        return '<p class="cablecast-error">' . __('Invalid channel.', 'cablecast') . '</p>';
    }

    // Parse options
    $show_descriptions = filter_var($atts['show_descriptions'], FILTER_VALIDATE_BOOLEAN);
    $exclude_filler = filter_var($atts['exclude_filler'], FILTER_VALIDATE_BOOLEAN);
    $show_thumbnails = filter_var($atts['show_thumbnails'], FILTER_VALIDATE_BOOLEAN);
    $count = absint($atts['count']) ?: 20;
    $mode = in_array($atts['mode'], ['all', 'remaining', 'next']) ? $atts['mode'] : 'all';

    // Determine date range
    $timezone = get_option('timezone_string') ?: 'America/New_York';
    $now = new DateTime('now', new DateTimeZone($timezone));

    if (!empty($atts['date'])) {
        $date_start = $atts['date'];
        $date_end = date('Y-m-d', strtotime($date_start . ' +1 day'));
    } else {
        $date_start = $now->format('Y-m-d');
        // For "next" mode, get 2 days to ensure we find upcoming shows
        $date_end = $mode === 'next' ? date('Y-m-d', strtotime($date_start . ' +2 days')) : date('Y-m-d', strtotime($date_start . ' +1 day'));
    }

    // Fetch schedule items
    $items = cablecast_get_schedules($cablecast_channel_id, $date_start, $date_end);

    if (empty($items)) {
        return '<p class="cablecast-no-results">' . __('No schedule data available.', 'cablecast') . '</p>';
    }

    // Filter based on mode
    $now_timestamp = $now->getTimestamp();
    $filtered_items = [];

    foreach ($items as $item) {
        // Exclude filler if requested
        if ($exclude_filler && cablecast_is_filler($item->show_title)) {
            continue;
        }

        $item_time = strtotime($item->run_date_time);

        switch ($mode) {
            case 'remaining':
                // Only shows that haven't ended yet (assuming 30min default)
                if ($item_time + 1800 > $now_timestamp) {
                    $filtered_items[] = $item;
                }
                break;
            case 'next':
                // Only future shows
                if ($item_time > $now_timestamp) {
                    $filtered_items[] = $item;
                }
                break;
            default:
                $filtered_items[] = $item;
        }

        if (count($filtered_items) >= $count) {
            break;
        }
    }

    if (empty($filtered_items)) {
        return '<p class="cablecast-no-results">' . __('No upcoming programs.', 'cablecast') . '</p>';
    }

    // Build output
    $classes = ['cablecast-schedule'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    $current_date = '';
    foreach ($filtered_items as $item) {
        $item_date = date('Y-m-d', strtotime($item->run_date_time));
        $item_time = date('g:i A', strtotime($item->run_date_time));

        // Add day divider if date changes
        if ($item_date !== $current_date) {
            if ($current_date !== '') {
                $output .= '</div>'; // Close previous day group
            }
            $day_label = date('l, F j', strtotime($item->run_date_time));
            if ($item_date === $now->format('Y-m-d')) {
                $day_label = __('Today', 'cablecast');
            } elseif ($item_date === date('Y-m-d', strtotime('+1 day'))) {
                $day_label = __('Tomorrow', 'cablecast');
            }
            $output .= '<div class="cablecast-schedule__day-divider">' . esc_html($day_label) . '</div>';
            $output .= '<div class="cablecast-schedule__day-group">';
            $current_date = $item_date;
        }

        $show = cablecast_get_show_from_schedule($item);
        $color = $show ? cablecast_get_show_category_color($show->ID) : null;
        $style = $color ? ' style="border-left-color: ' . esc_attr($color) . ';"' : '';

        $output .= '<div class="cablecast-schedule__item"' . $style . '>';

        // Thumbnail
        if ($show_thumbnails && $show) {
            $thumbnail_url = cablecast_show_thumbnail_url($show->ID, 'thumbnail');
            if ($thumbnail_url) {
                $output .= '<div class="cablecast-schedule__thumbnail">';
                $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($item->show_title) . '" loading="lazy" />';
                $output .= '</div>';
            }
        }

        $output .= '<div class="cablecast-schedule__content">';
        $output .= '<span class="cablecast-schedule__time">' . esc_html($item_time) . '</span>';

        // Title with link if show exists
        if ($show) {
            $output .= '<a href="' . get_permalink($show->ID) . '" class="cablecast-schedule__title">';
            $output .= esc_html($item->show_title);
            $output .= '</a>';
        } else {
            $output .= '<span class="cablecast-schedule__title">' . esc_html($item->show_title) . '</span>';
        }

        // Description
        if ($show_descriptions && $show) {
            $description = get_post_meta($show->ID, 'cablecast_show_comments', true);
            if ($description) {
                $output .= '<p class="cablecast-schedule__description">' . esc_html(wp_trim_words($description, 20)) . '</p>';
            }
        }

        $output .= '</div>'; // content
        $output .= '</div>'; // item
    }

    if ($current_date !== '') {
        $output .= '</div>'; // Close last day group
    }

    $output .= '</div>'; // schedule

    return $output;
}

// ============================================================================
// NOW PLAYING SHORTCODE
// ============================================================================

/**
 * [cablecast_now_playing] - Display current and next program.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_now_playing_shortcode($atts) {
    cablecast_mark_shortcode_used('now_playing');

    $atts = shortcode_atts([
        'channel' => '',
        'show_up_next' => 'true',
        'show_thumbnail' => 'true',
        'show_description' => 'true',
        'exclude_filler' => 'false',
        'class' => '',
    ], $atts, 'cablecast_now_playing');

    // Validate channel
    $channel_id = absint($atts['channel']);
    if (!$channel_id) {
        return '<p class="cablecast-error">' . __('Please specify a channel ID.', 'cablecast') . '</p>';
    }

    $cablecast_channel_id = get_post_meta($channel_id, 'cablecast_channel_id', true);
    if (!$cablecast_channel_id) {
        return '<p class="cablecast-error">' . __('Invalid channel.', 'cablecast') . '</p>';
    }

    // Parse options
    $show_up_next = filter_var($atts['show_up_next'], FILTER_VALIDATE_BOOLEAN);
    $show_thumbnail = filter_var($atts['show_thumbnail'], FILTER_VALIDATE_BOOLEAN);
    $show_description = filter_var($atts['show_description'], FILTER_VALIDATE_BOOLEAN);
    $exclude_filler = filter_var($atts['exclude_filler'], FILTER_VALIDATE_BOOLEAN);

    // Get current time
    $timezone = get_option('timezone_string') ?: 'America/New_York';
    $now = new DateTime('now', new DateTimeZone($timezone));
    $now_timestamp = $now->getTimestamp();

    // Fetch schedule for today and tomorrow
    $date_start = $now->format('Y-m-d');
    $date_end = date('Y-m-d', strtotime($date_start . ' +2 days'));
    $items = cablecast_get_schedules($cablecast_channel_id, $date_start, $date_end);

    if (empty($items)) {
        return '<p class="cablecast-no-results">' . __('No schedule data available.', 'cablecast') . '</p>';
    }

    // Filter filler if requested
    if ($exclude_filler) {
        $items = array_filter($items, function($item) {
            return !cablecast_is_filler($item->show_title);
        });
        $items = array_values($items);
    }

    // Find current and next shows
    $current_show = null;
    $next_show = null;

    for ($i = 0; $i < count($items); $i++) {
        $item = $items[$i];
        $item_start = strtotime($item->run_date_time);

        // Get runtime from show meta if available
        $show = cablecast_get_show_from_schedule($item);
        $runtime = $show ? (int) get_post_meta($show->ID, 'cablecast_show_trt', true) : 0;
        $runtime = $runtime > 0 ? $runtime : 1800; // Default 30 minutes

        $item_end = $item_start + $runtime;

        if ($item_start <= $now_timestamp && $item_end > $now_timestamp) {
            $current_show = $item;
            $current_show->runtime = $runtime;
            $current_show->start_time = $item_start;
            $current_show->end_time = $item_end;

            // Find next show
            if (isset($items[$i + 1])) {
                $next_show = $items[$i + 1];
            }
            break;
        } elseif ($item_start > $now_timestamp && !$current_show) {
            // No current show, this is the next one
            $next_show = $item;
            break;
        }
    }

    // Build output
    $classes = ['cablecast-now-playing'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    // Current show card
    if ($current_show) {
        $output .= cablecast_render_now_playing_card($current_show, 'now', $show_thumbnail, $show_description);
    } else {
        $output .= '<div class="cablecast-now-playing__card cablecast-now-playing__card--now">';
        $output .= '<div class="cablecast-now-playing__badge">' . __('Now', 'cablecast') . '</div>';
        $output .= '<p class="cablecast-now-playing__no-show">' . __('No program currently airing.', 'cablecast') . '</p>';
        $output .= '</div>';
    }

    // Up next card
    if ($show_up_next && $next_show) {
        $output .= cablecast_render_now_playing_card($next_show, 'next', $show_thumbnail, $show_description);
    }

    $output .= '</div>';

    return $output;
}

/**
 * Render a now playing card.
 */
function cablecast_render_now_playing_card($item, $type, $show_thumbnail, $show_description) {
    $show = cablecast_get_show_from_schedule($item);
    $color = $show ? cablecast_get_show_category_color($show->ID) : null;

    $badge_class = $type === 'now' ? 'cablecast-now-playing__badge--live' : '';
    $badge_text = $type === 'now' ? __('Live Now', 'cablecast') : __('Up Next', 'cablecast');
    $time_text = date('g:i A', strtotime($item->run_date_time));

    $card_style = $color ? ' style="border-top-color: ' . esc_attr($color) . ';"' : '';

    $output = '<div class="cablecast-now-playing__card cablecast-now-playing__card--' . $type . '"' . $card_style . '>';
    $output .= '<div class="cablecast-now-playing__badge ' . $badge_class . '">' . $badge_text . '</div>';

    if ($show_thumbnail && $show) {
        $thumbnail_url = cablecast_show_thumbnail_url($show->ID, 'medium');
        if ($thumbnail_url) {
            $output .= '<div class="cablecast-now-playing__thumbnail">';
            $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($item->show_title) . '" loading="lazy" />';
            $output .= '</div>';
        }
    }

    $output .= '<div class="cablecast-now-playing__content">';

    // Title
    if ($show) {
        $output .= '<a href="' . get_permalink($show->ID) . '" class="cablecast-now-playing__title">';
        $output .= esc_html($item->show_title);
        $output .= '</a>';
    } else {
        $output .= '<span class="cablecast-now-playing__title">' . esc_html($item->show_title) . '</span>';
    }

    // Time
    $output .= '<span class="cablecast-now-playing__time">' . esc_html($time_text) . '</span>';

    // Progress bar for current show
    if ($type === 'now' && isset($item->start_time) && isset($item->end_time)) {
        $total_duration = $item->end_time - $item->start_time;
        $elapsed = time() - $item->start_time;
        $progress = min(100, max(0, ($elapsed / $total_duration) * 100));

        $output .= '<div class="cablecast-now-playing__progress">';
        $output .= '<div class="cablecast-now-playing__progress-bar" style="width: ' . $progress . '%;"></div>';
        $output .= '</div>';
    }

    // Description
    if ($show_description && $show) {
        $description = get_post_meta($show->ID, 'cablecast_show_comments', true);
        if ($description) {
            $output .= '<p class="cablecast-now-playing__description">' . esc_html(wp_trim_words($description, 15)) . '</p>';
        }
    }

    $output .= '</div>'; // content
    $output .= '</div>'; // card

    return $output;
}

// ============================================================================
// WEEKLY GUIDE SHORTCODE
// ============================================================================

/**
 * [cablecast_weekly_guide] - Display a 7-day schedule grid.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_weekly_guide_shortcode($atts) {
    cablecast_mark_shortcode_used('weekly_guide');

    $atts = shortcode_atts([
        'channel' => '',
        'days' => 7,
        'show_channel_switcher' => 'true',
        'show_category_colors' => 'true',
        'show_descriptions' => 'false',
        'class' => '',
    ], $atts, 'cablecast_weekly_guide');

    // Get channel from URL param or attribute
    $channel_id = absint($atts['channel']);
    if (!$channel_id && isset($_GET['channel'])) {
        $channel_id = absint($_GET['channel']);
    }

    // If still no channel, get the first one
    $channels = cablecast_get_all_channels();
    if (!$channel_id && !empty($channels)) {
        $channel_id = $channels[0]->ID;
    }

    if (!$channel_id) {
        return '<p class="cablecast-error">' . __('No channels available.', 'cablecast') . '</p>';
    }

    $cablecast_channel_id = get_post_meta($channel_id, 'cablecast_channel_id', true);
    if (!$cablecast_channel_id) {
        return '<p class="cablecast-error">' . __('Invalid channel.', 'cablecast') . '</p>';
    }

    // Parse options
    $days = min(14, max(1, absint($atts['days'])));
    $show_channel_switcher = filter_var($atts['show_channel_switcher'], FILTER_VALIDATE_BOOLEAN);
    $show_category_colors = filter_var($atts['show_category_colors'], FILTER_VALIDATE_BOOLEAN);
    $show_descriptions = filter_var($atts['show_descriptions'], FILTER_VALIDATE_BOOLEAN);

    // Get current time
    $timezone = get_option('timezone_string') ?: 'America/New_York';
    $now = new DateTime('now', new DateTimeZone($timezone));

    // Fetch schedule for date range - SINGLE DATABASE QUERY
    $date_start = $now->format('Y-m-d');
    $date_end = date('Y-m-d', strtotime($date_start . ' +' . $days . ' days'));
    $items = cablecast_get_schedules($cablecast_channel_id, $date_start, $date_end);

    // Group items by date
    $schedule_by_date = [];
    foreach ($items as $item) {
        $date = date('Y-m-d', strtotime($item->run_date_time));
        if (!isset($schedule_by_date[$date])) {
            $schedule_by_date[$date] = [];
        }
        $schedule_by_date[$date][] = $item;
    }

    // Build output
    $classes = ['cablecast-weekly-guide'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    // Channel switcher
    if ($show_channel_switcher && count($channels) > 1) {
        $current_url = remove_query_arg('channel');
        $output .= '<div class="cablecast-weekly-guide__channel-switcher">';
        $output .= '<label for="cablecast-channel-select">' . __('Channel:', 'cablecast') . '</label>';
        $output .= '<select id="cablecast-channel-select" onchange="window.location.href=\'' . esc_url($current_url) . '&channel=\' + this.value">';
        foreach ($channels as $channel) {
            $selected = ($channel->ID === $channel_id) ? ' selected' : '';
            $output .= '<option value="' . esc_attr($channel->ID) . '"' . $selected . '>';
            $output .= esc_html($channel->post_title);
            $output .= '</option>';
        }
        $output .= '</select>';
        $output .= '</div>';
    }

    // Day columns
    $output .= '<div class="cablecast-weekly-guide__grid">';

    for ($d = 0; $d < $days; $d++) {
        $date = date('Y-m-d', strtotime($date_start . ' +' . $d . ' days'));
        $day_items = isset($schedule_by_date[$date]) ? $schedule_by_date[$date] : [];

        $is_today = ($date === $now->format('Y-m-d'));
        $day_class = $is_today ? 'cablecast-weekly-guide__day cablecast-weekly-guide__day--today' : 'cablecast-weekly-guide__day';

        $output .= '<div class="' . $day_class . '">';

        // Day header
        $day_label = date('D', strtotime($date));
        $date_label = date('M j', strtotime($date));
        if ($is_today) {
            $day_label = __('Today', 'cablecast');
        }

        $output .= '<div class="cablecast-weekly-guide__day-header">';
        $output .= '<span class="cablecast-weekly-guide__day-name">' . esc_html($day_label) . '</span>';
        $output .= '<span class="cablecast-weekly-guide__day-date">' . esc_html($date_label) . '</span>';
        $output .= '</div>';

        // Programs
        $output .= '<div class="cablecast-weekly-guide__programs">';

        if (empty($day_items)) {
            $output .= '<div class="cablecast-weekly-guide__no-programs">';
            $output .= __('No programs', 'cablecast');
            $output .= '</div>';
        } else {
            foreach ($day_items as $item) {
                $show = cablecast_get_show_from_schedule($item);
                $item_time = date('g:i A', strtotime($item->run_date_time));

                // Determine if this is the current program
                $item_timestamp = strtotime($item->run_date_time);
                $is_current = false;
                if ($is_today) {
                    $runtime = $show ? (int) get_post_meta($show->ID, 'cablecast_show_trt', true) : 1800;
                    $runtime = $runtime > 0 ? $runtime : 1800;
                    $item_end = $item_timestamp + $runtime;
                    $is_current = ($item_timestamp <= $now->getTimestamp() && $item_end > $now->getTimestamp());
                }

                $program_class = 'cablecast-weekly-guide__program';
                if ($is_current) {
                    $program_class .= ' cablecast-weekly-guide__program--current';
                }

                // Category color
                $style = '';
                if ($show_category_colors && $show) {
                    $color = cablecast_get_show_category_color($show->ID);
                    if ($color) {
                        $style = ' style="border-left-color: ' . esc_attr($color) . ';"';
                    }
                }

                $output .= '<div class="' . $program_class . '"' . $style . '>';
                $output .= '<span class="cablecast-weekly-guide__program-time">' . esc_html($item_time) . '</span>';

                if ($show) {
                    $output .= '<a href="' . get_permalink($show->ID) . '" class="cablecast-weekly-guide__program-title">';
                    $output .= esc_html($item->show_title);
                    $output .= '</a>';
                } else {
                    $output .= '<span class="cablecast-weekly-guide__program-title">' . esc_html($item->show_title) . '</span>';
                }

                if ($show_descriptions && $show) {
                    $description = get_post_meta($show->ID, 'cablecast_show_comments', true);
                    if ($description) {
                        $output .= '<p class="cablecast-weekly-guide__program-desc">' . esc_html(wp_trim_words($description, 10)) . '</p>';
                    }
                }

                $output .= '</div>';
            }
        }

        $output .= '</div>'; // programs
        $output .= '</div>'; // day
    }

    $output .= '</div>'; // grid
    $output .= '</div>'; // weekly-guide

    return $output;
}

// ============================================================================
// SHOWS SHORTCODE
// ============================================================================

/**
 * [cablecast_shows] - Display a grid/list of shows.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_shows_shortcode($atts) {
    cablecast_mark_shortcode_used('shows');

    $atts = shortcode_atts([
        'category' => '',
        'producer' => '',
        'series' => '',
        'count' => 12,
        'orderby' => 'date',
        'order' => 'DESC',
        'layout' => 'grid',
        'columns' => 4,
        'show_pagination' => 'false',
        'class' => '',
    ], $atts, 'cablecast_shows');

    // Build query args
    $query_args = [
        'post_type' => 'show',
        'posts_per_page' => absint($atts['count']) ?: 12,
        'orderby' => in_array($atts['orderby'], ['title', 'date', 'event_date']) ? $atts['orderby'] : 'date',
        'order' => in_array(strtoupper($atts['order']), ['ASC', 'DESC']) ? strtoupper($atts['order']) : 'DESC',
    ];

    // Handle event_date ordering
    if ($atts['orderby'] === 'event_date') {
        $query_args['meta_key'] = 'cablecast_show_event_date';
        $query_args['orderby'] = 'meta_value';
    }

    // Tax queries
    $tax_query = [];

    if (!empty($atts['category'])) {
        $tax_query[] = [
            'taxonomy' => 'category',
            'field' => is_numeric($atts['category']) ? 'term_id' : 'slug',
            'terms' => $atts['category'],
        ];
    }

    if (!empty($atts['producer'])) {
        $tax_query[] = [
            'taxonomy' => 'cablecast_producer',
            'field' => is_numeric($atts['producer']) ? 'term_id' : 'slug',
            'terms' => $atts['producer'],
        ];
    }

    if (!empty($atts['series'])) {
        $tax_query[] = [
            'taxonomy' => 'cablecast_project',
            'field' => is_numeric($atts['series']) ? 'term_id' : 'slug',
            'terms' => $atts['series'],
        ];
    }

    if (!empty($tax_query)) {
        $query_args['tax_query'] = $tax_query;
    }

    // Pagination
    $show_pagination = filter_var($atts['show_pagination'], FILTER_VALIDATE_BOOLEAN);
    if ($show_pagination) {
        $query_args['paged'] = get_query_var('paged') ?: 1;
    }

    $query = new WP_Query($query_args);

    if (!$query->have_posts()) {
        return '<p class="cablecast-no-results">' . __('No shows found.', 'cablecast') . '</p>';
    }

    // Build output
    $layout = in_array($atts['layout'], ['grid', 'list', 'featured']) ? $atts['layout'] : 'grid';
    $columns = min(6, max(2, absint($atts['columns'])));

    $classes = ['cablecast-shows', 'cablecast-shows--' . $layout];
    if ($layout === 'grid' || $layout === 'featured') {
        $classes[] = 'cablecast-shows--columns-' . $columns;
    }
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    $item_index = 0;
    while ($query->have_posts()) {
        $query->the_post();
        $show_id = get_the_ID();

        // Featured layout: first item is large
        $item_classes = ['cablecast-shows__item'];
        if ($layout === 'featured' && $item_index === 0) {
            $item_classes[] = 'cablecast-shows__item--featured';
        }
        $item_index++;

        $output .= '<div class="' . implode(' ', $item_classes) . '">';

        // Thumbnail - use larger size for featured item
        $is_featured_item = ($layout === 'featured' && $item_index === 1);
        $thumb_size = $is_featured_item ? 'large' : 'medium';
        $thumbnail_url = cablecast_show_thumbnail_url($show_id, $thumb_size);
        if ($thumbnail_url) {
            $output .= '<a href="' . get_permalink() . '" class="cablecast-shows__thumbnail">';
            $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr(get_the_title()) . '" loading="lazy" />';

            // Runtime badge overlay
            $runtime = (int) get_post_meta($show_id, 'cablecast_show_trt', true);
            if ($runtime > 0) {
                $output .= '<span class="cablecast-shows__runtime-badge">' . cablecast_format_runtime($runtime) . '</span>';
            }

            $output .= '</a>';
        }

        $output .= '<div class="cablecast-shows__content">';
        $output .= '<a href="' . get_permalink() . '" class="cablecast-shows__title">' . esc_html(get_the_title()) . '</a>';

        // Runtime (text version, for list layout)
        $runtime = (int) get_post_meta($show_id, 'cablecast_show_trt', true);
        if ($runtime > 0 && $layout === 'list') {
            $output .= '<span class="cablecast-shows__runtime">' . cablecast_format_runtime($runtime) . '</span>';
        }

        // Category tag
        $categories = get_the_terms($show_id, 'category');
        if ($categories && !is_wp_error($categories)) {
            $cat = $categories[0];
            $color = cablecast_get_show_category_color($show_id);
            $style = $color ? ' style="border-color: ' . esc_attr($color) . ';"' : '';
            $output .= '<a href="' . get_term_link($cat) . '" class="cablecast-shows__category"' . $style . '>';
            $output .= esc_html($cat->name);
            $output .= '</a>';
        }

        $output .= '</div>'; // content
        $output .= '</div>'; // item
    }

    $output .= '</div>'; // shows

    // Pagination
    if ($show_pagination && $query->max_num_pages > 1) {
        $output .= '<div class="cablecast-shows__pagination">';
        $output .= paginate_links([
            'total' => $query->max_num_pages,
            'current' => max(1, get_query_var('paged')),
        ]);
        $output .= '</div>';
    }

    wp_reset_postdata();

    return $output;
}

// ============================================================================
// SINGLE SHOW SHORTCODE
// ============================================================================

/**
 * [cablecast_show] - Display a single show.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_show_shortcode($atts) {
    cablecast_mark_shortcode_used('show');

    $atts = shortcode_atts([
        'id' => '',
        'show_vod' => 'true',
        'show_thumbnail' => 'true',
        'show_meta' => 'true',
        'show_description' => 'true',
        'class' => '',
    ], $atts, 'cablecast_show');

    $show_id = absint($atts['id']);
    if (!$show_id) {
        return '<p class="cablecast-error">' . __('Please specify a show ID.', 'cablecast') . '</p>';
    }

    $show = get_post($show_id);
    if (!$show || $show->post_type !== 'show') {
        return '<p class="cablecast-error">' . __('Show not found.', 'cablecast') . '</p>';
    }

    // Parse options
    $show_vod = filter_var($atts['show_vod'], FILTER_VALIDATE_BOOLEAN);
    $show_thumbnail = filter_var($atts['show_thumbnail'], FILTER_VALIDATE_BOOLEAN);
    $show_meta = filter_var($atts['show_meta'], FILTER_VALIDATE_BOOLEAN);
    $show_description = filter_var($atts['show_description'], FILTER_VALIDATE_BOOLEAN);

    // Build output
    $classes = ['cablecast-show'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    // VOD Player
    if ($show_vod) {
        $vod_embed = get_post_meta($show_id, 'cablecast_vod_embed', true);
        if ($vod_embed) {
            $output .= '<div class="cablecast-show__vod">' . $vod_embed . '</div>';
        }
    }

    // Thumbnail (if no VOD)
    if ($show_thumbnail && !$show_vod) {
        $thumbnail_url = cablecast_show_thumbnail_url($show_id, 'large');
        if ($thumbnail_url) {
            $output .= '<div class="cablecast-show__thumbnail">';
            $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($show->post_title) . '" />';
            $output .= '</div>';
        }
    }

    $output .= '<div class="cablecast-show__content">';

    // Title
    $output .= '<h2 class="cablecast-show__title">' . esc_html($show->post_title) . '</h2>';

    // Meta
    if ($show_meta) {
        $output .= '<div class="cablecast-show__meta">';

        // Producer
        $producers = get_the_terms($show_id, 'cablecast_producer');
        if ($producers && !is_wp_error($producers)) {
            $output .= '<span class="cablecast-show__producer">';
            $output .= __('By', 'cablecast') . ' ';
            $producer_links = [];
            foreach ($producers as $producer) {
                $producer_links[] = '<a href="' . get_term_link($producer) . '">' . esc_html($producer->name) . '</a>';
            }
            $output .= implode(', ', $producer_links);
            $output .= '</span>';
        }

        // Runtime
        $runtime = (int) get_post_meta($show_id, 'cablecast_show_trt', true);
        if ($runtime > 0) {
            $output .= '<span class="cablecast-show__runtime">' . cablecast_format_runtime($runtime) . '</span>';
        }

        // Category
        $categories = get_the_terms($show_id, 'category');
        if ($categories && !is_wp_error($categories)) {
            $output .= '<span class="cablecast-show__category">';
            $cat_links = [];
            foreach ($categories as $cat) {
                $cat_links[] = '<a href="' . get_term_link($cat) . '">' . esc_html($cat->name) . '</a>';
            }
            $output .= implode(', ', $cat_links);
            $output .= '</span>';
        }

        $output .= '</div>'; // meta
    }

    // Description
    if ($show_description) {
        $description = get_post_meta($show_id, 'cablecast_show_comments', true);
        if ($description) {
            $output .= '<div class="cablecast-show__description">' . wpautop(esc_html($description)) . '</div>';
        }
    }

    $output .= '</div>'; // content
    $output .= '</div>'; // show

    return $output;
}

// ============================================================================
// VOD PLAYER SHORTCODE
// ============================================================================

/**
 * [cablecast_vod_player] - Display just the VOD player for a show.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_vod_player_shortcode($atts) {
    cablecast_mark_shortcode_used('vod_player');

    $atts = shortcode_atts([
        'id' => '',
        'poster' => 'true',
        'class' => '',
    ], $atts, 'cablecast_vod_player');

    $show_id = absint($atts['id']);
    if (!$show_id) {
        return '<p class="cablecast-error">' . __('Please specify a show ID.', 'cablecast') . '</p>';
    }

    $show = get_post($show_id);
    if (!$show || $show->post_type !== 'show') {
        return '<p class="cablecast-error">' . __('Show not found.', 'cablecast') . '</p>';
    }

    $vod_embed = get_post_meta($show_id, 'cablecast_vod_embed', true);
    if (!$vod_embed) {
        return '<p class="cablecast-no-results">' . __('No video available for this show.', 'cablecast') . '</p>';
    }

    $classes = ['cablecast-vod-player'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    return '<div class="' . implode(' ', $classes) . '">' . $vod_embed . '</div>';
}

// ============================================================================
// CHAPTERS SHORTCODE
// ============================================================================

/**
 * [cablecast_chapters] - Display interactive chapters for a show's VOD.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_chapters_shortcode($atts) {
    cablecast_mark_shortcode_used('chapters');

    $atts = shortcode_atts([
        'id' => '',                    // Show post ID (optional, defaults to current post)
        'player' => '',                // Target player element selector (for multiple players)
        'show_descriptions' => 'true', // Show chapter descriptions
        'show_timestamps' => 'true',   // Show formatted timestamps
        'layout' => 'list',            // list or compact
        'class' => '',                 // Additional CSS class
    ], $atts, 'cablecast_chapters');

    // Determine show ID
    $show_id = absint($atts['id']);
    if (!$show_id) {
        // Try to get from current post context
        $show_id = cablecast_current_show_post_id();
    }

    if (!$show_id) {
        return '<p class="cablecast-error">' . __('Please specify a show ID or use within a show context.', 'cablecast') . '</p>';
    }

    $show = get_post($show_id);
    if (!$show || $show->post_type !== 'show') {
        return '<p class="cablecast-error">' . __('Show not found.', 'cablecast') . '</p>';
    }

    // Check if show has VOD
    $vod_embed = get_post_meta($show_id, 'cablecast_vod_embed', true);
    if (!$vod_embed) {
        return ''; // Silent fail - no VOD means no chapters to display
    }

    // Get chapters
    $chapters = get_post_meta($show_id, 'cablecast_vod_chapters', true);
    if (empty($chapters)) {
        return ''; // Silent fail - no chapters available
    }

    // Ensure chapters is an array (handles both serialized and unserialized cases)
    if (is_string($chapters)) {
        $chapters = maybe_unserialize($chapters);
    }

    if (!is_array($chapters) || empty($chapters)) {
        return '';
    }

    // Parse options
    $show_descriptions = filter_var($atts['show_descriptions'], FILTER_VALIDATE_BOOLEAN);
    $show_timestamps = filter_var($atts['show_timestamps'], FILTER_VALIDATE_BOOLEAN);
    $layout = in_array($atts['layout'], ['list', 'compact']) ? $atts['layout'] : 'list';
    $player_selector = sanitize_text_field($atts['player']);

    // Build output
    $classes = ['cablecast-chapters', 'cablecast-chapters--' . $layout];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    // Data attributes for JS
    $data_attrs = ' data-show-id="' . esc_attr($show_id) . '"';
    if ($player_selector) {
        $data_attrs .= ' data-player-selector="' . esc_attr($player_selector) . '"';
    }

    $output = '<div class="' . implode(' ', $classes) . '"' . $data_attrs . '>';

    $output .= '<h3 class="cablecast-chapters__heading">' . __('Chapters', 'cablecast') . '</h3>';
    $output .= '<ul class="cablecast-chapters__list">';

    foreach ($chapters as $index => $chapter) {
        $offset = (int) $chapter['offset'];
        $timestamp = cablecast_format_chapter_timestamp($offset);

        $item_class = 'cablecast-chapters__item';

        $output .= '<li class="' . $item_class . '" data-offset="' . esc_attr($offset) . '">';

        $output .= '<button type="button" class="cablecast-chapters__button">';

        if ($show_timestamps) {
            $output .= '<span class="cablecast-chapters__timestamp">' . esc_html($timestamp) . '</span>';
        }

        $output .= '<span class="cablecast-chapters__title">' . esc_html($chapter['title']) . '</span>';

        $output .= '</button>';

        if ($show_descriptions && !empty($chapter['body'])) {
            $output .= '<p class="cablecast-chapters__description">' . esc_html($chapter['body']) . '</p>';
        }

        $output .= '</li>';
    }

    $output .= '</ul>';
    $output .= '</div>';

    return $output;
}

/**
 * Format seconds to HH:MM:SS or MM:SS timestamp.
 *
 * @param int $seconds Total seconds
 * @return string Formatted timestamp
 */
function cablecast_format_chapter_timestamp($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }

    return sprintf('%d:%02d', $minutes, $secs);
}

// ============================================================================
// PRODUCERS SHORTCODE
// ============================================================================

/**
 * [cablecast_producers] - Display a list of producers.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_producers_shortcode($atts) {
    cablecast_mark_shortcode_used('producers');

    $atts = shortcode_atts([
        'count' => 0,
        'orderby' => 'name',
        'layout' => 'list',
        'show_contact' => 'false',
        'class' => '',
    ], $atts, 'cablecast_producers');

    $terms = get_terms([
        'taxonomy' => 'cablecast_producer',
        'hide_empty' => true,
        'number' => absint($atts['count']) ?: 0,
        'orderby' => in_array($atts['orderby'], ['name', 'count']) ? $atts['orderby'] : 'name',
        'order' => $atts['orderby'] === 'count' ? 'DESC' : 'ASC',
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return '<p class="cablecast-no-results">' . __('No producers found.', 'cablecast') . '</p>';
    }

    $layout = in_array($atts['layout'], ['grid', 'list']) ? $atts['layout'] : 'list';
    $show_contact = filter_var($atts['show_contact'], FILTER_VALIDATE_BOOLEAN);

    $classes = ['cablecast-producers', 'cablecast-producers--' . $layout];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    foreach ($terms as $term) {
        $output .= '<div class="cablecast-producers__item">';
        $output .= '<a href="' . get_term_link($term) . '" class="cablecast-producers__name">';
        $output .= esc_html($term->name);
        $output .= '</a>';

        $output .= '<span class="cablecast-producers__count">' . sprintf(_n('%d show', '%d shows', $term->count, 'cablecast'), $term->count) . '</span>';

        if ($show_contact) {
            $email = get_term_meta($term->term_id, 'cablecast_producer_email', true);
            $website = get_term_meta($term->term_id, 'cablecast_producer_website', true);

            if ($email || $website) {
                $output .= '<div class="cablecast-producers__contact">';
                if ($email) {
                    $output .= '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                }
                if ($website) {
                    $output .= '<a href="' . esc_url($website) . '" target="_blank">' . esc_html($website) . '</a>';
                }
                $output .= '</div>';
            }
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

// ============================================================================
// SERIES SHORTCODE
// ============================================================================

/**
 * [cablecast_series] - Display a list of series/projects.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_series_shortcode($atts) {
    cablecast_mark_shortcode_used('series');

    $atts = shortcode_atts([
        'count' => 0,
        'orderby' => 'name',
        'layout' => 'grid',
        'show_thumbnails' => 'true',
        'class' => '',
    ], $atts, 'cablecast_series');

    $terms = get_terms([
        'taxonomy' => 'cablecast_project',
        'hide_empty' => true,
        'number' => absint($atts['count']) ?: 0,
        'orderby' => in_array($atts['orderby'], ['name', 'count']) ? $atts['orderby'] : 'name',
        'order' => $atts['orderby'] === 'count' ? 'DESC' : 'ASC',
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return '<p class="cablecast-no-results">' . __('No series found.', 'cablecast') . '</p>';
    }

    $layout = in_array($atts['layout'], ['grid', 'list']) ? $atts['layout'] : 'grid';
    $show_thumbnails = filter_var($atts['show_thumbnails'], FILTER_VALIDATE_BOOLEAN);

    $classes = ['cablecast-series', 'cablecast-series--' . $layout];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    foreach ($terms as $term) {
        $output .= '<div class="cablecast-series__item">';

        // Get a thumbnail from a show in this series
        // Try multiple shows to find one with a valid thumbnail
        if ($show_thumbnails) {
            $shows = get_posts([
                'post_type' => 'show',
                'posts_per_page' => 5, // Try up to 5 shows
                'tax_query' => [[
                    'taxonomy' => 'cablecast_project',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ]],
            ]);

            $thumbnail_url = '';
            foreach ($shows as $show) {
                $thumbnail_url = cablecast_show_thumbnail_url($show->ID, 'medium');
                if ($thumbnail_url) {
                    break; // Found a valid thumbnail
                }
            }

            if ($thumbnail_url) {
                $output .= '<a href="' . get_term_link($term) . '" class="cablecast-series__thumbnail">';
                $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($term->name) . '" loading="lazy" />';
                $output .= '</a>';
            }
        }

        $output .= '<div class="cablecast-series__content">';
        $output .= '<a href="' . get_term_link($term) . '" class="cablecast-series__name">';
        $output .= esc_html($term->name);
        $output .= '</a>';
        $output .= '<span class="cablecast-series__count">' . sprintf(_n('%d episode', '%d episodes', $term->count, 'cablecast'), $term->count) . '</span>';
        $output .= '</div>';

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

// ============================================================================
// SCHEDULE CALENDAR SHORTCODE (FullCalendar)
// ============================================================================

/**
 * AJAX handler for FullCalendar events.
 */
function cablecast_calendar_events_ajax() {
    check_ajax_referer('cablecast_calendar_nonce', 'nonce');

    $channel_id = intval($_GET['channel_id']);
    $start = sanitize_text_field($_GET['start']);
    $end = sanitize_text_field($_GET['end']);

    // Get schedule items from database
    $items = cablecast_get_schedules($channel_id, $start, $end);

    // Get category colors
    $options = get_option('cablecast_options');
    $category_colors = isset($options['category_colors']) ? $options['category_colors'] : [];

    // Convert to FullCalendar event format
    $events = [];
    foreach ($items as $item) {
        $show = cablecast_get_show_from_schedule($item);
        $show_url = $show ? get_permalink($show) : '';

        // Get show category for color
        $color = '#3788d8'; // Default blue
        if ($show) {
            $categories = get_the_terms($show->ID, 'category');
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    if (isset($category_colors[$cat->slug])) {
                        $color = $category_colors[$cat->slug];
                        break;
                    }
                }
            }
        }

        // Calculate end time (default 30 min if no runtime)
        $start_time = strtotime($item->run_date_time);
        $runtime = $show ? (int) get_post_meta($show->ID, 'cablecast_show_trt', true) : 0;
        $end_time = $start_time + ($runtime > 0 ? $runtime : 1800);

        $events[] = [
            'id' => $item->schedule_item_id,
            'title' => $item->show_title,
            'start' => date('c', $start_time),
            'end' => date('c', $end_time),
            'url' => $show_url,
            'backgroundColor' => $color,
            'borderColor' => $color,
        ];
    }

    wp_send_json($events);
}

/**
 * [cablecast_schedule_calendar] - Display schedule using FullCalendar.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_schedule_calendar_shortcode($atts) {
    cablecast_mark_shortcode_used('schedule_calendar');

    $atts = shortcode_atts([
        'channel' => '',
        'view' => 'timeGridWeek',
        'height' => 'auto',
        'header' => 'true',
        'nav' => 'true',
        'show_category_colors' => 'true',
        'class' => '',
    ], $atts, 'cablecast_schedule_calendar');

    // Validate channel
    $channel_id = absint($atts['channel']);
    if (!$channel_id) {
        return '<p class="cablecast-error">' . __('Please specify a channel ID.', 'cablecast') . '</p>';
    }

    // Get Cablecast channel ID from post meta
    $cablecast_channel_id = get_post_meta($channel_id, 'cablecast_channel_id', true);
    if (!$cablecast_channel_id) {
        return '<p class="cablecast-error">' . __('Invalid channel.', 'cablecast') . '</p>';
    }

    // Enqueue FullCalendar from CDN
    wp_enqueue_script(
        'fullcalendar',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js',
        [],
        '6.1.11',
        true
    );

    wp_enqueue_script(
        'cablecast-fullcalendar',
        plugins_url('../assets/js/fullcalendar-shortcode.js', __FILE__),
        ['fullcalendar'],
        filemtime(plugin_dir_path(__FILE__) . '../assets/js/fullcalendar-shortcode.js'),
        true
    );

    wp_enqueue_style(
        'cablecast-fullcalendar',
        plugins_url('../assets/css/fullcalendar-shortcode.css', __FILE__),
        [],
        filemtime(plugin_dir_path(__FILE__) . '../assets/css/fullcalendar-shortcode.css')
    );

    // Generate unique ID for this calendar instance
    $calendar_id = 'cablecast-calendar-' . uniqid();

    // Parse boolean options
    $show_header = filter_var($atts['header'], FILTER_VALIDATE_BOOLEAN);
    $show_nav = filter_var($atts['nav'], FILTER_VALIDATE_BOOLEAN);

    // Build config for JS
    $config = [
        'calendarId' => $calendar_id,
        'channelId' => $cablecast_channel_id,
        'initialView' => $atts['view'],
        'height' => $atts['height'],
        'showHeader' => $show_header,
        'showNav' => $show_nav,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cablecast_calendar_nonce'),
    ];

    // Inline the config
    wp_add_inline_script(
        'cablecast-fullcalendar',
        'window.cablecastCalendars = window.cablecastCalendars || []; window.cablecastCalendars.push(' . wp_json_encode($config) . ');',
        'before'
    );

    // Build output
    $classes = ['cablecast-fullcalendar'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    return '<div id="' . esc_attr($calendar_id) . '" class="' . implode(' ', $classes) . '"></div>';
}

// ============================================================================
// UPCOMING RUNS SHORTCODE
// ============================================================================

/**
 * [cablecast_upcoming_runs] - Display upcoming airings for a show across all channels.
 *
 * Shows a list of upcoming scheduled airings for a specific show,
 * displaying the date, time, and channel for each airing.
 *
 * @param array $atts Shortcode attributes:
 *   - id: Show post ID (optional, defaults to current post)
 *   - count: Number of upcoming runs to display (default: 5)
 *   - show_channel: Display channel name with link (default: true)
 *   - show_date: Display date and time (default: true)
 *   - days_ahead: How many days ahead to look (default: 14)
 *   - class: Additional CSS class
 * @return string HTML output
 */
function cablecast_upcoming_runs_shortcode($atts) {
    cablecast_mark_shortcode_used('upcoming_runs');

    $atts = shortcode_atts([
        'id'           => '',
        'count'        => 5,
        'show_channel' => 'true',
        'show_date'    => 'true',
        'days_ahead'   => 14,
        'class'        => '',
    ], $atts, 'cablecast_upcoming_runs');

    // Determine show ID
    $show_id = absint($atts['id']);
    if (!$show_id) {
        $show_id = cablecast_current_show_post_id();
    }

    if (!$show_id) {
        return ''; // Silent fail - no context
    }

    // Verify it's a show post
    $post = get_post($show_id);
    if (!$post || $post->post_type !== 'show') {
        return '';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cablecast_schedule_items';

    // Get timezone
    $timezone = get_option('timezone_string');
    if (empty($timezone)) {
        $timezone = 'UTC';
    }

    // Calculate date range in UTC
    try {
        $now = new DateTime('now', new DateTimeZone($timezone));
        $now->setTimezone(new DateTimeZone('UTC'));
        $now_utc = $now->format('Y-m-d H:i:s');

        $end = new DateTime('now', new DateTimeZone($timezone));
        $end->modify('+' . absint($atts['days_ahead']) . ' days');
        $end->setTimezone(new DateTimeZone('UTC'));
        $end_utc = $end->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return '';
    }

    $count = absint($atts['count']) ?: 5;

    /**
     * Filter the query arguments for upcoming runs.
     *
     * @param array $args Query parameters.
     * @param int   $show_id The show post ID.
     */
    $query_args = apply_filters('cablecast_upcoming_runs_args', [
        'show_post_id' => $show_id,
        'start_utc'    => $now_utc,
        'end_utc'      => $end_utc,
        'count'        => $count,
    ], $show_id);

    // Query upcoming runs
    $runs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table
         WHERE show_post_id = %d
         AND cg_exempt = 0
         AND run_date_time >= %s
         AND run_date_time < %s
         ORDER BY run_date_time ASC
         LIMIT %d",
        $query_args['show_post_id'],
        $query_args['start_utc'],
        $query_args['end_utc'],
        $query_args['count']
    ));

    if (empty($runs)) {
        return ''; // Silent fail - no upcoming runs
    }

    // Parse boolean options
    $show_channel = filter_var($atts['show_channel'], FILTER_VALIDATE_BOOLEAN);
    $show_date = filter_var($atts['show_date'], FILTER_VALIDATE_BOOLEAN);

    // Build output
    $classes = ['cablecast-upcoming-runs'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    /**
     * Action before upcoming runs heading.
     *
     * @param int   $show_id The show post ID.
     * @param array $runs    The array of run objects.
     */
    do_action('cablecast_before_upcoming_runs_heading', $show_id, $runs);

    $output .= '<h3 class="cablecast-upcoming-runs__heading">' . esc_html__('Upcoming Airings', 'cablecast') . '</h3>';

    /**
     * Action after upcoming runs heading.
     *
     * @param int   $show_id The show post ID.
     * @param array $runs    The array of run objects.
     */
    do_action('cablecast_after_upcoming_runs_heading', $show_id, $runs);

    $output .= '<ul class="cablecast-upcoming-runs__list">';

    foreach ($runs as $run) {
        // Convert UTC time to local timezone for display
        try {
            $run_utc = new DateTime($run->run_date_time, new DateTimeZone('UTC'));
            $run_utc->setTimezone(new DateTimeZone($timezone));
            $run_local = $run_utc;
        } catch (Exception $e) {
            continue;
        }

        $channel = get_post($run->channel_post_id);

        $output .= '<li class="cablecast-upcoming-runs__item">';

        if ($show_date) {
            $date_str = $run_local->format('l, F j'); // e.g., "Monday, January 15"
            $time_str = $run_local->format('g:i A');   // e.g., "7:00 PM"
            $output .= '<span class="cablecast-upcoming-runs__date">' . esc_html($date_str) . '</span>';
            $output .= '<span class="cablecast-upcoming-runs__time">' . esc_html($time_str) . '</span>';
        }

        if ($show_channel && $channel) {
            $channel_url = get_permalink($channel->ID);
            $output .= '<a href="' . esc_url($channel_url) . '" class="cablecast-upcoming-runs__channel">';
            $output .= esc_html($channel->post_title);
            $output .= '</a>';
        }

        $output .= '</li>';
    }

    $output .= '</ul>';

    /**
     * Action after upcoming runs list.
     *
     * @param int   $show_id The show post ID.
     * @param array $runs    The array of run objects.
     */
    do_action('cablecast_after_upcoming_runs_list', $show_id, $runs);

    $output .= '</div>';

    return $output;
}

// ============================================================================
// CATEGORIES SHORTCODE
// ============================================================================

/**
 * [cablecast_categories] - Display categories that have shows.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_categories_shortcode($atts) {
    cablecast_mark_shortcode_used('categories');

    $atts = shortcode_atts([
        'layout'      => 'cloud',  // cloud, grid, list
        'show_colors' => 'true',
        'show_counts' => 'true',
        'count'       => 0,        // 0 = all
        'class'       => '',
    ], $atts, 'cablecast_categories');

    // Get categories that have shows
    $categories = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'number'     => absint($atts['count']) ?: 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    if (empty($categories) || is_wp_error($categories)) {
        return '';
    }

    // Filter to only categories with shows
    $categories_with_shows = [];
    foreach ($categories as $cat) {
        $show_count = new WP_Query([
            'post_type'      => 'show',
            'tax_query'      => [[
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $cat->term_id,
            ]],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]);

        if ($show_count->found_posts > 0) {
            $cat->show_count = $show_count->found_posts;
            $categories_with_shows[] = $cat;
        }
        wp_reset_postdata();
    }

    if (empty($categories_with_shows)) {
        return '';
    }

    // Parse options
    $layout = in_array($atts['layout'], ['cloud', 'grid', 'list']) ? $atts['layout'] : 'cloud';
    $show_colors = filter_var($atts['show_colors'], FILTER_VALIDATE_BOOLEAN);
    $show_counts = filter_var($atts['show_counts'], FILTER_VALIDATE_BOOLEAN);

    // Get category colors from settings
    $options = get_option('cablecast_options');
    $category_colors = isset($options['category_colors']) ? $options['category_colors'] : [];

    // Build output
    $classes = ['cablecast-categories', 'cablecast-categories--' . $layout];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    foreach ($categories_with_shows as $cat) {
        $color = isset($category_colors[$cat->slug]) ? $category_colors[$cat->slug] : null;

        $item_style = '';
        if ($show_colors && $color) {
            if ($layout === 'cloud') {
                $item_style = ' style="background-color: ' . esc_attr($color) . '20; border-color: ' . esc_attr($color) . '; color: ' . esc_attr($color) . ';"';
            } else {
                $item_style = ' style="border-left-color: ' . esc_attr($color) . ';"';
            }
        }

        $output .= '<a href="' . get_term_link($cat) . '" class="cablecast-categories__item"' . $item_style . '>';

        if ($show_colors && $color && $layout !== 'cloud') {
            $output .= '<span class="cablecast-categories__color" style="background-color: ' . esc_attr($color) . ';"></span>';
        }

        $output .= '<span class="cablecast-categories__name">' . esc_html($cat->name) . '</span>';

        if ($show_counts) {
            $output .= '<span class="cablecast-categories__count">' . $cat->show_count . '</span>';
        }

        $output .= '</a>';
    }

    $output .= '</div>';

    return $output;
}

// ============================================================================
// HOME PAGE SHORTCODE
// ============================================================================

/**
 * [cablecast_home] - Display a complete home page with all sections.
 *
 * Composes multiple shortcodes into a cohesive home page layout:
 * - Now Playing hero section
 * - Weekly schedule grid
 * - Recent shows gallery
 * - Browse by series/category
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cablecast_home_shortcode($atts) {
    cablecast_mark_shortcode_used('home');

    // Get settings
    $options = get_option('cablecast_options', []);
    $home_settings = isset($options['home_page']) ? $options['home_page'] : [];

    $atts = shortcode_atts([
        'show_now_playing' => isset($home_settings['show_now_playing']) ? $home_settings['show_now_playing'] : 'true',
        'show_schedule'    => isset($home_settings['show_schedule']) ? $home_settings['show_schedule'] : 'true',
        'schedule_days'    => isset($home_settings['schedule_days']) ? $home_settings['schedule_days'] : 7,
        'show_recent'      => isset($home_settings['show_recent']) ? $home_settings['show_recent'] : 'true',
        'recent_count'     => isset($home_settings['recent_count']) ? $home_settings['recent_count'] : 12,
        'show_browse'      => isset($home_settings['show_browse']) ? $home_settings['show_browse'] : 'true',
        'class'            => '',
    ], $atts, 'cablecast_home');

    // Parse booleans
    $show_now_playing = filter_var($atts['show_now_playing'], FILTER_VALIDATE_BOOLEAN);
    $show_schedule = filter_var($atts['show_schedule'], FILTER_VALIDATE_BOOLEAN);
    $show_recent = filter_var($atts['show_recent'], FILTER_VALIDATE_BOOLEAN);
    $show_browse = filter_var($atts['show_browse'], FILTER_VALIDATE_BOOLEAN);

    // Get channels
    $channels = cablecast_get_all_channels();
    $default_channel = !empty($channels) ? $channels[0]->ID : 0;

    // Get section headings from settings
    $now_playing_heading = isset($home_settings['now_playing_heading']) ? $home_settings['now_playing_heading'] : __('Now Playing', 'cablecast');
    $schedule_heading = isset($home_settings['schedule_heading']) ? $home_settings['schedule_heading'] : __("This Week's Schedule", 'cablecast');
    $recent_heading = isset($home_settings['recent_heading']) ? $home_settings['recent_heading'] : __('Recent Shows', 'cablecast');
    $browse_heading = isset($home_settings['browse_heading']) ? $home_settings['browse_heading'] : __('Browse', 'cablecast');

    // Build output
    $classes = ['cablecast-home'];
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    // Section 1: Now Playing Hero
    if ($show_now_playing && $default_channel) {
        $output .= '<section class="cablecast-home__section cablecast-home__section--now-playing">';
        $output .= '<h2 class="cablecast-home__section-heading">' . esc_html($now_playing_heading) . '</h2>';

        // If multiple channels, show tabs
        if (count($channels) > 1) {
            $output .= '<div class="cablecast-home__channel-tabs">';
            foreach ($channels as $index => $channel) {
                $active = $index === 0 ? ' cablecast-home__channel-tab--active' : '';
                $output .= '<button type="button" class="cablecast-home__channel-tab' . $active . '" data-channel="' . esc_attr($channel->ID) . '">';
                $output .= esc_html($channel->post_title);
                $output .= '</button>';
            }
            $output .= '</div>';
        }

        $output .= '<div class="cablecast-home__now-playing-content">';
        $output .= do_shortcode('[cablecast_now_playing channel="' . $default_channel . '" show_up_next="true" show_thumbnail="true" show_description="true" exclude_filler="true"]');
        $output .= '</div>';
        $output .= '</section>';
    }

    // Section 2: Weekly Schedule
    if ($show_schedule && $default_channel) {
        $schedule_days = min(14, max(1, absint($atts['schedule_days'])));

        $output .= '<section class="cablecast-home__section cablecast-home__section--schedule">';
        $output .= '<h2 class="cablecast-home__section-heading">' . esc_html($schedule_heading) . '</h2>';
        $output .= do_shortcode('[cablecast_weekly_guide channel="' . $default_channel . '" days="' . $schedule_days . '" show_channel_switcher="true" show_category_colors="true"]');
        $output .= '</section>';
    }

    // Section 3: Recent Shows
    if ($show_recent) {
        $recent_count = absint($atts['recent_count']) ?: 12;

        $output .= '<section class="cablecast-home__section cablecast-home__section--recent">';
        $output .= '<h2 class="cablecast-home__section-heading">' . esc_html($recent_heading) . '</h2>';
        $output .= do_shortcode('[cablecast_shows count="' . $recent_count . '" layout="featured" columns="4" orderby="date" order="DESC"]');

        // View all link
        $shows_archive = get_post_type_archive_link('show');
        if ($shows_archive) {
            $output .= '<div class="cablecast-home__view-all">';
            $output .= '<a href="' . esc_url($shows_archive) . '" class="cablecast-home__view-all-link">';
            $output .= __('View All Shows', 'cablecast') . ' &rarr;';
            $output .= '</a>';
            $output .= '</div>';
        }

        $output .= '</section>';
    }

    // Section 4: Browse
    if ($show_browse) {
        $output .= '<section class="cablecast-home__section cablecast-home__section--browse">';
        $output .= '<h2 class="cablecast-home__section-heading">' . esc_html($browse_heading) . '</h2>';

        $output .= '<div class="cablecast-home__browse-grid">';

        // Series
        $output .= '<div class="cablecast-home__browse-section">';
        $output .= '<h3 class="cablecast-home__browse-heading">' . esc_html__('Series', 'cablecast') . '</h3>';
        $output .= do_shortcode('[cablecast_series count="6" layout="grid" show_thumbnails="true"]');

        $series_archive = get_post_type_archive_link('show');
        if ($series_archive) {
            $output .= '<a href="' . esc_url(add_query_arg('browse', 'series', $series_archive)) . '" class="cablecast-home__browse-link">';
            $output .= __('All Series', 'cablecast') . ' &rarr;';
            $output .= '</a>';
        }
        $output .= '</div>';

        // Categories
        $output .= '<div class="cablecast-home__browse-section">';
        $output .= '<h3 class="cablecast-home__browse-heading">' . esc_html__('Categories', 'cablecast') . '</h3>';
        $output .= do_shortcode('[cablecast_categories layout="cloud" show_colors="true" show_counts="true"]');
        $output .= '</div>';

        // Producers
        $output .= '<div class="cablecast-home__browse-section">';
        $output .= '<h3 class="cablecast-home__browse-heading">' . esc_html__('Producers', 'cablecast') . '</h3>';
        $output .= do_shortcode('[cablecast_producers count="10" orderby="count" layout="list"]');

        $producers_link = get_term_link('cablecast_producer');
        // Note: get_term_link with just taxonomy returns WP_Error, so we link to shows archive instead
        $output .= '<a href="' . esc_url($shows_archive ?? '#') . '" class="cablecast-home__browse-link">';
        $output .= __('All Producers', 'cablecast') . ' &rarr;';
        $output .= '</a>';
        $output .= '</div>';

        $output .= '</div>'; // browse-grid
        $output .= '</section>';
    }

    $output .= '</div>'; // cablecast-home

    return $output;
}
