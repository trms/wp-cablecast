<?php
/*
Plugin Name: Cablecast
Author: Ray Tiley
Author URI: https://github.com/raytiley
Description: This plugin creates custom post types to store information about shows and schedule information from Tightrope Media Systems Cablecast Automation system.
*/



/**
 * @internal    never define functions inside callbacks.
 *              these functions could be run multiple times; this would result in a fatal error.
 */

/* Filter the single_template with our custom function*/

global $cablecast_db_version;
$cablecast_db_version = '1.0';

add_action('activated_plugin','my_save_error');
function my_save_error()
{
    file_put_contents(dirname(__file__).'/error_activation.txt', ob_get_contents());
}

/**
 * custom option and settings
 */
function cablecast_settings_init()
{
    // register a new setting for "cablecast" page
    register_setting('cablecast', 'cablecast_options');

    // register a new section in the "cablecast" page
    add_settings_section(
        'cablecast_section_developers',
        __('Configure Cablecast Sync Settings', 'cablecast'),
        'cablecast_section_developers_cb',
        'cablecast'
    );

    // register a new field in the "cablecast_section_developers" section, inside the "cablecast" page
    add_settings_field(
        'cablecast_field_server', // as of WP 4.6 this value is used only internally
        // use $args' label_for to populate the id inside the callback
        __('Server', 'cablecast'),
        'cablecast_field_server_cb',
        'cablecast',
        'cablecast_section_developers',
        [
            'label_for'         => 'cablecast_field_server',
            'class'             => 'cablecast_row',
            'cablecast_custom_data' => 'custom',
        ]
    );
}

/**
 * register our cablecast_settings_init to the admin_init action hook
 */
add_action('admin_init', 'cablecast_settings_init');

/**
 * custom option and settings:
 * callback functions
 */

// developers section cb

// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function cablecast_section_developers_cb($args)
{
    ?>
    <p>Server Address</p>
    <?php
}

// pill field cb

// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function cablecast_field_server_cb($args)
{
    // get the value of the setting we've registered with register_setting()
    $options = get_option('cablecast_options');
    // output the field
    ?>
    <input type="text" name="cablecast_options[server]" value="<?= isset($options['server']) ? esc_attr($options['server']) : ''; ?>">

    <?php
}

/**
 * top level menu
 */
function cablecast_options_page()
{
    // add top level menu page
    add_menu_page(
        'Cablecast',
        'Cablecast Settings',
        'manage_options',
        'cablecast',
        'cablecast_options_page_html'
    );
}

/**
 * register our cablecast_options_page to the admin_menu action hook
 */
add_action('admin_menu', 'cablecast_options_page');

/**
 * top level menu:
 * callback functions
 */
function cablecast_options_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // add error/update messages

    // check if the user have submitted the settings
    // wordpress will add the "settings-updated" $_GET parameter to the url
    if (isset($_GET['settings-updated'])) {
        // add settings saved message with the class of "updated"
        add_settings_error('cablecast_messages', 'cablecast_message', __('Settings Saved', 'cablecast'), 'updated');
    }

    // show error/update messages
    settings_errors('cablecast_messages');
    ?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "cablecast"
            settings_fields('cablecast');
            // output setting sections and their fields
            // (sections are registered for "cablecast", each field is registered to a specific section)
            do_settings_sections('cablecast');
            // output save settings button
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

function cablecast_get_schedules($channel_id, $date_start, $date_end = NULL) {
  if (empty($date_start)) {
    $date_start = date('Y-m-d');
  }
  if (empty($date_end)) {
    $date_end = date('Y-m-d', strtotime($date_start . "+1days"));
  }

  global $wpdb;
  $table = $wpdb->prefix . 'cablecast_schedule_items';
  return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE channel_id=%d AND run_date_time >= %s AND run_date_time < %s",
    $channel_id,
    $date_start,
    $date_end
  ));
}

function cablecast_cron_schedules( $schedules ) {
	// add a 'weekly' schedule to the existing set
	$schedules['5-seconds'] = array(
		'interval' => 5,
		'display' => __('Once Weekly')
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'cablecast_cron_schedules' );

add_action( 'cablecast_cron_hook', 'cablecast_sync_data' );

if ( ! wp_next_scheduled( 'cablecast_cron_hook' ) ) {
  wp_schedule_event( time(), '5-seconds', 'cablecast_cron_hook' );
}

function cablecast_sync_channels($channels) {
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

    if ( ! add_post_meta( $post->ID, 'cablecast_channel_id', $channel->id, true ) ) {
      update_post_meta ( $post->ID, 'cablecast_channel_id', $channel->id );
    }

    wp_update_post( $post );
  }
}

function cablecast_sync_data() {
  $options = get_option('cablecast_options');
  $server = $options["server"];
  $url = "$server/cablecastapi/v1/shows?page_size=300&include=reel,vod,project,producer,webfile";
  $shows_json = file_get_contents($url);
  $channels = json_decode(file_get_contents( "$server/cablecastapi/v1/channels"));
  $categories = json_decode(file_get_contents( "$server/cablecastapi/v1/categories"));
  $producers = json_decode(file_get_contents( "$server/cablecastapi/v1/producers"));
  $projects = json_decode(file_get_contents( "$server/cablecastapi/v1/projects"));
  $two_days_ago = date('Y-m-d', strtotime("-2days"));
  $scheduleItems = json_decode(file_get_contents( "$server/cablecastapi/v1/scheduleitems?start=$two_days_ago&page_size=500"));
  var_dump($scheduleItems->meta);
  $categories = $categories->categories;
  $projects = $projects->projects;
  $producers = $producers->producers;
  $channels = $channels->channels;
  $scheduleItems = $scheduleItems->scheduleItems;

  cablecast_sync_channels($channels);
  cablecast_sync_projects($projects);
  cablecast_sync_categories($categories);
  cablecast_sync_schedule($scheduleItems);

  $payload = json_decode($shows_json);
  print "Syncing data for $server...\n\n";
  foreach($payload->shows as $show) {
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
      continue;
    }

    $id = $post->ID;

    $post->post_title = isset($show->cgTitle) ? $show->cgTitle : $show->title;
    $post->post_content = isset($show->comments) ? $show->title : '';

    $id = wp_update_post( $post );

    if (isset($show->showThumbnailOriginal)) {
      $webFile = cablecast_extract_id($show->showThumbnailOriginal, $payload->webFiles);
      $thumbnail_id = cablecast_insert_attachment_from_url($webFile, $id);
      set_post_thumbnail( $id, $thumbnail_id );
    }

    if (isset($show->vods) && count($show->vods)) {
      $vod = cablecast_extract_id($show->vods[0], $payload->vods);
      update_post_meta($id, "cablecast_vod_url", $vod->url, true);
      update_post_meta($id, "cablecast_vod_embed", $vod->embedCode, true);
    }

    if (isset($show->producer)) {
      $producer = cablecast_extract_id($show->producer, $producers);
      update_post_meta($id, "cablecast_producer_name", $producer->name);
      update_post_meta($id, "cablecast_producer_id", $producer->id);
    }

    if (isset($show->project)) {
      $project = cablecast_extract_id($show->project, $projects);
      update_post_meta($id, "cablecast_project_name", $project->name);
      update_post_meta($id, "cablecast_project_id", $project->id);
      wp_set_post_terms( $id, $project->name, 'cablecast_project');
    }

    if (isset($show->category)) {
      $category = cablecast_extract_id($show->category, $categories);
      update_post_meta($id, "cablecast_category_name", $category->name);
      update_post_meta($id, "cablecast_category_id", $category->id);
      $term = get_cat_ID( $category->name);
      echo "category: $category->name\n";
      var_dump($term);
      wp_set_post_terms($id, $term, 'category', true);
    }

    update_post_meta($id, "cablecast_last_modified", $show->lastModified);
    update_post_meta($id, "cablecast_show_id", $show->id);

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

register_deactivation_hook( __FILE__, 'cablecast_deactivate' );

function cablecast_deactivate() {
   $timestamp = wp_next_scheduled( 'cablecast_cron_hook' );
   wp_unschedule_event( $timestamp, 'cablecast_cron_hook' );
}

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
        $show_content =  "<div>";
        if (is_single()) {
          $show_content .= "[video src=\"$vod_url\"]";
        }
        $show_content .= "<p>";
        $show_content .= $post->post_content;
        $show_content .= "</p>";
        $show_content .= "<dl>";
        if (empty($producer) == false) {
          $show_content .= "<dt>Producer</dt>";
          $show_content .= "<dd>$producer</dd>";
        }
        if (empty($project) == false) {
          $show_content .= "<dt>Series</dt>";
          $show_content .= "<dd>$project</dd>";
        }
        if (empty($category) == false) {
          $show_content .= "<dt>Category</dt>";
          $show_content .= "<dd>$producer</dd>";
        }
        $show_content .= "</dl>";
        $show_content .= "</div>";
        return do_shortcode($show_content);
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

      $schedule_itmes = cablecast_get_schedules($channel_id, $date);

      $schedule_content = "<h3>Schedule For $date</h3>";
      $schedule_content .= "<a href=\"$prev_link\">Previous</a> | <a href=\"$next_link\">Next</a>";
      $schedule_content .= "<table><thead><tr><th>Time</th><th>Show</th></tr></thead><tbody>";
      foreach($schedule_itmes as $item) {
        $show_link = get_post_permalink($item->show_post_id);
        if (empty($show_link)) { continue; }
        $time = date('h:m a', strtotime($item->run_date_time));
        $title = $item->show_title;
        $schedule_content .= "<tr><td>$time</td><td><a href=\"$show_link\">$item->show_title</a></td></tr>";
      }
      $schedule_content .= "</tbody></table>";
      return $schedule_content;
    } else {
      return $content;
    }
}

function cablecast_setup_post_types() {
    // register the "book" custom post type
    register_post_type( 'show', [
      'public' => true,
      'labels' => [
        'name' => __('Shows'),
        'singular_name' => __('Show')
      ],
      'supports' => array('title','thumbnail','comments', 'custom-fields'),
      'capabilities' => array('create_posts' => 'do_not_allow'),
      'map_meta_cap' => true,
      'taxonomies' => array('category', 'cablecast_project')
      ] );

    register_post_type( 'cablecast_channel', [
      'public' => true,
      'labels' => [
        'name' => __('Channels'),
        'singular_name' => __('Channel')
      ],
      'supports' => array('title', 'custom-fields'),
      'capabilities' => array('create_posts' => 'do_not_allow'),
      'map_meta_cap' => true
      ] );
}
add_action( 'init', 'cablecast_setup_post_types' );

function cablecast_install()
{
    // trigger our function that registers the custom post type
    cablecast_setup_post_types();

    global $wpdb;
  	global $cablecast_db_version;

  	$table_name = $wpdb->prefix . 'cablecast_schedule_items';

  	$charset_collate = $wpdb->get_charset_collate();

  	$sql = "CREATE TABLE $table_name (
  		id mediumint(9) NOT NULL AUTO_INCREMENT,
  		run_date_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  		show_id int NOT NULL,
      show_title varchar(255) DEFAULT '' NOT NULL,
      channel_id int NOT NULL,
      show_post_id int NOT NULL,
      channel_post_id int NOT NULL,
      schedule_item_id int NOT NULL,
  		PRIMARY KEY  (id)
  	) $charset_collate;";

  	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  	dbDelta( $sql );

  	add_option( 'cablecast_db_version', $cablecast_db_version );

    // clear the permalinks after the post type has been registered
    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'cablecast_install' );


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

function cablecast_register_taxonomies()
{
    $projects_labels = [
        'name'              => _x('Series', 'taxonomy general name'),
        'singular_name'     => _x('Series', 'taxonomy singular name'),
        'search_items'      => __('Search Searies'),
        'all_items'         => __('All Series'),
        'parent_item'       => __('Parent Series'),
        'parent_item_colon' => __('Parent Series:'),
        'edit_item'         => __('Edit Series'),
        'update_item'       => __('Update Series'),
        'add_new_item'      => __('Add New Series'),
        'new_item_name'     => __('New Series Name'),
        'menu_name'         => __('Series'),
        'no_terms'          => __('No Series Imported')
    ];

    $args = [
        'public'            => true,
        'labels'            => $projects_labels,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_menu'      => true,
        'show_in_nav_menus' => true,
        'capabilities' => array(
          'edit_terms' => '',
          'delete_terms' => ''
        ),
        'rewrite'           => ['slug' => 'series'],
    ];
    register_taxonomy('cablecast_project', ['show'], $args);
}
add_action('init', 'cablecast_register_taxonomies');

