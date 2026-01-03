# Tester Feedback Analysis: WP Cablecast Shortcodes

## Summary

This document categorizes tester feedback into **Bugs**, **Feature Requests**, and **Change Requests**, with investigation findings and prioritized recommendations.

---

## BUGS (6 Total)

### BUG-1: Timezone - 8 Hours Ahead Issue (CRITICAL)
**Affects:** Schedule (remaining/next modes), Weekly_Guide, Now_Playing

**Root Cause:** `strtotime()` and `time()` are used without timezone context after schedule times have already been converted to site timezone.

**Files:**
- `includes/shortcodes.php` lines 345, 615, 766-772
- `assets/js/shortcodes.js` lines 72-88

**The Problem:**
1. Schedule times stored in UTC in database
2. `cablecast_get_schedules()` converts to site timezone (correct)
3. Shortcodes then call `strtotime()` on already-converted time strings
4. `strtotime()` interprets these as server time (UTC), causing 8-hour offset

**Why Schedule_Calendar works:** Uses ISO 8601 dates with `date('c')` and FullCalendar JS handles timezone conversion automatically.

**Fix Options:**
- **Option A (Recommended):** Use DateTime objects with explicit timezones throughout instead of `strtotime()`. Follow the pattern used in `upcoming_runs_shortcode()` which handles this correctly.
- **Option B:** Store timestamps alongside formatted times in schedule data and use those for comparisons.

---

### BUG-2: Weekly_Guide Channel Switcher Not Working
**Affects:** Weekly_Guide shortcode

**Root Cause:** URL building may create malformed URLs, plus potential cache issues.

**Files:**
- `includes/shortcodes.php` lines 715-718

**The Problem (Line 718):**
```php
$current_url = remove_query_arg('channel');
// Then concatenates: $current_url . '&channel=' + value
```
If `$current_url` has no existing query string (e.g., `https://example.com/schedule/`), appending `&channel=123` creates malformed URL: `https://example.com/schedule/&channel=123` instead of `?channel=123`.

**Note:** The PHP logic for reading channel IDs (lines 659-675) appears correct - it reads WordPress post IDs from URL and correctly looks up Cablecast channel IDs. The tester reports "URL changes but data doesn't change" which suggests:
1. Malformed URL issue, OR
2. Page-level caching preventing PHP re-execution

**Fix Options:**
- **Option A (Recommended):** Use `add_query_arg()` properly in JavaScript or build URL correctly with `?` vs `&` handling
- **Option B:** Debug on live site to determine if caching is the actual issue

---

### BUG-3: Shortcode Styling Checkbox Won't Disable
**Affects:** Settings page

**Root Cause:** Missing sanitization callback for checkbox handling.

**File:** `includes/settings.php` lines 116, 582-586

**The Problem:**
- When checkbox unchecked, HTML forms don't send any value
- Without sanitization callback, WordPress doesn't update the option
- Default logic `!isset($options['shortcode_styles']) || $options['shortcode_styles']` treats "not set" as enabled

**Fix:**
- Add sanitization callback to `register_setting()` that explicitly sets false for unchecked checkboxes

---

### BUG-4: Cablecast Home Channel Buttons Don't Work
**Affects:** Cablecast Home shortcode

**Root Cause:** JavaScript event handlers are completely missing.

**Files:**
- `includes/shortcodes.php` lines 1952-1961 (renders buttons)
- `assets/js/shortcodes.js` (missing click handlers)

**The Problem:**
- Buttons render with `data-channel` attributes
- No JavaScript code exists to handle button clicks
- Only dropdown-based channel switcher has JS handlers

**Fix:**
- Add event listeners for `.cablecast-home__channel-tab` buttons
- Implement AJAX-based content update or page reload with channel parameter

---

### BUG-5: Categories Grid Layout Breaks with Long Names
**Affects:** Categories shortcode in grid layout

**Example:** https://cmac.tv/dev/category-test/

**Root Cause:** CSS doesn't handle text overflow for long category names.

**File:** `assets/css/shortcodes.css` (categories grid styles)

**Fix:**
- Add CSS: `word-wrap: break-word; overflow-wrap: break-word;` to category items
- Consider `text-overflow: ellipsis` or `hyphens: auto`

---

### BUG-6: Cablecast Home Browse Links Incorrect
**Affects:** Cablecast Home shortcode

**The Problem:**
- "All Series" links to `?browse=series` but parameter is ignored
- "All Producers" had undefined variable and broken link

**Files:**
- `includes/shortcodes.php` lines 2001-2040
- Archive templates don't process `browse` parameter

**Status: PARTIALLY FIXED**
- Fixed: Undefined `$shows_archive` variable causing broken Producers link
- Both links now consistently use the `browse` query parameter
- Remaining: The `browse` parameter is not yet processed by archive templates

**Future Enhancement:**
To fully fix this, add processing for the `browse` query parameter in the shows archive template, or create dedicated pages with `[cablecast_series]` and `[cablecast_producers]` shortcodes.

---

## FEATURE REQUESTS (Prioritized)

### Priority: HIGH (Broadly useful, moderate effort)

| Feature | Shortcode(s) | Description | Effort | Status |
|---------|-------------|-------------|--------|--------|
| Date picker/navigation | Schedule | Add date switcher/picker for schedule navigation | Medium | Pending |
| ~~Exclude by ID/slug~~ | ~~Shows, Producers, Series, Categories~~ | ~~Add `exclude` parameter to filter out specific items~~ | ~~Low~~ | **RESOLVED** - Replaced by "Hide from Listings" feature |
| ~~CG Exempt master setting~~ | ~~All schedule shortcodes~~ | ~~Master setting or per-shortcode option to exclude CG-exempt shows~~ | ~~Medium~~ | **RESOLVED** - CG Exempt shows are now auto-hidden |
| Additional Calendar views | Schedule_Calendar | Document listDay, dayGridWeek, dayGridDay views | Low (docs only) | **DONE** |

### Priority: MEDIUM (Useful, varies in effort)

| Feature | Shortcode(s) | Description | Effort |
|---------|-------------|-------------|--------|
| Separate show_email/show_website | Producers | Replace `show_contact` with granular controls | Low |
| show_description option | Series | Add description display toggle | Low |
| Show/hide options | Shows | Add: show_category, show_postdate, show_producer, show_series, show_description | Medium |
| "remaining" mode for Calendar | Schedule_Calendar | Add remaining-today mode like Schedule shortcode | Medium |
| show_thumbnails for Calendar | Schedule_Calendar | Add thumbnail display in calendar events | Medium |

### Priority: LOW (Niche or high effort)

| Feature | Shortcode(s) | Description | Effort |
|---------|-------------|-------------|--------|
| Alphabetic navigation | Producers, Series | A-Z letter navigation for name-ordered lists | High |
| Search functionality | Producers, Series | Add search/filter capability | High |
| First_Runs shortcode | New | Display shows with first-ever airing this week | High (needs API research) |
| Categories as custom taxonomy | Categories | Register as taxonomy of 'shows' CPT | High (breaking change) |

---

## CHANGE REQUESTS / DOCUMENTATION

| Request | Type | Action |
|---------|------|--------|
| Clarify "next" mode means next 24 hours | Docs | Update shortcode documentation |
| Clarify "remaining" means rest of today | Docs | Update shortcode documentation |
| "Loading" instead of "No events" during fetch | UX | Change JavaScript loading state message |
| Document additional Calendar views | Docs | Add listDay, dayGridWeek, dayGridDay to docs |
| Explain Cablecast Home page editing | Docs | Note that generated page is template-controlled |
| Template system setting reference | Docs/Code | Either add the setting or remove references from display.php and README |
| Clarify date vs event_date in Shows | Docs | Document that date=post date, event_date=Cablecast event date |

---

## ITEMS REQUIRING INVESTIGATION

### VOD Chapters Post Meta Not Visible in Admin
**Reported:** Chapter meta not visible in WP admin for post ID 62285

**Finding:** Post meta `cablecast_vod_chapters` is stored as serialized array. WordPress admin doesn't show custom post meta by default.

**Status:** This is expected behavior, not a bug. Chapters are stored and retrieved correctly (working on cmac.tv/dev/show-test/).

**Options:**
- Add a custom meta box to display chapters in admin (feature request)
- Document that chapters are stored as hidden post meta

### show_channel in Upcoming Runs
**Reported:** show_channel not working

**Finding:** Code logic appears correct (lines 1703, 1755 in shortcodes.php). Tests exist and pass.

**Possible causes:**
1. Channel data is null (missing `channel_post_id` in schedule item)
2. CSS hiding the element
3. Caching issue

**Action:** Need live debugging on cmac.tv/dev/show-test/ to determine root cause.

---

## RECOMMENDED FIX ORDER

1. **BUG-1: Timezone** - Critical, affects multiple shortcodes
2. **BUG-3: Styling Checkbox** - Quick fix, high user impact
3. **BUG-2: Weekly_Guide Channel Switcher** - Core functionality broken
4. **BUG-5: Categories Grid CSS** - Quick CSS fix
5. **BUG-4: Cablecast Home Buttons** - Missing functionality
6. **BUG-6: Browse Links** - Lower priority, workaround exists

---

## FILES TO MODIFY

### For Bug Fixes:
- `includes/shortcodes.php` - Timezone fixes, channel switcher
- `includes/settings.php` - Checkbox sanitization
- `assets/js/shortcodes.js` - Cablecast Home button handlers
- `assets/css/shortcodes.css` - Categories grid overflow

### For High-Priority Features:
- `includes/shortcodes.php` - exclude parameters, show/hide options
- `includes/settings.php` - CG Exempt master setting
- `README.md` / documentation files - Clarifications

---

## IMPLEMENTATION STATUS

### Completed (All Bugs Fixed)
All 6 bugs have been fixed in PR #48:
- BUG-1: Timezone - Fixed with DateTime objects and explicit timezones
- BUG-2: Weekly_Guide Channel Switcher - Fixed with URL API in JavaScript
- BUG-3: Styling Checkbox - Fixed with proper sanitization callback
- BUG-4: Cablecast Home Buttons - Added click handlers in JavaScript
- BUG-5: Categories Grid CSS - Added word-wrap styles
- BUG-6: Browse Links - Fixed undefined variable

### Completed (Documentation)
- Clarified mode description (remaining/next)
- Clarified orderby options (date vs event_date)
- Documented additional Calendar views
- Explained Cablecast Home page editing
- Added Enable Templates setting to UI

### Completed (Hide from Listings Feature)
A new "Hide from Listings" feature replaces the need for:
- Exclude by ID/slug shortcode parameter
- CG Exempt master setting

**How it works:**
- "Cablecast: Visibility" checkbox on Show edit screen
- "Hide from listings" checkbox on Producer, Series, Category term edit screens
- CG Exempt shows are automatically hidden (synced from Cablecast API)
- Cascading: hiding a producer/series/category hides all associated shows
- Hidden column in Shows admin list shows visibility status
- Hidden content still accessible via direct URL

**Files modified:**
- `includes/content.php` - Meta box, term fields, helper functions, admin column, archive filtering
- `includes/sync.php` - Sync cablecast_show_cg_exempt from API
- `includes/shortcodes.php` - Filter hidden content from all listing shortcodes
- `README.md` - Documentation

---

## REMAINING FEATURE REQUESTS (Prioritized by Value)

### HIGH Value (Broadly useful)
| Feature | Complexity | Notes |
|---------|------------|-------|
| Date picker/navigation for Schedule | Medium | Natural UX improvement for browsing schedules |

### MEDIUM Value (Useful but niche)
| Feature | Complexity | Notes |
|---------|------------|-------|
| show_description option for Series | Very Low | Quick win if requested |
| Show/hide options for Shows shortcode | Low | Provides layout flexibility |
| show_thumbnails for Calendar | Medium | Visual improvement |
| Separate show_email/show_website | Very Low | Brian-specific granularity |

### LOW Value (Niche or high effort)
| Feature | Complexity | Notes |
|---------|------------|-------|
| Alphabetic navigation | High | Search would be more practical |
| Search functionality | High | Only useful for large lists |
| First_Runs shortcode | High | Needs API research |
| "remaining" mode for Calendar | Medium | Odd UX for calendar view |
| Categories as custom taxonomy | Very High | Breaking change, not recommended |
