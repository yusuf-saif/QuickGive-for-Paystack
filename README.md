# QuickDonate

QuickDonate is a lightweight WordPress donation plugin with a polished popup checkout flow, admin dashboard, donation logging, donor thank-you emails, and Paystack as its first supported gateway.

## Features

- Modern shortcode-triggered donation popup
- New primary shortcode: `[quickdonate_popup]`
- Backward-compatible shortcode aliases: `[paystack_donation_popup]` and `[quickgive_donation_popup]`
- Preset amounts and optional custom amount entry
- Donor email capture
- Server-side payment verification
- Donation logging with amount type, gateway, status, and reference
- Thank-you message after verified success
- Optional thank-you email after verified success
- Admin overview dashboard and full donation log
- Gateway-ready structure with Paystack implemented first
- Elementor-safe modal behavior by moving overlays to `document.body`

## Active Gateway

QuickDonate currently supports Paystack for live checkout and verification.

The plugin UI is branded generically so future gateways can be added without changing the frontend shortcode or the admin information architecture.

## Shortcode

```text
[quickdonate_popup]
```

Legacy aliases still work and route to the same renderer:

```text
[paystack_donation_popup]
[quickgive_donation_popup]
```

## Admin Areas

- Settings
- Overview dashboard
- Donation log

## Included Docs

- `docs/overview.md`
- `docs/installation.md`
- `docs/configuration.md`
- `docs/shortcode.md`
- `docs/gateways.md`
- `docs/faq.md`

## Disclaimer

“QuickDonate is independently developed and is not affiliated with, endorsed by, or sponsored by Paystack, GiveWP, Donorbox, or any other third-party payment provider.”

## Security Notes

- Secret keys are stored in WordPress options and used only server-side.
- The frontend receives only the active public key.
- AJAX verification is nonce-protected.
- Donation success is only recorded after gateway verification succeeds.

## Development Notes

- Main plugin file: `quickdonate.php`
- Text domain: `quickdonate`
- Option name: `quickdonate_settings`
- Legacy option data is migrated automatically from `quickgive_settings`
