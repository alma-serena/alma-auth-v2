# migracion-estandar — el estándar subió de versión

Sin este workflow, propagar el estándar a mano produce exactamente la divergencia que
SAD-05 prohíbe. La migración es una misión, con su REQ y su cierre.

## Orden

1. **Anclar la versión nueva** al tag remoto firmado. No a una copia local: un
   checksum contra sí mismo no verifica nada.
2. **Diff contra la versión anclada** en la capa de proyecto. Qué cambió de verdad
   entre la que se tiene y la que se adopta.
3. **Reemplazar los archivos verbatim.** Todos. No se elige cuáles. **Y borrar con
   `git rm` lo que salió del conjunto:** un archivo que pertenecía al andamiaje en la
   versión anterior y ya no está en el manifest nuevo no se queda huérfano —
   `verificadores/andamiaje.sh` lo hace fallar hasta que desaparece, y lo comprueba
   sobre el índice, así que un `rm` a secas no le basta.
4. **Validar la capa de proyecto** contra la versión nueva: ¿alguna regla nueva
   contradice algo declarado en `proyecto-<nombre>.md`?
5. **Revisar las excepciones declaradas.** Cada una vuelve a justificarse o cae. Una
   excepción que nadie revisa deja de ser excepción y pasa a ser costumbre.
6. **Descargar el manifest** del release del tag y dejarlo en `.alma/manifest.sha256`.
   No se calcula localmente: ver [ALMA-UPGRADE.md](https://github.com/alma-serena/alma-standard/blob/main/ALMA-UPGRADE.md),
   «Quién lo genera». Vive en el repositorio del estándar, no en el tuyo.
7. **Actualizar el ancla de versión** en `proyecto-<nombre>.md` y cerrar la misión.

Este orden es la **fuente única**: [ALMA-UPGRADE.md](https://github.com/alma-serena/alma-standard/blob/main/ALMA-UPGRADE.md) especifica
el comando que lo ejecuta y no vuelve a enunciarlo.

## Qué falla el cierre

- Un archivo verbatim modificado localmente.
- Una excepción sin revisar.
- El manifest calculado localmente en vez de descargado del release del tag.
