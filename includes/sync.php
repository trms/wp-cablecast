<?php

function cablecast_sync_data() {
  // Prevent concurrent sync operations using a transient lock
  $lock_key = 'cablecast_sync_lock';
  $lock_timeout = 300; // 5 minute lock timeout

  if (get_transient($lock_key)) {
    \Cablecast\Logger::log('warning', 'Sync already in progress, skipping this run');
    return;
  }

  // Acquire the lock
  set_transient($lock_key, true, $lock_timeout);

  try {
    $options = get_option('cablecast_options');
    $server = $options["server"];
    \Cablecast\Logger::log('info', "Syncing data for $server");

    $field_response = wp_remote_get("$server/cablecastapi/v1/showfields", array('timeout' => 30));
    if (!is_wp_error($field_response) && wp_remote_retrieve_response_code($field_response) === 200) {
      $field_definitions = json_decode(wp_remote_retrieve_body($field_response));
      if (isset($field_definitions->fieldDefinitions) && isset($field_definitions->showFields)) {
        update_option('cablecast_custom_taxonomy_definitions', $field_definitions);
      }
    } else {
      \Cablecast\Logger::log('error', 'Failed to fetch show field definitions from API');
    }

    $channels = cablecast_get_resources("$server/cablecastapi/v1/channels", 'channels');
    $live_streams = cablecast_get_resources("$server/cablecastapi/v1/livestreams", 'liveStreams');
    $categories = cablecast_get_resources("$server/cablecastapi/v1/categories", 'categories');
    $producers = cablecast_get_resources("$server/cablecastapi/v1/producers", 'producers');
    $projects = cablecast_get_resources("$server/cablecastapi/v1/projects", 'projects');
    $show_fields = cablecast_get_resources("$server/cablecastapi/v1/showfields", 'showFields');
    $field_definitions = cablecast_get_resources("$server/cablecastapi/v1/showfields", 'fieldDefinitions');

    $today = date('Y-m-d', strtotime("now"));
    $two_weeks_from_now = date('Y-m-d', strtotime('+2 weeks'));
    $schedule_sync_url = "$server/cablecastapi/v1/scheduleitems?start=$today&end=$two_weeks_from_now&include_cg_exempt=false&page_size=2000";
    $schedule_items = cablecast_get_resources($schedule_sync_url, 'scheduleItems');

    $shows_payload = cablecast_get_shows_payload();

    cablecast_sync_channels($channels, $live_streams);
    cablecast_sync_projects($projects);
    cablecast_sync_producers($producers);
    cablecast_sync_categories($categories);

    cablecast_sync_shows($shows_payload, $categories, $projects, $producers, $show_fields, $field_definitions);
    cablecast_sync_schedule($schedule_items);
    \Cablecast\Logger::log('info', "Sync finished successfully");
  } catch (Exception $e) {
    \Cablecast\Logger::log('error', "Sync failed with exception: " . $e->getMessage());
  } finally {
    // Always release the lock when done, even if an error occurred
    delete_transient($lock_key);
  }
}

function cablecast_get_shows_payload() {
  $batch_size = 100;
  $options = get_option('cablecast_options');
  $since = get_option('cablecast_sync_since');
  if ($since == FALSE) {
    $since = date("Y-m-d\TH:i:s", strtotime("1900-01-01T00:00:00"));
  }
  $sync_index = get_option('cablecast_sync_index');
  if ($sync_index == FALSE) {
    $sync_index = 0;
  }
  $server = $options["server"];
  \Cablecast\Logger::log('info', "Getting shows since: $since");

  $json_search = "{\"savedShowSearch\":{\"query\":{\"groups\":[{\"orAnd\":\"and\",\"filters\":[{\"field\":\"lastModified\",\"operator\":\"greaterThan\",\"searchValue\":\"$since\"}]}],\"sortOptions\":[{\"field\":\"lastModified\",\"descending\":false},{\"field\":\"title\",\"descending\":false}]},\"name\":\"\"}}";

  // Use wp_remote_post instead of file_get_contents for proper timeout handling
  $search_response = wp_remote_post("$server/cablecastapi/v1/shows/search/advanced", array(
    'timeout' => 30,
    'headers' => array('Content-Type' => 'application/json'),
    'body' => $json_search,
  ));

  if (is_wp_error($search_response)) {
    \Cablecast\Logger::log('error', 'Failed to search shows: ' . $search_response->get_error_message());
    $response = new stdClass();
    $response->shows = [];
    return $response;
  }

  if (wp_remote_retrieve_response_code($search_response) !== 200) {
    \Cablecast\Logger::log('error', 'Show search API returned status: ' . wp_remote_retrieve_response_code($search_response));
    $response = new stdClass();
    $response->shows = [];
    return $response;
  }

  $result = json_decode(wp_remote_retrieve_body($search_response));
  if (!$result || !isset($result->savedShowSearch->results)) {
    \Cablecast\Logger::log('error', 'Invalid JSON response from show search API');
    $response = new stdClass();
    $response->shows = [];
    return $response;
  }

  $total_result_count = count($result->savedShowSearch->results);
  if ($total_result_count <= $sync_index) {
    $sync_index = 0;
    update_option('cablecast_sync_index', $sync_index);
  }

  if ($total_result_count == 0) {
    \Cablecast\Logger::log('info', "No shows to sync");
    $response = new stdClass();
    $response->shows = [];
    return $response;
  }

  $ids = array_slice($result->savedShowSearch->results, $sync_index, $batch_size);
  $processing_result_count = count($ids);
  $end_index = $sync_index + $processing_result_count;

  update_option('cablecast_sync_total_result_count', $total_result_count);
  \Cablecast\Logger::log('info', "Processing $sync_index through $end_index out of $total_result_count results for search");

  $id_query = "";
  foreach ($ids as $id) {
    $id_query .= "&ids[]=$id";
  }

  $url = "$server/cablecastapi/v1/shows?page_size=$batch_size&include=reel,vod,webfile,thumbnail$id_query";
  \Cablecast\Logger::log('info', "Retreving shows from using: $url");

  // Use wp_remote_get instead of file_get_contents for proper timeout handling
  $shows_response = wp_remote_get($url, array('timeout' => 30));

  if (is_wp_error($shows_response)) {
    \Cablecast\Logger::log('error', 'Failed to fetch shows: ' . $shows_response->get_error_message());
    $response = new stdClass();
    $response->shows = [];
    return $response;
  }

  if (wp_remote_retrieve_response_code($shows_response) !== 200) {
    \Cablecast\Logger::log('error', 'Shows API returned status: ' . wp_remote_retrieve_response_code($shows_response));
    $response = new stdClass();
    $response->shows = [];
    return $response;
  }

  $shows_payload = json_decode(wp_remote_retrieve_body($shows_response));
  if (!$shows_payload) {
    \Cablecast\Logger::log('error', 'Invalid JSON response from shows API');
    $response = new stdClass();
    $response->shows = [];
    return $response;
  }

  return $shows_payload;
}

function cablecast_get_resources($url, $key, $ensure_all_loaded = FALSE) {
  $resources = [];
  $paged_url = $url;
  // give an inital high best guess page size to try and do this in one request.
  $page_size = 500;
  try {
    if ($ensure_all_loaded) {
      $paged_url = "$url&page_size=$page_size";
    }

    \Cablecast\Logger::log('info', "Retreiving $key from $paged_url");

    // Use wp_remote_get instead of file_get_contents for proper timeout handling
    $response = wp_remote_get($paged_url, array('timeout' => 30));

    if (is_wp_error($response)) {
      \Cablecast\Logger::log('error', "Failed to fetch $key: " . $response->get_error_message());
      return $resources;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
      \Cablecast\Logger::log('error', "API returned status " . wp_remote_retrieve_response_code($response) . " for $key");
      return $resources;
    }

    $result = json_decode(wp_remote_retrieve_body($response));
    if (!$result) {
      \Cablecast\Logger::log('error', "Invalid JSON response for $key");
      return $resources;
    }

    if ($ensure_all_loaded && isset($result->meta) && $result->meta->count > $result->meta->pageSize) {
      \Cablecast\Logger::log('info', "Not enough schedule items loaded. Increase page size");
      $page_size = $result->meta->count + 10;
      $paged_url = "$url&page_size=$page_size";
      \Cablecast\Logger::log('info', "Retreiving $key from $paged_url");

      $response = wp_remote_get($paged_url, array('timeout' => 60)); // longer timeout for large payloads
      if (is_wp_error($response)) {
        \Cablecast\Logger::log('error', "Failed to fetch $key (expanded): " . $response->get_error_message());
        return $resources;
      }
      if (wp_remote_retrieve_response_code($response) !== 200) {
        \Cablecast\Logger::log('error', "API returned status " . wp_remote_retrieve_response_code($response) . " for $key (expanded)");
        return $resources;
      }
      $result = json_decode(wp_remote_retrieve_body($response));
    }

    if (isset($result->$key)) {
      $resources = $result->$key;
    }
  } catch (Exception $e) {
    \Cablecast\Logger::log('error', "Error retreiving \"$key\": " . $e->getMessage());
  }
  return $resources;
}

function cablecast_sync_shows($shows_payload, $categories, $projects, $producers, $show_fields, $field_definitions) {
  $sync_total_result_count = get_option('cablecast_sync_total_result_count');
  $sync_index = get_option('cablecast_sync_index');
  if ($sync_index == FALSE) {
    $sync_index = 0;
  }

  // Get thumbnail mode setting
  $options = get_option('cablecast_options');
  $thumbnail_mode = isset($options['thumbnail_mode']) ? $options['thumbnail_mode'] : 'local';

  foreach($shows_payload->shows as $show) {
    \Cablecast\Logger::log('debug', "Syncing Show: ($show->id) $show->title");
    $args = array(
        'meta_key' => 'cablecast_show_id',
        'meta_value' => $show->id,
        'post_type' => 'show',
        'post_status' => 'any',
        'posts_per_page' => 1
    );

    $posts = get_posts($args);
    if (count($posts)) {
      $post = $posts[0];

      $update_params = array(
        'ID'            => $post->ID,
        'post_title'    => isset($show->cgTitle) ? $show->cgTitle : $show->title,
        'post_content'  => isset($show->comments) ? $show->comments : '',
        'post_date'     => $show->eventDate
      );

      wp_update_post($update_params);
      
    } else {
      $post = array(
          'post_title'    => isset($show->cgTitle) ? $show->cgTitle : $show->title,
          'post_content'  => isset($show->comments) ? $show->comments : '',
          'post_date'     => $show->eventDate,
          'post_status'   => 'publish',
          'post_type'     => 'show',
          'meta_input'    => array(
            'cablecast_show_id' => $show->id
          )
      );
      $post = get_post(wp_insert_post( $post ));
    }

    $lastModified = get_metadata('post', $post->ID, 'cablecast_last_modified', true);
    if ($lastModified == $show->lastModified) {
      //print "Skipping $show->id: It has not been modified\n";
      //continue;
    }

    $id = $post->ID;

    if (isset($show->vods) && count($show->vods)) {
      $vod = cablecast_extract_id($show->vods[0], $shows_payload->vods);
      if ($vod != NULL) {
        cablecast_upsert_post_meta($id, "cablecast_vod_url", $vod->url);
        cablecast_upsert_post_meta($id, "cablecast_vod_embed", $vod->embedCode);
      }
    }

    if (empty($show->producer) == FALSE) {
      $producer = cablecast_extract_id($show->producer, $producers);
      if ($producer != NULL) {
        cablecast_upsert_post_meta($id, "cablecast_producer_name", $producer->name);
        cablecast_upsert_post_meta($id, "cablecast_producer_id", $producer->id);
        $processed_producer = cablecast_replace_commas_in_tag($producer->name);
        wp_set_post_terms( $id, $processed_producer, 'cablecast_producer');
      }
    }

    if (empty($show->project) == FALSE) {
      $project = cablecast_extract_id($show->project, $projects);
      if ($project != NULL) {
        cablecast_upsert_post_meta($id, "cablecast_project_name", $project->name);
        cablecast_upsert_post_meta($id, "cablecast_project_id", $project->id);
        $processed_project = cablecast_replace_commas_in_tag($project->name);
        wp_set_post_terms( $id, $processed_project, 'cablecast_project');
      }
    }

    if (empty($show->category) == FALSE) {
      $category = cablecast_extract_id($show->category, $categories);
      if ($category != NULL) {
        cablecast_upsert_post_meta($id, "cablecast_category_name", $category->name);
        cablecast_upsert_post_meta($id, "cablecast_category_id", $category->id);
        $term = get_cat_ID( $category->name);
        wp_set_post_terms($id, $term, 'category', true);
      }
    }
    cablecast_upsert_post_meta($id, "cablecast_show_id", $show->id);
    cablecast_upsert_post_meta($id, "cablecast_show_title", $show->title);
    cablecast_upsert_post_meta($id, "cablecast_show_cg_title", $show->cgTitle);
    cablecast_upsert_post_meta($id, "cablecast_show_comments", $show->comments);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_1", $show->custom1);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_2", $show->custom2);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_3", $show->custom3);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_4", $show->custom4);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_5", $show->custom5);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_6", $show->custom6);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_7", $show->custom7);
    cablecast_upsert_post_meta($id, "cablecast_show_custom_8", $show->custom8);

    if (isset($show->customFields)) {
      $terms_to_set = [];
  
      foreach ($show->customFields as $custom_field) {
          // Look up name of field
          $show_field = cablecast_extract_id($custom_field->showField, $show_fields);
          $field_definition = cablecast_extract_id($show_field->fieldDefinition, $field_definitions);
          $tax_name = "cbl-tax-" . $custom_field->showField;
  
          if (taxonomy_exists($tax_name)) {
              if (!isset($terms_to_set[$tax_name])) {
                  $terms_to_set[$tax_name] = [];
              }
              // Append new terms to the taxonomy array
              $terms_to_set[$tax_name][] = $custom_field->fieldValueString;
          }
  
          cablecast_upsert_post_meta($id, $field_definition->name, $custom_field->value);
      }
  
      // Set all collected terms for each taxonomy
      foreach ($terms_to_set as $taxonomy => $terms) {
          // Use array_values to ensure the terms are correctly formatted as an array
          wp_set_post_terms($id, array_values($terms), $taxonomy);
      }
  }

    cablecast_upsert_post_meta($id, "cablecast_show_event_date", $show->eventDate);
    cablecast_upsert_post_meta($id, "cablecast_show_location_id", $show->location);
    cablecast_upsert_post_meta($id, "cablecast_last_modified", $show->lastModified);

    $trt = cablecast_calculate_trt($show, $shows_payload->reels);
    cablecast_upsert_post_meta($id, "cablecast_show_trt", $trt);

    // Handle thumbnails based on mode setting
    if ($thumbnail_mode === 'local') {
      // Original behavior - download thumbnails as WordPress attachments
      if (isset($show->thumbnailImage) && isset($show->thumbnailImage->url)) {
        // Validate URL before downloading
        $thumbnail_url = esc_url_raw($show->thumbnailImage->url);
        if (wp_http_validate_url($thumbnail_url)) {
          $thumbnail_id = cablecast_insert_attachment_from_url($thumbnail_url, $id, true);
          if ($thumbnail_id) {
            set_post_thumbnail($id, $thumbnail_id);
          }
        } else {
          \Cablecast\Logger::log('warning', "Invalid thumbnail URL for show $show->id: " . $show->thumbnailImage->url);
        }
      }
    } else {
      // Remote hosting - save URL to meta for CDN-based display
      if (isset($show->thumbnailImage) && isset($show->thumbnailImage->url)) {
        // Validate URL before saving to prevent storing malicious URLs
        $thumbnail_url = esc_url_raw($show->thumbnailImage->url);
        if (wp_http_validate_url($thumbnail_url)) {
          cablecast_upsert_post_meta($id, "cablecast_thumbnail_url", $thumbnail_url);
        } else {
          \Cablecast\Logger::log('warning', "Invalid thumbnail URL for show $show->id: " . $show->thumbnailImage->url);
        }
      }
    }

    $since = get_option('cablecast_sync_since');
    $sync_index = $sync_index + 1;
    update_option('cablecast_sync_index', $sync_index);
    if ($sync_index >= $sync_total_result_count && strtotime($show->lastModified) >= strtotime($since)) {
      $since = $show->lastModified;
      update_option('cablecast_sync_total_result_count', 0);
      update_option('cablecast_sync_index', 0);
      update_option('cablecast_sync_since', $since);

      // Run orphan detection after a full sync cycle completes
      cablecast_detect_orphan_posts();
    }
  }
}

/**
 * Detect shows in WordPress that may no longer exist in Cablecast.
 * Logs warnings for potential orphans but does not auto-delete.
 */
function cablecast_detect_orphan_posts() {
  $options = get_option('cablecast_options');
  $server = $options["server"] ?? '';

  if (empty($server)) {
    return;
  }

  // Only run orphan detection once per day to avoid excessive API calls
  $last_check = get_option('cablecast_orphan_check_last_run', 0);
  $one_day_ago = time() - DAY_IN_SECONDS;

  if ($last_check > $one_day_ago) {
    return;
  }

  update_option('cablecast_orphan_check_last_run', time());

  // Get all Cablecast show IDs from the API
  $api_url = "$server/cablecastapi/v1/shows?page_size=10000&fields=id";
  $response = wp_remote_get($api_url, array('timeout' => 60));

  if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
    \Cablecast\Logger::log('warning', 'Could not fetch show IDs for orphan detection');
    return;
  }

  $body = json_decode(wp_remote_retrieve_body($response));
  if (!$body || !isset($body->shows)) {
    return;
  }

  // Extract all Cablecast show IDs
  $api_show_ids = array_map(function($show) {
    return (int) $show->id;
  }, $body->shows);

  // Get all WordPress show posts with cablecast_show_id
  $wp_shows = get_posts(array(
    'post_type' => 'show',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'meta_key' => 'cablecast_show_id',
    'fields' => 'ids',
  ));

  $orphan_count = 0;
  foreach ($wp_shows as $post_id) {
    $cablecast_id = (int) get_post_meta($post_id, 'cablecast_show_id', true);
    if ($cablecast_id && !in_array($cablecast_id, $api_show_ids, true)) {
      $orphan_count++;
      $post_title = get_the_title($post_id);
      \Cablecast\Logger::log('warning', "Potential orphan: Show '$post_title' (WP ID: $post_id, Cablecast ID: $cablecast_id) not found in Cablecast API");
    }
  }

  if ($orphan_count > 0) {
    \Cablecast\Logger::log('info', "Orphan detection complete: $orphan_count potential orphan(s) found");
  } else {
    \Cablecast\Logger::log('info', "Orphan detection complete: No orphans found");
  }
}

function cablecast_calculate_trt($show, $reels_payload) {
    $reels = [];
    foreach($show->reels as $reel_id) {
        $reels[] = cablecast_extract_id($reel_id, $reels_payload);
    }
    $trt = 0;
    foreach($reels as $reel) {
        $trt += $reel->length;
    }
    return $trt;
}

function cablecast_sync_channels($channels, $live_streams) {
  foreach($channels as $channel) {
    $args = array(
        'meta_key' => 'cablecast_channel_id',
        'meta_value' => $channel->id,
        'post_type' => 'cablecast_channel',
        'post_status' => 'any',
        'posts_per_page' => 1
    );

    $posts = get_posts($args);

    if (count($posts)) {
      $post = $posts[0];
    } else {
      $post = array(
          'post_title'    => $channel->name,
          'post_status'   => 'publish',
          'post_type'     => 'cablecast_channel'
      );
      $post = get_post(wp_insert_post( $post ));
    }

    if (empty($channel->liveStreams) == FALSE) {
      $live_stream = cablecast_extract_id($channel->liveStreams[0], $live_streams);
      cablecast_upsert_post_meta($post->ID, 'cablecast_channel_live_embed_code', $live_stream->embedCode);
    }

    cablecast_upsert_post_meta($post->ID, 'cablecast_channel_id', $channel->id);
  }
}

function cablecast_get_show_post_by_id($id) {
  $post = NULL;
  $args = array(
      'meta_key' => 'cablecast_show_id',
      'meta_value' => $id,
      'post_type' => 'show',
      'post_status' => 'any',
      'posts_per_page' => 1
  );

  $posts = get_posts($args);

  if (count($posts)) {
    $post = $posts[0];
  }
  return $post;
}

function cablecast_get_schedule_item_by_id($id) {
  $post = NULL;
  $args = array(
      'meta_key' => 'cablecast_show_id',
      'meta_value' => $id,
      'post_type' => 'show',
      'post_status' => 'any',
      'posts_per_page' => 1
  );

  $posts = get_posts($args);

  if (count($posts)) {
    $post = $posts[0];
  }
  return $post;
}

/**
 * Sync Cablecast schedule items into WP DB with global pruning:
 * After syncing, delete any rows whose schedule_item_id isn't in the payload (global scope).
 *
 * @param array|object $scheduleItems
 * @return bool True if work ran (hash changed or no prior hash), false if skipped.
 */
function cablecast_sync_schedule($scheduleItems) {
  global $wpdb;

  // ---- Early-exit guard: compare payload hashes ----
  $payload_json = wp_json_encode($scheduleItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $new_hash     = md5($payload_json);
  $option_key   = 'cablecast_schedule_items_hash';

  $prev_hash = get_option($option_key, '');
  if (!empty($prev_hash) && hash_equals($prev_hash, $new_hash)) {
    \Cablecast\Logger::log('info', "Schedule items unchanged; skipping DB sync.");
    return false; // unchanged payload; skip DB work
  }

  $table = $wpdb->prefix . 'cablecast_schedule_items';

  // Collect *all* schedule_item_ids from the payload to use for global pruning
  $all_payload_ids = [];

  foreach ($scheduleItems as $item) {
    if (!$item->show) { continue; }

    $schedule_item_id = (int)$item->id;
    $all_payload_ids[] = $schedule_item_id;

    $is_deleted = isset($item->deleted) ? (bool)$item->deleted : false;

    // Lookup existing row
    $existing_row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE schedule_item_id = %d", $schedule_item_id)
    );

    // Map show post
    $show = cablecast_get_show_post_by_id($item->show);
    if (!$show) { continue; }

    // Normalize time to UTC
    try {
      $run_date_time = new DateTime($item->runDateTime);
      $run_date_time->setTimezone(new DateTimeZone('UTC'));
      $run_date_time_str = $run_date_time->format('Y-m-d H:i:s');
    } catch (Exception $e) {
      continue; // skip bad datetime
    }

    if (empty($existing_row) && $is_deleted === false) {
      // Insert
      $wpdb->insert(
        $table,
        array(
          'run_date_time'    => $run_date_time_str,
          'show_id'          => (int)$item->show,
          'show_title'       => $show->post_title,
          'show_post_id'     => (int)$show->ID,
          'channel_id'       => (int)$item->channel,
          'channel_post_id'  => 0,
          'schedule_item_id' => $schedule_item_id,
          'cg_exempt'        => (int)!empty($item->cgExempt),
        ),
        array('%s','%d','%s','%d','%d','%d','%d','%d')
      );
    } else if ($is_deleted === false) {
      // Update
      $wpdb->update(
        $table,
        array(
          'run_date_time'    => $run_date_time_str,
          'show_id'          => (int)$item->show,
          'show_title'       => $show->post_title,
          'show_post_id'     => (int)$show->ID,
          'channel_id'       => (int)$item->channel,
          'channel_post_id'  => 99,
          'schedule_item_id' => $schedule_item_id,
          'cg_exempt'        => (int)!empty($item->cgExempt),
        ),
        array('schedule_item_id' => $schedule_item_id),
        array('%s','%d','%s','%d','%d','%d','%d','%d'),
        array('%d')
      );
    } else {
      // Delete (explicitly flagged as deleted from remote)
      $wpdb->delete($table, array('schedule_item_id' => $schedule_item_id), array('%d'));
    }
  }

  // ---- Global prune: delete any DB rows not in the payload ----
  // Deduplicate incoming IDs
  $all_payload_ids = array_values(array_unique(array_map('intval', $all_payload_ids)));

  if (empty($all_payload_ids)) {
    // If the payload is empty and represents the complete dataset, wipe the table.
    // (This matches your requirement: delete any id not in the change set — here that's "all".)
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DELETE FROM {$table}");
  } else {
    // Get current IDs from DB
    $existing_ids = $wpdb->get_col("SELECT schedule_item_id FROM {$table}");
    $existing_ids = array_map('intval', $existing_ids);

    // Compute rows to delete
    $to_delete = array_values(array_diff($existing_ids, $all_payload_ids));

    // Delete in chunks to keep placeholder lists reasonable
    $chunk_size = 500;
    for ($i = 0; $i < count($to_delete); $i += $chunk_size) {
      $chunk = array_slice($to_delete, $i, $chunk_size);
      if (empty($chunk)) { continue; }

      $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
      $sql = $wpdb->prepare(
        "DELETE FROM {$table} WHERE schedule_item_id IN ($placeholders)",
        $chunk
      );
      $wpdb->query($sql);
    }
  }

  // ---- Persist the new hash after successful processing ----
  if ($prev_hash === '') {
    add_option($option_key, $new_hash, '', 'no');
  } else {
    update_option($option_key, $new_hash, 'no');
  }

  return true;
}

function cablecast_sync_categories($categories) {
  // ---- Early-exit guard: compare payload hashes ----
  $payload_json = wp_json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $new_hash     = md5($payload_json);
  $option_key   = 'cablecast_categories_items_hash';

  $prev_hash = get_option($option_key, '');
  if (!empty($prev_hash) && hash_equals($prev_hash, $new_hash)) {
    \Cablecast\Logger::log('info', "Category items unchanged; skipping DB sync.");
    return false; // unchanged payload; skip DB work
  }

  foreach ($categories as $category) {
    $term = term_exists( $category->name, 'category' ); // array is returned if taxonomy is given
    if ($term == NULL) {
      wp_insert_term(
          $category->name,   // the term
          'category' // the taxonomy
      );
    }
  }

  // ---- Persist the new hash after successful processing ----
  if ($prev_hash === '') {
    add_option($option_key, $new_hash, '', 'no');
  } else {
    update_option($option_key, $new_hash, 'no');
  }
}

function cablecast_sync_projects($projects) {
  // ---- Early-exit guard: compare payload hashes ----
  $payload_json = wp_json_encode($projects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $new_hash     = md5($payload_json);
  $option_key   = 'cablecast_projects_items_hash';

  $prev_hash = get_option($option_key, '');
  if (!empty($prev_hash) && hash_equals($prev_hash, $new_hash)) {
    \Cablecast\Logger::log('info', "Project items unchanged; skipping DB sync.");
    return false; // unchanged payload; skip DB work
  }


  foreach ($projects as $project) {
    $processed = cablecast_replace_commas_in_tag($project->name);
    $term = term_exists( $processed, 'cablecast_project' ); // array is returned if taxonomy is given
    if ($term == NULL) {
      wp_insert_term(
          $processed,   // the term
          'cablecast_project', // the taxonomy
          array(
              'description' => empty($project->description) ? '' : $project->description,
          )
      );
    } else {
      wp_update_term($term['term_id'], 'cablecast_project', array(
        'description' => empty($project->description) ? '' : $project->description,
      ));
    }
  }

  // ---- Persist the new hash after successful processing ----
  if ($prev_hash === '') {
    add_option($option_key, $new_hash, '', 'no');
  } else {
    update_option($option_key, $new_hash, 'no');
  }
}

function cablecast_sync_producers($producers) {
  // ---- Early-exit guard: compare payload hashes ----
  $payload_json = wp_json_encode($producers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $new_hash     = md5($payload_json);
  $option_key   = 'cablecast_producers_items_hash';

  $prev_hash = get_option($option_key, '');
  if (!empty($prev_hash) && hash_equals($prev_hash, $new_hash)) {
    \Cablecast\Logger::log('info', "Producer items unchanged; skipping DB sync.");
    return false; // unchanged payload; skip DB work
  }
  
  foreach ($producers as $producer) {
    $processed = cablecast_replace_commas_in_tag($producer->name);
    if (empty($processed)) { return; }

    $term = term_exists( $processed, 'cablecast_producer' ); // array is returned if taxonomy is given
    if ($term == NULL) {
      $term = wp_insert_term(
          $processed,   // the term
          'cablecast_producer' // the taxonomy
      );
    } else {
      wp_update_term($term['term_id'], 'cablecast_producer', array(
        'description' => empty($project->description) ? '' : $project->description,
      ));
    }
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_address', $producer->address);
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_contact', $producer->contact);
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_email', $producer->email);
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_name', $producer->name);
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_notes', $producer->notes);
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_phone_one', $producer->phoneOne);
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_phone_two', $producer->phoneTwo);
    cablecast_upsert_term_meta($term['term_id'], 'cablecast_producer_website', $producer->website);
  }

  // ---- Persist the new hash after successful processing ----
  if ($prev_hash === '') {
    add_option($option_key, $new_hash, '', 'no');
  } else {
    update_option($option_key, $new_hash, 'no');
  }
}

function cablecast_replace_commas_in_tag($tag) {
  return str_replace(',', '-', $tag);
}

function cablecast_extract_id($id, $records) {
    $item = null;
    foreach($records as $record) {
        if ($id == $record->id) {
            $item = $record;
            break;
        }
    }
    return $item;
}

/**
 * Insert an attachment from an URL address.
 *
 * @param  String $url
 * @param  Int    $post_id
 * @param  Array  $meta_data
 * @return Int    Attachment ID
 */
function cablecast_insert_attachment_from_url($url, $post_id, $dynamic_files_api=false) {
  
  $name = basename($url);
  $args = array(
      'title' => $name,
      'post_type' => 'attachment',
      'post_status' => 'any',
      'posts_per_page' => -1
  );

  $posts = get_posts($args);
  if (count($posts)) {
    return $posts[0]->ID;
  }

	if( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC . '/class-http.php' );

	$http = new WP_Http();
	$response = $http->request( $url, array('timeout' => 20));

	if (is_wp_error($response) || $response['response']['code'] != 200 ) { 
    echo "Got an error";
	  return;
	}
  
  $file_name = $name;
  if ($dynamic_files_api) {
    $content_type = $response['headers']['content-type'];
    $file_name = $name . ".jpg";
    if ($content_type == "image/png") {
      $file_name = $name . ".png";
    }
  }

	$upload = wp_upload_bits( $file_name, null, $response['body'] );
	if( !empty( $upload['error'] ) ) {
		return false;
	}

	$file_path = $upload['file'];
	$file_name = basename( $file_path );
	$file_type = wp_check_filetype( $file_name, null );
	$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
	$wp_upload_dir = wp_upload_dir();

	$post_info = array(
		'guid'				=> $wp_upload_dir['url'] . '/' . $file_name,
		'post_mime_type'	=> $file_type['type'],
		'post_title'		=> $name,
		'post_content'		=> '',
		'post_status'		=> 'inherit',
	);

	// Create the attachment
	$attach_id = wp_insert_attachment( $post_info, $file_path, $post_id );

	// Include image.php
	require_once( ABSPATH . 'wp-admin/includes/image.php' );

	// Define attachment metadata
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

	// Assign metadata to attachment
	wp_update_attachment_metadata( $attach_id,  $attach_data );

	return $attach_id;

}

function cablecast_upsert_post_meta($id, $name, $value) {
  if ($value == NULL) { $value = ''; }
  if ( ! add_post_meta( $id, $name, $value, true ) ) {
    update_post_meta ( $id, $name, $value );
  }
}

function cablecast_upsert_term_meta($id, $name, $value) {
  if ($value == NULL) { $value = ''; }
  if ( ! add_term_meta( $id, $name, $value, true ) ) {
    update_term_meta ( $id, $name, $value );
  }
}

/**
 * @deprecated Use \Cablecast\Logger::log() instead
 */
function cablecast_log ($message) {
  \Cablecast\Logger::log('info', $message);
}

/**
 * Cleanup local thumbnails when user switches to remote hosting.
 * Runs in batches during cron to avoid timeout issues.
 */
function cablecast_cleanup_local_thumbnails() {
  $options = get_option('cablecast_options');

  // Only run if deletion requested AND in remote mode
  if (empty($options['delete_local_thumbnails']) || ($options['thumbnail_mode'] ?? 'local') !== 'remote') {
    return;
  }

  $batch_size = 25;

  // Find show posts with featured images
  $args = [
    'post_type' => 'show',
    'meta_query' => [
      ['key' => '_thumbnail_id', 'compare' => 'EXISTS']
    ],
    'posts_per_page' => $batch_size,
    'fields' => 'ids'
  ];
  $posts = get_posts($args);

  if (empty($posts)) {
    // Done - clear the flag
    $options['delete_local_thumbnails'] = false;
    update_option('cablecast_options', $options);
    \Cablecast\Logger::log('info', "Thumbnail cleanup complete");
    return;
  }

  foreach ($posts as $post_id) {
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id) {
      wp_delete_attachment($thumbnail_id, true);
      delete_post_meta($post_id, '_thumbnail_id');
      \Cablecast\Logger::log('info', "Deleted thumbnail $thumbnail_id for show $post_id");
    }
  }

  \Cablecast\Logger::log('info', "Processed $batch_size thumbnails for deletion, more may remain");
}
