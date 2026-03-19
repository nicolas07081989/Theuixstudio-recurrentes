# Migration Notes

## Settings legacy mapeados/importados
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
- DATAFAST_STYLE -> style
- DATAFAST_REQUIRECVV -> require_cvv
- DATAFAST_PREFIJOTRX -> prefijo_trx

## Tablas
- Se crean tablas nuevas `wp_df_*` con dbDelta.
- Si existen tablas legacy `{$wpdb->base_prefix}datafast_transactions` y `{$wpdb->base_prefix}datafast_customertoken`, se migra contenido de forma automática al activar.

## Compatibilidad
- Se preserva id del gateway `pg_woocommerce`.
- Se conserva hook `woocommerce_order_refunded`.
- Se mantiene flujo `process_payment -> receipt -> return -> verify`.
- Se soporta retorno legacy `paymentDatafast=confirm`.
- Se exponen métodos compat: `generate_datafast_form`, `processPayment`, `buildInitialBody`.

## Cambios que pueden impactar
- Endpoint delete card ahora exige nonce `wp_rest` y ownership del token.
- Se usan tablas nuevas para operaciones recientes; histórico legacy queda importado.
