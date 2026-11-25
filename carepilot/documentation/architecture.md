# CarePilot architecture

CarePilot is two plugins.

| Plugin | Folder | Namespace | Role |
| --- | --- | --- | --- |
| Free | `carepilot` | `WPCareReport` | Maintenance record, health, dashboard |
| Pro | `wp-care-report-pro` | `WPCareReportPro` | PageSpeed, reports, email, white label |

The free plugin works with Pro absent. Pro activates with the free plugin absent, shows an admin notice, and does not run premium features. Pro never fatals because a free class is missing.

## How they communicate

Pro does not query `wpcare_activities` or `wpcare_maintenance_logs`. It calls `WPCareReport\Api`.

`Api::CONTRACT` is `1`. Pro expects that exact contract. A newer free plugin that changes a call bumps the contract, and this Pro build asks for an update instead of guessing.

The free plugin fires `wpcare_report_loaded` on `plugins_loaded` at priority 10, after its schema check. Pro subscribes to that action while its own file is included. Folder order cannot be trusted: `wp-care-report-pro` sorts before `carepilot`.

Useful calls:

- `Api::version()` and `Api::contract()`
- `Api::settings()` for agency name, retention, and report period
- `Api::count_activities()`, `Api::get_activities()`
- `Api::count_logs()`, `Api::get_logs()`
- `Api::activity_label()`, `Api::category_label()`, `Api::status_label()`

Activity rows and manual logs are different tables. A report can count them separately. The free settings array does not contain the Google API key.

## What this phase actually does

The free plugin shows four screens: Dashboard, Maintenance Activity, Maintenance Logs, and Settings. Counts and the 30-day chart come from stored rows. A new install has an empty state.

Automatic rows are written when WordPress reports a finished outcome: a successful core update, a package install or update that `install_package()` accepted or rejected, a plugin activation or deactivation, or a theme switch. Starting an update is not recorded as completed. Translation packs are ignored. The same event key is stored once.

Manual logs are created, edited, deleted, filtered, and exported by an administrator. Internal notes stay on that screen.

A daily cleanup removes rows older than the retention setting. Zero means keep them. Settings changes tracking, excluded activity types, retention, and the delete-data flag. Website health is a later phase.

Pro, when the free plugin is compatible, adds three screens under the CarePilot menu: Pro, Pro settings, and License. The overview reads stored URL, result, and report counts. A new install shows zeros. Settings saves frequency, device, retention, batch size, the scheduled-check preference, and the Pro delete-data flag. It does not store a Google API key. The license screen reports development mode or that no license server is connected. `License::allows()` is the gate later phases will call. Development mode is `WPCARE_DEV_MODE` set to boolean `true`. It skips that gate only. PageSpeed requests and client reports are not running.

## Database

Free, created by the free plugin:

- `{prefix}wpcare_activities` — one row per verified automatic event. `event_key` is unique so the same completion cannot be stored twice. It is left NULL until a real event is written, because a unique empty string would collide. `status` is `completed` or `failed`. A hook that only starts an update is not a completed row.
- `{prefix}wpcare_maintenance_logs` — manual work. `internal_notes` stays in the admin. Client reports must not print that column.

Pro, created by the Pro plugin:

- `{prefix}wpcare_monitored_urls` — `url_hash` is a SHA-256 of the normalized URL. The column is NULL until a URL is saved, so empty hashes do not collide.
- `{prefix}wpcare_performance_results` — one row per URL, per device, per run. Scores and timings are NULL when the API omits them. NULL is not stored as zero. Lab columns and `field_*` columns are separate. `field_data_available` is 0 when CrUX has nothing for that URL. `error_message` is a short failure note, not the raw response.
- `{prefix}wpcare_report_history` — generated reports. The file or HTML is not stored in this table during this phase.

Times are milliseconds. CLS is a decimal. There is no column for the raw PageSpeed JSON.

## PageSpeed, when that phase is built

Requests go out with `wp_remote_get()` to `https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed` from WP-Cron, never during a page view. The key is a separate non-autoloaded option. It is not printed in HTML, admin JS, or the free settings array.

Mobile and desktop are two rows. Category scores of 0–100 come from Lighthouse's 0–1 scores. 90–100, 50–89, and 0–49 are labels for those scores, not a verdict on the whole site.

CrUX is a second server-side call to `https://chromeuxreport.googleapis.com/v1/records:queryRecord`. The same saved key is used when the Google project has that API enabled. Field metrics stay in the `field_*` columns. If Google has no record for the URL, the report says so. Lab numbers are not copied into those columns, and the PageSpeed `loadingExperience` block is not used as a stand-in.

A monitored URL must be `http` or `https`, must pass `wp_http_validate_url()`, and must use the host of `home_url()` or `site_url()`. That keeps the client's key pointed at the site being maintained. Local and private addresses are rejected. One in-flight test per URL and device is enforced with a short-lived lock so a second cron pass cannot start the same call.

A failed Google request does not touch the maintenance tables.

## License and development mode

The license server is not connected. `WPCareReportPro\License::provider()` returns a development provider or an unconfigured provider. A later license server replaces the unconfigured provider without changing callers of `License::allows()`.

`WPCARE_DEV_MODE` set to boolean `true` in `wp-config.php` is the only local bypass, and it bypasses the license check only. The string `"true"` does not count. Development mode does not bypass the free plugin, and it does not invent scores.

## Data removal

Pro uninstall drops only Pro tables and Pro options, and only when its own delete setting is on. It does not drop the free plugin's tables.
