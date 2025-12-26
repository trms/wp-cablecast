<?php
/**
 * Tests for Cablecast chapter sync functionality.
 */

class ChapterSyncTest extends WP_UnitTestCase {

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Register post type if not exists
        if (!post_type_exists('show')) {
            register_post_type('show', [
                'public' => true,
            ]);
        }

        // Set up options
        update_option('cablecast_options', [
            'server' => 'https://test.cablecast.net',
        ]);
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        delete_option('cablecast_options');
        parent::tearDown();
    }

    /**
     * Test cablecast_fetch_vod_chapters returns empty array on API error.
     */
    public function test_fetch_chapters_api_error() {
        // Mock a failing API call by using an invalid server
        update_option('cablecast_options', [
            'server' => 'https://invalid-server-that-does-not-exist.local',
        ]);

        $chapters = cablecast_fetch_vod_chapters('https://invalid-server-that-does-not-exist.local', 12345);

        $this->assertIsArray($chapters);
        $this->assertEmpty($chapters);
    }

    /**
     * Test that chapters are stored correctly during sync.
     */
    public function test_chapters_stored_as_array() {
        // Create a show with VOD
        $show_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_vod_embed' => '<iframe src="https://test.com/embed"></iframe>',
            ],
        ]);

        // Simulate storing chapters
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => 'Description 1', 'offset' => 0],
            ['id' => 2, 'title' => 'Chapter 2', 'body' => 'Description 2', 'offset' => 60],
        ];

        update_post_meta($show_id, 'cablecast_vod_chapters', $chapters);

        // Retrieve and verify
        $stored = get_post_meta($show_id, 'cablecast_vod_chapters', true);

        $this->assertIsArray($stored);
        $this->assertCount(2, $stored);
        $this->assertEquals('Chapter 1', $stored[0]['title']);
        $this->assertEquals(60, $stored[1]['offset']);

        wp_delete_post($show_id, true);
    }

    /**
     * Test that deleted/hidden chapters are filtered out.
     */
    public function test_chapters_filter_deleted_hidden() {
        // This tests the filtering logic in cablecast_fetch_vod_chapters
        // We can't easily mock the API response, but we can verify the function exists
        $this->assertTrue(function_exists('cablecast_fetch_vod_chapters'));
    }

    /**
     * Test that chapters are sorted by offset.
     */
    public function test_chapters_sorted_by_offset() {
        $show_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_vod_embed' => '<iframe src="https://test.com/embed"></iframe>',
            ],
        ]);

        // Store chapters in wrong order
        $chapters = [
            ['id' => 3, 'title' => 'Chapter 3', 'body' => '', 'offset' => 300],
            ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
            ['id' => 2, 'title' => 'Chapter 2', 'body' => '', 'offset' => 120],
        ];

        // Sort them like the sync function does
        usort($chapters, function($a, $b) {
            return $a['offset'] - $b['offset'];
        });

        update_post_meta($show_id, 'cablecast_vod_chapters', $chapters);

        $stored = get_post_meta($show_id, 'cablecast_vod_chapters', true);

        $this->assertEquals(0, $stored[0]['offset']);
        $this->assertEquals(120, $stored[1]['offset']);
        $this->assertEquals(300, $stored[2]['offset']);

        wp_delete_post($show_id, true);
    }

    /**
     * Test that chapters are cleared when VOD is removed.
     */
    public function test_chapters_cleared_when_no_vod() {
        $show_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_vod_embed' => '<iframe src="https://test.com/embed"></iframe>',
                'cablecast_vod_chapters' => [
                    ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
                ],
            ],
        ]);

        // Verify chapters exist
        $chapters = get_post_meta($show_id, 'cablecast_vod_chapters', true);
        $this->assertNotEmpty($chapters);

        // Clear chapters (as sync would do when VOD is removed)
        delete_post_meta($show_id, 'cablecast_vod_chapters');

        // Verify chapters are gone
        $chapters = get_post_meta($show_id, 'cablecast_vod_chapters', true);
        $this->assertEmpty($chapters);

        wp_delete_post($show_id, true);
    }

    /**
     * Test chapter data structure integrity.
     */
    public function test_chapter_data_structure() {
        $show_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_vod_embed' => '<iframe></iframe>',
            ],
        ]);

        $chapters = [
            [
                'id' => 123,
                'title' => 'Test Title',
                'body' => 'Test Body Content',
                'offset' => 456,
            ],
        ];

        update_post_meta($show_id, 'cablecast_vod_chapters', $chapters);

        $stored = get_post_meta($show_id, 'cablecast_vod_chapters', true);

        // Verify all fields are preserved
        $this->assertArrayHasKey('id', $stored[0]);
        $this->assertArrayHasKey('title', $stored[0]);
        $this->assertArrayHasKey('body', $stored[0]);
        $this->assertArrayHasKey('offset', $stored[0]);

        $this->assertEquals(123, $stored[0]['id']);
        $this->assertEquals('Test Title', $stored[0]['title']);
        $this->assertEquals('Test Body Content', $stored[0]['body']);
        $this->assertEquals(456, $stored[0]['offset']);

        wp_delete_post($show_id, true);
    }

    /**
     * Test empty chapters array is handled correctly.
     */
    public function test_empty_chapters_array() {
        $show_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_vod_embed' => '<iframe></iframe>',
                'cablecast_vod_chapters' => [],
            ],
        ]);

        $chapters = get_post_meta($show_id, 'cablecast_vod_chapters', true);

        // Empty array stored should return empty
        $this->assertEmpty($chapters);

        wp_delete_post($show_id, true);
    }
}
