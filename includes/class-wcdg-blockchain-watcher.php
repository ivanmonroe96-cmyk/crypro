<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Blockchain_Watcher
{
    public const CRON_HOOK = 'wcdg_poll_blockchain_payments';

    public const CRON_SCHEDULE = 'wcdg_every_two_minutes';

    private WCDG_Payment_Requests $payment_requests;

    public function __construct(WCDG_Payment_Requests $payment_requests)
    {
        $this->payment_requests = $payment_requests;
    }

    public function hooks(): void
    {
        add_filter('cron_schedules', array($this, 'register_schedule'));
        add_action('init', array($this, 'ensure_schedule'));
        add_action(self::CRON_HOOK, array($this, 'poll_pending_payments'));
        add_action('admin_post_wcdg_rescan_payment', array($this, 'handle_manual_rescan'));
    }

    public static function activate(): void
    {
        $schedules = wp_get_schedules();
        if (isset($schedules[self::CRON_SCHEDULE]) && ! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);

        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
        }
    }

    public function register_schedule(array $schedules): array
    {
        $schedules[self::CRON_SCHEDULE] = array(
            'interval' => 2 * MINUTE_IN_SECONDS,
            'display' => __('Every 2 minutes', 'wp-crypto-direct-gateway'),
        );

        return $schedules;
    }

    public function ensure_schedule(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public function handle_manual_rescan(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to rescan payments.', 'wp-crypto-direct-gateway'));
        }

        check_admin_referer('wcdg_rescan_payment');

        $reference = strtoupper(sanitize_text_field(wp_unslash($_POST['reference'] ?? '')));
        if ($reference !== '') {
            $this->inspect_reference($reference);
        }

        wp_safe_redirect(add_query_arg(array(
            'page' => 'wcdg-payments',
            'rescanned' => '1',
        ), admin_url('admin.php')));
        exit;
    }

    public function poll_pending_payments(): void
    {
        $settings = WCDG_Settings::get_settings();
        if (empty($settings['watcher_enabled'])) {
            return;
        }

        foreach ($this->payment_requests->list_watchable() as $record) {
            $this->inspect_payment($record);
        }
    }

    public function inspect_reference(string $reference): ?array
    {
        $record = $this->payment_requests->get_by_reference($reference);
        if (! $record) {
            return null;
        }

        return $this->inspect_payment($record);
    }

    public function inspect_payment(array $record): array
    {
        $record = $this->payment_requests->maybe_expire($record);
        if (! in_array($record['status'], array('pending', 'confirming'), true)) {
            return $record;
        }

        $match = $this->find_chain_match($record);
        if (is_wp_error($match) || ! is_array($match) || empty($match['matched'])) {
            return $record;
        }

        $status = ((int) $match['confirmations'] >= (int) $record['required_confirmations']) ? 'paid' : 'confirming';
        $updated = $this->payment_requests->update_status($record['reference'], $status, array(
            'tx_hash' => (string) ($match['tx_hash'] ?? ''),
            'confirmations' => (int) ($match['confirmations'] ?? 0),
            'notes' => (string) ($match['notes'] ?? ''),
        ));

        return is_array($updated) ? $updated : $record;
    }

    private function find_chain_match(array $record)
    {
        if (WCDG_Crypto::is_bitcoin($record)) {
            return $this->lookup_bitcoin_payment($record);
        }

        if (WCDG_Crypto::is_eth_native($record)) {
            return $this->lookup_eth_payment($record);
        }

        if (WCDG_Crypto::is_usdt_erc20($record)) {
            return $this->lookup_erc20_payment($record);
        }

        if (WCDG_Crypto::is_usdt_trc20($record)) {
            return $this->lookup_trc20_payment($record);
        }

        return new WP_Error('wcdg_unsupported_chain', __('Unsupported chain for automatic watcher.', 'wp-crypto-direct-gateway'));
    }

    private function lookup_bitcoin_payment(array $record)
    {
        $wallet = WCDG_Crypto::get_wallet_for_record($record);
        if (! $wallet) {
            return new WP_Error('wcdg_wallet_missing', __('Wallet configuration missing for Bitcoin payment.', 'wp-crypto-direct-gateway'));
        }

        $transactions = $this->fetch_json('https://blockstream.info/api/address/' . rawurlencode($record['wallet_address']) . '/txs');
        if (is_wp_error($transactions) || ! is_array($transactions)) {
            return $transactions;
        }

        $tip_height = (int) $this->fetch_text('https://blockstream.info/api/blocks/tip/height');
        $best = null;

        foreach ($transactions as $transaction) {
            if (! is_array($transaction) || empty($transaction['vout']) || empty($transaction['txid'])) {
                continue;
            }

            $observed = 0.0;
            foreach ($transaction['vout'] as $vout) {
                if (($vout['scriptpubkey_address'] ?? '') !== $record['wallet_address']) {
                    continue;
                }

                $observed += ((float) ($vout['value'] ?? 0)) / 100000000;
            }

            if ($observed <= 0 || ! WCDG_Crypto::amount_matches($observed, (float) $record['crypto_amount'], $wallet)) {
                continue;
            }

            $status = is_array($transaction['status'] ?? null) ? $transaction['status'] : array();
            $confirmations = ! empty($status['confirmed']) && ! empty($status['block_height']) && $tip_height > 0 ? max(1, $tip_height - (int) $status['block_height'] + 1) : 0;
            $block_time = isset($status['block_time']) ? (int) $status['block_time'] : 0;

            if (! $this->is_transaction_time_eligible($record, $block_time)) {
                continue;
            }

            $best = array(
                'matched' => true,
                'tx_hash' => sanitize_text_field((string) $transaction['txid']),
                'confirmations' => $confirmations,
                'notes' => sprintf(__('Matched automatically via Bitcoin watcher for %.8f BTC.', 'wp-crypto-direct-gateway'), $observed),
            );

            if ($confirmations >= (int) $record['required_confirmations']) {
                break;
            }
        }

        return $best ?: array('matched' => false);
    }

    private function lookup_eth_payment(array $record)
    {
        $wallet = WCDG_Crypto::get_wallet_for_record($record);
        if (! $wallet) {
            return new WP_Error('wcdg_wallet_missing', __('Wallet configuration missing for Ethereum payment.', 'wp-crypto-direct-gateway'));
        }

        $data = $this->fetch_json('https://api.blockchair.com/ethereum/dashboards/address/' . rawurlencode($record['wallet_address']) . '?limit=20');
        if (is_wp_error($data)) {
            return $data;
        }

        $address_key = strtolower($record['wallet_address']);
        $calls = $data['data'][$address_key]['calls'] ?? array();
        $state = (int) ($data['context']['state'] ?? 0);

        return $this->find_evm_native_match($record, $wallet, $calls, $state);
    }

    private function lookup_erc20_payment(array $record)
    {
        $wallet = WCDG_Crypto::get_wallet_for_record($record);
        if (! $wallet) {
            return new WP_Error('wcdg_wallet_missing', __('Wallet configuration missing for ERC20 payment.', 'wp-crypto-direct-gateway'));
        }

        $url = sprintf(
            'https://api.blockchair.com/ethereum/erc-20/%s/dashboards/address/%s?limit=20',
            rawurlencode(WCDG_Crypto::ERC20_USDT_CONTRACT),
            rawurlencode($record['wallet_address'])
        );
        $data = $this->fetch_json($url);
        if (is_wp_error($data)) {
            return $data;
        }

        $address_key = strtolower($record['wallet_address']);
        $transactions = $data['data'][$address_key]['transactions'] ?? array();
        $state = (int) ($data['context']['state'] ?? 0);
        $best = null;

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            if (strtolower((string) ($transaction['recipient'] ?? '')) !== $address_key) {
                continue;
            }

            $observed = ((float) ($transaction['value'] ?? 0)) / pow(10, (int) ($transaction['token_decimals'] ?? 6));
            if (! WCDG_Crypto::amount_matches($observed, (float) $record['crypto_amount'], $wallet)) {
                continue;
            }

            $timestamp = strtotime((string) ($transaction['time'] ?? ''));
            if (! $this->is_transaction_time_eligible($record, $timestamp ?: 0)) {
                continue;
            }

            $block_id = (int) ($transaction['block_id'] ?? 0);
            $confirmations = $block_id > 0 && $state > 0 ? max(1, $state - $block_id + 1) : 0;
            $best = array(
                'matched' => true,
                'tx_hash' => sanitize_text_field((string) ($transaction['transaction_hash'] ?? '')),
                'confirmations' => $confirmations,
                'notes' => sprintf(__('Matched automatically via ERC20 watcher for %s USDT.', 'wp-crypto-direct-gateway'), WCDG_Crypto::format_amount($observed, $wallet)),
            );

            if ($confirmations >= (int) $record['required_confirmations']) {
                break;
            }
        }

        return $best ?: array('matched' => false);
    }

    private function lookup_trc20_payment(array $record)
    {
        $wallet = WCDG_Crypto::get_wallet_for_record($record);
        if (! $wallet) {
            return new WP_Error('wcdg_wallet_missing', __('Wallet configuration missing for TRC20 payment.', 'wp-crypto-direct-gateway'));
        }

        $url = sprintf(
            'https://api.trongrid.io/v1/accounts/%s/transactions/trc20?limit=20&only_confirmed=true',
            rawurlencode($record['wallet_address'])
        );
        $data = $this->fetch_json($url);
        if (is_wp_error($data)) {
            return $data;
        }

        $transactions = isset($data['data']) && is_array($data['data']) ? $data['data'] : array();
        $best = null;

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            $recipient = (string) ($transaction['to'] ?? $transaction['to_address'] ?? '');
            if ($recipient !== $record['wallet_address']) {
                continue;
            }

            $token_address = (string) ($transaction['token_info']['address'] ?? $transaction['token_info']['tokenId'] ?? '');
            if ($token_address !== '' && strcasecmp($token_address, WCDG_Crypto::TRC20_USDT_CONTRACT) !== 0) {
                continue;
            }

            $decimals = (int) ($transaction['token_info']['decimals'] ?? 6);
            $observed = ((float) ($transaction['value'] ?? $transaction['quant'] ?? 0)) / pow(10, $decimals);
            if (! WCDG_Crypto::amount_matches($observed, (float) $record['crypto_amount'], $wallet)) {
                continue;
            }

            $timestamp = (int) ($transaction['block_timestamp'] ?? $transaction['block_ts'] ?? 0);
            if ($timestamp > 1000000000000) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            if (! $this->is_transaction_time_eligible($record, $timestamp)) {
                continue;
            }

            $best = array(
                'matched' => true,
                'tx_hash' => sanitize_text_field((string) ($transaction['transaction_id'] ?? $transaction['transactionHash'] ?? '')),
                'confirmations' => 1,
                'notes' => sprintf(__('Matched automatically via TRC20 watcher for %s USDT.', 'wp-crypto-direct-gateway'), WCDG_Crypto::format_amount($observed, $wallet)),
            );
            break;
        }

        return $best ?: array('matched' => false);
    }

    private function find_evm_native_match(array $record, array $wallet, array $calls, int $state): array
    {
        $best = null;
        $address = strtolower($record['wallet_address']);

        foreach ($calls as $call) {
            if (! is_array($call)) {
                continue;
            }

            if (strtolower((string) ($call['recipient'] ?? '')) !== $address) {
                continue;
            }

            $observed = ((float) ($call['value'] ?? 0)) / pow(10, 18);
            if (! WCDG_Crypto::amount_matches($observed, (float) $record['crypto_amount'], $wallet)) {
                continue;
            }

            $timestamp = strtotime((string) ($call['time'] ?? ''));
            if (! $this->is_transaction_time_eligible($record, $timestamp ?: 0)) {
                continue;
            }

            $block_id = (int) ($call['block_id'] ?? 0);
            $confirmations = $block_id > 0 && $state > 0 ? max(1, $state - $block_id + 1) : 0;
            $best = array(
                'matched' => true,
                'tx_hash' => sanitize_text_field((string) ($call['transaction_hash'] ?? '')),
                'confirmations' => $confirmations,
                'notes' => sprintf(__('Matched automatically via Ethereum watcher for %s ETH.', 'wp-crypto-direct-gateway'), WCDG_Crypto::format_amount($observed, $wallet)),
            );

            if ($confirmations >= (int) $record['required_confirmations']) {
                break;
            }
        }

        return $best ?: array('matched' => false);
    }

    private function is_transaction_time_eligible(array $record, int $timestamp): bool
    {
        if ($timestamp <= 0) {
            return true;
        }

        $created_at = strtotime((string) ($record['created_at'] ?? '')) ?: 0;
        $expires_at = strtotime((string) ($record['expires_at'] ?? '')) ?: 0;

        if ($created_at > 0 && $timestamp < ($created_at - 600)) {
            return false;
        }

        if ($expires_at > 0 && $timestamp > ($expires_at + DAY_IN_SECONDS)) {
            return false;
        }

        return true;
    }

    private function fetch_json(string $url)
    {
        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            return new WP_Error('wcdg_invalid_watcher_response', __('Watcher API returned an invalid response.', 'wp-crypto-direct-gateway'));
        }

        return $body;
    }

    private function fetch_text(string $url): string
    {
        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'text/plain',
            ),
        ));

        if (is_wp_error($response)) {
            return '';
        }

        return trim((string) wp_remote_retrieve_body($response));
    }
}