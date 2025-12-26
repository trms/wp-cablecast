<?php
/*
Plugin Name: Cablecast
Author: Ray Tiley
Author URI: https://github.com/raytiley
Description: This plugin creates custom post types to store information about shows and schedule information from Tightrope Media Systems Cablecast Automation system.
*/
require_once __DIR__ . '/includes/Logger.php';

global $cablecast_db_version;
$cablecast_db_version = '1.1';

// Cablecast API configuration
define('CABLECAST_API_VERSION', 'v1');
define('CABLECAST_API_BASE', '/cablecastapi/' . CABLECAST_API_VERSION);

function cablecast_deactivate() {
   $timestamp = wp_next_scheduled( 'cablecast_cron_hook' );
   wp_unschedule_event( $timestamp, 'cablecast_cron_hook' );
}
register_deactivation_hook( __FILE__, 'cablecast_deactivate' );

function cablecast_install() {
    // trigger our function that registers the custom post type
    cablecast_setup_post_types();

    // Set default thumbnail mode based on new vs upgrade install
    $existing_options = get_option('cablecast_options');
    if ($existing_options === false) {
        // Fresh install - default to remote hosting
        update_option('cablecast_options', ['thumbnail_mode' => 'remote']);
    } else if (!isset($existing_options['thumbnail_mode'])) {
        // Upgrade from older version - preserve old behavior (sync local)
        $existing_options['thumbnail_mode'] = 'local';
        update_option('cablecast_options', $existing_options);
    }

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
      cg_exempt tinyint(1) DEFAULT 0 NOT NULL,
  		PRIMARY KEY  (id)
  	) $charset_collate;";

  	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  	dbDelta( $sql );

  	add_option( 'cablecast_db_version', $cablecast_db_version );

    // clear the permalinks after the post type has been registered
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cablecast_install' );

/**
 * Check if database needs upgrade and run dbDelta if so.
 * This handles adding new columns (like cg_exempt) to existing installations.
 */
function cablecast_maybe_upgrade() {
    global $wpdb;
    global $cablecast_db_version;

    $installed_ver = get_option('cablecast_db_version');

    if ($installed_ver !== $cablecast_db_version) {
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
            cg_exempt tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option('cablecast_db_version', $cablecast_db_version);
    }
}
add_action('plugins_loaded', 'cablecast_maybe_upgrade');

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




