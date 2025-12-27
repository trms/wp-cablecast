<?php
/**
 * Tests for sync concurrency lock behavior.
 */

class SyncLockTest extends WP_UnitTestCase {

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
        delete_transient('cablecast_sync_lock');
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        delete_option('cablecast_options');
        delete_transient('cablecast_sync_lock');
        parent::tearDown();
    }

    /**
     * Test that sync lock prevents concurrent execution.
     */
    public function test_sync_lock_prevents_concurrent_execution() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
        ]);

        // Set up lock manually
        set_transient('cablecast_sync_lock', true, 300);

        // Mock the HTTP request to track if it was called
        $request_made = false;
        add_filter('pre_http_request', function() use (&$request_made) {
            $request_made = true;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['shows' => []]),
            ];
        });

        // Try to run sync - should be skipped due to lock
        cablecast_sync_data();

        // The HTTP request should not have been made because sync was skipped
        $this->assertFalse($request_made, 'Sync should be skipped when lock is present');
    }

    /**
     * Test that sync lock is acquired during sync.
     */
    public function test_sync_acquires_lock() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
        ]);

        // Track lock state during sync
        $lock_was_set = false;

        add_filter('pre_http_request', function() use (&$lock_was_set) {
            // Check if lock exists during the request
            $lock_was_set = (bool) get_transient('cablecast_sync_lock');
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'shows' => [],
                    'showFields' => [],
                    'fieldDefinitions' => [],
                ]),
            ];
        });

        // Run sync
        cablecast_sync_data();

        $this->assertTrue($lock_was_set, 'Lock should be set during sync');
    }

    /**
     * Test that sync lock is released after sync completes.
     */
    public function test_sync_releases_lock_after_completion() {
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
        ]);

        // Mock all HTTP requests
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'shows' => [],
                    'channels' => [],
                    'liveStreams' => [],
                    'categories' => [],
                    'producers' => [],
                    'projects' => [],
                    'showFields' => [],
                    'fieldDefinitions' => [],
                    'scheduleItems' => [],
                    'savedShowSearch' => ['results' => []],
                ]),
            ];
        });

        // Run sync
        cablecast_sync_data();

        // Lock should be released
        $lock = get_transient('cablecast_sync_lock');
        $this->assertFalse($lock, 'Lock should be released after sync completes');
    }
}
