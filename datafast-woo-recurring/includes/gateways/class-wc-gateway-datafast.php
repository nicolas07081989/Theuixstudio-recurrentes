<?php
namespace DFWR;

use WC_Order;
use WC_Payment_Gateway;

class Gateway_Datafast extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'pg_woocommerce';
        $this->method_title = __('Datafast', 'datafast-woo-recurring');
        $this->method_description = __('Pasarela Datafast Dataweb', 'datafast-woo-recurring');
        $this->has_fields = false;
        $this->icon = DFWR_PLUGIN_URL . 'assets/css/datafast.png';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = (string) $this->get_option('title', 'Tarjeta de crédito / débito');
        $this->description = (string) $this->get_option('description', 'Pago seguro con Datafast');
        $this->enabled = (string) $this->get_option('enabled', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        add_action('woocommerce_order_refunded', [$this, 'on_order_refunded']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => ['title' => __('Habilitar', 'datafast-woo-recurring'), 'type' => 'checkbox', 'label' => __('Habilitar Datafast', 'datafast-woo-recurring'), 'default' => 'no'],
            'title' => ['title' => __('Título', 'datafast-woo-recurring'), 'type' => 'text', 'default' => 'Pago con tarjeta'],
            'description' => ['title' => __('Descripción', 'datafast-woo-recurring'), 'type' => 'textarea', 'default' => 'Paga de forma segura con Datafast'],
            'environment' => ['title' => __('Modo sandbox', 'datafast-woo-recurring'), 'type' => 'checkbox', 'default' => 'yes'],
            'base_url_checkout_test' => ['title' => __('Base URL checkout test', 'datafast-woo-recurring'), 'type' => 'text', 'default' => 'https://eu-test.oppwa.com'],
            'base_url_checkout_prod' => ['title' => __('Base URL checkout prod', 'datafast-woo-recurring'), 'type' => 'text', 'default' => 'https://eu-prod.oppwa.com'],
            'entity_id_test' => ['title' => __('Entity ID test', 'datafast-woo-recurring'), 'type' => 'text'],
            'bearer_token_test' => ['title' => __('Bearer test', 'datafast-woo-recurring'), 'type' => 'password'],
            'entity_id_prod' => ['title' => __('Entity ID prod', 'datafast-woo-recurring'), 'type' => 'text'],
            'bearer_token_prod' => ['title' => __('Bearer prod', 'datafast-woo-recurring'), 'type' => 'password'],
            'shopper_mid_test' => ['title' => __('MID test', 'datafast-woo-recurring'), 'type' => 'text'],
            'shopper_tid_test' => ['title' => __('TID test', 'datafast-woo-recurring'), 'type' => 'text'],
            'shopper_eci_test' => ['title' => __('ECI test', 'datafast-woo-recurring'), 'type' => 'text', 'default' => '0103910'],
            'shopper_pserv_test' => ['title' => __('PSERV test', 'datafast-woo-recurring'), 'type' => 'text', 'default' => '17913101'],
            'shopper_versiondf_test' => ['title' => __('VERSIONDF test', 'datafast-woo-recurring'), 'type' => 'text', 'default' => '2'],
            'language' => ['title' => __('Locale widget', 'datafast-woo-recurring'), 'type' => 'select', 'options' => ['es' => 'es', 'en' => 'en'], 'default' => 'es'],
        ];
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            wc_add_notice(__('Pedido inválido.', 'datafast-woo-recurring'), 'error');
            return ['result' => 'fail'];
        }

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }

    public function receipt_page($order): void
    {
        $order = $order instanceof WC_Order ? $order : wc_get_order((int) $order);
        if (! $order) {
            return;
        }

        try {
            $merchant_tx = Utils::merchant_transaction_id($order->get_id(), 'DF');
            $payload = (new Order_Mapper())->build_checkout_payload($order, $merchant_tx, isset($_POST['createRegistration']));
            $repo = new Transaction_Repository();
            $repo->create([
                'order_id' => $order->get_id(),
                'merchant_transaction_id' => $merchant_tx,
                'operation_type' => 'checkout',
                'status' => 'pending',
                'request_payload' => wp_json_encode($payload),
                'amount' => $order->get_total(),
                'currency' => 'USD',
            ]);

            $cfg = Environment::checkout_config();
            $result = (new Http_Client())->post_form(rtrim($cfg['base_url'], '/') . '/v1/checkouts', $payload, $cfg['bearer'], $cfg['mode'] !== 'prod');
            if (! $result['ok']) {
                throw new \RuntimeException($result['error'] ?? 'Error creando checkout');
            }
            $checkout_id = $result['body']['id'] ?? '';
            if (! $checkout_id) {
                throw new \RuntimeException('Respuesta sin checkoutId');
            }

            $order->update_meta_data('_dfwr_merchant_transaction_id', $merchant_tx);
            $order->update_meta_data('_dfwr_checkout_id', $checkout_id);
            $order->save();

            $return_url = add_query_arg(['wc-api' => 'dfwr_return', 'order_id' => $order->get_id()], home_url('/'));
            $lang = $this->get_option('language', 'es');

            echo '<p>' . esc_html__('Serás redirigido al formulario seguro de Datafast.', 'datafast-woo-recurring') . '</p>';
            echo '<script src="' . esc_url(rtrim($cfg['base_url'], '/') . '/v1/paymentWidgets.js?checkoutId=' . rawurlencode($checkout_id)) . '"></script>';
            echo '<form action="' . esc_url($return_url) . '" class="paymentWidgets" data-brands="VISA MASTER AMEX DISCOVER"></form>';
            echo '<script>var wpwlOptions={locale:"' . esc_js($lang) . '",style:"card",registrations:{hideInitialPaymentForms:false}};</script>';
        } catch (\Throwable $e) {
            wc_print_notice(sprintf(__('No se pudo iniciar el pago Datafast: %s', 'datafast-woo-recurring'), $e->getMessage()), 'error');
        }
    }

    public function finalize_order_from_resource_path(WC_Order $order, string $resource_path): void
    {
        $verify = (new Verifier())->verify_by_resource_path($resource_path);
        $body = $verify['body'] ?? [];
        $state = Verifier::classify($body);
        $merchant_tx = (string) $order->get_meta('_dfwr_merchant_transaction_id');

        (new Transaction_Repository())->update_by_merchant_tx($merchant_tx, [
            'operation_type' => 'verify',
            'response_payload' => wp_json_encode($body),
            'resource_path' => $resource_path,
            'payment_id' => $body['id'] ?? '',
            'registration_id' => $body['registrationId'] ?? '',
            'result_code' => $body['result']['code'] ?? '',
            'result_description' => $body['result']['description'] ?? '',
            'status' => $state,
        ]);

        if ($state === 'approved') {
            $order->payment_complete($body['id'] ?? '');
            $order->add_order_note('Datafast aprobado: ' . ($body['result']['description'] ?? 'OK'));
            if (! empty($body['registrationId']) && $order->get_user_id()) {
                $token_repo = new Token_Repository();
                $token_repo->upsert([
                    'wp_user_id' => $order->get_user_id(),
                    'merchant_customer_id' => Utils::merchant_customer_id((int) $order->get_user_id()),
                    'registration_id' => $body['registrationId'],
                    'brand' => $body['paymentBrand'] ?? null,
                    'last4' => $body['card']['last4Digits'] ?? null,
                    'expiry_month' => $body['card']['expiryMonth'] ?? null,
                    'expiry_year' => $body['card']['expiryYear'] ?? null,
                ]);
                $order->update_meta_data('_dfwr_registration_id', $body['registrationId']);
                $order->save();
            }
            WC()->cart?->empty_cart();
            return;
        }

        if ($state === 'pending') {
            $order->update_status('on-hold', 'Datafast pendiente de confirmación.');
            return;
        }

        $order->update_status('failed', 'Datafast rechazado: ' . ($body['result']['description'] ?? 'Sin detalle'));
    }

    public function charge_subscription(array $sub): void
    {
        $cfg = Environment::recurring_config();
        if (empty($cfg['base_url']) || empty($cfg['entity_id']) || empty($cfg['bearer'])) {
            return;
        }
        $merchant_tx = Utils::merchant_transaction_id((int) $sub['parent_order_id'], 'DFR');
        $payload = [
            'entityId' => $cfg['entity_id'],
            'amount' => number_format((float) $sub['amount'], 2, '.', ''),
            'currency' => 'USD',
            'paymentType' => 'DB',
            'recurringType' => 'REPEATED',
            'merchantTransactionId' => $merchant_tx,
            'risk.parameters[USER_DATA1]' => 'REPEATED',
            'risk.parameters[USER_DATA2]' => $cfg['user_data2'],
            'customParameters[SHOPPER_MID]' => $cfg['mid'],
            'customParameters[SHOPPER_TID]' => $cfg['tid'],
            'customParameters[SHOPPER_VERSIONDF]' => '2',
        ];
        if ($cfg['mode'] === 'test') {
            $payload['testMode'] = 'EXTERNAL';
        }

        $endpoint = rtrim($cfg['base_url'], '/') . '/v1/registrations/' . rawurlencode($sub['registration_id']) . '/payments';
        $result = (new Http_Client())->post_form($endpoint, $payload, $cfg['bearer'], $cfg['mode'] !== 'prod');
        $body = $result['body'] ?? [];
        $state = Verifier::classify($body);

        $tx_repo = new Transaction_Repository();
        $tx_repo->create([
            'order_id' => null,
            'subscription_id' => $sub['id'],
            'merchant_transaction_id' => $merchant_tx,
            'payment_id' => $body['id'] ?? null,
            'registration_id' => $sub['registration_id'],
            'operation_type' => 'recurring',
            'request_payload' => wp_json_encode($payload),
            'response_payload' => wp_json_encode($body),
            'result_code' => $body['result']['code'] ?? null,
            'result_description' => $body['result']['description'] ?? null,
            'status' => $state,
            'amount' => $sub['amount'],
            'currency' => 'USD',
        ]);

        $sub_repo = new Subscription_Repository();
        if ($state === 'approved') {
            $parent = wc_get_order((int) $sub['parent_order_id']);
            $renewal = wc_create_order(['customer_id' => (int) $sub['wp_user_id']]);
            if ($parent) {
                foreach ($parent->get_items() as $item) {
                    $renewal->add_product($item->get_product(), $item->get_quantity());
                }
            }
            $renewal->calculate_totals();
            $renewal->set_payment_method($this->id);
            $renewal->payment_complete($body['id'] ?? '');
            $renewal->add_order_note('Renovación Datafast aprobada.');

            $sub_repo->update((int) $sub['id'], [
                'status' => 'active',
                'retry_count' => 0,
                'renewal_order_id' => $renewal->get_id(),
                'last_payment_at' => current_time('mysql'),
                'next_payment_at' => gmdate('Y-m-d H:i:s', strtotime('+' . (int) $sub['billing_interval'] . ' ' . $sub['billing_period'])),
                'last_error' => null,
            ]);
            $sub_repo->add_event((int) $sub['id'], 'recurring_success', 'Cobro recurrente aprobado', $body);
            return;
        }

        $retry_count = ((int) $sub['retry_count']) + 1;
        $max = (int) $sub['max_retries'];
        $hours = (int) Settings::get('recurring_retry_spacing_hours', 24);
        $next = gmdate('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'));
        $new_status = $retry_count >= $max ? 'past_due' : $sub['status'];
        $sub_repo->update((int) $sub['id'], [
            'status' => $new_status,
            'retry_count' => $retry_count,
            'last_error' => $body['result']['description'] ?? ($result['error'] ?? 'Error recurrente'),
            'next_payment_at' => $next,
        ]);
        $sub_repo->add_event((int) $sub['id'], 'recurring_failed', 'Cobro recurrente fallido', $body);
    }

    public function on_order_refunded(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note(__('Datafast: registrar anulación manual según BIP/flujo adquirente.', 'datafast-woo-recurring'));
        }
    }
}
