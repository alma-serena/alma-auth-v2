# METODOLOGIA — el ciclo

```
REQ  →  misión  →  implementación  →  cierre
```

Cuatro pasos y ninguno se salta. Un cambio sin REQ es trabajo sin origen; una misión
sin cierre es trabajo sin evidencia.

---

## 1 · REQ

Todo cambio nace de un REQ: una fricción real, un hallazgo de evaluación, o un vacío
detectado. **Un cambio que no puede nombrar su REQ no debería estar ocurriendo.**

### Contrato mínimo

| Campo | Regla |
|---|---|
| **ID** | único, y único de forma que impida duplicados por descuido |
| **Descripción** | **verificable**. «Mejorar el rendimiento» no es un REQ: es un deseo |
| **Módulo** | dónde vive |
| **Estado** | abierto · en curso · cerrado · superado |

### Almacenamiento

Como modelo con su migración, o como markdown — **lo decide la capa de proyecto**, y
la elección es legítima en las dos direcciones: una aplicación con base de datos hará
lo primero, un repo documental lo segundo.

Lo que **no** decide la capa de proyecto: el almacenamiento es **append-only, sin
edición de histórico**. Un REQ que se reescribe deja de ser registro.

### Enmienda — `AMEND REQ`

Un REQ mal especificado **no se reescribe**. Se le añade un bloque `AMEND REQ-xx` que
**agrega**, dejando intacto el original y su historia. Quien lo lea dentro de un año
verá qué se pidió primero y qué se aclaró después — que suele ser la información más
útil de las dos.

### REQ de cambio

Un feedback que contradice un REQ **ya cerrado** no lo reabre. Crea un **REQ de
cambio** vinculado, que marca el original como **superado — no borrado**.

El ítem de feedback **no cierra** hasta que ese REQ de cambio se acepta o se rechaza
de forma explícita. Un feedback que se archiva sin veredicto es un feedback que
volverá.

---

## 2 · Misión

**Un objetivo verificable y un cierre.** Si aparecen dos objetivos, son dos misiones —
y notarlo a tiempo es más barato que descubrirlo en el cierre.

### Modo, declarado al abrir

| Modo | Qué permite |
|---|---|
| **aplicación** | `app/` sí, `packages/` legible e inmodificable |
| **paquete** | el paquete sí, `app/` no |
| **génesis** | directorio vacío: se propone estructura desde el estándar sin archivo previo, y el REQ se genera retroactivo en el cierre |
| **estándar** | cambios al propio estándar, bajo su gobernanza |

El modo condiciona qué está permitido tocar, y el cierre lo verifica comparando el
diff contra el modo. No es una etiqueta: es la frontera.

**Dónde vive.** El modo declarado se escribe en **`.alma/modo-mision`**, un archivo de
una sola línea con exactamente una de las cuatro palabras: `aplicacion`, `paquete`,
`genesis` o `estandar`. Lo lee el hook de OPS-07 nivel 1 para comparar el diff
escenificado contra la frontera del modo.

**Ausente o inválido: el commit falla.** No es un aviso. Un modo sin declarar deja al
hook sin frontera contra la cual comparar, y una frontera que no se puede comprobar no
es una frontera.

### Estados

```
abierta  →  en_progreso  →  cerrada
                  ↓  ↑
               pausada
```

**El estado no es un campo que se sobrescribe.** Cada transición se **anexa** con su
actor y su motivo, y el estado actual es la **proyección** de esas transiciones — la
última que aplica. Es el mismo patrón que el log del Runtime y su `proyeccion.json`,
que lleva escrito «DERIVADO del log. No es fuente de verdad. Regenerable».

Un campo mutable perdería justamente lo que hace falta para retomar: no *qué* estado
tiene, sino **cómo llegó a tenerlo**.

### Plantilla de pausa — obligatoria

Una misión que se pausa **no se cierra**. Se marca `pausada` y se llena:

```
Avance:          qué quedó hecho y verificado
Pendientes:      qué falta, en orden
Archivos tocados: rutas concretas
Siguiente paso:  la primera acción de quien retome
```

Es un checkpoint con documentación mínima, sin puerta de cierre completa. Existe para
que retomar seis semanas después no sea reconstruir — y reconstruir es exactamente lo
que este proyecto no admite como fuente de verdad.

---

## 3 · Implementación

Gobernada por `.agents/rules/` y el workflow que corresponda. Dos cosas que valen
aquí y no en otro sitio:

- **Las puertas de OPS-01 se cruzan cuando aparecen**, no al final. Una puerta
  anunciada en el cierre es una puerta que ya se cruzó.
- **El escalamiento de OPS-03 se dispara solo**, por síntoma persistente. No espera a
  que alguien lo note.

---

## 4 · Cierre

Orden invariante: **verificar → respaldar → documentar → commitear**. Detalle completo
en `.agents/workflows/cierre.md`.

Lo que no se negocia: la evidencia es un run externo, el diff se compara contra el
modo de misión, y el push es acto humano.

---

## Caminos infelices

El camino feliz estaba diseñado exhaustivamente en v0.2.1; estos no lo estaban, y por
eso están aquí con el mismo rango. Un proyecto vivo envejece, se interrumpe, se
equivoca y se actualiza.

| Situación | Camino |
|---|---|
| Hay que parar a medias | estado `pausada` + plantilla |
| El REQ estaba mal escrito | `AMEND REQ` |
| Un feedback contradice algo cerrado | REQ de cambio, original superado |
| Hay que deshacer lo commiteado | `.agents/workflows/reversion.md` |
| El estándar subió de versión | `.agents/workflows/migracion-estandar.md` |
| El agente no avanza | escala al humano (OPS-03). El traspaso entre agentes está diseñado en `.agents/workflows/intervencion.md`, **no normativo** hasta que exista certificación externa |

## La regla vale lo que vale su comprobación

Lo que sigue no trata del ciclo, sino de **la distancia entre una regla y su
comprobación**. Una regla que no se puede comprobar completa, o que se comprueba
mirando otra cosa, no es una regla más débil: es un permiso con redacción de regla.

Entra aquí una sección cuando enuncia **cómo debe escribirse algo para que su
comprobación signifique lo que dice**. No entran las reglas mismas — esas viven en su
dominio— ni las excepciones operativas, que están en «Caminos infelices».

### Los dos niveles invocan igual `[D-C47]`

> **El nivel 1 solo predice el nivel 2 si mira lo mismo.** Si difieren en cómo invocan
> los verificadores, en qué entorno corren o en qué archivos leen, el verde local deja
> de decir algo sobre el rojo externo — y lo peor no es que falle: es que no falla.

El primer run real de la certificación lo demostró sobre el propio estándar. El hook
pasaba cada script a `bash`; el workflow los invocaba directamente. La primera forma
ignora el bit de ejecución, la segunda lo exige. Sobre Windows, donde
`core.filemode` es `false` y el bit no existe en el sistema de archivos, **ningún
control local podía verlo**: el hook aprobó en verde el mismo árbol que el runner
rechazó con `exit 126`.

No fue un permiso olvidado. Fue que los dos niveles no estaban mirando lo mismo, y esa
diferencia solo es visible desde fuera del working copy — que es exactamente para lo
que existe OPS-07 nivel 2.

De ahí la regla, que es más ancha que su incidente:

| Debe coincidir | Por qué |
|---|---|
| La forma de invocar | Pasar el script a `bash` y ejecutarlo directamente no fallan ante lo mismo |
| El bit de ejecución en el índice | Git omite un hook no ejecutable **sin decir nada**: la prevención local desaparece en silencio al clonar en Linux |
| Los finales de línea | `.gitattributes` con `* text=auto eol=lf`; un `\r` en la shebang es `exit 126` con otro nombre |

Cuando el nivel 1 y el nivel 2 no puedan coincidir —y a veces no podrán, porque uno
corre en el equipo del humano y el otro en un contenedor limpio— **la diferencia se
escribe**, para que nadie lea el verde local como una promesa.

### Enumerar `[D-C46]`

> **Se enumera lo permitido, nunca lo prohibido.** Una lista de lo prohibido es finita
> y el mundo no lo es: cada nombre que falta es un permiso.

Tres raíces de componentes dejaban fuera `resources/js/components`; trece extensiones de
código dejaban fuera `.dart` y `.cs`; cuatro nombres de intérprete dejaban fuera `php` y
`pwsh`. **Las tres listas eran correcciones nuestras a huecos anteriores**, y las tres
movieron el hueco en vez de cerrarlo — el evaluador lo dijo seis veces con la misma
frase: *«el hueco se movió»*.

Y donde no se puede enumerar lo permitido —las herramientas, porque cualquier intérprete
produce cualquier efecto— **no se enumera: se autoriza**. Una autorización de alcance,
firmada por un humano al abrir la misión, es comprobable porque está escrita. Una
derivación a partir de nombres no lo es.

### Estados que eximen `[D-C41]`

Un estado que exime de una obligación **declara su condición de salida, y esa condición
es comprobable por máquina.** Sin condición de salida, un estado legítimo el primer día
se convierte en permiso permanente: se entra por una razón verdadera y no se sale nunca.

| Estado | Se sale cuando | Comprobación |
|---|---|---|
| `genesis` | aparece el primer archivo que no es andamiaje | **lista blanca**: el proyecto declara `andamiaje:`; todo lo demás es producto y el modo falla |
| `interfaz: no` | el proyecto tiene interfaz | **no es comprobable por máquina** — el anexo detecta el caso común y nada más. Declararlo en falso es una mentira humana, registrada. Ver la columna «¿falla cerrado hoy?» de `.agents/rules/seguridad.md` |
| `en convergencia` | cero entradas `superado por` con usos vivos | las cuenta `verificadores/catalogo.sh` |
| `pausada` | la misión se retoma o se cierra | la plantilla de pausa exige el siguiente paso |

La regla se enuncia aquí, y no en cada archivo, porque **el quinto estado de escape que
inventemos debe nacer ya cerrado**.
