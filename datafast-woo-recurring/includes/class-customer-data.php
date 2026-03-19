<?php
namespace DFWR;

use WC_Order;

class Customer_Data
{
    public function from_order(WC_Order $order): array
    {
        $doc = Utils::normalize_identification((string) $order->get_meta('_billing_identification_doc_id'));
        $middle = (string) $order->get_meta('_billing_middle_name');
        $customer_id = $order->get_user_id() ? Utils::merchant_customer_id((int) $order->get_user_id()) : 'GUEST-' . $order->get_id();

        return [
            'customer.givenName' => $order->get_billing_first_name(),
            'customer.middleName' => $middle,
            'customer.surname' => $order->get_billing_last_name(),
            'customer.ip' => Utils::detect_client_ip(),
            'customer.merchantCustomerId' => $customer_id,
            'customer.email' => $order->get_billing_email(),
            'customer.identificationDocType' => 'IDCARD',
            'customer.identificationDocId' => $doc,
            'customer.phone' => preg_replace('/\s+/', '', (string) $order->get_billing_phone()),
            'billing.street1' => $order->get_billing_address_1(),
            'billing.country' => strtoupper((string) $order->get_billing_country()),
            'shipping.street1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            'shipping.country' => strtoupper((string) ($order->get_shipping_country() ?: $order->get_billing_country())),
        ];
    }

    public function validate_required(array $payload): array
    {
        $required = [
            'customer.givenName', 'customer.surname', 'customer.ip', 'customer.merchantCustomerId',
            'customer.email', 'customer.identificationDocId', 'customer.phone',
            'billing.street1', 'billing.country', 'shipping.street1', 'shipping.country',
        ];
        $errors = [];
        foreach ($required as $key) {
            if (empty($payload[$key])) {
                $errors[] = sprintf('Falta campo obligatorio: %s', $key);
            }
        }
        return $errors;
    }
}
