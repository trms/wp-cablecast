<?php
/**
 * Cablecast Uninstall
 *
 * Cleans up plugin data when uninstalled through the WordPress admin.
 * This file is executed when the user deletes the plugin.
 *
 * @package Cablecast
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up all plugin data.
 * This runs when the plugin is deleted (not just deactivated).
 */

global $wpdb;

// 1. Delete the custom schedule items table
$table_name = $wpdb->prefix . 'cablecast_schedule_items';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// 2. Delete all plugin options
$options_to_delete = array(
    'cablecast_options',
    'cablecast_sync_since',
    'cablecast_sync_index',
    'cablecast_sync_total_result_count',
    'cablecast_schedule_items_hash',
    'cablecast_categories_items_hash',
    'cablecast_projects_items_hash',
    'cablecast_producers_items_hash',
    'cablecast_custom_taxonomy_definitions',
    'cablecast_orphan_check_last_run',
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// 3. Delete transients
delete_transient('cablecast_sync_lock');

// 4. Optionally delete all show and channel posts
// Uncomment the following lines if you want to remove all synced content on uninstall:
/*
$post_types = array('show', 'cablecast_channel');
foreach ($post_types as $post_type) {
    $posts = get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
    ));
    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
    }
}
*/

// 5. Delete custom taxonomies terms (optional)
// Note: WordPress will keep taxonomy terms but they will be orphaned
// Uncomment if you want to clean them up:
/*
$taxonomies = array('cablecast_producer', 'cablecast_project');
foreach ($taxonomies as $taxonomy) {
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'fields' => 'ids',
    ));
    if (!is_wp_error($terms)) {
        foreach ($terms as $term_id) {
            wp_delete_term($term_id, $taxonomy);
        }
    }
}
*/

// 6. Delete log directory
$log_dir = WP_CONTENT_DIR . '/cablecast-logs';
if (is_dir($log_dir)) {
    $files = glob($log_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($log_dir);
}
