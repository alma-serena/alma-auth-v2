# alma/auth (v2) — renacimiento bajo ALMA

Paquete Composer headless de autenticación para hosts Laravel.

| Pieza | Estado |
|---|---|
| Estándar ALMA (`v0.1.5`) | instalado |
| AUTH-01 login + 2FA TOTP | MIS-002 / REQ-002 |
| AUTH-02 refresh (familia + rotación) | MIS-003 / REQ-003 |
| AUTH-03…10 | pendientes |
| Consumidor de graduación | pendiente |

## Requisitos del host

1. Modelo de usuario que implemente `Alma\Auth\Contracts\AuthenticatableUser` y use `Laravel\Sanctum\HasApiTokens`.
2. Configurar `ALMA_AUTH_USER_MODEL` (o `config/alma-auth.php`).
3. Columnas `two_factor_secret` (text nullable) y `two_factor_enabled` (bool) en usuarios.
4. Correr migraciones del paquete (`alma_auth_refresh_tokens`).
5. Publicar config: `php artisan vendor:publish --tag=alma-auth-config`

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
