# proyecto-auth.md

> Único archivo editable de la capa de proyecto (OPS-05). Extiende el estándar,
> **nunca lo contradice**. Fuera de su alcance: `.agents/rules/seguridad.md` y reducir la DoD.

## Ancla de versión

Estándar: `alma-standard v0.1.5` · tag remoto: `v0.1.5`

El manifest de integridad se descarga del release de ese tag y vive en
`.alma/manifest.sha256` — no se calcula aquí `[D-C48]`. Pendiente de primera
corrida de `alma:upgrade` / descarga manual del release.

## Identidad

Paquete Composer **`alma/auth`**: autenticación headless para aplicaciones ALMA
sobre Laravel (Sanctum, 2FA TOTP, refresh tokens, anti-enumeración/lockout, audit
HMAC, base RBAC). No es una aplicación: lo consume un host Laravel.

Este repositorio (`alma-auth-v2`) es el **renacimiento** del paquete bajo gobernanza
ALMA. El remoto histórico `alma-serena/alma-auth` queda como referencia; no se copia
código aquí sin REQ.

## Stack

- **Lenguaje:** PHP ≥ 8.3
- **Framework de host:** Laravel (paquete vía Orchestra Testbench en pruebas)
- **Auth base:** Laravel Sanctum ^4
- **2FA:** pragmarx/google2fa
- **Sin frontend propio** — headless; la UI vive en el consumidor
- **Dev:** PHPUnit 11, Orchestra Testbench 9, Laravel Pint, PHPStan

## Comandos ejecutables

```
setup:  composer install
correr: no aplica — es biblioteca
tests:  no existe
lint:   no existe
```

La DoD ejecutable (`composer test` / `composer style`) entra al cerrar la misión de
génesis, cuando exista `composer.json`. Hasta entonces:

```
dod-excepcion: árbol de producto aún no existe; sin Composer no hay DoD corrible
dod-excepcion-desde: 2026-09-21
dod-revision: 2026-10-05
```

## Catálogo de interfaz

```
andamiaje: AGENTS.md, CLAUDE.md, METODOLOGIA.md, .agents/, .githooks/, .github/, verificadores/, plantillas/, proyecto-*.md, .alma/, .gitattributes, docs/, README.md, .gitignore
anexo: laravel
interfaz: no
```

`interfaz: no` es verdad: el paquete no renderiza vistas ni Livewire. Si un día
publicara componentes Blade de ejemplo, esta línea cambia y con ella el estado.

`anexo: laravel` aplica aunque el árbol sea de paquete: las raíces conocidas del
anexo solo fallan si **existen** y no están declaradas. Hoy no existen.

## Perímetro

```
rutas_secretas: .env, .env.local, .env.production, .env.staging, storage/oauth-private.key, storage/oauth-public.key
herramientas: git, bash, composer, php
```

`ALMA_AUTH_HMAC_KEY` es variable de entorno del consumidor/host, no una ruta de este
árbol. No se versiona ningún valor de clave.

`herramientas` incluye intérpretes de propósito general: el efecto externo se
deriva (red, escritura, gasto) y no se afirma inocuidad.

## Excepciones declaradas

- **Qué:** este repositorio aún no tiene raíz de confianza (OPS-07 nivel 2) en
  GitHub. · **Por qué:** el remoto `alma-serena/alma-auth-v2` no está creado; la
  instalación local precede al ancla externa. · **Qué lo compensa:** nivel 1
  (hook + verificadores) activo; ninguna declaración de conformidad hasta push +
  `certificacion` en verde. · **Cuándo se revisa:** al crear el remoto y aplicar
  `RAIZ-DE-CONFIANZA.md`.

## Notas de dominio

- Modo de misión previsto tras el andamiaje de producto: **`paquete`**. Hoy:
  `genesis` hasta que exista código de producto y el REQ de génesis cierre.
- Contratos inyectables del paquete deben resolverse en el service provider
  (hallazgo histórico S2-11 del anexo Laravel).
- Primitivas de referencia (AUTH-01…10) viven en la spec de plataforma / README
  del ciclo anterior; cada una entra por REQ propio, no por nostalgia del árbol
  viejo.
