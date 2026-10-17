#!/usr/bin/env bash
# Assemble the WordPress plugin and the OpenCart extension from the shared
# PHP engine library in public/api, and zip them into build/.
#
#   scripts/build-plugins.sh            # copy shared files + build zips
#   scripts/build-plugins.sh --no-zip   # copy shared files only (for local dev)
#
# The shared copies inside plugins/ are generated and git-ignored.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API="$ROOT/public/api"
OUT="$ROOT/build"
VERSION="$(node -p "require('$ROOT/package.json').version")"

WP="$ROOT/plugins/wordpress/keyword-suggest"
OC="$ROOT/plugins/opencart/keyword_suggest"

copy_api() {
  local dest="$1"
  rm -rf "$dest"
  mkdir -p "$dest/engines"
  cp "$API/lib.php" "$API/keyphrases.php" "$dest/"
  cp "$API"/engines/*.php "$dest/engines/"
  # Nothing in the bundled copy should be web-reachable on its own.
  printf 'Require all denied\n' > "$dest/.htaccess"
  printf '<?php\n// Silence is golden.\n' > "$dest/index.php"
  printf '<?php\n// Silence is golden.\n' > "$dest/engines/index.php"
}

echo "Syncing shared API (v$VERSION) into plugins…"
copy_api "$WP/includes/api"
copy_api "$OC/system/library/keyword_suggest/api"

# Keep the version headers in step with package.json.
sed -i -E "s/^( \* Version:\s+).*/\1$VERSION/" "$WP/keyword-suggest.php"
sed -i -E "s/^(define\('KSUG_VERSION', ')[^']*('\);)/\1$VERSION\2/" "$WP/keyword-suggest.php"
sed -i -E "s/^(Stable tag: ).*/\1$VERSION/" "$WP/readme.txt"
sed -i -E "s/(\"version\": \")[^\"]*(\")/\1$VERSION\2/" "$OC/install.json"

if [[ "${1:-}" == "--no-zip" ]]; then
  echo "Done (no zip)."
  exit 0
fi

rm -rf "$OUT"
mkdir -p "$OUT"

# WordPress: zip must contain the plugin folder itself.
(cd "$WP/.." && zip -qr "$OUT/keyword-suggest-wordpress-v$VERSION.zip" keyword-suggest -x '*.DS_Store')

# OpenCart 4: the zip root holds install.json + admin/ + system/; the installer
# derives the extension code from the file name, so it must be keyword_suggest.ocmod.zip.
(cd "$OC" && zip -qr "$OUT/keyword_suggest.ocmod.zip" install.json admin system -x '*.DS_Store')

ls -la "$OUT"
