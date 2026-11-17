#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Runs Composer or npm in a container on src/ (used by .github/workflows/deps.yml).
#   TOOL=composer ARGS="require laravel/fortify:^1.42" .github/ci/update-deps.sh
#   TOOL=npm      ARGS="install some-package@1.2.3"   .github/ci/update-deps.sh
# ARGS is split on spaces only. It is never run through a shell.

set -euo pipefail
cd "$(dirname "$0")/../.."

read -r -a args <<<"${ARGS:?Set ARGS}"
user="$(id -u):$(id -g)"

case "${TOOL:?Set TOOL}" in
    composer)
        # The runtime image has the PHP extensions; the composer image may not.
        docker run --rm -u "$user" -e COMPOSER_HOME=/tmp/composer \
            -v "$PWD/src:/app" -w /app composer:2.8.12 \
            "${args[@]}" --no-interaction --no-scripts --ignore-platform-req='ext-*'
        ;;
    npm)
        docker run --rm -u "$user" -e npm_config_cache=/tmp/npm-cache \
            -v "$PWD/src:/app" -w /app node:22.22-alpine \
            npm "${args[@]}" --no-audit --no-fund
        ;;
    *)
        echo "TOOL must be composer or npm" >&2
        exit 1
        ;;
esac
