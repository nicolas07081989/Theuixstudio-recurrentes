# Payload Examples (Datafast)

## 1) Checkout Fase 2 - orden simple
```php
[
  'entityId' => '8ac7a4c...TEST',
  'amount' => '25.00',
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
  'cart.items[0].name' => 'Servicio mensual',
  'cart.items[0].description' => 'Servicio mensual',
  'cart.items[0].price' => '25.00',
  'cart.items[0].quantity' => '1',
  'customParameters[SHOPPER_VAL_BASE0]' => '0.00',
  'customParameters[SHOPPER_VAL_BASEIMP]' => '22.32',
  'customParameters[SHOPPER_VAL_IVA]' => '2.68',
  'customParameters[SHOPPER_MID]' => '1000000001',
  'customParameters[SHOPPER_TID]' => '00000001',
  'customParameters[SHOPPER_ECI]' => '0103910',
  'customParameters[SHOPPER_PSERV]' => '17913101',
  'customParameters[SHOPPER_VERSIONDF]' => '2',
  'risk.parameters[USER_DATA2]' => 'COMERCIO WEB',
  'testMode' => 'EXTERNAL',
]
```

## 2) Checkout Fase 2 - multi ítem
Incluye `cart.items[0..n]` y mismos campos base.

## 3) Checkout con impuestos mixtos
`SHOPPER_VAL_BASE0=10.00`, `SHOPPER_VAL_BASEIMP=30.00`, `SHOPPER_VAL_IVA=3.60`.

## 4) Checkout con base 0 solamente
`SHOPPER_VAL_BASE0=50.00`, `SHOPPER_VAL_BASEIMP=0.00`, `SHOPPER_VAL_IVA=0.00`.

## 5) Recurrente con token
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
