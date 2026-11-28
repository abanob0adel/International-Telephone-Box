# Task 03b: Review fixes for the 1.3.0 settings page

Branch: `release/1.3.0` (on top of `30f7185`) · Implementer: opencode · Reviewer: Claude

Claude reviewed task 03 in WordPress 7.1 (Playground) with Chromium. The save flow, sanitizing, the Settings link, and
loading assets only on our screen all work. Fix the items below. Change nothing else.

## Must fix

1. **The geo-lookup toggle can't be turned off (privacy).** `wp_localize_script()` casts scalar values to strings, so
   `geoLookup` reaches the browser as `"1"` / `""`. The JS treats `""` as "not set → true", so ipapi.co is still called
   when the owner turns it off.
   - In `Intelebox_Main::enqueue_public_scripts()`, replace `wp_localize_script( ..., 'inteleboxConfig', ... )` with
     `wp_add_inline_script( 'international-telephone-box', 'window.inteleboxConfig = ' . wp_json_encode( self::public_config() ) . ';', 'before' );`
     This keeps the JSON types (bool, arrays).
   - In the JS, keep accepting a real boolean. Missing/undefined means `true`. Explicit `false` means off.
   - Do the same for any boolean or number passed to `admin.js`.

2. **The CSS isn't scoped, so WordPress core overrides it.** The title renders at 23px instead of 44px, because core
   `.wrap h1` wins. Inputs and selects render as white boxes instead of underlines, because core `input[type=search]`,
   `select`, etc. win.
   - Prefix **every** selector in `public/admin/css/admin.css` with `.intelebox-admin`, as `docs/DESIGN-SYSTEM.md` requires
     ("Every rule is scoped under `.intelebox-admin`"). Example: `.intelebox-admin .intelebox-title`.
   - For form controls, use selectors that also beat core's attribute selectors, e.g.
     `.intelebox-admin input.intelebox-input[type="search"]`, `.intelebox-admin select.intelebox-select`.
     Set `background: transparent; border: 0; border-bottom: 1px solid var(--ib-line); border-radius: 0; box-shadow: none; min-height: 0;`.
   - Make the paper background fill the whole content area: style `#wpcontent`/`#wpbody-content` only through
     `body.settings_page_intelebox-settings` (our page's body class). Don't affect other screens.

3. **The "Settings saved." notice shows twice.** WordPress already prints `settings_errors()` on pages under the
   Settings menu (`options-head.php`). Remove our extra `settings_errors()` call. Instead, style the core notice inside
   our page: `body.settings_page_intelebox-settings .notice` gets the design-system notice look (no box, 2px start-side rule).

## Should fix

4. **The segmented control wraps badly on mobile** (390px: the third option drops to a second line inside the pill).
   Under 782px, make it full width with three equal columns (`display: grid; grid-template-columns: repeat(3, 1fr)`),
   center the labels, and allow the text to wrap inside each segment.
5. **The preview doesn't match the front end.** The preview has no separate dial code next to the flag. Build the preview
   options from the same values the front end uses: `separateDialCode: true`, `numberDisplayFormat`,
   `placeholderNumberPolicy`, `placeholderNumberType: "MOBILE"`, `countrySelectorMode: "DROPDOWN"`, and so on. The
   simplest way is a shared helper or a copied options object with a comment saying "keep in sync with
   public/js/international-tel-box.js".
6. **The counter isn't translatable with a number.** Render it with `sprintf( _n( '%d selected', '%d selected', $n, ... ) )`
   on the server. Pass the JS a translated format string (e.g. `"%d selected"` and `"%1$d of %2$d selected"`) and
   replace the number client-side.
7. **Spacing:** in the preferred-countries fieldset, the "Search countries" label sits directly under the help text.
   Add the same gap (16px) that the other fields have between the help text and the next label.
8. **Exclude mode with every country selected** leaves nothing to offer. Treat it like include-with-none: fall back to
   `all`, and show an error ("You excluded every country. Leave at least one available.").

## Out of scope
Anything not listed. Do not change the vendored files, the fonts, `countries.php`, `docs/`, or the readme. Exception: add one
changelog line if item 1 changes documented behaviour. It doesn't, so leave the readme alone.

## Acceptance criteria
- [ ] The page source has `window.inteleboxConfig = {...}` with `"geoLookup":false` when the toggle is off, and `true` by default.
- [ ] Every rule in `admin.css` starts with `.intelebox-admin` or `body.settings_page_intelebox-settings`. Check with
      `grep -vE '^\s*(\.intelebox-admin|body\.settings_page_intelebox-settings|@|}|/\*|\*|$|[a-z-]+:)' public/admin/css/admin.css` → no selector lines.
- [ ] `settings_errors(` doesn't appear in `class-intelebox-admin.php`.
- [ ] `php -l` and `node --check` pass on all files.
- [ ] One commit on `release/1.3.0`. Do not push.
