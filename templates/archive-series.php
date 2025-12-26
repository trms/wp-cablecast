<?php
/**
 * Template: Series Archive
 *
 * This template displays shows from a specific series/project using the shows grid.
 *
 * To override this template, copy it to: yourtheme/cablecast/archive-series.php
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

$term = get_queried_object();

/**
 * Hook: cablecast_before_archive_series
 *
 * @hooked None by default
 */
do_action('cablecast_before_archive_series', $term);
?>

<div class="cablecast-taxonomy-archive cablecast-series-archive">

    <?php
    /**
     * Hook: cablecast_before_archive_series_header
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_series_header', $term);
    ?>

    <header class="cablecast-taxonomy-archive__header">
        <h1 class="cablecast-taxonomy-archive__title"><?php echo esc_html($term->name); ?></h1>

        <?php if (!empty($term->description)) : ?>
            <div class="cablecast-taxonomy-archive__description">
                <?php echo wpautop(esc_html($term->description)); ?>
            </div>
        <?php endif; ?>

        <div class="cablecast-taxonomy-archive__count">
            <?php
            /* translators: %d: number of episodes */
            printf(esc_html(_n('%d episode', '%d episodes', $term->count, 'cablecast')), $term->count);
            ?>
        </div>
    </header>

    <?php
    /**
     * Hook: cablecast_after_archive_series_header
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_series_header', $term);

    /**
     * Hook: cablecast_before_archive_series_shows
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_series_shows', $term);

    /**
     * Filter the shows shortcode attributes for series archive.
     *
     * @param array   $atts Shortcode attributes.
     * @param WP_Term $term The series term object.
     */
    $shows_atts = apply_filters('cablecast_archive_series_shows_atts', [
        'series'          => $term->slug,
        'count'           => 24,
        'columns'         => 4,
        'show_pagination' => 'true',
        'orderby'         => 'date',
        'order'           => 'DESC',
    ], $term);

    $atts_string = '';
    foreach ($shows_atts as $key => $value) {
        $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
    }
    ?>

    <div class="cablecast-taxonomy-archive__content">
        <?php echo do_shortcode('[cablecast_shows' . $atts_string . ']'); ?>
    </div>

    <?php
    /**
     * Hook: cablecast_after_archive_series_shows
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_series_shows', $term);
    ?>

</div>

<?php
/**
 * Hook: cablecast_after_archive_series
 *
 * @hooked None by default
 */
do_action('cablecast_after_archive_series', $term);

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
