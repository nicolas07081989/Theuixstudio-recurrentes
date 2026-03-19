<?php
namespace DFWR;

class Token_Repository
{
    public function upsert(array $data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'df_tokens';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE registration_id = %s", $data['registration_id']));
        $now = current_time('mysql');
        $record = [
            'wp_user_id' => $data['wp_user_id'] ?? null,
            'merchant_customer_id' => $data['merchant_customer_id'],
            'registration_id' => $data['registration_id'],
            'brand' => $data['brand'] ?? null,
            'last4' => $data['last4'] ?? null,
            'expiry_month' => $data['expiry_month'] ?? null,
            'expiry_year' => $data['expiry_year'] ?? null,
            'is_default' => ! empty($data['is_default']) ? 1 : 0,
            'is_active' => 1,
            'environment' => Environment::mode(),
            'updated_at' => $now,
        ];

        if ($existing) {
            $wpdb->update($table, $record, ['id' => (int) $existing]);
            return;
        }
        $record['created_at'] = $now;
        $wpdb->insert($table, $record);
    }

    public function by_user(int $user_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}df_tokens WHERE wp_user_id = %d AND is_active = 1", $user_id), ARRAY_A) ?: [];
    }
}
