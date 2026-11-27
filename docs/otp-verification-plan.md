# Phone verification (OTP): plan

Status: **planning only**, no code yet. Based on branch `update/intl-tel-input-29` (PR #1, plugin 1.2.0, intl-tel-input 29.5.12).
Author: Claude (planner). Implementer: opencode. Reviewer: Claude. Owner: Abanob Adel.

Owner decisions already made (2026-11-27):

- **Free plugin (WordPress.org)**: no verification. It keeps today's validation and formatting.
- **Pro add-on (sold separately, not on WordPress.org)**: all verification, meaning SMS OTP (every provider) and WhatsApp OTP.
- **Top priority**: nobody can bypass the protection or tamper with it. See §6 (visitor bypass) and §7 (licensing and cracking).

An earlier decision put one SMS provider in the free plugin. The owner replaced it with the split above. If the owner ever goes back to that idea, the free provider should be **Twilio Verify**: Twilio generates and checks the code, has Fraud Guard, needs no stored codes, and works in Egypt, KSA and the UAE once the sender ID is registered.

---

## 1. Where the code is today

The plugin is small. Everything below has to be built before OTP can exist.

| Piece | Today (1.2.0) | What OTP needs |
|---|---|---|
| `international-telephone-box.php` | Constants and bootstrap | Unchanged |
| `includes/class-intelebox-main.php` | Enqueues assets on **every** page and adds `[intelebox_input]` (`name`, `id`, `required`, `placeholder`, `class`) | Settings, validators, integrations, extension points |
| `public/js/international-tel-box.js` | Enhances **every** visible `input[type=tel]` (a `MutationObserver` also covers dynamic forms, Elementor popups and same-origin iframes), validates on blur and submit, writes E.164 back into the field, adds a hidden `full_phone` field, excludes `il` / `+972`, and preselects the country through ipapi.co | A per-field verification widget and an event API |
| Settings page | **None** | A global mode, providers and per-form overrides |
| Server-side validation | **None**. Every check runs in the browser. | Required: OTP enforcement is server-side or it is worthless |
| Form-builder integrations | **None in PHP**. CF7, Elementor, WPForms and Gravity Forms "work" only because their phone fields render `input[type=tel]`, which the JS picks up. | Real PHP integrations that hook into each builder's validation step |

What this means:

1. Today there is **no way to enforce anything**: a `curl` POST skips all of the JS. OTP depends on the 2.0.0 foundation (settings, PSR-4, server-side libphonenumber) and on the 2.1.0 integrations in `ROADMAP.md`. Those must ship first, built with the extension points from §4.
2. "Supports Elementor" today means Elementor **popups**. Elementor Forms is an Elementor **Pro** widget, so the Elementor form integration only applies when Elementor Pro is active.
3. Verification can only be **enforced** where the plugin hooks into the form's server-side processing. A random theme form with `input[type=tel]` gets formatting and nothing else. §3.7 covers that case.

---

## 2. Product shape

### 2.1 Modes

The setting is global, with a per-form override.

| Mode | Where it lives | Behaviour |
|---|---|---|
| `none` | Free | Today: validation and formatting. Free 2.1 adds server-side validation in the supported integrations. |
| `sms` | Pro | The number must be verified with a code sent by SMS. |
| `whatsapp` | Pro | The number must be verified with a code sent by WhatsApp. "Send by SMS instead" is offered if SMS is set up. |
| `any` | Pro | The visitor picks WhatsApp or SMS. The admin sets the order. |

### 2.2 Providers (all in Pro, and each site owner brings their own account; the plugin never resells)

| Provider | Kind | Channels | Phase | Why |
|---|---|---|---|---|
| **Twilio Verify** | Managed verify: Twilio generates and checks the code | SMS, WhatsApp | Pro 1.0 | Least code, no stored code, Fraud Guard and Geo Permissions, one account for both channels |
| **Meta WhatsApp Cloud API** | Transport: the plugin generates and checks the code | WhatsApp | Pro 1.1 | Cheapest WhatsApp, no middleman, the setup wizard in §8 |
| **Custom HTTP** | Transport | SMS (or any) | Pro 1.2 | Local gateways (Msegat, Taqnyat, Cequens, Victory Link) work without new code |
| **Infobip 2FA** | Managed verify (`POST /2fa/2/pin`, then `POST /2fa/2/pin/{pinId}/verify`, application plus message template with `{{pin}}`) | SMS (WhatsApp through their channels) | Pro 1.2 | Strong in MENA and Egypt |
| **Unifonic** | Managed verify or SMS transport | SMS, WhatsApp | Pro 1.2 | KSA and GCC sender-ID registration and templates. **I could not confirm the API endpoints with a quick search.** Read Unifonic's current developer docs before the task spec. If they have no verify API, use their SMS API as a transport. |

Things to check when writing each provider task:

- **Twilio Verify WhatsApp**: does it still offer a shared sender, or does it now require the customer's own WhatsApp sender?
- **Twilio WhatsApp-to-SMS fallback**: it is listed as a pilot that needs a support request, so the plugin builds its own fallback (§3.5).
- **Twilio Verify after approval**: once a verification is approved or expires (10 minutes), Twilio deletes it, and later checks return 404. The adapter must map 404 to `expired`.
- **Meta**: authentication templates are created with `POST /{WABA_ID}/message_templates`, category `AUTHENTICATION`, and a button `{type: "otp", otp_type: "copy_code"}`. `code_expiration_minutes` must be 1–90. The template starts as `PENDING`. URLs, media and emoji are not allowed. `upsert_message_templates` creates several languages at once (`en` and `ar`).
- **Meta pricing**: the higher "authentication-international" rate applies when the recipient's country differs from the business's primary country. The WABA field `auth_international_rate_eligibility` returns the start times and the exception countries. The wizard shows this (§8, step 9).

---

## 3. Architecture

### 3.1 Code layout

Free (`international-telephone-box`, PSR-4 `International_Telephone_Box\`, from 2.0):

```
src/Plugin.php                 bootstrap, INTELEBOX_API_VERSION
src/Settings/…                 settings page and the registry add-ons extend
src/Phone/Normalizer.php       libphonenumber-lite (Strauss-prefixed): E.164, number type, Arabic-Indic digits
src/Validation/Pipeline.php    runs core validation, then `intelebox_validate_submission` (§4)
src/Integrations/{Cf7,ElementorPro,WPForms,GravityForms,FluentForms,Shortcode}.php
public/js/international-tel-box.js   emits `intelebox:*` events (§4.3)
```

Pro (`international-telephone-box-pro`, PSR-4 `International_Telephone_Box_Pro\`, requires free ≥ 2.1 and checks `INTELEBOX_API_VERSION`):

```
src/Verification/Service.php       send, verify, issue/reserve/consume tokens
src/Verification/Store.php         custom tables (§5.2)
src/Verification/RateLimiter.php   atomic counters
src/Verification/Token.php         signed token format (§6.2)
src/Providers/ProviderInterface.php, Registry.php
src/Providers/{TwilioVerify,MetaWhatsApp,CustomHttp,Infobip,Unifonic}.php
src/Http/Client.php                wraps wp_remote_request; mocked in tests
src/Rest/{PublicController,AdminController,WebhookController}.php
src/Security/{Secrets,Signer,IpResolver}.php
src/Admin/{SettingsTabs,WhatsAppWizard,License}.php
public/js/verify.js, public/css/verify.css
```

### 3.2 Provider interface

```php
interface ProviderInterface {
    public function id(): string;                    // 'twilio_verify'
    public function label(): string;
    /** @return string[] */
    public function channels(): array;               // ['sms','whatsapp']
    public function generates_code(): bool;          // true: Twilio Verify, Infobip
    public function send( SendRequest $r ): SendResult;      // e164, channel, locale, code (null if generates_code), ttl
    public function check( CheckRequest $r ): CheckResult;   // only when generates_code(): e164, code, provider_ref
    public function test_connection(): TestResult;           // admin only
    public function settings_schema(): array;                // fields, types, which are secret
}
```

- Results are value objects with a normalized `status`: `sent | approved | invalid_code | expired | rate_limited | invalid_number | blocked | auth_failed | provider_error`. They also carry a `retryable` flag and a `provider_ref` (Twilio SID or Infobip pinId). The raw provider error goes to the debug log only, never to the visitor.
- When `generates_code()` is false, `Service` generates and checks the code itself (§5.1).
- Add-ons register providers on `intelebox_pro_register_providers`. Custom HTTP is the built-in escape hatch.

**Custom HTTP provider settings**: method, URL, headers, and a body template with `{{phone}}`, `{{phone_national}}`, `{{code}}`, `{{message}}`, `{{sender}}`, as JSON or form data. Also a success rule (HTTP 2xx and an optional JSON path/value) and an optional error-message JSON path. Header values can be marked secret, and those are encrypted (§5.4). The URL must be HTTPS. Send it with `wp_safe_remote_post` so it cannot reach internal addresses (SSRF protection).

### 3.3 REST endpoints (namespace `intelebox-pro/v1`)

Every response sends `Cache-Control: no-store` through `nocache_headers()`.

| Method and route | Who | Body | Response |
|---|---|---|---|
| `GET /session` | public | — | `{nonce, sid_set}`. Sets the `intelebox_sid` cookie (HttpOnly, Secure, SameSite=Lax, 2 h) and returns a fresh nonce. Cached pages call this first, so they never send a stale nonce. |
| `POST /otp/send` | public, nonce, sid | `{form_ref, phone, channel, captcha?}` | `{verification_id, channel, masked_phone, resend_after, expires_in}` |
| `POST /otp/verify` | public, nonce, sid | `{verification_id, phone, code}` | `{verified:true, token, token_expires_in}` or `{error, attempts_left?, retry_after?}` |
| `POST /admin/test-connection` | `manage_options` and REST nonce | `{provider}` | `TestResult` |
| `POST /admin/test-send` | `manage_options` | `{provider, channel, phone}` | Sends a test code (rate-limited) |
| `GET/POST /admin/whatsapp/template` | `manage_options` | — / `{name, languages}` | Template list or status, and template creation |
| `GET /webhooks/whatsapp` | Meta | `hub.mode, hub.verify_token, hub.challenge` | Echoes the challenge only if the verify token matches (`hash_equals`) |
| `POST /webhooks/whatsapp` | Meta | Status payload | Accepted only with a valid `X-Hub-Signature-256` (HMAC with App Secret) |

`form_ref` is a **signed** reference that the server renders into the field (§6.1). The endpoints never take a mode or a form id as plain data from the client.

### 3.4 Frontend UX flow

The widget is added by `verify.js`, only to fields the server marked with `data-intelebox-verify="<form_ref>"`.

```
idle ──(number valid per iti)──▶ ready [Send code ▸ WhatsApp | SMS]
ready ──click──▶ sending ──ok──▶ code_sent (code box, "Resend in 0:30", "Change number")
                     └─err──▶ send_failed (message; offer the other channel if allowed)
code_sent ──6 digits──▶ verifying ──ok──▶ verified (✓, phone input readonly, "Change number")
                              └─err──▶ invalid_code (attempts left) | expired | locked (retry_after)
verified ──number edited / "Change number"──▶ idle (token dropped)
any ──429──▶ rate_limited (countdown)
```

- Code input: one `<input inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr">`. Six separate boxes are an accessibility trap, so use one field. It auto-submits at 6 digits. Pasting works, and Arabic-Indic (`٠–٩`) and Persian (`۰–۹`) digits are normalized to ASCII on both the client and the server.
- The resend timer grows: 30 s, then 60 s, then 120 s, as returned by the server in `resend_after`. The client never decides it.
- Status changes are announced in an `aria-live="polite"` region. Errors use `aria-invalid` and `aria-describedby`. Focus moves to the code input after sending.
- The submit button is not disabled before verification (that would leave keyboard and screen-reader users stuck with no explanation). On submit without a token, the client shows "Verify your number first" and focuses the widget. **The server is the real gate (§6).**
- After `verified`, the client writes `intelebox_vtoken[<field>]` into a hidden input. If the phone value changes, the client clears it. That is a UX nicety only: the server rejects mismatches anyway.
- **RTL and Arabic**: logical CSS properties (`margin-inline-start`, etc.), the timer and the code always `dir="ltr"`, and strings through `wp_set_script_translations()`. The Arabic translation ships with launch (Pro translations are bundled as `.mo`/`.json`, since Pro is not on translate.wordpress.org). The WhatsApp template language is chosen from the visitor's locale (`ar` or `en`), then the site locale, then `en`.
- Example strings (en / ar):
  - "Send code via WhatsApp" / «إرسال الرمز عبر واتساب»
  - "Send code via SMS" / «إرسال الرمز برسالة نصية»
  - "Enter the 6-digit code" / «أدخل الرمز المكوّن من 6 أرقام»
  - "Resend in %s" / «إعادة الإرسال بعد %s»
  - "Change number" / «تغيير الرقم»
  - "Number verified" / «تم التحقق من الرقم»
  - "Wrong code. %d attempts left." / «رمز غير صحيح. تبقّى %d محاولات.»
  - "Too many attempts. Try again in %s." / «محاولات كثيرة. حاول مجددًا بعد %s.»

### 3.5 WhatsApp-to-SMS fallback

- **Automatic** when the WhatsApp send fails right away: `invalid_number`, the number not being on WhatsApp, or a retryable `provider_error`. Pro sends by SMS and tells the visitor ("We sent the code by SMS instead").
- **User-initiated** when WhatsApp accepted the message but it never arrived. After the first cooldown, a "Didn't get it? Send by SMS" link appears. It shares the same send limits.
- **Webhook-driven**, if the webhook is set up: a Meta `failed` status for the message id marks the verification `delivery_failed`, and the next poll or resend offers SMS right away.

### 3.6 Settings storage

- One option per area, all `autoload = no`:
  - `intelebox_pro_settings`: mode, channel order, limits and allowlist.
  - `intelebox_pro_providers`: per provider. Secret fields are stored encrypted (§5.4).
  - `intelebox_pro_license`: managed by the licensing SDK.
- Per-form overrides live with the form, so they travel with exports:
  - CF7: tag options.
  - Elementor: widget field controls.
  - WPForms and Gravity Forms: field settings, through hooks the free plugin exposes (§4.2).
  - Shortcode: attributes.
  - The resolved value is always read **from the server-side form definition**.

### 3.7 Fit with each integration (server-side enforcement)

The free plugin owns the integration and calls the pipeline (§4.1). Pro adds the verification rule to the pipeline. Each row names the hook that runs on **every** submission path of that builder.

| Integration | Per-form setting | Validation hook (runs on every submit path) | Success hook (consume token) | Paths covered and notes |
|---|---|---|---|---|
| **Contact Form 7** | New tag `[intelebox* phone verify:whatsapp]` (free 2.1 adds the tag, Pro adds the `verify:` option). The existing `[tel]` tags get a global switch. | `wpcf7_validate_intelebox`, `wpcf7_validate_intelebox*`, `wpcf7_validate_tel`, `wpcf7_validate_tel*` | `wpcf7_mail_sent`, or `wpcf7_submit` with status `mail_sent` | The REST `/contact-form-7/v1/contact-forms/{id}/feedback` and the no-JS POST both run the tag validation. The tag list comes from the form template, so removing the field from the DOM does not help an attacker. |
| **Elementor Pro Forms** | A new "Intelebox Phone" field type (`elementor_pro/forms/fields/register`) with a "Verification" control | `elementor_pro/forms/validation` with `$ajax_handler->add_error()` | `elementor_pro/forms/new_record` | `admin-ajax` `elementor_pro_forms_send_form`. Read the field config from the stored widget settings, not from `$_POST['form_fields']`. |
| **WPForms** | Field option on the Phone field (`wpforms_field_options_bottom_advanced-options`) | `wpforms_process` (iterate `$form_data['fields']`), errors into `wpforms()->obj('process')->errors` | `wpforms_process_complete` | AJAX and non-AJAX both go through `wpforms_process`. |
| **Gravity Forms** | Field setting (`gform_field_advanced_settings`, `gform_editor_js`) | `gform_field_validation` | `gform_after_submission` | Covers front-end, AJAX, `GFAPI::submit_form` and REST `POST /gf/v2/forms/{id}/submissions`. `POST /gf/v2/entries` skips validation but needs admin auth, which is acceptable and documented. |
| **Fluent Forms** | Field setting | `fluentform/validate_input_item_phone` | `fluentform/submission_inserted` | Phase 2.1 |
| **WooCommerce** (later) | Settings tab | Classic: `woocommerce_after_checkout_validation`. Blocks: `woocommerce_store_api_checkout_update_order_from_request`, throw `RouteException` | `woocommerce_checkout_order_processed` / `woocommerce_store_api_checkout_order_processed` | Pro 1.3 |
| **WP registration** (later) | Settings | `registration_errors`; WooCommerce `woocommerce_registration_errors` | `user_register` | Pro 1.3 |
| **`[intelebox_input]` shortcode** | `verify="none\|sms\|whatsapp\|any"` attribute | No form handler of its own, so enforcement is the form owner's job, through the public helper `intelebox_pro_verify_submission( $field_name, $form_id ): true\|WP_Error` and `intelebox_pro_consume_submission()` | Developer calls consume | The docs state this plainly. The `form_ref` is signed with the post ID and the attribute values, so the mode cannot be downgraded on the client. |
| **Any other `input[type=tel]`** | — | **No enforcement possible** | — | Pro does **not** add the OTP widget here (a widget the server ignores would give a false sense of security). The settings page explains this and points to the helper. |

Rules shared by every integration:

- **Empty optional field**: no verification needed. **Non-empty**: it must be verified. **Required field**: it must be present and verified.
- **Admins** (`manage_options`) editing or creating entries in wp-admin are exempt. Front-end submissions by admins are not exempt by default (filterable).
- **Error text**: "Please verify this phone number." Never reveal *why* a token failed.

---

## 4. Extension points in the free plugin (the free/Pro boundary)

The free plugin ships **generic, documented hooks** that any developer can use. It does not ship locked or hidden OTP code, so WordPress.org guideline 5 (no trialware or locked features) is respected. Pro is a normal separate plugin that uses these hooks.

### 4.1 PHP

```php
// Every integration builds a Submission and runs the pipeline in its validation hook.
$errors = apply_filters( 'intelebox_validate_submission', new WP_Error(), Submission $s );
//   Submission: integration ('cf7'), form_id, field_key, required (bool), raw value,
//   e164 (normalized server-side or null), hidden full_phone value, request context.
do_action( 'intelebox_submission_succeeded', Submission $s );  // from each success hook
do_action( 'intelebox_submission_failed', Submission $s, WP_Error $e );

// Field rendering: add-ons add attributes and markup to fields the plugin renders or wraps.
apply_filters( 'intelebox_field_attributes', array $attrs, FieldContext $ctx );
apply_filters( 'intelebox_shortcode_atts', array $defaults );            // lets Pro add `verify`

// Per-form settings: add-ons add controls to each builder's field UI through one registry.
apply_filters( 'intelebox_field_settings', array $settings, string $integration );
// Settings page tabs and sections.
do_action( 'intelebox_register_settings', SettingsRegistry $r );

do_action( 'intelebox_loaded', Plugin $plugin );
define( 'INTELEBOX_API_VERSION', '1' );  // bumped on breaking hook changes; Pro checks it
```

### 4.2 Data the free plugin reads from the form definition

Integrations resolve `field_settings` from the stored form (CF7 tag options, Elementor widget settings, WPForms `form_data`, GF field object). They pass the resolved values to the pipeline inside `Submission`, so Pro never reads the client's claims.

### 4.3 JavaScript

```js
document.addEventListener('intelebox:ready', e => { /* e.detail: { input, iti, form } */ });
window.intelebox.registerSubmitGuard(fn);  // fn(form, input) → true | string (message)
// Events: intelebox:ready, intelebox:validated, intelebox:invalid
```

The current `initializeInternationalTelephoneBox()` is refactored in 2.0 to emit these events and to keep the `iti` instance on the input (`input._intelebox`). Pro reads `iti.getNumber()` from it, so it never parses the number a second time.

### 4.4 How the free plugin presents Pro (guideline-compliant)

- **Allowed**: one "Phone verification (Pro)" link on the settings page and in the plugin row meta. A readme paragraph that names the add-on. Admin notices that are dismissible and rare.
- **Not allowed**:
  - Greyed-out OTP controls that look like part of the free plugin.
  - Nags on every admin page.
  - Any license check or Pro code inside the free zip.
  - Phoning home.
- **When Pro is deactivated** but forms were set to verify, the free plugin cannot enforce verification. Pro writes a flag on activation. If the flag is set and Pro is gone, the free plugin shows a generic notice: "An add-on that enforced extra phone checks is inactive; forms now accept unverified numbers." No branding is needed.

---

## 5. Security design (Pro)

### 5.1 Codes (transport providers only; managed-verify providers keep the code at the provider)

- 6 digits from `random_int( 0, 999999 )`, zero-padded. Configurable to 4–8 digits (default 6).
- Stored as `hash_hmac( 'sha256', $code . '|' . $verification_id, Secrets::key('otp') )` and compared with `hash_equals`. The plaintext code exists only in memory and in the outgoing message.
- TTL is 10 minutes (Meta's template expiry must be 1–90). One active code per verification. A resend **replaces** the code (the old one is invalid).
- At most 5 wrong attempts per code, then the code is burned. At most 3 codes per verification session.

### 5.2 Storage: custom tables, not transients

Transients can be evicted by object caches, are not atomic (race conditions in §6.6), and clutter `wp_options`. Pro creates these tables with `dbDelta` on activation, using a schema version option:

```
{prefix}intelebox_verifications
  id BIGINT PK, public_id CHAR(32) UNIQUE        -- verification_id sent to client
  phone_hash CHAR(64)  INDEX                     -- HMAC(E.164), never plaintext
  country CHAR(2), channel VARCHAR(16), provider VARCHAR(32), provider_ref VARCHAR(64)
  form_ref_hash CHAR(64), sid_hash CHAR(64), ip_hash CHAR(64)
  code_hash CHAR(64) NULL, attempts TINYINT, sends TINYINT
  status ENUM(pending,approved,burned,expired,delivery_failed)
  created_at, last_sent_at, expires_at DATETIME
{prefix}intelebox_tokens
  token_id CHAR(32) PK, verification_id BIGINT, phone_hash, form_ref_hash, sid_hash
  status ENUM(active,reserved,consumed,revoked), reserved_until, reserved_by CHAR(32)
  created_at, expires_at, consumed_at
{prefix}intelebox_counters
  bucket VARCHAR(96) PK  -- e.g. "send:phone:<hash>:h:2026112714"
  count INT, expires_at DATETIME INDEX
```

Cleanup runs on hourly WP-Cron (`intelebox_pro_gc`): it deletes expired counters, verifications older than the retention period (default 24 h), and consumed or expired tokens older than 24 h. A `wp_schedule_event` is re-registered on `init` if it is missing.

### 5.3 Rate limits (defaults, all configurable, all atomic: §6.6)

| Scope | Limit |
|---|---|
| Resend cooldown per number | 30 s, then 60 s, then 120 s |
| Sends per number | 5 / hour, 10 / day |
| Sends per IP | 10 / hour, 30 / day |
| Sends per session (`intelebox_sid`) | 5 / hour |
| Failed checks per number | 10 / day, then a 24 h lock |
| Failed checks per IP | 30 / day |
| **Global site cap** | 300 sends / day (circuit breaker: sending pauses and the admin gets an email) |
| Per country and per prefix | Optional caps for pumping (§6.7) |

The IP comes from `REMOTE_ADDR` by default. A "Trusted proxy" setting (Cloudflare, or a list of proxy CIDRs) enables `CF-Connecting-IP` or `X-Forwarded-For`. Otherwise those headers are ignored, because they are spoofable. IPs are stored as `HMAC(ip)` only.

### 5.4 Secrets

- Encrypted at rest with libsodium `sodium_crypto_secretbox` (core ships `sodium_compat` since 5.2). The format is `v1:` + base64(nonce|ciphertext).
- **Key**: the `INTELEBOX_PRO_KEY` constant in `wp-config.php` if it is defined (the wizard shows a generated line to copy). Otherwise the key is derived as `hash_hkdf('sha256', AUTH_KEY . SECURE_AUTH_SALT, 32, 'intelebox-pro')`. If the salts rotate, decryption fails, and the admin sees "Re-enter provider credentials." The plugin never breaks silently.
- **Constants override the DB.** `INTELEBOX_TWILIO_AUTH_TOKEN`, `INTELEBOX_WA_ACCESS_TOKEN`, etc. Hosts and agencies can keep secrets out of the database entirely.
- Secrets are **never** localized to JS and never returned by REST. The settings page shows `••••1a2b`. An empty submit keeps the stored value, and a "Clear" checkbox removes it.
- Logs mask numbers (`+20 10•• ••• 678`) and never contain codes, tokens or credentials.

### 5.5 Nonces and capabilities

- Public endpoints: a custom `intelebox_otp` nonce from `GET /session` (cache-safe) plus the `intelebox_sid` cookie. The nonce is **not** the main defence. It only stops drive-by cross-site posts. Rate limits, `form_ref` signatures and token binding do the real work.
- Admin endpoints: `permission_callback` → `current_user_can('manage_options')`, plus the standard `X-WP-Nonce`.
- Settings saves: Settings API with `check_admin_referer`, and every field goes through a schema sanitizer.

### 5.6 GDPR and data retention

- Phone numbers are personal data, and the providers (Twilio, Meta, Infobip, Unifonic, the custom gateway) act as processors for the site owner.
- Pro adds suggested text through `wp_add_privacy_policy_content`: what is sent, to whom, why, and how long it is kept.
- Pro stores **hashes only** (phone, IP, sid) and deletes them within 24 hours by default (configurable from 1 hour to 30 days, for audit).
- The phone number sent with the form is stored by the form plugin, as it is today, under the owner's existing policy.
- Registers a personal data exporter and eraser (`wp_privacy_personal_data_exporters` / `_erasers`). They look up rows by `HMAC(E.164)` of the requested user's phone meta.
- Optional consent line under the send button ("We'll send a one-time code to this number via WhatsApp"). It is on by default.
- The `intelebox_sid` cookie is strictly necessary (only set when the visitor clicks Send) and is documented.
- Meta's and Twilio's own data handling (including data residency for KSA PDPL and the UAE PDPL) is listed in the Pro docs. The owner should review it with counsel. The plugin cannot solve residency.

---

## 6. Anti-bypass: a visitor must never submit an unverified number

Threat model: the visitor or a bot controls the browser completely. It can edit the DOM and JS, replay requests, POST straight to the form endpoints, run requests in parallel, and use many IPs. The server and the site owner's database are trusted.

### 6.1 The server decides what needs verification

- Whether a field needs verification, and with which channels, comes **only** from the server-side form definition (§3.7). There is no `verified=1` flag, no client mode, and no `data-` attribute that the server believes.
- The `form_ref` rendered into the field is `base64url(json{int, form_id, field_key, mode, post_id, v}) . '.' . HMAC(Secrets::key('form'))`. The send endpoint checks the signature, checks that the form still exists, and checks that it still requires verification. A forged or stale `form_ref` gets a 400 response. This stops the send endpoint from being used as a free SMS gateway for forms that do not exist.
- On submit, the integration builds the `Submission` from the **stored form**. Pro never reads `form_ref` from the request to decide anything at submit time. It only checks that the token's `form_ref_hash` matches the server-computed one.

### 6.2 The verification token: signed, bound, single-use, with an expiry

Issued only after `POST /otp/verify` succeeds. For managed-verify providers, that means after the server-to-server check returns `approved`. The client never "tells" the server it is verified.

```
token = "v1." token_id "." exp "." base64url( HMAC-SHA256( key_token,
          "v1|" token_id "|" exp "|" E164 "|" form_ref_hash "|" sid_hash ) )
```

- **Bound to the phone**: the E.164 number, normalized on the server.
- **Bound to the form**: the integration, form id and field.
- **Bound to the session**: the `intelebox_sid` cookie, which is HttpOnly so JS cannot read it.
- **Expiry**: `exp` is 15 minutes after issue (configurable from 5 to 30 minutes).
- **Single use**: the DB row in `intelebox_tokens` changes `active → reserved → consumed`.
- **Why both a signature and a DB row**: the HMAC lets the server reject forged or garbled tokens without a DB hit. The DB row gives single use, revocation and race safety. Either check failing means reject.

### 6.3 Checks at submit time (all must pass)

1. Server-normalize the phone from the **visible field value**, using libphonenumber and the field's default country, with Arabic-Indic digits converted. If a hidden `full_phone` field is present, it must normalize to the **same** E.164 number. Otherwise reject. This closes the "verify A, submit B in the other field" trick.
2. Apply the number policy: valid number, allowed country, not `il`, and an allowed number type (§6.7).
3. Read the token from `intelebox_vtoken[field_key]`. Check the format and the HMAC (`hash_equals`), and check that `exp` is in the future.
4. Recompute the binding from the **server's** E.164, the form_ref_hash and the sid_hash from the cookie. It must match the HMAC.
5. **Reserve** the token atomically (§6.6). Exactly one request wins.
6. When the builder's success hook fires, the token is **consumed**. If validation fails elsewhere (another field is wrong), the reservation is released by `intelebox_submission_failed`, or it lapses after 120 s. The visitor fixes the other field without verifying again, and nobody can reuse the token in parallel.

If any check fails, the field error is "Please verify this phone number." The specific reason goes to the debug log only.

### 6.4 Replay protection

- A consumed token can never pass again: its status is final, and the row is kept until its expiry plus 24 h.
- A token cannot move to another form (form binding), another browser (sid binding), or another number (phone binding).
- A captured `POST /otp/verify` cannot be replayed: once the verification row is `approved` it is final, and a second verify returns `already_used` without minting a new token.
- Twilio deletes approved verifications (404 on a later check), which is a second line of defence.
- The `verification_id` is a 128-bit random value and cannot be guessed.

### 6.5 Brute-force limits

- 6 digits, at most 5 tries per code and 3 codes per session, with 10 failures per number per day before a lock. That gives an attacker at most about 10 guesses per number per day: a 1 in 100,000 chance per day per number.
- The attempt counter goes up **before** the compare, with a conditional update (§6.6). Parallel guesses cannot exceed the limit.
- A wrong code returns `attempts_left`. After the lock, every response has the same shape (`locked`, `retry_after`). There is no timing difference between "wrong code" and "locked" (constant-time compare, same code path).

### 6.6 Race conditions

Every limit and state change is a **single conditional SQL statement**, followed by a check of `$wpdb->rows_affected`:

| Race | Atomic statement |
|---|---|
| Parallel guesses beyond the attempt limit | `UPDATE verifications SET attempts = attempts + 1 WHERE id = %d AND attempts < 5 AND status = 'pending' AND expires_at > NOW()`. If 0 rows change, reject before comparing. |
| Parallel sends that dodge the cooldown | `UPDATE verifications SET last_sent_at = NOW(), sends = sends + 1 WHERE id = %d AND sends < 3 AND last_sent_at <= NOW() - INTERVAL %d SECOND`. The provider is called only if 1 row changed. |
| Counter limits | `INSERT INTO counters (bucket, count, expires_at) VALUES (%s, 1, %s) ON DUPLICATE KEY UPDATE count = count + 1`, then `SELECT count`. If it is over the limit, reject. Small overshoot under extreme concurrency is bounded and acceptable. For the global cap, use `UPDATE … WHERE count < cap` instead. |
| Double submit with one token | `UPDATE tokens SET status = 'reserved', reserved_by = %s, reserved_until = NOW() + INTERVAL 120 SECOND WHERE token_id = %s AND (status = 'active' OR (status = 'reserved' AND reserved_until < NOW())) AND expires_at > NOW()`. Only 1 row can change. |
| Consume | `UPDATE tokens SET status = 'consumed', consumed_at = NOW() WHERE token_id = %s AND status = 'reserved' AND reserved_by = %s` |
| Verify twice at the same moment | `UPDATE verifications SET status = 'approved' WHERE id = %d AND status = 'pending'`. Only the winner mints a token. |

All times are compared on the DB side (`NOW()` / UTC), so PHP clock skew between web nodes does not matter.

### 6.7 REST abuse and SMS pumping

SMS pumping (toll fraud) means bots trigger sends to premium or attacker-controlled ranges, and the site owner pays. Layers of defence:

1. **Country allowlist** (required before Pro will send). The default is the site's default country only. `il` is always blocked.
2. **Number type**: SMS only to `MOBILE` (or `FIXED_LINE_OR_MOBILE` where the country's numbering is ambiguous). `PREMIUM_RATE`, `SHARED_COST`, `UAN`, `PAGER`, `VOIP` and `TOLL_FREE` are always rejected. This uses libphonenumber on the server.
3. **Signed `form_ref`** (§6.1), the nonce, the sid cookie, a honeypot field in the widget, and rate limits per number, IP, session, country and prefix (§5.3).
4. **Global circuit breaker**: when the daily cap is hit, sending pauses and the admin gets an email. The "verification unavailable" policy (§6.9) then applies.
5. **Pumping detector**: in any 1-hour window, more than N sends to one country or one 6-digit prefix with a verify rate under 20 % means the prefix is paused for 24 h and the admin is emailed. Pumped numbers never verify, so a low verify rate is the signature.
6. **Optional CAPTCHA before send**: a `intelebox_pro_presend_check` hook, with Cloudflare Turnstile built in (Pro 1.3, an external service disclosed in the Pro docs).
7. **Provider-side controls**, which the wizard asks the owner to turn on:
   - Twilio: Fraud Guard, and Geo Permissions set to the same allowlist.
   - Meta: the WABA spend limit and a payment method alert.
8. Optional "IP country must match phone country" rule. It is off by default, because expats and VPNs are common in the Gulf.

### 6.8 The number changes after verification

- On the client, editing the phone or the country clears the hidden token and resets the widget (UX only).
- On the server, the token's HMAC binds the E.164 number, so the submitted number must equal the verified one (§6.3, steps 1 and 4). Changing the country selector, the visible field or `full_phone` breaks the binding.
- Verifying in one tab and submitting a different number in another tab fails the same way.

### 6.9 Fail-closed defaults

- If the provider is down, the circuit breaker is open, or Pro's license state disables sending (§7), the per-form policy "When verification is unavailable" applies:
  - **Block submission** (default and recommended).
  - **Accept and flag the entry as unverified** (keeps leads coming; adds an "unverified" note to the entry where the builder supports it).
- An expired or invalid token **always** blocks, whatever the policy.

### 6.10 Other tamper paths

| Path | Mitigation |
|---|---|
| POST straight to the CF7 REST endpoint, Elementor/WPForms `admin-ajax`, or GF REST | Covered: the validation hooks in §3.7 run on those paths |
| Remove the field from the DOM | Validation iterates the stored form fields, so a missing required field is rejected. A missing optional field is "empty". |
| Change the per-form setting | Needs `manage_options`. Pro logs changes to mode and limits in a small audit log (who, when, old → new). |
| Webhook spoofing | `X-Hub-Signature-256` HMAC with App Secret, and the verify-token challenge |
| Cached pages with stale nonces | `GET /session` gets a fresh nonce. REST responses are `no-store`. |
| Timing or enumeration on send | The same response shape and similar latency whether the number is blocked, limited or sent. `masked_phone` is computed from the input. |
| Pro deactivated by a compromised admin | Out of scope (a compromised admin owns the site). The free plugin's notice (§4.4) makes it visible. |

---

## 7. Pro licensing and cracking

### 7.1 What is realistic with GPL PHP

- Pro is a derivative of a GPL plugin and of WordPress, so **it must be GPL**. Buyers may legally redistribute the code. "Nulled" copies are legal redistribution with the license check removed. That cannot be prevented, only made less attractive.
- **Any check running on the buyer's server can be removed** by editing one `if`. Obfuscation is fragile, irritating for honest users, and conflicts with the spirit of the GPL. **Recommendation: do not obfuscate.**
- What *can* be controlled is **what runs on servers the owner controls**: the update feed, support, and optionally a hosted service. GPL covers code, not services, so the owner can restrict access to those to paying customers.
- Selling point: nulled plugins are a common malware vector. Pro's signed updates and integrity check (§7.4) protect honest buyers, and the marketing should say so.

### 7.2 Options

| Approach | Stops | Does not stop | Cost and risk |
|---|---|---|---|
| **A. License gates updates and support only** | Nulled copies getting updates and fixes, which also matter for security. Key sharing past the activation limit. | A nulled copy working forever at the version it was cracked at | Lowest friction; standard in the WP market |
| **B. A + activation required to enable verification** (local check, cached 7 days, 14-day grace if the license server is unreachable) | Casual unlicensed installs and shared keys past the activation limit (the server refuses extra domains). Expired or refunded keys after the grace period. | Anyone who patches the check (5 minutes). Nulled sites patch it. | Free to build with a licensing SDK. Must never break the site's forms unpredictably (§6.9 policy applies). |
| **C. B + optional hosted relay**: Pro calls an owner-run API that sends the OTP through the customer's provider, checks the code, and signs the token with a private key. Pro only verifies an Ed25519 signature. | A drop-in nulled copy can't send codes for relay-backed features. The adapter code is not in the zip. | A developer can rewrite a Twilio adapter in ~100 lines. Relay-signed tokens are verified on the site, so a cracker removes that check too. Only the *sending* is protected. | **Hosting, monitoring and on-call.** The relay becomes a hard dependency of every customer's forms (outage means blocked sign-ups). It holds customers' provider credentials (a breach magnet), phone numbers pass through it (the owner becomes a GDPR, KSA PDPL and UAE processor and needs DPAs), and it adds latency. |
| **D. Build your own licensing** | Same as A or B | Same | Months of payments, VAT, refunds, update server and key management. No reason to do it. |

### 7.3 Recommendation: **B, with Freemius, and no relay at launch**

- **Licensing platform: Freemius**, with Pro sold as a standalone "premium-only" plugin that requires the free plugin. The free plugin contains **no** Freemius SDK and no telemetry, so the WordPress.org review stays simple.
  - Freemius is the merchant of record, so it handles VAT and sales tax worldwide.
  - It handles license keys, activation limits per domain, license-gated updates delivered by its own updater, trials (allowed **in Pro**, because Pro is not on WordPress.org), renewals, refunds and an affiliate program.
  - It pays out by PayPal, Payoneer or wire. That matters because **Stripe does not onboard Egypt-based businesses**, which makes Stripe-only stacks (EDD with Stripe, many Lemon Squeezy setups) awkward. *The owner should confirm current payout options for their country before signing up.*
  - The cost is a revenue share (about 7 % plus the payment gateway fee; check current pricing).
- **Alternatives:**
  - **EDD Software Licensing**: self-hosted on abanob.co with your own payment gateway. Lower fees and full control, but you handle VAT, and the gateway availability problem from Egypt remains.
  - **Lemon Squeezy**: merchant of record with a license API (activate, validate, deactivate), but no WordPress update server. You would build and host the updater. It is now owned by Stripe, so check payout support for Egypt.
- **Behaviour:**
  - **Not activated**: Pro settings are visible, sending is disabled, and an admin notice is shown.
  - **Active license**: everything works.
  - **Expired license**: **verification keeps working**, but updates and support stop. Killing working protection on a live site after expiry is hostile and generates chargebacks.
  - **Revoked or refunded license, or activation limit exceeded** (after the grace period): sending stops, and the §6.9 policy applies (block by default).
- **License checks**: at most one call a day, on WP-Cron, never during a visitor request. The result is cached in `intelebox_pro_license`.
- **Activation limits**, as tiers for the owner to price: 1 site, 5 sites, unlimited (agency). Staging and local domains (`*.local`, `*.test`, `localhost`, `staging.*`, `*.wpengine.com`, InstaWP and Playground) don't count against the limit.
- **Relay**: do not build at launch. Revisit after 6–12 months if piracy is measurable (sales vs. active installs that report a license). If it is built, make it an **opt-in "Intelebox Cloud" tier** that sites choose for convenience (managed delivery reports, a fraud dashboard). Never make it the only way Pro works.

What the recommendation stops and does not stop:

| Threat | Stopped? |
|---|---|
| Unlicensed casual install from a shared zip | **Yes**: sending stays off until activation |
| One key shared across many sites | **Yes**: activation limit enforced server-side by Freemius |
| Refunded or charged-back key kept in use | **Yes**, after the grace period |
| Nulled copy getting updates | **Yes**: the update feed is license-gated |
| Tampered update package (MITM, malicious mirror) | **Yes**: signed updates (§7.4) |
| A nulled copy with the check patched out, used forever at an old version | **No** (no approach stops this without the relay, and the relay only partly) |
| A developer forking the GPL code | **No**, and legally allowed |

### 7.4 Update integrity (signed updates)

- Freemius serves updates over HTTPS from its CDN. Pro adds its **own** check on top:
  - CI signs each release zip with an Ed25519 key that is held only in the release pipeline's secrets.
  - The public key is embedded in Pro.
  - Pro hooks `upgrader_pre_download` / `upgrader_source_selection` for its own slug. It downloads the detached `.sig` and calls `sodium_crypto_sign_verify_detached()`. If the signature is bad, it aborts.
- This protects honest buyers against a hijacked update feed. It does nothing against nulled forks (they change the key), and that is fine.
- **Integrity check**: an admin screen compares Pro's files with a signed manifest and warns "Files differ from the official release." This helps buyers who received an infected copy from a reseller.

### 7.5 WordPress.org compliance of the split

| Guideline | How the plan complies |
|---|---|
| 5. No trialware or locked features | The free plugin has no OTP code, locked or hidden. Its hooks are generic and documented. Verification is a separate product. |
| 6. Serviceware | Not applicable to free. Pro's provider calls are the customer's own accounts. |
| 7. No tracking without consent | Free adds no telemetry, and there is no Freemius SDK in free. ipapi.co stays disclosed (and becomes opt-in in 2.0 per the roadmap). |
| 8. No remote executable code | Free never downloads code. Pro updates come through the Freemius updater, outside WordPress.org. |
| 11. Don't hijack the admin | One settings link and dismissible notices only (§4.4) |
| 4. Human-readable code | Free stays unminified source plus the vendored library, with its source linked (already in the readme) |
| External services in readme.txt | Free lists ipapi.co (already there). Pro's documentation (and its own readme.txt inside the zip) lists Twilio, Meta, Infobip, Unifonic, Custom HTTP (the URL the admin enters), Freemius (licensing and updates) and Cloudflare Turnstile, each with what is sent, when, why, and links to terms and privacy. |

---

## 8. WhatsApp setup guide (Pro, wp-admin → Phone Field → WhatsApp)

A step-by-step wizard. Each step has a status chip (✓ / ! / ○), a "Check" button where an API check exists, and short help text with links to Meta's docs. Progress is saved, so the admin can stop and come back.

The first screen offers two cards:

- **"Easiest: Twilio Verify WhatsApp channel"**: uses the Twilio credentials already entered.
- **"Direct with Meta (cheapest)"**: starts the wizard below.

1. **Before you start** (checklist, manual):
   - A Meta Business Portfolio. Business verification raises the sending limits.
   - A phone number **not** registered in the WhatsApp or WhatsApp Business app. It can receive SMS or a voice call.
   - A payment method.
   - About 30 minutes.
2. **Create a Meta app**: developers.facebook.com → My Apps → Create app → type *Business* → add the **WhatsApp** product. Screenshots inline.
3. **Add your business phone number** in WhatsApp → API Setup, and verify it. Paste the **Phone Number ID** and the **WhatsApp Business Account (WABA) ID**.
   - Check: `GET /{phone-number-id}?fields=display_phone_number,verified_name,quality_rating,code_verification_status` shows the number, the verified name and the quality.
4. **Create a permanent access token**:
   - Business Settings → Users → System users → Add (Admin role).
   - Assign assets: the app, and the WABA with full control.
   - Generate token → expiration *Never* → scopes `whatsapp_business_messaging` and `whatsapp_business_management`.
   - Paste the token. It is encrypted, or the admin can define `INTELEBOX_WA_ACCESS_TOKEN` in wp-config.
   - Check: `GET /{waba-id}?fields=name,currency,timezone_id` plus the phone check above. A clear message for each failure (`190` invalid token, `10`/`200` missing permission, `100` wrong ID).
5. **Add a payment method** to the WABA (manual; deep link to Business Settings → WhatsApp accounts → Payment settings).
6. **Authentication template**:
   - **"Create it for me"** sends `POST /{waba-id}/upsert_message_templates` with name `intelebox_otp`, languages `en` and `ar`, category `AUTHENTICATION`, `add_security_recommendation: true`, `code_expiration_minutes: 10`, and a button `{type: "otp", otp_type: "copy_code"}`.
   - Or **"Use an existing template"**, picked from a list (`GET /{waba-id}/message_templates?category=AUTHENTICATION`).
   - Shows the status per language: `PENDING`, `APPROVED` or `REJECTED` (with the reason). A "Refresh" button, plus an hourly cron while pending. The template name and language are saved in the settings.
7. **Send a test**: enter your own number, receive the code, and type it back. This proves the full path, including the copy-code button.
8. **Delivery webhook (optional, recommended for fallback)**:
   - Shows the callback URL (`/wp-json/intelebox-pro/v1/webhooks/whatsapp`) and a generated verify token, each with a copy button.
   - Asks for the **App Secret** (encrypted).
   - Steps: App → WhatsApp → Configuration → Webhook → Verify and save → subscribe to `messages`.
   - Check: the wizard shows "Last delivery status received at …".
9. **Pricing and limits**:
   - Shows the WABA's country.
   - If any allowlisted country differs from it, a warning explains that Meta may charge the **authentication-international** rate for those countries, with a link to Meta's rate cards. If available, it lists the countries from `auth_international_rate_eligibility`.
   - Shows the messaging limit tier and the quality rating.
   - Suggests turning on SMS fallback.
10. **Done**: choose WhatsApp as the global mode or per form.

Graph API version: a single constant (`INTELEBOX_PRO_GRAPH_VERSION`). Bump it in a scheduled maintenance task, because Meta retires versions about 2 years after release.

---

## 9. Release breakdown

Sizes: **S** ≤ 1 day, **M** 2–4 days, **L** 1–2 weeks, **XL** 2–4 weeks (implementation plus review). Each phase is split into `docs/tasks/NN-*.md` tasks, following the usual workflow.

| Phase | Product and version | Scope | Size | Milestone or exit criteria |
|---|---|---|---|---|
| 0 | Free 1.2.0 | PR #1 (library upgrade plus fixes) | — | Merged and released |
| 1 | **Free 2.0.0** | Roadmap foundation: tooling (composer, PHPCS/WPCS, PHPStan, wp-env, Plugin Check, CI), PSR-4, settings page, `libphonenumber-for-php-lite` through Strauss, assets only where a field exists, **plus §4 extension points** (pipeline, field settings registry, JS events, `INTELEBOX_API_VERSION`) | L | Hooks documented in `docs/hooks.md`, and a sample add-on in `tests/fixtures` that uses them |
| 2 | **Free 2.1.0** | Integrations with server-side validation: CF7 (`[intelebox]` tag), Elementor Pro, WPForms, Gravity Forms, Fluent Forms. Each calls the pipeline and the success and failure hooks. | L (M per builder) | An e2e test per builder proving that an invalid number is rejected **server-side** (JS disabled) |
| 3 | **Pro 1.0.0** | Verification core (§3, §5, §6), tables, REST, `verify.js` widget (RTL plus Arabic), Twilio Verify (SMS and WhatsApp channel), enforcement in all phase-2 integrations and the shortcode helper, fail-closed policy, Freemius licensing (B), signed updates, Pro readme with services disclosure | XL | The §6 bypass test suite passes, and a security review (Claude, `/security-review`) is clean |
| 4 | Pro 1.1.0 | Meta WhatsApp Cloud provider, the §8 wizard, template creation and status, webhook, WhatsApp-to-SMS fallback | L | The wizard is completed end to end with a Meta test number |
| 5 | Pro 1.2.0 | Custom HTTP, Infobip 2FA, Unifonic | M + M + M | A contract test per adapter. Custom HTTP is documented with presets for Msegat and Taqnyat. |
| 6 | Pro 1.3.0 | WooCommerce (classic and Blocks) checkout and registration, WP registration, Turnstile pre-send check, a stats screen (sent, verified, blocked, pumping alerts) | L | |
| 7 | Later / optional | "Intelebox Cloud" relay (§7.2 C), remembering verified numbers for logged-in users, per-field rules | — | Only with sales and piracy data |

The owner wanted a "major release": the visible launch is **Free 2.1 together with Pro 1.0**, announced together.

### 9.1 Testing strategy

- **Unit tests** (PHPUnit with Brain Monkey or the WP test suite under wp-env):
  - Each adapter runs against **mocked HTTP**. The `Http\Client` interface is used in tests, and integration-level tests use the `pre_http_request` filter.
  - Each adapter has recorded JSON fixtures per provider (`tests/fixtures/{provider}/*.json`) for success, wrong code, expired (Twilio 404), invalid number, auth failure (401 / Meta `190`), 429, 5xx, and a timeout.
  - The tests assert the request shape (URL, auth header, body) and the mapping to the normalized status.
- **Service tests**, with an injected clock:
  - Code hashing.
  - TTL.
  - Attempt and send limits.
  - The cooldown schedule.
  - Token HMAC (every bound field mutated must fail).
  - Reserve, consume and release.
  - The fail-closed policy.
- **Concurrency tests**: run N parallel `curl` requests against wp-env for verify, send and submit. Assert that exactly 1 succeeds or that the limit holds. This is the proof for §6.6.
- **REST tests**: permission callbacks, nonce, schema validation, `no-store` headers, and the same response shape for blocked, limited and sent.
- **A Postman collection** (`tests/postman/`) for `session → send → verify → submit` and the bypass cases (replayed token, swapped number, other form, no cookie, forged `form_ref`). It runs with the Postman CLI in CI against wp-env, using the **fake provider**.
- **Fake provider**: `intelebox_test`. It is only registered when `INTELEBOX_PRO_TEST_MODE` is defined, accepts `000000`, and logs sends. Its absence from production builds is checked by CI.
- **e2e** (Playwright):
  - On wp-env with CF7, WPForms Lite, Gravity Forms (dev license), Elementor and Elementor Pro (dev license) and Fluent Forms. Each test runs the full flow and a "JS disabled / direct POST" negative case.
  - The same specs run in **WordPress Playground** through a blueprint, for quick PR previews. Playground has no outbound SMS, which is why the fake provider exists.
  - RTL runs with `ar` locale snapshots.
- **Manual live smoke tests** before each Pro release, using the owner's real accounts:
  - Twilio Verify to Egyptian, Saudi and UAE numbers.
  - The Meta test number to 5 allowed recipients.
  - Note: Twilio *test credentials* do not cover Verify, so these tests use a real account.
- **Static checks**: PHPCS (WPCS), PHPStan level 6, Plugin Check on the free zip, and `php -l` / `node --check` as in `AGENTS.md`.

---

## 10. Decisions for the owner

Each has my recommendation in **bold**.

1. **Release order**: free 2.0 (foundation and hooks), then free 2.1 (integrations), then Pro 1.0. Alternatively, rush Pro on today's code. **Follow the order: OTP without server-side integrations can't be enforced.**
2. **Licensing platform**: Freemius, EDD Software Licensing or Lemon Squeezy. **Freemius** (merchant of record, payouts that work from Egypt; confirm this). Pro is a standalone add-on, with no SDK in the free plugin.
3. **Anti-piracy level** (§7.2): A, B or C. **B (activation required, update gating, activation limits, signed updates). No relay at launch.**
4. **Expired license behaviour**: **keep verifying, stop updates and support.** Revoked or refunded keys stop sending after a 14-day grace period.
5. **"Verification unavailable" default** (§6.9): **block submission**, with a per-form option to accept and flag.
6. **Launch provider for Pro 1.0**: **Twilio Verify (SMS and WhatsApp channel)**. Meta direct in 1.1, regional providers in 1.2.
7. **Storage**: **custom tables** (needed for atomic limits and single-use tokens), not transients.
8. **Coverage**: **enforce only in supported integrations plus the shortcode helper.** No OTP widget on unknown forms.
9. **Optional phone fields**: **an empty value is allowed, and a filled value must be verified.**
10. **Token lifetime and use**: **15 minutes, single-use, bound to the number, form and session.** Reserved at validation and consumed on success.
11. **Country allowlist default**: **required, and defaults to the site's default country.** `il` is always blocked.
12. **WhatsApp-to-SMS fallback**: **automatic on a synchronous failure, user-initiated after the cooldown.**
13. **Secrets**: **encrypted in the DB, plus wp-config constants that override it.**
14. **libphonenumber package**: **`giggsey/libphonenumber-for-php-lite`** (no geocoder or carrier data, so a much smaller zip), prefixed with Strauss.
15. **Minimum versions**: free stays WP 5.0+ / PHP 7.4 until 2.0. **From 2.0, raise the free plugin to WP 6.2 and use the same floor for Pro**, which simplifies REST, script translations and the Blocks checkout.
16. **Pricing and tiers**: the number of sites per tier (suggested 1 / 5 / unlimited) and whether Pro offers a trial (allowed, because Pro is not on WordPress.org). This is a commercial decision for the owner.
17. **Israel exclusion in OTP**: **yes, also blocked server-side** in the allowlist and the number policy, matching the free plugin.
18. **Optional pre-send CAPTCHA (Turnstile)**: **ship it in Pro 1.3, off by default.** It is a strong anti-pumping layer but is another external service.
19. **ipapi.co**: **make it opt-in in 2.0** (already in the roadmap). Free stays telemetry-free, which also keeps the split clean.

---

## Sources checked for this plan

- [Meta: copy-code authentication templates](https://developers.facebook.com/docs/whatsapp/business-management-api/authentication-templates/copy-code-button-authentication-templates)
- [Meta: authentication-international rates](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/authentication-international-rates)
- [Twilio Verify: Verification](https://twilio.com/docs/verify/api/verification) and [VerificationCheck](https://www.twilio.com/docs/verify/api/verification-check.md)
- [Twilio Verify product page (Fraud Guard, WhatsApp pricing, fallback pilot)](https://www.twilio.com/en-us/verify)
- [Infobip 2FA API](https://infobip.com/docs/2fa-service/using-2fa-api)
- [WordPress.org Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- Not verified: the Unifonic verify API endpoints, whether Twilio Verify's WhatsApp channel needs a customer-owned sender, the current Freemius fee and payout countries, and Stripe's availability for Egyptian businesses. Check each before writing the matching task.
