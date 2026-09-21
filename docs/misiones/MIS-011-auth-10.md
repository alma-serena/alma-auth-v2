# MIS-011 — AUTH-10 RBAC + revocación

| Campo | Contenido |
|---|---|
| Misión | MIS-011 · AUTH-10 RBAC canónico + matriz de revocación |
| Modo | paquete |
| REQ | REQ-011 |
| Hecho | Catálogo RBAC sembrado; assign/revoke; revokeAll en email/password/roles/HTTP |
| Abierto | OAuth, notificaciones, consumidor de graduación (ver ROADMAP) |
| Verificación | `composer test` · OK (38 tests, 206 assertions) · línea base MIS-010: 34/185 |
| Archivos | RbacPolicy, Role*, AuthService, routes, tests Auth10* |
| Puertas | auth · «vamos» (AUTH-08·09·10) · superficie de autorización |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | consumidor de graduación o ítem diferido del roadmap |

## Transiciones

- 2026-09-21 · abierta / cerrada · autorización explícita
