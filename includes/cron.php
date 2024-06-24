<?php

function cablecast_cron_schedules( $schedules ) {
	// add a 'weekly' schedule to the existing set
	$schedules['cablecast-5-minutes'] = array(
		'interval' => 5 * 60,
		'display' => __('Every 5 Minutes')
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'cablecast_cron_schedules' );

add_action( 'cablecast_cron_hook', 'cablecast_sync_data' );

if ( ! wp_next_scheduled( 'cablecast_cron_hook' ) ) {
  wp_schedule_event( time(), 'cablecast-5-minutes', 'cablecast_cron_hook' );
}