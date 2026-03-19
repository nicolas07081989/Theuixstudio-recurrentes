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

        update_option('dfwr_db_version', DFWR_VERSION);

        if (! wp_next_scheduled(Cron::HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', Cron::HOOK);
        }
    }
}
