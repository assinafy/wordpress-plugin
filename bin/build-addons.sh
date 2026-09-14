#!/usr/bin/env bash
# Build dedicated adapters without copying core, host plugins, credentials, or tests.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${ROOT}/dist/addons"
mkdir -p "${DEST}"
for source in "${ROOT}"/addons/assinafy-*; do
	slug="$(basename "${source}")"
	main="${source}/${slug}.php"
	version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\(.*\)$/\1/p' "${main}" | head -n 1 | tr -d '[:space:]')"
	stable="$(sed -n 's/^Stable tag:[[:space:]]*\(.*\)$/\1/p' "${source}/readme.txt" | head -n 1 | tr -d '[:space:]')"
	[ -n "${version}" ] && [ "${version}" = "${stable}" ] || { echo "Version mismatch: ${slug}" >&2; exit 1; }
	stage="${DEST}/${slug}"
	mkdir -p "${stage}"
	rsync -a --delete --exclude=/tests --exclude=.DS_Store "${source}/" "${stage}/"
	[ -f "${stage}/LICENSE" ] || { echo "Missing license: ${slug}" >&2; exit 1; }
	zip_path="${DEST}/${slug}-${version}.zip"
	rm -f "${zip_path}"
	( cd "${DEST}" && zip -rq "${zip_path}" "${slug}" )
	printf 'Built %s\n' "${zip_path}"
done
