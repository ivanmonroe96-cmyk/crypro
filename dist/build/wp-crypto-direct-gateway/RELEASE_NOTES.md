# Release Notes

## Version 0.3.0

Major frontend redesign for a professional, QR-centric payment experience.

### Highlights

- **Clean QR codes only** — Always uses the dynamic QuickChart QR (amount-aware, clean) instead of static wallet screenshots.
- **Professional card layout** — Centered 480px dark card with minimal design, white-framed QR hero, and clear detail rows.
- **Prominent timer** — Large monospace countdown in the status header bar.
- **Confirmation tracking** — Real-time `X / Y` confirmation counter visible to the customer.
- **Improved order flow** — WooCommerce thank-you page now says "Complete your payment" until blockchain confirmations are received.
- **Responsive** — Mobile-first design that adapts cleanly to all screen sizes.
- Packaging script now creates:
  - `dist/wp-crypto-direct-gateway.zip`
  - `dist/wp-crypto-direct-gateway-0.3.0.zip`

### Operational notes

- Backend payment logic unchanged — order stays on-hold until confirmations, then payment_complete fires.
- Automatic watcher support remains for BTC, ETH, USDT ERC20, and USDT TRC20.
- WooCommerce email instructions updated to always use dynamic QR.

### Recommended release checks

1. Install the packaged zip on your WordPress site.
2. Create a test payment and verify the clean QR code displays.
3. Confirm the countdown timer and confirmation counter update in real-time.
4. Check WooCommerce thank-you page shows the correct message before and after payment.

---

## Version 0.2.0

This release prepares WP Crypto Direct Gateway for distribution.

### Highlights

- WordPress plugin headers cleaned for release packaging.
- Added WordPress-style `readme.txt` metadata and listing content.
- Added changelog and release notes documents.
- Added prepared screenshot source assets for listing pages and product documentation.
- Packaging script now creates:
  - `dist/wp-crypto-direct-gateway.zip`
  - `dist/wp-crypto-direct-gateway-0.2.0.zip`