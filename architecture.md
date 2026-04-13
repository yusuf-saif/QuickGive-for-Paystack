# QuickGive for Paystack — Architecture & Developer Reference

## 1. Plugin Structure

```text
quickgive-for-paystack/
├── quickgive-for-paystack.php          # Main bootstrap file (singleton)
├── uninstall.php                       # Cleanup on plugin deletion
├── README.md                           # Product overview
│
├── includes/
│   ├── class-quickgive-admin.php       # Admin settings UI + donation log page
│   ├── class-quickgive-ajax.php        # AJAX handler — server-side Paystack verification
│   ├── class-quickgive-shortcode.php   # [paystack_donation_popup] shortcode + asset loading
│   └── class-quickgive-logger.php      # Custom DB table CRUD for donation records
│
├── assets/
│   ├── css/
│   │   ├── quickgive-frontend.css      # Modal, button, form, success panel styles
│   │   └── quickgive-admin.css         # Admin page enhancements
│   └── js/
│       └── quickgive-frontend.js       # Modal orchestration + Paystack + AJAX verification
│
└── templates/
    └── donation-popup.php              # HTML markup for the popup modal
```

---

## 2. Component Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress Site                              │
│                                                                 │
│  [ADMIN]                         [FRONTEND]                     │
│  ┌──────────────────────┐        ┌───────────────────────────┐  │
│  │  QuickGive_Admin      │        │  [paystack_donation_popup] │  │
│  │  ─────────────────── │        │  shortcode                 │  │
│  │  • Settings page      │        │  ──────────────────────── │  │
│  │  • Donation log       │        │  • Renders donation button │  │
│  │  • Sanitize inputs    │        │  • Loads assets on-demand  │  │
│  └──────────────────────┘        └─────────────┬─────────────┘  │
│                                                │                │
│  [DB]                                          ▼                │
│  ┌──────────────────────┐        ┌───────────────────────────┐  │
│  │  QuickGive_Logger    │        │  donation-popup.php        │  │
│  │  ─────────────────── │        │  ──────────────────────── │  │
│  │  wp_{prefix}         │◄───────│  • Preset amount buttons   │  │
│  │  quickgive_donations │        │  • Custom amount input     │  │
│  │                      │        │  • Email field             │  │
│  │  id, reference,      │        │  • Submit / close          │  │
│  │  donor_email,        │        │  • Success panel           │  │
│  │  amount, currency,   │        └─────────────┬─────────────┘  │
│  │  status, created_at  │                      │                │
│  └──────────────────────┘                      ▼                │
│            ▲                   ┌───────────────────────────┐    │
│            │                   │  quickgive-frontend.js    │    │
│            │                   │  ──────────────────────── │    │
│            │                   │  1. Open/close modal      │    │
│            │                   │  2. Validate form         │    │
│            │                   │  3. PaystackPop.setup()   │    │
│            │                   │     → onSuccess callback  │    │
│            │                   │  4. POST to admin-ajax.php│    │
│            │  (on success)     └─────────────┬─────────────┘    │
│            │                                 │                  │
│  [BACKEND]                                   ▼                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  QuickGive_Ajax::verify_transaction()                    │   │
│  │  ──────────────────────────────────────────────────────  │   │
│  │  1. wp_verify_nonce()           — replay protection      │   │
│  │  2. Sanitize reference, email, amount, currency          │   │
│  │  3. Get SECRET KEY from DB (never sent to browser)       │   │
│  │  4. wp_remote_get( Paystack /verify/{reference} )        │   │
│  │  5. Cross-check amount from Paystack vs amount from POST │   │
│  │  6. QuickGive_Logger::log()     — record in DB           │   │
│  │  7. wp_send_json_success/error()                         │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Security Model

| Concern | Mitigation |
|---|---|
| Secret key exposure | Stored in `wp_options`, only accessed server-side in `class-quickgive-ajax.php`. Never included in `wp_localize_script`. |
| CSRF / AJAX forgery | `wp_create_nonce()` + `wp_verify_nonce()` on every AJAX request |
| Client-side status trust | Success shown only after server-side Paystack API verification |
| Amount manipulation | `verified_amount !== POSTed_amount` check rejects tampered amounts |
| Admin input injection | All settings sanitized via `sanitize_text_field`, `sanitize_email`, `wp_kses_post`, `absint` |
| Capability escalation | Settings page and log page both call `current_user_can('manage_options')` |
| SQL injection | All DB queries use `$wpdb->prepare()`, `$wpdb->insert()`, `$wpdb->update()` |
| Direct file access | Every PHP file begins with `if ( ! defined('ABSPATH') ) { exit; }` |

---

## 4. Payment Flow (Step-by-Step)

```
Donor                    Frontend JS              Server (WP)              Paystack API
  │                          │                        │                        │
  │── Clicks "Donate" ──────►│                        │                        │
  │                          │── Opens modal          │                        │
  │── Selects amount ────────►│                        │                        │
  │── Enters email ──────────►│                        │                        │
  │── Clicks "Donate" ───────►│                        │                        │
  │                          │── Validates locally    │                        │
  │                          │── PaystackPop.setup()──┼────────────────────────►│
  │◄─────── Paystack checkout UI ──────────────────────────────────────────────│
  │── Pays ──────────────────►│                        │                        │
  │                          │◄── onSuccess(reference)│                        │
  │                          │── POST /admin-ajax.php ►│                        │
  │                          │   (nonce, reference,    │                        │
  │                          │    email, amount)        │                        │
  │                          │                        │── GET /verify/{ref} ───►│
  │                          │                        │◄── {status:"success"} ──│
  │                          │                        │── cross-check amount   │
  │                          │                        │── Logger::log()        │
  │                          │◄── JSON {success: true}│                        │
  │◄─── Thank-you panel ─────│                        │                        │
```

---

## 5. Database Schema

**Table:** `{prefix}quickgive_donations`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `reference` | `VARCHAR(100)` | Unique Paystack reference |
| `donor_email` | `VARCHAR(200)` | Sanitized email |
| `amount` | `DECIMAL(12,2)` | In main currency unit (e.g. Naira, not kobo) |
| `currency` | `VARCHAR(10)` | ISO code (NGN, GHS…) |
| `status` | `VARCHAR(20)` | `pending` → `success` or `failed` |
| `created_at` | `DATETIME` | Auto-set on insert |

Indexes: `PRIMARY KEY (id)`, `UNIQUE KEY (reference)`, `KEY (status)`

---

## 6. Settings Reference

All settings are stored in a single `quickgive_settings` option (serialized array).

| Key | Type | Description |
|---|---|---|
| `mode` | `string` | `test` or `live` |
| `public_key_test` | `string` | Paystack test public key |
| `secret_key_test` | `string` | Paystack test secret key |
| `public_key_live` | `string` | Paystack live public key |
| `secret_key_live` | `string` | Paystack live secret key |
| `currency` | `string` | ISO currency code (default: `NGN`) |
| `preset_amounts` | `string` | Comma-separated amounts (e.g. `500,1000,2500`) |
| `allow_custom` | `string` | `1` = enabled, `0` = disabled |
| `min_amount` | `int` | Minimum donation (0 = no minimum) |
| `max_amount` | `int` | Maximum donation (0 = no maximum) |
| `button_label` | `string` | Donate button label text |
| `thankyou_message` | `string` | HTML allowed via `wp_kses_post` |

---

## 7. Setup Instructions

### Step 1 — Upload

Copy `quickgive-for-paystack/` into `/wp-content/plugins/`.

### Step 2 — Activate

Go to **Plugins → Installed Plugins** and click **Activate** under QuickGive for Paystack.  
Activation automatically creates the `{prefix}quickgive_donations` table.

### Step 3 — Configure

Go to **QuickGive → Settings** and fill in:

1. **Mode** — choose Test while developing, switch to Live before going live.
2. **Test/Live Public Key** — copy from your [Paystack Dashboard → Settings → API Keys](https://dashboard.paystack.com/#/settings/developer).
3. **Test/Live Secret Key** — same dashboard page.
4. **Currency** — select the currency your Paystack account is configured for.
5. **Preset Amounts** — enter comma-separated values, e.g. `500,1000,2500,5000`.
6. **Allow Custom Amount** — tick to show a free-text amount field.
7. **Min / Max Amount** — set 0 for no limit.
8. **Button Label** — e.g. `Donate Now` or `Support Us`.
9. **Thank-You Message** — text or basic HTML shown after verified payment.

### Step 4 — Place Shortcode

Add `[paystack_donation_popup]` to any page, post, or widget.

### Step 5 — Test

With Mode = Test, use Paystack's [test cards](https://paystack.com/docs/payments/test-payments/) to verify the full flow end-to-end.

---

## 8. Future Extension Suggestions

### Recurring Donations
- Add a `frequency` field to the popup (one-time / monthly / annual).
- Use the [Paystack Plans API](https://paystack.com/docs/payments/recurring-debit/) to create a subscription after the first payment.
- Store `subscription_code` in the donations table.

### Multiple Forms (Campaign-Specific)
- Extend the shortcode to accept attributes: `[paystack_donation_popup campaign="education" min="1000"]`.
- Create a custom post type `quickgive_campaign` for per-campaign settings.
- Scope the admin log by campaign.

### Donor Receipts
- Hook into the `QuickGive_Logger::log()` success path.
- Use `wp_mail()` to send a styled email receipt with reference number and amount.

### Gutenberg Block
- Wrap the shortcode in a `register_block_type()` call.
- Build a minimal React block that exposes button label and campaign as block attributes.

### Webhooks
- Register a REST API endpoint: `POST /wp-json/quickgive/v1/webhook`.
- Verify Paystack's HMAC signature against the configured secret key.
- Use webhooks as a fallback to update donation status if the browser closes before AJAX completes.

### Export
- Add a CSV export button to the Donation Log page.
- Use `wp_send_json` + `fputcsv` to stream a filtered export.

### Analytics Dashboard
- Add a stats sub-page showing total raised, donor count, average donation.
- Use `$wpdb` aggregation queries grouped by date/currency.
