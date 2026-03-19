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
- MID/TID/ECI/PSERV checkout
- base_url/entity/bearer/MID/TID/ECI/PSERV recurrente
- risk USER_DATA2 checkout y recurrente

== Compatibilidad legacy ==
- Mantiene gateway id pg_woocommerce.
- Lee settings legacy DATAFAST_* como fallback e importa una vez.
- Migra tablas legacy datafast_transactions/datafast_customertoken si existen.
- Mantiene hook de refund y handler paymentDatafast=confirm.

== WP-CLI ==
- wp datafast recurring run

== Checklist manual ==
1. Activar plugin sin fatal errors.
2. Confirmar gateway Datafast visible en WooCommerce > Payments.
3. Crear orden con Datafast, llegar a receipt y comprobar checkoutId.
4. Ver retorno wc-api=dfwr_return con paymentDatafast=confirm y resourcePath.
5. Verificar orden aprobada/pending/failed y notas/meta Datafast.
6. Validar guardado token registrationId en My Account > Tarjetas guardadas.
7. Eliminar token y validar desactivación local.
8. Marcar producto recurrente y completar compra inicial.
9. Ejecutar cron/CLI y validar cobro recurrente + renewal order.
10. Forzar fallo y verificar retry_count + estado past_due.
