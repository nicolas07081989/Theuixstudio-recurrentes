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
            'customParameters[SHOPPER_MID]' => (string) $config['mid'],
            'customParameters[SHOPPER_TID]' => (string) $config['tid'],
            'customParameters[SHOPPER_ECI]' => '0103910',
            'customParameters[SHOPPER_PSERV]' => '17913101',
            'customParameters[SHOPPER_VERSIONDF]' => '2',
            'risk.parameters[USER_DATA2]' => (string) $config['risk_user_data2'],
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
            $name = str_replace('&', 'y', sanitize_text_field(substr((string) $item->get_name(), 0, 127)));
            $payload["cart.items[{$i}].name"] = $name;
            $payload["cart.items[{$i}].description"] = $name;
            $payload["cart.items[{$i}].price"] = number_format((float) $item->get_total(), 2, '.', '');
            $payload["cart.items[{$i}].quantity"] = (string) max(1, (int) $item->get_quantity());
            $i++;
        }

        if ($config['mode'] === 'test') {
            $payload['testMode'] = 'EXTERNAL';
        }

        if (strlen($merchant_transaction_id) < 8 || strlen($merchant_transaction_id) > 255) {
            throw new \RuntimeException('merchantTransactionId fuera de longitud [8,255]');
        }

        $errors = $customer_map->validate_required($payload);
        foreach (['customParameters[SHOPPER_VAL_BASE0]','customParameters[SHOPPER_VAL_BASEIMP]','customParameters[SHOPPER_VAL_IVA]','customParameters[SHOPPER_MID]','customParameters[SHOPPER_TID]','customParameters[SHOPPER_ECI]','customParameters[SHOPPER_PSERV]','customParameters[SHOPPER_VERSIONDF]','risk.parameters[USER_DATA2]'] as $required) {
            if (! isset($payload[$required]) || $payload[$required] === '') {
                $errors[] = 'Falta campo obligatorio: ' . $required;
            }
        }

        if ($errors) {
            throw new \RuntimeException(implode('; ', $errors));
        }

        Logger::log('Payload Fase2 generado', ['order_id' => $order->get_id(), 'merchantTransactionId' => $merchant_transaction_id, 'payload' => $payload]);
        return $payload;
    }
}
