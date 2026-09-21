# diseno.md — patrón

El estándar fija el patrón, no el stack. Los nombres concretos de componente viven en
el anexo; el **catálogo** vive en el proyecto.

> **Por qué este archivo cambió de tamaño** `[V-6]`. Su versión anterior exigía «una
> instantánea versionada de la guía de interfaz que el proyecto adopte» y «nunca
> improvisar componentes que la guía ya define», **sin obligar a que la guía
> existiera**. Si la guía no define nada, nada se improvisa: la regla se cumple
> perfectamente mientras cada pantalla inventa su propia tabla. Es el mismo defecto que
> «un alcance no escrito es permiso en blanco», y su daño está medido en un proyecto
> real: varias versiones de la misma tabla, filtros propios por pantalla y colores
> literales en el código, corrigiéndose meses después.

## 1 · El catálogo

**Todo proyecto con interfaz tiene un catálogo de componentes y una maqueta que lo
muestra.** No es documentación opcional: es el artefacto contra el que se comprueba si
un componente existe ya.

**El proyecto declara el origen de su catálogo** en `proyecto-<nombre>.md`: una base
adoptada con su versión, o «desde cero». Las desviaciones respecto de la base son
excepciones declaradas, con el formato de cuatro campos. El estándar **no nombra
ninguna base concreta**: eso es del anexo del stack, que puede recomendarla, y de la
capa de proyecto, que la declara.

### Roles canónicos mínimos

Todo catálogo debe cubrir estos roles con una entrada `vigente`. La lista es de
**roles**, no de implementaciones: un `LazyColumn` y una `<x-tabla>` cubren el mismo rol.

<!-- roles-canonicos:inicio -->
tabla
filtro
formulario
dialogo-confirmacion
notificacion
estado-vacio
estado-carga
estado-error
navegacion
accion
<!-- roles-canonicos:fin -->

**No puede tener dos componentes `vigente` para el mismo rol** — eso vale siempre.

**El piso de diez roles es condición de salida, no puerta de entrada** `[D-C45]`. Un
proyecto `en convergencia` cataloga **lo que tiene**; cubrir los diez roles es lo que le
permite declararse `conforme`.

> **Por qué cambió.** La versión anterior exigía los diez desde el primer commit. En el
> primer recorrido real sobre un proyecto con historia, con dos componentes existentes,
> el verificador falló ocho roles: *«para ponerse verde habría que inventar ocho
> componentes que el producto no usa. Estuve a punto de abandonar ahí»*. Y contradecía a
> D-C33, que promete *«se escribe el catálogo con lo que ya hay, diciendo la verdad»* —
> la verdad de ese proyecto eran dos. **Una pared en la entrada no es rigor: es la
> fricción que lleva al abandono**, que es un modo de falla, no una queja.
>
> Consecuencia asumida: **casi todo proyecto real nace `en convergencia`**. No es un
> defecto — es el estado honesto de quien adopta un estándar a mitad de camino, y ahora
> tiene nombre y contador en vez de promesa.

## 2 · La ficha

Se escribe **solo lo que el código no puede decir**. Los props los dice la firma; las
variantes las dice el código; los estados los muestra la maqueta renderizándolos.
Duplicar a mano lo que ya está en el código garantiza que un día se contradigan, y el
documento gana la discusión aunque el código tenga razón.

| Campo | Qué lleva |
|---|---|
| `rol` | uno de los roles canónicos |
| `estado` | `vigente` · `en revisión` · `superado por <id>` |
| `no-usar-cuando` | cuándo NO usarlo y cuál usar en su lugar |
| `excepciones` | desviaciones respecto de la base adoptada, o `ninguna` |
| `archivo` | ruta del componente — campo mecánico, no redacción |

El campo `estado` existe por una razón concreta: **el daño no es solo que nazcan
componentes fuera del catálogo, es que convivan tres versiones del mismo.** Esa segunda
tabla no se mata impidiendo que nazca; se mata haciendo que su nacimiento marque a la
primera como superada, con nombre. Sin ciclo de vida, un catálogo solo crece.

La plantilla está en `plantillas/catalogo-EJEMPLO.md`.

## 3 · La regla de cierre

> **Componente nuevo que no está en el catálogo → entra al catálogo.**
> **Componente que se actualiza → el catálogo se actualiza.**
> **En el mismo commit.**

**Se comprueba por índice inverso** `[D-C40]`: **todo archivo bajo una raíz de
componentes tiene ficha**. No al revés.

> **Por qué así, y qué se corrigió.** La primera redacción comprobaba que *el archivo de
> catálogo estuviera en el diff*. Tres formas de cumplirla sin catalogar nada: escribir
> el componente bajo una raíz **no declarada**; añadir **una línea en blanco** al
> catálogo en el mismo commit; o dejar que lo revise el CI, que llama sin `--diff` y por
> tanto nunca corría esa comprobación. El índice inverso cierra las tres, y la tercera
> por construcción: **no depende del diff**, así que los dos llamadores la ejecutan.

**Las raíces son una lista, y no la escribe solo el proyecto.** `componentes:` admite
varias, y el anexo del stack declara las **raíces conocidas**: si alguna existe en el
árbol y el proyecto no la declaró, falla. Una lista que escribe quien se beneficia de
que sea corta no es un control.

Si bajo una raíz hay archivos que no son componentes, la salida **no** es una excepción
silenciosa: se declara una subruta más estrecha como raíz, y eso es una excepción
declarada con sus cuatro campos.

## 4 · Reglas sin excepción

Va sin excepción toda regla que **tenga verificador de máquina** y cuyo cumplimiento
**no cueste más que su violación**. Ahí una excepción nunca es necesidad, siempre es
desgana.

- **Cero valor literal de color, tipografía o espaciado fuera de los tokens.**
- **Un solo componente `vigente` por rol canónico.**
- **La regla de cierre del §3.**
- **Confirmación explícita en toda acción destructiva, con el nombre de lo que se va a
  destruir dentro del diálogo.**

> **Estas no son «los innegociables».** Ese término queda reservado a
> `.agents/rules/seguridad.md`, donde el daño es externo e irreversible. Estas son
> reglas sin excepción de interfaz: igual de obligatorias, distinta familia. Mantener la
> distinción es lo que hace que «innegociable» siga significando algo.

## 5 · Reglas con excepción declarable

Exigen criterio, y una regla que exige criterio y no admite excepción **se evade en
silencio** — que es peor que una excepción registrada.

- **Los tres estados —vacío, carga, error— existen y se ven en la maqueta.** Un botón no
  tiene estado vacío: sin válvula, la regla produce excepciones falsas.
- **Los filtros pasan por el contrato único del catálogo.** El contrato concreto lo
  define el proyecto.
- **Idioma de la interfaz declarado** en la capa de proyecto, y consistente.

## 6 · Must Never

- **Nunca una acción destructiva a un clic de distancia.**
- **Nunca un error sin siguiente paso.** «Algo salió mal» no es un mensaje: es una
  disculpa.
- **Nunca improvisar un componente para un rol que el catálogo ya cubre.**

## 7 · La maqueta

**Una fuente, dos representaciones.** El archivo de catálogo es la fuente —texto,
greppable, en el repositorio, cargable por un agente que necesita saber qué existe antes
de escribir una pantalla—. La maqueta **renderiza esa fuente junto a los componentes
reales**. La maqueta nunca tiene contenido propio: si lo tuviera, sería un tercer sitio
del que diverger.

**Obligatorio hoy:** una ruta de la propia aplicación, en entorno de desarrollo,
generada desde el catálogo. Usa el mismo tema, los mismos tokens y el mismo runtime que
las pantallas reales — si un token se rompe, se rompe también ahí.

> **No exigible todavía:** publicar la maqueta como artefacto estático desde el CI.
> Bajo OPS-07 la evidencia válida es un run externo, así que una maqueta construida por
> la certificación convertiría «el catálogo corresponde al código» en un hecho producido
> fuera del alcance del agente. **Requiere el nivel 2, que no existe.** Queda escrito y
> marcado, no supuesto.

## 8 · Proyectos que ya divergieron

Un proyecto con deuda **no se refactoriza antes de adoptar el estándar**: se congela y
converge.

1. **Se escribe el catálogo con lo que ya hay**, diciendo la verdad: si hay tres tablas,
   entran las tres — una `vigente` y dos `superado por`.
2. **Desde ese commit, la regla de cierre aplica al 100 % para lo nuevo.**
3. **La deuda se paga cuando cada pantalla se toca por otra razón.** No se abre un
   proyecto de migración.

**Dos condiciones de salida, las dos contadas por el verificador:** cero entradas
`superado por` con usos vivos, y los diez roles canónicos cubiertos. Mientras falte
alguna, el proyecto está **en convergencia** y no declara conformidad de interfaz. No es un suspenso: es un estado
declarado, como `pausada` para una misión. Y es medible — `verificadores/catalogo.sh`
cuenta entradas superadas y usos restantes, así que la deuda deja de ser una sensación
y pasa a ser un número.

Congelar el inventario es lo que convierte «sigo corrigiendo» en una lista finita.

## 9 · Mapa de verificabilidad

Seis comprobaciones deterministas, cinco de ellas sobre archivos de texto. Las corre
`verificadores/catalogo.sh`, invocado por el hook local hoy y por el job `certificacion`
cuando exista — **un script, dos llamadores**, para que no haya dos definiciones de qué
significa que el catálogo esté sano.

| | Comprobación |
|---|---|
| 1 | El diff toca el directorio de componentes y no toca el catálogo |
| 2 | Dos entradas `vigente` con el mismo rol |
| 3 | Un rol canónico sin entrada `vigente` |
| 4 | Ficha sin sus campos |
| 5 | `superado por <id>` apuntando a una entrada inexistente |
| 6 | Valor literal de color fuera de los tokens |

**Lo que ninguna máquina comprueba, dicho con nombre:**

- Si un componente es **bueno**.
- **Una tabla escrita a mano dentro de una pantalla**: no pasa por el directorio de
  componentes, así que la regla de cierre no la ve. Hueco abierto a propósito; lo
  detecta una revisión de diff humana.
- Si el `no-usar-cuando` dice la verdad.
- Si los tres estados están **diseñados** o solo **presentes**.
