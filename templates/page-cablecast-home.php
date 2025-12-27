<?php
/**
 * Template: Cablecast Home Page
 *
 * A custom page template for creating a Cablecast home page.
 * Renders the [cablecast_home] shortcode with all sections.
 *
 * To override this template, copy it to: yourtheme/cablecast/page-cablecast-home.php
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
 * Hook: cablecast_before_home_page
 *
 * @hooked None by default
 */
do_action('cablecast_before_home_page');
?>

<main id="cablecast-home-main" class="cablecast-home-page">

    <?php
    /**
     * Hook: cablecast_before_home_content
     *
     * @hooked None by default
     */
    do_action('cablecast_before_home_content');

    // Render the home shortcode
    echo do_shortcode('[cablecast_home]');

    /**
     * Hook: cablecast_after_home_content
     *
     * @hooked None by default
     */
    do_action('cablecast_after_home_content');
    ?>

</main>

<?php
/**
 * Hook: cablecast_after_home_page
 *
 * @hooked None by default
 */
do_action('cablecast_after_home_page');

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
