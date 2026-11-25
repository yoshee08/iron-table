#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# End-to-end check of the local AI pictures (compose profile "images"):
# build the stable-diffusion.cpp server, download the model files (SHA-256
# checked), and make one picture with the same request as LocalImageProvider.

set -euo pipefail
cd "$(dirname "$0")/../.."

[ -f .env ] || ./scripts/setup.sh

stage() {
    echo "== $1 ($(date -u +%H:%M:%S), free memory $(free -m | awk '/Mem:/ {print $7}') MB, free disk $(df -h --output=avail / | tail -1))"
}

stage "1/4 Build the image server"
docker compose --profile images build images

stage "2/4 The server knows the options in compose.yaml"
help="$(docker run --rm --entrypoint /sd-server iron-table-images:79dc7ca --help 2>&1 || true)"
for option in --listen-ip --listen-port --model-dir --diffusion-model --llm --vae --cfg-scale; do
    grep -qF -- "$option" <<<"$help" || { echo "sd-server has no option $option"; exit 1; }
done

stage "3/4 Download the models and start the server"
docker compose --profile images up -d images
for _ in $(seq 1 60); do
    if docker run --rm --network iron-table_default curlimages/curl:8.22.0 -fs http://images:1234/v1/models >/dev/null; then
        break
    fi
    sleep 10
done

stage "4/4 Make one picture (this takes minutes on a CPU)"
start=$(date +%s)
docker run --rm --network iron-table_default curlimages/curl:8.22.0 -fsS --max-time 1800 \
    -H 'Content-Type: application/json' \
    -d '{"prompt":"An office door propped open with a chair at night, photo <sd_cpp_extra_args>{\"sample_params\":{\"sample_steps\":4,\"guidance\":{\"txt_cfg\":1.0}}}</sd_cpp_extra_args>","n":1,"size":"512x512","output_format":"png"}' \
    http://images:1234/v1/images/generations > /tmp/picture.json
python3 - <<'PY'
import base64, json
data = base64.b64decode(json.load(open("/tmp/picture.json"))["data"][0]["b64_json"])
assert data[:8] == b"\x89PNG\r\n\x1a\n", "not a PNG"
open("/tmp/picture.png", "wb").write(data)
print(f"PNG picture, {len(data)} bytes")
PY
echo "Picture made in $(( $(date +%s) - start )) seconds"
