# AGENTS.md

WordPress plugin **International Telephone Box** (slug `international-telephone-box`).
It adds an international phone field (intl-tel-input) to `input[type=tel]` and to the `[intelebox_input]` shortcode.

Workflow: Claude writes task specs in `docs/tasks/`, opencode implements them, and Claude reviews.
Read `ROADMAP.md` and the task file you were given before changing anything. Do only what the task asks.

## Layout

- `international-telephone-box.php`: plugin header, `INTELEBOX_*` constants, bootstrap
- `includes/class-intelebox-main.php`: `International_Telephone_Box\Intelebox_Main` (hooks, assets, shortcode)
- `public/js/international-tel-box.js`: front-end init
- `public/js/intlTelInput.min.js`, `public/js/utils.js`, `public/css/intlTelInput.css`, `public/img/flags*.png`: vendored intl-tel-input 17.0.19. Do not edit them.
- `readme.txt`: the WordPress.org listing. Follow the format in https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- `.distignore`: files kept out of the release zip

## Rules

- PHP 7.4+ compatible. Follow WordPress Coding Standards. Prefix everything with `intelebox` / `INTELEBOX_`.
- Escape all output (`esc_attr`, `esc_html`, `esc_url`), sanitize all input, and check nonces and capabilities in admin code.
- No remote assets (CDN JS/CSS/fonts). WordPress.org guidelines forbid them. Load bundled files via `INTELEBOX_PLUGIN_URL`.
- Make user-facing strings translatable with the `international-telephone-box` text domain.
- Backward compatibility: `[intelebox_input]` and its `placeholder` / `class` attributes must keep working.
- Israel (`il` / `+972`) stays excluded. This is intentional. Do not remove it.
- Never edit `tags/` or SVN, and never bump versions unless the task says to. Releases go through `docs/RELEASING.md`.

## Checks before you finish

```sh
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
node --check public/js/international-tel-box.js
```

Finish with a short summary: files changed, how each acceptance criterion was met, and anything left undone.
Commit on the task branch with a clear message. Do not push and do not merge.
