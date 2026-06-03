=== WebPressor - WebP Image Converter & Optimizer ===
Author: Techbysh
Author URI: https://techbysh.com
Contributors: techbysh
Donate link: https://techbysh.com
Tags: webp, image, compression, optimization, performance
License: GPLv2 or later
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 7.0
Stable tag: 1.0.1

Convert images to WebP format and serve them to compatible browsers for faster loading websites with reduced file sizes.

== Description ==

WebPressor automatically converts your JPEG and PNG images to the WebP format, which provides superior lossless and lossy compression for images on the web. WebP images are typically 25-35% smaller than comparable JPEG images at equivalent visual quality, helping your website load faster.

The plugin will automatically detect browser support and serve WebP images only to compatible browsers, ensuring backward compatibility with older browsers.

== HOW TO MANAGE SETTINGS? ==

To manage settings in WebPressor, download and activate the WordPress plugin.
Go to the WebPressor menu in the left menu bar of WordPress, where you can manage the settings.
Click "Start Conversion" to convert your existing images and configure quality settings.

== WebPressor Basic Features ==

Here are the features of WebPressor:

🔹 Automatic conversion of JPEG and PNG images to WebP format
🔹 Batch processing of existing media library images
🔹 Automatic detection of browser WebP support
🔹 Compatible with all WordPress themes and plugins
🔹 Easy to use dashboard for conversion management
🔹 Configurable WebP quality settings

== Why Choose WebPressor? ==

WebPressor helps you improve site performance by serving optimized WebP images that are significantly smaller than traditional image formats. This leads to faster page loads and better user experience, without sacrificing image quality.

== OTHER USEFUL LINKS ==

🔹 [Documentation](https://tbsplugins.com/webpressor-docs/)
🔹 [Support Ticket](https://wordpress.org/support/plugin/tbs-webpressor/#new-topic-0)

== INSTALLATION ==

Installation of "WebPressor" can be done either by searching for "WebPressor" via the "Plugins > Add New" screen in your WordPress dashboard, or by using the following steps:

1. Upload the `tbs-webpressor` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the WebPressor menu in your WordPress admin area
4. Click "Start Conversion" to convert your existing images

== Screenshots ==

1. WebPressor Dashboard
2. Conversion Settings
3. Statistics and Performance Improvements

== Source Code & Build Instructions ==

This plugin's JavaScript/CSS assets are minified for production. The original source code and build tools are available publicly on GitHub.

**Repository:**  
https://github.com/TechbyshOrg/tbs-webpressor.git

**How to build:**  
1. Clone the repo: `git clone https://github.com/TechbyshOrg/tbs-webpressor.git`  
2. Install dependencies: `yarn install`  
3. Build assets: `yarn build`  

== Frequently Asked Questions ==

= Does this plugin replace original images? =

No, this plugin keeps your original images intact. It creates WebP versions alongside your original files and serves them only to browsers that support WebP.

= Will this plugin slow down my site? =

No, quite the opposite! WebP images are significantly smaller than JPEG and PNG equivalents, which should improve your page load times.

= What happens if a browser doesn't support WebP? =

For browsers that don't support WebP, the plugin will automatically serve the original JPEG or PNG images.

= Can I disable WebP conversion for specific images? =

Currently, the plugin converts all JPG, JPEG, and PNG images. A future update may include selective conversion options.

== Upgrade Notice ==

= 1.0.1 =
* General Bug Fixes

== Requirements ==

- WordPress 5.0 or higher
- PHP 7.0 or higher
- GD library with WebP support

== Changelog ==
= 1.0.1 =
* General Bug Fixes

= 1.0.0 =
* Initial release