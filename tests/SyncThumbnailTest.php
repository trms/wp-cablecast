<?php
/**
 * Tests for sync thumbnail behavior based on mode setting.
 */

class SyncThumbnailTest extends WP_UnitTestCase {

    /**
     * Clean up before each test.
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

        delete_option('cablecast_options');
        delete_option('cablecast_sync_total_result_count');
        delete_option('cablecast_sync_index');
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        // Clean up any created posts
        $posts = get_posts([
            'post_type' => 'show',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        foreach ($posts as $post_id) {
            wp_delete_post($post_id, true);
        }

        delete_option('cablecast_options');
        delete_option('cablecast_sync_total_result_count');
        delete_option('cablecast_sync_index');
        parent::tearDown();
    }

    /**
     * Create a mock shows payload for testing.
     */
    private function create_mock_payload($show_data = []) {
        $default = [
            'id' => 12345,
            'title' => 'Test Show',
            'cgTitle' => 'Test Show CG',
            'eventDate' => '2024-01-01 12:00:00',
            'comments' => 'Test description',
            'lastModified' => '2024-01-01T12:00:00',
            'reels' => [],
            'thumbnailImage' => (object)[
                'url' => 'https://example.cablecast.net/cablecastapi/dynamicthumbnails/99999'
            ],
            'custom1' => '',
            'custom2' => '',
            'custom3' => '',
            'custom4' => '',
            'custom5' => '',
            'custom6' => '',
            'custom7' => '',
            'custom8' => '',
        ];

        $show = (object) array_merge($default, $show_data);

        return (object)[
            'shows' => [$show],
            'vods' => [],
            'reels' => [],
            'webFiles' => [],
        ];
    }

    /**
     * Test that remote mode saves thumbnail URL to meta.
     */
    public function test_remote_mode_saves_thumbnail_url_meta() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
            'thumbnail_mode' => 'remote',
        ]);
        update_option('cablecast_sync_total_result_count', 1);

        $payload = $this->create_mock_payload();

        // Run sync
        cablecast_sync_shows($payload, [], [], [], [], []);

        // Find the created post
        $posts = get_posts([
            'post_type' => 'show',
            'meta_key' => 'cablecast_show_id',
            'meta_value' => 12345,
        ]);

        $this->assertCount(1, $posts);
        $post = $posts[0];

        // Should have thumbnail URL in meta
        $thumbnail_url = get_post_meta($post->ID, 'cablecast_thumbnail_url', true);
        $this->assertEquals(
            'https://example.cablecast.net/cablecastapi/dynamicthumbnails/99999',
            $thumbnail_url
        );

        // Should NOT have a featured image attachment
        $this->assertFalse(has_post_thumbnail($post->ID));
    }

    /**
     * Test that show data is synced correctly regardless of thumbnail mode.
     */
    public function test_sync_creates_show_post_with_metadata() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
            'thumbnail_mode' => 'remote',
        ]);
        update_option('cablecast_sync_total_result_count', 1);

        $payload = $this->create_mock_payload([
            'id' => 67890,
            'title' => 'My Test Show',
            'cgTitle' => 'My Test Show CG Title',
            'comments' => 'This is the show description',
            'eventDate' => '2024-06-15 14:30:00',
        ]);

        cablecast_sync_shows($payload, [], [], [], [], []);

        $posts = get_posts([
            'post_type' => 'show',
            'meta_key' => 'cablecast_show_id',
            'meta_value' => 67890,
        ]);

        $this->assertCount(1, $posts);
        $post = $posts[0];

        // Check post data
        $this->assertEquals('My Test Show CG Title', $post->post_title);
        $this->assertEquals('This is the show description', $post->post_content);

        // Check metadata
        $this->assertEquals(67890, get_post_meta($post->ID, 'cablecast_show_id', true));
        $this->assertEquals('My Test Show', get_post_meta($post->ID, 'cablecast_show_title', true));
        $this->assertEquals('My Test Show CG Title', get_post_meta($post->ID, 'cablecast_show_cg_title', true));
    }

    /**
     * Test that sync updates existing posts.
     */
    public function test_sync_updates_existing_post() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
            'thumbnail_mode' => 'remote',
        ]);

        // Create an existing post
        $existing_id = wp_insert_post([
            'post_title' => 'Old Title',
            'post_type' => 'show',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_show_id' => 11111,
            ],
        ]);

        update_option('cablecast_sync_total_result_count', 1);

        $payload = $this->create_mock_payload([
            'id' => 11111,
            'cgTitle' => 'Updated Title',
        ]);

        cablecast_sync_shows($payload, [], [], [], [], []);

        // Refresh post data
        $post = get_post($existing_id);

        $this->assertEquals('Updated Title', $post->post_title);
    }

    /**
     * Test sync handles shows without thumbnail gracefully.
     */
    public function test_sync_handles_missing_thumbnail() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
            'thumbnail_mode' => 'remote',
        ]);
        update_option('cablecast_sync_total_result_count', 1);

        // Create payload without thumbnailImage
        $payload = (object)[
            'shows' => [(object)[
                'id' => 55555,
                'title' => 'No Thumbnail Show',
                'cgTitle' => 'No Thumbnail Show',
                'eventDate' => '2024-01-01 12:00:00',
                'comments' => '',
                'lastModified' => '2024-01-01T12:00:00',
                'reels' => [],
                'custom1' => '',
                'custom2' => '',
                'custom3' => '',
                'custom4' => '',
                'custom5' => '',
                'custom6' => '',
                'custom7' => '',
                'custom8' => '',
            ]],
            'vods' => [],
            'reels' => [],
            'webFiles' => [],
        ];

        // Should not throw an error
        cablecast_sync_shows($payload, [], [], [], [], []);

        $posts = get_posts([
            'post_type' => 'show',
            'meta_key' => 'cablecast_show_id',
            'meta_value' => 55555,
        ]);

        $this->assertCount(1, $posts);

        // Should not have thumbnail URL
        $thumbnail_url = get_post_meta($posts[0]->ID, 'cablecast_thumbnail_url', true);
        $this->assertEmpty($thumbnail_url);
    }
}
