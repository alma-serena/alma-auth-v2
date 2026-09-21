# MIS-003 — AUTH-02 refresh tokens

## Contexto

AUTH-01 estaba en main. Se añadió refresh con familia, rotación y detección de reuso.

## Cambios

REQ-003 cerrado.

## Fuera de alcance

AUTH-03, AUTH-07, OAuth, passkeys.

## Cierre

```
composer install --no-interaction && composer test
composer install --no-interaction && composer style
bash verificadores/secretos.sh
```

| Campo | Contenido |
|---|---|
| Misión | MIS-003 · AUTH-02 refresh |
| Modo | paquete |
| REQ | REQ-003 |
| Hecho | Emisión en login/2fa verify, `/refresh`, hash-only, reuso revoca familia, fingerprint |
| Abierto | AUTH-03 lockout; AUTH-07 audit HMAC |
| Verificación | `composer test` · OK (9 tests, 48 assertions) · línea base MIS-002: 5/28 |
| Archivos | `src/Models/RefreshToken.php`, repo, migración, AuthService/Controller/routes, tests Auth02* |
| Puertas | auth · dueño «vamos» 2026-09-21 |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | REQ-004 / AUTH-03 si el dueño lo pide |

## Transiciones

- 2026-09-21 · abierta · Nico
- 2026-09-21 · cerrada · Cursor Composer bajo autorización explícita
