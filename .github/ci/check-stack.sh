#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# CI checks on the running stack: health endpoint, live-update path through the
# app port, the first administrator, and the security headers.

set -euo pipefail
cd "$(dirname "$0")/../.."
# shellcheck source=/dev/null  # checked on its own (.github/ci/lib.sh)
. .github/ci/lib.sh

echo "1/4 Health endpoint"
curl -fsS http://127.0.0.1:8080/up >/dev/null

echo "2/4 WebSocket upgrade on /app/* (expect 101)"
# A working upgrade stays open, so curl stops at --max-time. Only the status counts.
code="$(curl -s -o /dev/null -w '%{http_code}' --http1.1 --max-time 5 \
    -H 'Connection: Upgrade' -H 'Upgrade: websocket' \
    -H 'Sec-WebSocket-Version: 13' -H 'Sec-WebSocket-Key: SXJvblRhYmxlQ0lUZXN0IQ==' \
    "http://127.0.0.1:8080/app/$(env_value REVERB_APP_KEY)?protocol=7&client=js&version=8.4.0" || true)"
[ "$code" = "101" ] || { echo "HTTP status: $code"; exit 1; }

echo "3/4 First administrator"
[ "$(psql_db 'SELECT count(*) FROM users')" = "1" ]
# Read all logs first: "grep -q" stops early, and with pipefail the broken pipe fails the step.
logs="$(docker compose logs app)"
grep -qF "First administrator created: $(env_value INITIAL_ADMIN_EMAIL)" <<<"$logs"

echo "4/4 Security headers (backlog B2)"
headers="$(curl -fsS -D - -o /dev/null http://127.0.0.1:8080/login | tr -d '\r')"
for h in "Content-Security-Policy: default-src 'self'" "X-Content-Type-Options: nosniff" "X-Frame-Options: DENY"; do
    grep -qiF "$h" <<<"$headers" || { echo "Missing header: $h"; exit 1; }
done
if grep -qi '^Server:' <<<"$headers"; then echo "The Server header is not removed"; exit 1; fi

echo "OK"
