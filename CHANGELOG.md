# Changelog

All notable changes to **QuickGive for Paystack** will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/) and the
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format.

---

## [1.1.0] — 2026-04-13

### Added

- **Donor thank-you email** (`class-quickgive-email.php`) — configurable email sent automatically to donors after successful server-side payment verification only. Supports `{amount}`, `{currency}`, `{email}`, `{reference}`, and `{site_name}` placeholders.
- **Donor Email settings section** in the admin settings page — toggle to enable/disable, from name, from email, subject, and body all configurable. Placeholder hints shown inline.
- **Overview admin page** (`QuickGive → Overview`) — lightweight dashboard showing three summary cards: Total Attempts, Successful Donations, and Total Raised. Includes a Recent Donations table listing the five most recent verified donations.
- **`amount_type` tracking** — donation records now store whether a donor used a preset amount (`preset`) or entered their own (`custom`). Displayed as a colour-coded badge in the donation log and the overview recent list.
- **`QuickGive_Logger::get_summary()`** — single-query method returning total count, success count, total raised, and recent donations for the Overview page.
- **`QuickGive_Logger::maybe_upgrade_table()`** — non-destructive schema migration that runs once on `plugins_loaded`. Adds the `amount_type` column to existing v1.0 installs via `ALTER TABLE` without losing any data.
- **Status filter tabs** on the Donation Log page — filter log records by All, Successful, Failed, or Pending.
- **Bidirectional amount deselection** in the frontend popup — typing in the custom amount input deselects any active preset button, and clicking a preset button clears the custom input value.
- **`amount_type` sent in AJAX verification POST** — the frontend now sends `amount_type` with every verification request so the server can store the correct type regardless of browser state.
- **Admin CSS enqueued on all plugin pages** — stylesheet now loads on the Settings page, Overview page, and Donation Log page (previously Settings only).
- **Overview card styles** and **amount-type badge styles** added to `quickgive-admin.css`.

### Changed

- **`QuickGive_Logger::log()`** — added optional `$amount_type` parameter (default `'preset'`). Existing call sites are backward-compatible.
- **`QuickGive_Logger::create_table()`** — schema updated to include `amount_type VARCHAR(10) NOT NULL DEFAULT 'preset'` with an index.
- **`QuickGive_Ajax::verify_transaction()`** — now reads `amount_type` from POST, passes it to the logger, and calls `QuickGive_Email::send()` after verified success.
- **`QuickGive_Admin::render_log_page()`** — donation log table now includes a `Type` column with preset/custom badges. Status filter tabs added above the table.
- **`QuickGive_Admin::enqueue_admin_assets()`** — expanded hook list to cover all three plugin admin pages.
- **Plugin version** bumped to `1.1.0` in the main plugin file header and `QUICKGIVE_VERSION` constant.
- **`quickgive-for-paystack.php`** — `load_dependencies()` now requires `class-quickgive-email.php`. `init_hooks()` registers `maybe_upgrade_table` on `plugins_loaded` priority 5.
- **Frontend JavaScript** (`quickgive-frontend.js`) — `resolveAmount()` now returns `{ amount, amountType }` instead of a bare number. `launchPaystack()` and `verifyServerSide()` accept and forward `amountType`. `wireCustomInput()` added for preset deselection on input.

### Security

- Thank-you email is dispatched exclusively inside `QuickGive_Ajax::verify_transaction()` after all server-side checks pass — never triggered by the frontend success callback alone.
- `amount_type` accepted from POST is validated against an allowlist (`['preset', 'custom']`) before being stored.

---

## [1.0.0] — 2026-04-13

### Added

- **Admin settings page** (`QuickGive → Settings`) with four sections: Paystack API, Donation Options, Button & Messages.
- **Paystack API configuration** — separate test and live public/secret key pairs with a mode toggle (Test / Live).
- **Currency selector** supporting NGN, GHS, ZAR, KES, USD, GBP, EUR.
- **Preset donation amounts** — comma-separated list rendered as selectable buttons in the popup.
- **Optional custom donation amount** — toggle in settings; renders a currency-prefixed number input when enabled.
- **Minimum and maximum amount validation** — configurable in settings; enforced on both frontend and implied server-side.
- **Configurable button label** and **thank-you message** (supports basic HTML via `wp_kses_post`).
- **`[paystack_donation_popup]` shortcode** — renders the donation button wherever placed; assets load only on pages using the shortcode.
- **Popup modal** (`templates/donation-popup.php`) with ARIA `role="dialog"`, `aria-modal`, `aria-labelledby`, focus trap, and Escape-to-close support.
- **Paystack inline checkout** (`PaystackPop.setup`) loaded from the Paystack CDN. Public key passed via `wp_localize_script`; secret key never sent to the browser.
- **Server-side transaction verification** (`class-quickgive-ajax.php`) — after the Paystack `onSuccess` callback fires, the reference is POSTed to `admin-ajax.php`. The backend calls the Paystack Verify API with the secret key, cross-checks the returned amount, and only then confirms success.
- **Nonce protection** on all AJAX requests via `wp_create_nonce` / `wp_verify_nonce`.
- **Donation logger** (`class-quickgive-logger.php`) — custom database table `{prefix}quickgive_donations` created on activation via `dbDelta`. Stores reference, donor email, amount, currency, status, and created date.
- **Donation Log admin page** (`QuickGive → Donation Log`) — paginated table of all donation records with status colour-coded pills.
- **`uninstall.php`** — removes the plugin options and drops the custom table on deletion.
- **Mobile-responsive frontend** — popup slides up as a bottom sheet on viewports ≤ 480 px.
- **Success panel** with animated SVG checkmark, shown in the modal after verified payment.
- **Loading spinner** on the submit button during Paystack checkout and server-side verification.
- **Failure / retry state** — warning message shown inside the modal if the donor cancels or verification fails, allowing them to try again without closing the popup.
- **Admin-only notice** if the shortcode is placed on a page before Paystack keys are configured.
- **`QUICKGIVE_VERSION`, `QUICKGIVE_FILE`, `QUICKGIVE_DIR`, `QUICKGIVE_URL`, `QUICKGIVE_SLUG`** plugin constants.
- Singleton bootstrap class `QuickGive_For_Paystack` loaded on `plugins_loaded`.

---

*For a full list of planned features and future release ideas, see [README.md](./README.md).*
