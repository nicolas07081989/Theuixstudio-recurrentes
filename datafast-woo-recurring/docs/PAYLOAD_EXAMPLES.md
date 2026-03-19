# Payload Examples (final)

## Pago corriente (checkout normal)
```php
[
  'entityId' => '8ac7a4c...TEST',
  'amount' => '30.00',
  'currency' => 'USD',
  'paymentType' => 'DB',
  'merchantTransactionId' => 'DF-1201-20260319183000-AB12CD34EF56',
  'customer.givenName' => 'Carlos',
  'customer.middleName' => 'Andres',
  'customer.surname' => 'Lopez',
  'customer.ip' => '181.39.10.44',
  'customer.merchantCustomerId' => 'DFCUST123',
  'customer.email' => 'carlos@example.com',
  'customer.identificationDocType' => 'IDCARD',
  'customer.identificationDocId' => '0123456789',
  'customer.phone' => '0999988877',
  'billing.street1' => 'Av. Primera 123',
  'billing.country' => 'EC',
  'shipping.street1' => 'Av. Primera 123',
  'shipping.country' => 'EC',
  'cart.items[0].name' => 'Plan corriente',
  'cart.items[0].description' => 'Plan corriente',
  'cart.items[0].price' => '30.00',
  'cart.items[0].quantity' => '1',
  'customParameters[SHOPPER_VAL_BASE0]' => '0.00',
  'customParameters[SHOPPER_VAL_BASEIMP]' => '26.79',
  'customParameters[SHOPPER_VAL_IVA]' => '3.21',
  'customParameters[SHOPPER_MID]' => '1000000001',
  'customParameters[SHOPPER_TID]' => '00000001',
  'customParameters[SHOPPER_ECI]' => '0103910',
  'customParameters[SHOPPER_PSERV]' => '17913101',
  'customParameters[SHOPPER_VERSIONDF]' => '2',
  'risk.parameters[USER_DATA2]' => 'COMERCIO WEB',
  'testMode' => 'EXTERNAL',
]
```

## Pago diferido 3 cuotas (modo recomendado legacy)
```php
[
  // ... mismos campos base ...
  'customParameters[SHOPPER_TIPOCREDITO]' => '22',
  'recurring.numberOfInstallments' => '3',
]
```

## Pago con tipo de crédito 22 (sin cuotas explícitas)
```php
[
  // ... mismos campos base ...
  'customParameters[SHOPPER_TIPOCREDITO]' => '22',
]
```

## Pago recurrente con token
```php
[
  'entityId' => '8ac7a4c...REC',
  'amount' => '19.99',
  'currency' => 'USD',
  'paymentType' => 'DB',
  'recurringType' => 'REPEATED',
  'merchantTransactionId' => 'DFR-1201-20260320100000-QWERTY123456',
  'risk.parameters[USER_DATA1]' => 'REPEATED',
  'risk.parameters[USER_DATA2]' => 'CANAL REC',
  'customParameters[SHOPPER_MID]' => '1000000001',
  'customParameters[SHOPPER_TID]' => '00000001',
  'customParameters[SHOPPER_ECI]' => '0103910',
  'customParameters[SHOPPER_PSERV]' => '17913101',
  'customParameters[SHOPPER_VERSIONDF]' => '2',
  'customParameters[SHOPPER_VAL_BASE0]' => '0.00',
  'customParameters[SHOPPER_VAL_BASEIMP]' => '17.85',
  'customParameters[SHOPPER_VAL_IVA]' => '2.14',
  'testMode' => 'EXTERNAL',
]
```
