<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_WC_Gateway extends WC_Payment_Gateway
{
    private WCDG_Payment_Requests $payment_requests;

    private WCDG_Rates $rates;

    protected string $instructions = '';

    public function __construct()
    {
        $this->payment_requests = new WCDG_Payment_Requests();
        $this->rates = new WCDG_Rates();

        $this->id = 'wcdg_direct';
        $this->method_title = __('Crypto Direct Gateway', 'wp-crypto-direct-gateway');
        $this->method_description = __('Let customers scan a QR code and pay directly to your configured wallets.', 'wp-crypto-direct-gateway');
        $this->has_fields = true;
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Crypto payment', 'wp-crypto-direct-gateway'));
        $this->description = $this->get_option('description', __('Pay directly to one of our crypto wallets after checkout.', 'wp-crypto-direct-gateway'));
        $this->instructions = __('Scan the QR code on the next page or in your order email, then send the exact amount to the displayed wallet address.', 'wp-crypto-direct-gateway');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields(): void
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wp-crypto-direct-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Crypto Direct Gateway', 'wp-crypto-direct-gateway'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'wp-crypto-direct-gateway'),
                'type' => 'text',
                'default' => __('Crypto payment', 'wp-crypto-direct-gateway'),
            ),
            'description' => array(
                'title' => __('Description', 'wp-crypto-direct-gateway'),
                'type' => 'textarea',
                'default' => __('Scan the QR code after checkout and send payment directly to the displayed wallet address.', 'wp-crypto-direct-gateway'),
            ),
        );
    }

    public function payment_fields(): void
    {
        $wallets = WCDG_Settings::get_wallets();

        if ($this->description) {
            echo wp_kses_post(wpautop($this->description));
        }

        echo '<ul style="margin:0 0 1rem 1rem; list-style:disc; color:#46515c;">';
        echo '<li>' . esc_html__('A live crypto quote is generated at checkout.', 'wp-crypto-direct-gateway') . '</li>';
        echo '<li>' . esc_html__('You will receive a QR code, wallet address, and payment reference.', 'wp-crypto-direct-gateway') . '</li>';
        echo '<li>' . esc_html__('Supported watcher routes: BTC, ETH, USDT ERC20, USDT TRC20.', 'wp-crypto-direct-gateway') . '</li>';
        echo '</ul>';

        if (empty($wallets)) {
            echo '<p>' . esc_html__('No wallets are configured for crypto payments yet.', 'wp-crypto-direct-gateway') . '</p>';
            return;
        }

        echo '<p><label for="wcdg_checkout_wallet">' . esc_html__('Select asset and network', 'wp-crypto-direct-gateway') . '</label><br />';
        echo '<select id="wcdg_checkout_wallet" name="wcdg_checkout_wallet" style="width:100%; max-width: 360px;">';

        foreach ($wallets as $wallet) {
            printf(
                '<option value="%1$s">%2$s</option>',
                esc_attr($wallet['uid']),
                esc_html($wallet['name'] . ' (' . $wallet['symbol'] . ' - ' . $wallet['network'] . ')')
            );
        }

        echo '</select></p>';
    }

    public function validate_fields(): bool
    {
        $wallet_id = sanitize_key(wp_unslash($_POST['wcdg_checkout_wallet'] ?? ''));
        $wallet = WCDG_Settings::find_wallet_by_uid($wallet_id);

        if (! $wallet) {
            wc_add_notice(__('Please choose a valid cryptocurrency.', 'wp-crypto-direct-gateway'), 'error');
            return false;
        }

        return true;
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $wallet_id = sanitize_key(wp_unslash($_POST['wcdg_checkout_wallet'] ?? ''));
        $wallet = WCDG_Settings::find_wallet_by_uid($wallet_id);

        if (! $order || ! $wallet) {
            wc_add_notice(__('Could not initialize the crypto payment request.', 'wp-crypto-direct-gateway'), 'error');
            return array('result' => 'failure');
        }

        $quote = $this->rates->get_crypto_amount((float) $order->get_total(), $order->get_currency(), $wallet);
        if (is_wp_error($quote)) {
            wc_add_notice($quote->get_error_message(), 'error');
            return array('result' => 'failure');
        }

        $record = $this->payment_requests->create(array(
            'source' => 'woocommerce',
            'source_id' => $order_id,
            'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'customer_email' => $order->get_billing_email(),
            'fiat_amount' => (float) $order->get_total(),
            'fiat_currency' => $order->get_currency(),
            'crypto_amount' => $quote['crypto_amount'],
            'crypto_currency' => $wallet['symbol'],
            'wallet_label' => $wallet['name'],
            'wallet_address' => $wallet['address'],
            'wallet_network' => $wallet['network'],
            'required_confirmations' => $wallet['confirmations'],
            'rate_used' => $quote['rate'],
            'meta' => array(
                'order_key' => $order->get_order_key(),
                'wallet_uid' => $wallet['uid'],
            ),
        ));

        if (is_wp_error($record)) {
            wc_add_notice($record->get_error_message(), 'error');
            return array('result' => 'failure');
        }

        $order->update_meta_data('_wcdg_reference', $record['reference']);
        $order->update_meta_data('_wcdg_coin', $wallet['symbol']);
        $order->update_meta_data('_wcdg_wallet_id', $wallet['uid']);
        $order->save();
        $order->update_status('on-hold', __('Awaiting crypto payment.', 'wp-crypto-direct-gateway'));
        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }
}