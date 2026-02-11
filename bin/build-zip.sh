#!/usr/bin/env bash
#
# Genera il file ZIP del plugin pronto per upload su WordPress.
# Esclude file di sviluppo (test, CI, IDE, docs dev, etc.)
#
# Uso:
#   bin/build-zip.sh              # genera dist/ops-health-dashboard-0.1.0.zip
#   bin/build-zip.sh --output /tmp/plugin.zip  # path custom
#

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SLUG="ops-health-dashboard"

# Legge la versione dal file principale del plugin.
VERSION=$(grep -oP "Version:\s*\K[0-9.]+" "$PLUGIN_DIR/$PLUGIN_SLUG.php" || echo "0.0.0")

OUTPUT=""

# Parse argomenti.
while [[ $# -gt 0 ]]; do
	case "$1" in
		--output)
			OUTPUT="$2"
			shift 2
			;;
		--help|-h)
			echo "Uso: bin/build-zip.sh [--output path.zip]"
			echo ""
			echo "Genera il file ZIP del plugin pronto per WordPress."
			echo "Default: dist/${PLUGIN_SLUG}-VERSION.zip"
			exit 0
			;;
		*)
			echo "Opzione sconosciuta: $1" >&2
			exit 1
			;;
	esac
done

if [ -z "$OUTPUT" ]; then
	mkdir -p "$PLUGIN_DIR/dist"
	OUTPUT="$PLUGIN_DIR/dist/${PLUGIN_SLUG}-${VERSION}.zip"
fi

# Converte in path assoluto.
case "$OUTPUT" in
	/*) ;;
	*) OUTPUT="$(pwd)/$OUTPUT" ;;
esac

# Crea la directory di output se necessario.
mkdir -p "$(dirname "$OUTPUT")"

# Directory temporanea per il build.
BUILD_DIR=$(mktemp -d)
trap 'rm -rf "$BUILD_DIR"' EXIT

DEST="$BUILD_DIR/$PLUGIN_SLUG"
mkdir -p "$DEST"

echo "Building $PLUGIN_SLUG v$VERSION ..."

# Copia solo i file necessari per il plugin in produzione.
cp "$PLUGIN_DIR/$PLUGIN_SLUG.php" "$DEST/"
cp "$PLUGIN_DIR/LICENSE" "$DEST/"
cp "$PLUGIN_DIR/README.md" "$DEST/"
cp "$PLUGIN_DIR/CHANGELOG.md" "$DEST/"
[ -f "$PLUGIN_DIR/uninstall.php" ] && cp "$PLUGIN_DIR/uninstall.php" "$DEST/"
[ -f "$PLUGIN_DIR/readme.txt" ] && cp "$PLUGIN_DIR/readme.txt" "$DEST/"

cp -r "$PLUGIN_DIR/src" "$DEST/"
cp -r "$PLUGIN_DIR/config" "$DEST/"
[ -d "$PLUGIN_DIR/languages" ] && cp -r "$PLUGIN_DIR/languages" "$DEST/"
[ -d "$PLUGIN_DIR/assets" ] && cp -r "$PLUGIN_DIR/assets" "$DEST/"

# Installa dipendenze production-only.
cp "$PLUGIN_DIR/composer.json" "$DEST/"
cd "$DEST"
composer install --no-dev --no-interaction --optimize-autoloader --quiet 2>/dev/null || true
rm -f "$DEST/composer.json" "$DEST/composer.lock"
cd - > /dev/null

# Rimuovi eventuali file nascosti o di test nei vendor.
find "$DEST/vendor" -name ".git" -type d -exec rm -rf {} + 2>/dev/null || true
find "$DEST/vendor" -name "tests" -type d -exec rm -rf {} + 2>/dev/null || true
find "$DEST/vendor" -name "Tests" -type d -exec rm -rf {} + 2>/dev/null || true
find "$DEST/vendor" -name ".github" -type d -exec rm -rf {} + 2>/dev/null || true

# Genera lo ZIP: usa il comando zip se disponibile, altrimenti PHP ZipArchive.
if command -v zip > /dev/null 2>&1; then
	cd "$BUILD_DIR"
	zip -rq "$OUTPUT" "$PLUGIN_SLUG"
	cd - > /dev/null
else
	php -r '
$src = $argv[1];
$dst = $argv[2];
$zip = new ZipArchive();
if ($zip->open($dst, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Errore: impossibile creare $dst\n");
    exit(1);
}
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$base = dirname($src);
foreach ($iterator as $file) {
    $path = str_replace($base . "/", "", $file->getPathname());
    if ($file->isDir()) {
        $zip->addEmptyDir($path);
    } else {
        $zip->addFile($file->getPathname(), $path);
    }
}
$zip->close();
' "$DEST" "$OUTPUT"
fi

FILE_SIZE=$(du -h "$OUTPUT" | cut -f1)
echo ""
echo "ZIP generato: $OUTPUT ($FILE_SIZE)"
echo "Versione: $VERSION"
echo ""
echo "Contenuto:"
php -r '
$zip = new ZipArchive();
$zip->open($argv[1]);
$total = $zip->numFiles;
for ($i = 0; $i < min($total, 20); $i++) {
    echo "  " . $zip->getNameIndex($i) . "\n";
}
if ($total > 20) {
    echo "  ... e altri " . ($total - 20) . " file\n";
}
echo "\nTotale: $total file\n";
$zip->close();
' "$OUTPUT"
