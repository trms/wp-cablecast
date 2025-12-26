<?php
/**
 * Template: Single Show
 *
 * This template displays a single show page with VOD player, chapters,
 * metadata, and upcoming airings.
 *
 * To override this template, copy it to: yourtheme/cablecast/single-show.php
 *
 * Available variables:
 * - $post: The show post object (WP_Post)
 *
 * Available functions:
 * - cablecast_has_vod($post): Check if show has VOD
 * - cablecast_has_chapters($post): Check if show has VOD chapters
 * - cablecast_get_vod_embed($post): Get VOD embed HTML
 * - cablecast_get_show_meta($post): Get array of meta data
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

while (have_posts()) :
    the_post();

    /**
     * Hook: cablecast_before_single_show
     *
     * @hooked None by default
     */
    do_action('cablecast_before_single_show');
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('cablecast-show-single'); ?>>

        <?php
        /**
         * Hook: cablecast_before_show_title
         *
         * @hooked None by default
         */
        do_action('cablecast_before_show_title', $post);
        ?>

        <header class="cablecast-show-single__header">
            <h1 class="cablecast-show-single__title"><?php the_title(); ?></h1>
        </header>

        <?php
        /**
         * Hook: cablecast_after_show_title
         *
         * @hooked None by default
         */
        do_action('cablecast_after_show_title', $post);

        /**
         * Hook: cablecast_before_show_player
         *
         * @hooked None by default
         */
        do_action('cablecast_before_show_player', $post);
        ?>

        <div class="cablecast-show-single__player">
            <?php
            if (cablecast_has_vod($post)) {
                // Show VOD player
                echo '<div class="cablecast-vod-player">';
                echo cablecast_get_vod_embed($post);
                echo '</div>';
            } elseif (has_post_thumbnail()) {
                // Show poster image only if no VOD (prevents duplicate image)
                echo '<div class="cablecast-show-single__poster">';
                the_post_thumbnail('large', ['class' => 'cablecast-show-single__poster-img']);
                echo '</div>';
            }
            ?>
        </div>

        <?php
        /**
         * Hook: cablecast_after_show_player
         *
         * @hooked None by default
         */
        do_action('cablecast_after_show_player', $post);

        // Display chapters if available
        if (cablecast_has_vod($post) && cablecast_has_chapters($post)) {
            /**
             * Hook: cablecast_before_show_chapters
             *
             * @hooked None by default
             */
            do_action('cablecast_before_show_chapters', $post);

            echo do_shortcode('[cablecast_chapters id="' . get_the_ID() . '"]');

            /**
             * Hook: cablecast_after_show_chapters
             *
             * @hooked None by default
             */
            do_action('cablecast_after_show_chapters', $post);
        }
        ?>

        <div class="cablecast-show-single__content">
            <?php
            /**
             * Hook: cablecast_before_show_description
             *
             * @hooked None by default
             */
            do_action('cablecast_before_show_description', $post);

            $content = get_the_content();
            if (!empty($content)) :
            ?>
                <div class="cablecast-show-single__description">
                    <?php the_content(); ?>
                </div>
            <?php
            endif;

            /**
             * Hook: cablecast_after_show_description
             *
             * @hooked None by default
             */
            do_action('cablecast_after_show_description', $post);

            /**
             * Hook: cablecast_before_show_meta
             *
             * @hooked None by default
             */
            do_action('cablecast_before_show_meta', $post);

            $meta = cablecast_get_show_meta($post);
            if (!empty($meta)) :
            ?>
                <div class="cablecast-show-single__meta">
                    <?php foreach ($meta as $key => $item) : ?>
                        <div class="cablecast-show-single__meta-item cablecast-show-single__meta-item--<?php echo esc_attr($key); ?>">
                            <span class="cablecast-show-single__meta-label"><?php echo esc_html($item['label']); ?></span>
                            <?php if (!empty($item['link'])) : ?>
                                <a href="<?php echo esc_url($item['link']); ?>" class="cablecast-show-single__meta-value">
                                    <?php echo esc_html($item['value']); ?>
                                </a>
                            <?php else : ?>
                                <span class="cablecast-show-single__meta-value"><?php echo esc_html($item['value']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php
            endif;

            /**
             * Hook: cablecast_after_show_meta
             *
             * @hooked None by default
             */
            do_action('cablecast_after_show_meta', $post);

            /**
             * Hook: cablecast_before_show_upcoming_runs
             *
             * @hooked None by default
             */
            do_action('cablecast_before_show_upcoming_runs', $post);

            // Display upcoming airings
            echo do_shortcode('[cablecast_upcoming_runs id="' . get_the_ID() . '" count="5"]');

            /**
             * Hook: cablecast_after_show_upcoming_runs
             *
             * @hooked None by default
             */
            do_action('cablecast_after_show_upcoming_runs', $post);
            ?>
        </div>

    </article>

    <?php
    /**
     * Hook: cablecast_after_single_show
     *
     * @hooked None by default
     */
    do_action('cablecast_after_single_show');

endwhile;

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
