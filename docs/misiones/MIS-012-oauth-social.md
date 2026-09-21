# MIS-012 — OAuth social

| Campo | Contenido |
|---|---|
| Misión | MIS-012 · OAuth social (vínculo + login) |
| Modo | paquete |
| REQ | REQ-012 |
| Hecho | Verificador inyectable; link con step-up; login solo si vinculado; sin Socialite |
| Abierto | Verificador real en el host; notificaciones; consumidor de graduación |
| Verificación | `composer test` · OK (43 tests, 230 assertions) · línea base MIS-011: 38/206 |
| Archivos | OAuthIdentityVerifier, Fake/Rejecting, OAuthIdentity, AuthService/Controller, tests OAuth* |
| Puertas | auth · «vamos» (sin dep Socialite) |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | adaptador Socialite en host o ítem del roadmap |

## Transiciones

- 2026-09-21 · abierta / cerrada · autorización explícita
