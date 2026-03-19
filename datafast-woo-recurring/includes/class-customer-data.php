<?php
namespace DFWR;

use WC_Order;

class Customer_Data
{
    public function from_order(WC_Order $order): array
    {
        $doc = Utils::normalize_identification((string) $order->get_meta('_billing_identification_doc_id'));
        $middle = substr(trim((string) $order->get_meta('_billing_middle_name')), 0, 50);
        $given = substr(trim((string) $order->get_billing_first_name()), 0, 48);
        $surname = substr(trim((string) $order->get_billing_last_name()), 0, 48);
        $customer_id = $order->get_user_id() ? Utils::merchant_customer_id((int) $order->get_user_id()) : ('G' . str_pad((string) $order->get_id(), 15, '0', STR_PAD_LEFT));
        $customer_id = substr($customer_id, 0, 16);
        $phone = preg_replace('/[^0-9+]/', '', (string) $order->get_billing_phone());

        return [
            'customer.givenName' => $given,
            'customer.middleName' => $middle,
            'customer.surname' => $surname,
            'customer.ip' => Utils::detect_client_ip(),
            'customer.merchantCustomerId' => $customer_id,
            'customer.email' => substr((string) $order->get_billing_email(), 0, 128),
            'customer.identificationDocType' => 'IDCARD',
            'customer.identificationDocId' => $doc,
            'customer.phone' => $phone,
            'billing.street1' => substr((string) $order->get_billing_address_1(), 0, 100),
            'billing.country' => strtoupper((string) $order->get_billing_country()),
            'shipping.street1' => substr((string) ($order->get_shipping_address_1() ?: $order->get_billing_address_1()), 0, 100),
            'shipping.country' => strtoupper((string) ($order->get_shipping_country() ?: $order->get_billing_country())),
        ];
    }

    public function validate_required(array $payload): array
    {
        $errors = [];
        $this->check_len($payload, 'customer.givenName', 3, 48, $errors);
        $this->check_len($payload, 'customer.middleName', 2, 50, $errors);
        $this->check_len($payload, 'customer.surname', 3, 48, $errors);
        if (! filter_var($payload['customer.ip'] ?? '', FILTER_VALIDATE_IP)) {
            $errors[] = 'customer.ip inválida';
        }
        $this->check_len($payload, 'customer.merchantCustomerId', 1, 16, $errors);
        $this->check_len($payload, 'customer.email', 6, 128, $errors);
        if (($payload['customer.identificationDocType'] ?? '') !== 'IDCARD') {
            $errors[] = 'customer.identificationDocType debe ser IDCARD';
        }
        if (strlen((string) ($payload['customer.identificationDocId'] ?? '')) !== 10) {
            $errors[] = 'customer.identificationDocId debe tener 10 caracteres';
        }
        $this->check_len($payload, 'customer.phone', 7, 25, $errors);
        $this->check_len($payload, 'shipping.street1', 1, 100, $errors);
        $this->check_len($payload, 'billing.street1', 1, 100, $errors);
        if (! preg_match('/^[A-Z]{2}$/', (string) ($payload['shipping.country'] ?? ''))) {
            $errors[] = 'shipping.country inválido';
        }
        if (! preg_match('/^[A-Z]{2}$/', (string) ($payload['billing.country'] ?? ''))) {
            $errors[] = 'billing.country inválido';
        }

        return $errors;
    }

    private function check_len(array $payload, string $key, int $min, int $max, array &$errors): void
    {
        $value = trim((string) ($payload[$key] ?? ''));
        $len = strlen($value);
        if ($len < $min || $len > $max) {
            $errors[] = sprintf('%s fuera de longitud [%d,%d]', $key, $min, $max);
        }
    }
}
