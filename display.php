<?php
/**
 * Cablecast Display Functions
 *
 * This file contains legacy display functions that are used when the
 * template system is disabled. When templates are enabled (default),
 * the template-loader.php handles display instead.
 *
 * @package Cablecast
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exclude shows from the Page List block and get_pages() queries.
 * Shows should not appear in navigation page lists.
 */
add_filter('get_pages', 'cablecast_exclude_shows_from_page_list', 10, 2);
function cablecast_exclude_shows_from_page_list($pages, $parsed_args) {
    // Filter out any show posts that might have been included
    return array_filter($pages, function($page) {
        return $page->post_type !== 'show' && $page->post_type !== 'cablecast_channel';
    });
}

/**
 * Include shows in category and tag archives.
 */
add_filter('pre_get_posts', 'cablecast_query_post_type');
function cablecast_query_post_type($query) {
    if (is_category() || is_tag()) {
        $post_type = get_query_var('post_type');
        if ($post_type) {
            $post_type = $post_type;
        } else {
            $post_type = array('post', 'show');
        }
        $query->set('post_type', $post_type);
        return $query;
    }
}

/**
 * Check if Cablecast templates are enabled.
 *
 * Templates are enabled by default. They can be disabled in settings
 * for themes that want to provide their own templates without using
 * the override system.
 *
 * @return bool
 */
function cablecast_templates_enabled() {
    $options = get_option('cablecast_options', []);
    // Default to enabled (true) if not set
    return isset($options['enable_templates']) ? (bool) $options['enable_templates'] : true;
}

/**
 * Check if the template loader is handling the current page.
 *
 * This is used to prevent the legacy content filter from interfering
 * with the new template system.
 *
 * @return bool
 */
function cablecast_is_template_page() {
    if (!cablecast_templates_enabled()) {
        return false;
    }

    // Check if we're on a page that the template loader handles
    $is_cablecast_page = is_singular('show') || is_singular('cablecast_channel') ||
        is_tax('cablecast_producer') || is_tax('cablecast_project') ||
        is_post_type_archive('show') || is_post_type_archive('cablecast_channel');

    // Also check for category archives with shows
    if (!$is_cablecast_page && is_category() && function_exists('cablecast_category_has_shows')) {
        $is_cablecast_page = cablecast_category_has_shows(get_queried_object_id());
    }

    if ($is_cablecast_page) {
        // Verify a template file exists
        $file = '';
        if (is_singular('show')) {
            $file = 'single-show.php';
        } elseif (is_singular('cablecast_channel')) {
            $file = 'single-channel.php';
        } elseif (is_tax('cablecast_producer')) {
            $file = 'archive-producer.php';
        } elseif (is_tax('cablecast_project')) {
            $file = 'archive-series.php';
        } elseif (is_post_type_archive('show')) {
            $file = 'archive-show.php';
        } elseif (is_post_type_archive('cablecast_channel')) {
            $file = 'archive-channel.php';
        } elseif (is_category()) {
            $file = 'archive-category.php';
        }

        if ($file && function_exists('cablecast_locate_template')) {
            $template = cablecast_locate_template($file);
            return !empty($template);
        }
    }

    return false;
}

/**
 * Legacy content display filter.
 *
 * This filter is only active when templates are disabled.
 * When templates are enabled, the template files handle display.
 *
 * @param string $content The post content.
 * @return string Modified content.
 */
add_filter('the_content', 'cablecast_content_display');
function cablecast_content_display($content) {
    global $post;

    // Skip if templates are handling this page
    if (cablecast_is_template_page()) {
        return $content;
    }

    // Legacy show display (when templates are disabled)
    // Only run on single show pages - archives should use theme templates
    if ($post != null && $post->post_type == "show" && is_single() && in_the_loop() && is_main_query()) {
        $show_meta = get_post_custom($post->ID);
        $vod_url = get_post_meta($post->ID, 'cablecast_vod_url', true);
        $producer = get_post_meta($post->ID, 'cablecast_producer_name', true);
        $category = get_post_meta($post->ID, 'cablecast_category_name', true);
        $project = get_post_meta($post->ID, 'cablecast_project_name', true);
        $trt = get_post_meta($post->ID, 'cablecast_show_trt', true);
        $vod_poster = get_the_post_thumbnail_url();
        $show_content = "<div>";

        if (is_single()) {
            $show_content .= '[video src="' . esc_url($vod_url) . '" poster="' . esc_url($vod_poster) . '" autoplay="true"]';
        }

        $show_content .= "<p>";
        $show_content .= wp_kses_post($post->post_content);
        $show_content .= "</p>";
        $show_content .= "<ul>";

        if (is_single()) {
            if (!empty($trt)) {
                $show_content .= "<li>Length: ";
                $pretty_trt = gmdate('H:i:s', absint($trt));
                $show_content .= esc_html($pretty_trt) . "</li>";
            }
            if (!empty($producer)) {
                $producer_link = get_term_link(cablecast_replace_commas_in_tag($producer), 'cablecast_producer');
                if (!is_wp_error($producer_link)) {
                    $show_content .= '<li>Producer: <a href="' . esc_url($producer_link) . '">' . esc_html($producer) . '</a></li>';
                }
            }
            if (!empty($project)) {
                $project_link = get_term_link(cablecast_replace_commas_in_tag($project), 'cablecast_project');
                if (!is_wp_error($project_link)) {
                    $show_content .= '<li>Series: <a href="' . esc_url($project_link) . '">' . esc_html($project) . '</a></li>';
                }
            }
            if (!empty($category)) {
                $category_link = get_term_link($category, 'category');
                if (!is_wp_error($category_link)) {
                    $show_content .= '<li>Category: <a href="' . esc_url($category_link) . '">' . esc_html($category) . '</a></li>';
                }
            }
            // Note: Removed duplicate poster display that was in the original
            $show_content .= "</ul>";
            $show_content .= "</div>";
        }

        return do_shortcode($show_content);
    }
    // Legacy channel display (when templates are disabled)
    elseif ($post != null && $post->post_type == 'cablecast_channel' && is_single() && in_the_loop() && is_main_query()) {
        $channel_id = get_post_meta($post->ID, 'cablecast_channel_id', true);
        $schedule_content = "";

        // Validate and sanitize schedule_date parameter
        if (empty($_GET["schedule_date"])) {
            $date = current_time('Y-m-d');
        } else {
            $raw_date = sanitize_text_field(wp_unslash($_GET["schedule_date"]));
            // Strict date format validation (YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
                $parsed = strtotime($raw_date);
                if ($parsed !== false) {
                    $date = date('Y-m-d', $parsed);
                } else {
                    $date = current_time('Y-m-d');
                }
            } else {
                $date = current_time('Y-m-d');
            }
        }
        $prev_date = date('Y-m-d', strtotime($date . "-1days"));
        $next_date = date('Y-m-d', strtotime($date . "+1days"));
        $prev_link = add_query_arg(array('schedule_date' => $prev_date));
        $next_link = add_query_arg(array('schedule_date' => $next_date));

        $schedule_items = cablecast_get_schedules($channel_id, $date);

        // Note: embed_code from Cablecast API is trusted HTML - filtered to allow only safe iframe/video tags
        $channel_embed_code = get_post_meta($post->ID, 'cablecast_channel_live_embed_code', true);
        if (!empty($channel_embed_code)) {
            $allowed_html = array(
                'iframe' => array(
                    'src'             => array(),
                    'width'           => array(),
                    'height'          => array(),
                    'frameborder'     => array(),
                    'allowfullscreen' => array(),
                    'allow'           => array(),
                    'style'           => array(),
                ),
                'video' => array(
                    'src'      => array(),
                    'width'    => array(),
                    'height'   => array(),
                    'controls' => array(),
                    'autoplay' => array(),
                    'style'    => array(),
                ),
                'source' => array(
                    'src'  => array(),
                    'type' => array(),
                ),
            );
            $schedule_content .= '<div class="wrap">' . wp_kses($channel_embed_code, $allowed_html) . '</div>';
        }

        $schedule_content .= '
            <h3>Schedule For ' . esc_html($date) . '</h3>
            <div class="schedule-title-container">
                <div class="schedule-prev-next-btns">
                    <a href="' . esc_url($prev_link) . '" class="!text-brand-accent hover:underline">&laquo; Previous Day</a> | <a href="' . esc_url($next_link) . '" class="!text-brand-accent hover:underline">Next Day &raquo;</a>
                </div>
                <div class="">
                    <form action="">
                        <label for="schedule-date">Choose Date:</label>
                        <input type="date" id="schedule-date" name="schedule-date" value="' . esc_attr($date) . '">
                        <input class="!text-brand-accent hover:underline" type="submit">
                    </form>
                </div>
            </div>
        ';

        $schedule_content .= "<table><thead><tr><th class=\"schedule-time\">Time</th><th class=\"schedule-show\">Show</th></tr></thead><tbody>";
        foreach ($schedule_items as $item) {
            $show_link = get_post_permalink($item->show_post_id);
            if (empty($show_link)) {
                continue;
            }
            $timezone = wp_timezone_string();
            try {
                $time = (new DateTime($item->run_date_time, new DateTimeZone($timezone)))->format('h:i a');
            } catch (Exception $e) {
                $time = esc_html($item->run_date_time);
            }
            $schedule_content .= '<tr><td>' . esc_html($time) . '</td><td><a href="' . esc_url($show_link) . '">' . esc_html($item->show_title) . ' (' . esc_html($item->run_date_time) . ')</a></td></tr>';
        }
        $schedule_content .= "</tbody></table>";

        return $schedule_content;
    } else {
        return $content;
    }
}
