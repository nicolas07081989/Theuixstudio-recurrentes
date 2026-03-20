<?php
namespace DFWR;

class Transaction_Repository
{
    public function create(array $data): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'df_transactions';
        $now = current_time('mysql');
        $row = array_merge([
            'status' => 'created',
            'currency' => 'USD',
            'amount' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'environment' => Environment::mode(),
        ], $data);
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    public function update_by_merchant_tx(string $merchant_tx, array $data): void
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($wpdb->prefix . 'df_transactions', $data, ['merchant_transaction_id' => $merchant_tx]);
    }
}
