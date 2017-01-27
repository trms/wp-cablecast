<?php

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
