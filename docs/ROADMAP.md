# Roadmap — alma/auth (v2)

Condiciones pre-firmadas para abrir misiones futuras. Lo que no está aquí
**no** se asume autorizado.

## Hecho (cerrado en `main`)

| Código | Capacidad | Misión |
|---|---|---|
| AUTH-01 | Login + 2FA TOTP | MIS-002 |
| AUTH-02 | Refresh (familia + rotación) | MIS-003 |
| AUTH-03 | Lockout IP\|cuenta | MIS-004 |
| AUTH-04 | Passkeys (WebAuthn) | MIS-007 |
| AUTH-05 | Trusted devices | MIS-008 |
| AUTH-06 | Step-up + cambio de email | MIS-006 |
| AUTH-07 | Audit HMAC | MIS-005 |
| AUTH-08 | Consentimiento legal (hooks) | MIS-009 |
| AUTH-09 | Este roadmap | MIS-010 |
| AUTH-10 | RBAC + matriz de revocación | MIS-011 |

## Siguiente acordado

Ninguno pendiente del catálogo AUTH-01…10. Lo nuevo entra por REQ propio.

## Diferido (requiere REQ nuevo)

- **OAuth social** (Google/Apple/etc.): no hay proveedor en v2; AUTH-01 quedó en email/password + TOTP.
- **Notificaciones** (email/push al marcar dispositivo o login nuevo): el host observa audit.
- **Paquete `alma/auth-legal-cl`**: textos Ley 21.719 viven en el host; AUTH-08 solo persiste consentimiento.
- **WebAuthn MDS / attestation enterprise**: solo `none` attestation.
- **PHPStan en `lint:`**: se añade cuando la superficie analizable lo justifique.
- **Consumidor de graduación**: app host que certifique el paquete en producción.

## No-objetivos

- Frontend / Blade / Livewire propios del paquete.
- Sustituir Sanctum por Passport/JWT propio.
- Multi-tenant built-in (el host modela tenants).

## Condiciones para una misión nueva

1. Un solo objetivo verificable y un REQ.
2. Modo `paquete` en `.alma/modo-mision`.
3. Si toca superficie de autorización, seguridad o dependencia: puerta explícita.
4. Tests que ejerciten `src/` (no stdlib sola).
