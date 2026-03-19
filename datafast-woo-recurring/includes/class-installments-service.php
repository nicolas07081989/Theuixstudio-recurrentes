<?php
namespace DFWR;

class Installments_Service
{
    public function get_credit_types(): array
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'datafast_termtype';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $code = (string) ($row['termtype'] ?? $row['code'] ?? $row['id']);
            $label = (string) ($row['description'] ?? $row['name'] ?? $code);
            $result[$code] = $label;
        }
        return $result;
    }

    public function get_installments_by_type(string $type): array
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'datafast_installments';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE termtype = %s ORDER BY CAST(months AS UNSIGNED) ASC", $type), ARRAY_A) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $months = (string) ($row['months'] ?? $row['installments'] ?? '0');
            if ($months === '0') {
                continue;
            }
            $result[$months] = $months . ' meses';
        }
        return $result;
    }

    public function append_payload(array $payload, string $term_type, string $months): array
    {
        if ($term_type !== '') {
            $payload['customParameters[SHOPPER_TIPOCREDITO]'] = $term_type;
        }
        if ($months !== '') {
            $payload['customParameters[SHOPPER_DIFERIDO]'] = $months;
        }
        return $payload;
    }
}
