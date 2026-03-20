<?php
namespace DFWR;

if (! defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = [];
        $sql[] = "CREATE TABLE {$prefix}df_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NULL,
            merchant_customer_id VARCHAR(64) NOT NULL,
            registration_id VARCHAR(128) NOT NULL,
            brand VARCHAR(32) NULL,
            last4 VARCHAR(4) NULL,
            expiry_month VARCHAR(2) NULL,
            expiry_year VARCHAR(4) NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            environment VARCHAR(10) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY registration_id (registration_id),
            KEY wp_user_id (wp_user_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}df_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NULL,
            subscription_id BIGINT UNSIGNED NULL,
            merchant_transaction_id VARCHAR(64) NOT NULL,
            payment_id VARCHAR(64) NULL,
            checkout_id VARCHAR(64) NULL,
            resource_path VARCHAR(255) NULL,
            registration_id VARCHAR(128) NULL,
            operation_type VARCHAR(32) NOT NULL,
            environment VARCHAR(10) NOT NULL,
            request_payload LONGTEXT NULL,
            response_payload LONGTEXT NULL,
            result_code VARCHAR(64) NULL,
            result_description TEXT NULL,
            status VARCHAR(32) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY merchant_transaction_id (merchant_transaction_id),
            KEY order_id (order_id),
            KEY payment_id (payment_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}df_subscriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            parent_order_id BIGINT UNSIGNED NOT NULL,
            renewal_order_id BIGINT UNSIGNED NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            merchant_customer_id VARCHAR(64) NOT NULL,
            registration_id VARCHAR(128) NOT NULL,
            status VARCHAR(20) NOT NULL,
            billing_interval INT UNSIGNED NOT NULL,
            billing_period VARCHAR(20) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            next_payment_at DATETIME NOT NULL,
            last_payment_at DATETIME NULL,
            retry_count INT UNSIGNED NOT NULL DEFAULT 0,
            max_retries INT UNSIGNED NOT NULL DEFAULT 3,
            last_error TEXT NULL,
            environment VARCHAR(10) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status_next (status, next_payment_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}df_subscription_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            message TEXT NOT NULL,
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY subscription_id (subscription_id)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        self::migrate_legacy_tables();
        update_option('dfwr_db_version', DFWR_VERSION);

        if (! wp_next_scheduled(Cron::HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', Cron::HOOK);
        }
    }

    private static function migrate_legacy_tables(): void
    {
        global $wpdb;
        if (get_option('dfwr_legacy_tables_migrated') === 'yes') {
            return;
        }

        $legacy_token = $wpdb->base_prefix . 'datafast_customertoken';
        $legacy_tx = $wpdb->base_prefix . 'datafast_transactions';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_token)) === $legacy_token) {
            $rows = $wpdb->get_results("SELECT * FROM {$legacy_token}", ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $registration = $row['registrationId'] ?? ($row['registration_id'] ?? '');
                if (! $registration) {
                    continue;
                }
                $wpdb->replace($wpdb->prefix . 'df_tokens', [
                    'wp_user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                    'merchant_customer_id' => $row['merchant_customer_id'] ?? ('DFCUST-' . (int) ($row['user_id'] ?? 0)),
                    'registration_id' => $registration,
                    'brand' => $row['brand'] ?? null,
                    'last4' => $row['last4'] ?? null,
                    'expiry_month' => $row['expiry_month'] ?? null,
                    'expiry_year' => $row['expiry_year'] ?? null,
                    'is_default' => ! empty($row['is_default']) ? 1 : 0,
                    'is_active' => 1,
                    'environment' => Environment::mode(),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            }
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_tx)) === $legacy_tx) {
            $rows = $wpdb->get_results("SELECT * FROM {$legacy_tx} ORDER BY id ASC", ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $merchant_tx = $row['merchant_transactionId'] ?? ($row['merchantTransactionId'] ?? '');
                if (! $merchant_tx) {
                    continue;
                }
                $wpdb->replace($wpdb->prefix . 'df_transactions', [
                    'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : null,
                    'merchant_transaction_id' => $merchant_tx,
                    'payment_id' => $row['transaction_id'] ?? null,
                    'checkout_id' => $row['checkout_id'] ?? null,
                    'resource_path' => $row['resourcePath'] ?? null,
                    'operation_type' => $row['operation_type'] ?? 'legacy_import',
                    'environment' => $row['environment'] ?? Environment::mode(),
                    'request_payload' => $row['request_json'] ?? null,
                    'response_payload' => $row['response_json'] ?? null,
                    'result_code' => $row['result_code'] ?? null,
                    'result_description' => $row['result_description'] ?? null,
                    'status' => $row['status'] ?? 'manual_review',
                    'amount' => isset($row['amount']) ? (float) $row['amount'] : 0,
                    'currency' => $row['currency'] ?? 'USD',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            }
        }

        update_option('dfwr_legacy_tables_migrated', 'yes');
    }
}
