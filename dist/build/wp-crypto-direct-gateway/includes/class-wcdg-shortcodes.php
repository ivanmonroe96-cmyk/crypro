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
            return '<p>' . esc_html__('Crypto payments are not available yet. Add at least one wallet in the plugin settings.', 'wp-crypto-direct-gateway') . '</p>';
        }

        $atts = shortcode_atts(array(
            'amount' => '',
            'currency' => WCDG_Settings::get_settings()['default_fiat_currency'],
            'title' => __('Pay with crypto', 'wp-crypto-direct-gateway'),
            'description' => $settings['brand_tagline'] ?: __('Create a payment request, scan the QR code, and send funds directly to the displayed wallet address.', 'wp-crypto-direct-gateway'),
        ), $atts, 'wcdg_payment_form');

        ob_start();
        ?>
        <section class="wcdg-payment-form" data-default-amount="<?php echo esc_attr((string) $atts['amount']); ?>" data-default-currency="<?php echo esc_attr((string) $atts['currency']); ?>">
            <div class="wcdg-card">
                <div class="wcdg-panel-header">
                    <div>
                        <p class="wcdg-kicker"><?php esc_html_e('Direct wallet checkout', 'wp-crypto-direct-gateway'); ?></p>
                        <?php if (! empty($settings['brand_logo_url'])) : ?>
                            <img class="wcdg-logo" src="<?php echo esc_url($settings['brand_logo_url']); ?>" alt="<?php echo esc_attr($settings['merchant_name']); ?>" />
                        <?php endif; ?>
                        <h2><?php echo esc_html($atts['title']); ?></h2>
                        <p class="wcdg-brand-name"><?php echo esc_html($settings['merchant_name']); ?></p>
                    </div>
                    <div class="wcdg-trust-points">
                        <span><?php esc_html_e('No custodial processor', 'wp-crypto-direct-gateway'); ?></span>
                        <span><?php esc_html_e('QR-ready', 'wp-crypto-direct-gateway'); ?></span>
                        <span><?php esc_html_e('Live rate quote', 'wp-crypto-direct-gateway'); ?></span>
                    </div>
                </div>
                <p><?php echo esc_html($atts['description']); ?></p>
                <form class="wcdg-form">
                    <label>
                        <span><?php esc_html_e('Your name', 'wp-crypto-direct-gateway'); ?></span>
                        <input type="text" name="customer_name" autocomplete="name" />
                    </label>
                    <label>
                        <span><?php esc_html_e('Email', 'wp-crypto-direct-gateway'); ?></span>
                        <input type="email" name="customer_email" autocomplete="email" />
                    </label>
                    <div class="wcdg-grid">
                        <label>
                            <span><?php esc_html_e('Amount', 'wp-crypto-direct-gateway'); ?></span>
                            <input type="number" step="0.01" min="0.01" name="amount" value="<?php echo esc_attr((string) $atts['amount']); ?>" required />
                        </label>
                        <label>
                            <span><?php esc_html_e('Fiat currency', 'wp-crypto-direct-gateway'); ?></span>
                            <input type="text" name="currency" maxlength="10" value="<?php echo esc_attr((string) $atts['currency']); ?>" required />
                        </label>
                    </div>
                    <label>
                        <span><?php esc_html_e('Asset and network', 'wp-crypto-direct-gateway'); ?></span>
                        <select name="wallet_id" required>
                            <?php foreach ($wallets as $wallet) : ?>
                                <option value="<?php echo esc_attr($wallet['uid']); ?>"><?php echo esc_html($wallet['name'] . ' (' . $wallet['symbol'] . ' - ' . $wallet['network'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit"><?php esc_html_e('Generate payment request', 'wp-crypto-direct-gateway'); ?></button>
                </form>
                <div class="wcdg-message" aria-live="polite"></div>
            </div>
            <div class="wcdg-payment-request" hidden>
                <div class="wcdg-card">
                    <div class="wcdg-request-header">
                        <div>
                            <p class="wcdg-eyebrow"><?php esc_html_e('Payment reference', 'wp-crypto-direct-gateway'); ?></p>
                            <strong class="wcdg-reference"></strong>
                        </div>
                        <div class="wcdg-request-meta">
                            <span class="wcdg-status wcdg-status-pending"></span>
                            <span class="wcdg-countdown"></span>
                        </div>
                    </div>
                    <div class="wcdg-grid wcdg-grid-request">
                        <div class="wcdg-qr-wrap"><img alt="<?php esc_attr_e('Crypto payment QR code', 'wp-crypto-direct-gateway'); ?>" class="wcdg-qr" /></div>
                        <div>
                            <p><strong><?php esc_html_e('Send exactly', 'wp-crypto-direct-gateway'); ?></strong> <span class="wcdg-crypto-amount"></span></p>
                            <p><strong><?php esc_html_e('Quote', 'wp-crypto-direct-gateway'); ?></strong> <span class="wcdg-fiat-amount"></span></p>
                            <p><strong><?php esc_html_e('Wallet', 'wp-crypto-direct-gateway'); ?></strong> <span class="wcdg-wallet-label"></span></p>
                            <p><strong><?php esc_html_e('Address', 'wp-crypto-direct-gateway'); ?></strong></p>
                            <textarea class="wcdg-address" readonly rows="3"></textarea>
                            <div class="wcdg-actions-row">
                                <button type="button" class="wcdg-copy-address"><?php esc_html_e('Copy address', 'wp-crypto-direct-gateway'); ?></button>
                                <button type="button" class="wcdg-copy-amount"><?php esc_html_e('Copy amount', 'wp-crypto-direct-gateway'); ?></button>
                                <a class="wcdg-open-wallet" href="#" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open wallet app', 'wp-crypto-direct-gateway'); ?></a>
                            </div>
                            <p><strong><?php esc_html_e('Expires', 'wp-crypto-direct-gateway'); ?></strong> <span class="wcdg-expires-at"></span></p>
                            <ol class="wcdg-steps">
                                <li><?php esc_html_e('Open your crypto wallet and scan the QR code.', 'wp-crypto-direct-gateway'); ?></li>
                                <li><?php esc_html_e('Verify the network and send the exact quoted amount.', 'wp-crypto-direct-gateway'); ?></li>
                                <li><?php esc_html_e('Keep this page open while payment status updates.', 'wp-crypto-direct-gateway'); ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}