# Manual Operativo — Instalación y Pruebas Sandbox

## 1) Checklist de instalación en WordPress (paso a paso)

1. Confirmar prerequisitos:
   - WordPress 6.x
   - WooCommerce activo
   - PHP 8.1+
2. Copiar carpeta `datafast-woo-recurring` en `wp-content/plugins/`.
3. Activar plugin en **Plugins > Installed Plugins**.
4. Verificar que no existan fatales en pantalla ni en `wp-content/debug.log`.
5. Verificar creación de tablas:
   - `wp_df_tokens`
   - `wp_df_transactions`
   - `wp_df_subscriptions`
   - `wp_df_subscription_events`
6. Verificar menú admin **Datafast** y submenús:
   - Transacciones
   - Suscripciones
   - Tokens
   - Tipos de Crédito
   - Herramientas
7. Verificar que el gateway Datafast aparezca en:
   - WooCommerce > Settings > Payments

---

## 2) Checklist de configuración sandbox (exacto)

Ir a **WooCommerce > Settings > Payments > Datafast (pg_woocommerce)** y configurar:

### Generales
- Habilitar gateway
- Título y descripción
- Debug mode = ON (en pruebas)
- Locale widget = `es`

### Checkout sandbox
- `base_url_checkout_test` (ej. `https://eu-test.oppwa.com`)
- `entity_id_test`
- `bearer_token_test`
- `shopper_mid_test`
- `shopper_tid_test`
- ECI/PSERV/VERSION (dejar por defecto documental)
- `risk_user_data2`

### Recurrencia sandbox
- `base_url_recurring_test`
- `recurring_entity_id_test`
- `recurring_bearer_token_test`
- `recurring_mid_test`
- `recurring_tid_test`
- `recurring_user_data2_test`

### Cuotas / diferidos
- `installments_param_mode`:
  - recomendado inicial: `legacy_recurring_installments`
  - alterno: `type_only`
  - compat no confirmada: `custom_differido`

### Motor recurrente
- `recurring_interval_minutes`
- `recurring_max_retries`
- `recurring_retry_spacing_hours`

---

## 3) Checklist de prueba E2E (exacto)

### A. Pago normal (sin tokenización)
**Precondición**: producto simple no recurrente.
1. Comprar con Datafast.
2. Validar paso por receipt/widget.
3. Confirmar retorno `paymentDatafast=confirm`.
4. Confirmar orden pagada/on-hold/failed según resultado.

**Esperado**:
- fila en `wp_df_transactions` (`operation_type=checkout/verify`)
- meta de orden `_dfwr_payment_id`, `_dfwr_result_code`, `_dfwr_resource_path`

### B. Pago con tokenización
**Precondición**: usuario logueado.
1. Repetir checkout marcando "Guardar tarjeta".
2. Confirmar aprobación.

**Esperado**:
- `registrationId` guardado en `wp_df_tokens`
- meta `_dfwr_registration_id` en orden

### C. Guardado/uso de token
1. Ir a Mi Cuenta > Tarjetas guardadas.
2. Ver token en listado.
3. Nueva compra: seleccionar tarjeta guardada.

**Esperado**:
- payload checkout con `registrations[0].id`

### D. Creación de suscripción
**Precondición**: producto con `_dfwr_is_recurring=yes`.
1. Comprar producto recurrente con token disponible.
2. Al pasar la orden a processing/completed, validar creación en `wp_df_subscriptions`.

**Esperado**:
- fila en `wp_df_subscriptions` con `status=active` y `next_payment_at`

### E. Cobro recurrente manual (WP-CLI)
1. Ejecutar: `wp datafast recurring run`
2. Revisar transacciones nuevas recurrentes.

**Esperado**:
- fila `operation_type=recurring` en `wp_df_transactions`
- si success: renewal order creada y pagada

### F. Retry / past_due
1. Forzar rechazo (credencial/token/monto de prueba de error).
2. Ejecutar CLI nuevamente.

**Esperado**:
- incremento `retry_count`
- `next_payment_at` reprogramado
- al superar máximo: `status=past_due`

---

## 4) Qué revisar (tablas, metas, logs) en cada prueba

### Tablas
- `wp_df_transactions`
  - `merchant_transaction_id`, `operation_type`, `status`, `result_code`, `request_payload`, `response_payload`
- `wp_df_tokens`
  - `wp_user_id`, `merchant_customer_id`, `registration_id`, `is_active`
- `wp_df_subscriptions`
  - `status`, `next_payment_at`, `retry_count`, `max_retries`, `last_error`
- `wp_df_subscription_events`
  - eventos `created`, `recurring_success`, `recurring_failed`, `canceled`

### Metas de orden WooCommerce
- `_dfwr_merchant_transaction_id`
- `_dfwr_checkout_id`
- `_dfwr_payment_id`
- `_dfwr_result_code`
- `_dfwr_result_description`
- `_dfwr_resource_path`
- `_dfwr_registration_id`
- `_dfwr_termtype`
- `_dfwr_installments`
- `_dfwr_response_termtype`
- `_dfwr_response_installments`

### Logs
- WooCommerce logger source: `datafast-woo-recurring`
- eventos esperados:
  - `Payload Fase2 generado`
  - `Tax mapping calculado`
  - `Checkout request/response`
  - `Recurring request/response`

---

## 5) Señales de error frecuentes y diagnóstico

1. **No aparece checkoutId**
   - Causa: credenciales/URL/entity incorrectos
   - Revisar `Checkout response` en logs y `result.code`

2. **Retorno sin confirmar pago**
   - Causa: no llega `resourcePath` o `paymentDatafast=confirm`
   - Revisar URL de retorno y nota de orden

3. **No guarda token**
   - Causa: respuesta sin `registrationId` o usuario no autenticado
   - Revisar `response_payload` en `wp_df_transactions`

4. **No crea suscripción**
   - Causa: producto no marcado recurrente o orden sin `_dfwr_registration_id`
   - Revisar meta de producto y orden

5. **Cron/CLI no cobra**
   - Causa: no hay suscripciones vencidas o lock activo
   - Revisar `next_payment_at`, lock transient `dfwr_recurring_lock`

6. **Pasa a past_due demasiado rápido**
   - Causa: `recurring_max_retries` bajo
   - Revisar settings de retries y spacing

7. **Error al eliminar token**
   - Causa: nonce inválido o token de otro usuario
   - Revisar sesión, nonce `wp_rest` y ownership

---

## 6) Confirmación final requerida con Datafast antes de producción

Antes de pasar a producción, confirmar por escrito con Datafast:

1. Si para diferidos en checkout el canal espera:
   - `recurring.numberOfInstallments` (modo legacy recomendado), o
   - un parámetro alterno como `customParameters[SHOPPER_DIFERIDO]`.
2. Catálogo válido de `SHOPPER_TIPOCREDITO` para el comercio/canal.
3. Combinaciones válidas entre tipo de crédito y número de cuotas.
4. Credenciales definitivas separadas de checkout y recurrencia.
5. Reglas finales de anulación/reverso para operaciones recurrentes.
