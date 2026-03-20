# Wallet Address Intake

To finish production setup, send one row per wallet using this format:

| Symbol | Coin Name | Network | Wallet Address | CoinGecko ID | Confirmations |
| --- | --- | --- | --- | --- | --- |
| BTC | Bitcoin | Bitcoin | `bc1...` | bitcoin | 1 |
| ETH | Ethereum | Ethereum | `0x...` | ethereum | 12 |
| USDT | Tether | TRC20 | `T...` | tether | 1 |

## What I need from you

1. Every coin you want to accept.
2. The exact network for each coin.
3. The wallet address for that network.
4. Preferred confirmation count for each network.
5. Whether you want the same wallet reused for all payments or separate wallets per network.

## Recommended first release set

1. BTC on Bitcoin
2. ETH on Ethereum
3. USDT on TRC20
4. USDT on ERC20 if you also want Ethereum-based stablecoin payments
5. LTC on Litecoin if you want a low-fee option

## Production decision still open

Automatic on-chain verification can be wired in one of two ways:

1. External watcher service posts to the plugin callback endpoint.
2. The plugin later integrates directly with a blockchain API provider.

The callback endpoint is already scaffolded. Once you send the addresses and tell me which monitoring route you want, I can wire the next phase around your exact chains.