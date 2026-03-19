<?php
namespace DFWR;

use WC_Order;

class Order_Mapper
{
    public function build_checkout_payload(WC_Order $order, string $merchant_transaction_id, bool $create_registration = false): array
    {
        $config = Environment::checkout_config();
        $customer_map = new Customer_Data();
        $tax = (new Tax_Mapper())->map($order);

        $payload = [
            'entityId' => $config['entity_id'],
            'amount' => number_format((float) $order->get_total(), 2, '.', ''),
            'currency' => 'USD',
            'paymentType' => 'DB',
            'merchantTransactionId' => $merchant_transaction_id,
            'customParameters[SHOPPER_MID]' => $config['mid'],
            'customParameters[SHOPPER_TID]' => $config['tid'],
            'customParameters[SHOPPER_ECI]' => $config['eci'],
            'customParameters[SHOPPER_PSERV]' => $config['pserv'],
            'customParameters[SHOPPER_VERSIONDF]' => $config['versiondf'],
            'risk.parameters[USER_DATA2]' => $config['risk_user_data2'],
        ];

        if ($create_registration) {
            $payload['createRegistration'] = 'true';
        }

        foreach ($customer_map->from_order($order) as $key => $value) {
            $payload[$key] = sanitize_text_field((string) $value);
        }

        foreach ($tax as $key => $value) {
            $payload['customParameters[' . $key . ']'] = $value;
        }

        $i = 0;
        foreach ($order->get_items() as $item) {
            $name = sanitize_text_field(substr((string) $item->get_name(), 0, 127));
            $payload["cart.items[{$i}].name"] = $name;
            $payload["cart.items[{$i}].description"] = $name;
            $payload["cart.items[{$i}].price"] = number_format((float) $item->get_total(), 2, '.', '');
            $payload["cart.items[{$i}].quantity"] = (string) max(1, (int) $item->get_quantity());
            $i++;
        }

        if ($config['mode'] === 'test') {
            $payload['testMode'] = 'EXTERNAL';
        }

        $errors = $customer_map->validate_required($payload);
        if ($errors) {
            throw new \RuntimeException(implode('; ', $errors));
        }

        return $payload;
    }
}
