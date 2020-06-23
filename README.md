# WP Cablecast
The WP Cablecast plugin aims to provide an easy way for WordPress site builders to integrate data from a Cablecast system into WordPress. The plugin handles importing `Shows`, `Channels`, `ScheduleItems`, `Projects`, `Producers`, `Categories` into WordPress so they can be queried and displayed the same as any other WordPress post.

## Getting Started Video
[![IMAGE ALT TEXT](http://img.youtube.com/vi/ARXSms9K_UE/0.jpg)](http://www.youtube.com/watch?v=ARXSms9K_UE "Video Title")

## Demo
I've set up a simple demo site that I'll be expanding with examples of how to do common things as questions are asked. Checkout http://wp-cablecast.raytiley.com to see what this plugin does.

## Installation
Upload the plugin and activate it. Then in the admin dashboard navigate to `Cablecast Settings`. There is a single input for the `Cablecast Server Address`. This is the url you use to login to Cablecast from the public internet. Example `http://tighty.tv`.

## Important Notes About cron_schedules
TL;DR - Follow these instructions for setting up wordpress cron: https://developer.wordpress.org/plugins/cron/hooking-into-the-system-task-scheduler/

The WP Cablecast plugin uses Wordpress's Cron System to periodically sync content from Cablecast. This can be a lengthy operation involving many API calls to Cablecast. By default WordPress will attempt to do this in a normal users web request. Depending on your server configuration this may cause a poor user experience as the visitor has to wait on the Cablecast Sync. It also may lead to incomplete syncs due to web request timeouts. For best experience it is recommended you execute WordPress cron using the system task scheduler so the sync happens independent of visitors using your site. See the link above for more information.

## Custom Post types
The WP Cablecast plugin creates custom posts for the following Cablecast resources. The resource and the post custom meta properties are described below.

### Show
A Cablecast show is the primary resource in Cablecast. It describes a program that viewers can watch on the channel or through VOD.

#### Show Meta

|Properties|Description|
|---|----|
|`cablecast_show_id`| |
|`cablecast_show_title`| |
|`cablecast_show_event_date`| |
|`cablecast_show_cg_title`| |
|`cablecast_show_comments`| |
|`cablecast_show_location_id`| |
|`cablecast_show_project_id`| |
|`cablecast_show_project_name`| |
|`cablecast_show_producer_id`| |
|`cablecast_show_producer_id_name`| |
|`cablecast_show_category_id`| |
|`cablecast_show_category_name`| |
|`cablecast_show_vod_embed`| |
|`cablecast_show_vod_url`| |
|`cablecast_show_custom_1`| |
|`cablecast_show_custom_2`| |
|`cablecast_show_custom_3`| |
|`cablecast_show_custom_4`| |
|`cablecast_show_custom_5`| |
|`cablecast_show_custom_6`| |
|`cablecast_show_custom_7`| |
|`cablecast_show_custom_8`| |
|`cablecast_last_modified`| |

### Channel

#### Channel Meta

|Properties|Description|
|---|---|
|`cablecast_channel_id`| |
|`cablecast_channel_live_embed_code`| |

## Custom taxonomies
WP Cablecast creates several two custom taxonomies `Projects` and `Producers` that are used to relate Cablecast Projects and Producers to Cablecast Shows.

### Projects

### Producers

#### Producer Meta
|Properties|Description|
|---|---|
|`cablecast_producer_address`| |
|`cablecast_producer_contact`| |
|`cablecast_producer_email`| |
|`cablecast_producer_name`| |
|`cablecast_producer_notes`| |
|`cablecast_producer_phone_one`| |
|`cablecast_producer_phone_two`| |
|`cablecast_producer_phone_website`| |

## Frequently Asked Questions
So far there are none... But in order to head some people off... here we go.

### How do I show the schedule for a channel?
Because there are so many schedule events, the WP plugin stores the schedule events as a seprate database table rather than creating a custom post type.

The plugin provides a function `cablecast_get_schedules($channel_id, $date_start)` that can be used in themes to get schedule items. The function returns an `Array` of objects with details on a schedule item.

|Property|Description|
|---|---|
|`run_date_time`| |
|`show_id`| |
|`show_title`| |
|`channel_id`| |
|`show_post_id`| |
|`channel_post_id`| |
|`schedule_item_id`| |

### How do I show the live stream for a channel?
The plugin sets a custom meta property of `cablecast_channel_live_embed_code` to the channel for the embed code to watch the live stream. If this property isn't set it's likely your Cablecast isn't configured correctly, or you don't have a live streaming server.

By default the plugin will modify the content when viewing a `Channel` to place the embed code above the schedule. If you have custom themed the `Channel` page you'll need to add the embed code to your theme.

### How do I show the vod for a show?
The vod embed code and url are set as custom meta on the `Show` in the `cablecast_show_vod_embed` and `cablecast_show_vod_url` respectivly.

### How do I show what's playing right now and what's coming up next?
Use this code: https://gist.github.com/bryanharley/7986451176db08f982683c1b973abc8c. Change the default timezone and $ChannelID as necessary. Insert the code in your theme file where desired. 


