<?php
/**
 * Tests for API error handling behavior.
 */

class ApiErrorTest extends WP_UnitTestCase {

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
        delete_option('cablecast_sync_since');
        delete_option('cablecast_sync_index');
        delete_option('cablecast_sync_total_result_count');
        delete_transient('cablecast_sync_lock');
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        delete_option('cablecast_options');
        delete_option('cablecast_sync_since');
        delete_option('cablecast_sync_index');
        delete_option('cablecast_sync_total_result_count');
        delete_transient('cablecast_sync_lock');
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    /**
     * Test that API timeout returns empty shows.
     */
    public function test_api_timeout_returns_empty_payload() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
        ]);

        // Mock HTTP request to return WP_Error (timeout)
        add_filter('pre_http_request', function() {
            return new WP_Error('http_request_failed', 'Connection timed out');
        });

        $result = cablecast_get_shows_payload();

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('shows', $result);
        $this->assertIsArray($result->shows);
        $this->assertEmpty($result->shows);
    }

    /**
     * Test that API 500 error returns empty shows.
     */
    public function test_api_500_error_returns_empty_payload() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
        ]);

        // Mock HTTP request to return 500 error
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 500],
                'body' => 'Internal Server Error',
            ];
        });

        $result = cablecast_get_shows_payload();

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('shows', $result);
        $this->assertEmpty($result->shows);
    }

    /**
     * Test that API 404 error returns empty shows.
     */
    public function test_api_404_error_returns_empty_payload() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
        ]);

        // Mock HTTP request to return 404 error
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 404],
                'body' => 'Not Found',
            ];
        });

        $result = cablecast_get_shows_payload();

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('shows', $result);
        $this->assertEmpty($result->shows);
    }

    /**
     * Test that invalid JSON returns empty payload.
     */
    public function test_invalid_json_returns_empty_payload() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
        ]);

        // Mock HTTP request to return invalid JSON
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => 'not valid json {{{',
            ];
        });

        $result = cablecast_get_shows_payload();

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('shows', $result);
        $this->assertEmpty($result->shows);
    }

    /**
     * Test that get_resources handles timeout gracefully.
     */
    public function test_get_resources_handles_timeout() {
        // Mock HTTP request to return WP_Error
        add_filter('pre_http_request', function() {
            return new WP_Error('http_request_failed', 'Connection timed out');
        });

        $result = cablecast_get_resources('https://example.cablecast.net/api/test', 'items');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that get_resources handles 500 error.
     */
    public function test_get_resources_handles_server_error() {
        // Mock HTTP request to return 500
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 500],
                'body' => 'Internal Server Error',
            ];
        });

        $result = cablecast_get_resources('https://example.cablecast.net/api/test', 'items');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test URL validation rejects invalid URLs.
     */
    public function test_invalid_thumbnail_url_not_saved() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
            'thumbnail_mode' => 'remote',
        ]);
        update_option('cablecast_sync_total_result_count', 1);

        // Create payload with invalid thumbnail URL
        $payload = (object)[
            'shows' => [(object)[
                'id' => 99999,
                'title' => 'Test Show',
                'cgTitle' => 'Test Show',
                'eventDate' => '2024-01-01 12:00:00',
                'comments' => '',
                'lastModified' => '2024-01-01T12:00:00',
                'reels' => [],
                'thumbnailImage' => (object)[
                    'url' => 'javascript:alert(1)' // Invalid/malicious URL
                ],
                'custom1' => '',
                'custom2' => '',
                'custom3' => '',
                'custom4' => '',
                'custom5' => '',
                'custom6' => '',
                'custom7' => '',
                'custom8' => '',
                'location' => null,
            ]],
            'vods' => [],
            'reels' => [],
            'webFiles' => [],
        ];

        cablecast_sync_shows($payload, [], [], [], [], []);

        $posts = get_posts([
            'post_type' => 'show',
            'meta_key' => 'cablecast_show_id',
            'meta_value' => 99999,
        ]);

        $this->assertCount(1, $posts);

        // Should not have saved the invalid URL
        $thumbnail_url = get_post_meta($posts[0]->ID, 'cablecast_thumbnail_url', true);
        $this->assertEmpty($thumbnail_url, 'Invalid URL should not be saved');
    }
}
