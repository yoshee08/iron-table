#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# CI npm audit (spec SEC-13): fail on high or critical advisories, except the
# reviewed exceptions in .github/ci/npm-audit-allowlist.txt.

set -euo pipefail
cd "$(dirname "$0")/../.."

allowlist=.github/ci/npm-audit-allowlist.txt
report="$(cd src || exit 1; npm audit --package-lock-only --json || true)"

found="$(jq -r '.vulnerabilities[].via[] | objects
    | select(.severity == "high" or .severity == "critical")
    | .url | sub(".*/"; "")' <<<"$report" | sort -u)"
allowed="$(sed -e 's/#.*//' -e 's/[[:space:]]//g' "$allowlist" | grep -v '^$' | sort -u || true)"
blocking="$(comm -23 <(printf '%s\n' "$found" | grep -v '^$') <(printf '%s\n' "$allowed"))"

if [ -n "$allowed" ]; then
    echo "Allowed by $allowlist:"
    while read -r id; do echo "  $id"; done <<<"$allowed"
fi
if [ -n "$blocking" ]; then
    echo "High or critical advisories:"
    while read -r id; do echo "  $id"; done <<<"$blocking"
    exit 1
fi
echo "OK: no blocking advisories"
