# proyecto-<nombre>.md

> Único archivo editable de la capa de proyecto (OPS-05). Extiende el estándar,
> **nunca lo contradice**. Fuera de su alcance: `../.agents/rules/seguridad.md` y reducir la DoD.

## Ancla de versión

Estándar: `alma-standard v0.0.0` · tag remoto: `<tag>`

## Identidad

Qué es este proyecto, en dos líneas. Para quién y con qué propósito.

## Stack

Lenguaje y versión mínima · framework · base de datos · frontend, si lo hay.
Dependencias que un agente necesita conocer antes de escribir la primera línea.

## Comandos ejecutables

```
setup:  <comando>
correr: <comando>
tests:  <comando>
lint:   <comando>
```

Deben funcionar copiados y pegados. Un comando que no corre es peor que ninguno.

## Catálogo de interfaz

Campos que lee `verificadores/catalogo.sh`. **La ausencia de `interfaz:` falla:** no se
interpreta el silencio.

```
andamiaje: AGENTS.md, CLAUDE.md, METODOLOGIA.md, .agents/, .githooks/, .github/, verificadores/, proyecto-*.md, .alma/, docs/
anexo: laravel
interfaz: si
componentes: resources/views/components, app/Livewire
catalogo: docs/catalogo.md
tokens: resources/css/tokens.css
maqueta: /dev/catalogo
origen: desde cero
```

`andamiaje:` es la **lista blanca**: lo que el estándar aportó y no es producto. Todo lo
demás es producto — así `genesis` no depende de una lista de extensiones que siempre deja
algo fuera.

`anexo:` dice qué anexo de stack aplica — de ahí salen las raíces conocidas y las rutas
secretas conocidas. `ninguno` es válido si tu stack aún no tiene anexo, y entonces el
verificador **lo dice**: no puede comprobar lo que no tiene con qué comparar.

`componentes:` es una **lista** de raíces. Declarar una y dejar otra fuera era la fuga:
el componente se escribía en la raíz no declarada y ninguna comprobación se disparaba.

`interfaz: no` es una respuesta válida y cierra la comprobación. Declararlo en falso es
una mentira humana, y queda registrada como tal.

## Perímetro

```
rutas_secretas: .env, .env.local, storage/claves/
herramientas: <las que la misión puede usar; lo no declarado no está disponible>
```

**Lo que el estándar comprueba de `rutas_secretas`:** que esas rutas no entren al
repositorio y estén ignoradas, y que ninguna ruta secreta **conocida del anexo** quede
fuera de la lista. Que el agente no pueda leerlas es del Runtime y del entorno — lo que
se le entrega—, **no de este archivo**.

**Sobre `herramientas`:** el efecto externo **se deriva** de ellas y no se afirma. Y un
intérprete de propósito general —`bash`, `sh`, `python`, `node`— **deriva las cinco
clases**, no ninguna: si te entregaron una shell, te entregaron todo efecto posible.

## Excepciones declaradas

Una por bloque. Sin este formato no es excepción: es incumplimiento.

- **Qué** se excepciona · **por qué** · **qué lo compensa** · **cuándo se revisa**

## Notas de dominio

Vocabulario propio, invariantes de negocio, cosas que un agente no puede deducir
leyendo el código.
