# MIS-008 — AUTH-05 trusted devices

| Campo | Contenido |
|---|---|
| Misión | MIS-008 · AUTH-05 trusted devices |
| Modo | paquete |
| REQ | REQ-008 |
| Hecho | Marca tras 2FA; omite TOTP con fingerprint activo; list/revoke; audit |
| Abierto | AUTH-08 legal; AUTH-10 RBAC |
| Verificación | `composer test` · OK (32 tests, 171 assertions) · línea base MIS-007: 27/140 |
| Archivos | TrustedDevice, AuthService/Controller/routes, tests Auth05* |
| Puertas | auth · «vamos» |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | AUTH-10 RBAC o AUTH-08 según prioridad |

## Transiciones

- 2026-09-21 · abierta / cerrada · autorización explícita
