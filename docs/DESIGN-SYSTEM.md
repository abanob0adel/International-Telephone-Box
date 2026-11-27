# Admin design system

Every admin screen the plugin adds uses the visual language of https://abanob.co.
Reference screenshots: `docs/design/reference-home.png` (light, "paper") and
`docs/design/reference-contact.png` (dark, "ink").

The feel is editorial and quiet: warm paper background, near-black ink, one warm accent,
hairline rules instead of boxes, pill buttons, and small monospace uppercase labels.
No gradients, no drop shadows, no rounded cards.

## Scope

All styles live in `public/admin/css/admin.css`. Every rule is scoped under `.intelebox-admin`,
the root element of our page. Never restyle WordPress core admin chrome (menu, admin bar, notices
outside our root). Load the CSS and fonts only on our settings screen (check the hook suffix).

## Tokens

Declare these as custom properties on `.intelebox-admin`:

| Token | Value | Use |
|---|---|---|
| `--ib-paper` | `#f4f1ea` | page background |
| `--ib-paper-2` | `#efebe2` | alternate section / hover background |
| `--ib-ink` | `#1a1917` | headings, primary text, primary button background |
| `--ib-text` | `#3c3a35` | body copy |
| `--ib-muted` | `#6b665e` | help text, placeholders, counters (5.0:1 on paper, so AA) |
| `--ib-line` | `#c9c2b2` | hairline rules, outline-button border, input underline |
| `--ib-accent` | `#8a5a2b` | accent word in the title, links, checked state, focus ring |
| `--ib-accent-soft` | `rgba(138, 90, 43, .10)` | selected-row background |
| `--ib-danger` | `#a83a3a` | errors (5.6:1 on paper) |
| `--ib-success` | `#00795e` | "Settings saved" |
| `--ib-radius-pill` | `9999px` | buttons, chips, segmented control |
| `--ib-radius-sm` | `4px` | checkboxes, dropdown panels |

There is no dark mode in this version. The page is always "paper".

## Typography

Fonts are bundled in `public/admin/fonts/` (SIL OFL; licence files are next to them). Declare them with
`@font-face` in `admin.css` using relative URLs and `font-display: swap`:

- `"Schibsted Grotesk"` → `schibsted-grotesk-latin-wght-normal.woff2`, weight range `400 900`
- `"JetBrains Mono"` → `jetbrains-mono-latin-wght-normal.woff2`, weight range `100 800`

Stacks:

- Display (title, section headings, buttons): `"Clash Display", "Schibsted Grotesk", system-ui, sans-serif`.
  Clash Display is not bundled (its licence is not GPL-compatible). It is used only when the
  visitor has it installed.
- Body: `"Schibsted Grotesk", system-ui, -apple-system, "Segoe UI", sans-serif`
- Mono (eyebrows, labels, counters, dial codes): `"JetBrains Mono", ui-monospace, monospace`

The fonts cover Latin only. Arabic and other scripts fall back to the system font. That's expected.

| Element | Font | Size | Weight | Other |
|---|---|---|---|---|
| Eyebrow above the title | mono | 11px | 400 | uppercase, `letter-spacing: .18em`, `--ib-muted` |
| Page title (h1) | display | 44px (32px under 782px) | 500 | `letter-spacing: -0.035em`, line-height 1.05, `--ib-ink`. One word is wrapped in `<em>` and coloured `--ib-accent`, with no italics |
| Section heading (h2) | display | 22px | 500 | `letter-spacing: -0.02em` |
| Body / help | body | 15px | 400 | line-height 1.6, `--ib-text`. Help text is 13px `--ib-muted` |
| Field label | mono | 11px | 400 | uppercase, `letter-spacing: .14em`, `--ib-muted` |
| Button | display | 15px | 500 | |

## Components

- **Layout.** Max content width 880px, left aligned, with 40px top padding. Sections are separated by
  a 1px `--ib-line` rule and 40px of vertical space. Each section has a two-column grid on wide screens:
  heading and help on the left (240px), controls on the right. It collapses to one column under 782px.
- **Primary button.** `--ib-ink` background, `--ib-paper` text, pill radius, `padding: 14px 28px`, no border.
  Hover: background `#000`. Focus-visible: `outline: 2px solid var(--ib-accent); outline-offset: 3px`.
- **Secondary button.** Transparent background, `--ib-ink` text, `1px solid var(--ib-line)` border, pill.
  Hover: border `--ib-ink`.
- **Text input / search.** No box: transparent background, a bottom border only (`1px solid var(--ib-line)`),
  `padding: 10px 0`, 16px body font. Focus: bottom border `--ib-ink`, no glow. Placeholder `--ib-muted`.
- **Select.** Same underline style as inputs, with a custom chevron.
- **Segmented control** (radio group). A pill-shaped outline container (`1px solid var(--ib-line)`), with
  options inside as pills. The checked option has an `--ib-ink` background and `--ib-paper` text. It is
  built from real `<input type="radio">` elements, visually hidden, with labels styled as the segments.
  Keyboard arrows must work (native radio behaviour).
- **Toggle.** A 40×22 pill track. Off: `--ib-line`. On: `--ib-ink`. The knob is `--ib-paper`. Built from
  `<input type="checkbox" role="switch">`.
- **Chip** (a selected country). Pill, `1px solid var(--ib-line)`, 13px. It shows the flag, the name, and a
  mono dial code in `--ib-muted`, plus a remove "×" button with an accessible label.
- **Country list row.** 40px high, with a hairline bottom border. Contents: checkbox, flag, name, then the
  dial code in mono and `--ib-muted`, right aligned. Hover: `--ib-paper-2`. Checked: `--ib-accent-soft`
  background.
- **Notice** (saved or error). No box. It's a line of text with a 2px left rule in `--ib-success` or
  `--ib-danger`, shown inside our root under the title.

## Accessibility and RTL

- Contrast: every text token above passes WCAG AA (4.5:1) on `--ib-paper` and `--ib-paper-2`. Don't add lighter greys for text.
- `--ib-line` is for rules and borders only, never text.
- Every control has a visible label. Groups use `<fieldset>` + `<legend>`.
- Use logical CSS properties (`margin-inline-start`, `padding-inline`, `inset-inline-end`, `text-align: start`)
  so the page works in RTL admin (Arabic) without a separate stylesheet.
- Respect `prefers-reduced-motion`. Transitions are limited to colour and border, 150ms.
