=== WP Crypto Direct Gateway ===
Contributors: wp-crypto-direct-gateway
Tags: cryptocurrency, bitcoin, ethereum, usdt, woocommerce, payment gateway, qr code
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Direct crypto payment gateway for WordPress and WooCommerce with QR-based checkout, wallet copy actions, automatic chain watching, and branded customer payment instructions.

== Description ==

WP Crypto Direct Gateway lets merchants accept cryptocurrency directly to their own wallet addresses without relying on a custodial processor.

Features include:

* QR-based crypto checkout for WordPress pages and WooCommerce orders
* Wallet address copy action and exact-amount copy action
* Automatic payment watching for BTC, ETH, USDT ERC20, and USDT TRC20
* Network-aware wallet selection for assets that exist on multiple chains
* Branded payment experience with merchant name, tagline, colors, and optional logo
* WooCommerce thank-you page payment panel with live status updates
* WooCommerce order email instructions with QR code and wallet details
* Manual admin reconciliation screen and callback endpoint for custom monitors

This plugin is designed for direct wallet settlement, not custodial conversion or payout services.

== Installation ==

1. Upload the plugin zip through the WordPress Plugins screen, or upload the `wp-crypto-direct-gateway` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open `Crypto Gateway` in the WordPress admin area.
4. Paste your wallet addresses manually, then review confirmations, brand settings, and the callback secret.
5. If you use WooCommerce, enable `Crypto Direct Gateway` under WooCommerce payment settings.

== Frequently Asked Questions ==

= Does this plugin generate QR codes? =

Yes. Each payment request includes a QR code, exact amount, wallet address, and copy actions.

= Can customers copy the wallet address? =

Yes. The payment interface includes direct copy actions for both the wallet address and the quoted crypto amount.

= Does it support multiple networks for the same asset? =

Yes. Assets such as USDT can be configured on both TRC20 and ERC20 independently.

= Does it detect payments automatically? =

Yes. The built-in watcher polls supported public explorer APIs for BTC, ETH, USDT ERC20, and USDT TRC20.

= Is WooCommerce required? =

No. The plugin includes a standalone shortcode flow as well as WooCommerce support.

== Screenshots ==

1. Premium admin settings screen for wallets, branding, watcher controls, and packaging guidance.
2. Customer-facing shortcode payment form with QR code, exact amount, wallet copy action, and countdown timer.
3. WooCommerce order-received payment panel with live status updates and branded crypto instructions.
4. WooCommerce order email payment instructions with QR code and wallet details.

== Changelog ==

= 0.5.1 =

* Maintenance release: rebuilt distribution package and version bump.

= 0.5.0 =

* Wallet payout addresses are now fully managed from the WordPress dashboard: every address field is editable and whatever the admin saves is exactly what customers see at checkout, on the thank-you page, in emails, and in QR codes.
* Removed all bundled/hardcoded wallet addresses and the code path that overrode admin-entered addresses on save.
* Removed bundled static QR images; wallets default to dynamic QR codes generated from the saved address.
* Added Add wallet / Remove wallet controls so any coin and network can be configured.
* Added per-network address format validation (Bitcoin, EVM chains, TRON); invalid addresses are disabled with an admin notice instead of reaching checkout.
* Fixed the wallet static QR "Choose image" button (now opens the WordPress media library).
* Deleted wallet rows no longer reappear after saving.
* Fixed literal "–"/"≈" text appearing in checkout, thank-you page, and email output.

= 0.3.0 =

* Professional QR-centric payment UI redesign.
* Always display clean dynamic QR code instead of static wallet screenshots.
* Centered card layout with prominent countdown timer and status badge.
* Real-time blockchain confirmation counter (X / Y) in payment details.
* WooCommerce thank-you page now instructs users to complete payment before marking order as received.
* Order only shown as received after sufficient blockchain confirmations.
* Responsive mobile-first design.

= 0.2.0 =

* Cleaned plugin headers and distribution metadata.
* Added WordPress.org-style readme file.
* Added release notes and changelog files.
* Added prepared SVG screenshot source assets for plugin marketing and listing pages.
* Improved packaging script to generate both stable and versioned installable zip files.

= 0.1.0 =

* Initial direct crypto payment gateway release with WooCommerce support, QR checkout, wallet management, and automatic watcher services.

== Upgrade Notice ==

= 0.5.0 =

Important: wallet payout addresses are now fully admin-managed. After updating, open Crypto Gateway settings and verify every wallet row contains YOUR address.

= 0.3.0 =

Major UI redesign with professional QR-centric payment display, confirmation tracking, and improved WooCommerce order flow.

= 0.2.0 =

Recommended distribution update with cleaned metadata, release documentation, prepared screenshot assets, and improved packaging output.