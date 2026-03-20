<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Database
{
    public static function activate(): void
    {
        self::install();
        self::maybe_seed_options();
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reference VARCHAR(64) NOT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'shortcode',
            source_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(191) NULL,
            customer_email VARCHAR(191) NULL,
            fiat_amount DECIMAL(20, 8) NOT NULL,
            fiat_currency VARCHAR(10) NOT NULL,
            crypto_amount DECIMAL(30, 12) NOT NULL,
            crypto_currency VARCHAR(20) NOT NULL,
            wallet_label VARCHAR(191) NOT NULL,
            wallet_address VARCHAR(255) NOT NULL,
            wallet_network VARCHAR(64) NULL,
            rate_used DECIMAL(30, 12) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_uri TEXT NULL,
            tx_hash VARCHAR(191) NULL,
            confirmations INT UNSIGNED NOT NULL DEFAULT 0,
            required_confirmations INT UNSIGNED NOT NULL DEFAULT 1,
            notes TEXT NULL,
            meta LONGTEXT NULL,
            expires_at DATETIME NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reference (reference),
            KEY source (source, source_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wcdg_payments';
    }

    private static function maybe_seed_options(): void
    {
        $defaults = WCDG_Settings::default_settings();
        $current = get_option(WCDG_Settings::OPTION_KEY);

        if (! is_array($current)) {
            add_option(WCDG_Settings::OPTION_KEY, $defaults);
            return;
        }

        update_option(WCDG_Settings::OPTION_KEY, wp_parse_args($current, $defaults));
    }
}