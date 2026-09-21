# REQ-007 — AUTH-04: passkeys (WebAuthn)

| Campo | Valor |
|---|---|
| **ID** | REQ-007 |
| **Descripción** | El paquete registra y autentica con passkeys (WebAuthn). La ceremonia criptográfica es un contrato inyectable; en tests se usa un fake. |
| **Módulo** | AUTH-04 |
| **Estado** | cerrado |
| **Misión** | MIS-007 |

## Criterio verificable

1. Con step-up reciente: `POST /passkeys/register/options` devuelve opciones de creación; `POST /passkeys/register` persiste la credencial (solo hash/id opaco + clave pública; sin secreto privado).
2. Sin step-up: registro y borrado de passkeys → `403` `step_up_required`.
3. `POST /passkeys/login/options` (+ email opcional) y `POST /passkeys/login` con aserción válida → `authenticated` + access + refresh (equivalente a login pleno; no exige TOTP adicional).
4. Aserción inválida o credencial revocada → fallo sin filtrar si el usuario existe.
5. `DELETE /passkeys/{id}` (con step-up) revoca; no se puede autenticar con ella después.
6. Eventos de auditoría `passkey.registered` / `passkey.authenticated` / `passkey.revoked` (y fallos).
7. `composer test` verde con `FakePasskeyCeremony` (sin navegador ni hardware).

## Fuera de alcance

- AUTH-05 trusted devices, AUTH-08 legal, AUTH-10 RBAC
- UI / JavaScript del cliente WebAuthn
- Attestation metadata service (MDS) / enterprise attestation
