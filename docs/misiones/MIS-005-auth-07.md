# MIS-005 — AUTH-07 audit HMAC

| Campo | Contenido |
|---|---|
| Misión | MIS-005 · AUTH-07 cadena HMAC |
| Modo | paquete |
| REQ | REQ-005 |
| Hecho | log/verifyIntegrity reales; fail-fast clave; wiring login/refresh; 19 tests |
| Abierto | AUTH-04 passkeys; AUTH-05 devices; AUTH-06 email; AUTH-08 legal; AUTH-10 RBAC |
| Verificación | `composer test` · OK (19 tests, 90 assertions) · línea base MIS-004: 13/73 |
| Archivos | AuditLog, migración, AuthService HMAC, Enums, Contract, tests Auth07* |
| Puertas | auth · «vamos» |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | la primitiva que pida el dueño |

## Transiciones

- 2026-09-21 · abierta / cerrada · autorización explícita
