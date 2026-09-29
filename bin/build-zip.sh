#!/usr/bin/env bash
# ────────────────────────────────────────────────────────────────────────────
# Genera el ZIP instalable de SECOP Suite.
#
#   bin/build-zip.sh                 → carpeta raíz «secop-suite-main/» (la de producción)
#   bin/build-zip.sh otra-carpeta    → carpeta raíz «otra-carpeta/»
#
# Produce:
#   dist/secop-suite-<versión>.zip   (versión tomada de «Version:» en secop-suite.php)
#   dist/secop-suite.zip             (copia con nombre fijo, la que busca el actualizador)
#   o, con otra carpeta:             dist/secop-suite-<versión>-carpeta-<carpeta>.zip
#
# WordPress solo ofrece «Reemplazar el actual con el subido» cuando la carpeta
# raíz del ZIP se llama IGUAL que la carpeta instalada en wp-content/plugins/.
# La instalación de producción está en «secop-suite-main/», por eso es la carpeta
# por defecto. Reemplazar así no ejecuta activación ni desinstalación: la base de
# datos, las gráficas, los filtros, las cards y los shortcodes quedan intactos.
# Desde la 5.19.1 el plugin instalado también renombra cualquier ZIP subido a su
# propia carpeta; el nombre solo es decisivo al actualizar desde versiones anteriores.
#
# Los archivos de desarrollo se excluyen según .distignore.
# Requiere: bash, rsync, zip, unzip.
# ────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SLUG="secop-suite"
DEFAULT_FOLDER="secop-suite-main"
FOLDER="${1:-${SECOP_ZIP_FOLDER:-$DEFAULT_FOLDER}}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN="$ROOT/$SLUG.php"
IGNORE="$ROOT/.distignore"
DIST="$ROOT/dist"

die() { echo "ERROR: $*" >&2; exit 1; }

for cmd in rsync zip unzip; do
    command -v "$cmd" >/dev/null 2>&1 || die "falta el comando '$cmd'."
done
[ -f "$MAIN" ]   || die "no se encontró $MAIN"
[ -f "$IGNORE" ] || die "no se encontró $IGNORE"
[[ "$FOLDER" =~ ^[A-Za-z0-9._-]+$ ]] || die "nombre de carpeta inválido: '$FOLDER'"

# Versión del encabezado « * Version: X.Y.Z » del archivo principal.
VERSION="$(grep -m1 -E '^[[:space:]/*#]*Version:' "$MAIN" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//' | tr -d '\r')"
[[ "$VERSION" =~ ^[0-9]+(\.[0-9]+){1,3}$ ]] || die "versión inválida en el encabezado: '$VERSION'"

# Debe coincidir con la constante SECOP_SUITE_VERSION.
CONST="$(sed -nE "s/.*define\([[:space:]]*'SECOP_SUITE_VERSION'[[:space:]]*,[[:space:]]*'([^']+)'.*/\1/p" "$MAIN" | head -n1)"
[ "$CONST" = "$VERSION" ] || die "Version: ($VERSION) y SECOP_SUITE_VERSION ($CONST) no coinciden."

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/$FOLDER"
rsync -a --exclude-from="$IGNORE" "$ROOT/" "$STAGE/$FOLDER/"

# Comprobaciones mínimas del contenido.
for f in "$SLUG.php" uninstall.php index.php includes templates assets logs/index.php logs/.htaccess; do
    [ -e "$STAGE/$FOLDER/$f" ] || die "falta '$f' en el paquete."
done
for f in tests .git .github .claude bin dist docs AUDITORIA.md REVIEW.md .distignore secop-v5.15; do
    [ ! -e "$STAGE/$FOLDER/$f" ] || die "'$f' no debería estar en el paquete (revise .distignore)."
done
# Ninguna otra copia del plugin anidada dentro del paquete.
if find "$STAGE/$FOLDER" -mindepth 2 -name "$SLUG.php" | grep -q .; then
    die "el paquete contiene otra copia de $SLUG.php en una subcarpeta (revise .distignore)."
fi

# Permisos estándar para archivos y carpetas del plugin.
find "$STAGE/$FOLDER" -type d -exec chmod 755 {} +
find "$STAGE/$FOLDER" -type f -exec chmod 644 {} +

mkdir -p "$DIST"
if [ "$FOLDER" = "$DEFAULT_FOLDER" ]; then
    OUT="$DIST/$SLUG-$VERSION.zip"
else
    OUT="$DIST/$SLUG-$VERSION-carpeta-$FOLDER.zip"
fi
rm -f "$OUT"

( cd "$STAGE" && zip -rqX "$OUT" "$FOLDER" )
if [ "$FOLDER" = "$DEFAULT_FOLDER" ]; then
    rm -f "$DIST/$SLUG.zip"
    cp "$OUT" "$DIST/$SLUG.zip"
fi

# Verificar que TODAS las entradas cuelgan de la carpeta raíz.
if unzip -Z1 "$OUT" | grep -qv "^$FOLDER/"; then
    die "el ZIP contiene entradas fuera de '$FOLDER/'."
fi

echo "ZIP generado (carpeta raíz: $FOLDER/, versión $VERSION):"
echo "  $OUT"
[ "$FOLDER" = "$DEFAULT_FOLDER" ] && echo "  $DIST/$SLUG.zip"
exit 0
