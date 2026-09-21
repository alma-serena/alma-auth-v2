# intervencion — un agente corrige el trabajo de otro

> **NO NORMATIVO. No forma parte de v0.1.0.**
>
> Este workflow depende de un prerrequisito que todavía no existe: **certificación
> externa** (OPS-07, nivel 2). Sin un árbitro fuera del alcance de los agentes, un
> protocolo de intervención es *agentes vigilando agentes*, que es exactamente el
> escenario que OPS-07 declara insuficiente. El archivo queda escrito y fechado para
> que el diseño no se pierda, pero **nada de lo que sigue obliga** mientras el
> prerrequisito no esté en pie. Ningún verificador lo comprueba y ningún cierre falla
> por incumplirlo. `[D-C25]`

**Este workflow no viene de ALMA Dev v0.2.1.** El documento maestro se escribió para un
agente por misión con un humano en las puertas. Nace de **D-C17** (el Runtime apunta a
CORE+GOV) y **D-C18** (la corrección se registra para poder medirla), y se rediseñó
decisión por decisión contra un ataque adversarial: **I-1** a **I-4**, reabiertas como
**RI-1** a **RI-4**.

> **Vocabulario M1, con cuidado.** `Intervención` está definida en M1 como *«acción
> humana no prevista por el flujo»*. **Humana.** Lo que un agente hace sobre la salida
> de otro es `Corrección` —modificación de una salida previa— o `Validación`
> —comprobación contra criterio—, nunca `Intervención`. El nombre de este archivo
> describe el momento, no el tipo de evento.

## 0 · El eje del diseño

Una sola regla resuelve las cuatro decisiones, y conviene leerla antes que ninguna:

> **Si no se puede derivar del registro, no dispara, no cierra y no se emite.**

Nada en este workflow se apoya en lo que un agente declara sobre sí mismo, sobre su
progreso o sobre la causa de un fallo. Ni el disparo, ni la condición de salida, ni las
señales de observación. Donde eso no se pudo conseguir, está escrito como límite, no
disimulado como cobertura.

## 1 · Quién la invoca `[I-1 · RI-1]`

Tres vías, y solo tres:

- **El humano**, siempre y sin condición.
- **El ejecutor**, que puede **solicitarla**. La solicitud no es la intervención: queda
  registrada y espera visto bueno.
- **Disparo automático atado a un artefacto de CI**, nunca a una autoevaluación.

**La condición de disparo**, en concreto:

| | |
|---|---|
| Hecho | **N corridas remotas consecutivas** con el mismo test en rojo |
| Identidad | el identificador canónico `archivo::nombre_del_test` |
| Dónde vive el contador | en las corridas remotas, **no** en el registro de misión |
| Qué nunca es | un campo de texto libre, una etiqueta que el agente elige, un «estoy atascado» |

Esto instrumenta **OPS-03** —tres cierres fallidos consecutivos por la misma razón de
fondo— dándole por fin un hecho comprobable donde antes había un juicio. «Estoy
atascado» es autoevaluación y es evadible en las dos direcciones. «El mismo test lleva
tres corridas remotas en rojo» lo comprueba cualquiera, incluido quien no confía en
ninguno de los dos agentes.

> **Límite escrito.** Un disparo atado a CI **solo cubre lo que el CI mide**. Un agente
> que se atasca en algo que ninguna prueba observa no dispara nada, y ese caso sigue
> dependiendo del humano. Lo que no se puede medir, no se automatiza.

## 2 · Qué recibe el interventor `[I-2 · RI-2]`

**Recibe:** el artefacto, la instrucción original, y el **registro de intentos**.

**No recibe: el razonamiento del ejecutor.** La independencia no es una recomendación de
higiene; sostiene una inferencia concreta que aparece en §4. Por eso se implementa en
tres partes, y **ninguna de las tres está bajo el control del agente**:

**2.1 · El razonamiento del ejecutor no entra al repositorio.** Lo que entra es el
registro de intentos con forma de máquina: comando ejecutado, código de salida,
identificador canónico del test que falló. Hechos, no relato.

**2.2 · Un verificador comprueba la forma del registro de misión.** El registro admite
campos estructurados y **no** bloques de razonamiento en prosa. Un log de misión con
prosa de razonamiento **hace fallar el cierre**. Esto es lo que convierte 2.1 de
intención en mecanismo.

**2.3 · El interventor abre sobre un checkout limpio del commit.** Lo decide la
herramienta que abre la sesión, no el agente que entra en ella.

Las tres razones de fondo ya estaban escritas antes del rediseño:

1. **EST-05.** Todo dato no tecleado por un humano es `UNTRUSTED`. La salida de un
   agente lo es por definición, y es peor vector que el texto de un usuario: viene
   estructurada, argumentada y con apariencia de método.
2. **OPS-03** manda abrir el diagnóstico en **sesión limpia**. Pasar la cadena de
   razonamiento hace que la sesión no sea limpia: es el mismo contexto con otro modelo
   dentro.
3. **La regla de oro del proyecto.** Los intentos son registro; el razonamiento es la
   reconstrucción que el ejecutor hace de su propio proceso. La fuente de verdad es el
   registro real, nunca la reconstrucción.

El razonamiento **no se borra**: sigue en el log del ejecutor. Simplemente no entra al
contexto del interventor.

> **Lo que queda sin mecanismo, y se dice.** Si el humano pega el razonamiento del
> ejecutor en la sesión del interventor, la independencia se rompe. No hay forma de
> impedirlo desde dentro del repositorio. Lo que sí ocurre es que la rompe **con su
> firma y queda registrado como decisión suya**, no como un accidente del flujo.

## 3 · Alcance, condición de salida y certificación `[I-3 · RI-3]`

**Devuelve la corrección aplicada**, no un veredicto para que el ejecutor lo aplique.
Devolver la corrección al agente que ya falló N veces sobre ese síntoma crea un bucle:
el disparo existe precisamente porque esa ruta está cerrada para él.

**La autorización es de alcance, no por intervención.** GOV exige autorización **previa**
registrada; pedirla en cada intervención convierte el disparo automático en una
notificación y devuelve al humano al cuello de botella.

### 3.1 · El alcance falla cerrado

> **Sin una lista afirmativa de rutas escrita en la instrucción, el interventor no toca
> nada.** No hay alcance implícito, no hay alcance heredado del ejecutor, y el silencio
> no autoriza. Es la misma cautela que AUTH-09 exige a las *condiciones pre-firmadas*.

El alcance se escribe en la sección «Orden con puertas» de la instrucción, y describe al
**interventor**, no al ejecutor: las puertas de uno no son las del otro. Lo que caiga
fuera del alcance declarado se detiene y pide autorización nueva.

**El test que define el síntoma está fuera del alcance por defecto.** Si la corrección
legítima exige tocarlo, eso cae fuera, se detiene, y la autorización nueva es un acto
humano registrado — no un trámite que el interventor se concede a sí mismo.

### 3.2 · La condición de salida es el identificador, no la suite

La intervención se cierra cuando **el mismo `archivo::nombre_del_test` que disparó**
aparece **presente y en verde** en la corrida remota.

> **Ausente no es verde.** Borrar el caso, marcarlo `skipped` o renombrarlo hace
> desaparecer el identificador de los resultados, y eso **falla**. La suite entera en
> verde no es la condición y nunca la sustituye.

### 3.3 · Quien corrige no certifica, porque la certificación no es suya

La misión tiene **un** cierre —no hay cierre paralelo—, pero ese cierre dejó de ser algo
que un agente escribe y concede: **lo corre el CI remoto y lo ancla el humano al
empujar** (OPS-07, P-R6-bis). «Quien corrige no se certifica a sí mismo» deja así de ser
un *Must Never* de honor y pasa a ser una consecuencia de dónde vive la certificación.

> **Lo que ni esto detecta, y se dice.** Una aserción debilitada *dentro del código de
> producción*, que haga pasar el test por la razón equivocada sin tocar el archivo de
> test, no la detecta ningún mecanismo de este workflow. La detecta una revisión de diff
> humana. Está escrito como límite porque lo es.

## 4 · Señales de observación `[RI-4]`

La intervención alimenta el protocolo de observación (D-C18) **solo con lo derivable**:

| | Señal | De dónde sale |
|---|---|---|
| S-1 | el diff aplicado y el identificador canónico que disparó | derivado del registro; nadie lo redacta |
| S-3 | el modo de misión | `.alma/modo-mision`, declarado antes de la intervención y ya verificado en el gancho local |

**S-2 —la causa raíz: prompt · contexto · modelo— no se emite aquí.** Es una conclusión
del humano en el protocolo de observación, sobre el **conjunto acumulado** de casos, no
sobre uno.

La razón es la consecuencia, no la pureza: S-2 es el campo que decide qué modelos siguen
calificando para el trabajo. Autodeclarado por el interventor, significa que un agente
redacta el registro que descalifica al modelo del otro — y quien clasifica «modelo»
obtiene además el relato más favorable a sí mismo. El dato más caro del sistema sería el
más fácil de escribir a conveniencia.

Dos campos derivados pueden faltar de forma comprobable: si faltan, la herramienta no
corrió. Un juicio no puede faltar de forma comprobable, y por eso no se pide.

> **Lo que esto cuesta.** D-C18 sigue midiendo, pero más lento: la causa raíz deja de
> estar disponible caso a caso y aparece cuando hay volumen suficiente para que un
> humano vea el patrón. La compensación es que un dato lento y verificable sirve para
> descalificar a un modelo; uno rápido y autodeclarado, no.

## 5 · Cuando el interventor también falla `[I-4]`

**Una intervención por síntoma.** Si hace falta una segunda sobre lo mismo, no es
intervención: es escalada, y va al humano. Sin este tope, el disparo automático puede
entrar en bucle — la peor combinación posible de las decisiones anteriores.

**No hay cadena de interventores.** Un tercer agente sabe menos que el segundo, no más, y
agentes corrigiendo agentes sin árbitro externo es el escenario que OPS-07 nombra.

**Artefacto de traspaso** al escalar: síntoma (identificador canónico), qué intentó cada
uno, qué resultado observable dio cada intento, y qué quedó sin explorar. Se produce en
el momento, con todo fresco.

> **El doble fallo es la medición más limpia del protocolo, y depende de §2.** Dos
> ejecutores independientes fallando sobre el mismo síntoma es evidencia fuerte de que
> la causa **no es el modelo**: es el prompt o el contexto. Si el segundo leyó al
> primero, esa inferencia muere — y con ella el dato más limpio que el protocolo de
> D-C18 iba a producir. Por eso la independencia de §2 no es higiene: es el sustrato de
> esta lectura. La evidencia se registra como hallazgo para el humano, que sigue siendo
> quien clasifica.

## Lo que la intervención NO hace

- **No crea un cierre paralelo.** La misión tiene un cierre, lo corre el CI y lo ancla el
  humano.
- **No se certifica a sí misma.** La certificación no está en manos de ningún agente.
- **No toca el ancla.** El push sigue siendo acto humano.
- **No hereda alcance.** Sin lista afirmativa de rutas, no toca nada.
- **No clasifica causas.** Emite hechos derivados; la causa raíz la concluye el humano.
- **No sube el perfil.** Nada de esto es autonomía delegada: no hay confianza acumulada,
  no hay autonomía derivada, y por tanto no es AUTO.
