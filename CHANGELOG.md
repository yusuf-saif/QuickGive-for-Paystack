# Changelog

All notable changes to **QuickDonate** are documented in this file.

## [1.0.1] - 2026-07-09

- Removed legacy backward-compatibility code and shortcode aliases (`[paystack_donation_popup]`, `[quickgive_donation_popup]`).
- Removed legacy settings and database table migration.
- Plugin slug, textdomain, and all prefixes are now consistently `quickdonate`.

## [1.0.0] - 2026-04-13

- Initial public release with Paystack checkout, AJAX verification, shortcode popup, and donation logging.
- Added shortcode-triggered donation popup with `[quickdonate_popup]`.
- Added preset and custom donation amounts.
- Added Paystack gateway verification.
- Added donation logging with status, amount type, gateway, and reference.
- Added donor thank-you email.
- Added admin dashboard, settings, and donation log pages.
- Added Elementor-safe modal behavior by relocating overlays to `document.body`.
