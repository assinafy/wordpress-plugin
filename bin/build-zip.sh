#!/usr/bin/env bash
#
# Builds the wordpress.org distribution tree and zip for the Assinafy plugin.
#
# Produces:
#   dist/assinafy/                 the distribution tree (what ships)
#   dist/assinafy-<version>.zip    the same tree, zipped with assinafy/ as its root
#   dist/VERSION                   the version string, for callers that need it
#
# Exclusions come from .distignore via rsync --exclude-from, which is the same
# mechanism 10up/action-wordpress-plugin-deploy uses when BUILD_DIR is unset.
# Setting BUILD_DIR makes that action skip .distignore entirely, so this script
# is the single place exclusions are applied for every distribution channel.
#
# Dependencies must already be installed:
#   composer install
# which installs the Strauss build tool and produces vendor-prefixed/.
# Only the prefixed runtime dependencies reach the package; vendor/ never ships.

set -euo pipefail

SLUG='assinafy'
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ASSINAFY_DIST_DIR:-${ROOT}/dist}"
STAGE="${DIST}/${SLUG}"

die() {
	printf '%s\n' "$1" >&2
	exit 1
}

[ -f "${ROOT}/${SLUG}.php" ] || die "Missing ${SLUG}.php — run this from a checkout of the plugin."
[ -f "${ROOT}/readme.txt" ] || die 'Missing readme.txt.'
[ -f "${ROOT}/.distignore" ] || die 'Missing .distignore — refusing to build a zip with no exclusions.'
[ -f "${ROOT}/vendor-prefixed/autoload.php" ] || die 'Missing vendor-prefixed/autoload.php — run composer install first.'

# The plugin header is authoritative for the version; readme.txt must agree with it.
version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\(.*\)$/\1/p' "${ROOT}/${SLUG}.php" | head -n 1 | tr -d '[:space:]')"
stable="$(sed -n 's/^Stable tag:[[:space:]]*\(.*\)$/\1/p' "${ROOT}/readme.txt" | head -n 1 | tr -d '[:space:]')"
runtime="$(php -r '$source = file_get_contents($argv[1]); preg_match("/define\\(\\s*\x27ASSINAFY_VERSION\x27\\s*,\\s*\x27([^\x27]+)\x27\\s*\\)/", $source, $match); echo $match[1] ?? "";' "${ROOT}/${SLUG}.php")"

[ -n "${version}" ] || die "No Version: header found in ${SLUG}.php."
[ -n "${stable}" ] || die 'No Stable tag: line found in readme.txt.'
[ "${version}" = "${stable}" ] || die "Version mismatch: ${SLUG}.php says ${version}, readme.txt Stable tag says ${stable}."
[ "${version}" = "${runtime}" ] || die "Version mismatch: ${SLUG}.php header says ${version}, ASSINAFY_VERSION says ${runtime}."

rm -rf "${DIST}"
mkdir -p "${STAGE}"

rsync -a --delete --delete-excluded \
	--exclude-from="${ROOT}/.distignore" \
	--exclude="/$(basename "${DIST}")" \
	"${ROOT}/" "${STAGE}/"

# The plugin ships a WordPress-native transport and no Guzzle at all; Guzzle
# source in the package means the dependency wiring regressed. The test is the
# namespace declaration, not the filename: the SDK's own GuzzleHttpClient.php
# ships (unreferenced) and must not trip this.
if grep -rqE '^[[:space:]]*namespace[[:space:]]+GuzzleHttp' "${STAGE}"; then
	die 'Guzzle source found in the distribution tree — the plugin must ship none.'
fi
[ -f "${STAGE}/vendor-prefixed/autoload.php" ] || die 'vendor-prefixed/autoload.php was excluded from the distribution tree.'
if [ -d "${STAGE}/vendor" ]; then
	die 'The unprefixed vendor/ tree reached the distribution — exclude it in .distignore.'
fi

zip_path="${DIST}/${SLUG}-${version}.zip"
( cd "${DIST}" && zip -rq "${zip_path}" "${SLUG}" -x '*.DS_Store' )

printf '%s\n' "${version}" > "${DIST}/VERSION"
printf 'Built %s\n' "${zip_path}"
