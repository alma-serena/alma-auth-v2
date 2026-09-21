#!/usr/bin/env bash
# Fin de linea de lo que el andamiaje EJECUTA o PARSEA. Un verificador, dos
# llamadores (D-C32): lo corre el hook y lo corre el piso de `certificacion`.
#
# H-H7 · El estandar normaliza a LF en SU repositorio, y por eso este defecto no
# puede reproducirse ahi: en el arbol del estandar la comprobacion no falla nunca.
# El daño aparece del otro lado. Por eso NO se exige un archivo —.gitattributes es
# del consumidor, con contenido propio, y sobrescribirlo repite el daño que el paso 1
# de la instalacion documenta—: se comprueba el EFECTO, y quien lo consiga de otro
# modo pasa igual (D-C46).
#
# Con `bash ./x.sh` —la forma que D-C47 fijo para los dos niveles— el CRLF casi nunca
# grita: `cat .alma/modo-mision` devuelve "aplicacion\r", ninguna rama del case
# coincide, y el hook acusa un modo invalido sobre un archivo que se ve correcto.
set -u
fallos=0
echo "== fin de linea del andamiaje =="

if ! git rev-parse --git-dir >/dev/null 2>&1; then
  echo "  FALLA · fuera de un repositorio git: no se puede mirar el indice, y no se"
  echo "           dice ok sin mirar"
  echo "lineas: 1 FALLA(S)"; exit 1
fi
cd "$(git rev-parse --show-toplevel)" || exit 1

for f in $(git ls-files); do
  case "$f" in
    *.sh|.githooks/*|.alma/modo-mision|proyecto-*.md) ;;
    *) continue ;;
  esac
  if git show ":$f" 2>/dev/null | grep -qU "$(printf '\r')"; then
    echo "  FALLA · CRLF en el indice: $f"
    fallos=$((fallos+1))
  fi
done

if [ "$fallos" -gt 0 ]; then
  echo
  echo "  El andamiaje ejecuta y parsea estos archivos. Con CRLF, las comparaciones"
  echo "  de cadena dejan de coincidir sin decir por que."
  echo "  Se arregla en TU repositorio, normalizando a LF —por ejemplo con"
  echo "  '* text=auto eol=lf' en .gitattributes— y volviendo a indexar:"
  echo "      git add --renormalize ."
  echo "lineas: $fallos FALLA(S)"; exit 1
fi
echo "  ok    · los archivos que el andamiaje ejecuta o parsea estan en LF"
echo "lineas: sin fallas"; exit 0
