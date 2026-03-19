# Installments / Tipo de Crédito Parameter Matrix

| Parámetro | Guía oficial | Plugin legacy | Plugin nuevo | Estado | Riesgo si mal enviado | Decisión final |
|---|---|---|---|---|---|---|
| `customParameters[SHOPPER_TIPOCREDITO]` | Sí | Sí | Sí | Obligatorio cuando aplica tipo crédito | Rechazo/incorrecta clasificación crédito | Mantener como parámetro principal de tipo de crédito |
| `recurring.numberOfInstallments` | No explícito en guía de checkout general | Sí | Sí (modo recomendado) | Opcional para diferido en checkout | Cuotas no aplicadas / rechazo si canal lo espera | Usar como modo por defecto de cuotas por compatibilidad legacy |
| `customParameters[SHOPPER_DIFERIDO]` | No confirmado | No observado en legacy entregado | Sí (modo opcional) | Dudoso | Incompatibilidad con canal | Mantener solo en modo compat opcional por setting |
| `SHOPPER_interes` / `SHOPPER_gracia` | Eliminados históricamente | No objetivo | No usados | No usar | Rechazo o comportamiento indefinido | No enviar |

## Estrategia final implementada
- Corriente checkout: sin cuotas, sin parámetro de diferido.
- Checkout con tipo crédito: `SHOPPER_TIPOCREDITO`.
- Checkout diferido: `SHOPPER_TIPOCREDITO` + `recurring.numberOfInstallments` (modo default).
- Recurrencia automática token: **sin** parámetros de diferido/tipo crédito adicionales.
