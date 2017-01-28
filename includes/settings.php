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
}

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
        <p>WordPress's built in cron is still enabled. This causes the cablecast plugin to attempt to sync during regular web requests which can lead to failures and poor user expericnes. It is recomended to disable the built in cron and instead run cron using the system task scheduler. See <a href="https://developer.wordpress.org/plugins/cron/hooking-into-the-system-task-scheduler/">https://developer.wordpress.org/plugins/cron/hooking-into-the-system-task-scheduler/</a> for more info.</p>
      </div>
      <?php
    }

    ?>

    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <div class="notice notice-info">
          <p><strong>Current Sync</strong>: <?= esc_html(get_option('cablecast_sync_since')); ?></p>
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