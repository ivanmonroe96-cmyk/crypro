# Static wallet QR images

The plugin no longer bundles any wallet QR images, and it never bundles wallet
addresses. Every payout address is entered by the site administrator under
**Crypto Gateway → Wallets** in the WordPress dashboard.

By default each wallet uses **dynamic QR mode**: the QR code is generated per
payment request from the address you saved, so it always matches what the
customer sees.

If you prefer a static image for a wallet:

1. Upload your own QR image via the **Choose image** button in the wallet row
   (or paste any image URL into the *Static QR image* field).
2. Switch that row's *QR mode* to **Static image**.
3. Make sure the image encodes the exact same address as the row — the plugin
   cannot verify the contents of an image.

If you replace a wallet address later, remember to replace its static QR image
too, or switch the row back to dynamic mode.
