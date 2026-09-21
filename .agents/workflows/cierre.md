# cierre — terminar una misión

## Orden invariante

**verificar → respaldar → documentar → commitear.** No se reordena. Documentar antes
de verificar produce documentación de algo que no funciona.

El agente **ejecuta** la Definición de Hecho. No la describe, no la promete: la corre.

## Qué cuenta como evidencia

Un **run externo verificable**. Output pegado por el agente **no es evidencia** — es
el agente diciendo que corrió algo. Mientras no exista CI remoto hay prevención
local, no certificación: se declara así y no se finge lo contrario.

## Comprobaciones

- **Diff contra el modo de misión.** En misión de aplicación, si el diff toca
  `packages/`, el cierre falla. En misión de paquete, si toca `app/`, falla.
- **REQ presente**, o retroactivo si el modo lo permite.
- **Tests y línea base declarados**: qué corrió, con qué resultado, contra qué
  número anterior. Un «todo verde» sin cifra no es un cierre.
- **Cambios en `tests/`**: admisibles sin REQ solo si el árbol sintáctico de las
  aserciones no cambió. Renombrar un método o reordenar imports, sí; alterar qué se
  asevera o mover aserciones entre tests renombrados, no — se llame como se llame.
- **Barrido de secretos** antes de commitear, y **sobre el árbol**, no sobre el diff:
  `bash verificadores/secretos.sh`. El hook ya miró lo escenificado en cada commit;
  esto es lo que atrapa un secreto que entró **antes** —de que el hook existiera, por
  `--no-verify`, o en el historial previo de un repo que adopta el estándar después—.
  Corre una vez por misión y no una por commit, que es la cadencia que lo caro admite
  sin enseñar `--no-verify` `[D-C44]`. El mismo verificador lo corre el job
  `certificacion`: el local previene mientras arreglarlo cuesta un `rebase`, el remoto
  certifica cuando ya cuesta una rotación.

## Commit

Mensaje estructurado: qué cambió, por qué, y de qué REQ o hallazgo salió. Un commit
sin origen registrado es historia sin trazabilidad.

## Ancla

El push es **acto humano**. El agente prepara el commit y se detiene ahí. La
declaración de conformidad exige ancla externa, y el ancla no se delega.

## Tabla de estado

El cierre deja esta tabla. La estructura es fija: sin ella, cada misión la inventa y
retomar vuelve a ser reconstruir.

```markdown
| Campo | Contenido |
|---|---|
| Misión | id y una línea |
| Modo | aplicacion · paquete · genesis · estandar |
| REQ | id, o `retroactivo` en génesis |
| Hecho | qué quedó terminado y verificado |
| Abierto | qué queda, en orden |
| Verificación | comando corrido · resultado · cifra anterior |
| Archivos | rutas tocadas |
| Puertas | cuáles se cruzaron y quién aprobó |
| Ejecutor | qué agente y qué versión ejecutó la misión |
| Efectos | herramientas usadas y clases de efecto que se **derivan** de ellas; `ninguno` solo si no se usó ninguna |

> **Si la misión usó un intérprete, el campo registra el alcance autorizado, no una
> derivación.** Cualquier intérprete —`bash`, `php`, `pwsh`, `artisan`, `node`— produce
> los cinco efectos, y ninguna lista de nombres los cubre a todos (D-C46). Lo que se
> anota es **qué autorizó el humano al abrir**, con su nombre. `Efectos | ninguno` solo
> es cierto si no se usó ninguna herramienta con efecto posible.
| Siguiente | la primera acción de quien retome |
```

## Pausa

Una misión que se pausa **no** se cierra: se marca `pausada` y se llena la plantilla
de pausa — avance, pendientes, archivos tocados, siguiente paso. Es un checkpoint con
documentación mínima, sin puerta de cierre completa.
