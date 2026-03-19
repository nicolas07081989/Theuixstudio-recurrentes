# Matriz Reutilizado / Rehecho / Nuevo

| Componente legacy | Estado | Decisión | Razón |
|---|---|---|---|
| Gateway `pg_woocommerce` | Existente | Reutilizado | Evita romper configuración histórica |
| Flujo checkoutId/widget/resourcePath | Parcial | Refactorizado | Se mantiene patrón pero con HTTP client y validaciones Fase 2 |
| Tokenización registrationId | Existente | Refactorizado | Se normaliza almacenamiento y ownership |
| REST delete card | Inseguro | Reemplazado (pendiente endpoint dedicado) | Debe tener permisos/nonce/capability |
| Transacciones admin | Básico | Nuevo módulo | Trazabilidad por operación y payload |
| Recurrencias | Inexistente | Nuevo | Cobro automático via `/registrations/{token}/payments` |
| Diferidos/tipo de crédito | Existente legacy | Reutilizable (pendiente portar) | Se debe mantener fuera de flujo recurrente |
