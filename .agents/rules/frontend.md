# frontend.md — patrón

El estándar fija el patrón, no el stack. La librería concreta es capa de proyecto.

## Must Always

- **Tipado estricto.** Propiedades tipadas, sin escapes al sistema de tipos.
- **Estructura canónica declarada** en la capa de proyecto, y respetada.
- **Mecanismo de caché para datos de servidor** — biblioteca de consultas o acciones
  de servidor. Cuál, lo decide el proyecto.
- **El guardián de permisos es solo experiencia de usuario:** oculta y deshabilita.
  La autorización real vive en el backend, siempre.

## Must Never

- **Nunca un tipo comodín ni un silenciador del comprobador de tipos.** Si el tipo no
  se sabe, el diseño no está terminado.
- **Nunca efectos para traer datos de servidor.** Es el antipatrón en cascada: hace
  daño mucho antes de que la lentitud lo delate.
- **Nunca confiar en el guardián de permisos como control de acceso.**

## Mapa de verificabilidad

| Regla | Cómo se verifica |
|---|---|
| Tipado estricto · sin comodines | comprobador de tipos en CI + `grep` del anexo |
| Efectos para fetch | `grep` del anexo |
| Guardián solo UX | prueba de humo de API con usuario sin permiso |
