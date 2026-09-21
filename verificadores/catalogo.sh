#!/usr/bin/env bash
# Verificador del catalogo de interfaz. Un verificador, dos llamadores (D-C32):
#   --diff  · lo invoca el hook. AVISA, no bloquea (D-C44).
#   sin arg · lo invoca el job `certificacion`. BLOQUEA.
#
# Ronda v0.2.0:
#   D-C40 · indice INVERSO: todo archivo bajo una raiz de componentes tiene ficha.
#           Antes se comprobaba que el catalogo estuviera en el diff, y eso se
#           cumplia con una linea en blanco, o escribiendo en una raiz no declarada,
#           o dejandolo al CI, que llama sin --diff y nunca lo corria.
#   D-C41 · los estados que eximen (`genesis`, `interfaz: no`) tienen condicion de
#           salida comprobable. Entrar era legitimo; no salir nunca, no.
#   H-VER-1/4 · sin archivo temporal de nombre fijo; anclado a la raiz del repo.

set -u
fallos=0; avisos=0
err()   { echo "  FALLA · $*"; fallos=$((fallos+1)); }
aviso() { echo "  aviso · $*"; avisos=$((avisos+1)); }
ok()    { echo "  ok    · $*"; }

RAIZ_ESTANDAR="${RAIZ_ESTANDAR:-.}"
CON_DIFF=0
[ "${1:-}" = "--diff" ] && CON_DIFF=1

echo "== catalogo de interfaz =="

raiz_repo="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [ -n "$raiz_repo" ]; then
  cd "$raiz_repo" || { echo "  FALLA · no se pudo anclar a la raiz del repo"; exit 1; }
fi

campo()  { sed -n "s/^$1:[[:space:]]*//p" "$2" | head -1; }
bloque_anexo() { # $1 marcador, $2 archivo
  awk -v ini="$1:inicio" -v fin="$1:fin" '$0 ~ ini {f=1;next} $0 ~ fin {f=0} f' "$2" \
    | tr -d ' \t' | grep -v '^$'
}
# D-C46 · LISTA BLANCA. Se enumera lo permitido —el andamiaje— y todo lo demas es
# producto. La version anterior enumeraba trece extensiones de codigo y dejaba fuera
# .dart, .cs, .rs, .xml: el hueco no se cerraba, se mudaba al primer stack no listado.
# Andamiaje por defecto si el proyecto no lo declara: lo que aporta el estandar.
# El andamiaje por defecto es TODO lo que el estandar aporta. Se escribe completo aqui
# y el dia que exista el manifest de OPS-07 se deriva de el en vez de listarse.
# (Al estrenar esta lista faltaban ALMA-UPGRADE.md y RAIZ-DE-CONFIANZA.md, y el
#  verificador fallo sobre el arbol del propio estandar: la lista blanca tambien
#  necesita una fuente derivable, no memoria.)
ANDAMIAJE_RESPALDO='AGENTS.md CLAUDE.md METODOLOGIA.md INSTALACION.md CHANGELOG.md ALMA-UPGRADE.md RAIZ-DE-CONFIANZA.md .agents .github .githooks verificadores plantillas'
# Lo que es andamiaje en cualquier proyecto, venga o no del estandar.
ANDAMIAJE_EXTRA='.git .alma docs .gitignore .gitattributes README.md LICENSE'
# D-C48 · DERIVACION. Si el proyecto tiene manifest, el andamiaje que aporta el
# estandar se lee de el en vez de recordarse. Eso cierra el hueco que esta misma
# lista confesaba mas arriba: una lista escrita de memoria olvida lo que no se mira.
ANDAMIAJE_ESTANDAR="$ANDAMIAJE_RESPALDO"
if [ -f .alma/manifest.sha256 ]; then
  derivado="$(awk '{ $1=""; sub(/^[[:space:]]+/,""); print }' .alma/manifest.sha256 \
              | sed 's#/.*##' | grep -v '^$' | sort -u | tr '\n' ' ')"
  [ -n "$derivado" ] && ANDAMIAJE_ESTANDAR="$derivado"
fi
ANDAMIAJE_DEF="$ANDAMIAJE_ESTANDAR $ANDAMIAJE_EXTRA"
hay_producto() {
  lista="${1:-$ANDAMIAJE_DEF}"
  find . -mindepth 1 -not -path './.git' -not -path './.git/*' 2>/dev/null \
  | sed 's#^\./##' | while IFS= read -r p; do
      [ -z "$p" ] && continue
      case "$p" in proyecto-*.md) continue ;; esac
      es_andamiaje=0
      for a in $lista; do
        a="${a%/}"; a="${a%,}"
        case "$p" in "$a"|"$a"/*) es_andamiaje=1; break ;; esac
        case "$a" in proyecto-\*.md) case "$p" in proyecto-*.md) es_andamiaje=1; break ;; esac ;; esac
      done
      [ $es_andamiaje -eq 0 ] && [ -f "$p" ] && { echo "$p"; break; }
    done | head -1
}

# --- 0 · capa de proyecto y estados que eximen ------------------------------
PROY="$(ls proyecto-*.md 2>/dev/null | head -1 || true)"
MODO="$(cat .alma/modo-mision 2>/dev/null || echo '')"

if [ -z "$PROY" ]; then
  if [ -n "$(hay_producto)" ]; then
    err "no hay proyecto-<nombre>.md y el arbol YA tiene archivos que no son andamiaje."
    err "       'genesis' describe un arbol vacio: su condicion de salida se cumplio (D-C41)."
    echo "catalogo: $fallos FALLA(S)"; exit 1
  fi
  aviso "sin proyecto-<nombre>.md y sin codigo de producto: genesis legitimo"
  echo "catalogo: sin fallas ($avisos aviso/s)"; exit 0
fi
ANDAMIAJE="$(campo andamiaje "$PROY" | tr ',' ' ')"
if [ "$MODO" = "genesis" ] && [ -n "$(hay_producto "$ANDAMIAJE")" ]; then
  err "modo 'genesis' declarado sobre un arbol con archivos fuera del andamiaje (D-C41/D-C46)."
fi

ANEXO="$(campo anexo "$PROY")"
[ -z "$ANEXO" ] && err "'$PROY' no declara 'anexo:' (nombre del anexo de stack, o 'ninguno')"
ARCH_ANEXO=""
if [ -n "$ANEXO" ] && [ "$ANEXO" != "ninguno" ]; then
  ARCH_ANEXO="$RAIZ_ESTANDAR/.agents/rules/anexos/$ANEXO.md"
  [ -f "$ARCH_ANEXO" ] || { err "el anexo declarado no existe: $ARCH_ANEXO"; ARCH_ANEXO=""; }
fi

raices_conocidas=""
if [ -n "$ARCH_ANEXO" ]; then
  raices_conocidas="$(bloque_anexo 'raices-componentes' "$ARCH_ANEXO")"
elif [ "$ANEXO" = "ninguno" ]; then
  aviso "anexo 'ninguno': no hay raices conocidas con que comparar. No se puede"
  aviso "        comprobar si falta declarar una raiz; queda a revision humana."
fi

INTERFAZ="$(campo interfaz "$PROY")"
case "$INTERFAZ" in
  no)
    # D-C41 · la salida temprana va DESPUES de comprobar raices conocidas.
    for r in $raices_conocidas; do
      [ -d "$r" ] && err "'interfaz: no' con la raiz de componentes '$r' presente en el arbol"
    done
    if [ $fallos -eq 0 ]; then
      ok "el proyecto declara que no tiene interfaz, y ninguna raiz conocida existe"
      echo "catalogo: sin fallas${avisos:+ ($avisos aviso/s)}"; exit 0
    fi
    echo "catalogo: $fallos FALLA(S)"; exit 1 ;;
  si) : ;;
  "") err "'$PROY' no declara 'interfaz: si|no'. La ausencia no se interpreta."
      echo "catalogo: $fallos FALLA(S)"; exit 1 ;;
  *)  err "valor invalido de 'interfaz': '$INTERFAZ'"
      echo "catalogo: $fallos FALLA(S)"; exit 1 ;;
esac

DIRS_COMP="$(campo componentes "$PROY" | tr ',' ' ')"
ARCH_CAT="$(campo catalogo "$PROY")"
ARCH_TOK="$(campo tokens "$PROY")"
[ -z "$DIRS_COMP" ] && err "'$PROY' declara interfaz y no declara 'componentes:'"
[ -z "$ARCH_CAT" ]  && err "'$PROY' declara interfaz y no declara 'catalogo:'"
[ -z "$ARCH_TOK" ]  && err "'$PROY' declara interfaz y no declara 'tokens:'"
[ $fallos -gt 0 ] && { echo "catalogo: $fallos FALLA(S)"; exit 1; }

for d in $DIRS_COMP; do
  [ -d "$d" ] || err "raiz de componentes declarada que no existe: $d"
done
[ -f "$ARCH_CAT" ] || err "el archivo de catalogo no existe: $ARCH_CAT"
[ $fallos -gt 0 ] && { echo "catalogo: $fallos FALLA(S)"; exit 1; }

# --- 0b · raices conocidas del anexo no declaradas ---------------------------
for r in $raices_conocidas; do
  if [ -d "$r" ]; then
    echo " $DIRS_COMP " | grep -q " $r " \
      || err "el anexo '$ANEXO' declara la raiz '$r', existe en el arbol y no esta en 'componentes:'"
  fi
done

# --- entradas del catalogo --------------------------------------------------
ids="$(grep -E '^## ' "$ARCH_CAT" | sed 's/^##[[:space:]]*//')"
[ -z "$ids" ] && err "el catalogo no tiene ninguna entrada"
bloque() { awk -v id="## $1" '$0==id{f=1;next} /^## /{f=0} f' "$ARCH_CAT"; }
val_de() { bloque "$1" | sed -n "s/^$2:[[:space:]]*//p" | head -1; }

# --- 1 · INDICE INVERSO · todo archivo bajo una raiz tiene ficha (D-C40) -----
fichados="$(for id in $ids; do val_de "$id" archivo; done | sed 's#^\./##' | sort -u)"
for d in $DIRS_COMP; do
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    f="${f#./}"
    printf '%s\n' "$fichados" | grep -qx "$f" \
      || err "sin ficha en el catalogo: $f"
  done <<EOF
$(find "$d" -type f -not -name '.*' 2>/dev/null)
EOF
done

# --- 4 · ficha completa ------------------------------------------------------
for id in $ids; do
  for c in rol estado no-usar-cuando excepciones archivo; do
    [ -z "$(val_de "$id" "$c")" ] && err "entrada '$id': falta el campo '$c'"
  done
  a="$(val_de "$id" archivo)"
  [ -n "$a" ] && [ ! -f "$a" ] && err "entrada '$id': el archivo declarado no existe ($a)"
done

# --- 2 · un solo vigente por rol --------------------------------------------
dups="$(for id in $ids; do
          [ "$(val_de "$id" estado)" = "vigente" ] && val_de "$id" rol
        done | sort | uniq -d)"
for r in $dups; do err "dos o mas entradas 'vigente' para el rol '$r'"; done

# --- 3 · cobertura de roles canonicos ---------------------------------------
# D-C45 · el piso de diez roles es condicion de SALIDA de `en convergencia`, no
# puerta de entrada. Exigirlo desde el primer commit obligaba a inventar componentes
# que el producto no usa, y era el punto donde un proyecto con historia abandonaba.
# La ausencia de 'Estado del proyecto' se lee como `en convergencia`: no declararse
# es no reclamar nada, y conformidad es lo que se reclama.
EST_PROY="$(sed -n 's/.*Estado del proyecto:[^`]*`\([a-z ]*\)`.*/\1/p' "$ARCH_CAT" | head -1)"
[ -z "$EST_PROY" ] && EST_PROY="en convergencia"
DIS="$RAIZ_ESTANDAR/.agents/rules/diseno.md"
[ -f "$DIS" ] || DIS="$raiz_repo/.agents/rules/diseno.md"
faltan=0
if [ -f "$DIS" ]; then
  for r in $(bloque_anexo 'roles-canonicos' "$DIS"); do
    hay=0
    for id in $ids; do
      [ "$(val_de "$id" rol)" = "$r" ] && [ "$(val_de "$id" estado)" = "vigente" ] && hay=1
    done
    if [ $hay -eq 0 ]; then
      faltan=$((faltan+1))
      if [ "$EST_PROY" = "conforme" ]; then
        err "'conforme' declarado y el rol canonico '$r' no tiene entrada vigente"
      fi
    fi
  done
  [ $faltan -gt 0 ] && [ "$EST_PROY" != "conforme" ] \
    && aviso "convergencia: faltan $faltan rol(es) canonico(s) para poder declararse conforme"
else
  aviso "no se encontro diseno.md: no se pudo comprobar la cobertura de roles"
fi

# --- 5 · integridad de 'superado por' y deuda -------------------------------
superadas=0
for id in $ids; do
  e="$(val_de "$id" estado)"
  case "$e" in
    "superado por "*)
      superadas=$((superadas+1))
      destino="${e#superado por }"
      printf '%s\n' "$ids" | grep -qx "$destino" \
        || err "entrada '$id': 'superado por $destino' no existe en el catalogo"
      # D-C44 · el conteo de usos recorre el arbol: solo en modo completo.
      if [ $CON_DIFF -eq 0 ]; then
        a="$(val_de "$id" archivo)"; base="$(basename "${a%.*}")"
        usos="$(grep -rl "$base" . --exclude-dir=.git --exclude="$(basename "$ARCH_CAT")" 2>/dev/null \
                | grep -v "^\./$a$" | wc -l | tr -d ' ')"
        [ "$usos" -gt 0 ] && aviso "deuda: '$id' superada, $usos archivo(s) todavia la usan"
      fi
      ;;
    vigente|"en revision"|"en revisión") : ;;
    *) err "entrada '$id': estado invalido '$e'" ;;
  esac
done
[ "$superadas" -gt 0 ] && aviso "el proyecto esta EN CONVERGENCIA: $superadas entrada(s) superada(s)"

# --- 6 · literales de color fuera de tokens ---------------------------------
for d in $DIRS_COMP; do
  lit="$(grep -rnE '#[0-9a-fA-F]{3,8}\b|rgba?\(' "$d" 2>/dev/null | grep -v "$ARCH_TOK" | head -10)"
  if [ -n "$lit" ]; then
    err "valores de color literales fuera de los tokens en '$d':"
    printf '%s\n' "$lit" | sed 's/^/           /'
  fi
done

if [ $fallos -eq 0 ]; then
  echo "catalogo: sin fallas${avisos:+ ($avisos aviso/s)}"; exit 0
fi
echo "catalogo: $fallos FALLA(S)"
exit 1
