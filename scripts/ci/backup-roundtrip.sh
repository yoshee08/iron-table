#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# CI check: back up, delete the data, restore, and confirm the data is back.

set -euo pipefail
cd "$(dirname "$0")/../.."
# shellcheck source=/dev/null  # checked on its own (scripts/ci/lib.sh)
. scripts/ci/lib.sh

marker=/app/storage/app/private/ci-marker.txt

docker compose exec -T app sh -c "echo ci-marker > $marker"
backup="$(./scripts/backup.sh | sed -n 's/^OK: //p')"
[ -f "$backup" ]

psql_db "DELETE FROM users" >/dev/null
docker compose exec -T app rm "$marker"
[ "$(psql_db 'SELECT count(*) FROM users')" = "0" ]

./scripts/restore.sh --yes "$backup"
[ "$(psql_db 'SELECT count(*) FROM users')" = "1" ]
[ "$(docker compose exec -T app cat "$marker")" = "ci-marker" ]
curl -fsS http://127.0.0.1:8080/up >/dev/null

echo "OK"
