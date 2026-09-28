#!/usr/bin/env bash
# ────────────────────────────────────────────────────────────────────────────
# Genera el ZIP instalable de SECOP Suite.
#
#   bin/build-zip.sh
#
# Produce:
#   dist/secop-suite-<versión>.zip   (versión tomada de «Version:» en secop-suite.php)
#   dist/secop-suite.zip             (copia con nombre fijo, la que busca el actualizador)
#
# La carpeta raíz dentro del ZIP es SIEMPRE «secop-suite/», la misma carpeta de
# la instalación en producción (wp-content/plugins/secop-suite/). Así WordPress
# ofrece «Reemplazar el actual con el subido» en vez de instalar un plugin
# distinto (lo que ocurre con el «Download ZIP» de GitHub, cuya carpeta raíz es
# «suite-secop-<rama>»).
#
# Los archivos de desarrollo se excluyen según .distignore.
# Requiere: bash, rsync, zip, unzip.
# ────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SLUG="secop-suite"
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

# Versión del encabezado « * Version: X.Y.Z » del archivo principal.
VERSION="$(grep -m1 -E '^[[:space:]/*#]*Version:' "$MAIN" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//' | tr -d '\r')"
[[ "$VERSION" =~ ^[0-9]+(\.[0-9]+){1,3}$ ]] || die "versión inválida en el encabezado: '$VERSION'"

# Debe coincidir con la constante SECOP_SUITE_VERSION.
CONST="$(sed -nE "s/.*define\([[:space:]]*'SECOP_SUITE_VERSION'[[:space:]]*,[[:space:]]*'([^']+)'.*/\1/p" "$MAIN" | head -n1)"
[ "$CONST" = "$VERSION" ] || die "Version: ($VERSION) y SECOP_SUITE_VERSION ($CONST) no coinciden."

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/$SLUG"
rsync -a --exclude-from="$IGNORE" "$ROOT/" "$STAGE/$SLUG/"

# Comprobaciones mínimas del contenido.
for f in "$SLUG.php" uninstall.php index.php includes templates assets logs/index.php logs/.htaccess; do
    [ -e "$STAGE/$SLUG/$f" ] || die "falta '$f' en el paquete."
done
for f in tests .git .github .claude bin dist docs AUDITORIA.md REVIEW.md .distignore; do
    [ ! -e "$STAGE/$SLUG/$f" ] || die "'$f' no debería estar en el paquete (revise .distignore)."
done

# Permisos estándar para archivos y carpetas del plugin.
find "$STAGE/$SLUG" -type d -exec chmod 755 {} +
find "$STAGE/$SLUG" -type f -exec chmod 644 {} +

mkdir -p "$DIST"
OUT="$DIST/$SLUG-$VERSION.zip"
rm -f "$OUT" "$DIST/$SLUG.zip"

( cd "$STAGE" && zip -rqX "$OUT" "$SLUG" )
cp "$OUT" "$DIST/$SLUG.zip"

# Verificar que TODAS las entradas cuelgan de «secop-suite/».
if unzip -Z1 "$OUT" | grep -qv "^$SLUG/"; then
    die "el ZIP contiene entradas fuera de '$SLUG/'."
fi

echo "ZIP generado (carpeta raíz: $SLUG/, versión $VERSION):"
echo "  $OUT"
echo "  $DIST/$SLUG.zip"
