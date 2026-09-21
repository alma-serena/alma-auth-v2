# Reglas por dominio

Verbatim, con checksum en el manifest. Carga perezosa: el agente lee la que toca su
tarea, no las cinco.

**Cuál toca, por lo que la misión escribe** — y si toca varias, se leen varias:

| Si la misión escribe en… | lee |
|---|---|
| lógica de servidor, acceso a datos, migraciones | `backend.md` |
| dentro de `packages/` | `paquetes.md` |
| autenticación, permisos, secretos, tokens, perímetro | `seguridad.md` **siempre, además de la que toque** |
| interfaz, componentes, estado de cliente | `frontend.md` |
| pantallas nuevas, **componentes**, textos visibles, flujos destructivos | `diseno.md` |

Y **siempre** el anexo del stack declarado en `proyecto-<nombre>.md`. `seguridad.md`
nunca se omite por estar tocando otra cosa: si el diff roza su territorio, aplica.

**Patrón arriba, stack abajo.** Cada regla enuncia el patrón universal; los anexos
de `anexos/` lo aterrizan en un stack concreto, con nombres propios que un `grep` o
un linter puedan encontrar. Decisión **V-2 / D-C21**.

La razón: v0.2.1 ya había establecido el principio —«el estándar fija el patrón, no
el stack»— pero lo aplicó solo a `frontend.md`. Los otros cuatro archivos seguían
siendo Laravel de principio a fin, y eso deja fuera a cualquier proyecto que no lo
sea. Separar patrón de anexo conserva las dos cosas: universalidad y dientes.

**Los anexos:** `anexos/laravel.md` es el principal y está escrito;
`anexos/android.md` existe vacío a propósito, con sus criterios de llenado, y espera a
la primera misión real sobre ese stack.
