<?php


function cablecast_sync_data() {
  $options = get_option('cablecast_options');
  $server = $options["server"];
  // TODO When Cablecast API Supports including these, take them out to make less api calls
  $channels = json_decode(file_get_contents( "$server/cablecastapi/v1/channels"));
  $live_streams = json_decode(file_get_contents( "$server/cablecastapi/v1/livestreams"));
  $categories = json_decode(file_get_contents( "$server/cablecastapi/v1/categories"));
  $producers = json_decode(file_get_contents( "$server/cablecastapi/v1/producers"));
  $projects = json_decode(file_get_contents( "$server/cablecastapi/v1/projects"));
  $two_days_ago = date('Y-m-d', strtotime("-2days"));
  $schedule_items = json_decode(file_get_contents( "$server/cablecastapi/v1/scheduleitems?start=$two_days_ago&page_size=500"));

  $categories = $categories->categories;
  $projects = $projects->projects;
  $producers = $producers->producers;
  $channels = $channels->channels;
  $live_streams = $live_streams->liveStreams;
  $schedule_items = $schedule_items->scheduleItems;

  $shows_payload = cablecast_get_shows_payload();

  cablecast_sync_shows($shows_payload, $categories, $projects, $producers);
  cablecast_sync_channels($channels, $live_streams);
  cablecast_sync_projects($projects);
  cablecast_sync_categories($categories);
  cablecast_sync_schedule($schedule_items);


}

function cablecast_get_shows_payload() {
  $options = get_option('cablecast_options');
  $since = get_option('cablecast_sync_since');
  if ($since == FALSE) {
    $since = date("Y-m-d\TH:i:s", strtotime("1900-01-01T00:00:00"));
  }
  $server = $options["server"];
  print "Syncing data for $server...\n\n";
  print "Getting shows since: $since\n";

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
  $ids = array_slice($result->savedShowSearch->results, 0, 200);

  $id_query = "";
  foreach ($ids as $id) {
    $id_query .= "&ids[]=$id";
  }

  $url = "$server/cablecastapi/v1/shows?page_size=50&include=reel,vod,webfile$id_query";
  $shows_json = file_get_contents($url);
  $shows_payload = json_decode($shows_json);

  return $shows_payload;
}

function cablecast_sync_shows($shows_payload, $categories, $projects, $producers) {
  foreach($shows_payload->shows as $show) {
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
    } else {
      $post = array(
          'post_status'   => 'publish',
          'post_type'     => 'show'
      );
      $post = get_post(wp_insert_post( $post ));
    }

    $lastModified = get_metadata('post', $post->ID, 'cablecast_last_modified', true);
    if ($lastModified == $show->lastModified) {
      //print "Skipping $show->id: It has not been modified\n";
      //continue;
    }

    $id = $post->ID;

    $post->post_title = isset($show->cgTitle) ? $show->cgTitle : $show->title;
    $post->post_content = isset($show->comments) ? $show->title : '';

    $id = wp_update_post( $post );

    if (isset($show->showThumbnailOriginal)) {
      $webFile = cablecast_extract_id($show->showThumbnailOriginal, $shows_payload->webFiles);
      $thumbnail_id = cablecast_insert_attachment_from_url($webFile, $id);
      set_post_thumbnail( $id, $thumbnail_id );
    }

    if (isset($show->vods) && count($show->vods)) {
      $vod = cablecast_extract_id($show->vods[0], $shows_payload->vods);
      cablecast_upsert_post_meta($id, "cablecast_vod_url", $vod->url);
      cablecast_upsert_post_meta($id, "cablecast_vod_embed", $vod->embedCode);
    }

    if (empty($show->producer) == FALSE) {
      $producer = cablecast_extract_id($show->producer, $producers);
      cablecast_upsert_post_meta($id, "cablecast_producer_name", $producer->name);
      cablecast_upsert_post_meta($id, "cablecast_producer_id", $producer->id);
    }

    if (empty($show->project) == FALSE) {
      $project = cablecast_extract_id($show->project, $projects);
      cablecast_upsert_post_meta($id, "cablecast_project_name", $project->name);
      cablecast_upsert_post_meta($id, "cablecast_project_id", $project->id);
      wp_set_post_terms( $id, $project->name, 'cablecast_project');
    }

    if (empty($show->category) == FALSE) {
      $category = cablecast_extract_id($show->category, $categories);
      cablecast_upsert_post_meta($id, "cablecast_category_name", $category->name);
      cablecast_upsert_post_meta($id, "cablecast_category_id", $category->id);
      $term = get_cat_ID( $category->name);
      wp_set_post_terms($id, $term, 'category', true);
    }

    cablecast_upsert_post_meta($id, "cablecast_last_modified", $show->lastModified);
    cablecast_upsert_post_meta($id, "cablecast_show_id", $show->id);
    $trt = cablecast_calculate_trt($show, $shows_payload->reels);
    cablecast_upsert_post_meta($id, "cablecast_show_trt", $trt);

    $since = get_option('cablecast_sync_since');
    if (strtotime($show->eventDate) > strtotime($since)) {
      $since = $show->eventDate;
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
          'post_status'   => 'publish',
          'post_type'     => 'cablecast_channel'
      );
      $post = get_post(wp_insert_post( $post ));
    }

    $post->post_title = $channel->name;
    if (empty($channel->liveStreams) == FALSE) {
      $live_stream = cablecast_extract_id($channel->liveStreams[0], $live_streams);
      if ( ! add_post_meta( $post->ID, 'cablecast_channel_live_embed_code', $live_stream->embedCode, true ) ) {
        update_post_meta ( $post->ID, 'cablecast_channel_live_embed_code', $live_stream->embedCode );
      }
    }

    if ( ! add_post_meta( $post->ID, 'cablecast_channel_id', $channel->id, true ) ) {
      update_post_meta ( $post->ID, 'cablecast_channel_id', $channel->id );
    }

    wp_update_post( $post );
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
  /*
  $sql = "CREATE TABLE $table_name (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    run_date_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    show_id int NOT NULL,
    show_title varchar(255) DEFAULT '' NOT NULL,
    channel_id int NOT NULL,
    show_post_id int NOT NULL,
    channel_post_id int NOT NULL,
    PRIMARY KEY  (id)
  ) $charset_collate;";
  */
  global $wpdb;
  foreach($scheduleItems as $item) {
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
    } else {
      /*
      wp_update_term($term->term_id, 'cablecast_project', array(
        'name' => $project->description,
        'description' => empty($project->description) ? '' : $project->description,
      ));
      */
    }
  }
}

function cablecast_sync_projects($projects) {
  foreach ($projects as $project) {
    $term = term_exists( $project->name, 'cablecast_project' ); // array is returned if taxonomy is given
    if ($term == NULL) {
      wp_insert_term(
          $project->name,   // the term
          'cablecast_project', // the taxonomy
          array(
              'description' => empty($project->description) ? '' : $project->description,
          )
      );
    } else {
      /*
      wp_update_term($term->term_id, 'cablecast_project', array(
        'name' => $project->description,
        'description' => empty($project->description) ? '' : $project->description,
      ));
      */
    }
  }
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
  if ( ! add_post_meta( $id, $name, $value, true ) ) {
    update_post_meta ( $id, $name, $value );
  }
}