<?php

function cablecast_get_schedules($channel_id, $date_start, $date_end = NULL) {
  if (empty($date_start)) {
    $date_start = date('Y-m-d');
  }
  if (empty($date_end)) {
    $date_end = date('Y-m-d', strtotime($date_start . "+1days"));
  }

  global $wpdb;
  $table = $wpdb->prefix . 'cablecast_schedule_items';
  $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE channel_id=%d AND run_date_time >= %s AND run_date_time < %s ORDER BY run_date_time",
    $channel_id,
    $date_start,
    $date_end
  ));

  // Get WordPress site timezone
  $wordpress_timezone = get_option('timezone_string');
  if (empty($wordpress_timezone)) {
    $wordpress_timezone = 'UTC'; // Fallback to UTC if timezone is not set
  }

  // Convert each $item->run_date_time to WordPress timezone
  foreach ($results as &$item) {
    try {
      // Create DateTime object from $item->run_date_time assuming it's in UTC
      $utc_datetime = new DateTime($item->run_date_time, new DateTimeZone('UTC'));

      // Convert to WordPress timezone
      $utc_datetime->setTimezone(new DateTimeZone($wordpress_timezone));

      // Update $item->run_date_time with the converted datetime
      $item->run_date_time = $utc_datetime->format('Y-m-d H:i:s');
    } catch (Exception $e) {
      error_log("Error converting datetime for item ID " . $item->id . ": " . $e->getMessage());
      // Optionally handle or log any conversion errors here
    }
  }

  // Log the number of results
  error_log("Number of results: " . count($results));

  return $results;
}