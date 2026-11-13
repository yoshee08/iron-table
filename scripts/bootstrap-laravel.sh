#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# One-time bootstrap: generates the Laravel application skeleton into this
# repository. Run it ONE time, on a computer with Docker, then commit the result.
#
#   scripts/bootstrap-laravel.sh
#
# The script uses containers only. It installs nothing on the computer.
# It never overwrites a file that already exists in the repository
# (README.md, .gitignore, .env.example, LICENSE and the docker/ folder stay as they are).

set -euo pipefail

COMPOSER_IMAGE="composer:2.8.12"
NODE_IMAGE="node:22.22-alpine"
LARAVEL_CONSTRAINT="^13.0"
LIVEWIRE_CONSTRAINT="^4.0"
REVERB_CONSTRAINT="^1.0"

say()  { printf '\n==> %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# --- Checks ------------------------------------------------------------------
[ -f "$repo/artisan" ] && fail "The repository already contains a Laravel application (artisan exists). Nothing to do."
command -v docker >/dev/null 2>&1 || fail "Docker is not installed."
docker info >/dev/null 2>&1 || fail "Docker is not running, or this user cannot use it."

uid="$(id -u)"
gid="$(id -g)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
app="$work/app"

# Run composer in a container, in folder $1.
composer_in() {
    local dir="$1"; shift
    docker run --rm -u "$uid:$gid" \
        -e COMPOSER_HOME=/tmp/composer \
        -v "$dir:/work" -w /work \
        "$COMPOSER_IMAGE" "$@"
}

# Run php (from the composer image) in a container, in folder $1.
php_in() {
    local dir="$1"; shift
    docker run --rm -u "$uid:$gid" \
        -e COMPOSER_HOME=/tmp/composer \
        -v "$dir:/work" -w /work \
        --entrypoint php \
        "$COMPOSER_IMAGE" "$@"
}

# Run npm in a container, in folder $1.
npm_in() {
    local dir="$1"; shift
    docker run --rm -u "$uid:$gid" \
        -e npm_config_cache=/tmp/npm-cache \
        -v "$dir:/work" -w /work \
        "$NODE_IMAGE" npm "$@"
}

# --- 1. Laravel skeleton -----------------------------------------------------
say "1/6 Creating Laravel $LARAVEL_CONSTRAINT skeleton (in a temporary folder)"
composer_in "$work" create-project --no-interaction --no-scripts --prefer-dist \
    "laravel/laravel:$LARAVEL_CONSTRAINT" app

# --- 2. Livewire and Reverb --------------------------------------------------
say "2/6 Adding Livewire $LIVEWIRE_CONSTRAINT and Reverb $REVERB_CONSTRAINT"
# The runtime image has the PHP extensions. The composer image may not.
composer_in "$app" require --no-interaction --no-scripts \
    --ignore-platform-req='ext-*' \
    "livewire/livewire:$LIVEWIRE_CONSTRAINT" "laravel/reverb:$REVERB_CONSTRAINT"
php_in "$app" artisan package:discover --ansi

# --- 3. Broadcasting files ---------------------------------------------------
say "3/6 Publishing broadcasting and Reverb configuration"
# This command can also try to run npm, which is not in the composer image.
# The result is checked below, so a failure here is only a warning.
if ! php_in "$app" artisan install:broadcasting --reverb --no-interaction; then
    warn "install:broadcasting returned an error. The required files are checked next."
fi
for f in config/broadcasting.php config/reverb.php routes/channels.php; do
    [ -f "$app/$f" ] || fail "Missing $f after install:broadcasting. Stop and check the Laravel $LARAVEL_CONSTRAINT broadcasting documentation."
done

# --- 4. Front-end packages ---------------------------------------------------
say "4/6 Installing front-end packages (makes package-lock.json)"
npm_in "$app" install --no-audit --no-fund
npm_in "$app" install --no-audit --no-fund --save-dev laravel-echo pusher-js
[ -f "$app/package-lock.json" ] || fail "package-lock.json was not created."

# --- 5. Clean up -------------------------------------------------------------
say "5/6 Removing local-only files"
rm -f "$app/.env" "$app/database/database.sqlite"

# Settings that Laravel expects but .env.example in this repository does not have.
missing_keys="$(comm -23 \
    <(grep -oE '^[A-Z0-9_]+=' "$app/.env.example" | sort -u) \
    <(grep -oE '^[A-Z0-9_]+=' "$repo/.env.example" | sort -u) || true)"

# --- 6. Copy into the repository ---------------------------------------------
say "6/6 Copying new files into the repository (existing files are kept)"
copied=0
skipped=()
while IFS= read -r -d '' src; do
    rel="${src#"$app"/}"
    dest="$repo/$rel"
    if [ -e "$dest" ]; then
        skipped+=("$rel")
        continue
    fi
    mkdir -p "$(dirname "$dest")"
    cp -p "$src" "$dest"
    copied=$((copied + 1))
done < <(find "$app" \( -path "$app/vendor" -o -path "$app/node_modules" \) -prune -o -type f -print0)

printf '\nCopied %d files.\n' "$copied"
if [ "${#skipped[@]}" -gt 0 ]; then
    printf 'Kept the repository version of these files:\n'
    printf '  %s\n' "${skipped[@]}"
fi
if [ -n "$missing_keys" ]; then
    warn "Laravel's .env.example has settings that this repository's .env.example does not have:"
    # shellcheck disable=SC2086  # word splitting is intended here
    printf '  %s\n' $missing_keys >&2
    warn "Add the ones that apply to .env.example before you commit."
fi

cat <<'EOF'

Done. Next steps:
  1. Read the changes:        git status
  2. Commit the skeleton:     git add -A && git commit -m "Add Laravel skeleton"
  3. Build and start:         see docs/development.md
EOF
