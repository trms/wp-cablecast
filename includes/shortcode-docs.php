<?php
/**
 * Cablecast Shortcode Documentation
 *
 * Provides an admin page with documentation and live examples for all shortcodes.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get documentation data for all shortcodes.
 *
 * @return array
 */
function cablecast_get_shortcode_docs() {
    return [
        'cablecast_schedule' => [
            'name' => 'Schedule',
            'tag' => 'cablecast_schedule',
            'description' => 'Display the schedule for a specific channel.',
            'long_description' => 'Shows a chronological list of programs for a channel, with options to filter by date, show only remaining or upcoming programs, and control thumbnail and description display. Programs are grouped by day with smart labels like "Today" and "Tomorrow".',
            'attributes' => [
                ['name' => 'channel', 'required' => true, 'default' => '', 'options' => 'Channel post ID', 'description' => 'WordPress post ID of the channel to display'],
                ['name' => 'date', 'required' => false, 'default' => 'Today', 'options' => 'Y-m-d format', 'description' => 'Specific date to show schedule for (e.g., 2024-01-15)'],
                ['name' => 'mode', 'required' => false, 'default' => 'all', 'options' => 'all, remaining, next', 'description' => 'Filter which programs to display'],
                ['name' => 'count', 'required' => false, 'default' => '20', 'options' => 'Any number', 'description' => 'Maximum number of programs to show'],
                ['name' => 'show_descriptions', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show program descriptions'],
                ['name' => 'exclude_filler', 'required' => false, 'default' => 'false', 'options' => 'true, false', 'description' => 'Hide filler content (color bars, station IDs, etc.)'],
                ['name' => 'show_thumbnails', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Display program thumbnails'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Basic Usage', 'atts' => ['channel' => '{channel_id}', 'count' => '5']],
                ['title' => 'Upcoming Only', 'atts' => ['channel' => '{channel_id}', 'mode' => 'next', 'count' => '10']],
                ['title' => 'No Filler', 'atts' => ['channel' => '{channel_id}', 'exclude_filler' => 'true']],
            ],
        ],
        'cablecast_now_playing' => [
            'name' => 'Now Playing',
            'tag' => 'cablecast_now_playing',
            'description' => 'Display the currently airing and upcoming program.',
            'long_description' => 'Shows cards for the current program ("Live Now") and optionally the next program ("Up Next"). Includes a progress bar for the current show and automatically updates based on program runtime.',
            'attributes' => [
                ['name' => 'channel', 'required' => true, 'default' => '', 'options' => 'Channel post ID', 'description' => 'WordPress post ID of the channel'],
                ['name' => 'show_up_next', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show the next program card'],
                ['name' => 'show_thumbnail', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Display program thumbnails'],
                ['name' => 'show_description', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show program descriptions'],
                ['name' => 'exclude_filler', 'required' => false, 'default' => 'false', 'options' => 'true, false', 'description' => 'Skip filler content when finding current/next'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Basic Usage', 'atts' => ['channel' => '{channel_id}']],
                ['title' => 'Current Only', 'atts' => ['channel' => '{channel_id}', 'show_up_next' => 'false']],
                ['title' => 'Minimal', 'atts' => ['channel' => '{channel_id}', 'show_thumbnail' => 'false', 'show_description' => 'false']],
            ],
        ],
        'cablecast_weekly_guide' => [
            'name' => 'Weekly Guide',
            'tag' => 'cablecast_weekly_guide',
            'description' => 'Display a multi-day schedule grid.',
            'long_description' => 'Shows a responsive grid with one column per day, displaying all programs for each day. Includes a channel switcher dropdown and highlights the current program. Uses a single database query for optimal performance.',
            'attributes' => [
                ['name' => 'channel', 'required' => false, 'default' => 'First channel', 'options' => 'Channel post ID', 'description' => 'WordPress post ID of the channel (auto-selects first if not specified)'],
                ['name' => 'days', 'required' => false, 'default' => '7', 'options' => '1-14', 'description' => 'Number of days to display'],
                ['name' => 'show_channel_switcher', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show the channel dropdown selector'],
                ['name' => 'show_category_colors', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Apply category colors to programs'],
                ['name' => 'show_descriptions', 'required' => false, 'default' => 'false', 'options' => 'true, false', 'description' => 'Show program descriptions (can make grid very tall)'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Basic (7 days)', 'atts' => []],
                ['title' => '3-Day Guide', 'atts' => ['days' => '3']],
                ['title' => 'Specific Channel', 'atts' => ['channel' => '{channel_id}', 'show_channel_switcher' => 'false']],
            ],
        ],
        'cablecast_shows' => [
            'name' => 'Shows',
            'tag' => 'cablecast_shows',
            'description' => 'Display a grid or list of shows.',
            'long_description' => 'Shows a filterable collection of shows with thumbnails. Can filter by category, producer, or series. Supports grid and list layouts with configurable columns and pagination.',
            'attributes' => [
                ['name' => 'category', 'required' => false, 'default' => '', 'options' => 'Slug or ID', 'description' => 'Filter by category slug or term ID'],
                ['name' => 'producer', 'required' => false, 'default' => '', 'options' => 'Slug or ID', 'description' => 'Filter by producer slug or term ID'],
                ['name' => 'series', 'required' => false, 'default' => '', 'options' => 'Slug or ID', 'description' => 'Filter by series/project slug or term ID'],
                ['name' => 'count', 'required' => false, 'default' => '12', 'options' => 'Any number', 'description' => 'Number of shows to display'],
                ['name' => 'orderby', 'required' => false, 'default' => 'date', 'options' => 'date, title, event_date', 'description' => 'How to sort the shows'],
                ['name' => 'order', 'required' => false, 'default' => 'DESC', 'options' => 'ASC, DESC', 'description' => 'Sort direction'],
                ['name' => 'layout', 'required' => false, 'default' => 'grid', 'options' => 'grid, list', 'description' => 'Display layout style'],
                ['name' => 'columns', 'required' => false, 'default' => '4', 'options' => '2-6', 'description' => 'Number of columns (grid layout only)'],
                ['name' => 'show_pagination', 'required' => false, 'default' => 'false', 'options' => 'true, false', 'description' => 'Show pagination links'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Recent Shows', 'atts' => ['count' => '8', 'columns' => '4']],
                ['title' => 'By Producer', 'atts' => ['producer' => '{producer_slug}', 'count' => '6']],
                ['title' => 'List Layout', 'atts' => ['layout' => 'list', 'count' => '10']],
            ],
        ],
        'cablecast_show' => [
            'name' => 'Single Show',
            'tag' => 'cablecast_show',
            'description' => 'Display a single show with full details.',
            'long_description' => 'Shows complete information for a specific show including VOD player (if available), thumbnail, producer, runtime, category, and full description.',
            'attributes' => [
                ['name' => 'id', 'required' => true, 'default' => '', 'options' => 'Show post ID', 'description' => 'WordPress post ID of the show'],
                ['name' => 'show_vod', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Display the VOD video player'],
                ['name' => 'show_thumbnail', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Display thumbnail (only if no VOD)'],
                ['name' => 'show_meta', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show producer, runtime, and category'],
                ['name' => 'show_description', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show the full description'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Full Display', 'atts' => ['id' => '{show_id}']],
                ['title' => 'No Video', 'atts' => ['id' => '{show_id}', 'show_vod' => 'false']],
                ['title' => 'Minimal', 'atts' => ['id' => '{show_id}', 'show_meta' => 'false', 'show_description' => 'false']],
            ],
        ],
        'cablecast_vod_player' => [
            'name' => 'VOD Player',
            'tag' => 'cablecast_vod_player',
            'description' => 'Display just the video player for a show.',
            'long_description' => 'A minimal shortcode that displays only the VOD embed code for a show. Use this when you want to embed the video player without any surrounding content.',
            'attributes' => [
                ['name' => 'id', 'required' => true, 'default' => '', 'options' => 'Show post ID', 'description' => 'WordPress post ID of the show'],
                ['name' => 'poster', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show poster image (reserved for future use)'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Basic Usage', 'atts' => ['id' => '{show_id}']],
            ],
        ],
        'cablecast_chapters' => [
            'name' => 'Chapters',
            'tag' => 'cablecast_chapters',
            'description' => 'Display interactive chapters for a show\'s VOD.',
            'long_description' => 'Shows a clickable list of chapters for the show\'s VOD. Clicking a chapter seeks the embedded player to that timestamp using postMessage. The current chapter is automatically highlighted as the video plays. Requires a VOD embed on the same page.',
            'attributes' => [
                ['name' => 'id', 'required' => false, 'default' => 'Current post', 'options' => 'Show post ID', 'description' => 'WordPress post ID of the show (auto-detects in show context)'],
                ['name' => 'player', 'required' => false, 'default' => 'Auto-detect', 'options' => 'CSS selector', 'description' => 'CSS selector for the player container (for pages with multiple players)'],
                ['name' => 'show_descriptions', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show chapter descriptions'],
                ['name' => 'show_timestamps', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show formatted timestamps'],
                ['name' => 'layout', 'required' => false, 'default' => 'list', 'options' => 'list, compact', 'description' => 'Display layout style'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Basic Usage', 'atts' => ['id' => '{show_with_chapters_id}']],
                ['title' => 'Compact (no descriptions)', 'atts' => ['id' => '{show_with_chapters_id}', 'layout' => 'compact']],
                ['title' => 'Timestamps Only', 'atts' => ['id' => '{show_with_chapters_id}', 'show_descriptions' => 'false']],
            ],
        ],
        'cablecast_producers' => [
            'name' => 'Producers',
            'tag' => 'cablecast_producers',
            'description' => 'Display a directory of producers.',
            'long_description' => 'Shows a list or grid of all producers with show counts. Can optionally display contact information. Links to producer archive pages.',
            'attributes' => [
                ['name' => 'count', 'required' => false, 'default' => '0 (all)', 'options' => 'Any number', 'description' => 'Number of producers to display (0 = all)'],
                ['name' => 'orderby', 'required' => false, 'default' => 'name', 'options' => 'name, count', 'description' => 'Sort by name or show count'],
                ['name' => 'layout', 'required' => false, 'default' => 'list', 'options' => 'grid, list', 'description' => 'Display layout style'],
                ['name' => 'show_contact', 'required' => false, 'default' => 'false', 'options' => 'true, false', 'description' => 'Show producer email and website'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'All Producers', 'atts' => []],
                ['title' => 'Top 10 by Shows', 'atts' => ['count' => '10', 'orderby' => 'count']],
                ['title' => 'With Contact Info', 'atts' => ['show_contact' => 'true']],
            ],
        ],
        'cablecast_series' => [
            'name' => 'Series',
            'tag' => 'cablecast_series',
            'description' => 'Display a directory of series/projects.',
            'long_description' => 'Shows a list or grid of all series with episode counts and thumbnails. Thumbnails are pulled from the first show in each series. Links to series archive pages.',
            'attributes' => [
                ['name' => 'count', 'required' => false, 'default' => '0 (all)', 'options' => 'Any number', 'description' => 'Number of series to display (0 = all)'],
                ['name' => 'orderby', 'required' => false, 'default' => 'name', 'options' => 'name, count', 'description' => 'Sort by name or episode count'],
                ['name' => 'layout', 'required' => false, 'default' => 'grid', 'options' => 'grid, list', 'description' => 'Display layout style'],
                ['name' => 'show_thumbnails', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Display series thumbnails'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'All Series', 'atts' => []],
                ['title' => 'Top 6 by Episodes', 'atts' => ['count' => '6', 'orderby' => 'count']],
                ['title' => 'List without Thumbnails', 'atts' => ['layout' => 'list', 'show_thumbnails' => 'false']],
            ],
        ],
        'cablecast_schedule_calendar' => [
            'name' => 'Schedule Calendar',
            'tag' => 'cablecast_schedule_calendar',
            'description' => 'Interactive calendar view powered by FullCalendar.',
            'long_description' => 'Displays channel schedule in an interactive calendar using FullCalendar.io. Users can switch between week, day, month, and list views. Events are color-coded by category and clicking navigates to show pages. Includes navigation buttons, current time indicator, and responsive design.',
            'attributes' => [
                ['name' => 'channel', 'required' => true, 'default' => '', 'options' => 'Channel post ID', 'description' => 'WordPress post ID of the channel to display'],
                ['name' => 'view', 'required' => false, 'default' => 'timeGridWeek', 'options' => 'timeGridWeek, timeGridDay, dayGridMonth, listWeek', 'description' => 'Initial calendar view'],
                ['name' => 'height', 'required' => false, 'default' => 'auto', 'options' => 'auto, number', 'description' => 'Calendar height (auto or pixels)'],
                ['name' => 'header', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show view switching buttons in toolbar'],
                ['name' => 'nav', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Show prev/next/today navigation buttons'],
                ['name' => 'show_category_colors', 'required' => false, 'default' => 'true', 'options' => 'true, false', 'description' => 'Color events by show category'],
                ['name' => 'class', 'required' => false, 'default' => '', 'options' => 'CSS class name', 'description' => 'Additional CSS class for custom styling'],
            ],
            'examples' => [
                ['title' => 'Basic (Week View)', 'atts' => ['channel' => '{channel_id}']],
                ['title' => 'Month View', 'atts' => ['channel' => '{channel_id}', 'view' => 'dayGridMonth']],
                ['title' => 'List View', 'atts' => ['channel' => '{channel_id}', 'view' => 'listWeek', 'header' => 'false']],
            ],
        ],
    ];
}

/**
 * Get dynamic IDs for live examples based on actual site data.
 *
 * @return array
 */
function cablecast_get_example_ids() {
    $ids = [
        'channel_id' => null,
        'show_id' => null,
        'show_with_chapters_id' => null,
        'producer_slug' => null,
        'series_slug' => null,
    ];

    // Get first available channel
    $channels = get_posts([
        'post_type' => 'cablecast_channel',
        'posts_per_page' => 1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    if (!empty($channels)) {
        $ids['channel_id'] = $channels[0]->ID;
    }

    // Get a show with a thumbnail
    $shows = get_posts([
        'post_type' => 'show',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    if (!empty($shows)) {
        $ids['show_id'] = $shows[0]->ID;
    }

    // Get a show with VOD chapters
    $shows_with_chapters = get_posts([
        'post_type' => 'show',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [
            [
                'key' => 'cablecast_vod_chapters',
                'compare' => 'EXISTS',
            ],
            [
                'key' => 'cablecast_vod_chapters',
                'value' => '',
                'compare' => '!=',
            ],
        ],
    ]);
    if (!empty($shows_with_chapters)) {
        $ids['show_with_chapters_id'] = $shows_with_chapters[0]->ID;
    }

    // Get producer with most shows
    $producers = get_terms([
        'taxonomy' => 'cablecast_producer',
        'hide_empty' => true,
        'number' => 1,
        'orderby' => 'count',
        'order' => 'DESC',
    ]);
    if (!empty($producers) && !is_wp_error($producers)) {
        $ids['producer_slug'] = $producers[0]->slug;
    }

    // Get series with most episodes
    $series = get_terms([
        'taxonomy' => 'cablecast_project',
        'hide_empty' => true,
        'number' => 1,
        'orderby' => 'count',
        'order' => 'DESC',
    ]);
    if (!empty($series) && !is_wp_error($series)) {
        $ids['series_slug'] = $series[0]->slug;
    }

    return $ids;
}

/**
 * Generate a shortcode string from tag and attributes.
 *
 * @param string $tag Shortcode tag
 * @param array $atts Attributes
 * @param array $example_ids Dynamic IDs to substitute
 * @return string
 */
function cablecast_generate_shortcode_string($tag, $atts, $example_ids = []) {
    $atts_string = '';
    foreach ($atts as $key => $value) {
        // Substitute placeholders with actual IDs
        if ($value === '{channel_id}' && !empty($example_ids['channel_id'])) {
            $value = $example_ids['channel_id'];
        } elseif ($value === '{show_id}' && !empty($example_ids['show_id'])) {
            $value = $example_ids['show_id'];
        } elseif ($value === '{show_with_chapters_id}' && !empty($example_ids['show_with_chapters_id'])) {
            $value = $example_ids['show_with_chapters_id'];
        } elseif ($value === '{producer_slug}' && !empty($example_ids['producer_slug'])) {
            $value = $example_ids['producer_slug'];
        } elseif ($value === '{series_slug}' && !empty($example_ids['series_slug'])) {
            $value = $example_ids['series_slug'];
        }

        $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
    }

    return '[' . $tag . $atts_string . ']';
}

/**
 * Render a live example with injected CSS.
 *
 * @param string $shortcode_string Full shortcode string
 * @return string HTML output
 */
function cablecast_render_live_example($shortcode_string) {
    ob_start();

    // Inject shortcode CSS
    $css_file = plugin_dir_path(__FILE__) . '../assets/css/shortcodes.css';
    if (file_exists($css_file)) {
        echo '<style>';
        include $css_file;
        echo '</style>';
    }

    // Render the shortcode
    echo '<div class="cablecast-docs-preview__content">';
    echo do_shortcode($shortcode_string);
    echo '</div>';

    return ob_get_clean();
}

/**
 * Render the attributes table for a shortcode.
 *
 * @param array $attributes
 */
function cablecast_render_attributes_table($attributes) {
    ?>
    <table class="wp-list-table widefat fixed striped cablecast-attributes-table">
        <thead>
            <tr>
                <th style="width: 140px;"><?php _e('Attribute', 'cablecast'); ?></th>
                <th><?php _e('Description', 'cablecast'); ?></th>
                <th style="width: 120px;"><?php _e('Default', 'cablecast'); ?></th>
                <th style="width: 140px;"><?php _e('Options', 'cablecast'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attributes as $attr): ?>
            <tr>
                <td>
                    <code><?php echo esc_html($attr['name']); ?></code>
                    <?php if (!empty($attr['required'])): ?>
                    <span class="cablecast-required-badge"><?php _e('Required', 'cablecast'); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html($attr['description']); ?></td>
                <td><code><?php echo esc_html($attr['default'] ?: '—'); ?></code></td>
                <td><?php echo esc_html($attr['options']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Render the shortcode index page.
 *
 * @param array $shortcodes
 * @param array $example_ids
 */
function cablecast_render_shortcode_index($shortcodes, $example_ids) {
    $has_data = !empty($example_ids['channel_id']) || !empty($example_ids['show_id']);
    ?>
    <div class="cablecast-docs-index">
        <p><?php _e('The Cablecast plugin provides shortcodes for displaying schedule and show information on your site. Click on any shortcode below to see detailed documentation and live examples.', 'cablecast'); ?></p>

        <?php if (!$has_data): ?>
        <div class="notice notice-warning inline">
            <p><strong><?php _e('No data available for live examples.', 'cablecast'); ?></strong>
            <?php _e('Sync some shows and channels first to see live previews.', 'cablecast'); ?></p>
        </div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 220px;"><?php _e('Shortcode', 'cablecast'); ?></th>
                    <th><?php _e('Description', 'cablecast'); ?></th>
                    <th style="width: 100px;"><?php _e('Copy', 'cablecast'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shortcodes as $key => $shortcode): ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=cablecast-shortcode-docs&shortcode=' . $key)); ?>">
                            <code>[<?php echo esc_html($shortcode['tag']); ?>]</code>
                        </a>
                    </td>
                    <td><?php echo esc_html($shortcode['description']); ?></td>
                    <td>
                        <button type="button" class="button button-small cablecast-copy-btn"
                                data-shortcode="[<?php echo esc_attr($shortcode['tag']); ?>]">
                            <?php _e('Copy', 'cablecast'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Render a shortcode detail page.
 *
 * @param array $shortcode
 * @param array $example_ids
 */
function cablecast_render_shortcode_detail($shortcode, $example_ids) {
    $has_required_data = true;

    // Check if required data is available for this shortcode
    if (in_array($shortcode['tag'], ['cablecast_schedule', 'cablecast_now_playing', 'cablecast_schedule_calendar'])) {
        $has_required_data = !empty($example_ids['channel_id']);
    } elseif (in_array($shortcode['tag'], ['cablecast_show', 'cablecast_vod_player'])) {
        $has_required_data = !empty($example_ids['show_id']);
    } elseif ($shortcode['tag'] === 'cablecast_chapters') {
        $has_required_data = !empty($example_ids['show_with_chapters_id']);
    }
    ?>
    <div class="cablecast-docs-detail">
        <div class="cablecast-docs-description">
            <p><?php echo esc_html($shortcode['long_description']); ?></p>
        </div>

        <h3><?php _e('Attributes', 'cablecast'); ?></h3>
        <?php cablecast_render_attributes_table($shortcode['attributes']); ?>

        <h3><?php _e('Usage Examples', 'cablecast'); ?></h3>
        <?php foreach ($shortcode['examples'] as $example): ?>
        <div class="cablecast-docs-example">
            <h4><?php echo esc_html($example['title']); ?></h4>
            <?php
            $shortcode_string = cablecast_generate_shortcode_string($shortcode['tag'], $example['atts'], $example_ids);
            ?>
            <div class="cablecast-code-block-wrapper">
                <pre class="cablecast-code-block"><?php echo esc_html($shortcode_string); ?></pre>
                <button type="button" class="button button-small cablecast-copy-btn"
                        data-shortcode="<?php echo esc_attr($shortcode_string); ?>">
                    <?php _e('Copy', 'cablecast'); ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <h3><?php _e('Live Preview', 'cablecast'); ?></h3>
        <div class="cablecast-docs-preview">
            <?php
            if ($has_required_data && !empty($shortcode['examples'][0])) {
                // Special handling for chapters - show full setup with VOD player
                if ($shortcode['tag'] === 'cablecast_chapters' && !empty($example_ids['show_with_chapters_id'])) {
                    $show_id = $example_ids['show_with_chapters_id'];
                    $preview_string = '[cablecast_vod_player id="' . $show_id . '"][cablecast_chapters id="' . $show_id . '"]';
                    ?>
                    <p class="description" style="margin-bottom: 15px;">
                        <?php _e('Complete setup showing VOD player with interactive chapters:', 'cablecast'); ?>
                    </p>
                    <div class="cablecast-code-block-wrapper" style="margin-bottom: 15px;">
                        <pre class="cablecast-code-block"><?php echo esc_html($preview_string); ?></pre>
                        <button type="button" class="button button-small cablecast-copy-btn"
                                data-shortcode="<?php echo esc_attr($preview_string); ?>">
                            <?php _e('Copy', 'cablecast'); ?>
                        </button>
                    </div>
                    <?php
                    echo cablecast_render_live_example($preview_string);
                } else {
                    $preview_string = cablecast_generate_shortcode_string(
                        $shortcode['tag'],
                        $shortcode['examples'][0]['atts'],
                        $example_ids
                    );
                    echo cablecast_render_live_example($preview_string);
                }
            } else {
                ?>
                <p class="cablecast-docs-no-preview">
                    <?php _e('No data available for live preview. Please sync channels and shows first.', 'cablecast'); ?>
                </p>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Main shortcode documentation admin page.
 */
function cablecast_shortcode_docs_page() {
    // Enqueue admin styles
    wp_enqueue_style(
        'cablecast-shortcode-docs',
        plugins_url('../assets/css/shortcode-docs.css', __FILE__),
        [],
        filemtime(plugin_dir_path(__FILE__) . '../assets/css/shortcode-docs.css')
    );

    $shortcodes = cablecast_get_shortcode_docs();
    $example_ids = cablecast_get_example_ids();
    $current_tab = isset($_GET['shortcode']) ? sanitize_key($_GET['shortcode']) : 'index';

    ?>
    <div class="wrap cablecast-shortcode-docs">
        <h1><?php _e('Cablecast Shortcode Documentation', 'cablecast'); ?></h1>

        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('admin.php?page=cablecast-shortcode-docs')); ?>"
               class="nav-tab <?php echo $current_tab === 'index' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Overview', 'cablecast'); ?>
            </a>
            <?php foreach ($shortcodes as $key => $shortcode): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=cablecast-shortcode-docs&shortcode=' . $key)); ?>"
               class="nav-tab <?php echo $current_tab === $key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($shortcode['name']); ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="tab-content">
            <?php
            if ($current_tab === 'index') {
                cablecast_render_shortcode_index($shortcodes, $example_ids);
            } elseif (isset($shortcodes[$current_tab])) {
                cablecast_render_shortcode_detail($shortcodes[$current_tab], $example_ids);
            }
            ?>
        </div>
    </div>

    <script>
    jQuery(function($) {
        // Copy to clipboard functionality
        $('.cablecast-copy-btn').on('click', function() {
            var shortcode = $(this).data('shortcode');
            var $btn = $(this);

            navigator.clipboard.writeText(shortcode).then(function() {
                var originalText = $btn.text();
                $btn.text('<?php _e('Copied!', 'cablecast'); ?>');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 1500);
            });
        });
    });
    </script>
    <?php
}
