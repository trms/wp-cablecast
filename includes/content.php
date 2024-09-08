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
      'taxonomies' => array('category', 'cablecast_project', 'cablecast_producer'),
      'has_archive' => 'shows',
      ] );

      register_post_type('cablecast_channel',
      array(
          'public' => true,
          'labels' => array(
              'name' => __('Channels'),
              'singular_name' => __('Channel')
          ),
          'supports' => array('title', 'custom-fields', 'editor', 'thumbnail'),
          'capabilities' => array('create_posts' => 'do_not_allow'),
          'map_meta_cap' => true,
          'rewrite' => array('slug' => 'channel'),
          'has_archive' => 'channels',
      )
  );
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

    $producer_labels = [
        'name'              => _x('Producers', 'taxonomy general name'),
        'singular_name'     => _x('Producer', 'taxonomy singular name'),
        'search_items'      => __('Search Producers'),
        'all_items'         => __('All Producers'),
        'parent_item'       => __('Parent Producer'),
        'parent_item_colon' => __('Parent Producers:'),
        'edit_item'         => __('Edit Producer'),
        'update_item'       => __('Update Producer'),
        'add_new_item'      => __('Add New Producer'),
        'new_item_name'     => __('New Producer Name'),
        'menu_name'         => __('Producers'),
        'no_terms'          => __('No Producers Found')
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
        'rewrite'           => array(
          'slug' => 'series',
          'with_front'    => true
        ),
    ];
    register_taxonomy('cablecast_project', ['show'], $args);

    $args['labels'] = $producer_labels;
    $args['rewrite'] = array(
      'slug' => 'producers',
      'with_front'    => true
    );
    register_taxonomy('cablecast_producer', ['show'], $args);

    $definitions = get_option('cablecast_custom_taxonomy_definitions');
    if (!empty($definitions->showFields)) {
      cablecast_register_show_field_taxonomies($definitions);
    }
}
add_action('init', 'cablecast_register_taxonomies');


function cablecast_register_show_field_taxonomies($definitions) {
  foreach ($definitions->showFields as $show_field) {
      $field_def = cablecast_extract_id($show_field->fieldDefinition, $definitions->fieldDefinitions);
      $tax_name = "cbl-tax-" . $show_field->id;
      if ($field_def->name && 
          ($field_def->type === "tag" || $field_def->type === "select") && 
          !taxonomy_exists($tax_name)) {
          $result = register_taxonomy($tax_name, ['show'], array(
              'label' => $field_def->name,
              'name' => _x($field_def->name, 'taxonomy general name'),
              'public' => true,
              'rewrite' => array('slug' => sanitize_title("cbl-" . $field_def->name), 'with_front' => true ),
              'hierarchical' => false,
          ));
      }
  }
}

// Add taxonomy filters to the custom post type 'show' list in admin
function add_taxonomy_filters_to_shows() {
  global $typenow;

  if ($typenow == 'show') {
      // Add filters for custom taxonomies
      $taxonomies = ['cablecast_project', 'cablecast_producer']; // Add any other taxonomies if needed

      foreach ($taxonomies as $taxonomy) {
          $tax = get_taxonomy($taxonomy);
          $terms = get_terms($taxonomy);

          if ($terms) {
              echo '<select name="' . $taxonomy . '" id="' . $taxonomy . '" class="postform">';
              echo '<option value="">' . __('Show All ', 'text_domain') . $tax->label . '</option>';

              foreach ($terms as $term) {
                  $selected = (isset($_GET[$taxonomy]) && $_GET[$taxonomy] == $term->slug) ? ' selected="selected"' : '';
                  echo '<option value="' . $term->slug . '"' . $selected . '>' . $term->name . '</option>';
              }

              echo '</select>';
          }
      }
  }
}
add_action('restrict_manage_posts', 'add_taxonomy_filters_to_shows');

// Filter the query by selected taxonomy
function filter_shows_by_taxonomy($query) {
  global $pagenow;
  $post_type = 'show';

  if ($pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == $post_type) {
      $taxonomies = ['cablecast_project', 'cablecast_producer']; // Add more taxonomies if needed

      foreach ($taxonomies as $taxonomy) {
          if (isset($_GET[$taxonomy]) && $_GET[$taxonomy] != '') {
              $query->query_vars[$taxonomy] = $_GET[$taxonomy];
          }
      }
  }
}
add_filter('parse_query', 'filter_shows_by_taxonomy');