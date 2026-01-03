<?php

/**
 * Admin notice for home page setup.
 *
 * Shows a dismissible notice prompting users to create a Cablecast home page
 * if a server is configured but no home page exists yet.
 */
add_action('admin_notices', function() {
    // Only show to users who can manage options
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if dismissed
    if (get_option('cablecast_home_page_notice_dismissed')) {
        return;
    }

    // Check if server is configured
    $options = get_option('cablecast_options', []);
    if (empty($options['server'])) {
        return;
    }

    // Check if home page already exists
    $existing_page = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'meta_query' => [[
            'key' => '_wp_page_template',
            'value' => 'cablecast-home',
        ]],
        'posts_per_page' => 1,
    ]);

    if (!empty($existing_page)) {
        return;
    }

    // Check if there are any shows synced (to avoid showing notice before first sync)
    $shows = get_posts([
        'post_type' => 'show',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    if (empty($shows)) {
        return;
    }

    $settings_url = admin_url('options-general.php?page=cablecast#cablecast_section_home_page');
    ?>
    <div class="notice notice-info is-dismissible" id="cablecast-home-page-notice">
        <p>
            <strong><?php _e('Cablecast:', 'cablecast'); ?></strong>
            <?php _e('Create a home page to showcase your TV station\'s content!', 'cablecast'); ?>
            <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary" style="margin-left: 10px;">
                <?php _e('Set Up Home Page', 'cablecast'); ?>
            </a>
        </p>
    </div>
    <script>
    jQuery(function($) {
        $(document).on('click', '#cablecast-home-page-notice .notice-dismiss', function() {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'cablecast_dismiss_home_notice',
                    nonce: '<?php echo wp_create_nonce('cablecast_dismiss_home_notice'); ?>'
                }
            });
        });
    });
    </script>
    <?php
});

// AJAX handler for dismissing home page notice
add_action('wp_ajax_cablecast_dismiss_home_notice', function() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cablecast_dismiss_home_notice')) {
        wp_send_json_error();
    }

    update_option('cablecast_home_page_notice_dismissed', true);
    wp_send_json_success();
});

/**
 * Get all custom field taxonomies (cbl-tax-*).
 *
 * These are dynamically registered taxonomies from Cablecast show fields.
 *
 * @return array List of taxonomy names.
 */
function cablecast_get_custom_taxonomies() {
    $custom_taxonomies = [];
    $all_taxonomies = get_taxonomies([], 'names');

    foreach ($all_taxonomies as $tax_name) {
        if (strpos($tax_name, 'cbl-tax-') === 0) {
            $custom_taxonomies[] = $tax_name;
        }
    }

    return $custom_taxonomies;
}

/**
 * Sanitize the cablecast options.
 * Ensures checkbox fields are properly saved as false when unchecked.
 */
function cablecast_sanitize_options($input) {
    $current_options = get_option('cablecast_options', []);

    // Define checkbox fields that need explicit false handling
    $checkbox_fields = [
        'shortcode_styles',
        'delete_local_thumbnails',
        'enable_category_colors',
        'enable_templates',
    ];

    // If input is not an array, return current options
    if (!is_array($input)) {
        return $current_options;
    }

    // Merge with current options
    $output = array_merge($current_options, $input);

    // Handle checkbox fields - if not present in input, set to false
    foreach ($checkbox_fields as $field) {
        if (!isset($input[$field])) {
            $output[$field] = false;
        } else {
            $output[$field] = (bool) $input[$field];
        }
    }

    return $output;
}

/**
 * custom option and settings
 */
function cablecast_settings_init()
{
    // register a new setting for "cablecast" page with sanitization callback
    register_setting('cablecast', 'cablecast_options', [
        'sanitize_callback' => 'cablecast_sanitize_options',
    ]);

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

    // Shortcode Settings Section
    add_settings_section(
        'cablecast_section_shortcodes',
        __('Shortcode Settings', 'cablecast'),
        'cablecast_section_shortcodes_cb',
        'cablecast'
    );

    add_settings_field(
        'cablecast_field_shortcode_styles',
        __('Default Styling', 'cablecast'),
        'cablecast_field_shortcode_styles_cb',
        'cablecast',
        'cablecast_section_shortcodes'
    );

    add_settings_field(
        'cablecast_field_enable_templates',
        __('Plugin Templates', 'cablecast'),
        'cablecast_field_enable_templates_cb',
        'cablecast',
        'cablecast_section_shortcodes'
    );

    add_settings_field(
        'cablecast_field_filler_keywords',
        __('Filler Keywords', 'cablecast'),
        'cablecast_field_filler_keywords_cb',
        'cablecast',
        'cablecast_section_shortcodes'
    );

    add_settings_field(
        'cablecast_field_category_colors',
        __('Category Colors', 'cablecast'),
        'cablecast_field_category_colors_cb',
        'cablecast',
        'cablecast_section_shortcodes'
    );

    // Home Page Settings Section
    add_settings_section(
        'cablecast_section_home_page',
        __('Home Page', 'cablecast'),
        'cablecast_section_home_page_cb',
        'cablecast'
    );

    add_settings_field(
        'cablecast_field_home_page_sections',
        __('Home Page Sections', 'cablecast'),
        'cablecast_field_home_page_sections_cb',
        'cablecast',
        'cablecast_section_home_page'
    );

    add_settings_field(
        'cablecast_field_home_page_create',
        __('Quick Setup', 'cablecast'),
        'cablecast_field_home_page_create_cb',
        'cablecast',
        'cablecast_section_home_page'
    );

    // Maintenance Section
    add_settings_section(
        'cablecast_section_maintenance',
        __('Maintenance', 'cablecast'),
        'cablecast_section_maintenance_cb',
        'cablecast'
    );

    add_settings_field(
        'cablecast_field_sync_status',
        __('Sync Status', 'cablecast'),
        'cablecast_field_sync_status_cb',
        'cablecast',
        'cablecast_section_maintenance'
    );

    add_settings_field(
        'cablecast_field_reset_sync',
        __('Reset Sync', 'cablecast'),
        'cablecast_field_reset_sync_cb',
        'cablecast',
        'cablecast_section_maintenance'
    );

    add_settings_field(
        'cablecast_field_clear_schedule',
        __('Clear Schedule', 'cablecast'),
        'cablecast_field_clear_schedule_cb',
        'cablecast',
        'cablecast_section_maintenance'
    );

    // Danger Zone Section
    add_settings_section(
        'cablecast_section_danger',
        __('Danger Zone', 'cablecast'),
        'cablecast_section_danger_cb',
        'cablecast'
    );

    add_settings_field(
        'cablecast_field_clear_all_content',
        __('Clear All Content', 'cablecast'),
        'cablecast_field_clear_all_content_cb',
        'cablecast',
        'cablecast_section_danger'
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
        // Use GET with page_size=1 since some servers don't support HEAD requests
        $test_url = rtrim($server, '/') . CABLECAST_API_BASE . '/shows?page_size=1';
        $response = wp_remote_get($test_url, ['timeout' => 10]);

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
    // Use GET since some servers don't support HEAD requests
    $thumbnail_url = get_post_meta($shows[0]->ID, 'cablecast_thumbnail_url', true);
    $response = wp_remote_get($thumbnail_url, ['timeout' => 10]);

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

// Shortcode settings callbacks
function cablecast_section_shortcodes_cb($args)
{
    ?>
    <p><?php _e('Configure settings for Cablecast shortcodes.', 'cablecast'); ?></p>
    <?php
}

function cablecast_field_shortcode_styles_cb($args)
{
    $options = get_option('cablecast_options');
    // Default to true if never saved, otherwise use the saved value
    $styles_enabled = !isset($options['shortcode_styles']) ? true : (bool) $options['shortcode_styles'];
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="cablecast_options[shortcode_styles]" value="1" <?php checked($styles_enabled); ?>>
            <?php _e('Enable default shortcode styling', 'cablecast'); ?>
        </label>
        <p class="description" style="margin-top: 4px;">
            <?php _e('When enabled, shortcodes include professional default CSS. Disable for full theme control over styling.', 'cablecast'); ?>
        </p>
    </fieldset>
    <?php
}

function cablecast_field_enable_templates_cb($args)
{
    $options = get_option('cablecast_options');
    // Default to true if never saved, otherwise use the saved value
    $templates_enabled = !isset($options['enable_templates']) ? true : (bool) $options['enable_templates'];
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="cablecast_options[enable_templates]" value="1" <?php checked($templates_enabled); ?>>
            <?php _e('Enable plugin templates for show, channel, and archive pages', 'cablecast'); ?>
        </label>
        <p class="description" style="margin-top: 4px;">
            <?php _e('When enabled, the plugin provides templates for single shows, channels, producers, and series pages. Disable if your theme provides its own templates.', 'cablecast'); ?>
        </p>
    </fieldset>
    <?php
}

function cablecast_field_filler_keywords_cb($args)
{
    $options = get_option('cablecast_options');
    $filler_keywords = isset($options['filler_keywords']) ? $options['filler_keywords'] : '';
    $default_keywords = implode(', ', CABLECAST_DEFAULT_FILLER_KEYWORDS);
    ?>
    <textarea name="cablecast_options[filler_keywords]" rows="3" cols="50" class="large-text code"><?php echo esc_textarea($filler_keywords); ?></textarea>
    <p class="description">
        <?php _e('Comma-separated list of keywords to identify filler content (e.g., color bars, test patterns). Programs matching these keywords can be hidden in schedule shortcodes.', 'cablecast'); ?>
    </p>
    <p class="description">
        <strong><?php _e('Default keywords:', 'cablecast'); ?></strong> <?php echo esc_html($default_keywords); ?>
    </p>
    <?php
}

function cablecast_field_category_colors_cb($args)
{
    $options = get_option('cablecast_options');
    $colors_enabled = !empty($options['enable_category_colors']);
    $category_colors = isset($options['category_colors']) ? $options['category_colors'] : [];

    // Get all categories used by shows
    $categories = get_terms([
        'taxonomy' => 'category',
        'hide_empty' => true,
        'object_ids' => get_posts([
            'post_type' => 'show',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]),
    ]);
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="cablecast_options[enable_category_colors]" value="1" <?php checked($colors_enabled); ?> id="cablecast-enable-category-colors">
            <?php _e('Enable category color coding in schedules', 'cablecast'); ?>
        </label>
        <p class="description" style="margin-top: 4px;">
            <?php _e('When enabled, schedule items will be color-coded based on their category.', 'cablecast'); ?>
        </p>
    </fieldset>

    <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
    <div id="cablecast-category-colors-table" style="margin-top: 15px; <?php echo $colors_enabled ? '' : 'display: none;'; ?>">
        <table class="widefat fixed" style="max-width: 500px;">
            <thead>
                <tr>
                    <th><?php _e('Category', 'cablecast'); ?></th>
                    <th style="width: 150px;"><?php _e('Color', 'cablecast'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category) :
                    $current_color = isset($category_colors[$category->slug]) ? $category_colors[$category->slug] : '';
                ?>
                <tr>
                    <td>
                        <?php echo esc_html($category->name); ?>
                        <span class="description">(<?php echo esc_html($category->count); ?> <?php _e('shows', 'cablecast'); ?>)</span>
                    </td>
                    <td>
                        <input type="color"
                               name="cablecast_options[category_colors][<?php echo esc_attr($category->slug); ?>]"
                               value="<?php echo esc_attr($current_color ?: '#cccccc'); ?>"
                               style="width: 50px; height: 30px; padding: 0; border: 1px solid #ddd; cursor: pointer;">
                        <button type="button" class="button button-small cablecast-clear-color" data-slug="<?php echo esc_attr($category->slug); ?>" style="margin-left: 5px;">
                            <?php _e('Clear', 'cablecast'); ?>
                        </button>
                        <input type="hidden" class="cablecast-color-cleared" name="cablecast_options[category_colors_cleared][<?php echo esc_attr($category->slug); ?>]" value="<?php echo $current_color ? '0' : '1'; ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description" style="margin-top: 8px;">
            <?php _e('Click "Clear" to remove a category color. Only categories with assigned colors will be highlighted.', 'cablecast'); ?>
        </p>
    </div>
    <?php else : ?>
    <p class="description" style="margin-top: 10px;">
        <?php _e('No categories found. Sync shows with categories to configure colors.', 'cablecast'); ?>
    </p>
    <?php endif; ?>

    <script>
    jQuery(function($) {
        // Toggle category colors table visibility
        $('#cablecast-enable-category-colors').on('change', function() {
            $('#cablecast-category-colors-table').toggle(this.checked);
        });

        // Clear color button
        $('.cablecast-clear-color').on('click', function() {
            var $btn = $(this);
            var $input = $btn.prev('input[type="color"]');
            var $cleared = $btn.next('.cablecast-color-cleared');
            $input.val('#cccccc');
            $cleared.val('1');
        });

        // Mark as not cleared when color is changed
        $('input[type="color"]').on('change', function() {
            $(this).siblings('.cablecast-clear-color').next('.cablecast-color-cleared').val('0');
        });
    });
    </script>
    <?php
}

// Filter to clean up category colors on save
add_filter('pre_update_option_cablecast_options', function($value, $old_value) {
    // Remove cleared colors
    if (isset($value['category_colors']) && isset($value['category_colors_cleared'])) {
        foreach ($value['category_colors_cleared'] as $slug => $cleared) {
            if ($cleared === '1' && isset($value['category_colors'][$slug])) {
                unset($value['category_colors'][$slug]);
            }
        }
        unset($value['category_colors_cleared']);
    }

    // Remove default/gray colors (user never set them)
    if (isset($value['category_colors'])) {
        $value['category_colors'] = array_filter($value['category_colors'], function($color) {
            return $color && $color !== '#cccccc';
        });
    }

    return $value;
}, 10, 2);

// Home Page settings callbacks
function cablecast_section_home_page_cb($args)
{
    ?>
    <p><?php _e('Configure the Cablecast home page shortcode. Use <code>[cablecast_home]</code> on any page or assign the "Cablecast Home" page template.', 'cablecast'); ?></p>
    <?php
}

function cablecast_field_home_page_sections_cb($args)
{
    $options = get_option('cablecast_options');
    $home = isset($options['home_page']) ? $options['home_page'] : [];

    $show_now_playing = isset($home['show_now_playing']) ? $home['show_now_playing'] : true;
    $show_schedule = isset($home['show_schedule']) ? $home['show_schedule'] : true;
    $schedule_days = isset($home['schedule_days']) ? $home['schedule_days'] : 7;
    $show_recent = isset($home['show_recent']) ? $home['show_recent'] : true;
    $recent_count = isset($home['recent_count']) ? $home['recent_count'] : 12;
    $show_browse = isset($home['show_browse']) ? $home['show_browse'] : true;
    ?>
    <fieldset>
        <label style="display: block; margin-bottom: 10px;">
            <input type="checkbox" name="cablecast_options[home_page][show_now_playing]" value="1" <?php checked($show_now_playing); ?>>
            <?php _e('Now Playing - Show what\'s currently on air', 'cablecast'); ?>
        </label>

        <label style="display: block; margin-bottom: 5px;">
            <input type="checkbox" name="cablecast_options[home_page][show_schedule]" value="1" <?php checked($show_schedule); ?>>
            <?php _e('Weekly Schedule - Display schedule grid', 'cablecast'); ?>
        </label>
        <p style="margin-left: 24px; margin-top: 0; margin-bottom: 10px;">
            <label>
                <?php _e('Days to show:', 'cablecast'); ?>
                <input type="number" name="cablecast_options[home_page][schedule_days]" value="<?php echo esc_attr($schedule_days); ?>" min="1" max="14" style="width: 60px;">
            </label>
        </p>

        <label style="display: block; margin-bottom: 5px;">
            <input type="checkbox" name="cablecast_options[home_page][show_recent]" value="1" <?php checked($show_recent); ?>>
            <?php _e('Recent Shows - Gallery of newest content', 'cablecast'); ?>
        </label>
        <p style="margin-left: 24px; margin-top: 0; margin-bottom: 10px;">
            <label>
                <?php _e('Number of shows:', 'cablecast'); ?>
                <input type="number" name="cablecast_options[home_page][recent_count]" value="<?php echo esc_attr($recent_count); ?>" min="4" max="24" style="width: 60px;">
            </label>
        </p>

        <label style="display: block; margin-bottom: 10px;">
            <input type="checkbox" name="cablecast_options[home_page][show_browse]" value="1" <?php checked($show_browse); ?>>
            <?php _e('Browse - Series, categories, and producers', 'cablecast'); ?>
        </label>
    </fieldset>
    <?php
}

function cablecast_field_home_page_create_cb($args)
{
    // Check if a home page already exists
    $existing_page = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'meta_query' => [[
            'key' => '_wp_page_template',
            'value' => 'cablecast-home',
        ]],
        'posts_per_page' => 1,
    ]);

    if (!empty($existing_page)) {
        $page = $existing_page[0];
        $edit_link = get_edit_post_link($page->ID);
        $view_link = get_permalink($page->ID);
        ?>
        <div class="notice notice-success inline" style="margin: 0; padding: 10px;">
            <p>
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php printf(__('Home page exists: <strong>%s</strong>', 'cablecast'), esc_html($page->post_title)); ?>
                (<a href="<?php echo esc_url($edit_link); ?>"><?php _e('Edit', 'cablecast'); ?></a> |
                <a href="<?php echo esc_url($view_link); ?>" target="_blank"><?php _e('View', 'cablecast'); ?></a>)
            </p>
        </div>
        <?php
        return;
    }
    ?>
    <button type="button" id="cablecast-create-home-page" class="button button-primary">
        <?php _e('Create Home Page', 'cablecast'); ?>
    </button>
    <span id="cablecast-create-home-status" style="margin-left: 10px;"></span>
    <p class="description" style="margin-top: 8px;">
        <?php _e('Creates a new page with the Cablecast Home template. You can then set it as your site\'s front page in Settings > Reading.', 'cablecast'); ?>
    </p>

    <script>
    jQuery(function($) {
        $('#cablecast-create-home-page').on('click', function() {
            var $btn = $(this);
            var $status = $('#cablecast-create-home-status');

            $btn.prop('disabled', true);
            $status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php _e('Creating...', 'cablecast'); ?>');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'cablecast_create_home_page',
                    nonce: '<?php echo wp_create_nonce('cablecast_create_home_page'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;"><?php _e('Created!', 'cablecast'); ?></span>');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $status.html('<span style="color: #dc3232;">' + response.data + '</span>');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.html('<span style="color: #dc3232;"><?php _e('Error creating page', 'cablecast'); ?></span>');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler for creating home page
add_action('wp_ajax_cablecast_create_home_page', function() {
    if (!current_user_can('edit_pages')) {
        wp_send_json_error(__('Permission denied', 'cablecast'));
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cablecast_create_home_page')) {
        wp_send_json_error(__('Invalid nonce', 'cablecast'));
    }

    // Build modular page content with individual shortcodes
    $page_content = <<<CONTENT
<!-- wp:heading -->
<h2>Now Playing</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[cablecast_now_playing show_up_next="true" show_thumbnail="true" show_description="true" exclude_filler="true"]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2>This Week's Schedule</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[cablecast_weekly_guide days="7" show_channel_switcher="true" show_category_colors="true"]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2>Recent Shows</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[cablecast_shows count="12" layout="featured" columns="4" orderby="date" order="DESC"]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2>Browse by Series</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[cablecast_series count="6" layout="grid" show_thumbnails="true"]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2>Categories</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[cablecast_categories layout="cloud" show_colors="true" show_counts="true"]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2>Producers</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[cablecast_producers count="10" orderby="count" layout="list"]
<!-- /wp:shortcode -->
CONTENT;

    $page_id = wp_insert_post([
        'post_type' => 'page',
        'post_title' => __('Cablecast Home', 'cablecast'),
        'post_content' => $page_content,
        'post_status' => 'publish',
    ]);

    if (is_wp_error($page_id)) {
        wp_send_json_error($page_id->get_error_message());
    }

    // Set the page template
    update_post_meta($page_id, '_wp_page_template', 'cablecast-home');

    wp_send_json_success([
        'page_id' => $page_id,
        'edit_url' => get_edit_post_link($page_id, 'raw'),
    ]);
});

// Maintenance section callbacks
function cablecast_section_maintenance_cb($args)
{
    ?>
    <p><?php _e('Tools for troubleshooting and managing sync state.', 'cablecast'); ?></p>
    <?php
}

function cablecast_field_sync_status_cb($args)
{
    $total = get_option('cablecast_sync_total_result_count', 0);
    $sync_index = get_option('cablecast_sync_index', 0);
    $since = get_option('cablecast_sync_since');
    $remaining = max(0, $total - $sync_index);

    $since_display = $since ? date('F j, Y', strtotime($since)) : __('beginning of time', 'cablecast');
    ?>
    <div class="notice notice-info inline" style="margin: 0; padding: 10px;">
        <p>
            <strong><?php _e('Syncing shows modified after:', 'cablecast'); ?></strong> <?php echo esc_html($since_display); ?><br>
            <strong><?php _e('Progress:', 'cablecast'); ?></strong>
            <?php if ($total > 0): ?>
                <?php printf(__('%d of %d shows processed (%d remaining)', 'cablecast'), $sync_index, $total, $remaining); ?>
            <?php else: ?>
                <?php _e('No sync in progress', 'cablecast'); ?>
            <?php endif; ?>
        </p>
    </div>
    <?php
}

function cablecast_field_reset_sync_cb($args)
{
    // Default to 1 year ago
    $default_date = date('Y-m-d', strtotime('-1 year'));
    $current_since = get_option('cablecast_sync_since');
    $current_date = $current_since ? date('Y-m-d', strtotime($current_since)) : $default_date;
    ?>
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <label for="cablecast-reset-sync-date">
            <?php _e('Sync shows modified after:', 'cablecast'); ?>
        </label>
        <input type="date" id="cablecast-reset-sync-date" value="<?php echo esc_attr($current_date); ?>" style="width: auto;">
        <button type="button" class="button" id="cablecast-reset-sync">
            <?php _e('Reset Sync', 'cablecast'); ?>
        </button>
        <span id="cablecast-reset-sync-result" style="margin-left: 5px;"></span>
    </div>
    <p class="description" style="margin-top: 8px;">
        <?php _e('Clears current sync progress and starts fresh from the selected date. Use an older date to sync more historical content, or a recent date to limit to new shows only.', 'cablecast'); ?>
    </p>
    <script>
    jQuery(function($) {
        $('#cablecast-reset-sync').on('click', function() {
            var $btn = $(this);
            var $result = $('#cablecast-reset-sync-result');
            var syncDate = $('#cablecast-reset-sync-date').val();

            if (!syncDate) {
                $result.html('<span style="color: #d63638;"><?php _e('Please select a date', 'cablecast'); ?></span>');
                return;
            }

            if (!confirm('<?php _e('Are you sure you want to reset the sync? This will clear current progress and start fresh from the selected date.', 'cablecast'); ?>')) {
                return;
            }

            $btn.prop('disabled', true).text('<?php _e('Resetting...', 'cablecast'); ?>');
            $result.text('');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'cablecast_reset_sync',
                    nonce: '<?php echo wp_create_nonce('cablecast_reset_sync'); ?>',
                    sync_date: syncDate
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('<?php _e('Reset Sync', 'cablecast'); ?>');
                    if (response.success) {
                        $result.html('<span style="color: #00a32a;">&#10004; ' + response.data + '</span>');
                        // Reload page after short delay to show updated status
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $result.html('<span style="color: #d63638;">&#10006; ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('<?php _e('Reset Sync', 'cablecast'); ?>');
                    $result.html('<span style="color: #d63638;">&#10006; <?php _e('Request failed', 'cablecast'); ?></span>');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler for reset sync
add_action('wp_ajax_cablecast_reset_sync', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized', 'cablecast'));
        return;
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cablecast_reset_sync')) {
        wp_send_json_error(__('Invalid nonce', 'cablecast'));
        return;
    }

    $sync_date = sanitize_text_field($_POST['sync_date'] ?? '');
    if (empty($sync_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sync_date)) {
        wp_send_json_error(__('Invalid date format', 'cablecast'));
        return;
    }

    // Convert date to the format used by sync (ISO 8601 with time)
    $sync_since = date('Y-m-d\TH:i:s', strtotime($sync_date));

    update_option('cablecast_sync_since', $sync_since);
    update_option('cablecast_sync_index', 0);
    update_option('cablecast_sync_total_result_count', 0);

    $formatted_date = date('F j, Y', strtotime($sync_date));
    wp_send_json_success(sprintf(__('Sync reset. Next cron run will sync shows modified after %s.', 'cablecast'), $formatted_date));
});

function cablecast_field_clear_schedule_cb($args)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'cablecast_schedule_items';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    ?>
    <div style="display: flex; align-items: center; gap: 10px;">
        <button type="button" class="button" id="cablecast-clear-schedule">
            <?php _e('Clear Schedule', 'cablecast'); ?>
        </button>
        <span id="cablecast-clear-schedule-result"></span>
    </div>
    <p class="description" style="margin-top: 8px;">
        <?php printf(__('Removes all %d schedule items from the database. Schedule will be rebuilt on next sync.', 'cablecast'), intval($count)); ?>
    </p>
    <script>
    jQuery(function($) {
        $('#cablecast-clear-schedule').on('click', function() {
            var $btn = $(this);
            var $result = $('#cablecast-clear-schedule-result');

            if (!confirm('<?php _e('Are you sure you want to clear all schedule data? This cannot be undone.', 'cablecast'); ?>')) {
                return;
            }

            $btn.prop('disabled', true).text('<?php _e('Clearing...', 'cablecast'); ?>');
            $result.text('');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'cablecast_clear_schedule',
                    nonce: '<?php echo wp_create_nonce('cablecast_clear_schedule'); ?>'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('<?php _e('Clear Schedule', 'cablecast'); ?>');
                    if (response.success) {
                        $result.html('<span style="color: #00a32a;">&#10004; ' + response.data + '</span>');
                        // Reload page after short delay to show updated count
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $result.html('<span style="color: #d63638;">&#10006; ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('<?php _e('Clear Schedule', 'cablecast'); ?>');
                    $result.html('<span style="color: #d63638;">&#10006; <?php _e('Request failed', 'cablecast'); ?></span>');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler for clear schedule
add_action('wp_ajax_cablecast_clear_schedule', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized', 'cablecast'));
        return;
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cablecast_clear_schedule')) {
        wp_send_json_error(__('Invalid nonce', 'cablecast'));
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cablecast_schedule_items';

    // Use TRUNCATE for efficiency (resets auto-increment too)
    $result = $wpdb->query("TRUNCATE TABLE $table_name");

    if ($result !== false) {
        wp_send_json_success(__('Schedule cleared. Next sync will rebuild schedule data.', 'cablecast'));
    } else {
        wp_send_json_error(__('Failed to clear schedule', 'cablecast'));
    }
});

// Danger Zone section callback
function cablecast_section_danger_cb($args)
{
    ?>
    <div style="background: #fff6f6; border: 2px solid #d63638; border-radius: 4px; padding: 15px; margin-bottom: 10px;">
        <p style="color: #d63638; margin: 0; font-weight: 600;">
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
            <?php _e('Warning: Actions in this section are destructive and cannot be undone. Use with caution.', 'cablecast'); ?>
        </p>
    </div>
    <?php
}

function cablecast_field_clear_all_content_cb($args)
{
    // Count content (safely handle missing properties)
    $show_counts = wp_count_posts('show');
    $show_count = ($show_counts->publish ?? 0) + ($show_counts->draft ?? 0) + ($show_counts->private ?? 0);

    $channel_counts = wp_count_posts('cablecast_channel');
    $channel_count = ($channel_counts->publish ?? 0) + ($channel_counts->draft ?? 0);

    // Projects and Producers are taxonomies, not post types
    $project_count = wp_count_terms(['taxonomy' => 'cablecast_project', 'hide_empty' => false]);
    if (is_wp_error($project_count)) {
        $project_count = 0;
    }

    $producer_count = wp_count_terms(['taxonomy' => 'cablecast_producer', 'hide_empty' => false]);
    if (is_wp_error($producer_count)) {
        $producer_count = 0;
    }

    // Count custom field taxonomy terms
    $custom_tax_count = 0;
    $custom_taxonomies = cablecast_get_custom_taxonomies();
    foreach ($custom_taxonomies as $tax_name) {
        $count = wp_count_terms(['taxonomy' => $tax_name, 'hide_empty' => false]);
        if (!is_wp_error($count)) {
            $custom_tax_count += $count;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cablecast_schedule_items';
    $schedule_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    $total_count = $show_count + $channel_count + $project_count + $producer_count + $custom_tax_count + $schedule_count;
    ?>
    <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px; max-width: 500px;">
        <p style="margin-top: 0;">
            <strong><?php _e('This will permanently delete:', 'cablecast'); ?></strong>
        </p>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li><?php printf(__('%d Shows (and their thumbnails)', 'cablecast'), $show_count); ?></li>
            <li><?php printf(__('%d Channels', 'cablecast'), $channel_count); ?></li>
            <li><?php printf(__('%d Series terms', 'cablecast'), $project_count); ?></li>
            <li><?php printf(__('%d Producer terms', 'cablecast'), $producer_count); ?></li>
            <?php if ($custom_tax_count > 0) : ?>
            <li><?php printf(__('%d Custom field terms', 'cablecast'), $custom_tax_count); ?></li>
            <?php endif; ?>
            <li><?php printf(__('%d Schedule Items', 'cablecast'), $schedule_count); ?></li>
        </ul>
        <p style="color: #666; font-size: 12px; margin-bottom: 15px;">
            <?php _e('Sync state will also be reset. This is useful when switching to a different Cablecast server.', 'cablecast'); ?>
        </p>

        <?php if ($total_count > 0) : ?>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="button" id="cablecast-clear-all-content" style="background: #d63638; border-color: #d63638; color: #fff;">
                <?php _e('Delete All Cablecast Content', 'cablecast'); ?>
            </button>
            <span id="cablecast-clear-all-result"></span>
        </div>
        <?php else : ?>
        <p class="description"><?php _e('No Cablecast content to delete.', 'cablecast'); ?></p>
        <?php endif; ?>
    </div>

    <script>
    jQuery(function($) {
        $('#cablecast-clear-all-content').on('click', function() {
            var $btn = $(this);
            var $result = $('#cablecast-clear-all-result');

            // Double confirmation for safety
            if (!confirm('<?php _e('WARNING: This will permanently delete ALL Cablecast content including shows, channels, projects, producers, and schedule data.\n\nAre you sure you want to continue?', 'cablecast'); ?>')) {
                return;
            }

            var confirmText = prompt('<?php _e('Type DELETE to confirm:', 'cablecast'); ?>');
            if (confirmText !== 'DELETE') {
                $result.html('<span style="color: #666;"><?php _e('Cancelled', 'cablecast'); ?></span>');
                return;
            }

            $btn.prop('disabled', true).text('<?php _e('Deleting...', 'cablecast'); ?>');
            $result.html('<span style="color: #666;"><?php _e('This may take a while...', 'cablecast'); ?></span>');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                timeout: 300000, // 5 minute timeout for large datasets
                data: {
                    action: 'cablecast_clear_all_content',
                    nonce: '<?php echo wp_create_nonce('cablecast_clear_all_content'); ?>'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('<?php _e('Delete All Cablecast Content', 'cablecast'); ?>');
                    if (response.success) {
                        $result.html('<span style="color: #00a32a;">&#10004; ' + response.data + '</span>');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $result.html('<span style="color: #d63638;">&#10006; ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('<?php _e('Delete All Cablecast Content', 'cablecast'); ?>');
                    $result.html('<span style="color: #d63638;">&#10006; <?php _e('Request failed or timed out', 'cablecast'); ?></span>');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler for clear all content
add_action('wp_ajax_cablecast_clear_all_content', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized', 'cablecast'));
        return;
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cablecast_clear_all_content')) {
        wp_send_json_error(__('Invalid nonce', 'cablecast'));
        return;
    }

    global $wpdb;
    $deleted = ['shows' => 0, 'channels' => 0, 'series' => 0, 'producers' => 0, 'custom_terms' => 0, 'schedule' => 0];

    // Delete shows (with thumbnails)
    $shows = get_posts([
        'post_type' => 'show',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
    ]);
    foreach ($shows as $show_id) {
        // Delete thumbnail attachment if exists
        $thumbnail_id = get_post_thumbnail_id($show_id);
        if ($thumbnail_id) {
            wp_delete_attachment($thumbnail_id, true);
        }
        wp_delete_post($show_id, true);
        $deleted['shows']++;
    }

    // Delete channels (correct post type is cablecast_channel)
    $channels = get_posts([
        'post_type' => 'cablecast_channel',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
    ]);
    foreach ($channels as $channel_id) {
        wp_delete_post($channel_id, true);
        $deleted['channels']++;
    }

    // Delete series/project taxonomy terms
    $series_terms = get_terms([
        'taxonomy' => 'cablecast_project',
        'hide_empty' => false,
        'fields' => 'ids',
    ]);
    if (!is_wp_error($series_terms)) {
        foreach ($series_terms as $term_id) {
            wp_delete_term($term_id, 'cablecast_project');
            $deleted['series']++;
        }
    }

    // Delete producer taxonomy terms
    $producer_terms = get_terms([
        'taxonomy' => 'cablecast_producer',
        'hide_empty' => false,
        'fields' => 'ids',
    ]);
    if (!is_wp_error($producer_terms)) {
        foreach ($producer_terms as $term_id) {
            wp_delete_term($term_id, 'cablecast_producer');
            $deleted['producers']++;
        }
    }

    // Delete custom field taxonomy terms (cbl-tax-*)
    $custom_taxonomies = cablecast_get_custom_taxonomies();
    foreach ($custom_taxonomies as $tax_name) {
        $custom_terms = get_terms([
            'taxonomy' => $tax_name,
            'hide_empty' => false,
            'fields' => 'ids',
        ]);
        if (!is_wp_error($custom_terms)) {
            foreach ($custom_terms as $term_id) {
                wp_delete_term($term_id, $tax_name);
                $deleted['custom_terms']++;
            }
        }
    }

    // Clear schedule table
    $table_name = $wpdb->prefix . 'cablecast_schedule_items';
    $deleted['schedule'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $wpdb->query("TRUNCATE TABLE $table_name");

    // Reset sync state
    update_option('cablecast_sync_since', date('Y-m-d\TH:i:s', strtotime('-1 year')));
    update_option('cablecast_sync_index', 0);
    update_option('cablecast_sync_total_result_count', 0);

    $message = sprintf(
        __('Deleted %d shows, %d channels, %d series, %d producers, %d custom terms, %d schedule items. Sync state reset.', 'cablecast'),
        $deleted['shows'],
        $deleted['channels'],
        $deleted['series'],
        $deleted['producers'],
        $deleted['custom_terms'],
        $deleted['schedule']
    );

    wp_send_json_success($message);
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

    // add shortcode documentation submenu
    add_submenu_page(
        'cablecast',
        __('Shortcode Documentation', 'cablecast'),
        __('Shortcode Docs', 'cablecast'),
        'manage_options',
        'cablecast-shortcode-docs',
        'cablecast_shortcode_docs_page'
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
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
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
