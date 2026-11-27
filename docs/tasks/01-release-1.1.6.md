> **Superseded** by `02-release-1.2.0.md`. 1.1.6 was never released; its fixes ship in 1.2.0.

# Task 01: Release 1.1.6 (bug-fix patch)

Branch: `release/1.1.6` · Implementer: opencode · Reviewer: Claude

Small, backward-compatible fixes to the published 1.1.5. **No new features, no settings page, no library upgrade.**

## Changes

### 1. The shortcode input is never submitted
`includes/class-intelebox-main.php` → `render_tel_box()`. The `<input>` has no `name`.
- Add a `name` shortcode attribute, default `phone`. Output `name="<?php echo esc_attr( $atts['name'] ); ?>"`.
- Add an `id` attribute, default empty. Output `id` only when it is non-empty.
- Add a `required` attribute. If it is `"true"`/`"1"`/`"yes"`, output the `required` HTML attribute.
- Make the default placeholder translatable: `__( 'Enter phone number', 'international-telephone-box' )`.
- Keep the `placeholder` and `class` attributes as they are.

### 2. The styles never apply
`public/css/international-tel-box.css` targets `.itb-container` / `.itb-phone-input`, but the markup uses `.intelebox-container` / `.intelebox-phone-input`.
- Rename the `.itb-*` selectors to `.intelebox-*`.
- Delete the unused `.itb-country-select` rule and the duplicate `padding-left: 80px` block. intl-tel-input already handles dial-code padding through `.iti--separate-dial-code input[type="tel"]`.
- Change `max-width: 300px` on the container to `100%`, so the field fits form builders.

### 3. Remote `utils.js`
`public/js/international-tel-box.js` sets `utilsScript` to a cdnjs URL. The local `public/js/utils.js` is already enqueued.
- Pass the local URL from PHP: in `enqueue_public_scripts()`, add
  `wp_localize_script( 'international-telephone-box', 'inteleboxConfig', array( 'utilsUrl' => INTELEBOX_PLUGIN_URL . 'public/js/utils.js' ) );`
  (use `esc_url_raw` on the URL).
- In the JS, use `utilsScript: (window.inteleboxConfig && window.inteleboxConfig.utilsUrl) || ""`.
- Make the `international-telephone-box` script depend on `intl-tel-input` (it calls `window.intlTelInput`) as well as `jquery`.
- The plugin must make no requests to cdnjs.cloudflare.com. Search the code to confirm.

### 4. Guard against a missing library and a double init
In `initializeInternationalTelephoneBox`, return early if `typeof window.intlTelInput !== "function"`.
Inside `initializeInsideIframes`, skip iframes whose document has no `window.intlTelInput`. Initializing into a foreign document without the library loaded throws an error.

### 5. Version bump to 1.1.6
- `international-telephone-box.php`: `Version: 1.1.6`, `INTELEBOX_VERSION` = `'1.1.6'`, and add the header `Requires PHP: 7.4`.

### 6. Rewrite `readme.txt` (keep the WordPress.org format)
- Header: `Tested up to: 7.1`, `Requires PHP: 7.4`, `Stable tag: 1.1.6`. Keep `Contributors: abanob0adel`.
- Short description: one line, under 150 characters.
- Description: list the real features. Document the `[intelebox_input]` shortcode and its attributes (`name`, `id`, `placeholder`, `class`, `required`). Say that the field automatically enhances every `input[type="tel"]` on the site. Say that it works inside Elementor popups.
- State plainly in the Description: "Israel (+972) is not included in the country list, and numbers with that dial code are rejected."
- Installation: remove step 3 (there is no settings page). Replace it with "Add `[intelebox_input]` to any page or form, or use any form plugin's phone/tel field."
- FAQ: remove the "plugin settings" claim. Add a Q&A on how to get the full international number. The input value becomes E.164 on blur, and a hidden `full_phone` field is added.
- Keep the `== Included Libraries ==` and `== External Services ==` sections (ipapi.co is still used).
- `== Changelog ==`: newest first, one entry per version 1.1.6 → 1.0.0, deduplicated. 1.1.6 entry:
  * Fix: shortcode field now has a `name`, so the number is submitted with the form.
  * Fix: plugin styles now apply to the shortcode field.
  * Fix: `utils.js` now loads from the plugin instead of a CDN.
  * New: `name`, `id` and `required` shortcode attributes.
  * Tested with WordPress 7.1.
- `== Upgrade Notice ==`: newest first, add `= 1.1.6 =` with one line. Remove the misplaced changelog lines that currently sit under Upgrade Notice.

## Out of scope
Settings page, intl-tel-input upgrade, removing jQuery, MutationObserver changes, integrations. These belong to 2.0.0.

## Acceptance criteria
- [ ] `[intelebox_input]` outputs `<input type="tel" name="phone" ...>`. `[intelebox_input name="mobile" id="m" required="true"]` outputs those attributes, escaped.
- [ ] `grep -rn "cdnjs\|itb-" includes public/js/international-tel-box.js public/css/international-tel-box.css` returns nothing.
- [ ] The versions in the plugin header, `INTELEBOX_VERSION`, and `Stable tag` are all `1.1.6`.
- [ ] `readme.txt` has no reference to a settings page, and has a clean changelog and upgrade notice.
- [ ] `php -l` and `node --check` pass.
- [ ] The vendored files (`intlTelInput.min.js`, `utils.js`, `intlTelInput.css`, `flags*.png`) are unchanged.
