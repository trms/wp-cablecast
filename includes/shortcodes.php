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
    add_shortcode('cablecast_producers', 'cablecast_producers_shortcode');
    add_shortcode('cablecast_series', 'cablecast_series_shortcode');
}
add_action('init', 'cablecast_register_shortcodes');

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
}
add_action('wp_footer', 'cablecast_enqueue_shortcode_assets', 5);

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
    $layout = in_array($atts['layout'], ['grid', 'list']) ? $atts['layout'] : 'grid';
    $columns = min(6, max(2, absint($atts['columns'])));

    $classes = ['cablecast-shows', 'cablecast-shows--' . $layout];
    if ($layout === 'grid') {
        $classes[] = 'cablecast-shows--columns-' . $columns;
    }
    if (!empty($atts['class'])) {
        $classes[] = esc_attr($atts['class']);
    }

    $output = '<div class="' . implode(' ', $classes) . '">';

    while ($query->have_posts()) {
        $query->the_post();
        $show_id = get_the_ID();

        $output .= '<div class="cablecast-shows__item">';

        // Thumbnail
        $thumbnail_url = cablecast_show_thumbnail_url($show_id, 'medium');
        if ($thumbnail_url) {
            $output .= '<a href="' . get_permalink() . '" class="cablecast-shows__thumbnail">';
            $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr(get_the_title()) . '" loading="lazy" />';
            $output .= '</a>';
        }

        $output .= '<div class="cablecast-shows__content">';
        $output .= '<a href="' . get_permalink() . '" class="cablecast-shows__title">' . esc_html(get_the_title()) . '</a>';

        // Runtime
        $runtime = (int) get_post_meta($show_id, 'cablecast_show_trt', true);
        if ($runtime > 0) {
            $output .= '<span class="cablecast-shows__runtime">' . cablecast_format_runtime($runtime) . '</span>';
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
        if ($show_thumbnails) {
            $shows = get_posts([
                'post_type' => 'show',
                'posts_per_page' => 1,
                'tax_query' => [[
                    'taxonomy' => 'cablecast_project',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ]],
            ]);

            if (!empty($shows)) {
                $thumbnail_url = cablecast_show_thumbnail_url($shows[0]->ID, 'medium');
                if ($thumbnail_url) {
                    $output .= '<a href="' . get_term_link($term) . '" class="cablecast-series__thumbnail">';
                    $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($term->name) . '" loading="lazy" />';
                    $output .= '</a>';
                }
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
