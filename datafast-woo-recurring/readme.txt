=== Datafast Woo Recurring ===
Conector Datafast Dataweb para WooCommerce con checkout widget, Fase 2, tokenización y cobros recurrentes.

== Instalación ==
1. Copiar carpeta a wp-content/plugins/datafast-woo-recurring.
2. Activar plugin.
3. Configurar WooCommerce > Settings > Payments > Datafast.

== Variables requeridas ==
- base_url_checkout_test/prod
- entity_id_test/prod
- bearer_token_test/prod
- MID/TID/ECI/PSERV
- credenciales recurrentes separadas

== Notas de migración ==
- Mantiene gateway id pg_woocommerce.
- Lee settings legacy DATAFAST_* como fallback.
- Conserva hook de refund.

== Checklist manual ==
- Pago inicial exitoso y fallido.
- Verificación por resourcePath.
- Guardado de registrationId.
- Cobro recurrente vía cron y WP-CLI.
- Retry y estado past_due.
