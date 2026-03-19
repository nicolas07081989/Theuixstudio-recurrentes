<?php
namespace DFWR;

class Settings
{
    public static function init(): void
    {
        add_filter('woocommerce_get_settings_checkout', [__CLASS__, 'inject_settings'], 20, 2);
        add_action('admin_init', [__CLASS__, 'maybe_import_legacy_settings']);
    }

    public static function maybe_import_legacy_settings(): void
    {
        if (get_option('dfwr_legacy_settings_imported') === 'yes') {
            return;
        }
        $settings = get_option('woocommerce_pg_woocommerce_settings', []);

        $map = [
            'title' => 'DATAFAST_TITLE',
            'description' => 'DATAFAST_DESCRIPTION',
            'language' => 'checkout_language',
            'enabled_tokenization' => 'DATAFAST_CUSTOMERTOKEN',
            'environment' => 'DATAFAST_DEV',
            'base_url_checkout_test' => 'DATAFAST_URL_TEST',
            'base_url_checkout_prod' => 'DATAFAST_URL_PROD',
            'entity_id_test' => 'DATAFAST_ENTITY_ID',
            'bearer_token_test' => 'DATAFAST_BEARER_TOKEN',
            'shopper_mid_test' => 'DATAFAST_MID',
            'shopper_tid_test' => 'DATAFAST_TID',
            'risk_user_data2' => 'DATAFAST_RISK',
            'style' => 'DATAFAST_STYLE',
            'require_cvv' => 'DATAFAST_REQUIRECVV',
            'prefijo_trx' => 'DATAFAST_PREFIJOTRX',
        ];

        foreach ($map as $new_key => $legacy_option) {
            if (! isset($settings[$new_key])) {
                $legacy = get_option($legacy_option, null);
                if ($legacy !== null && $legacy !== '') {
                    $settings[$new_key] = $legacy;
                }
            }
        }

        update_option('woocommerce_pg_woocommerce_settings', $settings);
        update_option('dfwr_legacy_settings_imported', 'yes');
    }

    public static function get(string $key, $default = '')
    {
        $settings = get_option('woocommerce_pg_woocommerce_settings', []);
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        $legacy_map = [
            'title' => 'DATAFAST_TITLE',
            'description' => 'DATAFAST_DESCRIPTION',
            'language' => 'checkout_language',
            'enabled_tokenization' => 'DATAFAST_CUSTOMERTOKEN',
            'environment' => 'DATAFAST_DEV',
            'base_url_checkout_test' => 'DATAFAST_URL_TEST',
            'base_url_checkout_prod' => 'DATAFAST_URL_PROD',
            'entity_id_test' => 'DATAFAST_ENTITY_ID',
            'bearer_token_test' => 'DATAFAST_BEARER_TOKEN',
            'shopper_mid_test' => 'DATAFAST_MID',
            'shopper_tid_test' => 'DATAFAST_TID',
            'risk_user_data2' => 'DATAFAST_RISK',
        ];

        if (isset($legacy_map[$key])) {
            $legacy = get_option($legacy_map[$key], null);
            return $legacy ?? $default;
        }

        return $default;
    }

    public static function inject_settings(array $settings, string $current_section): array
    {
        if ($current_section !== 'pg_woocommerce') {
            return $settings;
        }

        $settings[] = ['title' => __('Datafast Advanced Settings', 'datafast-woo-recurring'), 'type' => 'title', 'id' => 'dfwr_title'];
        $settings[] = ['title' => __('Debug mode', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_debug', 'type' => 'checkbox'];
        $settings[] = ['title' => __('Retención de logs (días)', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_log_retention_days', 'type' => 'number', 'default' => 30];
        $settings[] = ['title' => __('Canal checkout USER_DATA2', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_risk_user_data2', 'type' => 'text'];
        $settings[] = ['title' => __('Activar tokenización', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_enabled_tokenization', 'type' => 'checkbox', 'default' => 'yes'];
        $settings[] = ['title' => __('Activar recurrencias', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_enable_recurring', 'type' => 'checkbox', 'default' => 'yes'];
        $settings[] = ['title' => __('Requerir cuenta para recurrentes', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_require_account_recurring', 'type' => 'checkbox', 'default' => 'yes'];
        $settings[] = ['title' => __('Base URL recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_base_url_recurring_test', 'type' => 'text'];
        $settings[] = ['title' => __('Entity recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_entity_id_test', 'type' => 'text'];
        $settings[] = ['title' => __('Bearer recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_bearer_token_test', 'type' => 'password'];
        $settings[] = ['title' => __('MID recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_mid_test', 'type' => 'text'];
        $settings[] = ['title' => __('TID recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_tid_test', 'type' => 'text'];
        $settings[] = ['title' => __('ECI recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_eci_test', 'type' => 'text', 'default' => '0103910'];
        $settings[] = ['title' => __('PSERV recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_pserv_test', 'type' => 'text', 'default' => '17913101'];
        $settings[] = ['title' => __('USER_DATA2 recurrente test', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_user_data2_test', 'type' => 'text'];
        $settings[] = ['title' => __('Base URL recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_base_url_recurring_prod', 'type' => 'text'];
        $settings[] = ['title' => __('Entity recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_entity_id_prod', 'type' => 'text'];
        $settings[] = ['title' => __('Bearer recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_bearer_token_prod', 'type' => 'password'];
        $settings[] = ['title' => __('MID recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_mid_prod', 'type' => 'text'];
        $settings[] = ['title' => __('TID recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_tid_prod', 'type' => 'text'];
        $settings[] = ['title' => __('ECI recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_eci_prod', 'type' => 'text', 'default' => '0103910'];
        $settings[] = ['title' => __('PSERV recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_pserv_prod', 'type' => 'text', 'default' => '17913101'];
        $settings[] = ['title' => __('USER_DATA2 recurrente prod', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_user_data2_prod', 'type' => 'text'];
        $settings[] = ['title' => __('Cron interval (minutos)', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_interval_minutes', 'type' => 'number', 'default' => 60];
        $settings[] = ['title' => __('Max retries', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_max_retries', 'type' => 'number', 'default' => 3];
        $settings[] = ['title' => __('Retry spacing (horas)', 'datafast-woo-recurring'), 'id' => 'woocommerce_pg_woocommerce_recurring_retry_spacing_hours', 'type' => 'number', 'default' => 24];
        $settings[] = ['type' => 'sectionend', 'id' => 'dfwr_title'];

        return $settings;
    }
}
