#!/usr/bin/env bash
# Nada sobra bajo las raices que el manifest cubre. Un verificador, dos llamadores:
# lo corre el hook y lo corre el piso de `certificacion`.
#
# El manifest enumera lo que el estandar aporta. Si bajo una de sus raices aparece un
# archivo que el manifest no lista, o es basura de un upgrade anterior —un verificador
# que dejo de pertenecer y nadie borro— o es una edicion de la capa de proyecto en
# territorio del estandar. Las dos cosas son la divergencia que SAD-05 prohibe.
#
# EXCEPCION DECLARADA, una sola:
#   Que: `.github/workflows/` admite archivos fuera del manifest.
#   Por que: GitHub fija esa ruta; un proyecto no puede poner su CI en otro sitio.
#   Que lo compensa: los workflows del estandar SI estan en el manifest, y el nivel 1
#     comprueba su integridad. Lo que se admite es sumar, no alterar.
#   Cuando se revisa: si GitHub permite algun dia declarar workflows fuera de esa ruta.
set -u
fallos=0
echo "== nada sobra bajo el andamiaje =="
if ! git rev-parse --git-dir >/dev/null 2>&1; then
  echo "  FALLA · fuera de un repositorio git"; echo "andamiaje: 1 FALLA(S)"; exit 1
fi
cd "$(git rev-parse --show-toplevel)" || exit 1
if [ ! -f .alma/manifest.sha256 ]; then
  echo "  aviso · sin manifest no hay con que comparar. Corre 'alma:upgrade': lo DESCARGA"
  echo "          del release del tag (D-C48). Esta comprobacion queda pendiente, no ok."
  echo "andamiaje: sin fallas (1 aviso)"; exit 0
fi
listados="$(awk '{ $1=""; sub(/^[[:space:]]+/,""); print }' .alma/manifest.sha256)"
raices="$(printf '%s\n' "$listados" | sed 's#/.*##' | sort -u)"
for r in $raices; do
  [ -d "$r" ] || continue
  for f in $(git ls-files -- "$r"); do
    case "$f" in .github/workflows/*) continue ;; esac
    printf '%s\n' "$listados" | grep -qxF "$f" || {
      echo "  FALLA · '$f' esta bajo el andamiaje y el manifest no lo lista"
      fallos=$((fallos+1)); }
  done
done
if [ "$fallos" -gt 0 ]; then
  echo
  echo "  O sobro de un upgrade anterior —un archivo que dejo de pertenecer— o es una"
  echo "  edicion de tu proyecto en territorio del estandar. Lo primero se borra; lo"
  echo "  segundo va a proyecto-<nombre>.md, el unico archivo editable de esa capa."
  echo "andamiaje: $fallos FALLA(S)"; exit 1
fi
echo "  ok    · nada sobra bajo las raices del manifest"
echo "andamiaje: sin fallas"; exit 0
