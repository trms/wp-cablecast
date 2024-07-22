<?php

add_filter('pre_get_posts', 'cablecast_query_post_type');
function cablecast_query_post_type($query) {
  if(is_category() || is_tag()) {
    $post_type = get_query_var('post_type');
    if($post_type) {
      $post_type = $post_type;
    } else {
      $post_type = array('post','show');
    }
    $query->set('post_type',$post_type);
    return $query;
  }
}

add_filter('the_content','cablecast_content_display');
function cablecast_content_display($content){
  global $post;
  if ($post->post_type == "show" && in_the_loop() && is_main_query()) {
      $show_meta = get_post_custom($post->ID);
      $vod_url = get_post_meta($post->ID, 'cablecast_vod_url', true);
      $producer = get_post_meta($post->ID, 'cablecast_producer_name', true);
      $category = get_post_meta($post->ID, 'cablecast_category_name', true);
      $project = get_post_meta($post->ID, 'cablecast_project_name', true);
      $trt = get_post_meta($post->ID, 'cablecast_show_trt', true);
      $show_content =  "<div>";
      if (is_single()) {
        $vod_poster = get_the_post_thumbnail_url();
        $show_content .= "[video src=\"$vod_url\" poster=\"$vod_poster\" autoplay=\"true\"]";
      }
      $show_content .= "<p>";
      $show_content .= $post->post_content;
      $show_content .= "</p>";
      $show_content .= "<ul>";
      if (is_single()) {
        if (empty($trt) == FALSE) {
          $show_content .= "<li>Length: ";
          $pretty_trt = gmdate('H:i:s', $trt);
          $show_content .= "$pretty_trt</li>";
        }
        if (empty($producer) == false) {
          $producer_link = get_term_link(cablecast_replace_commas_in_tag($producer), 'cablecast_producer');
          $show_content .= "<li>Producer: ";
          $show_content .= "<a href=\"$producer_link\">$producer</a></li>";
        }
        if (empty($project) == false) {
          $project_link = get_term_link(cablecast_replace_commas_in_tag($project), 'cablecast_project');
          $show_content .= "<li>Series: ";
          $show_content .= "<a href=\"$project_link\">$project</a></li>";
        }
        if (empty($category) == false) {
          $category_link = get_term_link($category, 'category');
          $show_content .= "<li>Category: ";
          $show_content .= "<a href=\"$category_link\">$category</a></li>";
        }
        $show_content .= "</ul>";
        $show_content .= "</div>";
      }
      return do_shortcode($show_content);
  } else if ($post->post_type == 'cablecast_channel' && is_single() && in_the_loop() && is_main_query()) {
    $channel_id = get_post_meta($post->ID, 'cablecast_channel_id', true);
    $schedule_content = "";
    if (empty($_GET["schedule_date"])) {
      $date = date("Y-m-d");
      //echo "No schedule_date provided. Using today's date: $date<br>";
    } else {
      $date = date('Y-m-d', strtotime($_GET["schedule_date"]));
      // echo "schedule_date provided: $date<br>";
    }    
    $prev_date = date('Y-m-d', strtotime($date . "-1days"));
    $next_date = date('Y-m-d', strtotime($date . "+1days"));
    $prev_link = add_query_arg(array('schedule_date' => $prev_date));
    $next_link = add_query_arg(array('schedule_date' => $next_date));

    $schedule_items = cablecast_get_schedules($channel_id, $date);
    
    // Debug statement to check schedule_items
    // echo "Schedule items fetched for $date: ";
    // var_dump($schedule_items);
    
    $channel_embed_code = get_post_meta($post->ID, 'cablecast_channel_live_embed_code', true);
    if (empty($channel_embed_code) == false) {
      $schedule_content .= "<div class=\"wrap\">$channel_embed_code</div>";
    }
    $schedule_content .= "<div class=\"schedule-container\">";
    $schedule_content .= "<div class=\"schedule-header-container\">";
    $schedule_content .= "<h3 class=\"heading-text-color\">Schedule for " . date("F j, Y", strtotime($date)) . "</h3>";

    $schedule_content .= "<div class=\"schedule-date-navigation\">";
    $schedule_content .= "<a href=\"$prev_link\" class=\"!text-brand-accent hover:underline\">« Previous</a>";

    $schedule_content .= "<form method=\"get\">";
    //$schedule_content .= "<label for=\"schedule_date\">Select Date:</label>";
    $schedule_content .= "<input type=\"date\" id=\"schedule_date\" name=\"schedule_date\" value=\"$date\" onchange=\"this.form.submit()\">";
    $schedule_content .= "</form>";

    $schedule_content .= "<a href=\"$next_link\" class=\"!text-brand-accent hover:underline schedule-next-btn\">Next »</a>";
    $schedule_content .= "</div>";
    $schedule_content .= "</div>";

    $schedule_content .= "<table><thead><tr><th class=\"schedule-time\">Time</th><th>Show</th></tr></thead><tbody>";

    if (empty($schedule_items)) {
      $schedule_content .= "<tr><td colspan=\"2\" class=\"text-center\">No shows scheduled for today. Check out some of our <a href=\"/shows\" class=\"!text-brand-accent hover:underline\">other programming</a> in the meantime!</td></tr>";
    } else {
        foreach ($schedule_items as $item) {
            $show_link = get_post_permalink($item->show_post_id);
            if (empty($show_link)) { continue; }
            $time = date('h:i a', strtotime($item->run_date_time));
            $title = $item->show_title;
            $schedule_content .= "<tr><td>$time</td><td><a href=\"$show_link\" class=\"!text-brand-accent hover:underline\">$item->show_title</a></td></tr>";
        }
    }

    $schedule_content .= "</tbody></table></div>";

    return $schedule_content;
  } else {
    return $content;
  }
}