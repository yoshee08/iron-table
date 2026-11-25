# syntax=docker/dockerfile:1
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Local AI pictures (compose profile "images"): the stable-diffusion.cpp server
# (sd-server), CPU only. Built from a pinned commit on the computer that runs it:
#   - GGML_NATIVE: optimized for this CPU (do not copy the image to another computer)
#   - BUILD_JOBS compile jobs (the upstream image starts unlimited jobs, which uses
#     all memory), no web UI, no WebP or WebM
#   - the server runs as a non-root user

FROM ubuntu:24.04 AS build
ARG SD_COMMIT=79dc7ca9074d79419a46380cdb95a4524db9b74c
ARG BUILD_JOBS=2
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get install -y --no-install-recommends build-essential cmake git ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /src
RUN git init -q . \
    && git remote add origin https://github.com/leejet/stable-diffusion.cpp.git \
    && git fetch -q --depth 1 origin "${SD_COMMIT}" \
    && git checkout -q FETCH_HEAD \
    && git submodule update -q --init ggml
RUN cmake -B build -DCMAKE_BUILD_TYPE=Release -DGGML_NATIVE=ON \
        -DSD_WEBP=OFF -DSD_WEBM=OFF -DSD_SERVER_BUILD_FRONTEND=OFF \
    && cmake --build build --target sd-server --parallel "${BUILD_JOBS}"

FROM ubuntu:24.04
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get install -y --no-install-recommends libgomp1 \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --uid 10002 --no-create-home --shell /usr/sbin/nologin sd
COPY --from=build /src/build/bin/sd-server /usr/local/bin/sd-server
USER sd
WORKDIR /tmp
EXPOSE 1234
ENTRYPOINT ["sd-server"]
