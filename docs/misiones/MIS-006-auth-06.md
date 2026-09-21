# MIS-006 — AUTH-06 step-up + email change

| Campo | Contenido |
|---|---|
| Misión | MIS-006 · AUTH-06 step-up token + email |
| Modo | paquete |
| REQ | REQ-006 |
| Hecho | Step-up en cache por token; 2fa/email protegidos; cambio de email con código hash-only |
| Abierto | AUTH-04 passkeys; AUTH-05 devices; AUTH-08 legal; AUTH-10 RBAC |
| Verificación | `composer test` · OK (23 tests, 114 assertions) · línea base MIS-005: 19/90 |
| Archivos | RequiresRecentAuth, EmailChangeRequest, AuthService/Controller/routes, tests Auth06* |
| Puertas | auth · «vamos» |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | AUTH-10 RBAC o AUTH-05 según prioridad |

## Transiciones

- 2026-09-21 · abierta / cerrada · autorización explícita
