<?php
/*
Plugin Name: Cablecast
Author: Ray Tiley
Author URI: https://github.com/raytiley
Description: This plugin creates custom post types to store information about shows and schedule information from Tightrope Media Systems Cablecast Automation system.
*/



/*
 * USE IN DEV TO LOG ACTIVAtION ERRORS *
add_action('activated_plugin','my_save_error');
function my_save_error()
{
    file_put_contents(dirname(__file__).'/error_activation.txt', ob_get_contents());
}
*/

// Load Settings Stuff For Admin Users
if ( is_admin() ) {
    // we are in admin mode
    require_once( dirname( __FILE__ ) . '/includes/settings.php' );
}

require_once( dirname( __FILE__ ) . '/includes/sync.php' );
require_once( dirname( __FILE__ ) . '/includes/cron.php' );
require_once( dirname( __FILE__ ) . '/includes/content.php' );
require_once( dirname( __FILE__ ) . '/includes/install.php' );
require_once( dirname( __FILE__ ) . '/display.php' );
require_once( dirname( __FILE__ ) . '/theme-functions.php' );




