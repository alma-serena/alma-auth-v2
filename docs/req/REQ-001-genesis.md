# REQ-001 — génesis del paquete alma/auth (v2)

| Campo | Valor |
|---|---|
| **ID** | REQ-001 |
| **Descripción** | El repositorio `alma-auth-v2` debe existir como paquete Composer `alma/auth` bajo gobernanza ALMA: andamiaje del estándar ya instalado; scaffold mínimo (autoload, service provider vacío, un test de humo) ejecutable con `composer test`; modo de misión `paquete`. |
| **Módulo** | génesis / empaquetado |
| **Estado** | cerrado |
| **Origen** | misión de génesis MIS-001 (REQ retroactivo al cierre, EST-04 / METODOLOGIA) |

## Criterio verificable

1. `composer install` completa sin error.
2. `composer test` reporta al menos 1 test en verde.
3. `.alma/modo-mision` contiene exactamente `paquete`.
4. `proyecto-auth.md` declara `tests: composer test` sin excepción de DoD vigente.
