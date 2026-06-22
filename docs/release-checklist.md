# QuickDonate — Release Checklist

Use this checklist before every WordPress.org submission or manual release.

## Pre-Release

- [ ] Tested on a clean WordPress install
- [ ] `WP_DEBUG` set to `true` — no PHP errors, warnings, or notices
- [ ] All version numbers aligned (plugin header, constant, readme.txt stable tag, CHANGELOG)
- [ ] No secret keys exposed in frontend markup or JavaScript
- [ ] Package contains no `.git`, `dist/`, `.DS_Store`, `__MACOSX`, `node_modules/`, or build artifacts

## Functionality

- [ ] Plugin activates without errors
- [ ] Settings page loads and all tabs render correctly
- [ ] Settings save works (all tabs: general, emails, gateways, logs, advanced)
- [ ] Shortcode `[quickdonate_popup]` renders the donation button and popup
- [ ] Legacy shortcodes `[paystack_donation_popup]` and `[quickgive_donation_popup]` render correctly
- [ ] Preset amount buttons display and are selectable
- [ ] Custom amount input works when enabled
- [ ] Donor email validation works
- [ ] Paystack checkout opens and completes successfully
- [ ] Server-side transaction verification works
- [ ] Donation is logged with correct status, amount, reference, and gateway
- [ ] Thank-you message displays after successful verification
- [ ] Thank-you email sends (when enabled in settings)
- [ ] Email placeholders (`{amount}`, `{currency}`, `{email}`, `{reference}`, `{site_name}`) resolve correctly
- [ ] Success/failure page redirects work (when configured)

## Admin

- [ ] Dashboard overview shows correct stats
- [ ] Donation log displays entries with pagination
- [ ] Status filter tabs work (All, Successful, Failed, Pending)
- [ ] No unescaped output in admin pages

## Frontend

- [ ] Popup opens and closes correctly
- [ ] Modal overlay covers full viewport
- [ ] Body scroll is locked when modal is open
- [ ] Focus trap works within modal
- [ ] Escape key closes modal
- [ ] Multiple shortcode instances on one page work independently
- [ ] Preset amount selection clears custom input and vice versa
- [ ] Loading spinner shows during processing
- [ ] Error messages display correctly

## Elementor

- [ ] Modal overlay is relocated to `document.body`
- [ ] Popup works inside Elementor page layouts
- [ ] No overflow or z-index containment issues

## Security

- [ ] No secret keys in source code, options output, or JS
- [ ] AJAX nonce verification works
- [ ] All user input is sanitized
- [ ] All output is escaped
- [ ] `uninstall.php` cleans all plugin data and legacy data

## Package

- [ ] ZIP contains only: `quickdonate.php`, `readme.txt`, `README.md`, `CHANGELOG.md`, `uninstall.php`, `includes/`, `assets/`, `templates/`, `docs/`, `languages/`
- [ ] No duplicate nested plugin folders
- [ ] No macOS or Windows metadata files
- [ ] Version numbers match across all files
