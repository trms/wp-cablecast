<?php
/**
 * Template: Channels Archive
 *
 * This template displays the channels archive page with a list of all channels.
 *
 * To override this template, copy it to: yourtheme/cablecast/archive-channel.php
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
 * Hook: cablecast_before_archive_channel
 *
 * @hooked None by default
 */
do_action('cablecast_before_archive_channel');
?>

<div class="cablecast-archive cablecast-channels-archive">

    <?php
    /**
     * Hook: cablecast_before_archive_channel_header
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_channel_header');
    ?>

    <header class="cablecast-archive__header">
        <h1 class="cablecast-archive__title"><?php esc_html_e('Channels', 'cablecast'); ?></h1>
    </header>

    <?php
    /**
     * Hook: cablecast_after_archive_channel_header
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_channel_header');

    /**
     * Hook: cablecast_before_archive_channel_content
     *
     * @hooked None by default
     */
    do_action('cablecast_before_archive_channel_content');
    ?>

    <div class="cablecast-archive__content">
        <div class="cablecast-channels-grid">
            <?php
            $channels = get_posts([
                'post_type'      => 'cablecast_channel',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);

            if (!empty($channels)) :
                foreach ($channels as $channel) :
                    /**
                     * Hook: cablecast_before_channel_card
                     *
                     * @hooked None by default
                     */
                    do_action('cablecast_before_channel_card', $channel);
                    ?>
                    <article class="cablecast-channel-card">
                        <?php if (has_post_thumbnail($channel)) : ?>
                            <a href="<?php echo esc_url(get_permalink($channel)); ?>" class="cablecast-channel-card__thumbnail-link">
                                <div class="cablecast-channel-card__thumbnail">
                                    <?php echo get_the_post_thumbnail($channel, 'medium', ['class' => 'cablecast-channel-card__thumbnail-img']); ?>
                                </div>
                            </a>
                        <?php endif; ?>

                        <div class="cablecast-channel-card__content">
                            <h2 class="cablecast-channel-card__title">
                                <a href="<?php echo esc_url(get_permalink($channel)); ?>"><?php echo esc_html($channel->post_title); ?></a>
                            </h2>

                            <?php if (!empty($channel->post_content)) : ?>
                                <div class="cablecast-channel-card__excerpt">
                                    <?php echo wp_trim_words($channel->post_content, 20, '...'); ?>
                                </div>
                            <?php endif; ?>

                            <a href="<?php echo esc_url(get_permalink($channel)); ?>" class="cablecast-channel-card__link">
                                <?php esc_html_e('View Schedule', 'cablecast'); ?>
                            </a>
                        </div>
                    </article>
                    <?php
                    /**
                     * Hook: cablecast_after_channel_card
                     *
                     * @hooked None by default
                     */
                    do_action('cablecast_after_channel_card', $channel);
                endforeach;
            else :
                ?>
                <p class="cablecast-no-channels"><?php esc_html_e('No channels found.', 'cablecast'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php
    /**
     * Hook: cablecast_after_archive_channel_content
     *
     * @hooked None by default
     */
    do_action('cablecast_after_archive_channel_content');
    ?>

</div>

<?php
/**
 * Hook: cablecast_after_archive_channel
 *
 * @hooked None by default
 */
do_action('cablecast_after_archive_channel');

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
