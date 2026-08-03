=== Plugin Name ===
Contributors: (ranktotop2015)
Donate link: https://marcmeese.de/
Tags: CAPI, tracking, facebook, google
Requires at least: 6.0.0
Tested up to: 6.8.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Erm&ouml;glicht Browser- und Serverseitiges Tracking f&uuml;r Facebook, Google & Co

== Description ==

<p>Seit dem IOS 14 Update, welches Apple im April 2021 live geschaltet hat, reicht einfaches Browser-basiertes Tracking nicht mehr aus. </p>
<p>Daher wird mit diesem Plugin eine zus&auml;tzliche DSGVO-konforme serverseitige M&ouml;glichkeit des Trackings zur Verf&uuml;gung gestellt. </p>
<p>Das Smart Server Side Tracking Plugin ist ideal f&uuml;r Seitenbetreiber, die auch ohne WooCommerce auf einfache Art und Weise tracken wollen.</p>

== Installation ==

1. Plugin in Plugins-Ordner hochladen
2. Aktivieren

== Fragen und Antworten ==

= Warum serverseitig tracken? =

Das serverseitige Tracking erm&ouml;glicht es, auch bei &Auml;nderungen wie dem iOS 14-Update und einer Ad-Blocking-Software genaue Berichte zu erhalten.

= Ist das DSGVO-Konform? =

Genau wie auch beim Browser-basierten Tracking wird eine Zustimmung des Nutzers zur Erfassung seiner Daten ben&ouml;tigt. Daher ist dieses Plugin mit mehreren Cookie-Diensten kompatibel.

== Changelog ==

= 1.15.1 =
* Consent mode panel in the Google section: the code blocks were rendered with the browser default look, which collided with the dark admin theme — the theme forces light heading colours, so the headings were near invisible on the white background. The panel now follows the plugin's own styling and its presentation moved from inline styles into the admin stylesheet

= 1.15.0 =
* Google Analytics: the browser tag now sends Consent Mode v2 signals (analytics_storage, ad_storage, ad_user_data, ad_personalization). Without an ad_user_data signal Google stops exporting GA4 conversions to Google Ads — the conversion column there stays at zero even though Analytics records the purchases. The signals follow the consent that is already configured for the tag, so there is nothing new to set up. If another tool (e.g. the consent manager itself) already manages consent mode, the plugin keeps its hands off — and because Borlabs' own consent mode only ever sets analytics_storage unless IAB TCF is running, the Google section now shows a collapsible panel with the ready-made opt-in, opt-out and fallback code for Borlabs, covering both cases

= 1.14.0 =
* TikTok: the click-id cookie (_ttc) was hard-wired to 7 days, TikTok's default click-through window rather than a limit. Accounts running a longer CTA window (14 or 28 days, set per ad group in Attribution Manager) silently lost the click-id on late conversions. It now lives for 28 days, the longest window TikTok offers
* Meta: the _fbc cookie had its lifetime renewed on every pageview while the click timestamp inside the value stayed frozen, so click-ids from months ago kept riding along on every event. Meta reads that timestamp, discards the value and flags it in Events Manager as an expired fbclid. Stale values are now dropped instead of being kept alive
* Google Analytics: removed the gclid cookie. It was stored for 90 days and sent along with every server event, where nothing consumed it — the GA4 Measurement Protocol has no click-id field at all. One cookie less per visitor

= 1.13.3 =
* Product feed: the feed now uses the numeric WooCommerce product ID as <g:id> instead of the SKU, matching the IDs the tracking pixels send as content_ids. A mismatch here is what makes Meta report a catalogue match below 90%. The "Manage feed" table shows the ID column accordingly

= 1.13.2 =
* Manage feed table: the product name now links to the product's edit screen (opens in a new tab) so you can jump straight to a product and fix a flagged problem. The link looks like normal text and only underlines on hover

= 1.13.1 =
* Manage feed table: reordered and resized the columns for readability — Image, Product, Description, Price, SKU, Status. Product and SKU are now narrower and the description column is much wider

= 1.13.0 =
* Product feed: added a "Description" column to the "Manage feed" product table showing a short preview of each product's feed description, with a quality check. An empty description or one longer than 5000 characters (Google/Meta limit) highlights the field in red with a hover icon explaining why. Like the other checks, it only runs for the products on the current page

= 1.12.1 =
* Product feed: a missing Google product category no longer highlights the product row in the feed list — the list now only turns red for image, SKU or price problems. Instead, categories without a mapping yet are highlighted in red in the mapping panel below, where they are assigned

= 1.12.0 =
* Product feed: added a per-field quality check on the "Manage feed" page. Products with a feed problem now highlight the offending field in red with a hover icon explaining why: missing image, image below 500x500 px or above 8 MB (Facebook/Meta limits), empty SKU, or price of 0. Image dimensions are read from stored metadata and only the visible page is checked, so large catalogs stay fast
* Product feed: added Google product category support. Map each WooCommerce category to a Google product category once (with autocomplete from the bundled Google taxonomy) in the new panel on the "Manage feed" page; products inherit the mapping (including sub-categories and variations) and the feed emits g:google_product_category. Products without a mapped category are flagged
* Product feed: added an "Include product variants" switch (WooCommerce section, on by default). When off, variable products are listed as a single parent item instead of one item per variation

= 1.11.2 =
* Fixed Google Analytics 4 server-side ecommerce events (view_item, add_to_cart, purchase, etc.) not appearing in GA4: the Measurement Protocol requires item_id/item_name/item_brand, but the plugin sent the legacy id/name/brand keys, so GA4 saw no valid item and dropped the whole event. Browser and server GA4 item payloads now use the correct GA4 item keys

= 1.11.1 =
* Fixed a harmless "[object Object] is not valid JSON" console error that appeared when a plugin (e.g. Borlabs Cookie) makes admin-ajax reply with an application/json content type; the tracking AJAX callbacks now handle both string and pre-parsed object responses

= 1.11.0 =
* Added a product-feed management page (WooCommerce section -> "Manage feed"): control which published products are included in or excluded from the product feed without editing each product
* Server-side paginated, searchable product list with a per-row in-feed/excluded toggle and bulk include/exclude; excluding a variable product also removes its variations
* Exclusions persist in the wp_sdtrk_feed_excluded option and invalidate the feed cache so changes apply on the next refresh

= 1.10.0 =
* Added WooCommerce InitiateCheckout tracking on the checkout page (Meta InitiateCheckout, GA4 begin_checkout, TikTok InitiateCheckout), browser and server in one pass
* Fires on every checkout page load for a non-empty cart; takes precedence over a pending add-to-cart (order > beginCheckout > addToCart > viewItem)

= 1.9.0 =
* Added WooCommerce ViewItem tracking on product pages (Meta ViewContent, GA4 view_item, etc.), browser and server in one pass
* Added WooCommerce AddToCart tracking (server-buffered on add, fired on the next page load; covers AJAX and form add-to-cart)
* Unified product id scheme: order line items now use the variation id so the feed, ViewItem, AddToCart and Purchase share one catalog id for variable products

= 1.8.0 =
* Rebuilt WooCommerce purchase tracking: fires on the order-received page, browser and server in one pass, deduplicated via the order id
* Multi-product carts and shop currency for Meta, GA4 and TikTok purchases (single-product and EUR fallbacks unchanged)
* Fixed Matomo tracking (matomo.js was never loaded), a TikTok identify crash, and a stray LinkedIn debug log
* Order-received page now validates the order key before exposing buyer data; per-order reload guard to avoid double-counting

= 1.7.6 =
* Added compatibility to borlabs v3

= 1.7.5 =
* Changed UI structure for decryption

= 1.7.0 =
* Switched to redux. Added matomo

= 1.6.5 =
* Added support for copecart

= 1.6.4 =
* Changed fieldtype of date in csv feed

= 1.6.3 =
* Added live-hit-feed sync feature

= 1.6.2 =
* Removed tracking for admin-users

= 1.6.1 =
* Added fingerprinting feature

= 1.6.0 =
* Completely rewriten tracker logic for higher compatibility

= 1.5.1 =
* Added option for CSV sync on hourly base

= 1.4.9 =
* Fixed Bug with non tracked local ButtonClicks

= 1.4.9 =
* Fixed Bug with custom table-prefix

= 1.4.8 =
* Fixed Bug with gSheet-sync

= 1.4.7 =
* Fixed Bug with gSheet-Index

= 1.4.6 =
* Added CSV-File sync

= 1.4.5 =
* Fixed bug with gsheet row-limits

= 1.4.4 =
* Fixed bug with headers are already sent on gauth

= 1.4.3 =
* Updated minified versions of js

= 1.4.2 =
* Added Google Sheet sync cronjob for auto-sync

= 1.4.1 =
* Added Google Sheet sync for local tracking

= 1.4.0 =
* Added Local Event-Tracking

= 1.3.2 =
* Added LinkedIn Browser-Tracking

= 1.3.1 =
* Added google measurement protocol

= 1.3.0 =
* Added Tracking overwrite for specific Pages

= 1.2.9 =
* Added support for digistore24 GET-encryption

= 1.2.8 =
* Added advanced matching parameters to tiktok

= 1.2.7 =
* Fixed Meta issue with privacy parameters

= 1.2.6 =
* Added mautic tracking

= 1.2.5 =
* Added funnelytics tracking

= 1.2.4 =
* Fixed error when tracking button clicks to ga4

= 1.2.3 =
* Fixed error with ViewContent Events on TikTok-Browser-Pixel

= 1.2.2 =
* Saves Google Ads click-ID if user came from google ad

= 1.2.1 =
* Changed GA4 Behavior

= 1.2.0 =
* Added TikTok to Tracking-Services

= 1.1.4 =
* Fixed issues with URLs containing other domains in parameters

= 1.1.3 =
* FBP and FBC cookies are now refreshing automatically

= 1.1.2 =
* Added automatic click- and scroll-tracking

= 1.1.1 =
* Fixed Bug when using utm_content with facebook-pixel

= 1.1.0 =
* Fixed Bug with Analytics Debugging

= 1.0.9 =
* Added support for automatic time tracking

= 1.0.8 =
* Fixed bug while backloading

= 1.0.7 =
* Fixed bug with name.toLowerCase() is not a defined

= 1.0.6 =
* Fixed bug when using caching

= 1.0.5 =
* Added help-section to plugin

= 1.0.4 =
* Added backload-function for cookie consents

= 1.0.3 =
* Added Borlabs Cookie-support

= 1.0.2 =
* Added Google-Analytics to services

= 1.0.0 =
* Initial release

== Marc Meese ==

Hier geht es direkt zur [Website](https://marcmeese.de/ "Ihre Marketing Agentur Nr 1").