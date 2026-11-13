#!/bin/sh
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Creates .env from .env.example and fills in random secrets.
# Run it one time, before the first "docker compose up -d".
# It never changes an .env file that already exists.

set -eu

cd "$(dirname "$0")/.."

if [ -f .env ]; then
    echo "OK: .env already exists. Nothing changed."
    exit 0
fi

# Random values from the operating system. No extra tools needed.
hex()  { od -An -tx1 -N"$1" /dev/urandom | tr -d ' \n'; }
b64()  { head -c "$1" /dev/urandom | base64 | tr -d '\n'; }
num()  { od -An -tu4 -N4 /dev/urandom | tr -d ' \n'; }

app_key="base64:$(b64 32)"
db_password="$(hex 24)"
reverb_id="$(( $(num) % 900000 + 100000 ))"
reverb_key="$(hex 16)"
reverb_secret="$(hex 24)"

awk \
    -v app_key="$app_key" \
    -v db_password="$db_password" \
    -v reverb_id="$reverb_id" \
    -v reverb_key="$reverb_key" \
    -v reverb_secret="$reverb_secret" '
    /^APP_KEY=$/           { print "APP_KEY=" app_key; next }
    /^DB_PASSWORD=$/       { print "DB_PASSWORD=" db_password; next }
    /^REVERB_APP_ID=$/     { print "REVERB_APP_ID=" reverb_id; next }
    /^REVERB_APP_KEY=$/    { print "REVERB_APP_KEY=" reverb_key; next }
    /^REVERB_APP_SECRET=$/ { print "REVERB_APP_SECRET=" reverb_secret; next }
    { print }
' .env.example > .env

chmod 600 .env
echo "OK: .env created with new random secrets."
echo "Next: docker compose up -d"
