# Migration Notes

## Settings legacy mapeados
- DATAFAST_TITLE -> title
- DATAFAST_DESCRIPTION -> description
- checkout_language -> language
- DATAFAST_CUSTOMERTOKEN -> enabled_tokenization
- DATAFAST_DEV -> environment
- DATAFAST_URL_TEST/PROD -> base_url_checkout_test/prod
- DATAFAST_ENTITY_ID -> entity_id_test (fallback)
- DATAFAST_BEARER_TOKEN -> bearer_token_test (fallback)
- DATAFAST_MID/TID -> shopper_mid_test/shopper_tid_test
- DATAFAST_RISK -> risk_user_data2

## Tablas
- Se crean nuevas tablas `wp_df_*` con dbDelta.
- Históricos legacy `datafast_transactions` y `datafast_customertoken` deben migrarse con script SQL controlado (pendiente operativo por instalación).

## Compatibilidad
- Se preserva id del gateway `pg_woocommerce`.
- Se conserva hook `woocommerce_order_refunded`.
- Se mantiene flujo `process_payment -> receipt -> return -> verify`.
