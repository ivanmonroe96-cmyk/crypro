<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Crypto
{
    public const ERC20_USDT_CONTRACT = '0xdac17f958d2ee523a2206206994597c13d831ec7';

    public const TRC20_USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    public static function get_precision(array $wallet): int
    {
        $symbol = strtoupper((string) ($wallet['symbol'] ?? ''));
        $network = strtoupper((string) ($wallet['network'] ?? ''));

        if ($symbol === 'BTC') {
            return 8;
        }

        if ($symbol === 'USDT') {
            return 6;
        }

        if ($symbol === 'ETH' || $network === 'ERC20') {
            return 8;
        }

        return 8;
    }

    public static function normalize_amount(float $amount, array $wallet): float
    {
        return round($amount, self::get_precision($wallet));
    }

    public static function format_amount(float $amount, array $wallet): string
    {
        $precision = self::get_precision($wallet);

        return rtrim(rtrim(number_format(self::normalize_amount($amount, $wallet), $precision, '.', ''), '0'), '.');
    }

    public static function minimum_acceptable_amount(float $expected, array $wallet): float
    {
        $precision = self::get_precision($wallet);
        $unitTolerance = pow(10, -1 * $precision) * 2;
        $percentageTolerance = $expected * 0.005;

        return max(0, $expected - max($unitTolerance, $percentageTolerance));
    }

    public static function amount_matches(float $observed, float $expected, array $wallet): bool
    {
        return $observed >= self::minimum_acceptable_amount($expected, $wallet);
    }

    public static function get_wallet_for_record(array $record): ?array
    {
        $wallet_uid = sanitize_key((string) ($record['meta']['wallet_uid'] ?? ''));
        if ($wallet_uid !== '') {
            $wallet = WCDG_Settings::find_wallet_by_uid($wallet_uid);
            if ($wallet) {
                return $wallet;
            }
        }

        return WCDG_Settings::find_wallet_by_symbol_and_network((string) ($record['crypto_currency'] ?? ''), (string) ($record['wallet_network'] ?? ''));
    }

    public static function is_bitcoin(array $record): bool
    {
        return strtoupper((string) ($record['crypto_currency'] ?? '')) === 'BTC';
    }

    public static function is_eth_native(array $record): bool
    {
        return strtoupper((string) ($record['crypto_currency'] ?? '')) === 'ETH';
    }

    public static function is_usdt_erc20(array $record): bool
    {
        return strtoupper((string) ($record['crypto_currency'] ?? '')) === 'USDT' && strtoupper((string) ($record['wallet_network'] ?? '')) === 'ERC20';
    }

    public static function is_usdt_trc20(array $record): bool
    {
        return strtoupper((string) ($record['crypto_currency'] ?? '')) === 'USDT' && strtoupper((string) ($record['wallet_network'] ?? '')) === 'TRC20';
    }
}