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
require_once DFWR_PLUGIN_DIR . 'includes/class-installments-service.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-token-repository.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-transaction-repository.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-subscription-repository.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-cron.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-verifier.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-webhook-or-return-handler.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-myaccount.php';

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

        if (! class_exists('WC_Payment_Gateway')) {
            return;
        }

        require_once DFWR_PLUGIN_DIR . 'includes/gateways/class-wc-gateway-datafast.php';

        Settings::init();
        Admin_Pages::init();
        Return_Handler::init();
        Rest_Controller::init();
        MyAccount::init();
        Cron::init();
        $this->register_checkout_fields();
        $this->register_recurring_product_fields();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_filter('woocommerce_get_checkout_url', [$this, 'filter_checkout_url_for_recurring_guests']);
        add_action('template_redirect', [$this, 'maybe_show_recurring_login_notice']);

        add_filter('woocommerce_payment_gateways', static function (array $gateways): array {
            $gateways[] = Gateway_Datafast::class;
            return $gateways;
        });

        add_action('woocommerce_order_status_processing', [$this, 'maybe_create_subscription_from_order']);
        add_action('woocommerce_order_status_completed', [$this, 'maybe_create_subscription_from_order']);
    }

    public function enqueue_frontend_assets(): void
    {
        if (! function_exists('is_woocommerce') || (! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page())) {
            return;
        }

        wp_enqueue_style('dfwr-checkout-style', DFWR_PLUGIN_URL . 'assets/css/checkout.css', [], DFWR_VERSION);
        wp_enqueue_script('dfwr-checkout-script', DFWR_PLUGIN_URL . 'assets/js/checkout.js', [], DFWR_VERSION, true);
    }

    public function filter_checkout_url_for_recurring_guests(string $checkout_url): string
    {
        if (is_admin() || wp_doing_ajax() || is_user_logged_in() || ! $this->cart_has_recurring_product()) {
            return $checkout_url;
        }

        $target = wc_get_page_permalink('myaccount');
        return add_query_arg(
            [
                'dfwr_recurring_gate' => '1',
                'redirect_to' => rawurlencode($checkout_url),
            ],
            $target
        );
    }

    public function maybe_show_recurring_login_notice(): void
    {
        if (is_admin() || wp_doing_ajax() || ! function_exists('is_account_page') || ! is_account_page()) {
            return;
        }
        $show_notice = isset($_GET['dfwr_recurring_gate']) && sanitize_text_field(wp_unslash($_GET['dfwr_recurring_gate'])) === '1';
        if ($show_notice) {
            wc_add_notice(__('Para contratar una suscripción debes iniciar sesión o crear una cuenta.', 'datafast-woo-recurring'), 'notice');
        }
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

        add_action('woocommerce_checkout_process', static function (): void {
            $doc = isset($_POST['billing_identification_doc_id']) ? sanitize_text_field(wp_unslash($_POST['billing_identification_doc_id'])) : '';
            if (strlen(preg_replace('/\D+/', '', $doc)) < 5) {
                wc_add_notice(__('La identificación es obligatoria y debe contener al menos 5 dígitos.', 'datafast-woo-recurring'), 'error');
            }

            $has_recurring = false;
            foreach (WC()->cart?->get_cart() ?? [] as $cart_item) {
                $product = $cart_item['data'] ?? null;
                if ($product && $product->get_meta('_dfwr_is_recurring') === 'yes') {
                    $has_recurring = true;
                    break;
                }
            }

            if (! $has_recurring) {
                return;
            }

            $selected_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
            if ($selected_method !== 'pg_woocommerce') {
                return;
            }

            $creating_account = ! empty($_POST['createaccount']);
            if (! is_user_logged_in() && ! $creating_account) {
                wc_add_notice(__('Para productos recurrentes debes iniciar sesión o crear una cuenta.', 'datafast-woo-recurring'), 'error');
            }

            $selected_registration = isset($_POST['dfwr_registration']) ? sanitize_text_field(wp_unslash($_POST['dfwr_registration'])) : '';
            $create_registration = ! empty($_POST['createRegistration']);
            if ($selected_registration === '' && ! $create_registration) {
                wc_add_notice(__('Para productos recurrentes debes guardar tarjeta o seleccionar una tarjeta guardada.', 'datafast-woo-recurring'), 'error');
            }
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

    private function cart_has_recurring_product(): bool
    {
        foreach (WC()->cart?->get_cart() ?? [] as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if ($product && $product->get_meta('_dfwr_is_recurring') === 'yes') {
                return true;
            }
        }

        return false;
    }

    private function register_recurring_product_fields(): void
    {
        add_action('woocommerce_product_options_general_product_data', static function (): void {
            woocommerce_wp_checkbox(['id' => '_dfwr_is_recurring', 'label' => __('Producto recurrente', 'datafast-woo-recurring')]);
            woocommerce_wp_text_input(['id' => '_dfwr_interval', 'label' => __('Intervalo recurrente', 'datafast-woo-recurring'), 'type' => 'number', 'custom_attributes' => ['min' => 1]]);
            woocommerce_wp_select(['id' => '_dfwr_period', 'label' => __('Periodo', 'datafast-woo-recurring'), 'options' => ['day' => 'Día', 'week' => 'Semana', 'month' => 'Mes', 'year' => 'Año']]);
        });
        add_action('woocommerce_process_product_meta', static function (int $product_id): void {
            update_post_meta($product_id, '_dfwr_is_recurring', isset($_POST['_dfwr_is_recurring']) ? 'yes' : 'no');
            update_post_meta($product_id, '_dfwr_interval', isset($_POST['_dfwr_interval']) ? max(1, (int) $_POST['_dfwr_interval']) : 1);
            update_post_meta($product_id, '_dfwr_period', isset($_POST['_dfwr_period']) ? sanitize_text_field(wp_unslash($_POST['_dfwr_period'])) : 'month');
        });
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
        $subscription_id = $sub_repo->create_from_order($order, $token);
        if ($subscription_id > 0) {
            $order->update_meta_data('_dfwr_subscription_created', 'yes');
            $order->add_order_note(sprintf('Datafast: suscripción interna creada correctamente (ID %d).', $subscription_id));
            $order->save();
            return;
        }

        $error = $sub_repo->get_last_error();
        $order->add_order_note('Datafast: no se pudo crear suscripción interna en BD.' . ($error !== '' ? ' Error: ' . $error : ''));
        $order->save();
    }
}
