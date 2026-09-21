# paquetes.md — patrón

## Must Always

- **Dependencia unidireccional.** La aplicación importa del paquete; el paquete
  jamás conoce el espacio de nombres de la aplicación. Integración solo por
  contratos, configuración publicada, eventos y enlaces del contenedor.
- **Cada paquete versiona y registra sus cambios** por su cuenta.
- **Los contratos se documentan en el README del paquete.** Un contrato que solo
  existe en el código no es contrato: es implementación.
- **Los tests del paquete corren aislados del consumidor.**
- **`AGENTS.md` anidado en el paquete** como refuerzo de alcance.

## Must Never

- **Nunca copiar código entre proyectos.** Si dos lo necesitan, es un paquete. Las
  copias divergen; es cuestión de cuándo.
- **Nunca modificar el paquete desde una misión de aplicación.** Legible siempre,
  inmodificable siempre.
- **Nunca graduar a v1.0 sin un consumidor real en uso.** Diseñar por delante de la
  necesidad es legítimo; declararlo maduro sin uso, no.

## Pausa y bifurca

Descubrir en misión de aplicación que el paquete debe cambiar **no** autoriza a
tocarlo. Se pausa limpio la misión con su plantilla de pausa, se abre misión de
paquete con su REQ y su test de regresión, y se retoma. El diagnóstico ya hecho hace
que ese REQ nazca informado.

**Emergencia:** una vulnerabilidad descubierta en el paquete durante una misión de
aplicación se corrige por arreglo urgente con puerta dedicada. Ni se aborta la
misión ni se salta la frontera.

## Pase de a pares

Antes de v1.0, cada característica se confronta contra cada otra buscando
interacciones. Las interacciones no analizadas de a pares fueron la fuente de la
mayoría de los hallazgos críticos de la evaluación externa. El entregable del pase es
una matriz, no una nota.
