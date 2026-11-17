# shellcheck shell=bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Shared helpers for the CI checks. Source it; do not run it.

# Value of KEY from .env.
env_value() { grep "^$1=" .env | cut -d= -f2-; }

# Run one SQL statement in the db service and print the result.
psql_db() {
    # shellcheck disable=SC2016  # $POSTGRES_* expand inside the db container
    docker compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "$1"' sh "$1"
}
