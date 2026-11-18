=== WebPressor – WebP & AVIF Image Converter ===
Contributors: techbysh
Donate link: https://techbysh.com
Tags: webp, avif, convert webp, image optimization, image compression
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Convert JPEG and PNG images to WebP and AVIF on your own server and serve them automatically. Free AVIF, no image limits, originals kept.

== Description ==

WebPressor is a free WebP and AVIF converter for WordPress. It creates smaller WebP and AVIF copies of the JPEG and PNG images in your Media Library and shows them to browsers that support them, so pages load faster and PageSpeed's "Serve images in next-gen formats" warning goes away.

Conversion runs on your own server: no account, no API key, no monthly image limit, and AVIF is included for free. Your original images are never changed.

### Why WebPressor

* **AVIF at no cost.** Create WebP, AVIF or both. When both exist, browsers that support AVIF get AVIF and all others get WebP.
* **Nothing leaves your server.** Images are converted with PHP's GD library on your hosting. There is no cloud service, account or credit system.
* **Safe with page caches and CDNs.** The `<picture>` delivery method lets each browser choose its format, so a cached page always shows a working image. WebPressor recommends it when it detects a page cache.
* **Never makes an image bigger.** If a WebP or AVIF file would be larger than the original, it is discarded and the original is used.
* **A bulk optimizer that always finishes.** Images that cannot be converted are skipped and listed with the reason, so you can fix them and retry.
* **Easy to check.** The delivery self-test tells you whether your server really sends WebP or AVIF.

### Key features

* **Bulk optimizer** for the images already in your Media Library, with progress, savings and a live log.
* **Run in the background:** let the server continue the bulk optimization after you close the page.
* **Automatic conversion of new uploads**, including every thumbnail size. Images you crop, rotate or scale in the WordPress image editor are converted again.
* **Three delivery methods:** HTML alteration, `<picture>` elements, or server rewrite rules (`.htaccess`).
* **Content or entire page:** replace images in post content only, or everywhere on the page, including theme headers and page builder sections.
* **Quality control:** separate WebP and AVIF quality settings (10–100), plus lossy or lossless mode.
* **Media Library column:** see the status and savings of each image, and optimize, remove or exclude single images.
* **Exclusions:** skip folders such as `logos/` or individual images.
* **Maximum upload size:** scale down very large new uploads before conversion, using WordPress' own big-image handling.
* **Automatic cleanup:** deleting an image also deletes its WebP and AVIF files.
* **System status:** checks GD, WebP and AVIF support, folder permissions and the server type. Shows ready-to-copy Nginx rules.
* **Accessible, translatable admin screens** that work with the keyboard and screen readers.

### How it works

1. Install and activate WebPressor.
2. Go to **WebPressor → Dashboard** and click **Start Optimization** (or **Run in Background**).
3. Optional: choose formats and a delivery method under **WebPressor → Settings**, then run the test under **WebPressor → System Status**.

New uploads are converted automatically from then on.

### Choose how images are delivered

* **HTML Alteration (default):** swaps image URLs in the page for each visitor, based on what their browser supports. Needs no server setup.
* **`<picture>` Elements (cache-safe):** wraps images in `<picture>` tags that list the AVIF and WebP versions. The browser picks the best one, so it works with page caching plugins and CDNs.
* **Server Rewrite Rules:** adds rules to `/wp-content/uploads/.htaccess` (Apache and LiteSpeed) so image requests get WebP or AVIF without changing your pages, including CSS background images. On Nginx, add the rules shown on the Settings screen to your server configuration.

Browsers that support neither format always get the original JPEG or PNG.

### Perfect for

* Blogs and business sites that want to pass PageSpeed's "Serve images in next-gen formats" check.
* WooCommerce stores with many product photos. All registered image sizes, including product thumbnails and gallery images, are converted.
* Sites that use a page cache (WP Rocket, LiteSpeed Cache, W3 Total Cache and others) together with the `<picture>` method.
* Shared hosting, where a cloud optimizer with credits or an API key is not wanted.
* Developers and agencies who manage images from the command line with WP-CLI.

### Compatibility and requirements

* WordPress 6.0 or newer and PHP 7.4 or newer.
* The PHP GD extension with WebP support.
* AVIF needs PHP 8.1 or newer with AVIF support in GD. WebP works without it. The System Status tab shows what your server supports.
* Converts JPEG and PNG images stored in your local `wp-content/uploads` folder.
* On a WordPress Multisite network, each site has its own settings and its own optimization.

### What WebPressor does not do

* It does not compress or overwrite your original JPEG and PNG files. It adds WebP and AVIF copies next to them.
* It does not convert GIF, SVG or existing WebP images.
* It does not convert images stored outside your server, for example images offloaded to Amazon S3.
* It does not resize images that are already uploaded. The maximum upload size applies to new uploads only.
* It is not a CDN and does not add lazy loading.

### For developers

* **WP-CLI:** `wp webpressor status`, `wp webpressor convert [<id>...] [--limit=<number>] [--retry-failed]` and `wp webpressor reset --yes`.
* **Filters:** `tbswebpressor_resolve_variant_url`, `tbswebpressor_is_excluded`, `tbswebpressor_active_formats`, `tbswebpressor_batch_size`, `tbswebpressor_skip_image`, `tbswebpressor_picture_html`, `tbswebpressor_enable_output_buffer`, `tbswebpressor_background_time_limit`.
* **Action:** `tbswebpressor_attachment_processed`.
* Add the `tbswebpressor-skip` class to an image to leave it unchanged in `<picture>` mode.

### Privacy and external services

Image conversion happens entirely on your own server; your images are never uploaded anywhere. The delivery self-test only requests images from your own site.

The only external service is optional:

1. **Google Apps Script** (https://script.google.com) — Optional deactivation feedback, sent to the plugin author (Techbysh) and stored in a Google Sheet. It is sent **only** when an administrator deactivates the plugin and clicks **Submit & Deactivate** in the feedback popup; **Skip & Deactivate** and closing the popup send nothing. It contains the chosen reason, any text typed, the plugin, WordPress, and PHP versions, the site language, whether the site is multisite, and the active theme name. The site URL and admin email are included only if the administrator ticks the opt-in checkbox. Privacy: https://policies.google.com/privacy Terms: https://policies.google.com/terms

### Documentation, support and source code

* [Support forum](https://wordpress.org/support/plugin/webpressor-webp-image-converter-optimizer/)
* [Source code on GitHub](https://github.com/TechbyshOrg/tbs-webpressor)

The admin JavaScript is minified. Its React source is in the `react-admin` folder of the GitHub repository. To build it: clone the repository, run `cd react-admin`, `npm install` and `npm run build`.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New Plugin**.
2. Search for **WebPressor**, click **Install Now**, then **Activate**.
3. Open **WebPressor → Dashboard** in the admin menu and click **Start Optimization** to convert the images already in your Media Library.
4. Optional: review formats, quality and the delivery method under **WebPressor → Settings**.
5. Optional: go to **WebPressor → System Status** and click **Run Delivery Test** to confirm that WebP or AVIF is served.

To install manually, upload the plugin zip under **Plugins → Add New Plugin → Upload Plugin**, then follow steps 3 to 5.

== Frequently Asked Questions ==

= What does WebPressor do? =

WebPressor is a WordPress plugin that converts JPEG and PNG images to the WebP and AVIF formats on your own server. It then shows the smaller WebP or AVIF file to browsers that support it and the original to all others. Smaller images make pages load faster.

= Is WebPressor free? Are there image limits? =

Yes, WebPressor is free, including AVIF. There is no Pro version, no account, no API key and no monthly image limit. The only limit is your server's capacity.

= Does WebPressor change or delete my original images? =

No. WebPressor never overwrites or deletes your original JPEG and PNG files. It saves WebP and AVIF copies next to them, for example `photo.webp` next to `photo.jpg`.

= Which delivery method should I choose? =

Use **`<picture>` Elements** if your site uses a page cache or CDN, because the page HTML is the same for every visitor and each browser picks its own format. Use **HTML Alteration** (the default) if you do not use a page cache. Use **Server Rewrite Rules** on Apache or LiteSpeed if you also want CSS background images served as WebP or AVIF.

= Does WebPressor work with caching plugins and CDNs? =

Yes. Choose the **`<picture>` Elements** delivery method for caching plugins such as WP Rocket, LiteSpeed Cache or W3 Total Cache, then clear your cache after the bulk optimization. WebPressor warns you on the Settings screen and in the delivery self-test when it detects a page cache. If you use Server Rewrite Rules behind a CDN, the CDN must vary its cache by the browser's `Accept` header.

= Does WebPressor work with WooCommerce? =

Yes. WebPressor converts every registered WordPress image size, including WooCommerce product images, thumbnails and gallery images, during the bulk optimization and on upload.

= Does it work with page builders and themes? =

Images in post content are covered by every delivery method. Images that a theme or page builder adds outside the post content, such as headers, sliders or footer images, are covered when you choose **Entire page** under "Where to Replace Images", or when you use Server Rewrite Rules. Server Rewrite Rules also cover CSS background images.

= What does my server need? Why is AVIF unavailable? =

WebPressor needs PHP 7.4 or newer with the GD extension and WebP support. AVIF also needs PHP 8.1 or newer with AVIF support compiled into GD. If AVIF shows as unavailable on the System Status tab, ask your hosting provider for a PHP version with GD AVIF support. WebP keeps working without it.

= How do I convert images already in my Media Library? =

Go to **WebPressor → Dashboard** and click **Start Optimization**. Keep the page open while it runs, or click **Run in Background** to let your server continue after you close the page. New uploads are converted automatically when "Automatically convert images" is turned on in Settings.

= How do I check that WebP or AVIF is really served? =

Go to **WebPressor → System Status** and click **Run Delivery Test**. It requests a converted image from your own site and reports the format your server sends, with a hint when something needs fixing.

= Why do some images show "Original kept (smaller)"? =

Some JPEGs are already highly compressed and some PNGs are very simple, so a WebP or AVIF version can be larger. WebPressor then keeps only the original, so visitors never download a bigger file. You can turn this safeguard off in Settings.

= What happens when an image cannot be converted? =

The bulk optimizer records the reason, for example a missing or corrupt file or not enough server memory, skips the image and continues. The Dashboard lists failed images. Fix the cause and click **Retry Failed Images**, or exclude the image.

= Does WebPressor work on Nginx? =

Yes. The HTML Alteration and `<picture>` delivery methods work on Nginx without any server changes. For Server Rewrite Rules, Nginx ignores `.htaccess` files, so copy the Nginx rules shown on the Settings screen into your server configuration.

= How do I undo conversions, and what happens when I uninstall? =

To remove all WebP and AVIF files, click **Delete All WebP/AVIF Files** on the Dashboard. To remove the files of one image, use **Remove WebP/AVIF** in the WebPressor column of the Media Library list view. Your originals are never affected. Uninstalling WebPressor removes its settings and its server rules, but keeps the generated files, so delete them first if you no longer want them.

= Is any data sent outside my site? =

No image or site data is sent anywhere during normal use. The only exception is the optional feedback form shown when you deactivate the plugin, and it sends nothing unless you click **Submit & Deactivate**. See "Privacy and external services" above.

== Screenshots ==

1. Dashboard: storage saved, library progress and the bulk optimizer with its live log.
2. Settings: choose WebP and AVIF, set quality, pick a delivery method and turn on automatic conversion.
3. System Status: checks GD, WebP and AVIF support, folder permissions and the server type.

== Upgrade Notice ==

= 2.2.0 =
Fixes a bulk optimizer that could run forever, adds cache-safe picture-element delivery, background optimization, a Media Library column and WP-CLI commands, and makes the admin screens accessible. Recommended for all users.

= 2.1.0 =
Security, reliability, and delivery improvements. Tested with WordPress 7.1. Recommended update for all users.

== Changelog ==

= 2.2.0 =
* FIX: Bulk optimizer no longer gets stuck; failed images can be retried
* NEW: Cache-safe `<picture>` delivery and whole-page processing
* NEW: Media Library column, exclusions and maximum upload size
* NEW: Background optimization, WP-CLI and delivery self-test
* NEW: Keeps originals when WebP/AVIF would be larger
* IMPROVED: Accessible, faster admin screens
* IMPROVED: Clearer plugin description, FAQ and setup steps

= 2.1.0 =
* IMPROVED: Admin AJAX endpoints require administrator privileges; removed public nopriv handlers
* IMPROVED: Admin scripts and data load only on WebPressor admin screens
* FIX: Storage saved statistics stay accurate when bulk optimization is run more than once
* IMPROVED: Bulk optimizer processes pending JPEG/PNG items only for faster, clearer progress
* IMPROVED: Pending media count respects active WebP and AVIF target format settings
* FIX: Auto-convert on upload uses attachment metadata so registered sizes convert reliably
* NEW: Responsive image srcset support in HTML delivery mode
* NEW: `tbswebpressor_resolve_variant_url` filter for CDN and offload plugin integrations
* FIX: Uploads `.htaccess` RewriteBase path for server rewrite delivery
* IMPROVED: Plugin settings preserved on deactivation; options removed on uninstall only
* IMPROVED: GitHub release workflow builds admin JavaScript before packaging

= 2.0.2 =
* Version bump and minor improvements.

= 2.0.1 =
* Version bump and minor improvements.

= 2.0.0 =
* NEW: AVIF next-generation image format conversion support (PHP 8.1+ with GD AVIF)
* NEW: Redesigned three-tab React admin panel (Dashboard, Settings, System Status)
* NEW: Live console log showing real-time per-image conversion results and savings
* NEW: Storage Saved statistics card showing total bytes and percentage saved
* NEW: Server-level delivery method via .htaccess rewrite rules for Apache/LiteSpeed
* NEW: Nginx configuration snippet generator for manual server rule setup
* NEW: System Status tab with full server compatibility diagnostics
* NEW: AVIF quality slider and independent quality control per format
* NEW: Lossy vs Lossless compression mode selector
* NEW: Auto-convert on upload toggle in Settings
* NEW: Toast notification system for settings save and reset feedback
* IMPROVED: Batch size reduced from 10 to 5 for more responsive live updates
* IMPROVED: Batch processing now returns per-image filename, original size, and optimized size
* IMPROVED: Reset conversions now clears AVIF files, thumbnail formats, and storage statistics
* IMPROVED: Public image serving now prioritizes AVIF over WebP with correct fallback chain
* FIX: Storage saved statistics now correctly accumulate both original and thumbnail file sizes

= 1.0.1 =
* General Bug Fixes

= 1.0.0 =
* Initial release with WebP conversion, batch processing, and auto-convert on upload
