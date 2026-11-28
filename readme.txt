=== International Telephone Box ===
Contributors: abanob0adel
Tags: telephone, international, phone, validation, formatting
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin for handling international telephone numbers with validation and formatting.

== Description ==

International Telephone Box is a WordPress plugin that provides a robust solution for handling international telephone numbers in your WordPress website. The plugin automatically enhances every `input[type="tel"]` on the site, including fields added dynamically by form builders and fields in Elementor popups. It includes:

* Separate dial code
* Validation on blur and on submit
* E.164 formatting on blur
* A hidden `full_phone` field
* IP-based country preselection
* No jQuery requirement
* Bundled assets only (no remote CDN JavaScript, CSS or fonts)
* Mobile-friendly interface
* Customizable appearance with custom CSS
* A settings page to choose which countries are offered

= Settings =

Go to **Settings → International Telephone Box** to configure the phone field. You can choose which countries are offered (all, only selected, or all except selected), the default and fallback country, up to five preferred countries shown at the top of the dropdown, and whether the visitor's country is detected with ipapi.co. The page includes a live preview. With no settings saved, the field behaves exactly as it did before.

= Shortcode =

Use `[intelebox_input]` to add a standalone phone field to any page or post. Example:

`[intelebox_input name="mobile" id="mobile" placeholder="Enter phone number" class="my-phone-field" required="true"]`

Attributes:

* `name` - the `name` attribute of the input, used when the form is submitted. Default `phone`.
* `id` - the `id` attribute of the input. Default empty; no `id` attribute is output.
* `placeholder` - the placeholder text. Default `Enter phone number`.
* `class` - extra CSS class(es) added to the container. Default empty.
* `required` - output the HTML `required` attribute when set to `true`, `1` or `yes` (case-insensitive). Default off.

== Installation ==

1. Upload the `international-telephone-box` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Add `[intelebox_input]` to any page, or use any form plugin's phone (tel) field.

== Frequently Asked Questions ==

= Can I customize the appearance? =

Yes. The **Settings → International Telephone Box** page lets you choose which countries are offered and how the default country is picked, and you can add custom CSS for styling.

= Can I limit which countries are available? =

Yes. On **Settings → International Telephone Box**, choose "Only selected" to offer just a chosen set of countries, or "All except selected" to remove specific countries. You can also set a default and fallback country and up to five preferred countries shown at the top.

= How do I get the full international number? =

On blur, the field value becomes an E.164 number (for example `+14155552671`), and the plugin adds a hidden `full_phone` field containing the full international number.

== Included Libraries ==

This plugin includes the following libraries:

* intl-tel-input (v29.5.12) - MIT License
  - Source: https://github.com/jackocnr/intl-tel-input
  - Unminified source: https://github.com/jackocnr/intl-tel-input/tree/v29.5.12/packages/core/src
  - Copyright (c) 2014-2016 Jack O'Connor
  - Bundled files: public/js/intlTelInputWithUtils.min.js, public/css/intlTelInput.css, public/img/flags.webp, public/img/flags-2x.webp (renamed from flags@2x.webp)

* Schibsted Grotesk - SIL Open Font License 1.1 (OFL 1.1)
  - Bundled file: public/admin/fonts/schibsted-grotesk-latin-wght-normal.woff2
  - Licence: public/admin/fonts/OFL-schibsted-grotesk.txt

* JetBrains Mono - SIL Open Font License 1.1 (OFL 1.1)
  - Bundled file: public/admin/fonts/jetbrains-mono-latin-wght-normal.woff2
  - Licence: public/admin/fonts/OFL-jetbrains-mono.txt

The plugin no longer requires jQuery. If your site already loads jQuery (as Elementor does), it is used only to listen for Elementor popups opening.

== External Services ==

This plugin connects to the following external service:

* **IPAPI** - Used to automatically detect the user's country based on IP address to preselect the country code field.
  - What is sent: The plugin sends a request to `https://ipapi.co/json` to retrieve the user's country and location data (IP-based).
  - No personally identifiable data is stored or logged.
  - Terms: https://ipapi.co/terms/
  - Privacy: https://ipapi.co/privacy/
  - This lookup can be turned off in **Settings → International Telephone Box**; when it is off, no request to ipapi.co is made and the fallback country is used.

== Screenshots ==

1. Settings page: choose which countries the phone field offers.

== Changelog ==

= 1.3.0 =
* New: **Settings → International Telephone Box** settings page. Choose which countries are offered (all / only selected / all except selected), the default and fallback country, up to five preferred countries, and whether to detect the visitor's country with ipapi.co. Includes a live preview, follows the admin design system, works without JavaScript, and resets to defaults. With no settings saved, the field behaves exactly as in 1.2.0.
* New: `Intelebox_Settings` class; a single `intelebox_settings` option sanitized on save and re-validated on read.
* New: `uninstall.php` removes the `intelebox_settings` option on uninstall (single site and multisite).
* Bundled the Schibsted Grotesk and JetBrains Mono fonts (SIL OFL 1.1) for the settings page.
* The IP-based country detection can be turned off in settings.

= 1.2.0 =
* Fixed: the `[intelebox_input]` field now has a `name`, so the number is submitted with the form.
* New: `name`, `id` and `required` shortcode attributes.
* Updated the bundled intl-tel-input library from v17.0.19 to v29.5.12 (newer phone number metadata, accessibility and country search improvements).
* Adapted the plugin to the new library API. Validation, country selection, formatting, geolocation and the `full_phone` hidden field keep working as before.
* jQuery is no longer required.
* Flags now use WebP images and display sharply on high-resolution screens.
* Fixed the `[intelebox_input]` shortcode field styles, which no longer matched the field's class names.
* Fixed theme button styles leaking into the country selector.
* Removed an unused legacy file.
* Tested with WordPress 7.1.

= 1.1.5 =
* Updated the country list.

= 1.1.4 =
* Fix showing popups.

= 1.1.3 =
* Fixed a global issue.

= 1.1.2 =
* Improved design and layout.

= 1.1.1 =
* Removed all external JS/CSS dependencies

= 1.1.0 =
* Fixed bugs

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.3.0 =
Adds a Settings → International Telephone Box page to choose which countries are offered and how the default country is picked. With no settings saved, behaviour is unchanged from 1.2.0. IP-based country detection can now be turned off.

= 1.2.0 =
Updates the bundled intl-tel-input library to v29.5.12. If you added custom CSS for the phone field, check it: the library renamed several CSS classes (for example `.iti__selected-flag` is now `.iti__selected-country`). Shortcode fields are now submitted as `phone` unless a `name` is given.

= 1.1.1 =
Removed all externally loaded assets. Please update to avoid remote dependency issues.
