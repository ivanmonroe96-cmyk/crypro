<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_WooCommerce_Gateway
{
    private WCDG_Payment_Requests $payment_requests;

    private WCDG_Rates $rates;

    public function __construct(WCDG_Payment_Requests $payment_requests, WCDG_Rates $rates)
    {
        $this->payment_requests = $payment_requests;
        $this->rates = $rates;
    }

    public function hooks(): void
    {
        add_action('plugins_loaded', array($this, 'register_gateway_class'), 20);
        add_filter('woocommerce_payment_gateways', array($this, 'register_gateway'));
        add_action('woocommerce_thankyou_wcdg_direct', array($this, 'render_order_payment_box'));
        add_action('woocommerce_email_after_order_table', array($this, 'render_email_payment_instructions'), 10, 4);
    }

    public function register_gateway_class(): void
    {
        if (! class_exists('WC_Payment_Gateway')) {
            return;
        }

        if (! class_exists('WCDG_WC_Gateway')) {
            require_once WCDG_PLUGIN_DIR . 'includes/class-wcdg-wc-gateway.php';
        }
    }

    public function register_gateway(array $gateways): array
    {
        if (class_exists('WCDG_WC_Gateway')) {
            $gateways[] = 'WCDG_WC_Gateway';
        }

        return $gateways;
    }

    public function render_order_payment_box(int $order_id): void
    {
        if (! function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $reference = $order->get_meta('_wcdg_reference');
        if (! $reference) {
            return;
        }

        $record = $this->payment_requests->get_by_reference((string) $reference);
        if (! $record) {
            return;
        }

        $record = wcdg_plugin()->inspect_payment_reference($record['reference']);
        $record = $this->payment_requests->maybe_expire($record);
        $this->render_live_payment_panel($record, $order_id);
    }

    public function render_email_payment_instructions($order, $sent_to_admin, $plain_text, $email): void
    {
        if (! $order instanceof WC_Order || $sent_to_admin || $plain_text) {
            return;
        }

        if ($order->get_payment_method() !== 'wcdg_direct' || $order->is_paid()) {
            return;
        }

        $reference = $order->get_meta('_wcdg_reference');
        if (! $reference) {
            return;
        }

        $record = $this->payment_requests->get_by_reference((string) $reference);
        if (! $record) {
            return;
        }

        $wallet = WCDG_Crypto::get_wallet_for_record($record);
        $settings = WCDG_Settings::get_settings();
        $dynamic_qr_url = add_query_arg(array(
            'text' => $record['payment_uri'],
            'size' => 220,
        ), 'https://quickchart.io/qr');
        $qr_url = ($wallet && ($wallet['qr_display_mode'] ?? 'dynamic') === 'static' && ! empty($wallet['static_qr_url'])) ? esc_url($wallet['static_qr_url']) : $dynamic_qr_url;
        $logo = ! empty($settings['brand_logo_url']) ? '<img src="' . esc_url($settings['brand_logo_url']) . '" alt="' . esc_attr($settings['merchant_name']) . '" style="display:block; max-width:160px; max-height:52px; height:auto; margin:0 0 14px;" />' : '';

        echo '<div style="margin:24px 0; border:1px solid #d8e3ee; border-radius:24px; overflow:hidden; background:#ffffff;">';
        echo '<div style="padding:28px; background:linear-gradient(140deg,#0f1f2b,#13293a 58%,#183548); color:#f4f6f8;">';
        echo $logo;
        echo '<p style="margin:0 0 6px; font-size:12px; letter-spacing:.12em; text-transform:uppercase; color:' . esc_attr($settings['brand_primary_color']) . ';">' . esc_html($settings['merchant_name']) . '</p>';
        echo '<h2 style="margin:0 0 12px; color:#ffffff; font-size:26px; line-height:1.2;">' . esc_html__('Complete your crypto payment', 'wp-crypto-direct-gateway') . '</h2>';
        echo '<p style="margin:0; color:rgba(244,246,248,.86);">' . esc_html__('Scan the QR code or copy the wallet address below. Your order will update automatically when the payment is detected on-chain.', 'wp-crypto-direct-gateway') . '</p>';
        echo '</div>';
        echo '<div style="padding:24px 28px;">';
        echo '<table cellspacing="0" cellpadding="0" style="width:100%; border-collapse:collapse; margin:0 0 20px;">';
        echo '<tr><td style="padding:0 0 12px; color:#617282; font-size:13px; text-transform:uppercase; letter-spacing:.08em;">' . esc_html__('Reference', 'wp-crypto-direct-gateway') . '</td><td style="padding:0 0 12px; text-align:right; color:#10202c; font-weight:700;">' . esc_html($record['reference']) . '</td></tr>';
        echo '<tr><td style="padding:0 0 12px; color:#617282; font-size:13px; text-transform:uppercase; letter-spacing:.08em;">' . esc_html__('Amount', 'wp-crypto-direct-gateway') . '</td><td style="padding:0 0 12px; text-align:right; color:#10202c; font-weight:700;">' . esc_html($record['crypto_amount'] . ' ' . $record['crypto_currency']) . '</td></tr>';
        echo '<tr><td style="padding:0; color:#617282; font-size:13px; text-transform:uppercase; letter-spacing:.08em;">' . esc_html__('Network', 'wp-crypto-direct-gateway') . '</td><td style="padding:0; text-align:right; color:#10202c; font-weight:700;">' . esc_html($record['wallet_label'] . ' - ' . $record['wallet_network']) . '</td></tr>';
        echo '</table>';
        echo '<div style="display:block; text-align:center; margin:0 0 20px;"><img src="' . esc_url($qr_url) . '" alt="' . esc_attr__('Crypto QR code', 'wp-crypto-direct-gateway') . '" style="display:inline-block; max-width:220px; height:auto; border-radius:14px; background:#fff; border:1px solid #d8e3ee; padding:12px;" /></div>';
        echo '<div style="padding:16px; border-radius:16px; background:#f6f9fc; border:1px solid #e2eaf2; margin-bottom:18px;">';
        echo '<p style="margin:0 0 8px; color:#617282; font-size:13px; text-transform:uppercase; letter-spacing:.08em;">' . esc_html__('Wallet address', 'wp-crypto-direct-gateway') . '</p>';
        echo '<p style="margin:0; word-break:break-all; color:#10202c; font-family:monospace; font-size:14px;">' . esc_html($record['wallet_address']) . '</p>';
        echo '</div>';
        echo '<p style="margin:0;"><a href="' . esc_url($order->get_checkout_order_received_url()) . '" style="display:inline-block; padding:12px 18px; border-radius:999px; text-decoration:none; background:' . esc_attr($settings['brand_primary_color']) . '; color:#09131b; font-weight:700;">' . esc_html__('Open payment status page', 'wp-crypto-direct-gateway') . '</a></p>';
        echo '</div>';
        echo '</div>';
    }

    private function render_live_payment_panel(array $record, int $order_id): void
    {
        $settings = WCDG_Settings::get_settings();
        $wallet = WCDG_Crypto::get_wallet_for_record($record);
        $dynamic_qr_url = add_query_arg(array(
            'text' => $record['payment_uri'],
            'size' => 280,
        ), 'https://quickchart.io/qr');
        $qr_url = ($wallet && ($wallet['qr_display_mode'] ?? 'dynamic') === 'static' && ! empty($wallet['static_qr_url'])) ? esc_url($wallet['static_qr_url']) : $dynamic_qr_url;

        echo '<section class="wcdg-payment-form wcdg-woo-box wcdg-live-payment" data-reference="' . esc_attr($record['reference']) . '">';
        echo '<div class="wcdg-payment-request">';
        echo '<div class="wcdg-card">';
        echo '<div class="wcdg-panel-header">';
        echo '<div>';
        echo '<p class="wcdg-kicker">' . esc_html($settings['merchant_name']) . '</p>';
        echo '<h2>' . esc_html__('Complete your crypto payment', 'wp-crypto-direct-gateway') . '</h2>';
        echo '</div>';
        echo '<div class="wcdg-trust-points"><span>' . esc_html__('Live chain watcher', 'wp-crypto-direct-gateway') . '</span><span>' . esc_html__('QR-ready', 'wp-crypto-direct-gateway') . '</span><span>' . esc_html__('Order #' . $order_id) . '</span></div>';
        echo '</div>';
        echo '<p>' . esc_html__('Scan the QR code, copy the wallet address if needed, and keep this page open while the status updates automatically.', 'wp-crypto-direct-gateway') . '</p>';
        echo '<div class="wcdg-request-header"><div><p class="wcdg-eyebrow">' . esc_html__('Payment reference', 'wp-crypto-direct-gateway') . '</p><strong class="wcdg-reference">' . esc_html($record['reference']) . '</strong></div><div class="wcdg-request-meta"><span class="wcdg-status wcdg-status-' . esc_attr($record['status']) . '">' . esc_html(ucfirst($record['status'])) . '</span><span class="wcdg-countdown"></span></div></div>';
        echo '<div class="wcdg-grid wcdg-grid-request"><div class="wcdg-qr-wrap"><img src="' . esc_url($qr_url) . '" alt="' . esc_attr__('Crypto payment QR code', 'wp-crypto-direct-gateway') . '" class="wcdg-qr" /></div><div>';
        echo '<p><strong>' . esc_html__('Send exactly', 'wp-crypto-direct-gateway') . '</strong> <span class="wcdg-crypto-amount">' . esc_html($record['crypto_amount'] . ' ' . $record['crypto_currency']) . '</span></p>';
        echo '<p><strong>' . esc_html__('Quote', 'wp-crypto-direct-gateway') . '</strong> <span class="wcdg-fiat-amount">' . esc_html(number_format((float) $record['fiat_amount'], 2) . ' ' . $record['fiat_currency']) . '</span></p>';
        echo '<p><strong>' . esc_html__('Wallet', 'wp-crypto-direct-gateway') . '</strong> <span class="wcdg-wallet-label">' . esc_html($record['wallet_label'] . ' - ' . $record['wallet_network']) . '</span></p>';
        echo '<p><strong>' . esc_html__('Address', 'wp-crypto-direct-gateway') . '</strong></p>';
        echo '<textarea class="wcdg-address" readonly rows="3">' . esc_textarea($record['wallet_address']) . '</textarea>';
        echo '<div class="wcdg-actions-row"><button type="button" class="wcdg-copy-address">' . esc_html__('Copy address', 'wp-crypto-direct-gateway') . '</button><button type="button" class="wcdg-copy-amount">' . esc_html__('Copy amount', 'wp-crypto-direct-gateway') . '</button><a class="wcdg-open-wallet" href="' . esc_url($record['payment_uri']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open wallet app', 'wp-crypto-direct-gateway') . '</a></div>';
        echo '<p><strong>' . esc_html__('Expires', 'wp-crypto-direct-gateway') . '</strong> <span class="wcdg-expires-at">' . esc_html((string) $record['expires_at']) . '</span></p>';
        echo '<ol class="wcdg-steps"><li>' . esc_html__('Confirm the network matches the one shown here before sending.', 'wp-crypto-direct-gateway') . '</li><li>' . esc_html__('Send the exact amount to avoid mismatches on a reused address.', 'wp-crypto-direct-gateway') . '</li><li>' . esc_html__('Status will refresh automatically while the watcher polls the chain.', 'wp-crypto-direct-gateway') . '</li></ol>';
        echo '<div class="wcdg-message" aria-live="polite"></div>';
        echo '</div></div></div></div></section>';
    }
}