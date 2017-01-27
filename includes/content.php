<?php

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

function cablecast_register_taxonomies() {
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