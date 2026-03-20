<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Rates
{
    public function get_crypto_amount(float $fiat_amount, string $fiat_currency, array $wallet)
    {
        $fiat_currency = strtolower($fiat_currency);
        $coin_id = $wallet['coingecko_id'] ?? '';

        if ($coin_id === '') {
            return new WP_Error('wcdg_missing_coin_id', __('This wallet is missing a CoinGecko ID.', 'wp-crypto-direct-gateway'));
        }

        $transient_key = 'wcdg_rate_' . md5($coin_id . '_' . $fiat_currency);
        $rate = get_transient($transient_key);

        if (! is_numeric($rate)) {
            $response = wp_remote_get(
                add_query_arg(
                    array(
                        'ids' => $coin_id,
                        'vs_currencies' => $fiat_currency,
                    ),
                    'https://api.coingecko.com/api/v3/simple/price'
                ),
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'Accept' => 'application/json',
                    ),
                )
            );

            if (is_wp_error($response)) {
                return $response;
            }

            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            $rate = $body[$coin_id][$fiat_currency] ?? null;

            if (! is_numeric($rate) || (float) $rate <= 0) {
                return new WP_Error('wcdg_invalid_rate', __('Unable to retrieve a live rate for the selected coin.', 'wp-crypto-direct-gateway'));
            }

            set_transient($transient_key, (float) $rate, 5 * MINUTE_IN_SECONDS);
        }

        $rate = (float) $rate;

        return array(
            'rate' => $rate,
            'crypto_amount' => WCDG_Crypto::normalize_amount($fiat_amount / $rate, $wallet),
        );
    }
}