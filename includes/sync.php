<?php

function cablecast_sync_data() {
  $options = get_option('cablecast_options');
  $server = $options["server"];
  cablecast_log ("Syncing data for $server");

  $channels = cablecast_get_resources("$server/cablecastapi/v1/channels", 'channels');
  $live_streams = cablecast_get_resources("$server/cablecastapi/v1/livestreams", 'liveStreams');
  $categories = cablecast_get_resources("$server/cablecastapi/v1/categories", 'categories');
  $producers = cablecast_get_resources("$server/cablecastapi/v1/producers", 'producers');
  $projects = cablecast_get_resources("$server/cablecastapi/v1/projects", 'projects');
  $show_fields = cablecast_get_resources("$server/cablecastapi/v1/showfields", 'showFields');
  $field_definitions = cablecast_get_resources("$server/cablecastapi/v1/showfields", 'fieldDefinitions');

  $two_days_ago = date('Y-m-d', strtotime("-2days"));
  $schedule_sync_url = "$server/cablecastapi/v1/scheduleitems?start=$two_days_ago&page_size=500";
  $schedule_items = cablecast_get_resources($schedule_sync_url, 'scheduleItems');

  $shows_payload = cablecast_get_shows_payload();

  cablecast_sync_channels($channels, $live_streams);
  cablecast_sync_projects($projects);
  cablecast_sync_producers($producers);
  cablecast_sync_categories($categories);

  cablecast_sync_shows($shows_payload, $categories, $projects, $producers, $show_fields, $field_definitions);
  cablecast_sync_schedule($schedule_items);
  cablecast_log( "Finished");
}

function cablecast_get_shows_payload() {
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
  cablecast_log ("Getting shows since: $since" );

  $json_search = "{\"savedShowSearch\":{\"query\":{\"groups\":[{\"orAnd\":\"and\",\"filters\":[{\"field\":\"lastModified\",\"operator\":\"greaterThan\",\"searchValue\":\"$since\"}]}],\"sortOptions\":[{\"field\":\"lastModified\",\"descending\":false},{\"field\":\"title\",\"descending\":false}]},\"name\":\"\"}}";

  $opts = array('http' =>
      array(
          'method'  => 'POST',
          'header'  => 'Content-Type: application/json',
          'content' => $json_search,
          'ignore_errors' => true
      )
  );
  $context = stream_context_create($opts);
  $result = file_get_contents("$server/cablecastapi/v1/shows/search/advanced", false, $context);
  $result = json_decode($result);


  $total_result_count = count($result->savedShowSearch->results);
  if ($total_result_count <= $sync_index) {
    $sync_index = 0;
    update_option('cablecast_sync_index', $sync_index);
  }

  $ids = array_slice($result->savedShowSearch->results, $sync_index, 100);
  $processing_result_count = count($ids);
  $end_index = $sync_index + $processing_result_count;

  update_option('cablecast_sync_total_result_count', $total_result_count);
  cablecast_log("Processing $sync_index through $end_index out of $total_result_count results for search");

  $id_query = "";
  foreach ($ids as $id) {
    $id_query .= "&ids[]=$id";
  }

  $url = "$server/cablecastapi/v1/shows?page_size=100&include=reel,vod,webfile$id_query";
  cablecast_log("Retreving shows from using: $url");

  $shows_json = file_get_contents($url);
  $shows_payload = json_decode($shows_json);

  return $shows_payload;
}

function cablecast_get_resources($url, $key) {
  $resources = [];
  try {
    cablecast_log("Retreiving $key from $url");
    $result = json_decode(file_get_contents($url));
    $resources = $result->$key;
  } catch (Exception $e) {
    cablecast_log("Error retreiving \"$key\"" . $e->message);
  }
  return $resources;
}

function cablecast_sync_shows($shows_payload, $categories, $projects, $producers, $show_fields, $field_definitions) {
  $sync_total_result_count = get_option('cablecast_sync_total_result_count');
  $sync_index = get_option('cablecast_sync_index');
  if ($sync_index == FALSE) {
    $sync_index = 0;
  }

  foreach($shows_payload->shows as $show) {
    cablecast_log ("Syncing Show: ($show->id) $show->title");
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
        'post_content'  => isset($show->comments) ? $show->comments : ''
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

    if (isset($show->showThumbnailOriginal)) {
      $webFile = cablecast_extract_id($show->showThumbnailOriginal, $shows_payload->webFiles);
      if ($webFile != NULL) {
        $thumbnail_id = cablecast_insert_attachment_from_url($webFile, $id);
        set_post_thumbnail( $id, $thumbnail_id );
      }
    }

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
      foreach ($show->customFields as $custom_field) {
        // Look up name of field
        $show_field = cablecast_extract_id($custom_field->showField, $show_fields);
        $field_definition = cablecast_extract_id($show_field->fieldDefinition, $field_definitions);
        cablecast_upsert_post_meta($id,  $field_definition->name, $custom_field->value);
      }
    }

    cablecast_upsert_post_meta($id, "cablecast_show_event_date", $show->eventDate);
    cablecast_upsert_post_meta($id, "cablecast_show_location_id", $show->location);
    cablecast_upsert_post_meta($id, "cablecast_last_modified", $show->lastModified);

    $trt = cablecast_calculate_trt($show, $shows_payload->reels);
    cablecast_upsert_post_meta($id, "cablecast_show_trt", $trt);

    $since = get_option('cablecast_sync_since');
    $sync_index = $sync_index + 1;
    update_option('cablecast_sync_index', $sync_index);
    if ($sync_index >= $sync_total_result_count && strtotime($show->lastModified) >= strtotime($since)) {
      $since = $show->lastModified;
      update_option('cablecast_sync_total_result_count', 0);
      update_option('cablecast_sync_index', 0);
      update_option('cablecast_sync_since', $since);
    }
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

function cablecast_sync_schedule($scheduleItems) {
  global $wpdb;
  foreach($scheduleItems as $item) {
    if (!$item->show) { continue; }
    $table = $wpdb->prefix . 'cablecast_schedule_items';
    $existing_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE schedule_item_id=%d", $item->id));
    $show = cablecast_get_show_post_by_id($item->show);
    if (!$show) { continue; }
    if (empty($existing_row) && $item->deleted == FALSE) {
      $wpdb->insert(
      	$table,
        	array(
        		'run_date_time' => $item->runDateTime,
        		'show_id' => $item->show,
            'show_title' => $show->post_title,
            'show_post_id' => $show->ID,
            'channel_id' => $item->channel,
            'channel_post_id' => 0,
            'schedule_item_id' => $item->id
        	)
      );
    } else if ($item->deleted == FALSE){
      $wpdb->update(
        $table,
        array(
          'run_date_time' => $item->runDateTime,
          'show_id' => $item->show,
          'show_title' => $show->post_title,
          'show_post_id' => $show->ID,
          'channel_id' => $item->channel,
          'channel_post_id' => 99,
          'schedule_item_id' => $item->id
        ),
        array(
          'schedule_item_id' => $item->id
        )
      );
    } else {
      $wpdb->delete(
        $table,
        array(
          'schedule_item_id' => $item->id
        )
      );
    }
  }
}

function cablecast_sync_categories($categories) {
  foreach ($categories as $category) {
    $term = term_exists( $category->name, 'category' ); // array is returned if taxonomy is given
    if ($term == NULL) {
      wp_insert_term(
          $category->name,   // the term
          'category' // the taxonomy
      );
    }
  }
}

function cablecast_sync_projects($projects) {
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
}

function cablecast_sync_producers($producers) {
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
function cablecast_insert_attachment_from_url($webFile, $post_id = null) {
  $url = $webFile->url;
  $args = array(
      'title' => $webFile->name,
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

	if( $response['response']['code'] != 200 ) {
		return false;
	}

	$upload = wp_upload_bits( basename($url), null, $response['body'] );
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
		'post_title'		=> $webFile->name,
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

function cablecast_log ($message) {
  error_log("[Cablecast] $message");
}
