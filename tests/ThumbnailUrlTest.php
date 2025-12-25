<?php
/**
 * Tests for thumbnail URL generation functionality.
 */

class ThumbnailUrlTest extends WP_UnitTestCase {

    private $show_post_id;

    /**
     * Set up test fixtures.
     */
    public function setUp(): void {
        parent::setUp();

        // Ensure the 'show' post type is registered
        if (!post_type_exists('show')) {
            register_post_type('show', [
                'public' => true,
                'supports' => ['title', 'editor', 'thumbnail'],
            ]);
        }

        // Create a test show post
        $this->show_post_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
        ]);

        // Set cablecast metadata
        update_post_meta($this->show_post_id, 'cablecast_show_id', 12345);
        update_post_meta($this->show_post_id, 'cablecast_thumbnail_url', 'https://example.cablecast.net/cablecastapi/dynamicthumbnails/99999');

        // Set up options for remote mode
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
            'thumbnail_mode' => 'remote'
        ]);
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        wp_delete_post($this->show_post_id, true);
        delete_option('cablecast_options');
        parent::tearDown();
    }

    /**
     * Test thumbnail URL generation with saved URL.
     */
    public function test_thumbnail_url_uses_saved_url() {
        $url = cablecast_show_thumbnail_url($this->show_post_id, 'medium');

        $this->assertStringContainsString('https://example.cablecast.net/cablecastapi/dynamicthumbnails/99999', $url);
        $this->assertStringContainsString('?d=500x500', $url);
    }

    /**
     * Test thumbnail URL generation for different sizes.
     */
    public function test_thumbnail_url_size_mappings() {
        $sizes = [
            'thumbnail' => '100x100',
            'medium' => '500x500',
            'large' => '1000x1000',
            'post-thumbnail' => '640x360',
        ];

        foreach ($sizes as $size => $expected_dimension) {
            $url = cablecast_show_thumbnail_url($this->show_post_id, $size);
            $this->assertStringContainsString("?d={$expected_dimension}", $url, "Failed for size: {$size}");
        }
    }

    /**
     * Test thumbnail URL with array size.
     */
    public function test_thumbnail_url_with_array_size() {
        $url = cablecast_show_thumbnail_url($this->show_post_id, [800, 600]);

        $this->assertStringContainsString('?d=800x600', $url);
    }

    /**
     * Test thumbnail URL returns empty for non-show posts.
     */
    public function test_thumbnail_url_empty_for_non_show() {
        // Create a regular post
        $post_id = wp_insert_post([
            'post_title' => 'Regular Post',
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        $url = cablecast_show_thumbnail_url($post_id, 'medium');

        $this->assertEmpty($url);

        wp_delete_post($post_id, true);
    }

    /**
     * Test thumbnail URL fallback when no saved URL exists.
     */
    public function test_thumbnail_url_fallback_without_saved_url() {
        // Remove the saved thumbnail URL
        delete_post_meta($this->show_post_id, 'cablecast_thumbnail_url');

        $url = cablecast_show_thumbnail_url($this->show_post_id, 'medium');

        // Should fall back to constructing URL from server settings
        $this->assertStringContainsString('https://example.cablecast.net', $url);
        $this->assertStringContainsString('12345', $url); // show_id
    }

    /**
     * Test thumbnail srcset generation.
     */
    public function test_thumbnail_srcset_generation() {
        $srcset = cablecast_show_thumbnail_srcset($this->show_post_id);

        $this->assertStringContainsString('320w', $srcset);
        $this->assertStringContainsString('480w', $srcset);
        $this->assertStringContainsString('640w', $srcset);
        $this->assertStringContainsString('960w', $srcset);
        $this->assertStringContainsString('1280w', $srcset);
    }

    /**
     * Test srcset returns empty when no thumbnail URL exists.
     */
    public function test_srcset_empty_without_thumbnail_url() {
        delete_post_meta($this->show_post_id, 'cablecast_thumbnail_url');

        $srcset = cablecast_show_thumbnail_srcset($this->show_post_id);

        $this->assertEmpty($srcset);
    }

    /**
     * Test full size returns URL without dimension parameter.
     */
    public function test_thumbnail_url_full_size_no_dimension() {
        $url = cablecast_show_thumbnail_url($this->show_post_id, 'full');

        $this->assertStringContainsString('https://example.cablecast.net/cablecastapi/dynamicthumbnails/99999', $url);
        $this->assertStringNotContainsString('?d=', $url);
    }
}
