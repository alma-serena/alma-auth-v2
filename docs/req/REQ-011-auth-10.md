# REQ-011 — AUTH-10: RBAC canónico + matriz de revocación

| Campo | Valor |
|---|---|
| **ID** | REQ-011 |
| **Descripción** | Roles y permisos se definen en catálogo (código/config) y se asignan por separado. Eventos sensibles revocan sesiones Sanctum y familias de refresh. |
| **Módulo** | AUTH-10 |
| **Estado** | cerrado |
| **Misión** | MIS-011 |

## Criterio verificable

1. Tablas: roles canónicos, permisos por rol, asignaciones usuario↔rol (sin confundir definición con asignación).
2. `syncRbacCatalog()` siembra desde `config('alma-auth.rbac')`; permiso no catalogado → denegado.
3. `assignRole` / `revokeRole` / `userHasPermission` funcionan; emiten `roles.changed`.
4. `revokeAllSessions(user)` invalida refresh del usuario y borra tokens Sanctum; emite `session.revoked`.
5. Call sites: cambio de email exitoso, cambio de password exitoso, y `POST /sessions/revoke` (step-up).
6. `POST /password/change` (step-up) cambia password y revoca sesiones; emite `password.changed`.
7. `composer test` verde.

## Fuera de alcance

- UI de admin de roles
- OAuth / multi-tenant
- Middleware HTTP genérico de autorización (el host llama `userHasPermission`)
