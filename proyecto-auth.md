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
- **Framework de host:** Laravel (el paquete depende de `illuminate/support`)
- **Auth base (planificado, no en génesis):** Laravel Sanctum ^4 + pragmarx/google2fa
- **Sin frontend propio** — headless; la UI vive en el consumidor
- **Dev (génesis):** PHPUnit 11, Laravel Pint
- **Dev (próximas misiones):** Orchestra Testbench, PHPStan — entran con el REQ que los necesite

Sanctum/Testbench no están en `composer.json` aún: Composer bloquea Laravel 11
con advisories abiertos (`block-insecure`). La génesis no abre esa puerta; la
primera primitiva AUTH lo resolverá con versiones no afectadas o excepción
declarada.

## Comandos ejecutables

```
setup:  composer install --no-interaction
correr: no aplica — es biblioteca
tests:  composer install --no-interaction && composer test
lint:   composer install --no-interaction && composer style
```

`composer analyse` (PHPStan) es DoD de backend del anexo cuando haya superficie
analizable comprometida; aún no está en el campo `lint:`.

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
herramientas: git, bash, composer, php, gh
```

`ALMA_AUTH_HMAC_KEY` es variable de entorno del consumidor/host, no una ruta de este
árbol. No se versiona ningún valor de clave.

`herramientas` incluye intérpretes de propósito general: el efecto externo se
deriva (red, escritura, gasto) y no se afirma inocuidad.

## Excepciones declaradas

- **Qué:** el repositorio es **público** aunque el histórico `alma-auth` era privado.
  · **Por qué:** en cuenta personal, GitHub responde 403 a la protección de rama en
  repos privados; sin visibilidad pública no hay raíz de confianza.
  · **Qué lo compensa:** `RAIZ-DE-CONFIANZA.md` aplicada (`certificacion` requerida,
  `enforce_admins`, PR obligatorio, `GITHUB_TOKEN` read-only).
  · **Cuándo se revisa:** si el repo se muda a organización con protección en privado,
  o si el dueño acepta excepción de nivel 2 y vuelve a privado.

## Notas de dominio

- Modo de misión vigente tras MIS-001: **`paquete`**.
- Contratos inyectables del paquete deben resolverse en el service provider
  (hallazgo histórico S2-11 del anexo Laravel).
- Primitivas de referencia (AUTH-01…10) viven en la spec de plataforma / README
  del ciclo anterior; cada una entra por REQ propio, no por nostalgia del árbol
  viejo.
- Remoto: https://github.com/alma-serena/alma-auth-v2
