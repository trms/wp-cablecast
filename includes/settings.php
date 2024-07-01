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
        'cablecast_field_server',
        __('Server', 'cablecast'),
        'cablecast_field_server_cb',
        'cablecast',
        'cablecast_section_developers',
        [
            'label_for' => 'cablecast_field_server',
            'class' => 'cablecast_row',
            'cablecast_custom_data' => 'custom',
        ]
    );

    // register a new field for the "Clear Schedule" button
    add_settings_field(
        'cablecast_clear_schedule',
        'Clear Schedule', 
        'cablecast_clear_schedule_cb',
        'cablecast',
        'cablecast_section_developers'
    );

    // register a new field for the "Reset Counters" button
    add_settings_field(
        'cablecast_reset_counters',
        'Reset Counters', 
        'cablecast_reset_counters_cb',
        'cablecast',
        'cablecast_section_developers'
    );
}

/**
 * register our cablecast_settings_init to the admin_init action hook
 */
add_action('admin_init', 'cablecast_settings_init');

function cablecast_section_developers_cb($args) { }

function cablecast_field_server_cb($args)
{
    $options = get_option('cablecast_options');
    ?>
<input type="url" name="cablecast_options[server]" class="regular-text code"
    value="<?= isset($options['server']) ? esc_attr($options['server']) : ''; ?>">
<?php
}

function cablecast_clear_schedule_cb()
{
    ?>
<input type="submit" name="clear_schedule" class="button button-secondary" value="Clear">
<?php
}

function cablecast_reset_counters_cb()
{
    ?>
<input type="submit" name="reset_counters" class="button button-secondary" value="Reset">
<?php
}

function cablecast_options_page()
{
    add_menu_page(
        'Cablecast',
        'Cablecast Settings',
        'manage_options',
        'cablecast',
        'cablecast_options_page_html'
    );
}

add_action('admin_menu', 'cablecast_options_page');

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
    <p>WordPress's built in cron is still enabled. This causes the cablecast plugin to attempt to sync during regular
        web requests which can lead to failures and poor user expericnes. It is recomended to disable the built in cron
        and instead run cron using the system task scheduler. See <a
            href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/">https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/</a>
        for more info.</p>
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
        <p>There are <?= $remaining ?> remaining shows out of <?= $total ?> shows updated after
            <?= esc_html(get_option('cablecast_sync_since')); ?></p>
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

function cablecast_handle_custom_actions()
{
    if (isset($_POST['clear_schedule'])) {
        cablecast_clear_schedule();
    }

    if (isset($_POST['reset_counters'])) {
        cablecast_reset_counters();
    }
}

add_action('admin_init', 'cablecast_handle_custom_actions');

function cablecast_clear_schedule() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cablecast_schedule_items';
    
    // Attempt to truncate the table
    $result = $wpdb->query("TRUNCATE TABLE $table_name");

    if ($result !== false) {
        // Truncation succeeded
        add_settings_error('cablecast_messages', 'cablecast_message', __('Schedule Cleared', 'cablecast'), 'updated');
    } else {
        // Truncation failed
        add_settings_error('cablecast_messages', 'cablecast_message', __('Failed to clear schedule', 'cablecast'), 'error');
    }

    // Save any errors for display later
    settings_errors('cablecast_messages');
}
function cablecast_reset_counters()
{
    // Reset the specified options
    update_option('cablecast_sync_since', current_time('mysql'));  // Set to the current date and time
    update_option('cablecast_sync_total_result_count', 0);
    update_option('cablecast_sync_index', 0);

    // Add a success message
    add_settings_error('cablecast_messages', 'cablecast_message', __('Counters Reset', 'cablecast'), 'updated');
}