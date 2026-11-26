#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Restores a backup made by scripts/backup.sh (spec 11.3, item 8).
# It REPLACES the current database and uploaded files.
#
#   ./scripts/restore.sh backups/iron-table-<time>.tar
#   ./scripts/restore.sh --yes backups/iron-table-<time>.tar   (no question)
#
# Use the same APP_KEY in .env as when the backup was made.

set -euo pipefail

say()  { printf '%s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
sha()  { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$@"; else shasum -a 256 "$@"; fi; }

assume_yes=false
if [ "${1:-}" = "--yes" ]; then
    assume_yes=true
    shift
fi
file="${1:-}"
[ -n "$file" ] || fail "Usage: ./scripts/restore.sh [--yes] backups/iron-table-<time>.tar"
[ -f "$file" ] || fail "File not found: $file"
file="$(cd "$(dirname "$file")" && pwd)/$(basename "$file")"

cd "$(dirname "$0")/.."

if [ "$assume_yes" != true ]; then
    printf 'This replaces ALL current data with the backup %s.\nType "restore" to continue: ' "$file"
    read -r answer
    [ "$answer" = "restore" ] || fail "Stopped. Nothing changed."
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

say "1/5 Check the backup file"
tar -C "$work" -xf "$file"
for f in database.dump storage.tar.gz SHA256SUMS; do
    [ -f "$work/$f" ] || fail "The backup has no $f. Nothing changed."
done
(cd "$work" && sha -c SHA256SUMS >/dev/null) || fail "Checksum error. The backup is damaged. Nothing changed."

say "2/5 Stop the application"
docker compose stop app reverb worker scheduler
docker compose up -d --wait db

say "3/5 Restore the database"
# shellcheck disable=SC2016  # $POSTGRES_* expand inside the db container
docker compose exec -T db sh -c \
    'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --single-transaction' \
    < "$work/database.dump"

say "4/5 Restore the uploaded files"
docker compose run --rm --no-deps -T --entrypoint sh app \
    -c 'find /app/storage -mindepth 1 -delete && tar -C /app/storage -xzf -' \
    < "$work/storage.tar.gz"

say "5/5 Start the application"
docker compose up -d --wait

say "OK: restored from ${file}"
