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

        echo '<p class="wcdg-checkout-label">' . esc_html__('Choose a wallet to pay with — scan the QR code or copy the address.', 'wp-crypto-direct-gateway') . '</p>';
        echo '<input type="hidden" name="wcdg_checkout_wallet" id="wcdg_checkout_wallet" value="' . esc_attr($first_uid) . '" />';
        echo '<div class="wcdg-checkout-wallets" role="radiogroup" aria-label="' . esc_attr__('Select cryptocurrency wallet', 'wp-crypto-direct-gateway') . '">';

        foreach ($wallets as $wallet) {
            $uid     = esc_attr($wallet['uid']);
            $is_first = $wallet['uid'] === $first_uid;

            // QR source: use static image if available, otherwise generate from QuickChart
            if (! empty($wallet['static_qr_url'])) {
                $qr_src = esc_url($wallet['static_qr_url']);
            } else {
                $qr_src = esc_url(add_query_arg(array(
                    'text' => $wallet['address'],
                    'size' => 180,
                    'centerImageUrl' => '',
                ), 'https://quickchart.io/qr'));
            }

            $brand_color = esc_attr($settings['brand_primary_color'] ?: '#d6ff4b');
            $selected_attr = $is_first ? 'wcdg-checkout-wallet-card wcdg-wallet-selected' : 'wcdg-checkout-wallet-card';

            echo '<div class="' . esc_attr($selected_attr) . '" data-uid="' . $uid . '" role="radio" aria-checked="' . ($is_first ? 'true' : 'false') . '" tabindex="' . ($is_first ? '0' : '-1') . '" style="--wcdg-brand:' . $brand_color . ';">';

            // Left: coin info + address
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

            // Right: QR code
            echo '<div class="wcdg-checkout-wallet-qr">';
            echo '<img src="' . $qr_src . '" alt="' . esc_attr(sprintf(__('%s QR code', 'wp-crypto-direct-gateway'), $wallet['symbol'] . ' ' . $wallet['network'])) . '" width="120" height="120" loading="lazy" />';
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