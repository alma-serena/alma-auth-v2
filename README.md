# alma/auth (v2) — renacimiento bajo ALMA

Paquete Composer headless de autenticación para hosts Laravel.

| Pieza | Estado |
|---|---|
| Estándar ALMA (`v0.1.5`) | instalado |
| AUTH-01 login + 2FA TOTP | MIS-002 / REQ-002 |
| AUTH-02 refresh (familia + rotación) | MIS-003 / REQ-003 |
| AUTH-03 lockout IP\|cuenta | MIS-004 / REQ-004 |
| AUTH-07 audit HMAC | MIS-005 / REQ-005 |
| AUTH-06 step-up + email change | MIS-006 / REQ-006 |
| AUTH-04 passkeys (WebAuthn) | MIS-007 / REQ-007 |
| AUTH-05 trusted devices | MIS-008 / REQ-008 |
| AUTH-08 consentimiento legal | MIS-009 / REQ-009 |
| AUTH-09 roadmap | MIS-010 / REQ-010 |
| AUTH-10 RBAC + revocación | pendiente |

Roadmap detallado: [docs/ROADMAP.md](docs/ROADMAP.md).
| Consumidor de graduación | pendiente |

## Requisitos del host

1. Modelo de usuario que implemente `Alma\Auth\Contracts\AuthenticatableUser` y use `Laravel\Sanctum\HasApiTokens`.
2. Configurar `ALMA_AUTH_USER_MODEL` (o `config/alma-auth.php`).
3. Columnas `two_factor_secret` (text nullable) y `two_factor_enabled` (bool) en usuarios.
5. Correr migraciones del paquete (`alma_auth_refresh_tokens`, `alma_auth_audit`, `alma_auth_email_changes`, `alma_auth_passkeys`, `alma_auth_trusted_devices`).
6. Publicar config: `php artisan vendor:publish --tag=alma-auth-config`
7. Definir `ALMA_AUTH_HMAC_KEY` (≥ 32 bytes) en el entorno del host.
8. Para passkeys: `ALMA_AUTH_PASSKEY_RP_ID`, `ALMA_AUTH_PASSKEY_ORIGINS` (orígenes con esquema, separados por coma).
9. Trusted devices: `ALMA_AUTH_TRUSTED_DEVICE_TTL_DAYS` (default 90).

## Rutas (`api/alma-auth`)

| Método | Ruta | Auth |
|---|---|---|
| POST | `/login` | — (throttle 5/min) |
| POST | `/refresh` | — (body: `refresh_token`) |
| POST | `/passkeys/login/options` | — (throttle 5/min) |
| POST | `/passkeys/login` | — (throttle 5/min) |
| POST | `/2fa/verify` | Sanctum ability `2fa:verify` |
| POST | `/step-up` | Sanctum ability `*` |
| GET | `/passkeys` | `*` |
| GET | `/devices` | `*` |
| GET | `/legal/consents` | `*` |
| POST | `/legal/consent` | `*` |
| POST | `/2fa/enroll` | `*` + step-up reciente |
| POST | `/2fa/confirm` | `*` + step-up reciente |
| POST | `/email/change` | `*` + step-up reciente |
| POST | `/email/confirm` | `*` + step-up reciente |
| POST | `/passkeys/register/options` | `*` + step-up reciente |
| POST | `/passkeys/register` | `*` + step-up reciente |
| DELETE | `/passkeys/{id}` | `*` + step-up reciente |
| DELETE | `/devices/{id}` | `*` + step-up reciente |

Login, `2fa/verify` y `passkeys/login` exitosos devuelven `token` + `refresh_token`. El login con passkey es sesión plena (no exige TOTP adicional). Con `trust_device=true` en `2fa/verify`, el fingerprint omite TOTP en logins siguientes hasta expirar o revocar.

## Comandos

```
composer install --no-interaction
composer test
composer style
```
