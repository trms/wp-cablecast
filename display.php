<?php

// Enqueue scripts
function cablecast_enqueue_scripts() {
    wp_enqueue_script('cablecast-schedule', plugin_dir_url(__FILE__) . 'js/cablecast-schedule.js', array('jquery'), null, true);
    wp_localize_script('cablecast-schedule', 'cablecast_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'cablecast_enqueue_scripts');

// Filter to handle post types in queries
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

// Content display filter
add_filter('the_content', 'cablecast_content_display');
function cablecast_content_display($content) {
    global $post;
    if ($post->post_type == "show" && in_the_loop() && is_main_query()) {
        // Your existing show content logic
    } else if ($post->post_type == 'cablecast_channel' && is_single() && in_the_loop() && is_main_query()) {
        $channel_id = get_post_meta($post->ID, 'cablecast_channel_id', true);
        $schedule_content = "";
        if (empty($_GET["schedule_date"])) {
            $date = date("Y-m-d");
        } else {
            $date = date('Y-m-d', strtotime($_GET["schedule_date"]));
        }    
        $prev_date = date('Y-m-d', strtotime($date . "-1days"));
        $next_date = date('Y-m-d', strtotime($date . "+1days"));
        $prev_link = add_query_arg(array('schedule_date' => $prev_date));
        $next_link = add_query_arg(array('schedule_date' => $next_date));

        $schedule_items = cablecast_get_schedules($channel_id, $date);

        $schedule_content = "<h3>Schedule For $date</h3>";
        $channel_embed_code = get_post_meta($post->ID, 'cablecast_channel_live_embed_code', true);
        if (empty($channel_embed_code) == false) {
            $schedule_content .= "<div class=\"wrap\">$channel_embed_code</div>";
        }
        $schedule_content .= "<div class=\"schedule-container\">";
        $schedule_content .= "<div class=\"schedule-date-navigation\"><div><a href=\"$prev_link\" class=\"!text-brand-accent hover:underline\">Previous</a> | <a href=\"$next_link\" class=\"!text-brand-accent hover:underline\">Next</a></div>";

        $schedule_content .= "<form id=\"schedule-form\" method=\"get\">";
        $schedule_content .= "<label for=\"schedule_date\">Select Date:</label>";
        $schedule_content .= "<input type=\"date\" id=\"schedule_date\" name=\"schedule_date\" value=\"$date\" data-post-id=\"{$post->ID}\">";
        $schedule_content .= "</form></div>";

        $schedule_content .= "<table><thead><tr><th class=\"schedule-time\">Time</th><th>Show</th></tr></thead><tbody>";
        foreach($schedule_items as $item) {
            $show_link = get_post_permalink($item->show_post_id);
            if (empty($show_link)) { continue; }
            $time = date('h:i a', strtotime($item->run_date_time));
            $title = $item->show_title;
            $schedule_content .= "<tr><td>$time</td><td><a href=\"$show_link\">$item->show_title</a></td></tr>";
        }
        $schedule_content .= "</tbody></table></div>";

        // Check if it's an AJAX request
        if (defined('DOING_AJAX') && DOING_AJAX) {
            echo $schedule_content;
            die();
        }

        return $schedule_content;
    } else {
        return $content;
    }
}

// Handle AJAX request
function cablecast_update_schedule() {
    if (!isset($_POST['schedule_date']) || !isset($_POST['post_id'])) {
        wp_send_json_error('Invalid request');
    }

    $date = date('Y-m-d', strtotime($_POST['schedule_date']));
    $post_id = intval($_POST['post_id']);
    $channel_id = get_post_meta($post_id, 'cablecast_channel_id', true);

    $schedule_items = cablecast_get_schedules($channel_id, $date);

    $schedule_content = "<h3>Schedule For $date</h3>";
    $channel_embed_code = get_post_meta($post_id, 'cablecast_channel_live_embed_code', true);
    if (empty($channel_embed_code) == false) {
        $schedule_content .= "<div class=\"wrap\">$channel_embed_code</div>";
    }
    $schedule_content .= "<div class=\"schedule-date-navigation\">";
    $schedule_content .= "<form id=\"schedule-form\" method=\"get\">";
    $schedule_content .= "<label for=\"schedule_date\">Select Date:</label>";
    $schedule_content .= "<input type=\"date\" id=\"schedule_date\" name=\"schedule_date\" value=\"$date\" data-post-id=\"$post_id\">";
    $schedule_content .= "</form></div>";

    $schedule_content .= "<table><thead><tr><th class=\"schedule-time\">Time</th><th>Show</th></tr></thead><tbody>";
    foreach($schedule_items as $item) {
        $show_link = get_post_permalink($item->show_post_id);
        if (empty($show_link)) { continue; }
        $time = date('h:i a', strtotime($item->run_date_time));
        $title = $item->show_title;
        $schedule_content .= "<tr><td>$time</td><td><a href=\"$show_link\">$item->show_title</a></td></tr>";
    }
    $schedule_content .= "</tbody></table>";

    echo $schedule_content;
    wp_die();
}
add_action('wp_ajax_cablecast_update_schedule', 'cablecast_update_schedule');
add_action('wp_ajax_nopriv_cablecast_update_schedule', 'cablecast_update_schedule');
