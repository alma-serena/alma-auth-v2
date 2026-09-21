# REQ-009 — AUTH-08: consentimiento legal (hooks Ley 21.719)

| Campo | Valor |
|---|---|
| **ID** | REQ-009 |
| **Descripción** | El paquete registra aceptaciones de política/finalidad con versión, sin textos legales embebidos (los aporta el host). Soporta el ancla mínima de consentimiento de la Ley 21.719 chilena. |
| **Módulo** | AUTH-08 |
| **Estado** | cerrado |
| **Misión** | MIS-009 |

## Criterio verificable

1. `POST /legal/consent` (auth `*`) con `purpose` + `policy_version` válidos persiste el registro; IP se guarda hasheada (no en claro).
2. `purpose` fuera del catálogo configurado → `422`.
3. `GET /legal/consents` lista los del usuario (sin IP en claro).
4. `hasConsent(user, purpose, minVersion?)` distingue vigencia por versión.
5. Evento de auditoría `legal.consent_recorded`.
6. `composer test` verde.

## Fuera de alcance

- Textos de política / UI de banner
- Paquete Composer separado `alma/auth-legal-cl`
- AUTH-09 roadmap, AUTH-10 RBAC
- Ejercicio completo de derechos ARCO (export/borrado masivo)
