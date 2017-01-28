<?php
/*
Plugin Name: Cablecast
Author: Ray Tiley
Author URI: https://github.com/raytiley
Description: This plugin creates custom post types to store information about shows and schedule information from Tightrope Media Systems Cablecast Automation system.
*/

global $cablecast_db_version;
$cablecast_db_version = '1.0';

function cablecast_deactivate() {
   $timestamp = wp_next_scheduled( 'cablecast_cron_hook' );
   wp_unschedule_event( $timestamp, 'cablecast_cron_hook' );
}
register_deactivation_hook( __FILE__, 'cablecast_deactivate' );

function cablecast_install() {
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



// Load Settings Stuff For Admin Users
if ( is_admin() ) {
    // we are in admin mode
    require_once( dirname( __FILE__ ) . '/includes/settings.php' );
}

require_once( dirname( __FILE__ ) . '/includes/sync.php' );
require_once( dirname( __FILE__ ) . '/includes/cron.php' );
require_once( dirname( __FILE__ ) . '/includes/content.php' );
require_once( dirname( __FILE__ ) . '/display.php' );
require_once( dirname( __FILE__ ) . '/theme-functions.php' );




