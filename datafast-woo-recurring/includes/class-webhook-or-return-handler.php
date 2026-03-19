<?php
namespace DFWR;

class Return_Handler
{
    public static function init(): void
    {
        add_action('woocommerce_api_dfwr_return', [__CLASS__, 'handle']);
    }

    public static function handle(): void
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $resource_path = isset($_GET['resourcePath']) ? sanitize_text_field(wp_unslash($_GET['resourcePath'])) : '';

        if (! $order_id || ! $resource_path) {
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
}
