<?php
namespace DFWR;

use WC_Order;

class Subscription_Repository
{
    private string $last_error = '';

    public function get_last_error(): string
    {
        return $this->last_error;
    }

    public function create_from_order(WC_Order $order, string $registration_id): int
    {
        global $wpdb;
        $this->last_error = '';
        $table = $wpdb->prefix . 'df_subscriptions';
        $interval = 1;
        $period = 'month';
        $product_id = 0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_meta('_dfwr_is_recurring') === 'yes') {
                $product_id = $product->get_id();
                $interval = (int) ($product->get_meta('_dfwr_interval') ?: 1);
                $period = (string) ($product->get_meta('_dfwr_period') ?: 'month');
                break;
            }
        }
        $now = current_time('mysql');
        $next = gmdate('Y-m-d H:i:s', strtotime('+' . $interval . ' ' . $period));
        $merchant_customer_id = $order->get_user_id() ? Utils::merchant_customer_id((int) $order->get_user_id()) : 'GUEST-' . $order->get_id();

        $insert_result = $wpdb->insert($table, [
            'product_id' => $product_id,
            'parent_order_id' => $order->get_id(),
            'wp_user_id' => (int) $order->get_user_id(),
            'merchant_customer_id' => $merchant_customer_id,
            'registration_id' => $registration_id,
            'status' => 'active',
            'billing_interval' => $interval,
            'billing_period' => $period,
            'amount' => $order->get_total(),
            'currency' => 'USD',
            'next_payment_at' => $next,
            'retry_count' => 0,
            'max_retries' => (int) Settings::get('recurring_max_retries', 3),
            'environment' => Environment::mode(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Logger::log('Subscription create_from_order insert', [
            'order_id' => $order->get_id(),
            'registration_id' => $registration_id,
            'product_id' => $product_id,
            'billing_interval' => $interval,
            'billing_period' => $period,
            'insert_result' => $insert_result,
            'insert_id' => (int) $wpdb->insert_id,
            'last_error' => (string) $wpdb->last_error,
        ]);

        if ($insert_result === false) {
            $this->last_error = (string) $wpdb->last_error;
            return 0;
        }

        $id = (int) $wpdb->insert_id;
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE id = %d", $id));
        Logger::log('Subscription create_from_order verify', [
            'order_id' => $order->get_id(),
            'subscription_id' => $id,
            'exists' => $exists > 0 ? 'yes' : 'no',
            'last_error' => (string) $wpdb->last_error,
        ]);
        if ($exists <= 0) {
            $this->last_error = 'No se encontró la fila insertada en df_subscriptions.';
            return 0;
        }

        $this->add_event($id, 'created', 'Suscripción creada desde orden ' . $order->get_id());
        return $id;
    }

    public function due_subscriptions(): array
    {
        global $wpdb;
        $now = current_time('mysql');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}df_subscriptions WHERE status IN ('active','past_due') AND next_payment_at <= %s", $now), ARRAY_A) ?: [];
    }

    public function update(int $id, array $data): void
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($wpdb->prefix . 'df_subscriptions', $data, ['id' => $id]);
    }

    public function cancel(int $id): void
    {
        $this->update($id, ['status' => 'canceled']);
        $this->add_event($id, 'canceled', 'Suscripción cancelada manualmente');
    }

    public function add_event(int $subscription_id, string $type, string $message, array $payload = []): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'df_subscription_events', [
            'subscription_id' => $subscription_id,
            'event_type' => $type,
            'message' => $message,
            'payload' => wp_json_encode($payload),
            'created_at' => current_time('mysql'),
        ]);
    }
}
