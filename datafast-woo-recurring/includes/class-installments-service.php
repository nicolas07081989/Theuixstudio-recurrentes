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

    public function append_payload(array $payload, string $term_type, string $months): array
    {
        $mode = (string) Settings::get('installments_param_mode', 'legacy_recurring_installments');

        if ($term_type !== '') {
            $payload['customParameters[SHOPPER_TIPOCREDITO]'] = $term_type;
        }

        if ($months === '') {
            return $payload;
        }

        if ($mode === 'legacy_recurring_installments') {
            $payload['recurring.numberOfInstallments'] = $months;
        } elseif ($mode === 'custom_differido') {
            $payload['customParameters[SHOPPER_DIFERIDO]'] = $months;
        }

        return $payload;
    }

    public function mode_help(): array
    {
        return [
            'legacy_recurring_installments' => 'Envía recurring.numberOfInstallments (alineado a plugin legacy).',
            'type_only' => 'Solo envía SHOPPER_TIPOCREDITO sin cuotas.',
            'custom_differido' => 'Envía SHOPPER_DIFERIDO por compatibilidad no confirmada.',
        ];
    }
}
