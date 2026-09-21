# alma/auth (v2) — renacimiento bajo ALMA

Paquete Composer headless de autenticación para hosts Laravel del ecosistema Alma.

| Pieza | Estado |
|---|---|
| Estándar ALMA (`v0.1.5`) | instalado |
| Hook OPS-07 n1 | activo |
| Raíz de confianza | configurada en GitHub |
| Scaffold Composer | MIS-001 / REQ-001 |
| Primitivas AUTH | pendientes (REQ propios) |
| Consumidor de graduación | pendiente |

## Uso (cuando exista superficie)

```bash
composer require alma/auth
```

Hoy el paquete solo expone `Alma\Auth\AuthServiceProvider` vacío: las primitivas
entran por misión, no por copia del árbol histórico.

## Lectura para agentes

1. `AGENTS.md`
2. `proyecto-auth.md`
3. `.agents/rules/` y workflows según la misión
4. `METODOLOGIA.md`

## Comandos

```
composer install
composer test
composer style
```
