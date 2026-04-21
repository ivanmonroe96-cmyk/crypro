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
        $settings = WCDG_Settings::get_settings();

        if ($this->description) {
            echo wp_kses_post(wpautop($this->description));
        }

        if (empty($wallets)) {
            echo '<p>' . esc_html__('No wallets are configured for crypto payments yet.', 'wp-crypto-direct-gateway') . '</p>';
            return;
        }

        $first_uid = $wallets[0]['uid'];

        echo '<p class="wcdg-checkout-label">' . esc_html__('Choose a wallet to pay with — the payment QR code will appear after checkout.', 'wp-crypto-direct-gateway') . '</p>';
        echo '<input type="hidden" name="wcdg_checkout_wallet" id="wcdg_checkout_wallet" value="' . esc_attr($first_uid) . '" />';
        echo '<div class="wcdg-checkout-wallets" role="radiogroup" aria-label="' . esc_attr__('Select cryptocurrency wallet', 'wp-crypto-direct-gateway') . '">';

        foreach ($wallets as $wallet) {
            $uid     = esc_attr($wallet['uid']);
            $is_first = $wallet['uid'] === $first_uid;

            $brand_color = sanitize_hex_color((string) ($settings['brand_primary_color'] ?? '')) ?: '#d6ff4b';
            $brand_rgb = $this->hex_to_rgb($brand_color);
            $wallet_accent = $this->get_checkout_wallet_accent($wallet);
            $wallet_accent_rgb = $this->hex_to_rgb($wallet_accent);
            $selected_attr = $is_first ? 'wcdg-checkout-wallet-card wcdg-wallet-selected' : 'wcdg-checkout-wallet-card';

            echo '<div class="' . esc_attr($selected_attr) . '" data-uid="' . $uid . '" role="radio" aria-checked="' . ($is_first ? 'true' : 'false') . '" tabindex="' . ($is_first ? '0' : '-1') . '" style="--wcdg-brand:' . esc_attr($brand_color) . '; --wcdg-brand-rgb:' . esc_attr($brand_rgb) . '; --wcdg-wallet-accent:' . esc_attr($wallet_accent) . '; --wcdg-wallet-accent-rgb:' . esc_attr($wallet_accent_rgb) . ';">';

            echo '<div class="wcdg-checkout-wallet-visual">';
            echo $this->get_checkout_wallet_icon_markup($wallet);
            echo '</div>';

            echo '<div class="wcdg-checkout-wallet-info">';
            echo '<div class="wcdg-checkout-wallet-header">';
            echo '<span class="wcdg-checkout-wallet-symbol">' . esc_html($wallet['symbol']) . '</span>';
            echo '<span class="wcdg-checkout-wallet-name">' . esc_html($wallet['name']) . '</span>';
            echo '</div>';
            echo '<div class="wcdg-checkout-wallet-network">' . esc_html($wallet['network']) . '</div>';
            echo '<div class="wcdg-checkout-wallet-address-wrap">';
            echo '<code class="wcdg-checkout-wallet-address">' . esc_html($wallet['address']) . '</code>';
            echo '<button type="button" class="wcdg-checkout-copy-addr" data-address="' . esc_attr($wallet['address']) . '" title="' . esc_attr__('Copy address', 'wp-crypto-direct-gateway') . '">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
            echo '</button>';
            echo '</div>';
            echo '</div>';

            echo '</div>'; // .wcdg-checkout-wallet-card
        }

        echo '</div>'; // .wcdg-checkout-wallets
        echo '<p class="wcdg-checkout-copy-notice" aria-live="polite"></p>';

        // Inline script: card selection + copy
        ?>
        <script>
        (function () {
            var container = document.querySelector('.wcdg-checkout-wallets');
            var hidden    = document.getElementById('wcdg_checkout_wallet');
            var notice    = document.querySelector('.wcdg-checkout-copy-notice');
            if (!container || !hidden) return;

            container.querySelectorAll('.wcdg-checkout-wallet-card').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    // Don't steal click from copy button
                    if (e.target.closest('.wcdg-checkout-copy-addr')) return;
                    container.querySelectorAll('.wcdg-checkout-wallet-card').forEach(function (c) {
                        c.classList.remove('wcdg-wallet-selected');
                        c.setAttribute('aria-checked', 'false');
                        c.setAttribute('tabindex', '-1');
                    });
                    card.classList.add('wcdg-wallet-selected');
                    card.setAttribute('aria-checked', 'true');
                    card.setAttribute('tabindex', '0');
                    hidden.value = card.dataset.uid;
                });

                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
                });
            });

            container.querySelectorAll('.wcdg-checkout-copy-addr').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var addr = btn.dataset.address;
                    if (!addr) return;
                    navigator.clipboard.writeText(addr).then(function () {
                        if (notice) { notice.textContent = '<?php echo esc_js(__('Address copied!', 'wp-crypto-direct-gateway')); ?>'; setTimeout(function () { notice.textContent = ''; }, 3000); }
                    }).catch(function () {
                        if (notice) notice.textContent = '<?php echo esc_js(__('Could not copy — please copy manually.', 'wp-crypto-direct-gateway')); ?>';
                    });
                });
            });
        }());
        </script>
        <?php
    }

    private function get_checkout_wallet_icon_markup(array $wallet): string
    {
        $symbol = strtoupper((string) ($wallet['symbol'] ?? ''));

        switch ($symbol) {
            case 'BTC':
                return '<span class="wcdg-checkout-wallet-icon" aria-hidden="true"><svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" focusable="false"><circle cx="32" cy="32" r="26" fill="#F7931A"/><path fill="#fff" d="M37.2 28.8c2.2-1.1 3.5-3 3.2-6-.5-4-3.9-5.4-8.5-5.8V12h-3.1v4h-2.4V12h-3.1v4h-6v3.7h2.6c1 0 1.3.3 1.5 1.1v18.5c-.1.5-.5 1-1.5 1h-2.6V44h6v4h3.1v-4h2.4v4h3.1v-4c5.2-.3 8.8-1.9 9.4-6.9.5-4.1-1.6-6.4-4.1-7.3Zm-8.6-8.1c2.5 0 8-.6 8 3.7 0 4.1-5.5 3.6-8 3.6h-2.8v-7.3h2.8Zm0 19.6h-2.8v-8.4h2.8c3.3 0 8.8-.9 8.8 4.1 0 5-5.5 4.3-8.8 4.3Z"/></svg></span>';
            case 'ETH':
                return '<span class="wcdg-checkout-wallet-icon" aria-hidden="true"><svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" focusable="false"><path fill="#627EEA" d="M32 8 19 32l13 7 13-7L32 8Z"/><path fill="#8A92B2" d="M32 8v31l13-7L32 8Z"/><path fill="#627EEA" d="M32 56 19 35l13 8 13-8L32 56Z"/><path fill="#8A92B2" d="M32 56V43l13-8L32 56Z"/></svg></span>';
            case 'USDT':
                return '<span class="wcdg-checkout-wallet-icon" aria-hidden="true"><svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" focusable="false"><circle cx="32" cy="32" r="26" fill="#26A17B"/><path fill="#fff" d="M18 20.5h28v5.7H35.8v4.3c7 .3 12.2 1.8 12.2 3.7 0 2.1-7.2 3.8-16 3.8S16 36.3 16 34.2c0-1.9 5.2-3.4 12.2-3.7v-4.3H18v-5.7Zm12.5 5.7v11.9c.5 0 1 .1 1.5.1s1 0 1.5-.1V26.2h-3Z"/></svg></span>';
            case 'TRX':
                return '<span class="wcdg-checkout-wallet-icon" aria-hidden="true"><svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M16 18.5 47.5 24 34.3 46.5 16 18.5Z" fill="none" stroke="#EF0027" stroke-width="3.2" stroke-linejoin="round"/><path d="M16 18.5 31.1 30.7 47.5 24" fill="none" stroke="#EF0027" stroke-width="3.2" stroke-linejoin="round"/><path d="M31.1 30.7 34.3 46.5" fill="none" stroke="#EF0027" stroke-width="3.2" stroke-linejoin="round"/></svg></span>';
        }

        return '<span class="wcdg-checkout-wallet-icon wcdg-checkout-wallet-icon-generic" aria-hidden="true"><span class="wcdg-checkout-wallet-icon-fallback">' . esc_html(substr($symbol !== '' ? $symbol : __('COIN', 'wp-crypto-direct-gateway'), 0, 4)) . '</span></span>';
    }

    private function get_checkout_wallet_accent(array $wallet): string
    {
        $symbol = strtoupper((string) ($wallet['symbol'] ?? ''));

        switch ($symbol) {
            case 'BTC':
                return '#F7931A';
            case 'ETH':
                return '#627EEA';
            case 'USDT':
                return '#26A17B';
            case 'TRX':
                return '#EF0027';
        }

        return '#334155';
    }

    private function hex_to_rgb(string $hex): string
    {
        $normalized = ltrim($hex, '#');

        if (strlen($normalized) === 3) {
            $normalized = $normalized[0] . $normalized[0] . $normalized[1] . $normalized[1] . $normalized[2] . $normalized[2];
        }

        if (! preg_match('/^[a-f0-9]{6}$/i', $normalized)) {
            return '51,65,85';
        }

        return implode(',', array(
            (string) hexdec(substr($normalized, 0, 2)),
            (string) hexdec(substr($normalized, 2, 2)),
            (string) hexdec(substr($normalized, 4, 2)),
        ));
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