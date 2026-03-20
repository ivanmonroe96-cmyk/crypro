<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Payment_Requests
{
    public function create(array $data)
    {
        global $wpdb;

        $now = current_time('mysql');
        $settings = WCDG_Settings::get_settings();
        $expires_at = gmdate('Y-m-d H:i:s', strtotime('+' . absint($settings['payment_window_minutes']) . ' minutes', current_time('timestamp', true)));
        $reference = $this->generate_reference();
        $payment_uri = $this->build_payment_uri($data['crypto_currency'], $data['wallet_address'], (float) $data['crypto_amount']);
        $meta = isset($data['meta']) && is_array($data['meta']) ? wp_json_encode($data['meta']) : null;

        $inserted = $wpdb->insert(
            WCDG_Database::table_name(),
            array(
                'reference' => $reference,
                'source' => sanitize_text_field($data['source'] ?? 'shortcode'),
                'source_id' => isset($data['source_id']) ? absint($data['source_id']) : null,
                'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
                'customer_email' => sanitize_email($data['customer_email'] ?? ''),
                'fiat_amount' => (float) $data['fiat_amount'],
                'fiat_currency' => strtoupper(sanitize_text_field($data['fiat_currency'] ?? 'USD')),
                'crypto_amount' => (float) $data['crypto_amount'],
                'crypto_currency' => strtoupper(sanitize_text_field($data['crypto_currency'] ?? '')),
                'wallet_label' => sanitize_text_field($data['wallet_label'] ?? ''),
                'wallet_address' => sanitize_text_field($data['wallet_address'] ?? ''),
                'wallet_network' => sanitize_text_field($data['wallet_network'] ?? ''),
                'rate_used' => (float) $data['rate_used'],
                'status' => sanitize_key($data['status'] ?? 'pending'),
                'payment_uri' => $payment_uri,
                'required_confirmations' => max(1, absint($data['required_confirmations'] ?? 1)),
                'meta' => $meta,
                'expires_at' => $expires_at,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%d', '%s', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        if (! $inserted) {
            return new WP_Error('wcdg_insert_failed', __('The payment request could not be created.', 'wp-crypto-direct-gateway'));
        }

        return $this->get_by_reference($reference);
    }

    public function get_by_reference(string $reference): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . WCDG_Database::table_name() . ' WHERE reference = %s', $reference),
            ARRAY_A
        );

        return $row ? $this->normalize_record($row) : null;
    }

    public function list_recent(int $limit = 50): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . WCDG_Database::table_name() . ' ORDER BY created_at DESC LIMIT %d', $limit),
            ARRAY_A
        );

        return array_map(array($this, 'normalize_record'), is_array($rows) ? $rows : array());
    }

    public function list_watchable(int $limit = 25): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . WCDG_Database::table_name() . " WHERE status IN ('pending', 'confirming') ORDER BY updated_at ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return array_map(array($this, 'normalize_record'), is_array($rows) ? $rows : array());
    }

    public function update_status(string $reference, string $status, array $payload = array())
    {
        global $wpdb;

        $allowed_statuses = array('pending', 'confirming', 'paid', 'expired', 'failed', 'cancelled');
        $status = sanitize_key($status);

        if (! in_array($status, $allowed_statuses, true)) {
            return new WP_Error('wcdg_invalid_status', __('Invalid payment status.', 'wp-crypto-direct-gateway'));
        }

        $record = $this->get_by_reference($reference);
        if (! $record) {
            return new WP_Error('wcdg_not_found', __('Payment request not found.', 'wp-crypto-direct-gateway'), array('status' => 404));
        }

        $update_data = array(
            'status' => $status,
            'tx_hash' => sanitize_text_field($payload['tx_hash'] ?? $record['tx_hash']),
            'confirmations' => absint($payload['confirmations'] ?? $record['confirmations']),
            'notes' => sanitize_textarea_field($payload['notes'] ?? $record['notes']),
            'updated_at' => current_time('mysql'),
        );
        $update_format = array('%s', '%s', '%d', '%s', '%s');

        if ($status === 'paid') {
            $update_data['paid_at'] = current_time('mysql');
            $update_format[] = '%s';
        }

        $updated = $wpdb->update(
            WCDG_Database::table_name(),
            $update_data,
            array('reference' => $reference),
            $update_format,
            array('%s')
        );

        if ($updated === false) {
            return new WP_Error('wcdg_update_failed', __('Payment status update failed.', 'wp-crypto-direct-gateway'));
        }

        $updated_record = $this->get_by_reference($reference);
        $this->sync_woocommerce_order($updated_record);

        return $updated_record;
    }

    public function maybe_expire(array $record): array
    {
        if ($record['status'] !== 'pending' && $record['status'] !== 'confirming') {
            return $record;
        }

        if (! empty($record['expires_at']) && strtotime($record['expires_at']) < current_time('timestamp', true)) {
            $updated = $this->update_status($record['reference'], 'expired');
            if (is_array($updated)) {
                return $updated;
            }
        }

        return $record;
    }

    private function generate_reference(): string
    {
        return strtoupper(wp_generate_password(12, false, false));
    }

    private function build_payment_uri(string $currency, string $address, float $amount): string
    {
        $scheme = strtolower($currency);
        $wallet = array(
            'symbol' => $currency,
        );
        $formatted_amount = WCDG_Crypto::format_amount($amount, $wallet);

        return sprintf('%s:%s?amount=%s', $scheme, rawurlencode($address), rawurlencode($formatted_amount));
    }

    private function sync_woocommerce_order(?array $record): void
    {
        if (! $record || $record['source'] !== 'woocommerce' || empty($record['source_id']) || ! function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order((int) $record['source_id']);
        if (! $order) {
            return;
        }

        if ($record['status'] === 'paid') {
            $order->payment_complete($record['tx_hash'] ?: 'wcdg:' . $record['reference']);
            $order->add_order_note(sprintf(__('Crypto payment %s confirmed.', 'wp-crypto-direct-gateway'), $record['reference']));
        } elseif ($record['status'] === 'expired') {
            $order->update_status('failed', __('Crypto payment window expired.', 'wp-crypto-direct-gateway'));
        } elseif ($record['status'] === 'confirming') {
            $order->update_status('on-hold', __('Crypto payment seen on-chain and awaiting final confirmations.', 'wp-crypto-direct-gateway'));
        }
    }

    private function normalize_record(array $row): array
    {
        if (! empty($row['meta'])) {
            $meta = json_decode((string) $row['meta'], true);
            $row['meta'] = is_array($meta) ? $meta : array();
        } else {
            $row['meta'] = array();
        }

        return $row;
    }
}