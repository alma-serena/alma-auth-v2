# MIS-002 — AUTH-01 login + 2FA TOTP

## Contexto

Tras MIS-001 el repo tenía andamiaje ALMA y un `AuthServiceProvider` vacío.
El histórico `alma-auth` documentó fallos de AUTH-01 (token parcial evitable,
enrolamiento sin confirmar posesión). Esta misión implementó el núcleo **sin**
copiar ese árbol.

## Cambios

REQ-002 cerrado: login, challenge 2FA, enrolamiento confirmado, rutas
`api/alma-auth`, tests Testbench verdes.

## Fuera de alcance

AUTH-02, AUTH-03, AUTH-07, OAuth, passkeys, trusted devices, refresh HTTP,
copiar `alma-auth/` histórico.

## Orden con puertas

1. Dependencias Sanctum / Google2FA / Testbench — **puerta 3** · autorizada («vamos uno por uno»).
2. Contratos + servicio + HTTP + provider + config.
3. Tests de los seis criterios de REQ-002.
4. Cierre + PR — **puerta 2** (superficie de auth) · misma autorización.

## Cierre

```
composer install --no-interaction && composer test
composer install --no-interaction && composer style
bash verificadores/secretos.sh
```

| Campo | Contenido |
|---|---|
| Misión | MIS-002 · AUTH-01 login + 2FA TOTP |
| Modo | paquete |
| REQ | REQ-002 |
| Hecho | Login, challenge 2FA, enroll+confirm, rutas, 5 tests / 28 aserciones |
| Abierto | AUTH-02 refresh; AUTH-03 lockout; AUTH-07 audit HMAC |
| Verificación | `composer test` · OK (5 tests, 28 assertions) · línea base MIS-001: 1/2 |
| Archivos | `src/**`, `config/`, `resources/lang/`, `tests/Auth01*`, `composer.json`, docs REQ/MIS |
| Puertas | deps + auth · dueño en chat 2026-09-21 |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | REQ-003 / AUTH-02 refresh tokens (si el dueño lo pide) |

## Transiciones

- 2026-09-21 · abierta · Nico (chat)
- 2026-09-21 · en_progreso · Cursor Composer
- 2026-09-21 · cerrada · Cursor Composer bajo autorización explícita
