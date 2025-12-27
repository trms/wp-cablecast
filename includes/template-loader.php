<?php
/**
 * Cablecast Template Loader
 *
 * Provides a template loading system that allows themes to override plugin templates.
 * Follows the WooCommerce pattern for template overrides.
 *
 * Themes can override templates by placing them in: yourtheme/cablecast/
 * For example: yourtheme/cablecast/single-show.php
 *
 * @package Cablecast
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if a category has any shows assigned to it.
 *
 * Used to determine whether to use the Cablecast category template
 * or fall back to the theme's default category template.
 *
 * @param int $category_id The category term ID.
 * @return bool True if the category has shows, false otherwise.
 */
function cablecast_category_has_shows($category_id) {
    static $cache = [];

    if (isset($cache[$category_id])) {
        return $cache[$category_id];
    }

    $query = new WP_Query([
        'post_type' => 'show',
        'tax_query' => [[
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $category_id,
        ]],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $cache[$category_id] = $query->have_posts();
    wp_reset_postdata();

    return $cache[$category_id];
}

/**
 * Get the path to the plugin's templates directory.
 *
 * @return string
 */
function cablecast_get_templates_dir() {
    return plugin_dir_path(dirname(__FILE__)) . 'templates/';
}

/**
 * Get the theme's template path for Cablecast templates.
 *
 * @return string
 */
function cablecast_get_theme_template_path() {
    return apply_filters('cablecast_theme_template_path', 'cablecast/');
}

/**
 * Locate a template file.
 *
 * Search order:
 * 1. yourtheme/cablecast/$template_name
 * 2. yourtheme/$template_name (for backwards compatibility)
 * 3. wp-cablecast/templates/$template_name
 *
 * @param string $template_name Template file name (e.g., 'single-show.php')
 * @param string $template_path Optional. Theme subdirectory to check. Default 'cablecast/'.
 * @param string $default_path  Optional. Plugin templates directory. Default plugin's templates dir.
 * @return string Full path to template file, or empty string if not found.
 */
function cablecast_locate_template($template_name, $template_path = '', $default_path = '') {
    if (!$template_path) {
        $template_path = cablecast_get_theme_template_path();
    }

    if (!$default_path) {
        $default_path = cablecast_get_templates_dir();
    }

    // Look in theme/cablecast/ first
    $template = locate_template([
        trailingslashit($template_path) . $template_name,
        $template_name, // Backwards compatibility - check theme root too
    ]);

    // If not found in theme, use plugin default
    if (!$template && file_exists($default_path . $template_name)) {
        $template = $default_path . $template_name;
    }

    /**
     * Filter the located template path.
     *
     * @param string $template      The located template path.
     * @param string $template_name The template file name.
     * @param string $template_path The theme template path.
     */
    return apply_filters('cablecast_locate_template', $template, $template_name, $template_path);
}

/**
 * Get and include a template file.
 *
 * @param string $template_name Template file name (e.g., 'single-show.php')
 * @param array  $args          Variables to pass to the template.
 * @param string $template_path Optional. Theme subdirectory to check.
 * @param string $default_path  Optional. Plugin templates directory.
 */
function cablecast_get_template($template_name, $args = [], $template_path = '', $default_path = '') {
    $template = cablecast_locate_template($template_name, $template_path, $default_path);

    if (!$template) {
        return;
    }

    /**
     * Filter the template file path before including.
     *
     * @param string $template      The template path.
     * @param string $template_name The template name.
     * @param array  $args          Template arguments.
     */
    $template = apply_filters('cablecast_get_template', $template, $template_name, $args);

    /**
     * Action before template is loaded.
     *
     * @param string $template_name The template name.
     * @param string $template_path The template path in theme.
     * @param string $template      The full template path.
     * @param array  $args          Template arguments.
     */
    do_action('cablecast_before_template', $template_name, $template_path, $template, $args);

    // Extract args to make them available as variables in the template
    if (!empty($args) && is_array($args)) {
        extract($args); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
    }

    include $template;

    /**
     * Action after template is loaded.
     *
     * @param string $template_name The template name.
     * @param string $template_path The template path in theme.
     * @param string $template      The full template path.
     * @param array  $args          Template arguments.
     */
    do_action('cablecast_after_template', $template_name, $template_path, $template, $args);
}

/**
 * Get a template part and return it as a string.
 *
 * @param string $template_name Template file name.
 * @param array  $args          Variables to pass to the template.
 * @param string $template_path Optional. Theme subdirectory to check.
 * @param string $default_path  Optional. Plugin templates directory.
 * @return string Template output.
 */
function cablecast_get_template_html($template_name, $args = [], $template_path = '', $default_path = '') {
    ob_start();
    cablecast_get_template($template_name, $args, $template_path, $default_path);
    return ob_get_clean();
}

/**
 * Main template loader hook.
 *
 * Intercepts WordPress template loading for Cablecast post types and taxonomies.
 *
 * @param string $template The current template path.
 * @return string The template path to use.
 */
function cablecast_template_loader($template) {
    // Check if templates are enabled (default to enabled)
    $options = get_option('cablecast_options', []);
    $templates_enabled = isset($options['enable_templates']) ? (bool) $options['enable_templates'] : true;

    if (!$templates_enabled) {
        return $template;
    }

    $file = '';

    // Single show page
    if (is_singular('show')) {
        $file = 'single-show.php';

        /**
         * Filter the single show template file.
         *
         * @param string $file The template file name.
         */
        $file = apply_filters('cablecast_single_show_template', $file);
    }
    // Single channel page
    elseif (is_singular('cablecast_channel')) {
        $file = 'single-channel.php';

        /**
         * Filter the single channel template file.
         *
         * @param string $file The template file name.
         */
        $file = apply_filters('cablecast_single_channel_template', $file);
    }
    // Producer taxonomy archive
    elseif (is_tax('cablecast_producer')) {
        $file = 'archive-producer.php';

        /**
         * Filter the producer archive template file.
         *
         * @param string $file The template file name.
         */
        $file = apply_filters('cablecast_archive_producer_template', $file);
    }
    // Series/Project taxonomy archive
    elseif (is_tax('cablecast_project')) {
        $file = 'archive-series.php';

        /**
         * Filter the series archive template file.
         *
         * @param string $file The template file name.
         */
        $file = apply_filters('cablecast_archive_series_template', $file);
    }
    // Shows archive
    elseif (is_post_type_archive('show')) {
        $file = 'archive-show.php';

        /**
         * Filter the shows archive template file.
         *
         * @param string $file The template file name.
         */
        $file = apply_filters('cablecast_archive_show_template', $file);
    }
    // Channels archive
    elseif (is_post_type_archive('cablecast_channel')) {
        $file = 'archive-channel.php';

        /**
         * Filter the channels archive template file.
         *
         * @param string $file The template file name.
         */
        $file = apply_filters('cablecast_archive_channel_template', $file);
    }
    // Category archive (only if category has shows)
    elseif (is_category() && cablecast_category_has_shows(get_queried_object_id())) {
        $file = 'archive-category.php';

        /**
         * Filter the category archive template file.
         *
         * @param string $file The template file name.
         */
        $file = apply_filters('cablecast_archive_category_template', $file);
    }

    // If we have a template file to load, try to locate it
    if ($file) {
        $located = cablecast_locate_template($file);

        if ($located) {
            /**
             * Filter the final template path before returning.
             *
             * @param string $located  The located template path.
             * @param string $file     The template file name.
             * @param string $template The original template path.
             */
            return apply_filters('cablecast_template_loader_file', $located, $file, $template);
        }
    }

    return $template;
}
add_filter('template_include', 'cablecast_template_loader', 10);

/**
 * Get show meta data for display.
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Defaults to current post.
 * @return array Array of meta data with labels and values.
 */
function cablecast_get_show_meta($post = null) {
    $post = get_post($post);

    if (!$post || $post->post_type !== 'show') {
        return [];
    }

    $meta = [];

    // Runtime
    $trt = get_post_meta($post->ID, 'cablecast_show_trt', true);
    if (!empty($trt)) {
        $meta['runtime'] = [
            'label' => __('Runtime', 'cablecast'),
            'value' => cablecast_format_runtime(absint($trt)),
            'raw'   => $trt,
        ];
    }

    // Producer
    $producer = get_post_meta($post->ID, 'cablecast_producer_name', true);
    if (!empty($producer)) {
        $producer_link = get_term_link(cablecast_replace_commas_in_tag($producer), 'cablecast_producer');
        $meta['producer'] = [
            'label' => __('Producer', 'cablecast'),
            'value' => $producer,
            'link'  => !is_wp_error($producer_link) ? $producer_link : '',
        ];
    }

    // Series
    $project = get_post_meta($post->ID, 'cablecast_project_name', true);
    if (!empty($project)) {
        $project_link = get_term_link(cablecast_replace_commas_in_tag($project), 'cablecast_project');
        $meta['series'] = [
            'label' => __('Series', 'cablecast'),
            'value' => $project,
            'link'  => !is_wp_error($project_link) ? $project_link : '',
        ];
    }

    // Category
    $category = get_post_meta($post->ID, 'cablecast_category_name', true);
    if (!empty($category)) {
        $category_link = get_term_link($category, 'category');
        $meta['category'] = [
            'label' => __('Category', 'cablecast'),
            'value' => $category,
            'link'  => !is_wp_error($category_link) ? $category_link : '',
        ];
    }

    /**
     * Filter the show meta data.
     *
     * @param array   $meta Array of meta data.
     * @param WP_Post $post The post object.
     */
    return apply_filters('cablecast_show_meta', $meta, $post);
}

/**
 * Check if show has VOD available.
 *
 * @param int|WP_Post $post Optional. Post ID or post object.
 * @return bool
 */
function cablecast_has_vod($post = null) {
    $post = get_post($post);

    if (!$post || $post->post_type !== 'show') {
        return false;
    }

    $vod_embed = get_post_meta($post->ID, 'cablecast_vod_embed', true);
    $vod_url = get_post_meta($post->ID, 'cablecast_vod_url', true);

    return !empty($vod_embed) || !empty($vod_url);
}

/**
 * Check if show has VOD chapters.
 *
 * @param int|WP_Post $post Optional. Post ID or post object.
 * @return bool
 */
function cablecast_has_chapters($post = null) {
    $post = get_post($post);

    if (!$post || $post->post_type !== 'show') {
        return false;
    }

    $chapters = get_post_meta($post->ID, 'cablecast_vod_chapters', true);

    return !empty($chapters);
}

/**
 * Get VOD embed code for a show.
 *
 * @param int|WP_Post $post Optional. Post ID or post object.
 * @return string VOD embed HTML or empty string.
 */
function cablecast_get_vod_embed($post = null) {
    $post = get_post($post);

    if (!$post || $post->post_type !== 'show') {
        return '';
    }

    // Prefer embed code (supports chapters and more features)
    $vod_embed = get_post_meta($post->ID, 'cablecast_vod_embed', true);
    if (!empty($vod_embed)) {
        return $vod_embed;
    }

    // Fall back to direct URL with WordPress video shortcode
    $vod_url = get_post_meta($post->ID, 'cablecast_vod_url', true);
    if (!empty($vod_url)) {
        $poster = get_the_post_thumbnail_url($post->ID, 'large');
        $shortcode = '[video src="' . esc_url($vod_url) . '"';
        if ($poster) {
            $shortcode .= ' poster="' . esc_url($poster) . '"';
        }
        $shortcode .= ']';
        return do_shortcode($shortcode);
    }

    return '';
}

/**
 * Get channel live embed code.
 *
 * @param int|WP_Post $post Optional. Post ID or post object.
 * @return string Live embed HTML or empty string.
 */
function cablecast_get_channel_live_embed($post = null) {
    $post = get_post($post);

    if (!$post || $post->post_type !== 'cablecast_channel') {
        return '';
    }

    $embed_code = get_post_meta($post->ID, 'cablecast_channel_live_embed_code', true);

    if (empty($embed_code)) {
        return '';
    }

    // Allow only safe HTML elements
    $allowed_html = [
        'iframe' => [
            'src'             => [],
            'width'           => [],
            'height'          => [],
            'frameborder'     => [],
            'allowfullscreen' => [],
            'allow'           => [],
            'style'           => [],
        ],
        'video' => [
            'src'      => [],
            'width'    => [],
            'height'   => [],
            'controls' => [],
            'autoplay' => [],
            'style'    => [],
        ],
        'source' => [
            'src'  => [],
            'type' => [],
        ],
    ];

    return wp_kses($embed_code, $allowed_html);
}

/**
 * Register custom page templates.
 *
 * This allows users to select "Cablecast Home" as a page template
 * without needing to add it to their theme.
 *
 * @param array $templates Array of page templates.
 * @return array Modified templates array.
 */
function cablecast_register_page_templates($templates) {
    $templates['cablecast-home'] = __('Cablecast Home', 'cablecast');
    return $templates;
}
add_filter('theme_page_templates', 'cablecast_register_page_templates');

/**
 * Load custom page template from plugin directory.
 *
 * @param string $template The path to the template file.
 * @return string Modified template path.
 */
function cablecast_load_page_template($template) {
    global $post;

    if (!$post) {
        return $template;
    }

    $page_template = get_page_template_slug($post->ID);

    if ($page_template === 'cablecast-home') {
        // First check if theme has an override
        $theme_template = cablecast_locate_template('page-cablecast-home.php');
        if ($theme_template) {
            return $theme_template;
        }

        // Use plugin template
        $plugin_template = cablecast_get_templates_dir() . 'page-cablecast-home.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    return $template;
}
add_filter('template_include', 'cablecast_load_page_template', 5);

/**
 * Enqueue template styles when needed.
 */
function cablecast_enqueue_template_styles() {
    // Check for category archives with shows
    $is_show_category = is_category() && cablecast_category_has_shows(get_queried_object_id());

    // Only enqueue on Cablecast pages
    if (is_singular('show') || is_singular('cablecast_channel') ||
        is_tax('cablecast_producer') || is_tax('cablecast_project') ||
        is_post_type_archive('show') || is_post_type_archive('cablecast_channel') ||
        $is_show_category) {

        // Mark shortcodes as used so their CSS loads
        if (function_exists('cablecast_mark_shortcode_used')) {
            if (is_singular('show')) {
                cablecast_mark_shortcode_used('show');
                cablecast_mark_shortcode_used('chapters');
                cablecast_mark_shortcode_used('upcoming_runs');
            } elseif (is_singular('cablecast_channel')) {
                cablecast_mark_shortcode_used('now_playing');
                cablecast_mark_shortcode_used('schedule_calendar');
            } else {
                cablecast_mark_shortcode_used('shows');
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'cablecast_enqueue_template_styles', 5);
