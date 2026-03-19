<?php
namespace DFWR;

class Admin_Pages
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu(): void
    {
        add_menu_page('Datafast', 'Datafast', 'manage_woocommerce', 'dfwr-transactions', [__CLASS__, 'transactions_page'], 'dashicons-money-alt');
        add_submenu_page('dfwr-transactions', 'Transacciones', 'Transacciones', 'manage_woocommerce', 'dfwr-transactions', [__CLASS__, 'transactions_page']);
        add_submenu_page('dfwr-transactions', 'Suscripciones', 'Suscripciones', 'manage_woocommerce', 'dfwr-subscriptions', [__CLASS__, 'subscriptions_page']);
        add_submenu_page('dfwr-transactions', 'Tokens', 'Tokens', 'manage_woocommerce', 'dfwr-tokens', [__CLASS__, 'tokens_page']);
        add_submenu_page('dfwr-transactions', 'Tipos de Crédito', 'Tipos de Crédito', 'manage_woocommerce', 'dfwr-termtypes', [__CLASS__, 'termtypes_page']);
        add_submenu_page('dfwr-transactions', 'Herramientas', 'Herramientas', 'manage_woocommerce', 'dfwr-tools', [__CLASS__, 'tools_page']);
    }

    public static function transactions_page(): void
    {
        global $wpdb;
        $where = '1=1';
        if (! empty($_GET['order_id'])) {
            $where .= $wpdb->prepare(' AND order_id = %d', absint($_GET['order_id']));
        }
        if (! empty($_GET['merchant_transaction_id'])) {
            $where .= $wpdb->prepare(' AND merchant_transaction_id = %s', sanitize_text_field(wp_unslash($_GET['merchant_transaction_id'])));
        }
        if (! empty($_GET['payment_id'])) {
            $where .= $wpdb->prepare(' AND payment_id = %s', sanitize_text_field(wp_unslash($_GET['payment_id'])));
        }
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}df_transactions WHERE {$where} ORDER BY id DESC LIMIT 100", ARRAY_A) ?: [];

        echo '<div class="wrap"><h1>Datafast Transacciones</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="dfwr-transactions"/>';
        echo '<input name="order_id" placeholder="order_id"/> <input name="merchant_transaction_id" placeholder="merchantTransactionId"/> <input name="payment_id" placeholder="payment_id"/> <button class="button">Filtrar</button></form>';
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Order</th><th>MerchantTx</th><th>Payment</th><th>Status</th><th>Result</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html((string) $r['id']) . '</td><td>' . esc_html((string) $r['order_id']) . '</td><td><code>' . esc_html((string) $r['merchant_transaction_id']) . '</code></td><td><code>' . esc_html((string) $r['payment_id']) . '</code></td><td>' . esc_html((string) $r['status']) . '</td><td>' . esc_html((string) $r['result_code']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function subscriptions_page(): void
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}df_subscriptions ORDER BY id DESC LIMIT 100", ARRAY_A) ?: [];
        echo '<div class="wrap"><h1>Datafast Suscripciones</h1><table class="widefat"><thead><tr><th>ID</th><th>Status</th><th>Next</th><th>Retry</th><th>Token</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html((string) $r['id']) . '</td><td>' . esc_html((string) $r['status']) . '</td><td>' . esc_html((string) $r['next_payment_at']) . '</td><td>' . esc_html((string) $r['retry_count']) . '/' . esc_html((string) $r['max_retries']) . '</td><td><code>' . esc_html((string) $r['registration_id']) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function tokens_page(): void
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}df_tokens ORDER BY id DESC LIMIT 100", ARRAY_A) ?: [];
        echo '<div class="wrap"><h1>Datafast Tokens</h1><table class="widefat"><thead><tr><th>ID</th><th>User</th><th>Token</th><th>Estado</th><th>Last4</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html((string) $r['id']) . '</td><td>' . esc_html((string) $r['wp_user_id']) . '</td><td><code>' . esc_html((string) $r['registration_id']) . '</code></td><td>' . esc_html((string) $r['is_active']) . '</td><td>' . esc_html((string) $r['last4']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }


    public static function termtypes_page(): void
    {
        $svc = new Installments_Service();
        $types = $svc->get_credit_types();
        echo '<div class="wrap"><h1>Tipos de Crédito (legacy)</h1><table class="widefat"><thead><tr><th>Código</th><th>Descripción</th></tr></thead><tbody>';
        foreach ($types as $code => $label) {
            echo '<tr><td><code>' . esc_html((string) $code) . '</code></td><td>' . esc_html((string) $label) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function tools_page(): void
    {
        $order = null;
        if (! empty($_GET['dry_order_id'])) {
            $order = wc_get_order(absint($_GET['dry_order_id']));
        }
        echo '<div class="wrap"><h1>Datafast Diagnóstico</h1>';
        echo '<p>Conectividad checkout: ' . esc_html(rtrim((string) Environment::checkout_config()['base_url'], '/')) . '</p>';
        echo '<p>Conectividad recurrente: ' . esc_html(rtrim((string) Environment::recurring_config()['base_url'], '/')) . '</p>';
        echo '<form method="get"><input type="hidden" name="page" value="dfwr-tools"/><input name="dry_order_id" placeholder="Order ID dry-run"/> <button class="button">Generar payload dry-run</button></form>';
        if ($order) {
            try {
                $payload = (new Order_Mapper())->build_checkout_payload($order, Utils::merchant_transaction_id($order->get_id(), 'DRY'), false);
                echo '<h3>Payload checkout dry-run</h3><pre>' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT)) . '</pre>';
            } catch (\Throwable $e) {
                echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
            }
        }
        echo '</div>';
    }
}
