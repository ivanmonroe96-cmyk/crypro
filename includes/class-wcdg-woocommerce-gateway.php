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
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'filter_order_received_text'), 10, 2);
    }

    public function filter_order_received_text(string $text, $order): string
    {
        if (! $order instanceof WC_Order || $order->get_payment_method() !== 'wcdg_direct') {
            return $text;
        }

        if ($order->is_paid()) {
            return __('Your crypto payment has been confirmed. Thank you for your order!', 'wp-crypto-direct-gateway');
        }

        return __('Complete your crypto payment below. Your order will be confirmed once the payment receives sufficient blockchain confirmations.', 'wp-crypto-direct-gateway');
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

        $settings = WCDG_Settings::get_settings();
        $qr_url = add_query_arg(array(
            'text' => $record['payment_uri'],
            'size' => 220,
        ), 'https://quickchart.io/qr');
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
        $qr_url = add_query_arg(array(
            'text' => $record['payment_uri'],
            'size' => 280,
        ), 'https://quickchart.io/qr');
        $status = esc_attr($record['status']);
        $confirmations = (int) ($record['confirmations'] ?? 0);
        $required = (int) ($record['required_confirmations'] ?? 1);

        echo '<section class="wcdg-payment-form wcdg-woo-box wcdg-live-payment" data-reference="' . esc_attr($record['reference']) . '" data-status="' . $status . '">';
        echo '<div class="wcdg-payment-request">';
        echo '<div class="wcdg-card">';

        // Header
        echo '<div class="wcdg-pay-header">';
        echo '<span class="wcdg-status wcdg-status-' . $status . '">' . esc_html(ucfirst($record['status'])) . '</span>';
        echo '<span class="wcdg-countdown"></span>';
        echo '</div>';

        // Body
        echo '<div class="wcdg-pay-body">';
        echo '<div class="wcdg-pay-qr"><div class="wcdg-qr-frame"><img src="' . esc_url($qr_url) . '" alt="' . esc_attr__('Payment QR code', 'wp-crypto-direct-gateway') . '" class="wcdg-qr" /></div></div>';
        echo '<div class="wcdg-pay-amount">';
        echo '<div class="wcdg-pay-amount-label">' . esc_html__('Send exactly', 'wp-crypto-direct-gateway') . '</div>';
        echo '<span class="wcdg-crypto-amount">' . esc_html($record['crypto_amount'] . ' ' . $record['crypto_currency']) . '</span>';
        echo '<span class="wcdg-fiat-amount">\u2248 ' . esc_html(number_format((float) $record['fiat_amount'], 2) . ' ' . $record['fiat_currency']) . '</span>';
        echo '</div>';

        // Details
        echo '<div class="wcdg-pay-details">';
        echo '<div class="wcdg-pay-row"><span class="wcdg-pay-row-label">' . esc_html__('Network', 'wp-crypto-direct-gateway') . '</span><span class="wcdg-pay-row-value wcdg-wallet-label">' . esc_html($record['wallet_label'] . ' \u2013 ' . $record['wallet_network']) . '</span></div>';
        echo '<div class="wcdg-pay-row"><span class="wcdg-pay-row-label">' . esc_html__('Reference', 'wp-crypto-direct-gateway') . '</span><span class="wcdg-pay-row-value wcdg-reference">' . esc_html($record['reference']) . '</span></div>';
        echo '<div class="wcdg-pay-row"><span class="wcdg-pay-row-label">' . esc_html__('Confirmations', 'wp-crypto-direct-gateway') . '</span><span class="wcdg-pay-row-value wcdg-confirmations">' . esc_html($confirmations . ' / ' . $required) . '</span></div>';
        echo '</div>';

        // Address
        echo '<div class="wcdg-pay-address-section">';
        echo '<span class="wcdg-pay-row-label">' . esc_html__('Wallet address', 'wp-crypto-direct-gateway') . '</span>';
        echo '<textarea class="wcdg-address" readonly rows="2">' . esc_textarea($record['wallet_address']) . '</textarea>';
        echo '</div>';

        // Actions
        echo '<div class="wcdg-pay-actions">';
        echo '<button type="button" class="wcdg-copy-address">' . esc_html__('Copy address', 'wp-crypto-direct-gateway') . '</button>';
        echo '<button type="button" class="wcdg-copy-amount">' . esc_html__('Copy amount', 'wp-crypto-direct-gateway') . '</button>';
        echo '<a class="wcdg-open-wallet" href="' . esc_url($record['payment_uri']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open wallet', 'wp-crypto-direct-gateway') . '</a>';
        echo '</div>';

        echo '</div>'; // .wcdg-pay-body

        // Footer
        echo '<div class="wcdg-pay-footer">';
        echo '<ol class="wcdg-steps">';
        echo '<li>' . esc_html__('Scan the QR code with your wallet app.', 'wp-crypto-direct-gateway') . '</li>';
        echo '<li>' . esc_html__('Send the exact amount shown above.', 'wp-crypto-direct-gateway') . '</li>';
        echo '<li>' . esc_html__('Keep this page open \u2013 status updates automatically.', 'wp-crypto-direct-gateway') . '</li>';
        echo '</ol>';
        echo '<div class="wcdg-message" aria-live="polite"></div>';
        echo '<span class="wcdg-expires-at" hidden>' . esc_html((string) $record['expires_at']) . '</span>';
        echo '</div>';

        echo '</div></div></section>';
    }
}