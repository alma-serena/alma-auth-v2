# REQ-013 — Adaptador OAuth Google

| Campo | Valor |
|---|---|
| **ID** | REQ-013 |
| **Descripción** | El paquete incluye un verificador Google real (userinfo / tokeninfo) sin Socialite. Se activa si hay `oauth_google.client_id`; si no, el default sigue rechazando. |
| **Módulo** | OAuth · Google |
| **Estado** | cerrado |
| **Misión** | MIS-013 |

## Criterio verificable

1. Con `ALMA_AUTH_GOOGLE_CLIENT_ID` / `oauth_google.client_id` definido, `OAuthIdentityVerifier` resuelve a un composite que delega `google` al adaptador.
2. `access_token` válido (simulado) → `OAuthUserInfo` con `provider=google` y `providerUserId=sub`.
3. `id_token` válido (simulado) → mismo resultado; `aud` distinto al client_id → fallo.
4. `provider` distinto de `google` → fallo (`oauth_provider_unsupported`).
5. Sin `client_id`, el default sigue siendo rechazar (comportamiento MIS-012).
6. `composer test` verde con `Http::fake` (sin red).

## Fuera de alcance

- Socialite
- Adaptadores Apple/GitHub
- Autoregistro / autovínculo por email
