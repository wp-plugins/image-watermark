=== Image Watermark ===
Contributors: dfactory
Donate link: http://www.dfactory.eu/
Tags: image, images, picture, photo, watermark, watermarking, protection, image protection, image security, plugin
Requires at least: 3.5
Tested up to: 3.8.1
Stable tag: 1.3.1
License: MIT License
License URI: http://opensource.org/licenses/MIT

Image Watermark allows you to automatically watermark images uploaded to the WordPress Media Library and bulk watermark previously uploaded images.

== Description ==

[Image Watermark](http://www.dfactory.eu/plugins/image-watermark/) allows you to automatically watermark images uploaded to the WordPress Media Library and bulk watermark previously uploaded images.

For more information, check out plugin page at [dFactory](http://www.dfactory.eu/) or plugin [support forum](http://www.dfactory.eu/support/forum/image-watermark/).

= Features include: =

* Bulk watermark - Apply watermark option in Media Library actions
* Watermark images already uploaded to Media Library
* Choose the position of watermark image
* Upload custom watermark image
* Watermark image preview
* Set watermark offset
* Select post types where watermark will be aplied to images or select adding watermark during any image upload
* Select from 3 methods of aplying watermark size: original, custom or scaled
* Set watermark transparency / opacity
* Select image format (baseline or progressive)
* Set image quality
* Protect your images from copying via drag&drop
* Disable right mouse click on images
* Disable image protection for logged-in users
* .pot file for translations included

= Translations: =

* Arabic - by Hassan Hisham
* German - by Matthias Siebler
* Polish - by Bartosz Arendt


== Installation ==

1. Install Image Watermark either via the WordPress.org plugin directory, or by uploading the files to your server
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the Watermark menu in Settings and set your watermarking options.
4. Enable watermark to apply watermark to uploaded images or go to Media Library to apply watermark to previously uploaded images

== Frequently Asked Questions ==

No questions yet.

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Changelog ==

= 1.3.1 =
* Fix: Option to disable right click on images not working 

= 1.3.0 =
* Tweak: Manual watermarking now works even if selected post types are selected
* Tweak: UI improvements for WP 3.8
* Fix: Image protection options not saving properly

= 1.2.1 =
* New: German translation, thanks to Matthias Siebler

= 1.2.0 =
* New: Frontend watermarking option (for front-end upload plugins and custom front-end upload code)
* New: Introducing iw_watermark_display filter
* New: Option to delete all plugin data on deactivation
* Tweak: Rewritten watermark application method
* Tweak: UI enhancements for settings page

= 1.1.4 =
* New: Arabic translation, thanks to Hassan Hisham

= 1.1.3 =
* New: Introducing API hooks: iw_before_apply_watermark, iw_after_apply_watermark, iw_watermark_options
* Fix: Wrong watermark watermark path
* Fix: Final fix (hopefully) for getimagesize() error

= 1.1.2 =
* New: Image quality option
* New: Image format selection (progressive or baseline)
* Fix: Error when getimagesize() is not available on some servers
* Tweak: Files & class naming conventions

= 1.1.1 =
* New: Added option to enable or disable manual watermarking in Media Library
* Fix: Apply watermark option not visible in Media Library actions
* Fix: Warning on full size images

= 1.1.0 =
* New: Bulk watermark - Apply watermark in Media Library actions
* New: Watermark images already uploaded to Media Library

= 1.0.3 =
* Fix: Error during upload of file types other than images (png, jpg)
* Fix: Limit watermark file types to png, gif, jpg
* Tweak: Validation for watermark size and transparency values
* Tweak: Remove unnecessary functions
* Tweak: Code cleanup
* Tweak: Added more code comments
* Tweak: Small css changes

= 1.0.2 =
* New: Add watermark to custom images sizes registered in theme
* Tweak: Admin notices on settings page if no watermark image selected
* Tweak: JavaScript enquequing on front-end
* Tweak: General code cleanup
* Tweak: Changed label for enabling image protection for logged-in users

= 1.0.1 =
* Fix: Using image ID instead of image URL during image upload

= 1.0.0 =
Initial release

== Upgrade Notice ==

= 1.3.1 =
* Fix: Option to disable right click on images not working