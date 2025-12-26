<?php
/**
 * Template: Shows Archive
 *
 * This template displays the shows archive page with a grid of all shows.
 *
 * To override this template, copy it to: yourtheme/cablecast/archive-show.php
 *
 * @package Cablecast
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get header if theme has one (and is not a block theme), otherwise output minimal HTML
if (!wp_is_block_theme() && locate_template('header.php')) {
    get_header();
} else {
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
    <?php
}

/**
 * Hook: cablecast_before_archive_show
 *
 * @hooked None by default
 */
do_action('cablecast_before_archive_show');
?>

<div class="cablecast-archive cablecast-shows-archive">

    <?php
    /**
     * Hook: cablecast_before_archive_show_header
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_show_header');
    ?>

    <header class="cablecast-archive__header">
        <h1 class="cablecast-archive__title"><?php esc_html_e('Shows', 'cablecast'); ?></h1>
    </header>

    <?php
    /**
     * Hook: cablecast_after_archive_show_header
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_show_header');

    /**
     * Hook: cablecast_before_archive_show_content
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_show_content');

    /**
     * Filter the shows shortcode attributes for shows archive.
     *
     * @param array $atts Shortcode attributes.
     */
    $shows_atts = apply_filters('cablecast_archive_show_shows_atts', [
        'count'           => 24,
        'columns'         => 4,
        'show_pagination' => 'true',
    ]);

    $atts_string = '';
    foreach ($shows_atts as $key => $value) {
        $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
    }
    ?>

    <div class="cablecast-archive__content">
        <?php echo do_shortcode('[cablecast_shows' . $atts_string . ']'); ?>
    </div>

    <?php
    /**
     * Hook: cablecast_after_archive_show_content
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_show_content');
    ?>

</div>

<?php
/**
 * Hook: cablecast_after_archive_show
 *
 * @hooked None by default
 */
do_action('cablecast_after_archive_show');

// Get footer if theme has one (and is not a block theme), otherwise close minimal HTML
if (!wp_is_block_theme() && locate_template('footer.php')) {
    get_footer();
} else {
    wp_footer();
    ?>
</body>
</html>
    <?php
}
