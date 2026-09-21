# reversion — deshacer una misión ya commiteada

Un `git revert` **no es ceremonia libre**. Revertir también puede romper.

## Orden

1. **REQ de reversión**, que enlaza la misión revertida y documenta **por qué**.
2. Ejecutar la reversión.
3. **Pasar la Definición de Hecho completa.** La misma que pasó la misión original.
4. Cerrar con la tabla de estado.

## Trazabilidad simétrica

El cierre registra lo que entró; la reversión registra lo que salió. Un historial que
solo cuenta las incorporaciones miente por omisión: dice qué se intentó, no qué quedó.

## Qué no es reversión

- Deshacer trabajo **no commiteado**: eso es descartar, y no necesita este workflow.
- Corregir un error dentro de la misma misión: eso es la misión.
- Revertir para «probar algo»: si hay que probar, es una rama.
