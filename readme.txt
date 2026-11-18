=== WebPressor - WebP & AVIF Image Converter, Optimizer & Compressor ===
Author: Techbysh
Author URI: https://techbysh.com
Contributors: techbysh
Donate link: https://techbysh.com
Tags: webp, avif, image optimization, image compression, page speed
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 2.1.0

Convert JPEG & PNG images to WebP and AVIF. Serve next-gen formats automatically. Reduce image sizes up to 50% for a faster, higher-scoring website.

== Description ==

**WebPressor** is the fastest and most powerful WordPress image optimizer plugin for converting your existing JPEG and PNG media to next-generation **WebP** and **AVIF** formats — the two formats recommended by Google for achieving perfect Core Web Vitals and PageSpeed Insights scores.

WebP images are typically **25–35% smaller** than JPEG at the same quality. AVIF goes even further — delivering files up to **50% smaller** than JPEG and **20% smaller** than WebP. By serving these formats automatically to supported browsers, your site loads dramatically faster with zero manual effort.

**Version 2.0.0 is a major release** introducing AVIF support, a redesigned dashboard, live optimization logs, server-level rewrite rules, system diagnostics, and much more.

---

### 🚀 Why Image Optimization Matters

Google's Core Web Vitals (LCP, FID, CLS) are directly impacted by image delivery performance. Large, unoptimized images are the #1 cause of slow page loads and poor PageSpeed Insights scores. WebPressor solves this automatically by:

- Replacing heavy JPEG/PNG files with lightweight next-gen formats
- Serving the smallest possible format each browser supports
- Reducing total page weight by up to 50% with zero quality loss
- Helping your site pass Google's **"Serve images in next-gen formats"** audit

---

### ✨ Core Features

🔹 **WebP Conversion** — Automatically convert all JPEG and PNG images to WebP format with GD library
🔹 **AVIF Conversion** *(New in 2.0)* — Generate AVIF images for the smallest possible file sizes (PHP 8.1+ with GD AVIF support required)
🔹 **Bulk Optimizer** — Process your entire Media Library in batches with a real-time live console log
🔹 **Auto-Convert on Upload** — Instantly convert new images as they are added to the Media Library
🔹 **Smart Browser Detection** — Serve AVIF to AVIF-capable browsers, WebP to WebP browsers, and original images to legacy browsers
🔹 **Three Delivery Methods** — HTML URL replacement, cache-safe `<picture>` elements, or server-level `.htaccess` rewrite rules
🔹 **Whole-Page Processing** — Optionally replace images anywhere on the page: theme headers, page builders and inline background images
🔹 **Reliable Bulk Optimizer** — Images that cannot be converted are listed with the reason and skipped, so a run always finishes; retry them with one click
🔹 **Background Processing** — Optimize the whole library on the server with WP-Cron and close the browser tab
🔹 **Never Larger** — Keeps the original when a WebP/AVIF file would be bigger than the source
🔹 **Media Library Column** — See the status and savings of every image and optimize, remove or exclude single images
🔹 **Exclusions** — Skip folders (e.g. `logos/`) or individual images
🔹 **Maximum Upload Size** — Scale down oversized uploads before conversion (uses WordPress' built-in big-image handling)
🔹 **Delivery Self-Test** — Checks that your server really delivers WebP/AVIF with the chosen method
🔹 **WP-CLI Commands** — `wp webpressor status`, `wp webpressor convert` and `wp webpressor reset`
🔹 **Automatic Cleanup** — Deleting an image also deletes its WebP/AVIF files; edited images are re-converted
🔹 **Adjustable Quality Sliders** — Independent WebP quality (0–100) and AVIF quality (0–100) controls
🔹 **Lossy & Lossless Modes** — Select compression mode for each format
🔹 **Storage Savings Dashboard** — See exactly how many megabytes and what percentage you have saved
🔹 **System Status Panel** — Checks GD extension, WebP/AVIF availability, folder write permissions, and server type
🔹 **Nginx Configuration Helper** — Displays a copyable Nginx server block for manual rewrite rule setup
🔹 **Non-Destructive** — Your original JPEG/PNG images are always preserved and never overwritten
🔹 **Compatible With All Themes** — Works with Elementor, Divi, Avada, GeneratePress, Astra, Kadence, OceanWP, and more
🔹 **WooCommerce Ready** — Optimizes product images, gallery thumbnails, and all image sizes automatically
🔹 **Multisite Compatible** — Works on WordPress Multisite installations
🔹 **Translation Ready** — Fully localized with `.pot` file included

---

### 📊 Performance Impact

| Format | Savings vs JPEG | Browser Support |
|--------|----------------|-----------------|
| WebP   | 25–35% smaller | 97%+ of browsers |
| AVIF   | 40–50% smaller | 80%+ of modern browsers |

---

### 🛠️ How It Works

1. **Install & activate** WebPressor from the WordPress dashboard
2. Go to the **WebPressor → Dashboard** in the admin menu
3. Click **Start Optimization** to bulk-convert your existing Media Library
4. Watch the **live console log** as images are processed in real-time
5. Monitor your **Storage Saved** — the dashboard shows total bytes and percentage saved
6. New uploads are automatically converted in the background (if enabled in Settings)

---

### ⚙️ Delivery Methods

**HTML Alteration (Default):**
WebPressor replaces image `src` URLs in page HTML on-the-fly. Safe, requires no server configuration, and works on any shared hosting.

**Server Rewrite Rules (.htaccess):**
For Apache and LiteSpeed servers, WebPressor writes redirect rules directly into `/wp-content/uploads/.htaccess`. Requests for `.jpg` and `.png` files are transparently redirected to their `.webp` or `.avif` equivalents — this also handles background CSS images, slider images, and images loaded via JavaScript.

---

### 🔗 Useful Links

🔹 [Documentation](https://tbsplugins.com/webpressor-docs/)
🔹 [Support Forum](https://wordpress.org/support/plugin/tbs-webpressor/#new-topic-0)
🔹 [GitHub Repository](https://github.com/TechbyshOrg/tbs-webpressor)

---

= External services =

Image conversion happens entirely on your own server; your images are never uploaded anywhere. The only external service is optional:

1. **Google Apps Script** (https://script.google.com) — Optional deactivation feedback, sent to the plugin author (Techbysh) and stored in a Google Sheet. It is sent **only** when an administrator deactivates the plugin and clicks **Submit & Deactivate** in the feedback popup; **Skip & Deactivate** and closing the popup send nothing. It contains the chosen reason, any text typed, the plugin, WordPress, and PHP versions, the site language, whether the site is multisite, and the active theme name. The site URL and admin email are included only if the administrator ticks the opt-in checkbox. Privacy: https://policies.google.com/privacy Terms: https://policies.google.com/terms

The Delivery Self-Test on the System Status tab only requests images from your own site.

---

== Installation ==

Installation of WebPressor can be done in two ways:

**Option 1 – Install from WordPress Dashboard (Recommended)**

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for **"WebPressor"**
3. Click **Install Now** and then **Activate**
4. Navigate to **WebPressor → Dashboard** and click **Start Optimization**

**Option 2 – Manual Upload**

1. Download the plugin `.zip` file
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the `.zip` file and click **Install Now**
4. Activate the plugin through the **Plugins** menu
5. Navigate to **WebPressor → Dashboard** and click **Start Optimization**

---

== Screenshots ==

1. **Dashboard — Bulk Optimizer & Storage Savings:** The main WebPressor dashboard showing the real-time optimization progress with storage saved statistics, library completion percentage, active output formats, and a live console log displaying per-image conversion results with file size savings.

2. **Settings — Format, Quality & Delivery Configuration:** The comprehensive settings panel where you can toggle WebP and AVIF output formats, control independent quality sliders for each format (0–100%), choose between HTML replacement or server-level `.htaccess` rewrite rules, and configure auto-conversion on upload.

3. **System Status — Server Compatibility Diagnostics:** The system status page displaying a full server compatibility checklist — including GD extension status, WebP support, AVIF support, upload directory write permissions, and detected web server software — along with a copyable Nginx configuration snippet for server rewrite rule setup.

---

== Frequently Asked Questions ==

= Does WebPressor replace or delete my original images? =

No. WebPressor **never modifies or deletes** your original JPEG and PNG files. It generates separate `.webp` and `.avif` versions alongside the originals and serves them conditionally based on what the visitor's browser supports. You can reset and delete all generated files at any time from the Dashboard.

= What image formats does WebPressor convert? =

WebPressor converts **JPEG** (`.jpg`, `.jpeg`) and **PNG** (`.png`) images. It converts the full-size original plus all registered WordPress thumbnail sizes (such as `thumbnail`, `medium`, `large`, `medium_large`, and custom theme sizes).

= Do I need to configure anything to get started? =

No! Simply install and activate the plugin, go to **WebPressor → Dashboard**, and click **Start Optimization**. The defaults are pre-configured for the best balance of quality and file size savings.

= What PHP version and server extensions are required? =

- **PHP 7.4+** is required for WebP support via GD
- **PHP 8.1+** with GD compiled with AVIF support is required for AVIF conversion
- You can check your server's capabilities on the **System Status** tab

= What is the difference between WebP and AVIF? =

**WebP** is Google's image format offering 25–35% smaller files than JPEG and is supported by 97%+ of browsers. **AVIF** is a newer AV1-based format offering 40–50% smaller files than JPEG and is supported by 80%+ of modern browsers. WebPressor automatically serves AVIF to browsers that support it, falls back to WebP for others, and falls back to the original image for legacy browsers.

= Will this plugin affect my site's SEO? =

Positively! Faster page loads from smaller images directly improve your Core Web Vitals scores (LCP in particular), which are a ranking factor in Google's algorithm. WebPressor also passes Google's PageSpeed Insights "Serve images in next-gen formats" audit.

= What is the difference between HTML Alteration and Server Rewrite Rules? =

**HTML Alteration** modifies image URLs inside page HTML content on-the-fly using PHP, based on what the visitor's browser supports. It works everywhere but, by default, only affects images in page content and widget text. Enable "Entire page" in Settings to process the whole page.

**<picture> Elements** wrap each image in a `<picture>` tag that lists the AVIF and WebP versions, so every browser picks the best format itself. Because the page HTML is the same for every visitor, it is the safest choice when a page cache or CDN is used.

**Server Rewrite Rules** add Apache/LiteSpeed `.htaccess` directives that intercept image requests at the server level before PHP even runs. This method also handles background CSS images, JavaScript-loaded images, and slider images — making it more thorough and slightly faster.

= Does the plugin work with WooCommerce product images? =

Yes! WebPressor converts all registered WordPress image sizes, including WooCommerce product thumbnails, gallery images, and the `woocommerce_thumbnail` size. All image sizes are optimized during bulk conversion.

= Does it work with page builders like Elementor, Divi, or Gutenberg? =

Yes. WebPressor is fully compatible with Elementor, Divi, Avada, Beaver Builder, the WordPress block editor (Gutenberg), and all other major page builders. When using the HTML delivery method, all images in content areas are replaced. For background images set through page builders, the Server Rewrite Rules delivery method is recommended.

= What happens to images already uploaded before I installed WebPressor? =

Use the **Bulk Optimizer** on the Dashboard to process your entire existing Media Library. The plugin will convert all previously uploaded images in batches and display real-time progress and savings in the live console log.

= How do I convert newly uploaded images automatically? =

Enable the **"Automatically convert images on upload"** option in the Settings tab. When enabled, any new image uploaded to the Media Library will be automatically converted in the background.

= Can I control the compression quality? =

Yes. The Settings tab provides independent quality sliders for WebP (recommended: 75–85) and AVIF (recommended: 60–70). You can also choose between **Lossy** mode (recommended for maximum size savings) and **Lossless** mode (100% quality, larger files).

= Can I undo the conversions and restore my original images? =

Yes. Click the **Reset Conversions** button on the Dashboard. This will delete all generated `.webp` and `.avif` files and reset the statistics, leaving your original images completely intact. To undo a single image, use **Remove WebP/AVIF** in the WebPressor column of the Media Library (list view).

= Will the plugin slow down my WordPress admin? =

No. Bulk conversion uses AJAX-based batch processing that runs in the background without blocking your admin interface. You can continue using WordPress normally while the optimizer runs. Use **Run in Background** to let the server continue even after you close the page.

= Why do some images show "Original kept (smaller)"? =

Some JPEGs are already highly compressed and some PNGs are very simple. Converting them can produce a larger file. WebPressor then keeps only the original, so visitors never download a bigger image. You can turn this safeguard off in Settings.

= What happens when an image cannot be converted? =

The bulk optimizer records the reason (for example a missing or corrupt file, or not enough server memory), skips the image, and continues. The Dashboard lists failed images; fix the cause and click **Retry Failed Images**, or exclude the image from the Media Library.

= How do I exclude images? =

Enter folders or file name parts in **Settings → Excluded Folders and Files** (one per line, e.g. `logos/`), or click **Exclude** in the WebPressor column of the Media Library list view.

= Which WP-CLI commands are available? =

`wp webpressor status` shows the library status, `wp webpressor convert [<id>...] [--limit=<number>] [--retry-failed]` converts pending (or specific) images, and `wp webpressor reset --yes` deletes all generated files.

= Which hooks can developers use? =

Filters: `tbswebpressor_resolve_variant_url`, `tbswebpressor_is_excluded`, `tbswebpressor_active_formats`, `tbswebpressor_batch_size`, `tbswebpressor_skip_image`, `tbswebpressor_picture_html`, `tbswebpressor_enable_output_buffer`, `tbswebpressor_background_time_limit`. Action: `tbswebpressor_attachment_processed`. Add the `tbswebpressor-skip` class to an image to leave it unchanged in `<picture>` mode.

= Is WebPressor compatible with WordPress Multisite? =

Yes, WebPressor is compatible with WordPress Multisite installations.

= Does WebPressor work with CDN or object storage? =

WebPressor converts images stored in the local filesystem (`/wp-content/uploads/`). If you use a CDN, the CDN will automatically serve the WebP/AVIF files once they have been generated and your CDN is configured to cache those file types. If images are stored externally (e.g., Amazon S3 via a cloud offload plugin), local conversion may not be possible — CDN-level WebP conversion would be needed instead.

= Does WebPressor work with caching plugins? =

Yes. WebPressor works with WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, Autoptimize, and other popular caching plugins. When a page cache is active, choose the **<picture> Elements** delivery method: HTML Alteration picks the format per visitor, so a cached page could otherwise show the wrong format to other browsers. After running the bulk optimizer, clear your cache to ensure visitors receive the new optimized images. The **Delivery Self-Test** on the System Status tab warns you when a page cache is detected.

= What happens if a visitor's browser does not support WebP or AVIF? =

WebPressor detects browser support using the HTTP `Accept` request header. Browsers that do not declare support for `image/avif` or `image/webp` will receive the original JPEG or PNG image automatically — there is zero impact on compatibility.

---

== Source Code & Build Instructions ==

This plugin's JavaScript/CSS assets are minified for production. The original React source code and build tools are available publicly on GitHub.

**Repository:**
https://github.com/TechbyshOrg/tbs-webpressor.git

**How to build:**
1. Clone the repo: `git clone https://github.com/TechbyshOrg/tbs-webpressor.git`
2. Enter the React admin folder: `cd react-admin`
3. Install dependencies: `npm install`
4. Build production assets: `npm run build`

---

== Upgrade Notice ==

= 2.1.0 =
Security, reliability, and delivery improvements. Tested with WordPress 7.1. Recommended update for all users.

---

== Requirements ==

- WordPress 6.0 or higher (tested up to 7.1)
- PHP 7.4 or higher (PHP 8.1+ required for AVIF conversion; PHP 8.3+ recommended by WordPress)
- GD library with WebP support enabled
- GD library with AVIF support (optional, for AVIF conversion)

---

== Changelog ==

= 2.2.0 =
* FIX: Bulk optimizer no longer runs endlessly when AVIF is selected on a server without AVIF support, or when images are missing or corrupt
* NEW: Failed images are recorded with the reason, listed on the Dashboard, and can be retried with one click
* NEW: Keep the original when the WebP/AVIF file would be larger (enabled by default)
* NEW: Cache-safe `<picture>` delivery method
* NEW: Optional whole-page processing for theme, page builder and inline background images
* NEW: WebPressor column in the Media Library with per-image optimize, remove and exclude actions
* NEW: Folder exclusions
* NEW: Background bulk optimization via WP-Cron
* NEW: WP-CLI commands: `wp webpressor status|convert|reset`
* NEW: Delivery self-test on the System Status tab
* NEW: Maximum image size for new uploads
* NEW: WebP/AVIF files are deleted together with their image, and edited images are re-converted
* IMPROVED: Admin interface is fully translatable and keyboard accessible
* IMPROVED: Admin screens meet WCAG 2.2 AA color contrast, show visible keyboard focus everywhere, respect reduced-motion settings and work in Windows High Contrast mode
* IMPROVED: Dashboard, Settings and System Status each have their own menu entry and link; switching tabs keeps the address bar, browser Back button and admin menu in sync
* IMPROVED: Settings are grouped into sections with a save bar that shows unsaved changes, a Discard button and inline validation
* IMPROVED: Dashboard explains its current state, shows a getting-started checklist and an empty state for new sites, and keeps "Delete all WebP/AVIF files" apart from everyday actions
* IMPROVED: Error messages stay visible until dismissed; Media Library "Exclude" asks for confirmation
* IMPROVED: Admin script is about 75% smaller because it uses the React library that ships with WordPress
* NEW: Optional deactivation feedback survey (nothing is sent unless you click "Submit & Deactivate"; see External services)
* IMPROVED: Settings are validated against allowed values

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