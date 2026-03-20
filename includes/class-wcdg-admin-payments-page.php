<?php

if (! defined('ABSPATH')) {
    exit;
}

class WCDG_Admin_Payments_Page
{
    private WCDG_Payment_Requests $payment_requests;

    public function __construct(WCDG_Payment_Requests $payment_requests)
    {
        $this->payment_requests = $payment_requests;
    }

    public function hooks(): void
    {
        add_action('admin_menu', array($this, 'register_submenu'));
        add_action('admin_post_wcdg_mark_payment', array($this, 'handle_status_change'));
    }

    public function register_submenu(): void
    {
        add_submenu_page(
            'wcdg-settings',
            __('Payments', 'wp-crypto-direct-gateway'),
            __('Payments', 'wp-crypto-direct-gateway'),
            'manage_options',
            'wcdg-payments',
            array($this, 'render_page')
        );
    }

    public function handle_status_change(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to update payments.', 'wp-crypto-direct-gateway'));
        }

        check_admin_referer('wcdg_mark_payment');

        $reference = strtoupper(sanitize_text_field(wp_unslash($_POST['reference'] ?? '')));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? 'pending'));
        $tx_hash = sanitize_text_field(wp_unslash($_POST['tx_hash'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        $this->payment_requests->update_status($reference, $status, array(
            'tx_hash' => $tx_hash,
            'notes' => $notes,
        ));

        wp_safe_redirect(add_query_arg(array(
            'page' => 'wcdg-payments',
            'updated' => '1',
        ), admin_url('admin.php')));
        exit;
    }

    public function render_page(): void
    {
        $payments = $this->payment_requests->list_recent();
        ?>
        <div class="wrap wcdg-admin-shell">
            <section class="wcdg-admin-hero">
                <div>
                    <p class="wcdg-admin-kicker"><?php esc_html_e('Operations', 'wp-crypto-direct-gateway'); ?></p>
                    <h1><?php esc_html_e('Crypto Payments', 'wp-crypto-direct-gateway'); ?></h1>
                    <p><?php esc_html_e('Track requests, update status manually, and copy references for your external watcher or reconciliation workflow.', 'wp-crypto-direct-gateway'); ?></p>
                </div>
                <div class="wcdg-admin-hero-badges">
                    <span><?php echo esc_html(sprintf(__('Recent %d', 'wp-crypto-direct-gateway'), count($payments))); ?></span>
                </div>
            </section>

            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Payment updated.', 'wp-crypto-direct-gateway'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['rescanned'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Payment rescanned.', 'wp-crypto-direct-gateway'); ?></p></div>
            <?php endif; ?>

            <section class="wcdg-admin-panel">
            <table class="widefat striped wcdg-payments-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Reference', 'wp-crypto-direct-gateway'); ?></th>
                        <th><?php esc_html_e('Source', 'wp-crypto-direct-gateway'); ?></th>
                        <th><?php esc_html_e('Customer', 'wp-crypto-direct-gateway'); ?></th>
                        <th><?php esc_html_e('Amount', 'wp-crypto-direct-gateway'); ?></th>
                        <th><?php esc_html_e('Wallet', 'wp-crypto-direct-gateway'); ?></th>
                        <th><?php esc_html_e('Status', 'wp-crypto-direct-gateway'); ?></th>
                        <th><?php esc_html_e('Actions', 'wp-crypto-direct-gateway'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)) : ?>
                        <tr><td colspan="7"><?php esc_html_e('No payment requests found.', 'wp-crypto-direct-gateway'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $payment) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($payment['reference']); ?></strong><br /><small><?php echo esc_html($payment['created_at']); ?></small></td>
                            <td><?php echo esc_html($payment['source']); ?><?php if (! empty($payment['source_id'])) : ?><br /><small>#<?php echo esc_html((string) $payment['source_id']); ?></small><?php endif; ?></td>
                            <td><?php echo esc_html($payment['customer_name'] ?: 'Guest'); ?><br /><small><?php echo esc_html($payment['customer_email']); ?></small></td>
                            <td><?php echo esc_html(number_format((float) $payment['fiat_amount'], 2) . ' ' . $payment['fiat_currency']); ?><br /><small><?php echo esc_html($payment['crypto_amount'] . ' ' . $payment['crypto_currency']); ?></small></td>
                            <td><?php echo esc_html($payment['wallet_label']); ?><br /><small><?php echo esc_html($payment['wallet_network']); ?></small></td>
                            <td><?php echo esc_html(ucfirst($payment['status'])); ?><?php if (! empty($payment['tx_hash'])) : ?><br /><small><?php echo esc_html($payment['tx_hash']); ?></small><?php endif; ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('wcdg_mark_payment'); ?>
                                    <input type="hidden" name="action" value="wcdg_mark_payment" />
                                    <input type="hidden" name="reference" value="<?php echo esc_attr($payment['reference']); ?>" />
                                    <select name="status">
                                        <?php foreach (array('pending', 'confirming', 'paid', 'expired', 'failed', 'cancelled') as $status) : ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected($payment['status'], $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="tx_hash" class="regular-text" value="<?php echo esc_attr((string) $payment['tx_hash']); ?>" placeholder="<?php esc_attr_e('Tx hash', 'wp-crypto-direct-gateway'); ?>" />
                                    <input type="text" name="notes" class="regular-text" value="<?php echo esc_attr((string) $payment['notes']); ?>" placeholder="<?php esc_attr_e('Notes', 'wp-crypto-direct-gateway'); ?>" />
                                    <button type="submit" class="button button-secondary"><?php esc_html_e('Update', 'wp-crypto-direct-gateway'); ?></button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:0.5rem;">
                                    <?php wp_nonce_field('wcdg_rescan_payment'); ?>
                                    <input type="hidden" name="action" value="wcdg_rescan_payment" />
                                    <input type="hidden" name="reference" value="<?php echo esc_attr($payment['reference']); ?>" />
                                    <button type="submit" class="button button-link-delete"><?php esc_html_e('Rescan chain', 'wp-crypto-direct-gateway'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </section>
        </div>
        <?php
    }
}