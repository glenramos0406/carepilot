=== CarePilot ===
Contributors: glenramos0406
Tags: maintenance, admin, updates, logging, dashboard
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Records finished WordPress, plugin, and theme updates, and keeps a log of maintenance work you add by hand.

== Description ==

CarePilot keeps a maintenance record on the site where it is installed. It does not send that record to another service, and it does not watch other websites.

When an update finishes, CarePilot stores one row. Starting an update is not recorded as completed. You can also write down work WordPress cannot see, such as a content change or a layout fix. Those manual entries stay separate from detected updates.

The dashboard shows counts, a 30-day chart, basic site details, and the latest work. Maintenance Activity lists detected events. Maintenance Logs is where you add, edit, search, export, or delete work you recorded. Settings controls tracking, which events to skip, how long rows are kept, and whether data is removed when the plugin is deleted.

Deactivating the plugin keeps your tables. Deleting the plugin removes them only when "Delete all CarePilot data if I delete this plugin" is turned on in Settings.

CarePilot does not include performance checks or client reports.

== Installation ==

1. Upload the plugin zip from Plugins → Add New → Upload Plugin, or upload the `carepilot` folder to `/wp-content/plugins/`.
2. Activate CarePilot.
3. Open CarePilot in the admin menu. The dashboard stays empty until an update finishes or you add a maintenance log.

== Frequently Asked Questions ==

= Does activation record old updates? =

No. Nothing is backfilled. A row is added when an update finishes after CarePilot is active, or when you save a manual log.

= Does this plugin call Google or any other service? =

No. CarePilot stores data in your database and does not make remote requests.

= What personal data does it store? =

Only what an administrator types into a maintenance log, plus the update details WordPress already has, such as a plugin name and version. Nothing is sent off the site. Internal notes stay on the Maintenance Logs screen.

= Will deactivating the plugin delete the log? =

No. Deactivation leaves the tables in place. Deleting the plugin removes them only when the delete-data setting is on.

= Where is support? =

Use the support forum on this plugin's WordPress.org page.

== Screenshots ==

1. Dashboard with site details and an empty record.
2. Maintenance logs, including a saved entry.
3. Settings for tracking, retention, and deleting data with the plugin.

== Changelog ==

= 1.0.0 =
* Dashboard, detected maintenance activity, manual maintenance logs, and settings. An update is recorded when it finishes.

== Upgrade Notice ==

= 1.0.0 =
First release. Records finished updates and manual maintenance logs on this site.
