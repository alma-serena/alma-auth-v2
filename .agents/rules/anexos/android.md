# Anexo — Android / APK

> **Vacío a propósito.** El dueño empezó recientemente a construir APKs y no hay
> todavía una misión real de la que derivar reglas. Escribirlo ahora sería inventar
> un estándar sin uso, que es exactamente lo que ALMA-SAD-02 desaconseja: se puede
> construir por delante de la necesidad, pero nada gradúa sin uso real.

Este archivo existe para que el hueco se **vea**, no para disimularlo. Un anexo
ausente sin explicación se lee como olvido; uno vacío con criterio se lee como
pendiente.

## Qué lo llena, y cuándo

Se escribe **después** de la primera misión real de Android, derivando las reglas de
lo que esa misión haya necesitado de verdad. No antes.

## Qué preguntas tendrá que responder

Cada patrón de `../backend.md`, `../seguridad.md` y `../frontend.md` necesita su
traducción con nombre propio y su forma de encontrarla:

- **Tipos y análisis estático:** qué comprobador, en qué nivel, y en qué punto de CI.
- **Acceso a datos:** cuál es el equivalente de «no cargar la colección completa» y
  de la consulta en bucle, en la capa de persistencia que se use.
- **Secretos en reposo:** dónde viven las claves en un dispositivo que el usuario
  controla físicamente — el modelo de amenaza cambia respecto de un servidor, y esta
  es la diferencia más importante entre este anexo y el de Laravel.
- **Permisos:** los del sistema operativo, que se conceden y revocan fuera de la
  aplicación, no son los permisos de dominio de `../seguridad.md`. Habrá que decir cómo
  se relacionan.
- **Definición de Hecho:** qué comando construye, qué comando prueba, y qué evidencia
  externa certifica — recordando que la firma de un APK es efecto externo.

## Nota de perímetro

Publicar en una tienda es `publicacion` en el catálogo de efecto externo de M1, y
firmar un APK con una clave real es un acto que no se delega. Cuando este anexo se
escriba, esa frontera va primero.

## Lo primero que este anexo debe resolver `[D-C38]`

Tres reglas de `../seguridad.md` están cubiertas **solo** por el anexo Laravel, y en
este stack no las comprueba nada:

- códigos de recuperación con hash lento,
- contraseñas con derivación moderna y comprobación contra filtraciones,
- bloqueo atado a IP **más** cuenta.

Son las primeras filas que este archivo debe traer, con su `grep` o su herramienta. No
son opinables: pertenecen al único archivo del estándar sin excepciones.
