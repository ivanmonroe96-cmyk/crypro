# WP Crypto Direct Gateway

WordPress plugin for direct crypto payments to your own wallet addresses, with a modern QR-based payment flow, automatic on-chain watching, and WooCommerce support.

## What is included

- Standalone shortcode payment form with QR code generation
- WooCommerce payment gateway when WooCommerce is active
- Automatic blockchain polling for BTC, ETH, USDT ERC20, and USDT TRC20
- Network-aware wallet selection so the same asset can exist on multiple chains safely
- QR code scan flow, wallet-address copy action, amount copy action, and wallet-app deep links
- WooCommerce thank-you page payment panel with live status polling
- WooCommerce order email payment instructions with QR code and wallet details
- Admin settings page for wallet addresses and gateway configuration
- Payment request tracking table in the WordPress database
- Admin payments screen for manual confirmation and reconciliation
- REST API callback endpoint for external blockchain watchers or custom monitors
- Live fiat-to-crypto conversion using CoinGecko rates
- Multi-network wallet support, including USDT on TRC20 and ERC20
- Brand controls for merchant name, tagline, colors, and optional logo URL

## Preloaded wallets

- BTC on Bitcoin: `bc1qev9qvwxennyypmth024jndwlqqh7ft9mzjnapr`
- ETH on Ethereum: `0x08CA715802e9B7Be5F21D8e3aB67Ab515eDde955`
- USDT on TRC20: `TGkyrQigqKChK4KSfEjTdSRBC2XZboKfAL`
- USDT on ERC20: `0x08CA715802e9B7Be5F21D8e3aB67Ab515eDde955`

## WooCommerce flow

1. Customer selects `Crypto Direct Gateway` at checkout.
2. Customer chooses the asset and network.
3. The plugin creates a live quote and redirects to the order received page.
4. The customer sees a QR code, wallet address, exact amount, and payment reference.
5. The watcher polls supported public explorer APIs and updates the order automatically when a matching payment is found.
6. The on-hold order email includes the same payment instructions and QR code.

## Plugin entry file

- `wp-crypto-direct-gateway.php`

## Shortcode

Use this on any page or post:

```text
[wcdg_payment_form amount="49.99" currency="USD"]
```

## Admin setup

1. Install the plugin in your WordPress site.
2. Go to `Crypto Gateway` in WordPress admin.
3. Review the preloaded wallets and confirmations.
4. Set your brand tagline, colors, and optional logo URL.
5. Save the generated callback secret.
6. Leave `Automatic watcher` enabled if you want the built-in polling flow.
7. If you use WooCommerce, enable `Crypto Direct Gateway` under WooCommerce payments.

## Callback flow

Your watcher or reconciliation service can update payment status by `POST`ing to:

```text
/wp-json/wcdg/v1/payment-requests/{REFERENCE}/status
```

Required header:

```text
X-WCDG-Signature: <callback secret>
```

Example JSON body:

```json
{
	"status": "paid",
	"tx_hash": "0x123...",
	"confirmations": 12,
	"notes": "Matched by external watcher"
}
```

## Notes

- This version is designed around direct wallet payments, not custodial processors.
- QR generation currently uses QuickChart.
- Wallet selection is network-aware, so duplicate symbols like USDT can be offered safely on more than one chain.
- Automatic polling currently uses public explorer endpoints: Blockstream for BTC, Blockchair for ETH/ERC20, and TronGrid for TRC20.
- Static wallet reuse can still create ambiguity when two customers send the same amount to the same address near the same time. Unique addresses per order are still the stronger production architecture.
