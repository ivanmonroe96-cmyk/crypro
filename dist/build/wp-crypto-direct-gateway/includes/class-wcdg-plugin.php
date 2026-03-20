<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Plugin
{
    private WCDG_Payment_Requests $payment_requests;

    private WCDG_Rates $rates;

    private WCDG_Settings $settings;

    private WCDG_Blockchain_Watcher $watcher;

    private WCDG_Admin_Payments_Page $payments_page;

    private WCDG_REST_Controller $rest_controller;

    private WCDG_Shortcodes $shortcodes;

    private WCDG_WooCommerce_Gateway $woocommerce_gateway;

    public function inspect_payment_reference(string $reference): ?array
    {
        return $this->watcher->inspect_reference($reference);
    }

    public function __construct()
    {
        WCDG_Database::install();

        $this->payment_requests = new WCDG_Payment_Requests();
        $this->rates = new WCDG_Rates();
        $this->settings = new WCDG_Settings();
        $this->watcher = new WCDG_Blockchain_Watcher($this->payment_requests);
        $this->payments_page = new WCDG_Admin_Payments_Page($this->payment_requests);
        $this->rest_controller = new WCDG_REST_Controller($this->payment_requests, $this->rates, $this->watcher);
        $this->shortcodes = new WCDG_Shortcodes();
        $this->woocommerce_gateway = new WCDG_WooCommerce_Gateway($this->payment_requests, $this->rates);

        $this->settings->hooks();
        $this->watcher->hooks();
        $this->payments_page->hooks();
        $this->rest_controller->hooks();
        $this->shortcodes->hooks();
        $this->woocommerce_gateway->hooks();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_assets(): void
    {
        wp_register_style('wcdg-frontend', WCDG_PLUGIN_URL . 'assets/css/frontend.css', array(), WCDG_VERSION);
        wp_register_script('wcdg-frontend', WCDG_PLUGIN_URL . 'assets/js/frontend.js', array(), WCDG_VERSION, true);

        $settings = WCDG_Settings::get_settings();
        $inline_css = sprintf(
            ':root{--wcdg-primary:%1$s;--wcdg-secondary:%2$s;--wcdg-brand-text:%3$s;} .wcdg-payment-form,.wcdg-woo-box{--wcdg-primary:%1$s;--wcdg-secondary:%2$s;--wcdg-brand-text:%3$s;}',
            esc_html($settings['brand_primary_color']),
            esc_html($settings['brand_secondary_color']),
            esc_html($settings['merchant_name'])
        );
        wp_add_inline_style('wcdg-frontend', $inline_css);

        wp_localize_script('wcdg-frontend', 'wcdgConfig', array(
            'createRequestUrl' => rest_url('wcdg/v1/payment-requests'),
            'statusUrlBase' => rest_url('wcdg/v1/payment-requests/'),
            'strings' => array(
                'creating' => __('Creating payment request...', 'wp-crypto-direct-gateway'),
                'created' => __('Payment request created.', 'wp-crypto-direct-gateway'),
                'error' => __('Something went wrong while creating the payment request.', 'wp-crypto-direct-gateway'),
                'copySuccess' => __('Wallet address copied.', 'wp-crypto-direct-gateway'),
                'copyAmountSuccess' => __('Crypto amount copied.', 'wp-crypto-direct-gateway'),
                'paid' => __('Payment confirmed on-chain.', 'wp-crypto-direct-gateway'),
                'qrDynamic' => __('Live QR with amount and wallet details.', 'wp-crypto-direct-gateway'),
                'qrStatic' => __('Showing uploaded wallet QR image. Double-check the exact amount before sending.', 'wp-crypto-direct-gateway'),
            ),
        ));

        $is_woo_page = (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout());

        if (is_singular() || $is_woo_page) {
            wp_enqueue_style('wcdg-frontend');
            wp_enqueue_script('wcdg-frontend');
        }
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if (strpos($hook_suffix, 'wcdg-settings') === false && strpos($hook_suffix, 'wcdg-payments') === false) {
            return;
        }

        wp_enqueue_media();
        wp_register_style('wcdg-admin', WCDG_PLUGIN_URL . 'assets/css/admin.css', array(), WCDG_VERSION);

        $settings = WCDG_Settings::get_settings();
        $inline_css = sprintf(
            '.wcdg-admin-shell{--wcdg-admin-primary:%1$s;--wcdg-admin-secondary:%2$s;}',
            esc_html($settings['brand_primary_color']),
            esc_html($settings['brand_secondary_color'])
        );

        wp_enqueue_style('wcdg-admin');
        wp_add_inline_style('wcdg-admin', $inline_css);
    }
}