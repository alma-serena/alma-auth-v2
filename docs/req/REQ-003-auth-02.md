# REQ-003 — AUTH-02: refresh tokens (familia + rotación)

| Campo | Valor |
|---|---|
| **ID** | REQ-003 |
| **Descripción** | El paquete emite refresh tokens opacos (solo hash en DB), los rota en cada uso, y ante reuso de un token ya revocado revoca toda la familia. |
| **Módulo** | AUTH-02 |
| **Estado** | cerrado |
| **Misión** | MIS-003 |

## Criterio verificable

1. Login autenticado (sin 2FA pendiente) y `2fa/verify` exitoso devuelven `refresh_token` además del access token.
2. `POST /api/alma-auth/refresh` con refresh válido + fingerprint coherente → nuevo `refresh_token` + nuevo access token; el anterior queda revocado.
3. Reusar el refresh ya rotado → `401` y la familia queda revocada (no se puede rotar un hermano vivo).
4. Solo se persiste `sha256` del token; el valor en claro no se guarda.
5. Familia con más de `refresh_family_ttl_days` (default 90) no rota: se revoca.
6. Suite PHPUnit cubre emisión, rotación feliz y reuso; `composer test` verde.

## Fuera de alcance

- AUTH-03 lockout, AUTH-07 audit HMAC
- Trusted devices lifecycle completo
- Cambiar el contrato de abilities de AUTH-01
