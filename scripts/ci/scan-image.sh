#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# CI image scan (spec SEC-13): report fixable HIGH findings, fail on fixable CRITICAL.

set -euo pipefail

image="${1:-iron-table:local}"
trivy() {
    docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy:0.76.1 \
        image --scanners vuln --ignore-unfixed --no-progress "$@" "$image"
}

trivy --severity HIGH --exit-code 0
trivy --severity CRITICAL --exit-code 1
