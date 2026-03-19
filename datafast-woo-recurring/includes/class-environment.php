<?php
namespace DFWR;

class Environment
{
    public static function mode(): string
    {
        return Settings::get('environment', 'yes') === 'yes' ? 'test' : 'prod';
    }

    public static function checkout_config(): array
    {
        $mode = self::mode();
        return [
            'mode' => $mode,
            'base_url' => Settings::get($mode === 'test' ? 'base_url_checkout_test' : 'base_url_checkout_prod', $mode === 'test' ? 'https://eu-test.oppwa.com' : 'https://eu-prod.oppwa.com'),
            'entity_id' => Settings::get($mode === 'test' ? 'entity_id_test' : 'entity_id_prod'),
            'bearer' => Settings::get($mode === 'test' ? 'bearer_token_test' : 'bearer_token_prod'),
            'mid' => Settings::get($mode === 'test' ? 'shopper_mid_test' : 'shopper_mid_prod'),
            'tid' => Settings::get($mode === 'test' ? 'shopper_tid_test' : 'shopper_tid_prod'),
            'eci' => Settings::get($mode === 'test' ? 'shopper_eci_test' : 'shopper_eci_prod', '0103910'),
            'pserv' => Settings::get($mode === 'test' ? 'shopper_pserv_test' : 'shopper_pserv_prod', '17913101'),
            'versiondf' => Settings::get($mode === 'test' ? 'shopper_versiondf_test' : 'shopper_versiondf_prod', '2'),
            'risk_user_data2' => Settings::get('risk_user_data2', ''),
        ];
    }

    public static function recurring_config(): array
    {
        $mode = self::mode();
        return [
            'mode' => $mode,
            'base_url' => Settings::get($mode === 'test' ? 'base_url_recurring_test' : 'base_url_recurring_prod'),
            'entity_id' => Settings::get($mode === 'test' ? 'recurring_entity_id_test' : 'recurring_entity_id_prod'),
            'bearer' => Settings::get($mode === 'test' ? 'recurring_bearer_token_test' : 'recurring_bearer_token_prod'),
            'mid' => Settings::get($mode === 'test' ? 'recurring_mid_test' : 'recurring_mid_prod'),
            'tid' => Settings::get($mode === 'test' ? 'recurring_tid_test' : 'recurring_tid_prod'),
            'user_data2' => Settings::get($mode === 'test' ? 'recurring_user_data2_test' : 'recurring_user_data2_prod', ''),
        ];
    }
}
