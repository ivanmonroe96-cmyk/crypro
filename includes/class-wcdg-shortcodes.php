<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Shortcodes
{
    public function hooks(): void
    {
        add_shortcode('wcdg_payment_form', array($this, 'render_payment_form'));
    }

    public function render_payment_form(array $atts = array()): string
    {
        $wallets = WCDG_Settings::get_wallets();
        $settings = WCDG_Settings::get_settings();

        if (empty($wallets)) {
            return '<p>' . esc_html__('Crypto payments are not available yet.', 'wp-crypto-direct-gateway') . '</p>';
        }

        $atts = shortcode_atts(array(
            'amount' => '',
            'currency' => $settings['default_fiat_currency'],
            'title' => __('Pay with crypto', 'wp-crypto-direct-gateway'),
            'description' => $settings['brand_tagline'] ?: __('Send payment directly to a wallet address.', 'wp-crypto-direct-gateway'),
        ), $atts, 'wcdg_payment_form');

        ob_start();
        ?>
        <section class="wcdg-payment-form">
            <div class="wcdg-card">
                <div class="wcdg-form-card">
                    <h2><?php echo esc_html($atts['title']); ?></h2>
                    <p><?php echo esc_html($atts['description']); ?></p>
                    <form class="wcdg-form">
                        <div class="wcdg-grid">
                            <label>
                                <span><?php esc_html_e('Amount', 'wp-crypto-direct-gateway'); ?></span>
                                <input type="number" step="0.01" min="0.01" name="amount" value="<?php echo esc_attr((string) $atts['amount']); ?>" required />
                            </label>
                            <label>
                                <span><?php esc_html_e('Currency', 'wp-crypto-direct-gateway'); ?></span>
                                <input type="text" name="currency" maxlength="10" value="<?php echo esc_attr((string) $atts['currency']); ?>" required />
                            </label>
                        </div>
                        <label>
                            <span><?php esc_html_e('Cryptocurrency', 'wp-crypto-direct-gateway'); ?></span>
                            <select name="wallet_id" required>
                                <?php foreach ($wallets as $wallet) : ?>
                                    <option value="<?php echo esc_attr($wallet['uid']); ?>"><?php echo esc_html($wallet['name'] . ' (' . $wallet['symbol'] . ' \u2013 ' . $wallet['network'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Name (optional)', 'wp-crypto-direct-gateway'); ?></span>
                            <input type="text" name="customer_name" autocomplete="name" />
                        </label>
                        <label>
                            <span><?php esc_html_e('Email (optional)', 'wp-crypto-direct-gateway'); ?></span>
                            <input type="email" name="customer_email" autocomplete="email" />
                        </label>
                        <button type="submit"><?php esc_html_e('Generate payment request', 'wp-crypto-direct-gateway'); ?></button>
                    </form>
                    <div class="wcdg-message" aria-live="polite"></div>
                </div>
            </div>
            <div class="wcdg-payment-request" hidden>
                <div class="wcdg-card">
                    <div class="wcdg-pay-header">
                        <span class="wcdg-status wcdg-status-pending"></span>
                        <span class="wcdg-countdown"></span>
                    </div>
                    <div class="wcdg-pay-body">
                        <div class="wcdg-pay-qr"><div class="wcdg-qr-frame"><img alt="<?php esc_attr_e('Payment QR', 'wp-crypto-direct-gateway'); ?>" class="wcdg-qr" /></div></div>
                        <div class="wcdg-pay-amount">
                            <div class="wcdg-pay-amount-label"><?php esc_html_e('Send exactly', 'wp-crypto-direct-gateway'); ?></div>
                            <span class="wcdg-crypto-amount"></span>
                            <span class="wcdg-fiat-amount"></span>
                        </div>
                        <div class="wcdg-pay-details">
                            <div class="wcdg-pay-row"><span class="wcdg-pay-row-label"><?php esc_html_e('Network', 'wp-crypto-direct-gateway'); ?></span><span class="wcdg-pay-row-value wcdg-wallet-label"></span></div>
                            <div class="wcdg-pay-row"><span class="wcdg-pay-row-label"><?php esc_html_e('Reference', 'wp-crypto-direct-gateway'); ?></span><span class="wcdg-pay-row-value wcdg-reference"></span></div>
                            <div class="wcdg-pay-row"><span class="wcdg-pay-row-label"><?php esc_html_e('Confirmations', 'wp-crypto-direct-gateway'); ?></span><span class="wcdg-pay-row-value wcdg-confirmations">0 / 1</span></div>
                        </div>
                        <div class="wcdg-pay-address-section">
                            <span class="wcdg-pay-row-label"><?php esc_html_e('Wallet address', 'wp-crypto-direct-gateway'); ?></span>
                            <textarea class="wcdg-address" readonly rows="2"></textarea>
                        </div>
                        <div class="wcdg-pay-actions">
                            <button type="button" class="wcdg-copy-address"><?php esc_html_e('Copy address', 'wp-crypto-direct-gateway'); ?></button>
                            <button type="button" class="wcdg-copy-amount"><?php esc_html_e('Copy amount', 'wp-crypto-direct-gateway'); ?></button>
                            <a class="wcdg-open-wallet" href="#" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open wallet', 'wp-crypto-direct-gateway'); ?></a>
                        </div>
                    </div>
                    <div class="wcdg-pay-footer">
                        <ol class="wcdg-steps">
                            <li><?php esc_html_e('Scan the QR code with your wallet app.', 'wp-crypto-direct-gateway'); ?></li>
                            <li><?php esc_html_e('Send the exact amount shown above.', 'wp-crypto-direct-gateway'); ?></li>
                            <li><?php esc_html_e('Keep this page open \u2013 status updates automatically.', 'wp-crypto-direct-gateway'); ?></li>
                        </ol>
                        <div class="wcdg-message" aria-live="polite"></div>
                        <span class="wcdg-expires-at" hidden></span>
                    </div>
                </div>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}