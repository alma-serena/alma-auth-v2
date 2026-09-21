# REQ-002 — AUTH-01: login + 2FA TOTP

| Campo | Valor |
|---|---|
| **ID** | REQ-002 |
| **Descripción** | El paquete `alma/auth` ofrece login por email/contraseña y segundo factor TOTP, con tokens Sanctum que distinguen challenge 2FA de sesión plena. |
| **Módulo** | AUTH-01 · servicio + HTTP mínimo |
| **Estado** | cerrado |
| **Misión** | MIS-002 |

## Criterio verificable

1. Credenciales válidas **sin** 2FA → respuesta `authenticated` + token Sanctum con ability `*`.
2. Credenciales válidas **con** 2FA habilitado → `2fa_required` + token solo con ability `2fa:verify` (no sirve para `/2fa/enable`).
3. Código TOTP válido tras challenge → `authenticated` + token pleno.
4. Credenciales inválidas o email inexistente → mismo mensaje de error de credenciales; el camino de email inexistente ejecuta un `Hash::check` contra un dummy hash (anti-timing básico).
5. Enrolamiento 2FA: `POST /2fa/enroll` (token pleno) devuelve secreto; **no** deja 2FA activo hasta `POST /2fa/confirm` con código válido.
6. Suite PHPUnit (Testbench) ejercita los flujos anteriores; `composer test` en verde.

## Fuera de alcance (este REQ)

- Refresh tokens / familias (AUTH-02)
- Lockout por cuenta + IP (AUTH-03)
- Cadena HMAC de auditoría (AUTH-07)
- OAuth / passkeys / trusted devices / cambio de email
- Copiar el árbol histórico `alma-auth` verbatim
