# MIS-007 — AUTH-04 passkeys (WebAuthn)

| Campo | Contenido |
|---|---|
| Misión | MIS-007 · AUTH-04 passkeys |
| Modo | paquete |
| REQ | REQ-007 |
| Hecho | Ceremonia inyectable (WebAuthn + Fake); registro con step-up; login passwordless; revocación; audit |
| Abierto | AUTH-05 devices; AUTH-08 legal; AUTH-10 RBAC |
| Verificación | `composer test` · OK (27 tests, 140 assertions) · línea base MIS-006: 23/114 |
| Archivos | PasskeyCeremony, Fake/WebAuthn, Passkey model, AuthService/Controller/routes, tests Auth04* |
| Puertas | auth · «vamos» |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | AUTH-10 RBAC o AUTH-05 según prioridad |

## Transiciones

- 2026-09-21 · abierta / cerrada · autorización explícita
