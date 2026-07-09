# Changelog

## 0.5.0 - 2026-07-09

- Wallet payout addresses are now fully managed from the WordPress dashboard. The address field in every wallet row is editable, and the address the admin saves is exactly what customers see at checkout, on the thank-you page, in order emails, and in generated QR codes.
- Removed all hardcoded/bundled wallet addresses and the save-handler logic that silently overrode admin-entered addresses with fixed ones.
- Removed the bundled static QR images (they encoded the old fixed addresses); all wallets now default to dynamic QR codes generated from the saved address.
- Added "Add wallet" and "Remove" controls so admins can accept any coin on any network.
- Added server-side address format validation for Bitcoin (legacy, P2SH, bech32), EVM chains (ETH/ERC20/BEP20/etc.), and TRON/TRC20, with a permissive sanity check for other networks. Rows with invalid addresses are disabled and flagged with an admin notice.
- Fixed the dead "Choose image" button for static QR images (now opens the WordPress media library).
- Deleted wallet rows no longer resurrect on the next save.
- Fixed literal `–` / `≈` escape sequences appearing as raw text in the checkout, thank-you page, and email output.
- Bumped version to 0.5.0.

## 0.3.0 - 2026-03-20

- Professional QR-centric payment UI redesign.
- Always use clean dynamic QR code instead of static wallet screenshots.
- Centered minimal dark card layout with prominent countdown timer and status badge.
- Real-time blockchain confirmation counter in payment details.
- Clean detail rows: network, reference, confirmations, wallet address.
- WooCommerce thank-you page shows "Complete your payment" until blockchain confirms.
- Order marked as received only after sufficient blockchain confirmations.
- Removed cluttered trust badges, kickers, and gradient noise.
- Responsive mobile-first design.

## 0.2.0 - 2026-03-20

- Cleaned plugin metadata for WordPress distribution.
- Added `readme.txt` with installation, FAQ, screenshots, changelog, and upgrade notice sections.
- Added release documentation for packaging and handoff.
- Added prepared screenshot source assets for admin, shortcode checkout, and WooCommerce payment screens.
- Improved packaging to output both `wp-crypto-direct-gateway.zip` and a versioned release zip.

## 0.1.0 - 2026-03-19

- Initial plugin scaffold.
- Direct wallet payment flow with QR code generation.
- WooCommerce gateway support.
- Admin wallet management and payment reconciliation pages.
- Automatic watcher services for supported chains.
- Brand settings and premium admin styling.