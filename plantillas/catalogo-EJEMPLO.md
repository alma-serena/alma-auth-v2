# Catálogo de componentes — <proyecto>

> Fuente única del catálogo. La maqueta se genera desde este archivo; no se escribe
> contenido en la maqueta. Ver `.agents/rules/diseno.md`.

**Origen:** `<base adoptada> v<version>` · o `desde cero`
**Estado del proyecto:** `conforme` · o `en convergencia`

---

## tabla-registros

rol: tabla
estado: vigente
no-usar-cuando: la vista muestra un solo registro — usar `ficha-registro`
excepciones: ninguna
archivo: resources/views/components/tabla-registros.blade.php

## tabla-legacy-personal

rol: tabla
estado: superado por tabla-registros
no-usar-cuando: siempre — pendiente de retirar, quedan usos vivos
excepciones: mantiene su propio filtro embebido hasta que se migren sus tres pantallas
archivo: resources/views/components/tabla-legacy-personal.blade.php

## filtro-listado

rol: filtro
estado: vigente
no-usar-cuando: hay un único criterio fijo — no se filtra, se consulta
excepciones: ninguna
archivo: resources/views/components/filtro-listado.blade.php
