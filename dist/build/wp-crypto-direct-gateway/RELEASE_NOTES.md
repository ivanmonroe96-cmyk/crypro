# Release Notes

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

### Operational notes

- The plugin continues to support QR code scanning and wallet copy actions.
- WooCommerce email payment instructions remain branded and styled.
- Automatic watcher support remains limited to supported public endpoints for BTC, ETH, USDT ERC20, and USDT TRC20.

### Recommended release checks

1. Install the packaged zip in a clean WordPress test site.
2. Confirm the admin styles load on both `Crypto Gateway` and `Payments` screens.
3. Confirm WooCommerce email styling renders well in your email client mix.
4. Export or rasterize the SVG screenshot assets into PNGs if you need WordPress.org-compatible listing screenshots.