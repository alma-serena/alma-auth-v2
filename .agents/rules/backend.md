# backend.md — patrón

## Must Always

- **Una responsabilidad por unidad de trabajo.** La unidad que orquesta valida,
  delega y responde; la que ejecuta hace una sola cosa y devuelve datos, nunca una
  respuesta HTTP.
- **Tipos completos.** Firmas y retornos tipados. Un tipo ausente es una decisión no
  tomada.
- **Toda escritura múltiple va en transacción.** Si dos cosas deben ser verdad a la
  vez, el sistema no puede quedar entre medio.
- **Objetos de transferencia inmutables.** Lo que cruza una frontera no se muta al
  otro lado.
- **Proporcionalidad:** un CRUD trivial no necesita la ceremonia completa. La regla
  protege del acoplamiento, no del código corto.

## Must Never

- **Nunca cargar una colección completa sin acotar.** Ni sin filtro, ni sin límite,
  ni dentro de un bucle. El anexo del stack nombra las funciones concretas.
- **Nunca consultar dentro de un bucle.** El N+1 no es un problema de rendimiento:
  es un problema de diseño que aparece como rendimiento.
- **Nunca carga perezosa implícita.** Se declara lo que se necesita; lo no declarado
  falla ruidoso, no lento.
- **Nunca SQL crudo fuera de una clase dedicada a consultas.** Concentrado y
  auditable, o no existe.
- **Nunca traducir excepciones fuera del manejador central.**

## Mapa de verificabilidad

| Regla | Cómo se verifica |
|---|---|
| Tipos completos | analizador estático en el nivel comprometido |
| Colección sin acotar · consulta en bucle | anexo del stack + prueba de humo |
| Carga perezosa implícita | interruptor del ORM en entorno de pruebas |
| Transacción en escritura múltiple | revisión de cierre |
| SQL fuera de clase dedicada | `grep` del anexo |
