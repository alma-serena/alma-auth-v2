# MIS-001 — génesis scaffold alma/auth

| Campo | Contenido |
|---|---|
| Misión | MIS-001 · scaffold Composer del paquete y salida de génesis |
| Modo | genesis → paquete (al cierre) |
| REQ | REQ-001 (retroactivo al cierre) |
| Hecho | `composer.json`, `src/AuthServiceProvider.php`, test de humo, DoD ejecutable, modo `paquete`, raíz de confianza ya configurada en el remoto |
| Abierto | primitivas AUTH-01…10 por REQ propios; bindings de contratos; consumidor de graduación |
| Verificación | `composer test` · OK (1 test, 2 aserciones) · línea base: 0 (no había DoD) · `composer style` verde tras Pint |
| Archivos | `composer.json`, `phpunit.xml`, `src/AuthServiceProvider.php`, `tests/AuthServiceProviderTest.php`, `proyecto-auth.md`, `.alma/modo-mision`, `README.md`, `docs/req/REQ-001-genesis.md`, `docs/misiones/MIS-001-genesis.md` |
| Puertas | cierre de misión — aprobado por autorización explícita del dueño en chat (2026-09-21), no por acción manual en UI |
| Ejecutor | Cursor Composer · sesión alma-auth-v2 instalación+génesis |
| Efectos | alcance autorizado al abrir: `git`, `bash`, `composer`, `php`, `gh` (cinco clases derivadas de intérprete) |
| Siguiente | abrir REQ/misión de la primera primitiva (p. ej. AUTH-01 login+2FA) o anclar consumidor |

## Transiciones

- 2026-09-21 · abierta · actor: Nico (autorización chat) · motivo: opción B + cuatro pasos
- 2026-09-21 · en_progreso · actor: Cursor Composer · motivo: scaffold
- 2026-09-21 · cerrada · actor: Cursor Composer bajo autorización explícita · motivo: DoD verde local + PR
