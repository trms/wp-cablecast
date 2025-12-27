<?php
/**
 * Template Part: Show Content
 *
 * This template part displays a show card for use in archives and grids.
 * It's a reusable partial that can be customized.
 *
 * To override this template, copy it to: yourtheme/cablecast/content-show.php
 *
 * Available variables:
 * - $post: The show post object (WP_Post)
 *
 * @package Cablecast
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook: cablecast_before_show_card
 *
 * @hooked None by default
 */
do_action('cablecast_before_show_card', $post);
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('cablecast-show-card'); ?>>

    <?php if (has_post_thumbnail()) : ?>
        <a href="<?php the_permalink(); ?>" class="cablecast-show-card__thumbnail-link">
            <div class="cablecast-show-card__thumbnail">
                <?php the_post_thumbnail('medium', ['class' => 'cablecast-show-card__thumbnail-img']); ?>
            </div>
        </a>
    <?php endif; ?>

    <div class="cablecast-show-card__content">
        <h3 class="cablecast-show-card__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <?php
        $trt = get_post_meta(get_the_ID(), 'cablecast_show_trt', true);
        if (!empty($trt)) :
            $formatted_trt = function_exists('cablecast_format_runtime')
                ? cablecast_format_runtime(absint($trt))
                : gmdate('H:i:s', absint($trt));
        ?>
            <div class="cablecast-show-card__runtime">
                <?php echo esc_html($formatted_trt); ?>
            </div>
        <?php endif; ?>

        <?php
        $category = get_post_meta(get_the_ID(), 'cablecast_category_name', true);
        if (!empty($category)) :
        ?>
            <div class="cablecast-show-card__category">
                <?php echo esc_html($category); ?>
            </div>
        <?php endif; ?>
    </div>

</article>

<?php
/**
 * Hook: cablecast_after_show_card
 *
 * @hooked None by default
 */
do_action('cablecast_after_show_card', $post);
