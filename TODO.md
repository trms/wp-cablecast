# wp-cablecast Plugin TODO

## Future Data Sources

### Show Agendas

The theme template `show-agenda.php` supports displaying PDF agendas for shows. Currently this is a placeholder template that needs a data source.

**Required implementation:**
- Add meta field `cablecast_show_agenda_url` to store the URL of the agenda PDF
- Option A: Sync from Cablecast API if agenda attachments are available
- Option B: Allow manual entry via WordPress admin
- Option C: Look for agenda in Cablecast show attachments during sync

**Meta field name:** `cablecast_show_agenda_url`
**Expected value:** Full URL to PDF file (string)

### Show Chapters

The theme template `show-chapters.php` supports displaying chapter markers/timestamps for shows. Currently this is a placeholder template.

**Required implementation:**
- Add meta field `cablecast_show_chapters` to store chapter data
- Data format: Array of associative arrays with `timestamp` and `title` keys
- Option A: Sync from Cablecast API if chapter data is available
- Option B: Allow manual entry via WordPress admin
- Option C: Parse from video metadata during sync

**Meta field name:** `cablecast_show_chapters`
**Expected value:** Serialized array
```php
[
    ['timestamp' => '00:00:00', 'title' => 'Introduction'],
    ['timestamp' => '00:05:30', 'title' => 'Main Topic'],
    ['timestamp' => '00:15:00', 'title' => 'Q&A'],
]
```

## Notes

- Both features require corresponding changes in `includes/sync.php` if sourcing from Cablecast API
- Theme templates are ready to consume this data once meta fields are populated
