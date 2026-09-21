# REQ-006 — AUTH-06: step-up (token) + cambio de email

| Campo | Valor |
|---|---|
| **ID** | REQ-006 |
| **Descripción** | Operaciones sensibles exigen reautenticación reciente anclada al access token Sanctum (no a sesión web). Cambio de email requiere step-up y confirmación por código de un solo uso. |
| **Módulo** | AUTH-06 |
| **Estado** | cerrado |
| **Misión** | MIS-006 |

## Criterio verificable

1. `POST /2fa/enroll` y `/2fa/confirm` con token pleno **sin** step-up reciente → `403` `step_up_required`.
2. `POST /step-up` con password correcta marca el token actual; tras eso enroll/confirm pasan.
3. Login exitoso (y `2fa/verify` exitoso) también marcan step-up reciente en ese token.
4. `POST /email/change` (con step-up) crea pendiente; `confirmEmailChange` con código correcto cambia el email; el código no se persiste en claro.
5. Código inválido o expirado no cambia el email.
6. Eventos de auditoría `email.change_requested` / `email.changed` / fallos de step-up según corresponda.
7. `composer test` verde.

## Fuera de alcance

- AUTH-04 passkeys, AUTH-05 trusted devices, AUTH-08 legal, AUTH-10 RBAC
- Envío real de correo (el host notifica con el código que devuelve el servicio)
