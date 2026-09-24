# MIS-014 — alta por correo

> Formato fijo de `alma-standard/.agents/workflows/instruccion.md`.

**Estado:** cerrada
**Modo:** `paquete`
**REQ:** REQ-014
**Autoridad:** Nico · 2026-09-24 · «ok, vamos»

## Contexto

`alma-auth-v2` cierra en MIS-013. Hay login, 2FA, refresh, lockout y auditoría. No hay ruta para abrir una cuenta. El host resuelve el usuario con `config('alma-auth.user_model')`. WorldWeaver todavía no consume el paquete.

## Cambios

Al cerrar, se puede comprobar que `POST /api/alma-auth/register` abre la cuenta, que repetir el correo no cambia la contraseña ni delata que ya existía, y que una contraseña filtrada no crea la cuenta.

## Fuera de alcance

- Instalar el paquete en WorldWeaver
- Enviar correo
- Alta por Google, passkey o OAuth
- Pantallas
- Cambiar el archivo `.agents/rules/seguridad.md`
- Push

## Orden con puertas

1. **PUERTA 2.** Superficie de autorización. Aprobada («ok, vamos»).
2. Alta, anti-enumeración y rechazo de contraseña filtrada, con tests. Sin dependencia Composer nueva: la consulta de filtraciones usa el HTTP del host.
3. **PUERTA 5.** Cierre. Aprobada («vamos»).

## Cierre

```
composer install --no-interaction && composer test
composer install --no-interaction && composer style
```

Corridos el 2026-09-24, antes del commit:

- `composer test` → 57 tests, 265 aserciones, en verde. Línea base MIS-013: 51 tests, 244 aserciones.
- `composer style` → passed.

## Notas

- Un objetivo: abrir la cuenta por correo.
- Acción crítica: el alta queda en la auditoría (`account.opened`). No hay otro registro de críticas en este paquete.
- La derivación de la contraseña la hace el `hashed` del modelo del host. Al integrar, el host usa Argon2id.
- La consulta a Have I Been Pwned es k-anonimato (prefijo SHA-1). La contraseña no se guarda ni se escribe en el log. Si el servicio no responde, el alta sigue.

## Transiciones

- 2026-09-24 · abierta · Nico · «ok, vamos»
- 2026-09-24 · puerta 2 · Nico · «ok, vamos»
- 2026-09-24 · en_progreso · Cursor · alta y tests
- 2026-09-24 · puerta 5 · Nico · «vamos»
- 2026-09-24 · cerrada · Cursor bajo autorización explícita

## Tabla de cierre

| Campo | Contenido |
|---|---|
| Misión | MIS-014 · alta por correo |
| Modo | paquete |
| REQ | REQ-014 |
| Hecho | `POST /api/alma-auth/register` abre la cuenta, repetir el correo no cambia la contraseña ni delata que ya existía, y una contraseña filtrada no crea la cuenta |
| Abierto | Instalar el paquete en WorldWeaver. Envío de correo. Alta por Google o passkey |
| Verificación | `composer test` · 57 tests, 265 aserciones · línea base MIS-013: 51/244 · `composer style` passed |
| Archivos | `AuthService::openAccount`, `AuthController::register`, `HibpPasswordChecker`, rutas, tests Auth14 y Hibp, REQ/MIS-014 |
| Puertas | 2 (autorización) y 5 (cierre), aprobadas por Nico con «vamos» |
| Ejecutor | Cursor |
| Efectos | alcance autorizado: `git`, `composer`, `php` |
| Siguiente | Instalar `alma/auth` en WorldWeaver. Lo decide el humano |
