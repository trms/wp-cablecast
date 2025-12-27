# Cablecast Templates

This directory contains the default templates used by the Cablecast plugin. These templates can be overridden by your theme to customize the appearance of shows, channels, and taxonomy archives.

## Template Override System

The Cablecast plugin follows WordPress best practices for template overriding, similar to WooCommerce.

### How to Override Templates

1. **Create a cablecast folder** in your theme:
   ```
   yourtheme/cablecast/
   ```

2. **Copy the template** you want to customize from:
   ```
   wp-content/plugins/wp-cablecast/templates/
   ```

3. **Paste it into your theme's cablecast folder**:
   ```
   yourtheme/cablecast/single-show.php
   ```

4. **Edit your copy** - it will be used instead of the plugin's default.

### Search Order

Templates are loaded in this order:
1. `yourtheme/cablecast/{template-name}.php`
2. `yourtheme/{template-name}.php` (backwards compatibility)
3. `wp-cablecast/templates/{template-name}.php` (default)

## Available Templates

| Template | Purpose | Used On |
|----------|---------|---------|
| `single-show.php` | Individual show page | `/show/{show-name}/` |
| `single-channel.php` | Channel page with schedule | `/channel/{channel-name}/` |
| `archive-show.php` | Shows listing page | `/shows/` |
| `archive-channel.php` | Channels listing page | `/channels/` |
| `archive-producer.php` | Shows by producer | `/producers/{producer-name}/` |
| `archive-series.php` | Shows in a series | `/series/{series-name}/` |
| `content-show.php` | Show card partial | Used by other templates |

## Available Hooks

### Show Single Page

```php
// Before show content
do_action('cablecast_before_single_show');
do_action('cablecast_before_show_title', $post);
do_action('cablecast_after_show_title', $post);
do_action('cablecast_before_show_player', $post);
do_action('cablecast_after_show_player', $post);
do_action('cablecast_before_show_chapters', $post);
do_action('cablecast_after_show_chapters', $post);
do_action('cablecast_before_show_description', $post);
do_action('cablecast_after_show_description', $post);
do_action('cablecast_before_show_meta', $post);
do_action('cablecast_after_show_meta', $post);
do_action('cablecast_before_show_upcoming_runs', $post);
do_action('cablecast_after_show_upcoming_runs', $post);
do_action('cablecast_after_single_show');
```

### Channel Single Page

```php
do_action('cablecast_before_single_channel');
do_action('cablecast_before_channel_title', $post);
do_action('cablecast_after_channel_title', $post);
do_action('cablecast_before_channel_player', $post);
do_action('cablecast_after_channel_player', $post);
do_action('cablecast_before_channel_now_playing', $post);
do_action('cablecast_after_channel_now_playing', $post);
do_action('cablecast_before_channel_description', $post);
do_action('cablecast_after_channel_description', $post);
do_action('cablecast_before_channel_schedule', $post);
do_action('cablecast_after_channel_schedule', $post);
do_action('cablecast_after_single_channel');
```

### Taxonomy Archives

```php
// Producer archive
do_action('cablecast_before_archive_producer', $term);
do_action('cablecast_before_archive_producer_header', $term);
do_action('cablecast_after_archive_producer_header', $term);
do_action('cablecast_before_archive_producer_shows', $term);
do_action('cablecast_after_archive_producer_shows', $term);
do_action('cablecast_after_archive_producer', $term);

// Series archive
do_action('cablecast_before_archive_series', $term);
do_action('cablecast_before_archive_series_header', $term);
do_action('cablecast_after_archive_series_header', $term);
do_action('cablecast_before_archive_series_shows', $term);
do_action('cablecast_after_archive_series_shows', $term);
do_action('cablecast_after_archive_series', $term);
```

### Show Card

```php
do_action('cablecast_before_show_card', $post);
do_action('cablecast_after_show_card', $post);
```

### Channel Card

```php
do_action('cablecast_before_channel_card', $channel);
do_action('cablecast_after_channel_card', $channel);
```

## Available Filters

### Show Meta Data

```php
// Modify the show meta array before display
add_filter('cablecast_show_meta', function($meta, $post) {
    // Add custom meta
    $meta['custom'] = [
        'label' => 'My Label',
        'value' => 'My Value',
        'link'  => 'https://example.com',
    ];
    return $meta;
}, 10, 2);
```

### Schedule Calendar Attributes

```php
// Customize calendar settings on channel pages
add_filter('cablecast_schedule_calendar_atts', function($atts, $channel_id) {
    $atts['view'] = 'dayGridMonth'; // Default to month view
    return $atts;
}, 10, 2);
```

### Now Playing Attributes

```php
// Customize now playing widget on channel pages
add_filter('cablecast_channel_now_playing_atts', function($atts, $channel_id) {
    $atts['show_up_next'] = 'false';
    return $atts;
}, 10, 2);
```

### Shows Grid on Archives

```php
// Customize shows grid on producer archive
add_filter('cablecast_archive_producer_shows_atts', function($atts, $term) {
    $atts['columns'] = 3;
    $atts['count'] = 12;
    return $atts;
}, 10, 2);

// Customize shows grid on series archive
add_filter('cablecast_archive_series_shows_atts', function($atts, $term) {
    $atts['orderby'] = 'title';
    return $atts;
}, 10, 2);
```

### Upcoming Runs Query

```php
// Modify the upcoming runs query
add_filter('cablecast_upcoming_runs_args', function($args, $show_id) {
    $args['count'] = 10;
    $args['days_ahead'] = 30;
    return $args;
}, 10, 2);
```

### Template Location

```php
// Change where the plugin looks for templates in themes
add_filter('cablecast_theme_template_path', function($path) {
    return 'my-custom-cablecast/';
});

// Filter the located template path
add_filter('cablecast_locate_template', function($template, $template_name, $template_path) {
    // Custom logic to determine template
    return $template;
}, 10, 3);
```

## Helper Functions

These functions are available for use in your templates:

```php
// Check if show has VOD
cablecast_has_vod($post);

// Check if show has chapters
cablecast_has_chapters($post);

// Get VOD embed HTML
cablecast_get_vod_embed($post);

// Get show meta array
cablecast_get_show_meta($post);

// Format runtime (seconds to H:MM:SS)
cablecast_format_runtime($seconds);

// Get channel live embed HTML
cablecast_get_channel_live_embed($post);

// Load a template part
cablecast_get_template('content-show.php', ['post' => $post]);

// Get template HTML as string
$html = cablecast_get_template_html('content-show.php', ['post' => $post]);

// Locate a template file
$path = cablecast_locate_template('single-show.php');
```

## Disabling Templates

If your theme provides its own templates without using the override system, you can disable the plugin's template system:

1. Go to **Settings > Cablecast**
2. Uncheck "Enable Templates"

Or programmatically:

```php
add_filter('cablecast_templates_enabled', '__return_false');
```

## CSS Classes

All templates use BEM-style CSS classes for easy customization:

- `.cablecast-show-single` - Show single page wrapper
- `.cablecast-channel-single` - Channel single page wrapper
- `.cablecast-taxonomy-archive` - Taxonomy archive wrapper
- `.cablecast-archive` - General archive wrapper
- `.cablecast-show-card` - Show card component
- `.cablecast-channel-card` - Channel card component
- `.cablecast-upcoming-runs` - Upcoming runs component

The default styles are in `assets/css/shortcodes.css` and can be disabled in settings.
