# alma/auth (v2) — renacimiento bajo ALMA

Paquete Composer headless de autenticación para hosts Laravel.

| Pieza | Estado |
|---|---|
| Estándar ALMA (`v0.1.5`) | instalado |
| AUTH-01 login + 2FA TOTP | MIS-002 / REQ-002 |
| AUTH-02 refresh (familia + rotación) | MIS-003 / REQ-003 |
| AUTH-03 lockout IP\|cuenta | MIS-004 / REQ-004 |
| AUTH-07 audit HMAC | MIS-005 / REQ-005 |
| AUTH-04…06, 08…10 | pendientes |
| Consumidor de graduación | pendiente |

## Requisitos del host

1. Modelo de usuario que implemente `Alma\Auth\Contracts\AuthenticatableUser` y use `Laravel\Sanctum\HasApiTokens`.
2. Configurar `ALMA_AUTH_USER_MODEL` (o `config/alma-auth.php`).
3. Columnas `two_factor_secret` (text nullable) y `two_factor_enabled` (bool) en usuarios.
5. Correr migraciones del paquete (`alma_auth_refresh_tokens`, `alma_auth_audit`).
6. Publicar config: `php artisan vendor:publish --tag=alma-auth-config`
7. Definir `ALMA_AUTH_HMAC_KEY` (≥ 32 bytes) en el entorno del host.

## Rutas (`api/alma-auth`)

| Método | Ruta | Auth |
|---|---|---|
| POST | `/login` | — (throttle 5/min) |
| POST | `/refresh` | — (body: `refresh_token`) |
| POST | `/2fa/verify` | Sanctum ability `2fa:verify` |
| POST | `/2fa/enroll` | Sanctum ability `*` |
| POST | `/2fa/confirm` | Sanctum ability `*` |

Login y `2fa/verify` exitosos devuelven `token` + `refresh_token`.

## Comandos

```
composer install --no-interaction
composer test
composer style
```
