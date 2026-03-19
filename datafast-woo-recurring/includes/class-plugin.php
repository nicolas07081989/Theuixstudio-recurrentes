<?php
namespace DFWR;

if (! defined('ABSPATH')) {
    exit;
}

require_once DFWR_PLUGIN_DIR . 'includes/class-settings.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-logger.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-environment.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-utils.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-http-client.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-tax-mapper.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-customer-data.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-order-mapper.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-token-repository.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-transaction-repository.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-subscription-repository.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-cron.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-verifier.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-webhook-or-return-handler.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once DFWR_PLUGIN_DIR . 'includes/gateways/class-wc-gateway-datafast.php';

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function bootstrap(): void
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        Settings::init();
        Admin_Pages::init();
        Return_Handler::init();
        Cron::init();
        $this->register_checkout_fields();

        add_filter('woocommerce_payment_gateways', static function (array $gateways): array {
            $gateways[] = Gateway_Datafast::class;
            return $gateways;
        });

        add_action('woocommerce_order_status_processing', [$this, 'maybe_create_subscription_from_order']);
        add_action('woocommerce_order_status_completed', [$this, 'maybe_create_subscription_from_order']);
    }

    private function register_checkout_fields(): void
    {
        add_filter('woocommerce_checkout_fields', static function (array $fields): array {
            $fields['billing']['billing_identification_doc_id'] = [
                'type' => 'text',
                'label' => __('Identificación', 'datafast-woo-recurring'),
                'required' => true,
                'priority' => 130,
            ];
            $fields['billing']['billing_middle_name'] = [
                'type' => 'text',
                'label' => __('Segundo nombre', 'datafast-woo-recurring'),
                'required' => false,
                'priority' => 35,
            ];
            return $fields;
        });

        add_action('woocommerce_checkout_create_order', static function ($order, array $data): void {
            if (! empty($data['billing_identification_doc_id'])) {
                $order->update_meta_data('_billing_identification_doc_id', sanitize_text_field($data['billing_identification_doc_id']));
            }
            if (! empty($data['billing_middle_name'])) {
                $order->update_meta_data('_billing_middle_name', sanitize_text_field($data['billing_middle_name']));
            }
        }, 10, 2);
    }

    public function maybe_create_subscription_from_order(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $has_recurring = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_meta('_dfwr_is_recurring') === 'yes') {
                $has_recurring = true;
                break;
            }
        }

        if (! $has_recurring || $order->get_meta('_dfwr_subscription_created') === 'yes') {
            return;
        }

        $token = $order->get_meta('_dfwr_registration_id');
        if (! $token) {
            $order->add_order_note(__('Datafast: no se pudo crear suscripción interna (sin token).', 'datafast-woo-recurring'));
            return;
        }

        $sub_repo = new Subscription_Repository();
        $sub_repo->create_from_order($order, $token);
        $order->update_meta_data('_dfwr_subscription_created', 'yes');
        $order->save();
    }
}
