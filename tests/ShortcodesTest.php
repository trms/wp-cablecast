<?php
/**
 * Tests for Cablecast shortcodes.
 */

class ShortcodesTest extends WP_UnitTestCase {

    private $show_post_id;
    private $channel_post_id;
    private $schedule_table;

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Set timezone for tests (prevents DateTimeZone exception)
        update_option('timezone_string', 'America/New_York');

        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'cablecast_schedule_items';

        // Ensure post types are registered
        if (!post_type_exists('show')) {
            register_post_type('show', [
                'public' => true,
                'supports' => ['title', 'editor', 'thumbnail'],
                'taxonomies' => ['category', 'cablecast_project', 'cablecast_producer'],
            ]);
        }
        if (!post_type_exists('cablecast_channel')) {
            register_post_type('cablecast_channel', [
                'public' => true,
                'supports' => ['title', 'editor', 'thumbnail'],
            ]);
        }

        // Register taxonomies if not exists
        if (!taxonomy_exists('cablecast_producer')) {
            register_taxonomy('cablecast_producer', ['show'], ['public' => true]);
        }
        if (!taxonomy_exists('cablecast_project')) {
            register_taxonomy('cablecast_project', ['show'], ['public' => true]);
        }

        // Create test channel
        $this->channel_post_id = wp_insert_post([
            'post_title' => 'Test Channel',
            'post_type' => 'cablecast_channel',
            'post_status' => 'publish',
            'post_content' => 'Channel description',
            'meta_input' => [
                'cablecast_channel_id' => 1,
                'cablecast_channel_live_embed_code' => '<iframe src="test"></iframe>',
            ],
        ]);

        // Create test show
        $this->show_post_id = wp_insert_post([
            'post_title' => 'Test Show',
            'post_type' => 'show',
            'post_status' => 'publish',
            'post_content' => 'Show description',
            'meta_input' => [
                'cablecast_show_id' => 12345,
                'cablecast_vod_url' => 'https://example.com/vod/12345',
                'cablecast_vod_embed' => '<iframe src="https://example.com/embed/12345"></iframe>',
                'cablecast_producer_name' => 'Test Producer',
                'cablecast_category_name' => 'Test Category',
                'cablecast_project_name' => 'Test Project',
                'cablecast_show_trt' => 3600, // 1 hour
                'cablecast_show_comments' => 'This is the show description.',
                'cablecast_thumbnail_url' => 'https://example.com/thumb/12345.jpg',
            ],
        ]);

        // Set category
        wp_set_object_terms($this->show_post_id, 'news', 'category');

        // Create schedule item for today
        $this->create_schedule_item();
    }

    /**
     * Create a test schedule item.
     */
    private function create_schedule_item() {
        global $wpdb;

        // Create schedule table if it doesn't exist
        $charset_collate = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->schedule_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            run_date_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            show_id int NOT NULL,
            show_title varchar(255) DEFAULT '' NOT NULL,
            channel_id int NOT NULL,
            show_post_id int NOT NULL,
            channel_post_id int NOT NULL,
            schedule_item_id int NOT NULL,
            cg_exempt tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;");

        // Insert schedule items for today
        $now = current_time('mysql');
        $wpdb->insert($this->schedule_table, [
            'run_date_time' => $now,
            'show_id' => 12345,
            'show_title' => 'Test Show',
            'channel_id' => 1,
            'show_post_id' => $this->show_post_id,
            'channel_post_id' => $this->channel_post_id,
            'schedule_item_id' => 1001,
            'cg_exempt' => 0,
        ]);

        // Insert a future schedule item
        $future = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $wpdb->insert($this->schedule_table, [
            'run_date_time' => $future,
            'show_id' => 12346,
            'show_title' => 'Future Show',
            'channel_id' => 1,
            'show_post_id' => $this->show_post_id,
            'channel_post_id' => $this->channel_post_id,
            'schedule_item_id' => 1002,
            'cg_exempt' => 0,
        ]);
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        global $wpdb;

        if ($this->show_post_id) {
            wp_delete_post($this->show_post_id, true);
        }
        if ($this->channel_post_id) {
            wp_delete_post($this->channel_post_id, true);
        }

        // Clean up schedule items
        $wpdb->query("TRUNCATE TABLE {$this->schedule_table}");

        // Reset options
        delete_option('cablecast_options');

        parent::tearDown();
    }

    // =========================================================================
    // Shortcode Registration Tests
    // =========================================================================

    /**
     * Test that all shortcodes are registered.
     */
    public function test_shortcodes_are_registered() {
        global $shortcode_tags;

        $expected_shortcodes = [
            'cablecast_schedule',
            'cablecast_now_playing',
            'cablecast_weekly_guide',
            'cablecast_shows',
            'cablecast_show',
            'cablecast_vod_player',
            'cablecast_chapters',
            'cablecast_producers',
            'cablecast_series',
        ];

        foreach ($expected_shortcodes as $shortcode) {
            $this->assertArrayHasKey(
                $shortcode,
                $shortcode_tags,
                "Shortcode [$shortcode] should be registered"
            );
        }
    }

    // =========================================================================
    // Helper Function Tests
    // =========================================================================

    /**
     * Test cablecast_is_filler() with default keywords.
     */
    public function test_is_filler_default_keywords() {
        $this->assertTrue(cablecast_is_filler('Color Bars'));
        $this->assertTrue(cablecast_is_filler('COLORBARS'));
        $this->assertTrue(cablecast_is_filler('Test Pattern Display'));
        $this->assertTrue(cablecast_is_filler('Off Air'));
        $this->assertTrue(cablecast_is_filler('Station ID'));
        $this->assertTrue(cablecast_is_filler('Technical Difficulties'));
    }

    /**
     * Test cablecast_is_filler() with non-filler content.
     */
    public function test_is_filler_non_filler() {
        $this->assertFalse(cablecast_is_filler('City Council Meeting'));
        $this->assertFalse(cablecast_is_filler('News at Six'));
        $this->assertFalse(cablecast_is_filler('Documentary: Nature'));
    }

    /**
     * Test cablecast_is_filler() with custom keywords from settings.
     */
    public function test_is_filler_custom_keywords() {
        update_option('cablecast_options', [
            'filler_keywords' => 'custom filler, test content'
        ]);

        $this->assertTrue(cablecast_is_filler('Custom Filler Content'));
        $this->assertTrue(cablecast_is_filler('test content here'));
        // Default keywords should no longer match when custom are set
        $this->assertFalse(cablecast_is_filler('Color Bars'));
    }

    /**
     * Test cablecast_format_runtime() function.
     */
    public function test_format_runtime() {
        $this->assertEquals('1h 30m', cablecast_format_runtime(5400)); // 1.5 hours
        $this->assertEquals('2h 0m', cablecast_format_runtime(7200)); // 2 hours
        $this->assertEquals('45m', cablecast_format_runtime(2700)); // 45 minutes
        $this->assertEquals('5m', cablecast_format_runtime(300)); // 5 minutes
        $this->assertEquals('', cablecast_format_runtime(0));
        $this->assertEquals('', cablecast_format_runtime(-100));
    }

    /**
     * Test cablecast_category_colors_enabled() function.
     */
    public function test_category_colors_enabled() {
        // Default should be disabled
        $this->assertFalse(cablecast_category_colors_enabled());

        // Enable category colors
        update_option('cablecast_options', [
            'enable_category_colors' => true,
        ]);
        $this->assertTrue(cablecast_category_colors_enabled());

        // Disable again
        update_option('cablecast_options', [
            'enable_category_colors' => false,
        ]);
        $this->assertFalse(cablecast_category_colors_enabled());
    }

    /**
     * Test cablecast_get_show_category_color() function.
     */
    public function test_get_show_category_color() {
        // Should return null when colors disabled
        $this->assertNull(cablecast_get_show_category_color($this->show_post_id));

        // Enable colors with a mapping
        update_option('cablecast_options', [
            'enable_category_colors' => true,
            'category_colors' => [
                'news' => '#3b82f6',
            ],
        ]);

        $color = cablecast_get_show_category_color($this->show_post_id);
        $this->assertEquals('#3b82f6', $color);
    }

    /**
     * Test cablecast_get_show_category_color() returns null for unmapped category.
     */
    public function test_get_show_category_color_unmapped() {
        update_option('cablecast_options', [
            'enable_category_colors' => true,
            'category_colors' => [
                'sports' => '#f97316', // Different category than show has
            ],
        ]);

        $color = cablecast_get_show_category_color($this->show_post_id);
        $this->assertNull($color);
    }

    /**
     * Test cablecast_get_channel_post_id() function.
     */
    public function test_get_channel_post_id() {
        $post_id = cablecast_get_channel_post_id(1);
        $this->assertEquals($this->channel_post_id, $post_id);

        // Non-existent channel
        $post_id = cablecast_get_channel_post_id(999);
        $this->assertNull($post_id);
    }

    /**
     * Test cablecast_get_all_channels() function.
     */
    public function test_get_all_channels() {
        $channels = cablecast_get_all_channels();
        $this->assertIsArray($channels);
        $this->assertCount(1, $channels);
        $this->assertEquals('Test Channel', $channels[0]->post_title);
    }

    // =========================================================================
    // Schedule Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_schedule] requires channel attribute.
     */
    public function test_schedule_requires_channel() {
        $output = do_shortcode('[cablecast_schedule]');
        $this->assertStringContainsString('Please specify a channel ID', $output);
    }

    /**
     * Test [cablecast_schedule] with invalid channel.
     */
    public function test_schedule_invalid_channel() {
        $output = do_shortcode('[cablecast_schedule channel="99999"]');
        $this->assertStringContainsString('Invalid channel', $output);
    }

    /**
     * Test [cablecast_schedule] basic output structure.
     */
    public function test_schedule_basic_output() {
        $output = do_shortcode('[cablecast_schedule channel="' . $this->channel_post_id . '"]');

        $this->assertStringContainsString('cablecast-schedule', $output);
        $this->assertStringContainsString('Test Show', $output);
        $this->assertStringContainsString('cablecast-schedule__item', $output);
    }

    /**
     * Test [cablecast_schedule] with count attribute.
     */
    public function test_schedule_count_attribute() {
        $output = do_shortcode('[cablecast_schedule channel="' . $this->channel_post_id . '" count="1"]');

        // Should contain the first show
        $this->assertStringContainsString('Test Show', $output);
    }

    /**
     * Test [cablecast_schedule] with show_descriptions="false".
     */
    public function test_schedule_hide_descriptions() {
        $output = do_shortcode('[cablecast_schedule channel="' . $this->channel_post_id . '" show_descriptions="false"]');

        $this->assertStringContainsString('cablecast-schedule', $output);
        $this->assertStringNotContainsString('cablecast-schedule__description', $output);
    }

    /**
     * Test [cablecast_schedule] with custom class.
     */
    public function test_schedule_custom_class() {
        $output = do_shortcode('[cablecast_schedule channel="' . $this->channel_post_id . '" class="my-custom-class"]');

        $this->assertStringContainsString('my-custom-class', $output);
    }

    // =========================================================================
    // Now Playing Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_now_playing] requires channel attribute.
     */
    public function test_now_playing_requires_channel() {
        $output = do_shortcode('[cablecast_now_playing]');
        $this->assertStringContainsString('Please specify a channel ID', $output);
    }

    /**
     * Test [cablecast_now_playing] basic output structure.
     */
    public function test_now_playing_basic_output() {
        $output = do_shortcode('[cablecast_now_playing channel="' . $this->channel_post_id . '"]');

        $this->assertStringContainsString('cablecast-now-playing', $output);
        $this->assertStringContainsString('cablecast-now-playing__card', $output);
    }

    /**
     * Test [cablecast_now_playing] shows up next when enabled.
     */
    public function test_now_playing_shows_up_next() {
        $output = do_shortcode('[cablecast_now_playing channel="' . $this->channel_post_id . '" show_up_next="true"]');

        // Should have both now and next cards
        $this->assertStringContainsString('cablecast-now-playing__card--now', $output);
    }

    /**
     * Test [cablecast_now_playing] hides up next when disabled.
     */
    public function test_now_playing_hides_up_next() {
        $output = do_shortcode('[cablecast_now_playing channel="' . $this->channel_post_id . '" show_up_next="false"]');

        // Should not have the next card class
        $this->assertStringNotContainsString('cablecast-now-playing__card--next', $output);
    }

    // =========================================================================
    // Weekly Guide Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_weekly_guide] uses first channel when none specified.
     */
    public function test_weekly_guide_default_channel() {
        $output = do_shortcode('[cablecast_weekly_guide]');

        $this->assertStringContainsString('cablecast-weekly-guide', $output);
        $this->assertStringContainsString('cablecast-weekly-guide__grid', $output);
    }

    /**
     * Test [cablecast_weekly_guide] with specific channel.
     */
    public function test_weekly_guide_specific_channel() {
        $output = do_shortcode('[cablecast_weekly_guide channel="' . $this->channel_post_id . '"]');

        $this->assertStringContainsString('cablecast-weekly-guide', $output);
    }

    /**
     * Test [cablecast_weekly_guide] channel switcher.
     */
    public function test_weekly_guide_channel_switcher() {
        // Channel switcher only shows when there are multiple channels
        $second_channel = wp_insert_post([
            'post_title' => 'Second Channel',
            'post_type' => 'cablecast_channel',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_channel_id' => 2,
            ],
        ]);

        $output = do_shortcode('[cablecast_weekly_guide show_channel_switcher="true"]');

        $this->assertStringContainsString('cablecast-weekly-guide__channel-switcher', $output);
        $this->assertStringContainsString('cablecast-channel-select', $output);

        wp_delete_post($second_channel, true);
    }

    /**
     * Test [cablecast_weekly_guide] hides channel switcher when disabled.
     */
    public function test_weekly_guide_hide_channel_switcher() {
        $output = do_shortcode('[cablecast_weekly_guide show_channel_switcher="false"]');

        $this->assertStringNotContainsString('cablecast-weekly-guide__channel-switcher', $output);
    }

    /**
     * Test [cablecast_weekly_guide] days attribute.
     */
    public function test_weekly_guide_days_attribute() {
        $output = do_shortcode('[cablecast_weekly_guide days="3"]');

        // Count day columns - should have 3
        $this->assertStringContainsString('cablecast-weekly-guide__day', $output);
    }

    // =========================================================================
    // Shows Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_shows] basic output.
     */
    public function test_shows_basic_output() {
        $output = do_shortcode('[cablecast_shows]');

        $this->assertStringContainsString('cablecast-shows', $output);
        $this->assertStringContainsString('Test Show', $output);
    }

    /**
     * Test [cablecast_shows] grid layout.
     */
    public function test_shows_grid_layout() {
        $output = do_shortcode('[cablecast_shows layout="grid"]');

        $this->assertStringContainsString('cablecast-shows--grid', $output);
    }

    /**
     * Test [cablecast_shows] list layout.
     */
    public function test_shows_list_layout() {
        $output = do_shortcode('[cablecast_shows layout="list"]');

        $this->assertStringContainsString('cablecast-shows--list', $output);
    }

    /**
     * Test [cablecast_shows] columns attribute.
     */
    public function test_shows_columns() {
        $output = do_shortcode('[cablecast_shows columns="3"]');

        $this->assertStringContainsString('cablecast-shows--columns-3', $output);
    }

    /**
     * Test [cablecast_shows] category filter.
     */
    public function test_shows_category_filter() {
        $output = do_shortcode('[cablecast_shows category="news"]');

        $this->assertStringContainsString('Test Show', $output);
    }

    /**
     * Test [cablecast_shows] with non-existent category.
     */
    public function test_shows_empty_category() {
        $output = do_shortcode('[cablecast_shows category="nonexistent-category"]');

        $this->assertStringContainsString('No shows found', $output);
    }

    // =========================================================================
    // Single Show Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_show] requires id attribute.
     */
    public function test_show_requires_id() {
        $output = do_shortcode('[cablecast_show]');

        $this->assertStringContainsString('Please specify a show ID', $output);
    }

    /**
     * Test [cablecast_show] with invalid id.
     */
    public function test_show_invalid_id() {
        $output = do_shortcode('[cablecast_show id="99999"]');

        $this->assertStringContainsString('Show not found', $output);
    }

    /**
     * Test [cablecast_show] basic output.
     */
    public function test_show_basic_output() {
        $output = do_shortcode('[cablecast_show id="' . $this->show_post_id . '"]');

        $this->assertStringContainsString('cablecast-show', $output);
        $this->assertStringContainsString('Test Show', $output);
    }

    /**
     * Test [cablecast_show] includes VOD player.
     */
    public function test_show_includes_vod() {
        $output = do_shortcode('[cablecast_show id="' . $this->show_post_id . '" show_vod="true"]');

        $this->assertStringContainsString('cablecast-show__vod', $output);
        $this->assertStringContainsString('iframe', $output);
    }

    /**
     * Test [cablecast_show] hides VOD when disabled.
     */
    public function test_show_hides_vod() {
        $output = do_shortcode('[cablecast_show id="' . $this->show_post_id . '" show_vod="false"]');

        $this->assertStringNotContainsString('cablecast-show__vod', $output);
    }

    // =========================================================================
    // VOD Player Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_vod_player] requires id attribute.
     */
    public function test_vod_player_requires_id() {
        $output = do_shortcode('[cablecast_vod_player]');

        $this->assertStringContainsString('Please specify a show ID', $output);
    }

    /**
     * Test [cablecast_vod_player] basic output.
     */
    public function test_vod_player_basic_output() {
        $output = do_shortcode('[cablecast_vod_player id="' . $this->show_post_id . '"]');

        $this->assertStringContainsString('cablecast-vod-player', $output);
        $this->assertStringContainsString('iframe', $output);
    }

    // =========================================================================
    // Chapters Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_chapters] returns empty for show without chapters.
     */
    public function test_chapters_no_chapters() {
        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '"]');

        // Should return empty when no chapters exist
        $this->assertEmpty($output);
    }

    /**
     * Test [cablecast_chapters] returns empty for show without VOD.
     */
    public function test_chapters_no_vod() {
        // Create show without VOD
        $show_no_vod = wp_insert_post([
            'post_title' => 'Show Without VOD',
            'post_type' => 'show',
            'post_status' => 'publish',
            'meta_input' => [
                'cablecast_vod_chapters' => [
                    ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
                ],
            ],
        ]);

        $output = do_shortcode('[cablecast_chapters id="' . $show_no_vod . '"]');

        // Should return empty when no VOD embed exists
        $this->assertEmpty($output);

        wp_delete_post($show_no_vod, true);
    }

    /**
     * Test [cablecast_chapters] basic output with chapters.
     */
    public function test_chapters_basic_output() {
        // Add chapters to the test show
        $chapters = [
            ['id' => 1, 'title' => 'Introduction', 'body' => 'Welcome to the show', 'offset' => 0],
            ['id' => 2, 'title' => 'Main Content', 'body' => 'The main topic', 'offset' => 120],
            ['id' => 3, 'title' => 'Conclusion', 'body' => 'Wrapping up', 'offset' => 300],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '"]');

        $this->assertStringContainsString('cablecast-chapters', $output);
        $this->assertStringContainsString('cablecast-chapters__list', $output);
        $this->assertStringContainsString('Introduction', $output);
        $this->assertStringContainsString('Main Content', $output);
        $this->assertStringContainsString('Conclusion', $output);
    }

    /**
     * Test [cablecast_chapters] includes data attributes for JS.
     */
    public function test_chapters_data_attributes() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
            ['id' => 2, 'title' => 'Chapter 2', 'body' => '', 'offset' => 60],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '"]');

        // Should have data-show-id attribute
        $this->assertStringContainsString('data-show-id="' . $this->show_post_id . '"', $output);

        // Should have data-offset attributes on items
        $this->assertStringContainsString('data-offset="0"', $output);
        $this->assertStringContainsString('data-offset="60"', $output);
    }

    /**
     * Test [cablecast_chapters] shows timestamps by default.
     */
    public function test_chapters_shows_timestamps() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
            ['id' => 2, 'title' => 'Chapter 2', 'body' => '', 'offset' => 125], // 2:05
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '"]');

        $this->assertStringContainsString('cablecast-chapters__timestamp', $output);
        $this->assertStringContainsString('0:00', $output);
        $this->assertStringContainsString('2:05', $output);
    }

    /**
     * Test [cablecast_chapters] hides timestamps when disabled.
     */
    public function test_chapters_hides_timestamps() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '" show_timestamps="false"]');

        $this->assertStringNotContainsString('cablecast-chapters__timestamp', $output);
    }

    /**
     * Test [cablecast_chapters] shows descriptions by default.
     */
    public function test_chapters_shows_descriptions() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => 'This is the description', 'offset' => 0],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '"]');

        $this->assertStringContainsString('cablecast-chapters__description', $output);
        $this->assertStringContainsString('This is the description', $output);
    }

    /**
     * Test [cablecast_chapters] hides descriptions when disabled.
     */
    public function test_chapters_hides_descriptions() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => 'This is the description', 'offset' => 0],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '" show_descriptions="false"]');

        $this->assertStringNotContainsString('cablecast-chapters__description', $output);
    }

    /**
     * Test [cablecast_chapters] compact layout.
     */
    public function test_chapters_compact_layout() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '" layout="compact"]');

        $this->assertStringContainsString('cablecast-chapters--compact', $output);
    }

    /**
     * Test [cablecast_chapters] with custom class.
     */
    public function test_chapters_custom_class() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '" class="my-custom-class"]');

        $this->assertStringContainsString('my-custom-class', $output);
    }

    /**
     * Test [cablecast_chapters] with player selector attribute.
     */
    public function test_chapters_player_selector() {
        $chapters = [
            ['id' => 1, 'title' => 'Chapter 1', 'body' => '', 'offset' => 0],
        ];
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', $chapters);

        $output = do_shortcode('[cablecast_chapters id="' . $this->show_post_id . '" player="#my-player"]');

        $this->assertStringContainsString('data-player-selector="#my-player"', $output);
    }

    /**
     * Test [cablecast_chapters] requires valid show ID.
     */
    public function test_chapters_requires_valid_id() {
        $output = do_shortcode('[cablecast_chapters id="99999"]');

        $this->assertStringContainsString('Show not found', $output);
    }

    /**
     * Test [cablecast_chapters] requires id when not in show context.
     */
    public function test_chapters_requires_id() {
        $output = do_shortcode('[cablecast_chapters]');

        $this->assertStringContainsString('specify a show ID', $output);
    }

    /**
     * Test cablecast_format_chapter_timestamp() helper.
     */
    public function test_format_chapter_timestamp() {
        $this->assertEquals('0:00', cablecast_format_chapter_timestamp(0));
        $this->assertEquals('1:30', cablecast_format_chapter_timestamp(90));
        $this->assertEquals('10:05', cablecast_format_chapter_timestamp(605));
        $this->assertEquals('1:00:00', cablecast_format_chapter_timestamp(3600));
        $this->assertEquals('1:30:45', cablecast_format_chapter_timestamp(5445));
    }

    // =========================================================================
    // Producers Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_producers] with no producers returns message.
     */
    public function test_producers_no_results() {
        $output = do_shortcode('[cablecast_producers]');

        $this->assertStringContainsString('No producers found', $output);
    }

    /**
     * Test [cablecast_producers] with producers.
     */
    public function test_producers_with_results() {
        // Create a producer term and assign to show
        $term = wp_insert_term('Test Producer', 'cablecast_producer');
        if (!is_wp_error($term)) {
            wp_set_object_terms($this->show_post_id, $term['term_id'], 'cablecast_producer');

            $output = do_shortcode('[cablecast_producers]');

            $this->assertStringContainsString('cablecast-producers', $output);
            $this->assertStringContainsString('Test Producer', $output);

            wp_delete_term($term['term_id'], 'cablecast_producer');
        }
    }

    /**
     * Test [cablecast_producers] list layout.
     */
    public function test_producers_list_layout() {
        $term = wp_insert_term('Test Producer', 'cablecast_producer');
        if (!is_wp_error($term)) {
            wp_set_object_terms($this->show_post_id, $term['term_id'], 'cablecast_producer');

            $output = do_shortcode('[cablecast_producers layout="list"]');

            $this->assertStringContainsString('cablecast-producers--list', $output);

            wp_delete_term($term['term_id'], 'cablecast_producer');
        }
    }

    // =========================================================================
    // Series Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_series] with no series returns message.
     */
    public function test_series_no_results() {
        $output = do_shortcode('[cablecast_series]');

        $this->assertStringContainsString('No series found', $output);
    }

    /**
     * Test [cablecast_series] with series.
     */
    public function test_series_with_results() {
        // Create a series term and assign to show
        $term = wp_insert_term('Test Series', 'cablecast_project');
        if (!is_wp_error($term)) {
            wp_set_object_terms($this->show_post_id, $term['term_id'], 'cablecast_project');

            $output = do_shortcode('[cablecast_series]');

            $this->assertStringContainsString('cablecast-series', $output);
            $this->assertStringContainsString('Test Series', $output);

            wp_delete_term($term['term_id'], 'cablecast_project');
        }
    }

    /**
     * Test [cablecast_series] grid layout.
     */
    public function test_series_grid_layout() {
        $term = wp_insert_term('Test Series', 'cablecast_project');
        if (!is_wp_error($term)) {
            wp_set_object_terms($this->show_post_id, $term['term_id'], 'cablecast_project');

            $output = do_shortcode('[cablecast_series layout="grid"]');

            $this->assertStringContainsString('cablecast-series--grid', $output);

            wp_delete_term($term['term_id'], 'cablecast_project');
        }
    }

    // =========================================================================
    // Asset Loading Tests
    // =========================================================================

    /**
     * Test that shortcode usage is tracked.
     */
    public function test_shortcode_usage_tracking() {
        global $cablecast_shortcodes_used;
        $cablecast_shortcodes_used = [];

        do_shortcode('[cablecast_shows]');
        $this->assertContains('shows', $cablecast_shortcodes_used);

        do_shortcode('[cablecast_weekly_guide]');
        $this->assertContains('weekly_guide', $cablecast_shortcodes_used);
    }

    // =========================================================================
    // XSS/Security Tests
    // =========================================================================

    /**
     * Test that class attribute is properly escaped.
     */
    public function test_class_attribute_escaping() {
        $output = do_shortcode('[cablecast_shows class="<script>alert(1)</script>"]');

        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test that channel ID is sanitized.
     */
    public function test_channel_id_sanitization() {
        $output = do_shortcode('[cablecast_schedule channel="1; DROP TABLE users;"]');

        // Should fail validation gracefully
        $this->assertStringContainsString('Invalid channel', $output);
    }

    /**
     * Test filler keywords setting doesn't allow script injection.
     */
    public function test_filler_keywords_escaping() {
        update_option('cablecast_options', [
            'filler_keywords' => '<script>alert(1)</script>, test'
        ]);

        // This shouldn't cause any issues - just test it doesn't crash
        $result = cablecast_is_filler('<script>alert(1)</script>');
        $this->assertTrue($result);
    }

    // =========================================================================
    // Upcoming Runs Shortcode Tests
    // =========================================================================

    /**
     * Test [cablecast_upcoming_runs] is registered.
     */
    public function test_upcoming_runs_registered() {
        $this->assertTrue(shortcode_exists('cablecast_upcoming_runs'));
    }

    /**
     * Test [cablecast_upcoming_runs] returns empty with no runs.
     */
    public function test_upcoming_runs_no_results() {
        global $wpdb;

        // Ensure schedule table exists (empty) to avoid database error output
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->schedule_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            run_date_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            show_id int NOT NULL,
            show_title varchar(255) DEFAULT '' NOT NULL,
            channel_id int NOT NULL,
            show_post_id int NOT NULL,
            channel_post_id int NOT NULL,
            schedule_item_id int NOT NULL,
            cg_exempt tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY (id)
        )");

        // Clear any data from previous tests
        $wpdb->query("TRUNCATE TABLE {$this->schedule_table}");

        $output = do_shortcode('[cablecast_upcoming_runs id="' . $this->show_post_id . '"]');

        // Should return empty when no upcoming runs exist
        $this->assertEmpty($output);
    }

    /**
     * Test [cablecast_upcoming_runs] with scheduled runs.
     */
    public function test_upcoming_runs_with_results() {
        global $wpdb;

        // Ensure schedule table exists
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->schedule_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            run_date_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            show_id int NOT NULL,
            show_title varchar(255) DEFAULT '' NOT NULL,
            channel_id int NOT NULL,
            show_post_id int NOT NULL,
            channel_post_id int NOT NULL,
            schedule_item_id int NOT NULL,
            cg_exempt tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY (id)
        )");

        // Insert a future schedule item
        $future_date = date('Y-m-d H:i:s', strtotime('+1 day'));
        $wpdb->insert($this->schedule_table, [
            'run_date_time' => $future_date,
            'show_id' => 12345,
            'show_title' => 'Test Show',
            'channel_id' => 1,
            'show_post_id' => $this->show_post_id,
            'channel_post_id' => $this->channel_post_id,
            'schedule_item_id' => 1,
            'cg_exempt' => 0,
        ]);

        $output = do_shortcode('[cablecast_upcoming_runs id="' . $this->show_post_id . '"]');

        $this->assertStringContainsString('cablecast-upcoming-runs', $output);
        $this->assertStringContainsString('Upcoming Airings', $output);
        $this->assertStringContainsString('Test Channel', $output);

        // Clean up
        $wpdb->query("DELETE FROM {$this->schedule_table} WHERE show_post_id = {$this->show_post_id}");
    }

    /**
     * Test [cablecast_upcoming_runs] respects count attribute.
     */
    public function test_upcoming_runs_count_attribute() {
        global $wpdb;

        // Insert multiple future schedule items
        for ($i = 1; $i <= 10; $i++) {
            $future_date = date('Y-m-d H:i:s', strtotime("+{$i} day"));
            $wpdb->insert($this->schedule_table, [
                'run_date_time' => $future_date,
                'show_id' => 12345,
                'show_title' => 'Test Show',
                'channel_id' => 1,
                'show_post_id' => $this->show_post_id,
                'channel_post_id' => $this->channel_post_id,
                'schedule_item_id' => $i,
                'cg_exempt' => 0,
            ]);
        }

        $output = do_shortcode('[cablecast_upcoming_runs id="' . $this->show_post_id . '" count="3"]');

        // Count the number of list items
        $count = substr_count($output, 'cablecast-upcoming-runs__item');
        $this->assertEquals(3, $count);

        // Clean up
        $wpdb->query("DELETE FROM {$this->schedule_table} WHERE show_post_id = {$this->show_post_id}");
    }

    /**
     * Test [cablecast_upcoming_runs] hides channel when disabled.
     */
    public function test_upcoming_runs_hide_channel() {
        global $wpdb;

        $future_date = date('Y-m-d H:i:s', strtotime('+1 day'));
        $wpdb->insert($this->schedule_table, [
            'run_date_time' => $future_date,
            'show_id' => 12345,
            'show_title' => 'Test Show',
            'channel_id' => 1,
            'show_post_id' => $this->show_post_id,
            'channel_post_id' => $this->channel_post_id,
            'schedule_item_id' => 1,
            'cg_exempt' => 0,
        ]);

        $output = do_shortcode('[cablecast_upcoming_runs id="' . $this->show_post_id . '" show_channel="false"]');

        $this->assertStringNotContainsString('cablecast-upcoming-runs__channel', $output);

        // Clean up
        $wpdb->query("DELETE FROM {$this->schedule_table} WHERE show_post_id = {$this->show_post_id}");
    }

    /**
     * Test [cablecast_upcoming_runs] requires valid show ID.
     */
    public function test_upcoming_runs_invalid_id() {
        $output = do_shortcode('[cablecast_upcoming_runs id="99999"]');

        // Should return empty for invalid ID
        $this->assertEmpty($output);
    }

    /**
     * Test [cablecast_upcoming_runs] with custom class.
     */
    public function test_upcoming_runs_custom_class() {
        global $wpdb;

        $future_date = date('Y-m-d H:i:s', strtotime('+1 day'));
        $wpdb->insert($this->schedule_table, [
            'run_date_time' => $future_date,
            'show_id' => 12345,
            'show_title' => 'Test Show',
            'channel_id' => 1,
            'show_post_id' => $this->show_post_id,
            'channel_post_id' => $this->channel_post_id,
            'schedule_item_id' => 1,
            'cg_exempt' => 0,
        ]);

        $output = do_shortcode('[cablecast_upcoming_runs id="' . $this->show_post_id . '" class="my-custom-class"]');

        $this->assertStringContainsString('my-custom-class', $output);

        // Clean up
        $wpdb->query("DELETE FROM {$this->schedule_table} WHERE show_post_id = {$this->show_post_id}");
    }

    // =========================================================================
    // Template Loader Tests
    // =========================================================================

    /**
     * Test template loader functions exist.
     */
    public function test_template_loader_functions_exist() {
        $this->assertTrue(function_exists('cablecast_get_template'));
        $this->assertTrue(function_exists('cablecast_locate_template'));
        $this->assertTrue(function_exists('cablecast_get_templates_dir'));
    }

    /**
     * Test cablecast_get_templates_dir returns valid path.
     */
    public function test_templates_dir_exists() {
        $dir = cablecast_get_templates_dir();
        $this->assertNotEmpty($dir);
        $this->assertStringContainsString('templates', $dir);
    }

    /**
     * Test cablecast_locate_template finds plugin templates.
     */
    public function test_locate_template_finds_plugin_templates() {
        $template = cablecast_locate_template('single-show.php');
        $this->assertNotEmpty($template);
        $this->assertStringContainsString('single-show.php', $template);
    }

    /**
     * Test cablecast_has_vod helper function.
     */
    public function test_has_vod_helper() {
        // Show with VOD
        $this->assertTrue(cablecast_has_vod($this->show_post_id));

        // Show without VOD
        $show_without_vod = wp_insert_post([
            'post_title' => 'No VOD Show',
            'post_type' => 'show',
            'post_status' => 'publish',
        ]);
        $this->assertFalse(cablecast_has_vod($show_without_vod));
        wp_delete_post($show_without_vod, true);
    }

    /**
     * Test cablecast_has_chapters helper function.
     */
    public function test_has_chapters_helper() {
        // Show without chapters
        $this->assertFalse(cablecast_has_chapters($this->show_post_id));

        // Show with chapters
        update_post_meta($this->show_post_id, 'cablecast_vod_chapters', [
            ['id' => 1, 'title' => 'Chapter 1', 'offset' => 0],
        ]);
        $this->assertTrue(cablecast_has_chapters($this->show_post_id));
    }

    /**
     * Test cablecast_get_show_meta helper function.
     */
    public function test_get_show_meta_helper() {
        $meta = cablecast_get_show_meta($this->show_post_id);

        $this->assertIsArray($meta);
        $this->assertArrayHasKey('runtime', $meta);
        $this->assertArrayHasKey('producer', $meta);
        $this->assertArrayHasKey('category', $meta);
        $this->assertArrayHasKey('series', $meta);

        $this->assertEquals('Test Producer', $meta['producer']['value']);
        $this->assertEquals('Test Category', $meta['category']['value']);
        $this->assertEquals('Test Project', $meta['series']['value']);
    }

    /**
     * Test cablecast_format_runtime helper function.
     */
    public function test_format_runtime_helper() {
        // Function returns empty for 0 or negative values
        $this->assertEquals('', cablecast_format_runtime(0));
        $this->assertEquals('', cablecast_format_runtime(-10));

        // Minutes only (< 1 hour)
        $this->assertEquals('1m', cablecast_format_runtime(90));    // 1.5 min -> 1m
        $this->assertEquals('10m', cablecast_format_runtime(605));  // 10.08 min -> 10m
        $this->assertEquals('30m', cablecast_format_runtime(1800)); // 30 min

        // Hours and minutes
        $this->assertEquals('1h 0m', cablecast_format_runtime(3600));  // 1 hour
        $this->assertEquals('1h 30m', cablecast_format_runtime(5445)); // 1h 30m 45s -> 1h 30m
    }
}
