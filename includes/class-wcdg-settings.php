<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Settings
{
    public const OPTION_KEY = 'wcdg_settings';

    public static function default_settings(): array
    {
        $btc_qr_url = self::get_bundled_wallet_qr_url(array('btc-wallet.jpeg', 'btc-wallet.png', 'btc.jpeg'));
        $eth_qr_url = self::get_bundled_wallet_qr_url(array('eth-wallet.jpeg', 'eth-wallet.png', 'eth.jpeg'));
        $usdt_trc20_qr_url = self::get_bundled_wallet_qr_url(array('usdt-trc20-wallet.jpeg', 'usdt-trc20-wallet.png', 'usdttrc20.jpeg'));
        $usdt_erc20_qr_url = self::get_bundled_wallet_qr_url(array('usdt-erc20-wallet.jpeg', 'usdt-erc20-wallet.png', 'usdterc20.jpeg'));

        return array(
            'merchant_name' => get_bloginfo('name') ?: 'Merchant',
            'brand_tagline' => __('Direct wallet checkout without a custodial processor.', 'wp-crypto-direct-gateway'),
            'brand_primary_color' => '#d6ff4b',
            'brand_secondary_color' => '#63b9ff',
            'brand_logo_url' => '',
            'default_fiat_currency' => 'USD',
            'payment_window_minutes' => 30,
            'watcher_enabled' => 1,
            'callback_secret' => wp_generate_password(24, false, false),
            'qr_provider' => 'quickchart',
            'wallets' => array(
                array(
                    'uid' => 'btc-bitcoin',
                    'enabled' => 1,
                    'symbol' => 'BTC',
                    'name' => 'Bitcoin',
                    'network' => 'Bitcoin',
                    'coingecko_id' => 'bitcoin',
                    'address' => 'bc1qev9qvwxennyypmth024jndwlqqh7ft9mzjnapr',
                    'static_qr_url' => $btc_qr_url,
                    'qr_display_mode' => $btc_qr_url !== '' ? 'static' : 'dynamic',
                    'confirmations' => 1,
                ),
                array(
                    'uid' => 'eth-ethereum',
                    'enabled' => 1,
                    'symbol' => 'ETH',
                    'name' => 'Ethereum',
                    'network' => 'Ethereum',
                    'coingecko_id' => 'ethereum',
                    'address' => '0x08CA715802e9B7Be5F21D8e3aB67Ab515eDde955',
                    'static_qr_url' => $eth_qr_url,
                    'qr_display_mode' => $eth_qr_url !== '' ? 'static' : 'dynamic',
                    'confirmations' => 12,
                ),
                array(
                    'uid' => 'usdt-trc20',
                    'enabled' => 1,
                    'symbol' => 'USDT',
                    'name' => 'Tether',
                    'network' => 'TRC20',
                    'coingecko_id' => 'tether',
                    'address' => 'TGkyrQigqKChK4KSfEjTdSRBC2XZboKfAL',
                    'static_qr_url' => $usdt_trc20_qr_url,
                    'qr_display_mode' => $usdt_trc20_qr_url !== '' ? 'static' : 'dynamic',
                    'confirmations' => 1,
                ),
                array(
                    'uid' => 'usdt-erc20',
                    'enabled' => 1,
                    'symbol' => 'USDT',
                    'name' => 'Tether',
                    'network' => 'ERC20',
                    'coingecko_id' => 'tether',
                    'address' => '0x08CA715802e9B7Be5F21D8e3aB67Ab515eDde955',
                    'static_qr_url' => $usdt_erc20_qr_url,
                    'qr_display_mode' => $usdt_erc20_qr_url !== '' ? 'static' : 'dynamic',
                    'confirmations' => 1,
                ),
            ),
        );
    }

    private static function get_bundled_wallet_qr_url(array $filenames): string
    {
        foreach ($filenames as $filename) {
            $path = WCDG_PLUGIN_DIR . 'assets/wallet-qr/' . $filename;

            if (! file_exists($path)) {
                continue;
            }

            return esc_url_raw(WCDG_PLUGIN_URL . 'assets/wallet-qr/' . rawurlencode($filename));
        }

        return '';
    }

    public function hooks(): void
    {
        add_action('admin_menu', array($this, 'register_admin_menus'));
        add_action('admin_post_wcdg_save_settings', array($this, 'handle_settings_save'));
    }

    public function register_admin_menus(): void
    {
        add_menu_page(
            __('Crypto Gateway', 'wp-crypto-direct-gateway'),
            __('Crypto Gateway', 'wp-crypto-direct-gateway'),
            'manage_options',
            'wcdg-settings',
            array($this, 'render_settings_page'),
            'dashicons-money-alt',
            56
        );
    }

    public static function get_settings(): array
    {
        $settings = get_option(self::OPTION_KEY, array());
        $settings = is_array($settings) ? $settings : array();
        $defaults = self::default_settings();

        $settings['wallets'] = self::merge_wallets($settings['wallets'] ?? array(), $defaults['wallets']);

        return wp_parse_args($settings, $defaults);
    }

    public static function get_wallets(bool $enabled_only = true): array
    {
        $settings = self::get_settings();
        $wallets = isset($settings['wallets']) && is_array($settings['wallets']) ? $settings['wallets'] : array();

        $wallets = array_values(array_filter(array_map(array(__CLASS__, 'sanitize_wallet'), $wallets), function (array $wallet) use ($enabled_only): bool {
            if ($wallet['symbol'] === '' || $wallet['address'] === '') {
                return false;
            }

            if (! $enabled_only) {
                return true;
            }

            return (bool) $wallet['enabled'];
        }));

        return $wallets;
    }

    public static function find_wallet(string $symbol): ?array
    {
        foreach (self::get_wallets(false) as $wallet) {
            if (strcasecmp($wallet['symbol'], $symbol) === 0) {
                return $wallet;
            }
        }

        return null;
    }

    public static function find_wallet_by_uid(string $uid): ?array
    {
        foreach (self::get_wallets(false) as $wallet) {
            if (($wallet['uid'] ?? '') === $uid) {
                return $wallet;
            }
        }

        return null;
    }

    public static function find_wallet_by_symbol_and_network(string $symbol, string $network): ?array
    {
        foreach (self::get_wallets(false) as $wallet) {
            if (strcasecmp((string) $wallet['symbol'], $symbol) === 0 && strcasecmp((string) $wallet['network'], $network) === 0) {
                return $wallet;
            }
        }

        return null;
    }

    public function handle_settings_save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to update these settings.', 'wp-crypto-direct-gateway'));
        }

        check_admin_referer('wcdg_save_settings');

        $wallets = array();
        $submitted_wallets = isset($_POST['wallets']) && is_array($_POST['wallets']) ? wp_unslash($_POST['wallets']) : array();

        foreach ($submitted_wallets as $wallet) {
            if (! is_array($wallet)) {
                continue;
            }

            $wallets[] = self::sanitize_wallet($wallet);
        }

        $settings = array(
            'merchant_name' => sanitize_text_field(wp_unslash($_POST['merchant_name'] ?? '')),
            'brand_tagline' => sanitize_text_field(wp_unslash($_POST['brand_tagline'] ?? '')),
            'brand_primary_color' => sanitize_hex_color(wp_unslash($_POST['brand_primary_color'] ?? '')) ?: '#d6ff4b',
            'brand_secondary_color' => sanitize_hex_color(wp_unslash($_POST['brand_secondary_color'] ?? '')) ?: '#63b9ff',
            'brand_logo_url' => esc_url_raw(wp_unslash($_POST['brand_logo_url'] ?? '')),
            'default_fiat_currency' => strtoupper(sanitize_text_field(wp_unslash($_POST['default_fiat_currency'] ?? 'USD'))),
            'payment_window_minutes' => max(5, absint($_POST['payment_window_minutes'] ?? 30)),
            'watcher_enabled' => empty($_POST['watcher_enabled']) ? 0 : 1,
            'callback_secret' => sanitize_text_field(wp_unslash($_POST['callback_secret'] ?? '')),
            'qr_provider' => sanitize_text_field(wp_unslash($_POST['qr_provider'] ?? 'quickchart')),
            'wallets' => $wallets,
        );

        if ($settings['callback_secret'] === '') {
            $settings['callback_secret'] = wp_generate_password(24, false, false);
        }

        update_option(self::OPTION_KEY, wp_parse_args($settings, self::default_settings()));

        wp_safe_redirect(add_query_arg(array(
            'page' => 'wcdg-settings',
            'updated' => '1',
        ), admin_url('admin.php')));
        exit;
    }

    public function render_settings_page(): void
    {
        $settings = self::get_settings();
        $wallets = isset($settings['wallets']) && is_array($settings['wallets']) ? $settings['wallets'] : array();
        ?>
        <div class="wrap wcdg-admin-shell">
            <section class="wcdg-admin-hero">
                <div>
                    <p class="wcdg-admin-kicker"><?php esc_html_e('Premium Crypto Checkout', 'wp-crypto-direct-gateway'); ?></p>
                    <h1><?php esc_html_e('WP Crypto Direct Gateway', 'wp-crypto-direct-gateway'); ?></h1>
                    <p><?php esc_html_e('Create direct wallet payment requests with QR codes, live rates, a callback endpoint, and optional WooCommerce checkout support.', 'wp-crypto-direct-gateway'); ?></p>
                </div>
                <div class="wcdg-admin-hero-badges">
                    <span><?php esc_html_e('Wallets', 'wp-crypto-direct-gateway'); ?> <?php echo esc_html((string) count(WCDG_Settings::get_wallets(false))); ?></span>
                    <span><?php esc_html_e('Watcher', 'wp-crypto-direct-gateway'); ?> <?php echo ! empty($settings['watcher_enabled']) ? esc_html__('Enabled', 'wp-crypto-direct-gateway') : esc_html__('Disabled', 'wp-crypto-direct-gateway'); ?></span>
                    <span><?php echo esc_html($settings['default_fiat_currency']); ?></span>
                </div>
            </section>

            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'wp-crypto-direct-gateway'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wcdg_save_settings" />
                <?php wp_nonce_field('wcdg_save_settings'); ?>

                <div class="wcdg-admin-grid">
                    <section class="wcdg-admin-panel">
                        <div class="wcdg-admin-panel-header">
                            <h2><?php esc_html_e('Brand & Gateway', 'wp-crypto-direct-gateway'); ?></h2>
                            <p><?php esc_html_e('Configure brand presentation, payout defaults, and the automatic watcher.', 'wp-crypto-direct-gateway'); ?></p>
                        </div>
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="wcdg-merchant-name"><?php esc_html_e('Merchant name', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td><input type="text" id="wcdg-merchant-name" class="regular-text" name="merchant_name" value="<?php echo esc_attr($settings['merchant_name']); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-brand-tagline"><?php esc_html_e('Brand tagline', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td><input type="text" id="wcdg-brand-tagline" class="regular-text" name="brand_tagline" value="<?php echo esc_attr($settings['brand_tagline']); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-brand-primary"><?php esc_html_e('Primary color', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td><input type="color" id="wcdg-brand-primary" name="brand_primary_color" value="<?php echo esc_attr($settings['brand_primary_color']); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-brand-secondary"><?php esc_html_e('Secondary color', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td><input type="color" id="wcdg-brand-secondary" name="brand_secondary_color" value="<?php echo esc_attr($settings['brand_secondary_color']); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-brand-logo"><?php esc_html_e('Logo URL', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td>
                                        <input type="url" id="wcdg-brand-logo" class="regular-text code" name="brand_logo_url" value="<?php echo esc_attr($settings['brand_logo_url']); ?>" />
                                        <p class="description"><?php esc_html_e('Optional remote logo image for the customer payment panel and WooCommerce emails.', 'wp-crypto-direct-gateway'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-default-fiat"><?php esc_html_e('Default fiat currency', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td><input type="text" id="wcdg-default-fiat" class="regular-text" name="default_fiat_currency" value="<?php echo esc_attr($settings['default_fiat_currency']); ?>" maxlength="10" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-payment-window"><?php esc_html_e('Payment window (minutes)', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td><input type="number" id="wcdg-payment-window" class="small-text" min="5" name="payment_window_minutes" value="<?php echo esc_attr((string) $settings['payment_window_minutes']); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Automatic watcher', 'wp-crypto-direct-gateway'); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="watcher_enabled" value="1" <?php checked(! empty($settings['watcher_enabled'])); ?> /> <?php esc_html_e('Enable automatic blockchain polling for BTC, ETH, USDT ERC20, and USDT TRC20.', 'wp-crypto-direct-gateway'); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-callback-secret"><?php esc_html_e('Callback secret', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td>
                                        <input type="text" id="wcdg-callback-secret" class="regular-text code" name="callback_secret" value="<?php echo esc_attr($settings['callback_secret']); ?>" />
                                        <p class="description"><?php esc_html_e('Use this secret in the X-WCDG-Signature header when an external watcher posts payment confirmations.', 'wp-crypto-direct-gateway'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wcdg-qr-provider"><?php esc_html_e('QR provider', 'wp-crypto-direct-gateway'); ?></label></th>
                                    <td>
                                        <select id="wcdg-qr-provider" name="qr_provider">
                                            <option value="quickchart" <?php selected($settings['qr_provider'], 'quickchart'); ?>><?php esc_html_e('QuickChart', 'wp-crypto-direct-gateway'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <aside class="wcdg-admin-panel wcdg-admin-sidebar">
                        <div class="wcdg-admin-panel-header">
                            <h2><?php esc_html_e('Launch Checklist', 'wp-crypto-direct-gateway'); ?></h2>
                            <p><?php esc_html_e('Use this before publishing the gateway live.', 'wp-crypto-direct-gateway'); ?></p>
                        </div>
                        <ul class="wcdg-checklist">
                            <li><?php esc_html_e('Confirm each wallet address and network.', 'wp-crypto-direct-gateway'); ?></li>
                            <li><?php esc_html_e('Verify the brand colors and logo render correctly.', 'wp-crypto-direct-gateway'); ?></li>
                            <li><?php esc_html_e('Place one test order per enabled asset.', 'wp-crypto-direct-gateway'); ?></li>
                            <li><?php esc_html_e('Keep watcher enabled unless you use an external monitoring service.', 'wp-crypto-direct-gateway'); ?></li>
                            <li><?php esc_html_e('Export a production zip from the packaging script before deployment.', 'wp-crypto-direct-gateway'); ?></li>
                        </ul>
                        <div class="wcdg-admin-callout">
                            <strong><?php esc_html_e('Installable build', 'wp-crypto-direct-gateway'); ?></strong>
                            <p><?php esc_html_e('Run the packaging script from the plugin root to generate a distribution-ready zip in the dist folder.', 'wp-crypto-direct-gateway'); ?></p>
                            <code>./scripts/package-plugin.sh</code>
                        </div>
                    </aside>
                </div>

                <section class="wcdg-admin-panel">
                    <div class="wcdg-admin-panel-header">
                        <h2><?php esc_html_e('Wallets', 'wp-crypto-direct-gateway'); ?></h2>
                        <p><?php esc_html_e('Add one wallet per coin or network. Multi-network assets such as USDT should be added once per network so checkout and QR requests stay accurate.', 'wp-crypto-direct-gateway'); ?></p>
                    </div>

                    <table class="widefat striped" id="wcdg-wallet-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Enabled', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('Symbol', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('Name', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('Network', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('CoinGecko ID', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('Wallet address', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('Static QR image', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('QR mode', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('Confirmations', 'wp-crypto-direct-gateway'); ?></th>
                            <th><?php esc_html_e('Action', 'wp-crypto-direct-gateway'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wallets as $index => $wallet) : ?>
                            <?php $this->render_wallet_row((int) $index, self::sanitize_wallet($wallet)); ?>
                        <?php endforeach; ?>
                    </tbody>
                    </table>

                    <p><button type="button" class="button button-primary" id="wcdg-add-wallet"><?php esc_html_e('Add wallet', 'wp-crypto-direct-gateway'); ?></button></p>
                </section>

                <section class="wcdg-admin-panel">
                    <div class="wcdg-admin-panel-header">
                        <h2><?php esc_html_e('Integration details', 'wp-crypto-direct-gateway'); ?></h2>
                        <p><?php esc_html_e('Use these endpoints and shortcodes to embed or extend the gateway.', 'wp-crypto-direct-gateway'); ?></p>
                    </div>
                    <ul class="wcdg-detail-list">
                        <li><code><?php echo esc_html(rest_url('wcdg/v1/payment-requests')); ?></code> <?php esc_html_e('creates public payment requests.', 'wp-crypto-direct-gateway'); ?></li>
                        <li><code><?php echo esc_html(rest_url('wcdg/v1/payment-requests/{reference}/status')); ?></code> <?php esc_html_e('accepts callback updates from your payment watcher.', 'wp-crypto-direct-gateway'); ?></li>
                        <li><code>[wcdg_payment_form amount="49.99" currency="USD"]</code> <?php esc_html_e('renders a QR payment form anywhere on the site.', 'wp-crypto-direct-gateway'); ?></li>
                    </ul>
                </section>

                <?php submit_button(__('Save settings', 'wp-crypto-direct-gateway')); ?>
            </form>
        </div>
        <script>
            (function () {
                const tableBody = document.querySelector('#wcdg-wallet-table tbody');
                const addButton = document.querySelector('#wcdg-add-wallet');
                if (!tableBody || !addButton) {
                    return;
                }

                const renderRow = (index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = [
                        '<input type="hidden" name="wallets[' + index + '][uid]" value=""><input type="checkbox" name="wallets[' + index + '][enabled]" value="1" checked>',
                        '<input type="text" class="regular-text" name="wallets[' + index + '][symbol]" placeholder="BTC">',
                        '<input type="text" class="regular-text" name="wallets[' + index + '][name]" placeholder="Bitcoin">',
                        '<input type="text" class="regular-text" name="wallets[' + index + '][network]" placeholder="Bitcoin">',
                        '<input type="text" class="regular-text" name="wallets[' + index + '][coingecko_id]" placeholder="bitcoin">',
                        '<input type="text" class="large-text code" name="wallets[' + index + '][address]" placeholder="Wallet address">',
                        '<div class="wcdg-qr-upload-field"><input type="url" class="regular-text code" name="wallets[' + index + '][static_qr_url]" placeholder="https://example.com/qr.png"><button type="button" class="button wcdg-upload-qr"><?php echo esc_js(__('Choose image', 'wp-crypto-direct-gateway')); ?></button></div>',
                        '<select name="wallets[' + index + '][qr_display_mode]"><option value="dynamic"><?php echo esc_js(__('Dynamic', 'wp-crypto-direct-gateway')); ?></option><option value="static"><?php echo esc_js(__('Static image', 'wp-crypto-direct-gateway')); ?></option></select>',
                        '<input type="number" class="small-text" min="1" name="wallets[' + index + '][confirmations]" value="1">',
                        '<button type="button" class="button-link-delete">Remove</button>'
                    ].map((cell) => '<td>' + cell + '</td>').join('');
                    attachRowHandlers(row);
                    tableBody.appendChild(row);
                };

                const attachRemoveHandler = (row) => {
                    const button = row.querySelector('.button-link-delete');
                    if (!button) {
                        return;
                    }
                    button.addEventListener('click', () => row.remove());
                };

                const attachQrUploader = (row) => {
                    const uploadButton = row.querySelector('.wcdg-upload-qr');
                    const input = row.querySelector('input[name*="[static_qr_url]"]');
                    if (!uploadButton || !input || typeof wp === 'undefined' || !wp.media) {
                        return;
                    }

                    uploadButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        const frame = wp.media({
                            title: '<?php echo esc_js(__('Select a wallet QR image', 'wp-crypto-direct-gateway')); ?>',
                            button: { text: '<?php echo esc_js(__('Use image', 'wp-crypto-direct-gateway')); ?>' },
                            library: { type: 'image' },
                            multiple: false
                        });

                        frame.on('select', () => {
                            const attachment = frame.state().get('selection').first().toJSON();
                            input.value = attachment.url || '';
                        });

                        frame.open();
                    });
                };

                const attachRowHandlers = (row) => {
                    attachRemoveHandler(row);
                    attachQrUploader(row);
                };

                tableBody.querySelectorAll('tr').forEach(attachRowHandlers);
                addButton.addEventListener('click', () => renderRow(Date.now()));
            }());
        </script>
        <?php
    }

    public function render_wallet_row(int $index, array $wallet): void
    {
        ?>
        <tr>
            <td><input type="hidden" name="wallets[<?php echo esc_attr((string) $index); ?>][uid]" value="<?php echo esc_attr($wallet['uid']); ?>" /><input type="checkbox" name="wallets[<?php echo esc_attr((string) $index); ?>][enabled]" value="1" <?php checked((bool) $wallet['enabled']); ?> /></td>
            <td><input type="text" class="regular-text" name="wallets[<?php echo esc_attr((string) $index); ?>][symbol]" value="<?php echo esc_attr($wallet['symbol']); ?>" /></td>
            <td><input type="text" class="regular-text" name="wallets[<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr($wallet['name']); ?>" /></td>
            <td><input type="text" class="regular-text" name="wallets[<?php echo esc_attr((string) $index); ?>][network]" value="<?php echo esc_attr($wallet['network']); ?>" /></td>
            <td><input type="text" class="regular-text" name="wallets[<?php echo esc_attr((string) $index); ?>][coingecko_id]" value="<?php echo esc_attr($wallet['coingecko_id']); ?>" /></td>
            <td><input type="text" class="large-text code" name="wallets[<?php echo esc_attr((string) $index); ?>][address]" value="<?php echo esc_attr($wallet['address']); ?>" /></td>
            <td>
                <div class="wcdg-qr-upload-field">
                    <input type="url" class="regular-text code" name="wallets[<?php echo esc_attr((string) $index); ?>][static_qr_url]" value="<?php echo esc_attr($wallet['static_qr_url']); ?>" />
                    <button type="button" class="button wcdg-upload-qr"><?php esc_html_e('Choose image', 'wp-crypto-direct-gateway'); ?></button>
                </div>
            </td>
            <td>
                <select name="wallets[<?php echo esc_attr((string) $index); ?>][qr_display_mode]">
                    <option value="dynamic" <?php selected($wallet['qr_display_mode'], 'dynamic'); ?>><?php esc_html_e('Dynamic', 'wp-crypto-direct-gateway'); ?></option>
                    <option value="static" <?php selected($wallet['qr_display_mode'], 'static'); ?>><?php esc_html_e('Static image', 'wp-crypto-direct-gateway'); ?></option>
                </select>
            </td>
            <td><input type="number" class="small-text" min="1" name="wallets[<?php echo esc_attr((string) $index); ?>][confirmations]" value="<?php echo esc_attr((string) $wallet['confirmations']); ?>" /></td>
            <td><button type="button" class="button-link-delete"><?php esc_html_e('Remove', 'wp-crypto-direct-gateway'); ?></button></td>
        </tr>
        <?php
    }

    public static function sanitize_wallet(array $wallet): array
    {
        $symbol = strtoupper(sanitize_text_field((string) ($wallet['symbol'] ?? '')));
        $network = sanitize_text_field((string) ($wallet['network'] ?? ''));
        $uid = sanitize_key((string) ($wallet['uid'] ?? self::build_wallet_uid($symbol, $network)));

        return array(
            'uid' => $uid,
            'enabled' => empty($wallet['enabled']) ? 0 : 1,
            'symbol' => $symbol,
            'name' => sanitize_text_field((string) ($wallet['name'] ?? '')),
            'network' => $network,
            'coingecko_id' => sanitize_key((string) ($wallet['coingecko_id'] ?? '')),
            'address' => sanitize_text_field((string) ($wallet['address'] ?? '')),
            'static_qr_url' => esc_url_raw((string) ($wallet['static_qr_url'] ?? '')),
            'qr_display_mode' => in_array((string) ($wallet['qr_display_mode'] ?? 'dynamic'), array('dynamic', 'static'), true) ? (string) $wallet['qr_display_mode'] : 'dynamic',
            'confirmations' => max(1, absint($wallet['confirmations'] ?? 1)),
        );
    }

    private static function build_wallet_uid(string $symbol, string $network): string
    {
        $base = trim(strtolower($symbol . '-' . $network));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);

        return trim((string) $base, '-');
    }

    private static function merge_wallets(array $saved_wallets, array $default_wallets): array
    {
        $normalized_saved = array();

        foreach ($saved_wallets as $wallet) {
            if (! is_array($wallet)) {
                continue;
            }

            $normalized = self::sanitize_wallet($wallet);
            if ($normalized['uid'] === '') {
                continue;
            }

            $normalized_saved[$normalized['uid']] = $normalized;
        }

        foreach ($default_wallets as $wallet) {
            $default_wallet = self::sanitize_wallet($wallet);
            $uid = $default_wallet['uid'];

            if (! isset($normalized_saved[$uid])) {
                $normalized_saved[$uid] = $default_wallet;
                continue;
            }

            $saved_wallet = $normalized_saved[$uid];
            if ($saved_wallet['address'] === '' && $default_wallet['address'] !== '') {
                $saved_wallet['address'] = $default_wallet['address'];
            }

            if ($saved_wallet['coingecko_id'] === '' && $default_wallet['coingecko_id'] !== '') {
                $saved_wallet['coingecko_id'] = $default_wallet['coingecko_id'];
            }

            if ($saved_wallet['name'] === '' && $default_wallet['name'] !== '') {
                $saved_wallet['name'] = $default_wallet['name'];
            }

            if ($saved_wallet['network'] === '' && $default_wallet['network'] !== '') {
                $saved_wallet['network'] = $default_wallet['network'];
            }

            if ($saved_wallet['uid'] === '' && $uid !== '') {
                $saved_wallet['uid'] = $uid;
            }

            if ($saved_wallet['static_qr_url'] === '' && $default_wallet['static_qr_url'] !== '') {
                $saved_wallet['static_qr_url'] = $default_wallet['static_qr_url'];
            }

            if ($saved_wallet['qr_display_mode'] === '' && $default_wallet['qr_display_mode'] !== '') {
                $saved_wallet['qr_display_mode'] = $default_wallet['qr_display_mode'];
            }

            $normalized_saved[$uid] = $saved_wallet;
        }

        return array_values($normalized_saved);
    }
}