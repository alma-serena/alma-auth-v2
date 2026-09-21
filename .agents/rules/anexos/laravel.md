# Anexo — Laravel / PHP

Aterriza las reglas de `../backend.md`, `../seguridad.md`, `../frontend.md` y
`../paquetes.md` en nombres propios. **Anexo principal:** es el stack declarado como
preferente por el dueño.

Cada fila da el `grep` o la herramienta que lo encuentra. Una regla de este anexo sin
forma de encontrarla no entra.

## backend

| Patrón | En Laravel | Cómo se encuentra |
|---|---|---|
| No cargar colección completa | Prohibido `Model::all()` y `->get()` sin `where`/`limit`/`paginate` | `grep -rnE "::all\\(\\)"` **y** `grep -rnE "->get\\(\\)" app/ \| grep -vE "where|whereIn|limit|take|paginate|find"` — la segunda es la que faltaba: el primer grep no veía una sola de las cargas `->get()` |
| No consultar en bucle | Prohibida cualquier consulta dentro de `foreach`/`while` | revisión + `preventLazyLoading` |
| Sin carga perezosa implícita | `Model::preventLazyLoading()` activo en pruebas y local | `grep` en el proveedor de servicios |
| Orquestador delgado | Controladores validan, delegan y responden; nada más | revisión de cierre |
| Unidad de trabajo | Actions con contrato `Actionable`, retornan DTO o modelo, **nunca** `Response` | `grep -rn "return response" app/Actions` |
| Objetos inmutables | DTOs `readonly` | analizador estático |
| Tipos completos | PHP ≥ 8.3, tipos en firma y retorno | Larastan en el nivel comprometido |
| Validación de formato | Form Requests validan formato; las Actions validan invariantes | revisión |
| Transacción | `DB::transaction()` en toda Action de escritura múltiple | `grep` + revisión |
| SQL crudo acotado | Solo en clases `Query` dedicadas | `grep -rn "DB::raw\|DB::select"` |
| Excepciones | Traducidas solo en el Handler | `grep` de `catch` en controladores |
| Estilo | PSR-12 vía Pint | `pint --test` |

## seguridad

| Patrón | En Laravel | Cómo se encuentra |
|---|---|---|
| Secreto cifrado en reposo | `casts` con `encrypted` | inspección del modelo |
| Códigos de recuperación | `bcrypt`, no cifrado | inspección del modelo |
| Token solo en hash | SHA-256 en columna; el valor claro se muestra una vez | inspección del esquema |
| Contraseña | Argon2id + regla `uncompromised()` con degradación elegante | `grep` de la regla |
| Permisos sembrados | Seeder; nada de creación manual | migración + seeder en CI |
| Auditoría de dependencias | `composer audit` (y `npm audit` si hay front) | paso de CI |
| Anti-enumeración uniforme | Mismo mensaje **y mismo tiempo** exista o no la cuenta; retardo uniforme para identidad no encontrada | prueba de tiempos sobre email inexistente vs existente |
| Bloqueo IP + cuenta | `RateLimiter` con clave compuesta `ip\|email`, nunca solo `email` | `grep -rn "RateLimiter::" \| grep -v "->ip()"` |
| Cabeceras de seguridad | Middleware con CSP, `X-Content-Type-Options`, `Referrer-Policy`, HSTS | prueba de respuesta que asevera cada cabecera |
| Límite de tasa en endpoints sensibles | `throttle` en login, recuperación y desbloqueo | `grep -rn "middleware(.*throttle" routes/` |
| Token solo en hash | Ninguna columna `*_token` sin hash; el valor claro se muestra una vez | inspección del esquema + `grep -rn "plainTextToken"` |

## paquetes

| Patrón | En Laravel | Cómo se encuentra |
|---|---|---|
| Dependencia unidireccional | El paquete jamás importa `App\` | ArchTest |
| Aislamiento de pruebas | Orchestra Testbench + SQLite en memoria | `phpunit.xml` |
| Contratos publicados | Interfaces en `Contracts/` + README | revisión |
| Enlaces del contenedor | Todo contrato inyectable **debe** estar enlazado en el proveedor | prueba que resuelve cada contrato |

> La última fila nace de un defecto real: en `alma/auth`, `AuditLogger` y `RbacPolicy`
> existían como contratos y no estaban enlazados — inyectarlos lanzaba
> `BindingResolutionException`. Hallazgo S2-11. Una prueba que resuelva cada contrato
> del paquete lo habría atrapado el primer día.

## frontend

| Patrón | Concretamente | Cómo se encuentra |
|---|---|---|
| Tipado estricto | TypeScript `strict`, prohibido `any` y `@ts-ignore` | `tsc --noEmit` + `grep` |
| Sin efectos para fetch | Prohibido `useEffect` para datos de servidor | `grep -rnE "useEffect\\(" -A6 \| grep -E "fetch\\(\|axios\\.\|\\$http\\|api\\."` — marcar **todo** `useEffect` produce tantos falsos positivos que se ignora el check entero, y un check que se ignora es peor que ninguno |
| Caché de servidor | React Query, SWR o acciones de servidor | revisión |

## diseño

| Patrón | En Laravel | Cómo se encuentra |
|---|---|---|
| Directorio de componentes | `resources/views/components/` (Blade) o `app/Livewire/` según el proyecto; se **declara** en `proyecto-<nombre>.md` | `verificadores/catalogo.sh` |
| Tokens | variables CSS en un único archivo declarado, o la configuración de tema de Tailwind | archivo declarado en `tokens:` |
| Cero color literal | prohibido `#rrggbb`, `rgb()` y las utilidades arbitrarias tipo `bg-[#...]` dentro del directorio de componentes | `grep -rnE '#[0-9a-fA-F]{3,8}\b\|rgba?\(\|-\[#' resources/views/components/` |
| Estilo en atributo | prohibido `style="…"` en plantillas | `grep -rn 'style="' resources/views/` |
| Maqueta | ruta solo en entorno de desarrollo, generada desde el archivo de catálogo | `grep` de la ruta en `routes/` + `app()->environment()` |
| Base recomendada | cuando exista `alma/ui`, es la base sugerida para este stack — **se declara en `proyecto-<nombre>.md`, no aquí** | campo `origen` del catálogo |

> El estándar no nombra ninguna base concreta: la dependencia apunta hacia abajo. Este
> anexo puede recomendar; quien adopta y versiona es la capa de proyecto.

## Raíces y rutas conocidas de este stack

Bloques **legibles por máquina**: `verificadores/catalogo.sh` y `verificadores/secretos.sh`
los leen. Existen porque una lista que escribe quien se beneficia de que sea corta no es
un control: el stack sabe dónde viven sus componentes y sus secretos, y el proyecto no
puede omitir una raíz sin que se note.

**Raíces de componentes.** Si alguna de estas existe en el árbol y el proyecto no la
declara en `componentes:`, el verificador falla.

<!-- raices-componentes:inicio -->
resources/views/components
app/Livewire
app/View/Components
<!-- raices-componentes:fin -->

**Rutas secretas.** Si alguna de estas existe y no está declarada en `rutas_secretas:`
ni ignorada por git, el barrido falla.

<!-- rutas-secretas:inicio -->
.env
.env.local
.env.production
.env.staging
storage/oauth-private.key
storage/oauth-public.key
<!-- rutas-secretas:fin -->

## Definición de Hecho

- **Backend:** `composer test` (con ArchTests) · `pint --test` · analizador estático
  en el nivel comprometido.
- **Frontend:** `tsc --noEmit` · linter · pruebas con el consumidor real.

Se aplica por superficie tocada. Extiende, nunca reduce.
