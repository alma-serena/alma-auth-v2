# instruccion — abrir una misión

## Orden

1. **Leer antes de escribir.** El código real, no lo que se supone que dice. Nunca
   inventar una estructura, una API o un nombre que se pueda verificar leyendo.
2. **Tipificar la misión:** paquete · aplicación · génesis · estándar. El modo se
   declara al abrir y condiciona qué está permitido tocar.
3. **Anclar al REQ.** Toda misión nace de un REQ. Si no existe, se crea antes — salvo
   en modo génesis, donde se genera retroactivo en el cierre.
4. **Redactar en la estructura fija.** Sin excepciones.
5. **Recorrer el checklist** antes de entregar la instrucción.

## Estructura fija

```
## Contexto
Qué existe hoy, con rutas reales. Lo que el agente no puede deducir leyendo.

## Cambios
Qué debe quedar distinto al terminar. Verificable, no aspiracional.

## Fuera de alcance
Qué NO se toca. La sección más importante y la que más se omite.

## Orden con puertas
Los pasos, y en cuáles hay que detenerse a esperar aprobación.

## Cierre
Qué se ejecuta para verificar. Comandos concretos — los de `proyecto-<nombre>.md`,
sección **Comandos ejecutables**, copiados tal cual. No referencias a `cierre.md`, y
no comandos de ejemplo: si no existen en este proyecto, el modo es génesis.

## Notas
Contexto que no cabe arriba.
```

## Checklist

- [ ] ¿El modo de misión está declarado?
- [ ] ¿**Fuera de alcance** está escrito y es específico? «No tocar otras cosas» no
      es fuera de alcance: es un deseo.
- [ ] ¿Hay un solo objetivo verificable? Si hay dos, son dos misiones.
- [ ] ¿Los comandos de cierre están copiados de la capa de proyecto y funcionan?
      **En modo génesis no hay capa de proyecto:** se declara `N/A` y se escriben los
      comandos que se proponen. Declarar N/A es cumplir el ítem; saltarlo, no.
- [ ] ¿Alguna de las cinco puertas de OPS-01 se cruza en el camino? ¿Está marcada?
- [ ] En una funcionalidad: **¿hay acciones candidatas al registro de críticas?**
      Pregunta obligatoria, no opcional.

## Dónde vive

`docs/req/<REQ-id>.md` y `docs/misiones/<MIS-id>.md`, salvo que la capa de proyecto
declare otra cosa. **Estaba sin decir**, y en el primer recorrido real hubo que
inventarlo — el estándar prohíbe inventar estructura verificable, y aquí no había nada
que verificar.

## Modo génesis

Directorio vacío: el agente propone la estructura desde el estándar sin exigir
archivo previo. La regla «nunca inventar» no aplica al andamiaje inicial — no hay
nada que leer. Sí aplica en cuanto exista la primera línea.
