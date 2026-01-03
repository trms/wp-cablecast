<?php

/**
 * CDN Thumbnail size mappings.
 * Define once to avoid duplication across functions.
 */
define('CABLECAST_THUMBNAIL_SIZES', [
    'thumbnail'      => '100x100',
    'medium'         => '500x500',
    'large'          => '1000x1000',
    'post-thumbnail' => '640x360',
    'full'           => '',  // no param = original size
]);

define('CABLECAST_SRCSET_VARIANTS', [
    '320x180'  => 320,
    '480x270'  => 480,
    '640x360'  => 640,
    '960x540'  => 960,
    '1280x720' => 1280,
]);

function cablecast_setup_post_types() {
    // register the "book" custom post type
    register_post_type( 'show', [
      'public' => true,
      'hierarchical' => false,
      'menu_icon' => 'dashicons-video-alt3',
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
          'menu_icon' => 'dashicons-networking',
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
          'manage_terms' => 'manage_categories',
          'edit_terms' => 'manage_categories',
          'delete_terms' => 'manage_categories',
          'assign_terms' => 'edit_posts'
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



/**
 * Cablecast: external CDN thumbnails for "show" CPT (no local attachments).
 * - Front-end renders CDN image via normal thumbnail APIs.
 * - Admin can override by setting a real Featured Image (we back off).
 */

/**
 * Build the external CDN URL for a show thumbnail.
 * Adjust the mapping/URL to match your CDN.
 */
function cablecast_has_real_featured_image( $post_id ) {
    // Safe here; we are NOT inside get_post_metadata for _thumbnail_id.
    $thumb_id = (int) get_post_meta( $post_id, '_thumbnail_id', true );

    if ( $thumb_id <= 0 ) {
        return false;
    }

    // Optional: ensure it’s a real attachment
    $att = get_post( $thumb_id );
    return ( $att && $att->post_type === 'attachment' );
}


function cablecast_show_thumbnail_url( $post_id, $size = 'post-thumbnail' ) {
    $options = get_option('cablecast_options');
    $thumbnail_mode = isset($options['thumbnail_mode']) ? $options['thumbnail_mode'] : 'local';

    // For local mode, check WordPress featured image first
    if ( $thumbnail_mode === 'local' ) {
        $featured_url = get_the_post_thumbnail_url( $post_id, $size );
        if ( $featured_url ) {
            return apply_filters( 'cablecast_show_thumbnail_url', $featured_url, $post_id, $size );
        }
    }

    // Check for saved thumbnail URL from API (remote mode or fallback)
    $base_thumbnail_url = get_post_meta( $post_id, 'cablecast_thumbnail_url', true );

    // No thumbnail available - return empty string instead of constructing
    // a fallback URL that may not exist on the server
    if ( ! $base_thumbnail_url ) {
        return '';
    }

    // Support [width, height] arrays or use defined size mappings
    if ( is_array( $size ) && isset( $size[0], $size[1] ) ) {
        $dimensions = absint( $size[0] ) . 'x' . absint( $size[1] );
    } else {
        $dimensions = CABLECAST_THUMBNAIL_SIZES[ $size ] ?? '';
    }

    $url = $base_thumbnail_url;
    if ( $dimensions ) {
        $url .= '?d=' . $dimensions;
    }

    // Allow theme/site overrides.
    return apply_filters( 'cablecast_show_thumbnail_url', $url, $post_id, $size );
}

/**
 * Optional: responsive srcset using CDN variants.
 * Tweak to match the sizes your CDN can produce efficiently.
 */
function cablecast_show_thumbnail_srcset( $post_id ) {
    $base_thumbnail_url = get_post_meta( $post_id, 'cablecast_thumbnail_url', true );
    if ( ! $base_thumbnail_url ) {
        return '';
    }

    $parts = [];
    foreach ( CABLECAST_SRCSET_VARIANTS as $wh => $w ) {
        $parts[] = esc_url( $base_thumbnail_url . "?d={$wh}" ) . " {$w}w";
    }

    return implode( ', ', $parts );
}

/**
 * Helper to resolve the current show post ID from various contexts.
 */
function cablecast_current_show_post_id( $maybe_post_id = null ) {
    if ( $maybe_post_id ) {
        $ptype = get_post_type( $maybe_post_id );
        if ( $ptype === 'show' ) return (int) $maybe_post_id;
    }
    $global = get_post();
    if ( $global && get_post_type( $global ) === 'show' ) {
        return (int) $global->ID;
    }
    // Support /?show=<slug> routing
    if ( isset( $_GET['show'] ) ) {
        $slug = sanitize_title_for_query( wp_unslash( $_GET['show'] ) );
        if ( $slug ) {
            $obj = get_page_by_path( $slug, OBJECT, 'show' );
            if ( $obj ) return (int) $obj->ID;
        }
    }
    return 0;
}

// Only register CDN thumbnail filters when in remote hosting mode
$cablecast_thumbnail_options = get_option('cablecast_options');
$cablecast_thumbnail_mode = isset($cablecast_thumbnail_options['thumbnail_mode']) ? $cablecast_thumbnail_options['thumbnail_mode'] : 'local';

if ($cablecast_thumbnail_mode === 'remote') :

/**
 * has_post_thumbnail(): true if there's a real Featured Image OR a show_id (CDN).
 * (No recursion risk here; we don't touch _thumbnail_id.)
 */
add_filter( 'has_post_thumbnail', function ( $has, $post, $thumb_id ) {
    // Divi (and others) may pass an int here. Normalize to a post ID.
    $pid = ($post instanceof WP_Post) ? $post->ID : ( is_numeric($post) ? (int) $post : 0 );

    // Resolve the intended Show post (handles ?show=<slug> too, if you use that helper)
    $target_id = function_exists('cablecast_current_show_post_id')
        ? cablecast_current_show_post_id( $pid )
        : $pid;

    if ( ! $target_id || get_post_type( $target_id ) !== 'show' ) {
        return $has; // not a Show context
    }

    $has_real = function_exists('cablecast_has_real_featured_image')
        ? cablecast_has_real_featured_image( $target_id )
        : ( (int) get_post_meta( $target_id, '_thumbnail_id', true ) > 0 );

    $has_cdn  = (bool) get_post_meta( $target_id, 'cablecast_show_id', true );

    return $has_real || $has_cdn;
}, 10, 3 );

/**
 * Fake a _thumbnail_id on the front-end so get_the_post_thumbnail() runs.
 * IMPORTANT: Never recurse. Respect real values. Only fake when needed.
 */
add_filter( 'get_post_metadata', function ( $value, $object_id, $meta_key, $single ) {
    if ( '_thumbnail_id' !== $meta_key ) {
        return $value; // only care about featured image key
    }

    // If core already resolved a value (real Featured Image), respect it.
    // $value is null when core didn't find anything.
    if ( null !== $value ) {
        return $value;
    }

    // Front-end only; avoid confusing the editor UI.
    if ( is_admin() ) {
        return $value;
    }

    // Only for our CPT.
    if ( 'show' !== get_post_type( $object_id ) ) {
        return $value;
    }

    // Only fake if we actually have an external image source.
    $show_id = get_post_meta( $object_id, 'cablecast_show_id', true ); // different key -> safe
    if ( ! $show_id ) {
        return $value;
    }

    // Return a non-zero int so WP thinks there is a thumbnail.
    return $single ? -1 : [ -1 ];
}, 10, 4 );

add_filter( 'post_thumbnail_html', function ( $html, $post_id, $thumb_id, $size, $attr ) {
    $target_id = cablecast_current_show_post_id( $post_id );

    if ( cablecast_has_real_featured_image( $target_id ) ) {
        \Cablecast\Logger::log('debug', "THUMB_HTML: valid real featured image ($thumb_id) exists, leaving html unchanged");
        return $html;
    }



    $src = cablecast_show_thumbnail_url( $target_id, $size );

    if ( ! $src ) {
        \Cablecast\Logger::log('debug', "THUMB_HTML: no CDN url built, returning original html");
        return $html;
    }

    \Cablecast\Logger::log('debug', "THUMB_HTML: replacing html with CDN img: {$src}");

    $defaults = [
        'alt'     => get_the_title( $target_id ),
        'loading' => 'lazy',
        'class'   => is_string( $size ) ? 'attachment-' . $size . ' size-' . $size : 'attachment-external',
        'srcset'  => cablecast_show_thumbnail_srcset( $target_id ),
        'sizes'   => '(max-width: 640px) 100vw, 640px',
    ];
    $attr = wp_parse_args( $attr, $defaults );

    $attr_str = '';
    foreach ( $attr as $k => $v ) {
        if ( $v === '' || $v === null ) continue;
        $attr_str .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
    }

    return '<img src="' . esc_url( $src ) . '"' . $attr_str . ' />';
}, 10, 5 );

add_filter( 'post_thumbnail_url', function ( $url, $post, $size ) {
    $pid = $post instanceof WP_Post ? $post->ID : ( is_numeric( $post ) ? (int) $post : 0 );
    $target_id = cablecast_current_show_post_id( $pid );

    if ( cablecast_has_real_featured_image( $target_id ) ) {
        \Cablecast\Logger::log('debug', "THUMB_URL: valid real featured image exists, leaving url unchanged");
        return $url;
    }

    \Cablecast\Logger::log('debug', "THUMB_URL: called with raw_post=" . (is_object($post)? "WP_Post({$post->ID})" : var_export($post, true)) .
               ", resolved target_id={$target_id}, size=" . print_r($size, true) .
               ", incoming url=" . var_export($url, true) );

    if ( ! $target_id ) {
        \Cablecast\Logger::log('debug', "THUMB_URL: no target_id, returning original url" );
        return $url;
    }

    if ( metadata_exists( 'post', $target_id, '_thumbnail_id' ) && empty($url) == false ) {
        \Cablecast\Logger::log('debug', "THUMB_URL: real featured image exists, returning original url: $url" );
        return $url;
    }

    $custom = cablecast_show_thumbnail_url( $target_id, $size );

    \Cablecast\Logger::log('debug', "THUMB_URL: built custom url=" . var_export($custom, true) );

    return $custom ?: $url;
}, 10, 3 );

endif; // End remote thumbnail mode filters

/**
 * =============================================================================
 * CONTENT VISIBILITY: Hide from Listings
 * =============================================================================
 * Allows hiding shows, producers, series, and categories from public listings.
 * Hidden content is still accessible via direct URL.
 */

/**
 * Check if a show should be hidden from listings.
 * Checks: manual hidden flag, CG exempt status, and associated taxonomy terms.
 *
 * @param int $post_id The show post ID.
 * @return bool True if the show should be hidden.
 */
function cablecast_is_hidden($post_id) {
    // Check show's own hidden flag
    if (get_post_meta($post_id, '_cablecast_hide_from_listings', true) === '1') {
        return true;
    }

    // Check if show is CG exempt (auto-hidden)
    if (get_post_meta($post_id, 'cablecast_show_cg_exempt', true) === '1') {
        return true;
    }

    // Check producer
    $producers = wp_get_post_terms($post_id, 'cablecast_producer', ['fields' => 'ids']);
    if (!is_wp_error($producers)) {
        foreach ($producers as $term_id) {
            if (get_term_meta($term_id, '_cablecast_hide_from_listings', true) === '1') {
                return true;
            }
        }
    }

    // Check series
    $series = wp_get_post_terms($post_id, 'cablecast_project', ['fields' => 'ids']);
    if (!is_wp_error($series)) {
        foreach ($series as $term_id) {
            if (get_term_meta($term_id, '_cablecast_hide_from_listings', true) === '1') {
                return true;
            }
        }
    }

    // Check categories
    $categories = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
    if (!is_wp_error($categories)) {
        foreach ($categories as $term_id) {
            if (get_term_meta($term_id, '_cablecast_hide_from_listings', true) === '1') {
                return true;
            }
        }
    }

    // Check tags
    $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']);
    if (!is_wp_error($tags)) {
        foreach ($tags as $term_id) {
            if (get_term_meta($term_id, '_cablecast_hide_from_listings', true) === '1') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if a taxonomy term should be hidden from listings.
 *
 * @param int $term_id The term ID.
 * @return bool True if the term should be hidden.
 */
function cablecast_is_term_hidden($term_id) {
    return get_term_meta($term_id, '_cablecast_hide_from_listings', true) === '1';
}

/**
 * Add visibility meta box to Show edit screen.
 */
add_action('add_meta_boxes', 'cablecast_add_visibility_meta_box');
function cablecast_add_visibility_meta_box() {
    add_meta_box(
        'cablecast_visibility',
        __('Cablecast: Visibility', 'cablecast'),
        'cablecast_visibility_meta_box_callback',
        'show',
        'side',
        'default'
    );
}

/**
 * Render the visibility meta box.
 */
function cablecast_visibility_meta_box_callback($post) {
    wp_nonce_field('cablecast_visibility', 'cablecast_visibility_nonce');
    $hidden = get_post_meta($post->ID, '_cablecast_hide_from_listings', true);
    $cg_exempt = get_post_meta($post->ID, 'cablecast_show_cg_exempt', true) === '1';
    ?>
    <label>
        <input type="checkbox" name="cablecast_hide_from_listings" value="1" <?php checked($hidden, '1'); ?>>
        <?php _e('Hide from listings', 'cablecast'); ?>
    </label>
    <p class="description"><?php _e('When checked, this show will not appear in shortcode listings, archives, or schedule displays.', 'cablecast'); ?></p>
    <?php if ($cg_exempt): ?>
        <p class="description" style="color: #b32d2e; margin-top: 8px;">
            <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px;"></span>
            <?php _e('This show is marked as CG Exempt in Cablecast and is automatically hidden from listings.', 'cablecast'); ?>
        </p>
    <?php endif; ?>
    <?php
}

/**
 * Save the visibility meta box data.
 */
add_action('save_post_show', 'cablecast_save_visibility_meta');
function cablecast_save_visibility_meta($post_id) {
    if (!isset($_POST['cablecast_visibility_nonce']) ||
        !wp_verify_nonce($_POST['cablecast_visibility_nonce'], 'cablecast_visibility')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $hidden = isset($_POST['cablecast_hide_from_listings']) ? '1' : '';
    update_post_meta($post_id, '_cablecast_hide_from_listings', $hidden);
}

/**
 * Add visibility field to taxonomy term edit screens.
 */
add_action('cablecast_producer_edit_form_fields', 'cablecast_taxonomy_visibility_field', 10, 2);
add_action('cablecast_project_edit_form_fields', 'cablecast_taxonomy_visibility_field', 10, 2);
add_action('category_edit_form_fields', 'cablecast_taxonomy_visibility_field', 10, 2);
add_action('post_tag_edit_form_fields', 'cablecast_taxonomy_visibility_field', 10, 2);

function cablecast_taxonomy_visibility_field($term, $taxonomy) {
    $hidden = get_term_meta($term->term_id, '_cablecast_hide_from_listings', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label><?php _e('Cablecast: Visibility', 'cablecast'); ?></label></th>
        <td>
            <label>
                <input type="checkbox" name="cablecast_hide_from_listings" value="1" <?php checked($hidden, '1'); ?>>
                <?php _e('Hide from listings', 'cablecast'); ?>
            </label>
            <p class="description"><?php _e('When checked, this item and all associated shows will not appear in shortcode listings.', 'cablecast'); ?></p>
        </td>
    </tr>
    <?php
}

/**
 * Save taxonomy term visibility meta.
 */
add_action('edited_cablecast_producer', 'cablecast_save_taxonomy_visibility');
add_action('edited_cablecast_project', 'cablecast_save_taxonomy_visibility');
add_action('edited_category', 'cablecast_save_taxonomy_visibility');
add_action('edited_post_tag', 'cablecast_save_taxonomy_visibility');

function cablecast_save_taxonomy_visibility($term_id) {
    $hidden = isset($_POST['cablecast_hide_from_listings']) ? '1' : '';
    update_term_meta($term_id, '_cablecast_hide_from_listings', $hidden);
}

/**
 * Add Hidden column to Shows admin list.
 */
add_filter('manage_show_posts_columns', 'cablecast_add_hidden_column');
function cablecast_add_hidden_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        // Insert after title column
        if ($key === 'title') {
            $new_columns['cablecast_hidden'] = __('Hidden', 'cablecast');
        }
    }
    return $new_columns;
}

/**
 * Render Hidden column content.
 */
add_action('manage_show_posts_custom_column', 'cablecast_hidden_column_content', 10, 2);
function cablecast_hidden_column_content($column, $post_id) {
    if ($column === 'cablecast_hidden') {
        if (cablecast_is_hidden($post_id)) {
            $cg_exempt = get_post_meta($post_id, 'cablecast_show_cg_exempt', true) === '1';
            $title = $cg_exempt
                ? esc_attr__('Hidden (CG Exempt)', 'cablecast')
                : esc_attr__('Hidden from listings', 'cablecast');
            echo '<span class="dashicons dashicons-hidden" title="' . $title . '" style="color: #b32d2e;"></span>';
        }
    }
}

/**
 * Exclude hidden shows from archive pages.
 * This filters the main query on show archives to hide manually hidden and CG exempt shows.
 * Note: Taxonomy-based hiding is handled post-query in shortcodes since it requires multiple meta lookups.
 */
add_action('pre_get_posts', 'cablecast_exclude_hidden_from_archives');
function cablecast_exclude_hidden_from_archives($query) {
    // Only modify frontend main queries for show archives
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    // Check if it's a show archive or taxonomy archive
    $is_show_archive = is_post_type_archive('show');
    $is_show_taxonomy = is_tax('cablecast_producer') || is_tax('cablecast_project') || (is_category() && $query->get('post_type') === 'show');

    if (!$is_show_archive && !$is_show_taxonomy) {
        return;
    }

    // Get existing meta query
    $meta_query = $query->get('meta_query') ?: [];

    // Exclude manually hidden shows
    $meta_query[] = [
        'relation' => 'OR',
        [
            'key' => '_cablecast_hide_from_listings',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key' => '_cablecast_hide_from_listings',
            'value' => '1',
            'compare' => '!=',
        ],
    ];

    // Exclude CG exempt shows
    $meta_query[] = [
        'relation' => 'OR',
        [
            'key' => 'cablecast_show_cg_exempt',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key' => 'cablecast_show_cg_exempt',
            'value' => '1',
            'compare' => '!=',
        ],
    ];

    $query->set('meta_query', $meta_query);
}
