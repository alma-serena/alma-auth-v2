# REQ-012 — OAuth social (vínculo + login)

| Campo | Valor |
|---|---|
| **ID** | REQ-012 |
| **Descripción** | Login y vínculo con identidades OAuth de proveedores configurados. La verificación del token del proveedor es un contrato inyectable (sin Socialite en el paquete). Vínculo solo tras sesión + step-up; nunca autovincular por email. |
| **Módulo** | OAuth social |
| **Estado** | cerrado |
| **Misión** | MIS-012 |

## Criterio verificable

1. `POST /oauth/link` (auth `*` + step-up) con proveedor permitido y credencial verificable persiste `provider` + `provider_user_id` → evento `oauth.linked`.
2. Proveedor fuera de catálogo o verificación fallida → error sin filtrar existencia de cuenta.
3. `POST /oauth/login` con identidad ya vinculada → `authenticated` (+ refresh) o `2fa_required` si aplica TOTP/trusted device (misma regla que login password).
4. Identidad no vinculada → fallo uniforme (`oauth_invalid`), sin crear usuario ni vincular por email.
5. `DELETE /oauth/{provider}` (step-up) desvincula; `GET /oauth/links` lista proveedores vinculados.
6. `composer test` verde con `FakeOAuthIdentityVerifier` (sin llamadas de red).

## Fuera de alcance

- Dependencia `laravel/socialite` (el host implementa el verificador o adapta Socialite)
- Autoregistro / autovínculo por email
- Apple/Google SDKs embebidos
