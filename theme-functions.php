<?php

function cablecast_get_schedules($channel_id, $date_start, $date_end = NULL) {
  // Get the WordPress site timezone (fall back to UTC if not set)
  $timezone = get_option('timezone_string');
  if (empty($timezone)) {
    $timezone = 'UTC';
  }

  if (empty($date_start)) {
    $date_start = current_time('Y-m-d');
  }
  if (empty($date_end)) {
    $date_end = date('Y-m-d', strtotime($date_start . " +1 days"));
  }

  // Convert start and end dates to DateTime objects in the site timezone
  $date_start_dt = new DateTime($date_start, new DateTimeZone($timezone));
  $date_end_dt = new DateTime($date_end, new DateTimeZone($timezone));

  // Convert the DateTime objects to UTC
  $date_start_dt->setTimezone(new DateTimeZone('UTC'));
  $date_end_dt->setTimezone(new DateTimeZone('UTC'));

  // Format the dates for the SQL query
  $date_start_utc = $date_start_dt->format('Y-m-d H:i:s');
  $date_end_utc = $date_end_dt->format('Y-m-d H:i:s');

  global $wpdb;
  $table = $wpdb->prefix . 'cablecast_schedule_items';
  $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE channel_id=%d AND cg_exempt=0 AND run_date_time >= %s AND run_date_time < %s ORDER BY run_date_time",
    $channel_id,
    $date_start_utc,
    $date_end_utc
  ));

  // Convert the retrieved run_date_time from UTC to the site timezone
  // Also add run_timestamp for timezone-safe comparisons in shortcodes
  foreach ($results as $result) {
    $run_date_time_utc = new DateTime($result->run_date_time, new DateTimeZone('UTC'));
    $result->run_timestamp = $run_date_time_utc->getTimestamp();
    $run_date_time_utc->setTimezone(new DateTimeZone($timezone));
    $result->run_date_time = $run_date_time_utc->format('Y-m-d H:i:s');
  }

  return $results;
}
