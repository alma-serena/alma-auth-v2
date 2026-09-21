# diagnostico — encontrar por qué algo no funciona

## Orden

1. **Síntoma**, escrito como se observa. No como se supone que es.
2. **Leer el código real.** No deducir, no recordar, no asumir que el nombre del
   archivo describe su contenido.
3. **Causa raíz con `archivo:línea`.** Un diagnóstico sin coordenada no es
   diagnóstico: es una hipótesis.
4. **Corregir**, o emitir veredicto si la corrección no corresponde a esta misión.

## La frontera, en diagnóstico

`packages/` es **legible siempre, inmodificable siempre**. Leer para diagnosticar
nunca fue el problema — la dependencia unidireccional lo exige. Editar sin contexto
del núcleo sí, y sigue bloqueado.

`Contracts/` y el README del paquete son **interfaz de lectura garantizada**: están
ahí para ser leídos desde fuera.

**Si la causa raíz vive en el paquete:** veredicto «causa en paquete → pausa y
bifurca». Se pausa limpio, se abre misión de paquete, y el REQ nace informado porque
la lectura ya está hecha.

## Escalamiento

Tres cierres fallidos consecutivos por la misma razón de fondo — el mismo test rojo,
el mismo N+1 — **aunque el intento haya variado cada vez**: detener, documentar y
abrir este workflow en **sesión limpia**.

La distinción importa: la regla no es «el mismo arreglo tres veces», porque eso no se
dispara nunca — un agente prueba A, luego B, luego C. Es el **síntoma** el que
persiste, no el intento.

## Tabla de síntomas

Se **genera automáticamente** en el cierre de cada diagnóstico. No se mantiene a
mano: una tabla que depende de que alguien se acuerde de actualizarla es documentación
que caduca en silencio.
