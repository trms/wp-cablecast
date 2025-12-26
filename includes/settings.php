<?php

/**
 * custom option and settings
 */
function cablecast_settings_init()
{
    // register a new setting for "cablecast" page
    register_setting('cablecast', 'cablecast_options');

    // register a new section in the "cablecast" page
    add_settings_section(
        'cablecast_section_developers',
        __('Configure Cablecast Sync Settings', 'cablecast'),
        'cablecast_section_developers_cb',
        'cablecast'
    );

    // register a new field in the "cablecast_section_developers" section, inside the "cablecast" page
    add_settings_field(
        'cablecast_field_server', // as of WP 4.6 this value is used only internally
        // use $args' label_for to populate the id inside the callback
        __('Server', 'cablecast'),
        'cablecast_field_server_cb',
        'cablecast',
        'cablecast_section_developers',
        [
            'label_for'         => 'cablecast_field_server',
            'class'             => 'cablecast_row',
            'cablecast_custom_data' => 'custom',
        ]
    );

    // Thumbnail Settings Section
    add_settings_section(
        'cablecast_section_thumbnails',
        __('Thumbnail Settings', 'cablecast'),
        'cablecast_section_thumbnails_cb',
        'cablecast'
    );

    add_settings_field(
        'cablecast_field_thumbnail_mode',
        __('Thumbnail Hosting', 'cablecast'),
        'cablecast_field_thumbnail_mode_cb',
        'cablecast',
        'cablecast_section_thumbnails'
    );

    add_settings_field(
        'cablecast_field_delete_local_thumbnails',
        __('Cleanup Local Thumbnails', 'cablecast'),
        'cablecast_field_delete_local_thumbnails_cb',
        'cablecast',
        'cablecast_section_thumbnails'
    );

    add_settings_field(
        'cablecast_field_cdn_test',
        __('CDN Connection Test', 'cablecast'),
        'cablecast_field_cdn_test_cb',
        'cablecast',
        'cablecast_section_thumbnails'
    );
}

/**
 * register our cablecast_settings_init to the admin_init action hook
 */
add_action('admin_init', 'cablecast_settings_init');

/**
 * custom option and settings:
 * callback functions
 */

// developers section cb

// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function cablecast_section_developers_cb($args)
{
}

// pill field cb

// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function cablecast_field_server_cb($args)
{
    // get the value of the setting we've registered with register_setting()
    $options = get_option('cablecast_options');
    // output the field
    ?>
    <input type="url" name="cablecast_options[server]" class="regular-text code" value="<?= isset($options['server']) ? esc_attr($options['server']) : ''; ?>">

    <?php
}

// Thumbnail settings callbacks
function cablecast_section_thumbnails_cb($args)
{
    ?>
    <p><?php _e('Configure how show thumbnails are handled.', 'cablecast'); ?></p>
    <?php
}

function cablecast_field_thumbnail_mode_cb($args)
{
    $options = get_option('cablecast_options');
    $current_mode = isset($options['thumbnail_mode']) ? $options['thumbnail_mode'] : 'local';
    ?>
    <fieldset>
        <label>
            <input type="radio" name="cablecast_options[thumbnail_mode]" value="local" <?php checked($current_mode, 'local'); ?>>
            <?php _e('Sync Local', 'cablecast'); ?>
        </label>
        <p class="description" style="margin-left: 24px; margin-top: 4px;">
            <?php _e('Thumbnails will be downloaded as WordPress attachments. Can use significant storage space.', 'cablecast'); ?>
        </p>
        <br>
        <label>
            <input type="radio" name="cablecast_options[thumbnail_mode]" value="remote" <?php checked($current_mode, 'remote'); ?>>
            <?php _e('Remote Hosting', 'cablecast'); ?>
        </label>
        <p class="description" style="margin-left: 24px; margin-top: 4px;">
            <?php _e('Use Cablecast for thumbnail hosting. Reduces storage and sync time.', 'cablecast'); ?>
        </p>
    </fieldset>
    <?php
}

function cablecast_field_delete_local_thumbnails_cb($args)
{
    // Verify user has capability before running expensive queries
    if (!current_user_can('manage_options')) {
        return;
    }

    $options = get_option('cablecast_options');
    $current_mode = isset($options['thumbnail_mode']) ? $options['thumbnail_mode'] : 'local';
    $delete_enabled = !empty($options['delete_local_thumbnails']);

    // Check if cleanup is in progress
    $cleanup_in_progress = $delete_enabled && $current_mode === 'remote';

    if ($cleanup_in_progress) {
        // Count remaining thumbnails to delete
        $remaining = get_posts([
            'post_type' => 'show',
            'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        $count = count($remaining);
        ?>
        <div class="notice notice-info inline" style="margin: 0; padding: 10px;">
            <p>
                <span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span>
                <?php printf(__('Thumbnail cleanup in progress... %d remaining.', 'cablecast'), $count); ?>
            </p>
        </div>
        <style>
            @keyframes rotation {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        </style>
        <?php
        return;
    }

    // Only show option when in remote mode
    if ($current_mode !== 'remote') {
        ?>
        <p class="description">
            <?php _e('Switch to Remote Hosting to enable thumbnail cleanup options.', 'cablecast'); ?>
        </p>
        <?php
        return;
    }

    // Count existing local thumbnails
    $existing = get_posts([
        'post_type' => 'show',
        'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);
    $count = count($existing);

    if ($count === 0) {
        ?>
        <p class="description">
            <?php _e('No local thumbnails to clean up.', 'cablecast'); ?>
        </p>
        <?php
        return;
    }

    ?>
    <label>
        <input type="checkbox" name="cablecast_options[delete_local_thumbnails]" value="1" <?php checked($delete_enabled); ?>>
        <?php printf(__('Delete %d existing local thumbnails', 'cablecast'), $count); ?>
    </label>
    <p class="description" style="color: #d63638; margin-top: 8px;">
        <strong><?php _e('Warning:', 'cablecast'); ?></strong>
        <?php _e('This will permanently delete ALL featured images from Show posts. Ensure you have a backup before enabling this option. Deletion happens gradually during cron runs.', 'cablecast'); ?>
    </p>
    <?php
}

function cablecast_field_cdn_test_cb($args)
{
    $options = get_option('cablecast_options');
    $server = isset($options['server']) ? $options['server'] : '';
    $current_mode = isset($options['thumbnail_mode']) ? $options['thumbnail_mode'] : 'local';

    if (empty($server)) {
        ?>
        <p class="description">
            <?php _e('Configure a server URL first to test CDN connectivity.', 'cablecast'); ?>
        </p>
        <?php
        return;
    }

    if ($current_mode !== 'remote') {
        ?>
        <p class="description">
            <?php _e('Switch to Remote Hosting to test CDN connectivity.', 'cablecast'); ?>
        </p>
        <?php
        return;
    }

    ?>
    <button type="button" class="button" id="cablecast-cdn-test">
        <?php _e('Test CDN Connection', 'cablecast'); ?>
    </button>
    <span id="cablecast-cdn-test-result" style="margin-left: 10px;"></span>
    <p class="description" style="margin-top: 8px;">
        <?php _e('Verifies that thumbnail images can be loaded from the Cablecast CDN.', 'cablecast'); ?>
    </p>
    <script>
    jQuery(function($) {
        $('#cablecast-cdn-test').on('click', function() {
            var $btn = $(this);
            var $result = $('#cablecast-cdn-test-result');

            $btn.prop('disabled', true).text('<?php _e('Testing...', 'cablecast'); ?>');
            $result.text('').removeClass('notice-success notice-error');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'cablecast_test_cdn',
                    nonce: '<?php echo wp_create_nonce('cablecast_test_cdn'); ?>'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('<?php _e('Test CDN Connection', 'cablecast'); ?>');
                    if (response.success) {
                        $result.html('<span style="color: #00a32a;">&#10004; ' + response.data + '</span>');
                    } else {
                        $result.html('<span style="color: #d63638;">&#10006; ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('<?php _e('Test CDN Connection', 'cablecast'); ?>');
                    $result.html('<span style="color: #d63638;">&#10006; <?php _e('Request failed', 'cablecast'); ?></span>');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler for CDN test
add_action('wp_ajax_cablecast_test_cdn', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized', 'cablecast'));
        return;
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cablecast_test_cdn')) {
        wp_send_json_error(__('Invalid nonce', 'cablecast'));
        return;
    }

    $options = get_option('cablecast_options');
    $server = isset($options['server']) ? $options['server'] : '';

    if (empty($server)) {
        wp_send_json_error(__('No server configured', 'cablecast'));
        return;
    }

    // Find a show with a thumbnail URL to test
    $shows = get_posts([
        'post_type' => 'show',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'cablecast_thumbnail_url',
                'compare' => 'EXISTS',
            ],
            [
                'key' => 'cablecast_thumbnail_url',
                'value' => '',
                'compare' => '!=',
            ],
        ],
    ]);

    if (empty($shows)) {
        // No shows with thumbnails, try testing a sample URL pattern
        $test_url = rtrim($server, '/') . CABLECAST_API_BASE . '/shows';
        $response = wp_remote_head($test_url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            wp_send_json_error(sprintf(__('Cannot reach server: %s', 'cablecast'), $response->get_error_message()));
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 400) {
            wp_send_json_success(__('Server reachable. Sync shows to test thumbnail loading.', 'cablecast'));
        } else {
            wp_send_json_error(sprintf(__('Server returned status %d', 'cablecast'), $code));
        }
        return;
    }

    // Test loading the thumbnail URL
    $thumbnail_url = get_post_meta($shows[0]->ID, 'cablecast_thumbnail_url', true);
    $response = wp_remote_head($thumbnail_url, ['timeout' => 10]);

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('Thumbnail load failed: %s', 'cablecast'), $response->get_error_message()));
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 400) {
        wp_send_json_success(__('CDN thumbnail loading works correctly!', 'cablecast'));
    } else {
        wp_send_json_error(sprintf(__('Thumbnail returned status %d', 'cablecast'), $code));
    }
});

/**
 * top level menu
 */
function cablecast_options_page()
{
    // add top level menu page
    add_menu_page(
        'Cablecast',
        'Cablecast Settings',
        'manage_options',
        'cablecast',
        'cablecast_options_page_html'
    );

    add_management_page(
        'Cablecast Logs',
        'Cablecast Logs',
        'manage_options',
        'cablecast-logs',
        function () {
            if ( ! current_user_can('manage_options') ) return;
            $url = wp_nonce_url(admin_url('admin-post.php?action=cablecast_download_log'), 'cablecast_download_log');
            echo '<div class="wrap"><h1>Cablecast Logs</h1>';
            if (\Cablecast\Logger::exists()) {
                echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Download current log</a></p>';
            } else {
                echo '<p>No log file yet.</p>';
            }
            echo '</div>';
        }
    );
}

add_action('admin_post_cablecast_download_log', function () {
    if ( ! current_user_can('manage_options') ) wp_die('Unauthorized', 403);
    check_admin_referer('cablecast_download_log');

    $path = \Cablecast\Logger::path();
    if ( ! file_exists($path) ) wp_die('No log file found.');

    // Nice filename with date
    $download = 'cablecast-' . wp_date('Ymd-His') . '.log';

    // Clean output buffers to avoid corrupting download
    while (ob_get_level()) { ob_end_clean(); }

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $download . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

/**
 * register our cablecast_options_page to the admin_menu action hook
 */
add_action('admin_menu', 'cablecast_options_page');

/**
 * top level menu:
 * callback functions
 */
function cablecast_options_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // add error/update messages

    // check if the user have submitted the settings
    // wordpress will add the "settings-updated" $_GET parameter to the url
    if (isset($_GET['settings-updated'])) {
        // add settings saved message with the class of "updated"
        add_settings_error('cablecast_messages', 'cablecast_message', __('Settings Saved', 'cablecast'), 'updated');
    }

    settings_errors('cablecast_messages');

    if (defined('DISABLE_WP_CRON') == false || DISABLE_WP_CRON == false) {
      ?>
      <div class="notice notice-warning">
        <p>WordPress's built in cron is still enabled. This causes the cablecast plugin to attempt to sync during regular web requests which can lead to failures and poor user expericnes. It is recomended to disable the built in cron and instead run cron using the system task scheduler. See <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/">https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/</a> for more info.</p>
      </div>
      <?php
    }

    ?>
    <?php
      $total = get_option('cablecast_sync_total_result_count');
      $sync_index = get_option('cablecast_sync_index');
      if ($total == FALSE) {
        $total = 0;
      }
      if ($sync_index == FALSE) {
        $sync_index = 0;
      }
      $remaining = $total - $sync_index;
     ?>

    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <div class="notice notice-info">
          <p>There are <?= $remaining ?> remaining shows out of  <?= $total ?> shows updated after <?= esc_html(get_option('cablecast_sync_since')); ?></p>
        </div>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "cablecast"
            settings_fields('cablecast');
            // output setting sections and their fields
            // (sections are registered for "cablecast", each field is registered to a specific section)
            do_settings_sections('cablecast');
            // output save settings button
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}
