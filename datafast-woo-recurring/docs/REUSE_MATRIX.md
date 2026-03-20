# Matriz Reutilizado / Rehecho / Nuevo

| Componente legacy | Estado actual | Decisión | Razón |
|---|---|---|---|
| Gateway `pg_woocommerce` | Implementado | Reutilizado | Mantener compatibilidad de configuración |
| `receipt_page()` | Implementado | Rehecho compatible | Conserva flujo checkout + widget y agrega validaciones |
| `generate_datafast_form()` | Implementado | Reutilizado/compat | Alias para compatibilidad legacy |
| `processPayment($resourcePath)` | Implementado | Reutilizado/compat | Verificación backend encapsulada |
| `buildInitialBody()` | Implementado | Rehecho compatible | Delega en mapper Fase 2 |
| `datafast_transactions` | Migración implementada | Rehecho con migración | Se migra hacia `wp_df_transactions` |
| `datafast_customertoken` | Migración implementada | Rehecho con migración | Se migra hacia `wp_df_tokens` |
| `registrations[n].id` | Implementado | Reutilizado | Soporte para tarjeta guardada |
| `createRegistration` | Implementado | Reutilizado | Checkbox en checkout y captura de `registrationId` |
| deleteCard REST | Implementado seguro | Reemplazado | REST con nonce + ownership |
| refund hook | Implementado | Reutilizado | Se conserva nota/manual flow |
| return `paymentDatafast=confirm` | Implementado | Reutilizado | Compatibilidad del handler de retorno |
| Diferidos / tipo de crédito | No migrado aún | Falta | Requiere portar tablas/UX legacy específicas |
