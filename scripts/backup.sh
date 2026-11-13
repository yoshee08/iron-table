#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Makes one backup file of the database and the uploaded files (spec 11.3, item 7).
#
#   ./scripts/backup.sh
#
# Result: backups/iron-table-<UTC time>.tar with
#   database.dump    PostgreSQL custom-format dump
#   storage.tar.gz   the "storage" volume (uploaded files)
#   SHA256SUMS       checksums
#
# The backup does NOT contain .env. Keep a safe copy of APP_KEY: without it,
# encrypted data in the backup cannot be read.

set -euo pipefail

say()  { printf '%s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
sha()  { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$@"; else shasum -a 256 "$@"; fi; }

cd "$(dirname "$0")/.."

docker compose ps --status running --services 2>/dev/null | grep -qx db \
    || fail "The db service is not running. Start it with: docker compose up -d"

umask 077
mkdir -p backups
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
out="backups/iron-table-${stamp}.tar"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

say "1/3 Database"
# shellcheck disable=SC2016  # $POSTGRES_* expand inside the db container
docker compose exec -T db sh -c \
    'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner' \
    > "$work/database.dump"

say "2/3 Uploaded files"
docker compose run --rm --no-deps -T --entrypoint tar app \
    -C /app/storage -czf - . > "$work/storage.tar.gz"

say "3/3 Checksums and archive"
(cd "$work" && sha database.dump storage.tar.gz > SHA256SUMS)
tar -C "$work" -cf "$out" database.dump storage.tar.gz SHA256SUMS

say "OK: ${out}"
say "Keep APP_KEY from .env in a safe place. The backup needs it."
