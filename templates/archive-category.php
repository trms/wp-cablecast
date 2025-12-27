<?php
/**
 * Template: Category Archive (Shows)
 *
 * This template displays shows from a specific category using the shows grid.
 * It only applies when the category contains shows.
 *
 * To override this template, copy it to: yourtheme/cablecast/archive-category.php
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
 * Hook: cablecast_before_archive_category
 *
 * @hooked None by default
 */
do_action('cablecast_before_archive_category', $term);
?>

<div class="cablecast-taxonomy-archive cablecast-category-archive">

    <?php
    /**
     * Hook: cablecast_before_archive_category_header
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_category_header', $term);
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
            // Count shows in this category specifically
            $show_count = new WP_Query([
                'post_type' => 'show',
                'tax_query' => [[
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ]],
                'posts_per_page' => 1,
                'fields' => 'ids',
            ]);
            $count = $show_count->found_posts;
            /* translators: %d: number of shows */
            printf(esc_html(_n('%d show', '%d shows', $count, 'cablecast')), $count);
            wp_reset_postdata();
            ?>
        </div>
    </header>

    <?php
    /**
     * Hook: cablecast_after_archive_category_header
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_category_header', $term);

    /**
     * Hook: cablecast_before_archive_category_shows
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_category_shows', $term);

    /**
     * Filter the shows shortcode attributes for category archive.
     *
     * @param array   $atts Shortcode attributes.
     * @param WP_Term $term The category term object.
     */
    $shows_atts = apply_filters('cablecast_archive_category_shows_atts', [
        'category'        => $term->slug,
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
     * Hook: cablecast_after_archive_category_shows
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_category_shows', $term);
    ?>

</div>

<?php
/**
 * Hook: cablecast_after_archive_category
 *
 * @hooked None by default
 */
do_action('cablecast_after_archive_category', $term);

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
