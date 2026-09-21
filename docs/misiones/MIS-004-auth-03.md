# MIS-004 — AUTH-03 lockout IP|cuenta

## Contexto

El histórico incrementaba un contador y nunca lo leía. Cerrado con lectura real.

## Cambios

REQ-004 cerrado.

## Fuera de alcance

AUTH-07, trusted devices, cambiar throttle HTTP de producción.

## Cierre

| Campo | Contenido |
|---|---|
| Misión | MIS-004 · AUTH-03 lockout |
| Modo | paquete |
| REQ | REQ-004 |
| Hecho | Contador IP\|email leído; bloqueo tras N fallos; clear en éxito; mismo mensaje |
| Abierto | AUTH-07 audit HMAC; AUTH-04+ |
| Verificación | `composer test` · OK (13 tests, 73 assertions) · línea base MIS-003: 9/48 |
| Archivos | AuthService, AuthController, config, tests Auth03* |
| Puertas | auth · «vamos» |
| Ejecutor | Cursor Composer |
| Efectos | git, bash, composer, php, gh |
| Siguiente | AUTH-07 o la primitiva que pida el dueño |

## Transiciones

- 2026-09-21 · abierta / cerrada · autorización explícita
