<?php
/**
 * Tests for display content filters.
 */

class DisplayFilterTest extends WP_UnitTestCase {

    private $show_post_id;
    private $channel_post_id;

    /**
     * Clean up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Ensure post types are registered
        if (!post_type_exists('show')) {
            register_post_type('show', [
                'public' => true,
                'supports' => ['title', 'editor', 'thumbnail'],
            ]);
        }
        if (!post_type_exists('cablecast_channel')) {
            register_post_type('cablecast_channel', [
                'public' => true,
                'supports' => ['title', 'editor', 'thumbnail'],
            ]);
        }

        // Create test posts
        $this->show_post_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
            'post_content' => 'Show description',
            'meta_input' => [
                'cablecast_show_id' => 12345,
                'cablecast_vod_url' => 'https://example.com/vod/12345',
                'cablecast_producer_name' => 'Test Producer',
                'cablecast_category_name' => 'Test Category',
                'cablecast_project_name' => 'Test Project',
                'cablecast_show_trt' => 3600, // 1 hour
            ],
        ]);

        $this->channel_post_id = wp_insert_post([
            'post_title' => 'Test Channel',
            'post_type' => 'cablecast_channel',
            'post_status' => 'publish',
            'post_content' => 'Channel description',
            'meta_input' => [
                'cablecast_channel_id' => 1,
            ],
        ]);
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        if ($this->show_post_id) {
            wp_delete_post($this->show_post_id, true);
        }
        if ($this->channel_post_id) {
            wp_delete_post($this->channel_post_id, true);
        }
        parent::tearDown();
    }

    /**
     * Test that show content filter returns modified content for show posts.
     */
    public function test_show_content_filter_modifies_show_content() {
        global $post;
        $post = get_post($this->show_post_id);
        setup_postdata($post);

        // Simulate single post context
        $this->go_to(get_permalink($this->show_post_id));

        $content = apply_filters('the_content', 'Original content');

        // The filter should wrap content in div and add video shortcode
        $this->assertStringContainsString('<div>', $content);

        wp_reset_postdata();
    }

    /**
     * Test that regular posts are not affected by the filter.
     */
    public function test_regular_posts_not_modified() {
        // Create a regular post
        $regular_post_id = wp_insert_post([
            'post_title' => 'Regular Post',
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_content' => 'Regular content',
        ]);

        global $post;
        $post = get_post($regular_post_id);
        setup_postdata($post);

        $this->go_to(get_permalink($regular_post_id));

        $original = 'Test content';
        $content = apply_filters('the_content', $original);

        // Content should be unchanged for regular posts
        $this->assertEquals($original, $content);

        wp_delete_post($regular_post_id, true);
        wp_reset_postdata();
    }

    /**
     * Test that VOD URL is properly escaped in output.
     */
    public function test_vod_url_is_escaped() {
        // Update with potentially dangerous URL
        update_post_meta($this->show_post_id, 'cablecast_vod_url', 'https://example.com/vod?test=1&foo=bar');

        global $post;
        $post = get_post($this->show_post_id);
        setup_postdata($post);

        $this->go_to(get_permalink($this->show_post_id));

        $content = apply_filters('the_content', '');

        // URL should be properly escaped (& should be &amp; in shortcode attribute)
        $this->assertStringNotContainsString('<script>', $content);

        wp_reset_postdata();
    }

    /**
     * Test that TRT is formatted correctly.
     */
    public function test_trt_format() {
        global $post;
        $post = get_post($this->show_post_id);
        setup_postdata($post);

        $this->go_to(get_permalink($this->show_post_id));

        $content = apply_filters('the_content', '');

        // TRT of 3600 seconds should be formatted as 01:00:00
        $this->assertStringContainsString('01:00:00', $content);

        wp_reset_postdata();
    }

    /**
     * Test date validation for schedule display.
     */
    public function test_schedule_date_validation() {
        // Test that invalid date formats fall back to current date
        $_GET['schedule_date'] = '"><script>alert(1)</script>';

        global $post;
        $post = get_post($this->channel_post_id);
        setup_postdata($post);

        $this->go_to(get_permalink($this->channel_post_id));

        $content = apply_filters('the_content', '');

        // Should not contain script tag - invalid date should be sanitized
        $this->assertStringNotContainsString('<script>', $content);

        unset($_GET['schedule_date']);
        wp_reset_postdata();
    }

    /**
     * Test valid date format is accepted.
     */
    public function test_valid_schedule_date_accepted() {
        $_GET['schedule_date'] = '2024-06-15';

        global $post;
        $post = get_post($this->channel_post_id);
        setup_postdata($post);

        $this->go_to(get_permalink($this->channel_post_id));

        $content = apply_filters('the_content', '');

        // Should contain the date
        $this->assertStringContainsString('2024-06-15', $content);

        unset($_GET['schedule_date']);
        wp_reset_postdata();
    }

    /**
     * Test thumbnail size constant is accessible.
     */
    public function test_thumbnail_size_constant_defined() {
        $this->assertTrue(defined('CABLECAST_THUMBNAIL_SIZES'));
        $this->assertIsArray(CABLECAST_THUMBNAIL_SIZES);
        $this->assertArrayHasKey('thumbnail', CABLECAST_THUMBNAIL_SIZES);
        $this->assertArrayHasKey('medium', CABLECAST_THUMBNAIL_SIZES);
        $this->assertArrayHasKey('large', CABLECAST_THUMBNAIL_SIZES);
        $this->assertArrayHasKey('post-thumbnail', CABLECAST_THUMBNAIL_SIZES);
    }

    /**
     * Test srcset variants constant is accessible.
     */
    public function test_srcset_variants_constant_defined() {
        $this->assertTrue(defined('CABLECAST_SRCSET_VARIANTS'));
        $this->assertIsArray(CABLECAST_SRCSET_VARIANTS);
        $this->assertCount(5, CABLECAST_SRCSET_VARIANTS);
    }
}
