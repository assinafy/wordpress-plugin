#!/usr/bin/env bash
# Verify the release version gate and exclusions without touching the working distribution.
set -euo pipefail

SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIXTURE="$(mktemp -d "${TMPDIR:-/tmp}/assinafy-build-test.XXXXXX")"
trap 'rm -rf "$FIXTURE"' EXIT

mkdir -p "$FIXTURE/bin" "$FIXTURE/vendor-prefixed" "$FIXTURE/.cache" "$FIXTURE/.agents" "$FIXTURE/addons/assinafy-example/tests"
cp "$SOURCE/bin/build-zip.sh" "$FIXTURE/bin/build-zip.sh"
cp "$SOURCE/.distignore" "$FIXTURE/.distignore"
touch "$FIXTURE/addons/assinafy-example/assinafy-example.php" "$FIXTURE/vendor-prefixed/autoload.php" "$FIXTURE/.cache/private" "$FIXTURE/.agents/private" "$FIXTURE/phpstan.neon"
cat > "$FIXTURE/assinafy.php" <<'PHP'
<?php
/**
 * Version: 1.0.0
 */
define( 'ASSINAFY_VERSION', '2.0.0' );
PHP
printf 'Stable tag: 1.0.0\n' > "$FIXTURE/readme.txt"

if bash "$FIXTURE/bin/build-zip.sh" > "$FIXTURE/result.log" 2>&1; then
	printf 'The build accepted a mismatched runtime version.\n' >&2
	exit 1
fi
grep -q 'ASSINAFY_VERSION says 2.0.0' "$FIXTURE/result.log"
test ! -d "$FIXTURE/dist"

sed 's/2.0.0/1.0.0/' "$FIXTURE/assinafy.php" > "$FIXTURE/fixed.php"
mv "$FIXTURE/fixed.php" "$FIXTURE/assinafy.php"
rm "$FIXTURE/result.log"
bash "$FIXTURE/bin/build-zip.sh"
test -f "$FIXTURE/dist/assinafy-1.0.0.zip"
test -f "$FIXTURE/dist/assinafy/vendor-prefixed/autoload.php"
test ! -e "$FIXTURE/dist/assinafy/.cache"
test ! -e "$FIXTURE/dist/assinafy/.agents"
test ! -e "$FIXTURE/dist/assinafy/addons"
test ! -e "$FIXTURE/dist/assinafy/phpstan.neon"
printf 'Build version and exclusion checks passed.\n'
