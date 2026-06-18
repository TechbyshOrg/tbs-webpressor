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
Tested up to: 7.0
Stable tag: 2.0.2

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
🔹 **Two Delivery Methods** — Choose between HTML URL replacement or server-level `.htaccess` rewrite rules
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

**HTML Alteration** modifies image URLs inside page HTML content on-the-fly using PHP. It works everywhere but only affects images in page content and widget text.

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

Yes. Click the **Reset Conversions** button on the Dashboard. This will delete all generated `.webp` and `.avif` files and reset the statistics, leaving your original images completely intact.

= Will the plugin slow down my WordPress admin? =

No. Bulk conversion uses AJAX-based batch processing that runs in the background without blocking your admin interface. You can continue using WordPress normally while the optimizer runs.

= Is WebPressor compatible with WordPress Multisite? =

Yes, WebPressor is compatible with WordPress Multisite installations.

= Does WebPressor work with CDN or object storage? =

WebPressor converts images stored in the local filesystem (`/wp-content/uploads/`). If you use a CDN, the CDN will automatically serve the WebP/AVIF files once they have been generated and your CDN is configured to cache those file types. If images are stored externally (e.g., Amazon S3 via a cloud offload plugin), local conversion may not be possible — CDN-level WebP conversion would be needed instead.

= Does WebPressor work with caching plugins? =

Yes. WebPressor is compatible with WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, Autoptimize, and other popular caching plugins. After running the bulk optimizer, clear your cache to ensure visitors receive the new optimized images.

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

= 2.0.2 =
Version bump and minor improvements.

---

== Requirements ==

- WordPress 6.0 or higher
- PHP 7.4 or higher (PHP 8.1+ recommended for AVIF support)
- GD library with WebP support enabled
- GD library with AVIF support (optional, for AVIF conversion)

---

== Changelog ==

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