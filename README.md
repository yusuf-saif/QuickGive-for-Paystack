# QuickGive-for-Paystack
A lightweight WordPress plugin that lets site owners collect one-time donations through a shortcode-triggered Paystack popup.


# QuickGive for Paystack

A lightweight WordPress plugin for collecting one-time donations through a shortcode-triggered Paystack popup checkout.

## Overview

QuickGive for Paystack is built for site owners who want a simple way to accept donations without installing a large fundraising platform.

The plugin allows an admin to configure Paystack keys, default currency, preset donation amounts, and a thank-you message from the WordPress dashboard. A shortcode can then be placed anywhere on the site to render a donation button that opens a popup checkout flow.

## MVP Features

- WordPress admin settings page
- Paystack public key and secret key configuration
- Test mode and live mode support
- Default currency setting
- Preset donation amounts
- Optional custom donation amount
- Custom thank-you message
- Shortcode-generated donation button
- Popup donation modal
- Donor email collection
- Paystack popup checkout integration
- Server-side payment verification
- Success and failure states
- Mobile-responsive frontend experience

## Planned Use Case

This plugin is designed for:

- nonprofits
- community projects
- churches
- personal causes
- small organizations
- any WordPress site that needs a simple donation flow

## Product Goal

Enable WordPress site owners to collect one-time donations quickly and securely using Paystack, with minimal setup and without needing a full donation management platform.

## How It Works

1. The site admin installs and activates the plugin.
2. The admin opens the plugin settings page in WordPress.
3. The admin enters:
   - Paystack public key
   - Paystack secret key
   - mode (test or live)
   - default currency
   - preset donation amounts
   - thank-you message
4. The plugin provides a shortcode.
5. The admin places the shortcode on any page or post.
6. A donor clicks the donation button.
7. A popup modal opens with donation options.
8. The donor selects or enters an amount, adds email, and proceeds to payment.
9. Paystack checkout handles payment.
10. The plugin verifies the transaction server-side before confirming success.
11. The donor sees a thank-you message after verified payment.

## Shortcode

```shortcode
[paystack_donation_popup]
````

Future versions may support shortcode attributes such as custom button text and styling options.

## Proposed Settings

### General

* Enable plugin
* Mode: Test / Live
* Default currency
* Button label
* Thank-you message

### Paystack

* Public key
* Secret key

### Donation Options

* Preset donation amounts
* Enable custom amount
* Minimum donation amount
* Maximum donation amount

## Technical Notes

* The Paystack **public key** is used on the frontend.
* The Paystack **secret key** is stored and used only on the backend.
* Successful transactions must be verified server-side before being marked as successful.
* Frontend payment status must never be trusted without backend verification.
* Plugin assets should only load when the shortcode is present.

## Security Principles

* Secret key must never be exposed in JavaScript or browser source
* Admin inputs must be sanitized and validated
* Backend requests must use nonce protection
* Settings page must be restricted to authorized users
* Payment verification must happen server-side
* The plugin should follow WordPress coding standards

## Suggested Plugin Structure

```text
quickgive-for-paystack/
├── quickgive-for-paystack.php
├── README.md
├── readme.txt
├── uninstall.php
├── includes/
│   ├── class-plugin.php
│   ├── class-admin-settings.php
│   ├── class-shortcode.php
│   ├── class-paystack-handler.php
│   └── class-donation-logger.php
├── assets/
│   ├── css/
│   │   └── frontend.css
│   └── js/
│       └── frontend.js
├── templates/
│   └── donation-popup.php
└── languages/
```

## Installation

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress admin dashboard.
3. Open the plugin settings page.
4. Add your Paystack credentials and donation settings.
5. Copy the shortcode and place it where you want the donation button to appear.

## MVP Scope

### In Scope

* one-time donations
* admin settings page
* preset donation amounts
* optional custom amount
* shortcode-triggered popup
* donor email collection
* Paystack popup checkout
* backend transaction verification
* thank-you message

### Out of Scope

* recurring donations
* donor CRM
* email receipts
* campaign management
* advanced analytics
* multiple donation forms
* Gutenberg block
* advanced design builder

## Roadmap Ideas

* recurring donations
* multiple donation forms
* donation logs dashboard
* donor receipts
* campaign-specific shortcodes
* Gutenberg block
* webhook handling
* multilingual support
* styling customization

## Status

This project is currently in MVP planning and initial implementation phase.


