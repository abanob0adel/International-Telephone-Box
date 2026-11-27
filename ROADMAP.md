# International Telephone Box: Roadmap

Workflow: **Claude plans → opencode implements → Claude reviews.**
Each task lives in `docs/tasks/NN-*.md`, gets its own branch, and merges only after review.

Baseline: 1.1.5 as published on WordPress.org (synced into git on `chore/sync-1.1.5`).
WordPress target: tested up to 7.1 (7.2 ships 2026-12-08). PHP minimum: 7.4.

## Product direction

One phone field that works across every major form builder. It respects visitor privacy
by default, stores clean E.164 numbers, and is first-class in Arabic and RTL.

Israel (`il` / `+972`) stays excluded from the country list. This is the owner's
decision and is stated in the readme.

---

## Release 1.2.0: library upgrade and bug fixes (now)

PR #1 upgrades intl-tel-input from 17.0.19 to 29.5.12 and drops jQuery. The bug fixes that were
planned for 1.1.6 ship inside 1.2.0 instead (1.1.6 is not released). Task: `docs/tasks/02-release-1.2.0.md`.

- The shortcode input has no `name`, so the number is never submitted.
- The stylesheet targets `.itb-*`, but the markup uses `.intelebox-*`, so the styles never apply.
- `utilsScript` still loads from cdnjs even though `utils.js` is bundled.
- The readme refers to a settings page that does not exist, and its changelog is broken.
- Tested up to 6.8 triggers the "not tested with the latest 3 major releases" warning.
- The release zip contained a nested `trunk/` folder. CI deploys and `.distignore` prevent that from happening again.

## Release 2.0.0: foundation and settings

- **Tooling**: composer, PHPCS + WPCS, PHPStan, `@wordpress/env`, Plugin Check, and GitHub Actions on every PR.
- **Architecture**: PSR-4 under the `International_Telephone_Box\` namespace. Separate `Plugin`, `Assets`, `Settings`, `Shortcode`, `Geo`, `Validator`, and `Integrations\*` classes.
- **Settings page** (Settings → Phone Field):
  - Default country, preferred countries, and excluded countries. `il` is always excluded.
  - Where the field applies: all `input[type=tel]`, a CSS selector, or shortcode/integrations only.
  - Country detection: off / browser locale / server header (`CF-IPCountry`, etc.) / ipapi.co. This setting is opt-in and is disclosed.
  - Error messages, number type (mobile/any), and separate dial code on/off.
- Only enqueue assets on pages that contain a phone field (the v29 bundle with utils is large).
- **Performance**: replace the whole-document attribute `MutationObserver` with targeted observation and integration events.
- **Backward compatibility**: keep the `[intelebox_input]` shortcode and its attributes working, and migrate silently.

## Release 2.1.0: integrations (the differentiator)

Each integration is a separate task with an e2e test:

- Contact Form 7 (`[intelebox]` form tag plus server validation)
- WPForms, Gravity Forms, Fluent Forms, Ninja Forms
- Elementor Pro Forms (field type plus popup support)
- WooCommerce: billing/shipping phone on the classic checkout and Checkout Blocks
- A Gutenberg block for the standalone field

Server-side validation uses `giggsey/libphonenumber-for-php`, prefixed with Strauss to avoid conflicts. Numbers are stored as E.164.

## Release 2.2.0: polish

- Full Arabic translation, RTL layout, and localized country names
- Accessibility: `aria-invalid`, `aria-describedby` error text, keyboard-friendly dropdown
- Optional `tel:` / WhatsApp link output for displayed numbers
- WordPress.org listing: screenshots, a demo video, real FAQs, and a Playground blueprint for "Live Preview"

## Later (Pro candidates)

SMS/WhatsApp OTP verification, number-type rules per form, analytics on rejected numbers.

---

## Definition of done (every task)

1. Acceptance criteria in the task file are met.
2. `php -l` is clean. PHPCS and Plugin Check are clean once the tooling exists.
3. Manually checked in a local WordPress site, or e2e tested.
4. `readme.txt` changelog updated, plus the version bump if it is a release.
5. Reviewed by Claude against the task file.

See `docs/RELEASING.md` for how a version reaches WordPress.org.
