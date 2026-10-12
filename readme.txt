=== Bobr Analytics ===
Contributors: loppadvp
Tags: analytics, statistics, privacy, cookieless, ai
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted website statistics without cookies: visits, sources, AI referrals, bots, geography and page speed in your own database.

== Description ==

Bobr Analytics collects website statistics directly into your WordPress database. No cookies, no localStorage, no external analytics service. If a big analytics provider goes down, your statistics keep working as long as your site does.

It works next to Google Analytics or any other counter and does not touch them.

= What it shows =

* **Summary:** visitors, visits, pageviews, bounce rate, depth, time on site, with comparison to the previous period.
* **Charts by day, week or month:** traffic, source types, bounce rate, time, device share, top cities, speed, visitor actions.
* **Sources:** search engines, social networks, websites, direct visits, UTM campaigns.
* **AI assistants:** visits from ChatGPT, Perplexity, Gemini, Claude, Copilot, DeepSeek and others, and which pages they link to.
* **Bots:** search engine crawlers and AI agents, what they read, where they get 404 errors.
* **Pages:** entry and exit pages, time on page, scroll depth, how many visitors read to the end.
* **Geography:** countries, regions and cities from the offline DB-IP database (optional).
* **Technology:** devices, operating systems, browsers, screen widths.
* **Time:** heat map by day of the week and hour.
* **Events:** phone and email link clicks, outbound links, submitted and abandoned forms.
* **Speed:** server response, first paint, LCP, INP and CLS measured on real visitors.
* **Live now:** who is on the site in the last 30 minutes.
* Filters by source, device, city and page. Table search, pagination and CSV export.

= Privacy =

* No cookies and no localStorage.
* IP addresses are never stored. When city detection is enabled, the IP is used once to look up the city in a local file and then discarded.
* The full user agent string is never stored, only device type, operating system and browser.
* A visitor is identified by a hash of IP, user agent, site address and a random salt that changes every day. The previous salt is deleted, so a visitor can be recognized only within one day.
* Form field values, phone numbers and email addresses are never read or sent.
* Logged-in administrators, editors and shop managers are not counted by default.

= What it does not show =

* **Search queries.** Only search engines know them, in their webmaster tools.
* **Gender and age.** Large services infer them from cross-site profiles. This plugin sees only your site and uses no cookies.

= Languages =

English and Russian are included. The interface language follows the site language.

== External services ==

This plugin can download the free **DB-IP IP to City Lite** database to detect visitor cities. The download happens only when an administrator clicks the Download the city database button in Bobr Analytics, Settings. The plugin then requests a single file from db-ip.com (`https://download.db-ip.com/free/dbip-city-lite-YYYY-MM.mmdb.gz`). No visitor data and no site data are sent in this request. The file is stored in `wp-content/uploads/bobr/` and all lookups happen locally on your server.

The database is provided by DB-IP under the Creative Commons Attribution 4.0 license.

* DB-IP website: https://db-ip.com
* Terms of use and license: https://db-ip.com/db/download/ip-to-city-lite

The plugin makes no other external requests. The tracking script sends data only to your own site (`/wp-json/bobr/v1/hit`).

== Installation ==

1. In the WordPress admin go to Plugins, Add New Plugin and search for Bobr Analytics, or upload the plugin zip file.
2. Install and activate the plugin.
3. The tracker is added to your pages automatically, no code is needed. Open Bobr Analytics in the admin menu.
4. Optional: in Bobr Analytics, Settings, exclude your own IP addresses and download the city database.

== Frequently Asked Questions ==

= Does it need a cookie banner? =

The plugin sets no cookies and stores nothing in the visitor's browser. Whether you need a consent banner depends on the laws that apply to your site, please check them.

= Does it work with page caching? =

Yes for people: the tracker runs in the browser. Bots are recorded on the server side, so bot visits to cached pages are not counted. The settings page shows whether a page cache is active.

= Why is the number of visitors over a week higher than expected? =

Without cookies a visitor is recognized only within one day, so visitors over several days are the sum of daily visitors.

= How long is data kept? =

Detailed records are kept for 180 days by default (configurable from 30 to 1095 days) and deleted at night. Daily totals without personal data are kept forever.

= How do I remove all data? =

Data is kept when the plugin is deleted, so statistics are not lost by accident. To remove everything, drop the tables `wp_bobr_visits`, `wp_bobr_hits`, `wp_bobr_events`, `wp_bobr_bots`, `wp_bobr_daily` (with your table prefix), delete the options `bobr_settings`, `bobr_db_ver`, `bobr_salt`, `bobr_geo_updated` and the folder `wp-content/uploads/bobr/`.

= Where can I report a bug? =

On GitHub: https://github.com/LopatinaDVP/bobr-analytics/issues

== Screenshots ==

1. Summary with traffic charts.
2. Sources and AI assistant referrals.
3. Bots and AI agents.
4. Page speed measured on real visitors.
5. Phone clicks, form submissions and abandoned forms.
6. Settings.

== Changelog ==

= 1.3.0 =
* English interface, Russian translation included.

= 1.2.0 =
* Settings and Feedback links on the Plugins page.
* Whole numbers on chart axes.

= 1.1.0 =
* Charts by day, week or month for all sections.
* Device share, top cities, speed and visitor actions over time.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.3.0 =
English interface and Russian translation.
