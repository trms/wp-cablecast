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
  return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE channel_id=%d AND run_date_time >= %s AND run_date_time < %s",
    $channel_id,
    $date_start,
    $date_end
  ));
}