<?php
/**
 * Tests for thumbnail cleanup functionality.
 */

class ThumbnailCleanupTest extends WP_UnitTestCase {

    private $show_post_ids = [];
    private $attachment_ids = [];

    /**
     * Set up test fixtures.
     */
    public function setUp(): void {
        parent::setUp();

        // Create test show posts with featured images
        for ($i = 0; $i < 5; $i++) {
            $show_id = wp_insert_post([
                'post_title' => "Test Show {$i}",
                'post_type' => 'show',
                'post_status' => 'publish',
            ]);
            $this->show_post_ids[] = $show_id;

            // Create a mock attachment
            $attachment_id = wp_insert_attachment([
                'post_title' => "Thumbnail {$i}",
                'post_type' => 'attachment',
                'post_mime_type' => 'image/jpeg',
            ]);
            $this->attachment_ids[] = $attachment_id;

            // Set as featured image
            set_post_thumbnail($show_id, $attachment_id);
        }
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        foreach ($this->show_post_ids as $id) {
            wp_delete_post($id, true);
        }
        foreach ($this->attachment_ids as $id) {
            wp_delete_attachment($id, true);
        }
        delete_option('cablecast_options');
        parent::tearDown();
    }

    /**
     * Test cleanup does nothing when not in remote mode.
     */
    public function test_cleanup_skips_when_local_mode() {
        update_option('cablecast_options', [
            'thumbnail_mode' => 'local',
            'delete_local_thumbnails' => true,
        ]);

        // Run cleanup
        cablecast_cleanup_local_thumbnails();

        // All thumbnails should still exist
        foreach ($this->show_post_ids as $show_id) {
            $this->assertTrue(has_post_thumbnail($show_id), "Show {$show_id} should still have thumbnail");
        }
    }

    /**
     * Test cleanup does nothing when delete flag is not set.
     */
    public function test_cleanup_skips_without_delete_flag() {
        update_option('cablecast_options', [
            'thumbnail_mode' => 'remote',
            'delete_local_thumbnails' => false,
        ]);

        // Run cleanup
        cablecast_cleanup_local_thumbnails();

        // All thumbnails should still exist
        foreach ($this->show_post_ids as $show_id) {
            $this->assertTrue(has_post_thumbnail($show_id), "Show {$show_id} should still have thumbnail");
        }
    }

    /**
     * Test cleanup processes thumbnails in batches.
     */
    public function test_cleanup_processes_batch() {
        update_option('cablecast_options', [
            'thumbnail_mode' => 'remote',
            'delete_local_thumbnails' => true,
        ]);

        // Run cleanup once (should process up to 25 items)
        cablecast_cleanup_local_thumbnails();

        // Count remaining thumbnails
        $remaining = 0;
        foreach ($this->show_post_ids as $show_id) {
            if (has_post_thumbnail($show_id)) {
                $remaining++;
            }
        }

        // All 5 should be deleted (batch size is 25, we only have 5)
        $this->assertEquals(0, $remaining, "All thumbnails should be deleted");
    }

    /**
     * Test cleanup clears the delete flag when complete.
     */
    public function test_cleanup_clears_flag_when_complete() {
        update_option('cablecast_options', [
            'thumbnail_mode' => 'remote',
            'delete_local_thumbnails' => true,
        ]);

        // Run cleanup
        cablecast_cleanup_local_thumbnails();

        // Flag should be cleared
        $options = get_option('cablecast_options');
        $this->assertFalse($options['delete_local_thumbnails']);
    }

    /**
     * Test cleanup deletes the attachment file.
     */
    public function test_cleanup_deletes_attachments() {
        update_option('cablecast_options', [
            'thumbnail_mode' => 'remote',
            'delete_local_thumbnails' => true,
        ]);

        $attachment_id = $this->attachment_ids[0];

        // Verify attachment exists before cleanup
        $this->assertNotNull(get_post($attachment_id));

        // Run cleanup
        cablecast_cleanup_local_thumbnails();

        // Attachment should be deleted
        $this->assertNull(get_post($attachment_id));
    }

    /**
     * Test cleanup removes _thumbnail_id meta.
     */
    public function test_cleanup_removes_thumbnail_meta() {
        update_option('cablecast_options', [
            'thumbnail_mode' => 'remote',
            'delete_local_thumbnails' => true,
        ]);

        $show_id = $this->show_post_ids[0];

        // Verify meta exists before cleanup
        $this->assertNotEmpty(get_post_meta($show_id, '_thumbnail_id', true));

        // Run cleanup
        cablecast_cleanup_local_thumbnails();

        // Meta should be removed
        $this->assertEmpty(get_post_meta($show_id, '_thumbnail_id', true));
    }
}
