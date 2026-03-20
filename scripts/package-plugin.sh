#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
BUILD_DIR="$DIST_DIR/build"
PLUGIN_DIR="$BUILD_DIR/wp-crypto-direct-gateway"
VERSION="$(grep -m1 '^ \* Version:' "$ROOT_DIR/wp-crypto-direct-gateway.php" | awk '{print $3}')"
ZIP_PATH="$DIST_DIR/wp-crypto-direct-gateway.zip"
VERSIONED_ZIP_PATH="$DIST_DIR/wp-crypto-direct-gateway-$VERSION.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$PLUGIN_DIR"

cp -R "$ROOT_DIR/assets" "$PLUGIN_DIR/assets"
cp -R "$ROOT_DIR/includes" "$PLUGIN_DIR/includes"
cp -R "$ROOT_DIR/docs" "$PLUGIN_DIR/docs"
cp "$ROOT_DIR/wp-crypto-direct-gateway.php" "$PLUGIN_DIR/"
cp "$ROOT_DIR/README.md" "$PLUGIN_DIR/"
cp "$ROOT_DIR/readme.txt" "$PLUGIN_DIR/"
cp "$ROOT_DIR/CHANGELOG.md" "$PLUGIN_DIR/"
cp "$ROOT_DIR/RELEASE_NOTES.md" "$PLUGIN_DIR/"

mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"
rm -f "$VERSIONED_ZIP_PATH"

cd "$BUILD_DIR"

if command -v zip >/dev/null 2>&1; then
	zip -rq "$ZIP_PATH" "wp-crypto-direct-gateway"
elif command -v python3 >/dev/null 2>&1; then
	python3 - "$BUILD_DIR" "$ZIP_PATH" <<'PY'
import pathlib
import sys
import zipfile

build_dir = pathlib.Path(sys.argv[1])
zip_path = pathlib.Path(sys.argv[2])
plugin_dir = build_dir / "wp-crypto-direct-gateway"

with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
	for path in sorted(plugin_dir.rglob("*")):
		if path.is_file():
			archive.write(path, path.relative_to(build_dir))
PY
else
	echo "No zip-capable tool available. Install zip or python3." >&2
	exit 1
fi

cp "$ZIP_PATH" "$VERSIONED_ZIP_PATH"

echo "Created $ZIP_PATH"
echo "Created $VERSIONED_ZIP_PATH"