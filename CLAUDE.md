@AGENTS.md

<!--
Puente obligatorio (EST-01). Claude Code NO lee AGENTS.md por sí solo, y su modo de
falla es cargar CERO instrucciones en silencio, sin error — inaceptable bajo SAD-07.

Es un import, no un symlink: sobrevive a Windows y a runners de CI que no preservan
symlinks, y deja espacio para lineas especificas de Claude debajo.

El check de integridad (OPS-07 n1) verifica que este puente existe y que git guardo
el import, no una copia del contenido.

Cursor, Windsurf y Cline leen AGENTS.md nativamente y no necesitan puente.
Antigravity: verificar en instalacion.
-->
