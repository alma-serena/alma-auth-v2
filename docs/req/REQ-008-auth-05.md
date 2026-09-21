# REQ-008 — AUTH-05: trusted devices

| Campo | Valor |
|---|---|
| **ID** | REQ-008 |
| **Descripción** | Un fingerprint de dispositivo marcado como confiable omite el challenge TOTP en login mientras no expire ni se revoque. |
| **Módulo** | AUTH-05 |
| **Estado** | cerrado |
| **Misión** | MIS-008 |

## Criterio verificable

1. Tras `2fa/verify` con `trust_device=true` y `device_fingerprint` no vacío, se persiste el dispositivo (TTL configurable).
2. Login posterior con el mismo fingerprint + 2FA habilitado → `authenticated` (sin `2fa_required`); se actualiza `last_used_at`.
3. Fingerprint vacío, expirado o revocado → sigue exigiendo 2FA.
4. `GET /devices` lista los activos; `DELETE /devices/{id}` (con step-up) revoca y el fingerprint vuelve a exigir 2FA.
5. Eventos `trusted_device.marked` / `trusted_device.revoked` (y uso en login cuando aplica).
6. `composer test` verde.

## Fuera de alcance

- Notificaciones push/email al marcar dispositivo (el host observa audit)
- AUTH-08 legal, AUTH-10 RBAC
