=== QuickDonate ===
Contributors: saif2002
Tags: donations, fundraising, paystack, charity, payments
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: quickdonate

Collect secure one-time donations with a clean popup checkout, donation logs, thank-you emails, and Paystack verification.

== Description ==

QuickDonate is a lightweight donation plugin for WordPress. It provides a shortcode-triggered popup, preset and custom donation amounts, donor email capture, server-side verification, donation logging, and optional thank-you emails.

QuickDonate is gateway-ready for future expansion, but this release includes only Paystack as a working gateway.

The plugin is architected for future gateway expansion. Paystack is the first supported gateway and remains the only fully functional gateway in this release.

**External service disclosure:**
QuickDonate connects to Paystack to open checkout and verify completed transactions. Payment data required to process a donation is sent to Paystack. Site administrators must provide their own Paystack public and secret keys.

Paystack Terms of Service:
https://paystack.com/terms

Paystack Privacy Policy:
https://paystack.com/privacy/merchant

Non-affiliation disclaimer:
"QuickDonate is independently developed and is not affiliated with, endorsed by, or sponsored by Paystack, GiveWP, Donorbox, or any other third-party payment provider."

== Installation ==

1. Upload the `quickdonate` plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress Plugins screen.
3. Open `QuickDonate` in the WordPress admin.
4. Configure your gateway keys, currency, donation amounts, and email settings.
5. Add `[quickdonate_popup]` to any page or post.

== Frequently Asked Questions ==

= What shortcode should I use? =

Use `[quickdonate_popup]`.

= Does QuickDonate support more than one gateway? =

The plugin is structured for multiple gateways, but this release only includes Paystack as a working gateway.

= Is payment verification done server-side? =

Yes. The transaction reference is verified on the server before a donation is marked as successful and before any thank-you email is sent.

= Does the plugin support preset and custom amounts? =

Yes. You can configure preset amounts and optionally allow donors to enter a custom amount.

= Does it work with Elementor layouts? =

Yes. The modal overlay is moved to `document.body` to avoid being trapped inside Elementor containers with overflow or transform stacking contexts.

== Screenshots ==

1. QuickDonate dashboard showing donation totals, successful donations, total raised, and average donation.
2. QuickDonate settings page with donation amounts, email settings, Paystack gateway settings, and advanced options.
3. Frontend donation popup with preset amounts, custom amount field, donor email field, and secure checkout button.
4. Donation logs page showing donor details, amount, currency, gateway, reference, status, and date.

== Changelog ==

= 1.0.1 =
* Removed legacy backward-compatibility code and shortcode aliases.

= 1.0.0 =
* Initial WordPress.org-ready release of QuickDonate.
* Added shortcode-triggered donation popup.
* Added preset and custom donation amounts.
* Added Paystack gateway verification.
* Added donation logs.
* Added donor thank-you email.
* Added Elementor-safe modal behavior.
* Added clean admin dashboard, settings, and logs interface.

== Upgrade Notice ==

= 1.0.1 =
Legacy shortcode aliases and backward-compatible migration code removed.

= 1.0.0 =
First public release on WordPress.org.
