# REQ-005 — AUTH-07: cadena de auditoría HMAC

| Campo | Valor |
|---|---|
| **ID** | REQ-005 |
| **Descripción** | Eventos de auth se registran en una cadena append-only firmada con HMAC; `verifyIntegrity()` detecta manipulación y huecos de secuencia. |
| **Módulo** | AUTH-07 |
| **Estado** | cerrado |
| **Misión** | MIS-005 |

## Criterio verificable

1. Con `alma-auth.hmac_key` ≥ 32 bytes, `log()` escribe fila con `seq` contiguo, `prev_hash` y `envelope_hash`.
2. Cadena intacta → `verifyIntegrity()` === `true` (test llama al método real).
3. Alterar `payload` de una fila → `verifyIntegrity()` === `false`.
4. Borrar una fila intermedia (hueco de `seq`) → `verifyIntegrity()` === `false`.
5. Clave ausente o < 32 bytes → `log()` / `verifyIntegrity()` lanzan (fail-fast).
6. Login fallido/exitoso y reuso de refresh emiten eventos de auditoría.
7. `composer test` verde.

## Fuera de alcance

- RBAC / AUTH-10
- Passkeys, trusted devices, legal CL
- UI de consulta de auditoría
