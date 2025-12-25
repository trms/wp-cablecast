<?php
/**
 * Tests for thumbnail settings functionality.
 */

class ThumbnailSettingsTest extends WP_UnitTestCase {

    /**
     * Clean up options before each test.
     */
    public function setUp(): void {
        parent::setUp();
        delete_option('cablecast_options');
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        delete_option('cablecast_options');
        parent::tearDown();
    }

    /**
     * Test that fresh installs default to remote thumbnail mode.
     */
    public function test_fresh_install_defaults_to_remote_mode() {
        // Ensure no existing options (fresh install)
        $this->assertFalse(get_option('cablecast_options'));

        // Run install hook
        cablecast_install();

        $options = get_option('cablecast_options');
        $this->assertIsArray($options);
        $this->assertEquals('remote', $options['thumbnail_mode']);
    }

    /**
     * Test that upgrades from older versions default to local mode.
     */
    public function test_upgrade_defaults_to_local_mode() {
        // Simulate existing install without thumbnail_mode setting
        update_option('cablecast_options', ['server' => 'https://example.cablecast.net']);

        // Run install hook (simulating upgrade)
        cablecast_install();

        $options = get_option('cablecast_options');
        $this->assertEquals('local', $options['thumbnail_mode']);
        // Ensure existing settings are preserved
        $this->assertEquals('https://example.cablecast.net', $options['server']);
    }

    /**
     * Test that existing thumbnail_mode setting is preserved on reinstall.
     */
    public function test_existing_thumbnail_mode_preserved() {
        // Set up existing options with remote mode
        update_option('cablecast_options', [
            'server' => 'https://example.cablecast.net',
            'thumbnail_mode' => 'remote'
        ]);

        // Run install hook
        cablecast_install();

        $options = get_option('cablecast_options');
        $this->assertEquals('remote', $options['thumbnail_mode']);
    }
}
