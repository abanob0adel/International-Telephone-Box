# Task 02: Finish release 1.2.0

Branch: `release/1.2.0` (built on PR #1, which upgrades intl-tel-input to v29.5.12) · Implementer: opencode · Reviewer: Claude

PR #1 already did the library upgrade and tested it. This task carries over the fixes from the
superseded task 01 (1.1.6) that PR #1 does not have. **Do not change the intl-tel-input options,
the validation logic, or any vendored file.**

## Changes

### 1. Shortcode: the input is never submitted
`includes/class-intelebox-main.php` → `render_tel_box()`. The `<input>` has no `name`.
- Add a `name` attribute, default `phone`. Output `name="..."`, escaped with `esc_attr`.
- Add an `id` attribute, default empty. Output `id="..."` only when it is non-empty.
- Add a `required` attribute. When it is `true`/`1`/`yes` (case-insensitive), output the `required` HTML attribute.
- Make the default placeholder translatable: `__( 'Enter phone number', 'international-telephone-box' )`.
- `placeholder` and `class` keep working exactly as before.

You can reuse the implementation from commit `637d5e9` (branch `release/1.1.6`), file `includes/class-intelebox-main.php`, function `render_tel_box()`. Copy that function only. That commit is built on v17 and must not be merged.

### 2. Plugin header
`international-telephone-box.php`: add `Requires PHP: 7.4` under `Requires at least`. The version stays `1.2.0`.

### 3. readme.txt
Keep the WordPress.org format. Keep the sections `== Included Libraries ==` and `== External Services ==` as they are.
- Header: add `Requires PHP: 7.4`. Keep `Tested up to: 7.1` and `Stable tag: 1.2.0`.
- Description: list the real features. The plugin automatically enhances every `input[type="tel"]` on the site, including fields added dynamically by form builders and fields in Elementor popups. It adds a separate dial code, validation on blur and on submit, E.164 formatting on blur, a hidden `full_phone` field, IP-based country preselection, no jQuery requirement, and bundled assets only.
- Document the `[intelebox_input]` shortcode and its attributes: `name`, `id`, `placeholder`, `class`, `required`. Give one example.
- State plainly in the Description: "Israel (+972) is not included in the country list, and numbers with that dial code are rejected."
- Installation step 3: replace the settings-page line with "Add `[intelebox_input]` to any page, or use any form plugin's phone (tel) field." There is no settings page.
- FAQ:
  - "All countries?": answer truthfully, including the Israel exclusion.
  - "Customize appearance": say custom CSS only. Remove the "plugin settings" claim.
  - Add "How do I get the full international number?": the field value becomes E.164 on blur, and a hidden `full_phone` field is added.
- `== Changelog ==`: **newest first**, with 1.2.0 at the top and 1.0.0 at the bottom. Fix wording, e.g. "Fix global issuse" → "Fixed a global issue." Keep all the existing 1.2.0 bullets, and add:
  * Fixed: the `[intelebox_input]` field now has a `name`, so the number is submitted with the form.
  * New: `name`, `id` and `required` shortcode attributes.
- `== Upgrade Notice ==`: keep the existing 1.2.0 and 1.1.1 notices, newest first. Add to the 1.2.0 notice that shortcode fields are now submitted as `phone` unless a `name` is given.

## Out of scope
Settings page, MutationObserver changes, integrations, and any change to `public/js/*` or `public/css/*`.

## Acceptance criteria
- [ ] `[intelebox_input]` outputs `<input type="tel" name="phone" ...>`. `[intelebox_input name="mobile" id="m" required="true"]` outputs `name="mobile"`, `id="m"` and `required`, escaped. Without `id`, no `id` attribute is output.
- [ ] `readme.txt` does not mention a settings page, has a newest-first changelog, and includes `Requires PHP: 7.4` plus the Israel statement.
- [ ] The plugin header has `Requires PHP: 7.4`. `Version`, `INTELEBOX_VERSION` and `Stable tag` are all `1.2.0`.
- [ ] `git diff origin/update/intl-tel-input-29 -- public/` is empty.
- [ ] `php -l` passes on all PHP files.
