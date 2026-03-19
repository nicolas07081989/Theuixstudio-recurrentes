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
        $this->has_fields = true;
        $this->icon = DFWR_PLUGIN_URL . 'assets/css/datafast.png';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = (string) $this->get_option('title', 'Tarjeta de crédito / débito');
        $this->description = (string) $this->get_option('description', 'Pago seguro con Datafast');
        $this->enabled = (string) $this->get_option('enabled', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        add_action('woocommerce_order_refunded', [$this, 'on_order_refunded']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'admin_order_actions']);
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

    public function payment_fields(): void
    {
        echo wp_kses_post(wpautop($this->description));
        if (is_user_logged_in()) {
            $tokens = (new Token_Repository())->by_user(get_current_user_id());
            if ($tokens) {
                echo '<p><strong>' . esc_html__('Tarjetas guardadas', 'datafast-woo-recurring') . '</strong></p>';
                foreach ($tokens as $idx => $token) {
                    echo '<label><input type="radio" name="dfwr_registration" value="' . esc_attr((string) $token['registration_id']) . '" ' . checked($idx, 0, false) . '> ' . esc_html(($token['brand'] ?: 'CARD') . ' ****' . ($token['last4'] ?: '')) . '</label><br>';
                }
            }
        }
        if (Settings::get('enabled_tokenization', 'yes') === 'yes') {
            echo '<p><label><input type="checkbox" name="createRegistration" value="1"> ' . esc_html__('Guardar tarjeta de forma segura', 'datafast-woo-recurring') . '</label></p>';
        }
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            wc_add_notice(__('Pedido inválido.', 'datafast-woo-recurring'), 'error');
            return ['result' => 'fail'];
        }

        $create_registration = ! empty($_POST['createRegistration']);
        $selected_registration = isset($_POST['dfwr_registration']) ? sanitize_text_field(wp_unslash($_POST['dfwr_registration'])) : '';
        $order->update_meta_data('_dfwr_create_registration_requested', $create_registration ? 'yes' : 'no');
        if ($selected_registration !== '') {
            $order->update_meta_data('_dfwr_selected_registration', $selected_registration);
        } else {
            $order->delete_meta_data('_dfwr_selected_registration');
        }
        $order->save();

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }

    public function generate_datafast_form(int $order_id): string
    {
        ob_start();
        $this->receipt_page($order_id);
        return (string) ob_get_clean();
    }

    public function buildInitialBody(WC_Order $order, string $merchant_tx, bool $create_registration): array
    {
        return (new Order_Mapper())->build_checkout_payload($order, $merchant_tx, $create_registration);
    }

    public function receipt_page($order): void
    {
        $order = $order instanceof WC_Order ? $order : wc_get_order((int) $order);
        if (! $order) {
            return;
        }

        try {
            $merchant_tx = Utils::merchant_transaction_id($order->get_id(), Settings::get('prefijo_trx', 'DF'));
            $selected_registration = isset($_POST['dfwr_registration']) ? sanitize_text_field(wp_unslash($_POST['dfwr_registration'])) : '';
            if ($selected_registration === '') {
                $selected_registration = (string) $order->get_meta('_dfwr_selected_registration');
            }
            $create_registration = ((string) $order->get_meta('_dfwr_create_registration_requested') === 'yes');
            if (isset($_POST['createRegistration'])) {
                $create_registration = true;
            }
            $payload = $this->buildInitialBody($order, $merchant_tx, $create_registration);
            $requires_recurring_token = false;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && $product->get_meta('_dfwr_is_recurring') === 'yes') {
                    $requires_recurring_token = true;
                    break;
                }
            }
            if ($requires_recurring_token && $selected_registration === '' && ! $create_registration) {
                throw new \RuntimeException(__('Para productos recurrentes debes guardar tarjeta o seleccionar una tarjeta guardada.', 'datafast-woo-recurring'));
            }
            if ($selected_registration !== '') {
                $payload['registrations[0].id'] = $selected_registration;
            }

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
            Logger::log('Checkout request', [
                'order_id' => $order->get_id(),
                'create_registration_requested' => $create_registration ? 'yes' : 'no',
                'create_registration_sent' => isset($payload['createRegistration']) ? 'yes' : 'no',
                'payload' => $payload,
            ]);
            $result = (new Http_Client())->post_form(rtrim($cfg['base_url'], '/') . '/v1/checkouts', $payload, $cfg['bearer'], $cfg['mode'] !== 'prod');
            Logger::log('Checkout response', ['order_id' => $order->get_id(), 'response' => $result]);
            if (! $result['ok']) {
                throw new \RuntimeException($result['error'] ?? 'Error creando checkout');
            }
            $checkout_id = $result['body']['id'] ?? '';
            if (! $checkout_id) {
                throw new \RuntimeException('Respuesta sin checkoutId');
            }

            $order->update_meta_data('_dfwr_merchant_transaction_id', $merchant_tx);
            $order->update_meta_data('_dfwr_checkout_id', $checkout_id);
            $order->update_meta_data('_dfwr_create_registration_requested', $create_registration ? 'yes' : 'no');
            $order->update_meta_data('_dfwr_checkout_payload', wp_json_encode($payload));
            $order->add_order_note(sprintf(
                'Datafast: tokenización solicitada=%s; createRegistration enviado=%s.',
                $create_registration ? 'yes' : 'no',
                isset($payload['createRegistration']) ? 'yes' : 'no'
            ));
            $order->save();

            $return_url = add_query_arg(['wc-api' => 'dfwr_return', 'order_id' => $order->get_id(), 'paymentDatafast' => 'confirm'], home_url('/'));
            $lang = $this->get_option('language', 'es');

            echo '<p>' . esc_html__('Serás redirigido al formulario seguro de Datafast.', 'datafast-woo-recurring') . '</p>';
            echo '<script src="' . esc_url(rtrim($cfg['base_url'], '/') . '/v1/paymentWidgets.js?checkoutId=' . rawurlencode($checkout_id)) . '"></script>';
            echo '<form action="' . esc_url($return_url) . '" class="paymentWidgets" data-brands="VISA MASTER AMEX DISCOVER"></form>';
            echo '<script>var wpwlOptions={locale:"' . esc_js($lang) . '",style:"card",registrations:{hideInitialPaymentForms:false}};</script>';
        } catch (\Throwable $e) {
            wc_print_notice(sprintf(__('No se pudo iniciar el pago Datafast: %s', 'datafast-woo-recurring'), $e->getMessage()), 'error');
        }
    }

    public function processPayment(string $resourcePath): array
    {
        return (new Verifier())->verify_by_resource_path($resourcePath);
    }

    public function finalize_order_from_resource_path(WC_Order $order, string $resource_path): void
    {
        $verify = $this->processPayment($resource_path);
        $body = $verify['body'] ?? [];
        $state = Verifier::classify($body);
        Logger::log('Finalize order state', [
            'order_id' => $order->get_id(),
            'state' => $state,
            'payment_id' => $body['id'] ?? '',
        ]);
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
            $has_registration_id = ! empty($body['registrationId']);
            $registration_id = $has_registration_id ? (string) $body['registrationId'] : '';
            $selected_registration = (string) $order->get_meta('_dfwr_selected_registration');
            $final_registration_id = $registration_id !== '' ? $registration_id : $selected_registration;
            Logger::log('Checkout verify registrationId', [
                'order_id' => $order->get_id(),
                'selected_registration' => $selected_registration,
                'has_registration_id' => $has_registration_id ? 'yes' : 'no',
                'registration_id' => $registration_id,
                'final_registration_id' => $final_registration_id,
            ]);
            $order->update_meta_data('_dfwr_payment_id', $body['id'] ?? '');
            $order->update_meta_data('_dfwr_result_code', $body['result']['code'] ?? '');
            $order->update_meta_data('_dfwr_result_description', $body['result']['description'] ?? '');
            $order->update_meta_data('_dfwr_resource_path', $resource_path);
            if (! empty($body['customParameters']['SHOPPER_TIPOCREDITO'] ?? '')) {
                $order->update_meta_data('_dfwr_response_termtype', $body['customParameters']['SHOPPER_TIPOCREDITO']);
            }
            if (! empty($body['recurring']['numberOfInstallments'] ?? '')) {
                $order->update_meta_data('_dfwr_response_installments', $body['recurring']['numberOfInstallments']);
            } elseif (! empty($body['customParameters']['SHOPPER_DIFERIDO'] ?? '')) {
                $order->update_meta_data('_dfwr_response_installments', $body['customParameters']['SHOPPER_DIFERIDO']);
            }
            $order->update_meta_data('_dfwr_registration_id_received', $has_registration_id ? 'yes' : 'no');
            if ($has_registration_id) {
                $order->update_meta_data('_dfwr_registration_id_value', $registration_id);
            }
            if ($final_registration_id !== '') {
                $order->update_meta_data('_dfwr_registration_id', $final_registration_id);
            }
            if ($has_registration_id && $order->get_user_id()) {
                $token_repo = new Token_Repository();
                $token_repo->upsert([
                    'wp_user_id' => $order->get_user_id(),
                    'merchant_customer_id' => Utils::merchant_customer_id((int) $order->get_user_id()),
                    'registration_id' => $registration_id,
                    'brand' => $body['paymentBrand'] ?? null,
                    'last4' => $body['card']['last4Digits'] ?? null,
                    'expiry_month' => $body['card']['expiryMonth'] ?? null,
                    'expiry_year' => $body['card']['expiryYear'] ?? null,
                ]);
            }
            if ($registration_id !== '') {
                $order->add_order_note(sprintf('Datafast: token para suscripción tomado de response.registrationId (%s).', $registration_id));
            } elseif ($selected_registration !== '') {
                $order->add_order_note(sprintf('Datafast: token para suscripción tomado de tarjeta guardada seleccionada (%s).', $selected_registration));
            }
            $create_requested = (string) $order->get_meta('_dfwr_create_registration_requested');
            if ($create_requested === 'yes' && ! $has_registration_id) {
                $order->add_order_note(__('Datafast: tokenización solicitada pero la respuesta no devolvió registrationId.', 'datafast-woo-recurring'));
            }
            $order->add_order_note(sprintf(
                'Datafast: respuesta verificada con registrationId=%s.',
                $has_registration_id ? 'yes (' . $registration_id . ')' : 'no'
            ));
            $has_recurring = false;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && $product->get_meta('_dfwr_is_recurring') === 'yes') {
                    $has_recurring = true;
                    break;
                }
            }
            if ($has_recurring && empty($body['registrationId'])) {
                $order->add_order_note(__('Datafast: pago aprobado sin registrationId. No se creó suscripción interna.', 'datafast-woo-recurring'));
            }
            $order->save();

            $token_final = (string) $order->get_meta('_dfwr_registration_id');
            Logger::log('Finalize auto subscription attempt', [
                'order_id' => $order->get_id(),
                'state' => $state,
                'token_final_used' => $token_final,
                'has_recurring' => $has_recurring ? 'yes' : 'no',
                'subscription_created_before' => $order->get_meta('_dfwr_subscription_created') === 'yes' ? 'yes' : 'no',
                'order_saved_before_subscription_creation' => 'yes',
            ]);
            if ($has_recurring) {
                if ($token_final !== '') {
                    if ($order->get_meta('_dfwr_subscription_created') !== 'yes') {
                        $sub_repo = new Subscription_Repository();
                        $subscription_id = $sub_repo->create_from_order($order, $token_final);
                        $last_error = $sub_repo->get_last_error();
                        Logger::log('Finalize auto subscription result', [
                            'order_id' => $order->get_id(),
                            'final_registration_id' => $token_final,
                            'has_recurring' => 'yes',
                            'subscription_insert_result' => $subscription_id > 0 ? 'success' : 'failed',
                            'subscription_id' => $subscription_id,
                            'wpdb_last_error' => $last_error,
                        ]);
                        if ($subscription_id > 0) {
                            $order->update_meta_data('_dfwr_subscription_created', 'yes');
                            $order->add_order_note(sprintf('Datafast: suscripción creada automáticamente al aprobarse el pago. ID %d', $subscription_id));
                        } else {
                            $order->add_order_note('Datafast: fallo creación automática de suscripción.' . ($last_error !== '' ? ' Error: ' . $last_error : ''));
                        }
                        $order->save();
                    } else {
                        $order->add_order_note(__('Datafast: la suscripción ya estaba marcada como creada.', 'datafast-woo-recurring'));
                        $order->save();
                    }
                } else {
                    $order->add_order_note(__('Datafast: suscripción pendiente; pago aprobado sin token final para recurrencia.', 'datafast-woo-recurring'));
                    $order->save();
                }
            }
            $order->payment_complete($body['id'] ?? '');
            $order->add_order_note('Datafast aprobado: ' . ($body['result']['description'] ?? 'OK'));
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
        $parent = wc_get_order((int) $sub['parent_order_id']);
        $tax = $parent ? (new Tax_Mapper())->map($parent) : ['SHOPPER_VAL_BASE0' => '0.00', 'SHOPPER_VAL_BASEIMP' => number_format((float) $sub['amount'], 2, '.', ''), 'SHOPPER_VAL_IVA' => '0.00'];

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
            'customParameters[SHOPPER_ECI]' => '0103910',
            'customParameters[SHOPPER_PSERV]' => '17913101',
            'customParameters[SHOPPER_VERSIONDF]' => '2',
            'customParameters[SHOPPER_VAL_BASE0]' => $tax['SHOPPER_VAL_BASE0'],
            'customParameters[SHOPPER_VAL_BASEIMP]' => $tax['SHOPPER_VAL_BASEIMP'],
            'customParameters[SHOPPER_VAL_IVA]' => $tax['SHOPPER_VAL_IVA'],
        ];
        if ($cfg['mode'] === 'test') {
            $payload['testMode'] = 'EXTERNAL';
        }

        $endpoint = rtrim($cfg['base_url'], '/') . '/v1/registrations/' . rawurlencode($sub['registration_id']) . '/payments';
        Logger::log('Recurring request', ['subscription_id' => $sub['id'], 'payload' => $payload]);
        $result = (new Http_Client())->post_form($endpoint, $payload, $cfg['bearer'], $cfg['mode'] !== 'prod');
        Logger::log('Recurring response', ['subscription_id' => $sub['id'], 'response' => $result]);
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

    public function admin_order_actions(WC_Order $order): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        $order_id = $order->get_id();
        $url = wp_nonce_url(admin_url('admin-post.php?action=dfwr_verify_order&order_id=' . $order_id), 'dfwr_verify_order_' . $order_id);
        echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html__('Verificar estado Datafast', 'datafast-woo-recurring') . '</a></p>';
    }

    public function on_order_refunded(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note(__('Datafast: registrar anulación manual según BIP/flujo adquirente.', 'datafast-woo-recurring'));
        }
    }
}
