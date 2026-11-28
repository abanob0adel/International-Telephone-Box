# Task 03: Settings page for countries (release 1.3.0)

Branch: `release/1.3.0` · Implementer: opencode · Reviewer: Claude

Add **Settings → International Telephone Box**, where the site owner chooses which countries the
phone field offers. It is a new, backward-compatible feature, so the version is a minor bump: 1.3.0.

**Read `docs/DESIGN-SYSTEM.md` before writing any CSS or markup.** The page must follow it exactly.
Reference screenshots are in `docs/design/`.

## Already done for you (do not regenerate)

- `includes/data/countries.php` returns `array( 'eg' => array( 'name' => 'Egypt', 'dial' => '20' ), ... )`:
  243 countries, sorted by English name, generated from the bundled intl-tel-input. Use it as the single
  source of truth for valid ISO codes and for the server-rendered list.
- `public/admin/fonts/`: Schibsted Grotesk and JetBrains Mono woff2, plus OFL licences.

## 1. The setting

One option, `intelebox_settings`, an array registered with `register_setting( 'intelebox_settings_group', 'intelebox_settings', ... )`
with `sanitize_callback` and `default`. Keys and defaults:

| Key | Type | Default | Meaning |
|---|---|---|---|
| `country_mode` | `all` \| `include` \| `exclude` | `all` | Offer all countries / only the selected ones / all except the selected ones |
| `countries` | string[] (ISO2, lower case) | `[]` | The countries used by `include` or `exclude` |
| `preferred_countries` | string[] | `['eg']` | Shown at the top of the dropdown, in this order (`countryOrder`) |
| `initial_country` | `auto` \| ISO2 | `auto` | `auto` = detect from the visitor's IP; otherwise always start on this country |
| `fallback_country` | ISO2 | `eg` | Used when detection is off, fails, or returns a country that isn't offered |
| `geo_lookup` | bool | `true` | Whether to call ipapi.co at all |

With the defaults, the front end must behave **exactly** like 1.2.0.

### Sanitizing (server side, in the sanitize callback)
- Keep only codes that exist in `countries.php`. Lower-case them, de-duplicate them, and drop anything else silently.
- `country_mode` not in the allowed set → `all`. `geo_lookup` → bool.
- `include` mode with an empty `countries` list is invalid. Fall back to `all` and add a settings error
  (`add_settings_error`) that says to select at least one country.
- Make the set of offered countries consistent:
  - Drop preferred countries that aren't offered.
  - If `fallback_country` isn't offered, use the first offered preferred country, or else the first offered
    country alphabetically.
  - Apply the same rule to `initial_country` when it isn't `auto`.
- `il` never appears anywhere. `countries.php` already omits it; it isn't a valid code. Do not mention it in any
  UI text, help text, comment shown to users, or the readme.

### Reading the setting
Add a small class `International_Telephone_Box\Intelebox_Settings` in `includes/class-intelebox-settings.php`
with `public static function get(): array`. It merges the stored option over the defaults and
re-applies the consistency rules, so a bad stored value can't break the front end. It also has
`public static function countries(): array`, which loads `countries.php` once.

## 2. Front end

In `Intelebox_Main::enqueue_public_scripts()`, pass the config with `wp_localize_script( 'international-telephone-box', 'inteleboxConfig', ... )`:

```
onlyCountries:    include mode → countries; otherwise []
excludeCountries: exclude mode → countries; otherwise []   (the JS always adds "il")
countryOrder:     preferred_countries
initialCountry:   initial_country ("auto" or ISO2)
fallbackCountry:  fallback_country
geoLookup:        geo_lookup (bool)
```

In `public/js/international-tel-box.js`:
- Read `window.inteleboxConfig`, with defaults equal to today's hard-coded values. Replace `FALLBACK_COUNTRY`,
  the `countryOrder` literal, and the `EXCLUDED_ISO` list with config values. `EXCLUDED_ISO` keeps `"il"` and
  adds `excludeCountries`. `BLOCKED_DIALS` stays.
- Pass `onlyCountries` to intl-tel-input only when it is non-empty.
- `initialCountry`:
  - If it is an ISO2 code, pass it as `initialCountry` and **do not** pass `initialCountryLookup`.
  - If it is `auto` and `geoLookup` is true, keep the existing ipapi.co lookup. The lookup's result falls back to
    `fallbackCountry` when the result is empty, excluded, or (in include mode) not in `onlyCountries`.
  - If it is `auto` and `geoLookup` is false, pass `initialCountry: fallbackCountry`. **No request to ipapi.co is made.**
- Don't change validation, blur/E.164, `hiddenInputs`, popups, iframes, or the observer.

## 3. Admin page

New class `International_Telephone_Box\Intelebox_Admin` in `includes/class-intelebox-admin.php`. Load it from the
main class only when `is_admin()`.

- `add_options_page()`, menu title "International Tel Box", slug `intelebox-settings`, capability `manage_options`.
- An action link "Settings" on the Plugins screen (`plugin_action_links_{basename}`).
- Enqueue `public/admin/css/admin.css`, `public/admin/js/admin.js`, and the bundled `intl-tel-input` CSS and JS
  **only** on this page's hook suffix. Pass `INTELEBOX_VERSION` as the asset version.
- The form posts to `options.php` and uses `settings_fields( 'intelebox_settings_group' )`. Show
  `settings_errors()` inside our root, styled as the design system's notice.
- Escape everything. Make every string translatable with the `international-telephone-box` text domain.

### Markup (server-rendered, works without JS)
Root: `<div class="wrap intelebox-admin">`. Keep `wrap` so core notices still position correctly.

1. **Header.** Eyebrow "International Telephone Box · Settings", then h1 "Phone field <em>countries</em>",
   then a one-line intro.
2. **Section "Countries".**
   - A segmented control (3 radios) for `country_mode`: "All countries", "Only selected", "All except selected".
   - A country picker:
     - A search input labelled "Search countries".
     - A row of chips showing the selected countries.
     - A counter in mono, e.g. "12 selected".
     - "Select all visible" and "Clear" text buttons.
     - A scrollable list (max-height 360px) of rows. Each row is
       `<label><input type="checkbox" name="intelebox_settings[countries][]" value="eg"> flag · Egypt · +20</label>`,
       rendered from `countries.php`.
   - The picker is hidden (`hidden` attribute, toggled by JS) when the mode is "All countries". Without JS, it
     simply stays visible.
   - Help text changes with the mode, e.g. "Visitors can only pick these countries." / "These countries are
     removed from the list."
3. **Section "Default country".**
   - A select for `initial_country`: the first option is "Detect from visitor's location", then all countries.
   - A select for `fallback_country`, with help text: "Used when detection is off or doesn't find an allowed country."
   - A preferred countries picker for `preferred_countries[]`. Use the same chip and search pattern, but with
     at most 5 selections. Order is kept in selection order; a hidden input per chip preserves it.
4. **Section "Privacy".** A toggle for `geo_lookup`: "Detect the visitor's country with ipapi.co". The help text
   explains that this sends the visitor's IP address to ipapi.co, links to its privacy policy, and says that
   when it's off the fallback country is used.
5. **Section "Preview".** A live `<input type="tel">` initialised with intl-tel-input using the
   *current, unsaved* form values. It re-initialises (destroy + init) whenever a setting changes. It never calls
   ipapi.co: use the fallback country when "detect" is chosen.
6. **Footer.** The primary button "Save changes" and the secondary button "Reset to defaults". Reset sets the
   form controls to the defaults client-side. The user still has to save.

Flags: use intl-tel-input's own flag classes (`<span class="iti__flag iti__eg"></span>`) with the bundled
`intlTelInput.css`. Don't add new images.

### admin.js (vanilla JS, no jQuery, no build step)
- Filter rows as the user types in the search box. Match on the country name and the dial code. Use the localized
  names from the next point.
- Localize names on the client with `new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' })`.
  Fall back to the server's English name on any error.
- Keep chips, the counter, and the checkboxes in sync. Hide the picker for "All countries". Enforce the limit of
  5 on preferred countries.
- Run the live preview.
- Everything works with the keyboard. The chip's remove button has `aria-label="Remove Egypt"` (translated, via
  `wp_localize_script` strings).

## 4. Housekeeping
- Add `uninstall.php` that deletes the `intelebox_settings` option (single site, and each site on multisite).
- Version bump to **1.3.0** in all three places. Bump `Tested up to` only if it isn't already 7.1.
- `readme.txt`:
  - Add a Description section about the settings page.
  - Update the FAQ "Can I customize the appearance?" answer. It currently says there's no settings page.
  - Add a FAQ entry: "Can I limit which countries are available?"
  - Changelog for 1.3.0, and an Upgrade Notice line.
  - Under External Services, say that the ipapi.co lookup can be turned off in settings.
  - Add the fonts (Schibsted Grotesk, JetBrains Mono, OFL 1.1) to Included Libraries.
- Add `== Screenshots ==` with one entry: "Settings page: choose which countries the phone field offers."
  (Claude will produce the image.)

## You may edit or create
`international-telephone-box.php`, `includes/**` (except `includes/data/countries.php`), `public/js/international-tel-box.js`,
`public/admin/css/admin.css`, `public/admin/js/admin.js`, `uninstall.php`, `readme.txt`.
Do not touch the vendored intl-tel-input files, the fonts, `docs/`, `tools/`, CI, `AGENTS.md`, or `ROADMAP.md`.

## Acceptance criteria
- [ ] With no option saved, the front end behaves exactly as in 1.2.0. That means EG fallback, EG first, ipapi.co
      lookup, and il excluded.
- [ ] Include mode with `sa, ae, eg` → the dropdown on a front-end page lists exactly those 3.
- [ ] Exclude mode with `us` → the US is missing, and everything else (minus il) is present.
- [ ] `geo_lookup` off → no network request to ipapi.co, and the field starts on the fallback country.
- [ ] `initial_country = ae` → it starts on AE and makes no ipapi.co request.
- [ ] Posting `il`, `xx`, `<script>`, or an empty include list is sanitized as described. Include-empty shows an error and saves as `all`.
- [ ] The settings page is only reachable with `manage_options`. Its CSS and JS load only on that screen. All output is escaped.
- [ ] The page matches `docs/DESIGN-SYSTEM.md`: tokens, fonts, pill buttons, underline inputs, segmented control, chips,
      and the one-column layout under 782px. It works in RTL.
- [ ] Without JS, the form still saves: the radios, checkboxes, and selects are real form fields.
- [ ] `php -l` is clean on all PHP files. `node --check` passes on both JS files. Search the code: the string "israel" or "Israel"
      appears nowhere in shipped files.
- [ ] The versions are all 1.3.0.

## Evidence to report
The checklist above with how each item was verified, the command outputs, the commit hash, and any deviation from this
spec with its reason.
