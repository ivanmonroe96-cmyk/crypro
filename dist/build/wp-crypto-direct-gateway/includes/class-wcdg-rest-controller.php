<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_REST_Controller
{
    private WCDG_Payment_Requests $payment_requests;

    private WCDG_Rates $rates;

    private WCDG_Blockchain_Watcher $watcher;

    public function __construct(WCDG_Payment_Requests $payment_requests, WCDG_Rates $rates, WCDG_Blockchain_Watcher $watcher)
    {
        $this->payment_requests = $payment_requests;
        $this->rates = $rates;
        $this->watcher = $watcher;
    }

    public function hooks(): void
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes(): void
    {
        register_rest_route('wcdg/v1', '/payment-requests', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_payment_request'),
                'permission_callback' => '__return_true',
            ),
        ));

        register_rest_route('wcdg/v1', '/payment-requests/(?P<reference>[A-Z0-9]+)/status', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_payment_status'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_payment_status'),
                'permission_callback' => array($this, 'can_update_payment_status'),
            ),
        ));
    }

    public function create_payment_request(WP_REST_Request $request)
    {
        $amount = (float) $request->get_param('amount');
        $currency = strtoupper(sanitize_text_field((string) $request->get_param('currency')));
        $wallet_id = sanitize_key((string) $request->get_param('wallet_id'));
        $coin = strtoupper(sanitize_text_field((string) $request->get_param('coin')));
        $wallet = $wallet_id !== '' ? WCDG_Settings::find_wallet_by_uid($wallet_id) : WCDG_Settings::find_wallet($coin);

        if ($amount <= 0) {
            return new WP_Error('wcdg_invalid_amount', __('A positive fiat amount is required.', 'wp-crypto-direct-gateway'), array('status' => 400));
        }

        if (! $wallet || empty($wallet['address'])) {
            return new WP_Error('wcdg_wallet_not_found', __('The selected wallet is not configured.', 'wp-crypto-direct-gateway'), array('status' => 400));
        }

        $quote = $this->rates->get_crypto_amount($amount, $currency, $wallet);
        if (is_wp_error($quote)) {
            return $quote;
        }

        $record = $this->payment_requests->create(array(
            'source' => sanitize_text_field((string) $request->get_param('source')) ?: 'shortcode',
            'source_id' => absint($request->get_param('source_id')),
            'customer_name' => sanitize_text_field((string) $request->get_param('customer_name')),
            'customer_email' => sanitize_email((string) $request->get_param('customer_email')),
            'fiat_amount' => $amount,
            'fiat_currency' => $currency,
            'crypto_amount' => $quote['crypto_amount'],
            'crypto_currency' => $wallet['symbol'],
            'wallet_label' => $wallet['name'],
            'wallet_address' => $wallet['address'],
            'wallet_network' => $wallet['network'],
            'required_confirmations' => $wallet['confirmations'],
            'rate_used' => $quote['rate'],
            'meta' => array(
                'wallet_uid' => $wallet['uid'],
                'network' => $wallet['network'],
            ),
        ));

        if (is_wp_error($record)) {
            return $record;
        }

        return rest_ensure_response($this->prepare_record_for_response($record));
    }

    public function get_payment_status(WP_REST_Request $request)
    {
        $reference = strtoupper(sanitize_text_field((string) $request->get_param('reference')));
        $record = $this->watcher->inspect_reference($reference) ?: $this->payment_requests->get_by_reference($reference);

        if (! $record) {
            return new WP_Error('wcdg_not_found', __('Payment request not found.', 'wp-crypto-direct-gateway'), array('status' => 404));
        }

        return rest_ensure_response($this->prepare_record_for_response($this->payment_requests->maybe_expire($record)));
    }

    public function update_payment_status(WP_REST_Request $request)
    {
        $reference = strtoupper(sanitize_text_field((string) $request->get_param('reference')));
        $status = sanitize_key((string) $request->get_param('status'));
        $record = $this->payment_requests->update_status($reference, $status, array(
            'tx_hash' => sanitize_text_field((string) $request->get_param('tx_hash')),
            'confirmations' => absint($request->get_param('confirmations')),
            'notes' => sanitize_textarea_field((string) $request->get_param('notes')),
        ));

        if (is_wp_error($record)) {
            return $record;
        }

        return rest_ensure_response($this->prepare_record_for_response($record));
    }

    public function can_update_payment_status(WP_REST_Request $request): bool
    {
        $signature = sanitize_text_field((string) $request->get_header('x-wcdg-signature'));
        $settings = WCDG_Settings::get_settings();

        return $signature !== '' && hash_equals((string) $settings['callback_secret'], $signature);
    }

    private function prepare_record_for_response(array $record): array
    {
        $settings = WCDG_Settings::get_settings();

        return array(
            'reference' => $record['reference'],
            'status' => $record['status'],
            'fiat_amount' => (float) $record['fiat_amount'],
            'fiat_currency' => $record['fiat_currency'],
            'crypto_amount' => (float) $record['crypto_amount'],
            'crypto_currency' => $record['crypto_currency'],
            'wallet_label' => $record['wallet_label'],
            'wallet_uid' => $record['meta']['wallet_uid'] ?? '',
            'wallet_address' => $record['wallet_address'],
            'wallet_network' => $record['wallet_network'],
            'payment_uri' => $record['payment_uri'],
            'payment_qr_url' => $this->get_qr_url($record['payment_uri'], (string) $settings['qr_provider']),
            'tx_hash' => $record['tx_hash'],
            'confirmations' => (int) $record['confirmations'],
            'required_confirmations' => (int) $record['required_confirmations'],
            'expires_at' => $record['expires_at'],
            'paid_at' => $record['paid_at'],
            'created_at' => $record['created_at'],
        );
    }

    private function get_qr_url(string $payload, string $provider): string
    {
        if ($provider === 'quickchart') {
            return add_query_arg(array(
                'text' => $payload,
                'size' => 280,
            ), 'https://quickchart.io/qr');
        }

        return '';
    }
}