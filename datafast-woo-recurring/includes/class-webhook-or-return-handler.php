<?php
namespace DFWR;

class Return_Handler
{
    public static function init(): void
    {
        add_action('woocommerce_api_dfwr_return', [__CLASS__, 'handle']);
        add_action('admin_post_dfwr_verify_order', [__CLASS__, 'admin_verify_order']);
    }

    public static function handle(): void
    {
        $is_legacy_confirm = (isset($_GET['paymentDatafast']) && sanitize_text_field(wp_unslash($_GET['paymentDatafast'])) === 'confirm');
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $resource_path = isset($_GET['resourcePath']) ? sanitize_text_field(wp_unslash($_GET['resourcePath'])) : '';

        if (! $order_id || ! $resource_path || ! $is_legacy_confirm) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $gateway = new Gateway_Datafast();
        $gateway->finalize_order_from_resource_path($order, $resource_path);

        wp_safe_redirect($order->get_checkout_order_received_url());
        exit;
    }

    public static function admin_verify_order(): void
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        if (! current_user_can('manage_woocommerce') || ! wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'dfwr_verify_order_' . $order_id)) {
            wp_die('No autorizado');
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            wp_safe_redirect(admin_url('edit.php?post_type=shop_order'));
            exit;
        }

        $merchant_tx = (string) $order->get_meta('_dfwr_merchant_transaction_id');
        if ($merchant_tx !== '') {
            $verify = (new Verifier())->verify_by_merchant_transaction_id($merchant_tx);
            $order->add_order_note('Verificación manual Datafast: ' . wp_json_encode($verify['body'] ?? []));
        }
        wp_safe_redirect(admin_url('post.php?post=' . $order_id . '&action=edit'));
        exit;
    }
}
