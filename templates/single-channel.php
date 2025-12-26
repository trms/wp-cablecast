<?php
/**
 * Template: Single Channel
 *
 * This template displays a single channel page with live stream,
 * now playing widget, and schedule calendar.
 *
 * To override this template, copy it to: yourtheme/cablecast/single-channel.php
 *
 * Available variables:
 * - $post: The channel post object (WP_Post)
 *
 * Available functions:
 * - cablecast_get_channel_live_embed($post): Get live stream embed HTML
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
     * Hook: cablecast_before_single_channel
     *
     * @hooked None by default
     */
    do_action('cablecast_before_single_channel');
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('cablecast-channel-single'); ?>>

        <?php
        /**
         * Hook: cablecast_before_channel_title
         *
         * @hooked None by default
         */
        do_action('cablecast_before_channel_title', $post);
        ?>

        <header class="cablecast-channel-single__header">
            <h1 class="cablecast-channel-single__title"><?php the_title(); ?></h1>
        </header>

        <?php
        /**
         * Hook: cablecast_after_channel_title
         *
         * @hooked None by default
         */
        do_action('cablecast_after_channel_title', $post);

        // Display live stream embed if available
        $live_embed = cablecast_get_channel_live_embed($post);
        if (!empty($live_embed)) :
            /**
             * Hook: cablecast_before_channel_player
             *
             * @hooked None by default
             */
            do_action('cablecast_before_channel_player', $post);
            ?>
            <div class="cablecast-channel-single__player">
                <?php echo $live_embed; ?>
            </div>
            <?php
            /**
             * Hook: cablecast_after_channel_player
             *
             * @hooked None by default
             */
            do_action('cablecast_after_channel_player', $post);
        endif;

        /**
         * Hook: cablecast_before_channel_now_playing
         *
         * @hooked None by default
         */
        do_action('cablecast_before_channel_now_playing', $post);
        ?>

        <div class="cablecast-channel-single__now-playing">
            <?php
            /**
             * Filter the now playing shortcode attributes.
             *
             * @param array $atts Shortcode attributes.
             * @param int   $channel_id The channel post ID.
             */
            $now_playing_atts = apply_filters('cablecast_channel_now_playing_atts', [
                'channel'        => get_the_ID(),
                'exclude_filler' => 'true',
            ], get_the_ID());

            $atts_string = '';
            foreach ($now_playing_atts as $key => $value) {
                $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
            echo do_shortcode('[cablecast_now_playing' . $atts_string . ']');
            ?>
        </div>

        <?php
        /**
         * Hook: cablecast_after_channel_now_playing
         *
         * @hooked None by default
         */
        do_action('cablecast_after_channel_now_playing', $post);

        // Display description if available
        $content = get_the_content();
        if (!empty($content)) :
            /**
             * Hook: cablecast_before_channel_description
             *
             * @hooked None by default
             */
            do_action('cablecast_before_channel_description', $post);
            ?>
            <div class="cablecast-channel-single__description">
                <?php the_content(); ?>
            </div>
            <?php
            /**
             * Hook: cablecast_after_channel_description
             *
             * @hooked None by default
             */
            do_action('cablecast_after_channel_description', $post);
        endif;

        /**
         * Hook: cablecast_before_channel_schedule
         *
         * @hooked None by default
         */
        do_action('cablecast_before_channel_schedule', $post);
        ?>

        <div class="cablecast-channel-single__schedule">
            <h2 class="cablecast-channel-single__schedule-heading"><?php esc_html_e('Schedule', 'cablecast'); ?></h2>
            <?php
            /**
             * Filter the schedule calendar shortcode attributes.
             *
             * @param array $atts Shortcode attributes.
             * @param int   $channel_id The channel post ID.
             */
            $calendar_atts = apply_filters('cablecast_schedule_calendar_atts', [
                'channel' => get_the_ID(),
                'view'    => 'timeGridWeek',
            ], get_the_ID());

            $atts_string = '';
            foreach ($calendar_atts as $key => $value) {
                $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
            echo do_shortcode('[cablecast_schedule_calendar' . $atts_string . ']');
            ?>
        </div>

        <?php
        /**
         * Hook: cablecast_after_channel_schedule
         *
         * @hooked None by default
         */
        do_action('cablecast_after_channel_schedule', $post);
        ?>

    </article>

    <?php
    /**
     * Hook: cablecast_after_single_channel
     *
     * @hooked None by default
     */
    do_action('cablecast_after_single_channel');

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
