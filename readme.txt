=== International Telephone Box ===
Contributors: abanob0adel
Tags: telephone, international, phone, validation, formatting
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin for handling international telephone numbers with validation and formatting.

== Description ==

International Telephone Box is a WordPress plugin that provides a robust solution for handling international telephone numbers in your WordPress website. It includes:

* International telephone number validation
* Country code selection
* Phone number formatting
* Mobile-friendly interface
* Customizable appearance

== Installation ==

1. Upload the `international-telephone-box` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure the plugin settings under Settings > International Telephone Box.

== Frequently Asked Questions ==

= Does this plugin support all countries? =
Yes, the plugin supports all international telephone number formats.

= Can I customize the appearance? =
Yes, you can customize the appearance through the plugin settings or by using custom CSS.

== Included Libraries ==

This plugin includes the following libraries:

* intl-tel-input (v29.5.12) - MIT License
  - Source: https://github.com/jackocnr/intl-tel-input
  - Unminified source: https://github.com/jackocnr/intl-tel-input/tree/v29.5.12/packages/core/src
  - Copyright (c) 2014-2016 Jack O'Connor
  - Bundled files: public/js/intlTelInputWithUtils.min.js, public/css/intlTelInput.css, public/img/flags.webp, public/img/flags-2x.webp (renamed from flags@2x.webp)

The plugin no longer requires jQuery. If your site already loads jQuery (as Elementor does), it is used only to listen for Elementor popups opening.

== External Services ==

This plugin connects to the following external service:

* **IPAPI** - Used to automatically detect the user's country based on IP address to preselect the country code field.
  - What is sent: The plugin sends a request to `https://ipapi.co/json` to retrieve the user's country and location data (IP-based).
  - No personally identifiable data is stored or logged.
  - Terms: https://ipapi.co/terms/
  - Privacy: https://ipapi.co/privacy/

== Changelog ==

= 1.0.0 =
* Initial release

= 1.1.0 =
* Fixed bugs

= 1.1.1 =
* Removed all external JS/CSS dependencies

= 1.1.2 =
* Improved design and layout.

= 1.1.3 =
* Fix global issuse.

= 1.1.4 =
* Fix showing popups.

= 1.1.5 =
* Remove Israel from countries.

= 1.2.0 =
* Updated the bundled intl-tel-input library from v17.0.19 to v29.5.12 (newer phone number metadata, accessibility and country search improvements).
* Adapted the plugin to the new library API. Validation, country selection, formatting, geolocation and the `full_phone` hidden field keep working as before.
* jQuery is no longer required.
* Flags now use WebP images and display sharply on high-resolution screens.
* Fixed the `[intelebox_input]` shortcode field styles, which no longer matched the field's class names.
* Fixed theme button styles leaking into the country selector.
* Removed an unused legacy file.
* Tested with WordPress 7.1.

== Upgrade Notice ==

= 1.2.0 =
Updates the bundled intl-tel-input library to v29.5.12. If you added custom CSS for the phone field, check it: the library renamed several CSS classes (for example `.iti__selected-flag` is now `.iti__selected-country`).

= 1.1.1 =
Removed all externally loaded assets. Please update to avoid remote dependency issues.