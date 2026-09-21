#!/usr/bin/env bash
# Barrido de secretos. Un verificador, dos llamadores (D-C32):
#   - el hook local, con --diff: rapido, sobre lo escenificado. PREVIENE.
#   - el job `certificacion`, sin argumentos: sobre el arbol. CERTIFICA.
#
# Correcciones de la ronda v0.2.0, todas del mismo defecto: el script decia "ok"
# cuando NO PODIA comprobar. Un verificador que falla abierto es peor que ninguno,
# y este es el codigo que hace cumplir el archivo sin excepciones.
#   H-VER-2 · los nombres se buscan sin distinguir mayusculas.
#   H-VER-3 · fuera de un repo git, --diff FALLA; no imprime verde.
#   H-VER-5 · el modo arbol se ancla a la raiz del repo, no al CWD.
#   H-CIS-8 · las exclusiones son las mismas en los dos modos.

set -u
fallos=0

# Firmas con forma propia: distinguen mayusculas por diseño (AKIA, sk-, ghp_...).
PAT_FIRMAS='(-----BEGIN|AKIA[0-9A-Z]{16}|ASIA[0-9A-Z]{16}|xox[abpres]-|sk-[A-Za-z0-9_-]{20,}|gh[pousr]_[A-Za-z0-9]{36}|glpat-[A-Za-z0-9_-]{20}|AIza[0-9A-Za-z_-]{35}|eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.)'
# Asignaciones por nombre: se buscan SIN distinguir mayusculas (API_KEY=, Password:).
PAT_NOMBRES='(api[_-]?key|secret|password|passwd|token)["'"'"'[:space:]]*[:=]["'"'"'[:space:]]*[A-Za-z0-9/+_-]{16,}'

# Archivos que contienen los patrones por ser quienes los definen. Misma lista en
# los dos modos: la asimetria enseñaba a evadir en local y pasar en el CI.
EXCLUIDOS='verificadores/secretos.sh|.githooks/pre-commit'

echo "== barrido de secretos =="

if [ "${1:-}" = "--diff" ]; then
  ambito="lo escenificado"
  if ! git rev-parse --git-dir >/dev/null 2>&1; then
    echo "  FALLA · --diff fuera de un repositorio git: no hay nada que barrer y no se"
    echo "           puede certificar lo que no se miro"
    echo "secretos: 1 FALLA(S)"; exit 1
  fi
  # H-VER-2 / H-MIS-8 · se excluye el ARCHIVO ENTERO, no solo su cabecera '+++ b/'.
  # Antes, el cuerpo de este mismo script disparaba PAT_FIRMAS por su '-----BEGIN',
  # y el estandar NO SE PODIA COMMITEAR A SI MISMO sin --no-verify: el primer acto
  # de la instalacion fallaba. Lo encontro quien lo instalo de verdad, no quien lo leyo.
  archivos="$(git diff --cached --name-only 2>/dev/null | grep -vE "^($EXCLUIDOS)$" || true)"
  diff=""
  for f in $archivos; do
    diff="$diff
$(git diff --cached -U0 -- "$f" 2>/dev/null)"
  done
  # H-MIS-9 · se nombra el ARCHIVO (nunca el valor). Con ~100 archivos en el primer
  # commit, "hay un secreto en el diff" sin ruta obliga a inventar como encontrarlo.
  for f in $archivos; do
    d="$(git diff --cached -U0 -- "$f" 2>/dev/null)"
    if printf '%s\n' "$d" | grep -qE "$PAT_FIRMAS"; then
      echo "  FALLA · posible secreto (firma) en: $f"; fallos=$((fallos+1))
    fi
    if printf '%s\n' "$d" | grep -qiE "$PAT_NOMBRES"; then
      echo "  FALLA · posible secreto (asignacion por nombre) en: $f"; fallos=$((fallos+1))
    fi
  done
  if printf '%s\n' "$archivos" | grep -qE '(^|/)\.env$'; then
    echo "  FALLA · .env escenificado"; fallos=$((fallos+1))
  fi
else
  ambito="el arbol completo"
  raiz="$(git rev-parse --show-toplevel 2>/dev/null || true)"
  if [ -n "$raiz" ]; then
    cd "$raiz" || exit 1
  else
    echo "  aviso · fuera de un repositorio git: se barre el directorio actual, no la raiz"
  fi
  ex=(--exclude-dir=.git --exclude-dir=node_modules --exclude-dir=vendor
      --exclude=secretos.sh --exclude=pre-commit)
  hits="$( { grep -rnEI  "$PAT_FIRMAS"  . "${ex[@]}" 2>/dev/null;
             grep -rniEI "$PAT_NOMBRES" . "${ex[@]}" 2>/dev/null; } | sort -u )"
  if [ -n "$hits" ]; then
    # H-MIS-4 · RUTA Y LINEA, NUNCA EL VALOR. La version anterior volcaba la linea
    # completa a la terminal y por tanto al contexto del agente, que es la
    # superficie 4 de D-C36: el verificador de los innegociables incumplia los
    # innegociables. Lo encontro el rol que uso el estandar, corriendo el comando
    # que INSTALACION.md manda correr.
    echo "  FALLA · posible secreto en el arbol (se muestra ruta:linea, nunca el valor):"
    printf '%s\n' "$hits" | head -10 | cut -d: -f1,2 | sed 's/^/           /'
    fallos=$((fallos+1))
  fi
  # H-MIS-5 · TRACKEADO, no presente. Un .env local e ignorado por git es lo normal
  # en cualquier proyecto real; hacerlo fallar obligaba a borrarlo, y borrarlo rompe
  # la aplicacion. Lo que importa no es que exista: es que haya entrado al repositorio.
  if git ls-files 2>/dev/null | grep -qE '(^|/)\.env$'; then
    echo "  FALLA · hay un .env TRACKEADO por git"; fallos=$((fallos+1))
  fi
fi

# --- rutas secretas declaradas y conocidas (D-C42) ---------------------------
# El campo `rutas_secretas` existia en la plantilla y NADIE lo leia: era una
# capacidad afirmada y no construida. Esto es lo que si se puede comprobar.
raiz="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [ -n "$raiz" ] && [ -f "$raiz/$(cd "$raiz" && ls proyecto-*.md 2>/dev/null | head -1)" ] 2>/dev/null; then
  PROY="$raiz/$(cd "$raiz" && ls proyecto-*.md 2>/dev/null | head -1)"
  declaradas="$(sed -n 's/^rutas_secretas:[[:space:]]*//p' "$PROY" | head -1 | tr ',' ' ')"
  anexo="$(sed -n 's/^anexo:[[:space:]]*//p' "$PROY" | head -1)"
  conocidas=""
  # H-VER-1 · tras el cd a la raiz del repo, una RAIZ_ESTANDAR relativa deja de
  # apuntar al anexo y el bloque se saltaba en silencio. Se resuelve contra la raiz
  # y, si no se puede leer el anexo declarado, FALLA: no se dice "ok" sin mirar.
  arch_anexo="${RAIZ_ESTANDAR:-$raiz}/.agents/rules/anexos/$anexo.md"
  [ -f "$arch_anexo" ] || arch_anexo="$raiz/.agents/rules/anexos/$anexo.md"
  if [ -n "$anexo" ] && [ "$anexo" != "ninguno" ]; then
    if [ -f "$arch_anexo" ]; then
      conocidas="$(awk '/rutas-secretas:inicio/{f=1;next} /rutas-secretas:fin/{f=0} f' \
                    "$arch_anexo" | tr -d ' \t' | grep -v '^$')"
    else
      echo "  FALLA · el anexo declarado ('$anexo') no se pudo leer: no se comprobaron"
      echo "           las rutas secretas conocidas. Un verificador que no puede mirar"
      echo "           no dice ok."
      fallos=$((fallos+1))
    fi
  fi

  # 1 · ninguna ruta declarada esta trackeada por git
  for r in $declaradas; do
    if (cd "$raiz" && git ls-files --error-unmatch "$r" >/dev/null 2>&1); then
      echo "  FALLA · ruta declarada como secreta y TRACKEADA por git: $r"
      fallos=$((fallos+1))
    fi
  done

  # 2 · ninguna ruta secreta conocida del anexo queda fuera de la lista ni sin ignorar
  for k in $conocidas; do
    if (cd "$raiz" && [ -e "$k" ]); then
      if ! printf '%s\n' $declaradas | grep -qx "$k"; then
        if ! (cd "$raiz" && git check-ignore -q "$k" 2>/dev/null); then
          echo "  FALLA · '$k' existe, el anexo la conoce como secreta, y no esta"
          echo "           declarada en rutas_secretas ni ignorada por git"
          fallos=$((fallos+1))
        fi
      fi
    fi
  done
fi

if [ $fallos -eq 0 ]; then
  echo "  ok    · sin secretos detectados en $ambito"
  echo "secretos: sin fallas"; exit 0
fi
echo "secretos: $fallos FALLA(S) sobre $ambito"
exit 1
