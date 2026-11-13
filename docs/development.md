<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# IRon Table — Development and deployment guide

This guide uses ASD-STE100 Simplified Technical English. It tells you how to build and start IRon Table with Docker.

## 1. Requirements

- A computer with Docker Engine 24 or later and Docker Compose v2.
- Git.
- No PHP, Composer or Node.js on the computer. All build steps run in containers.

## 2. Repository layout

| Path | Contents |
| --- | --- |
| `compose.yaml` | The Docker Compose deployment: services, volumes and profiles |
| `docker/Dockerfile` | The one image for the three roles: `app`, `reverb` and `worker` |
| `docker/entrypoint.sh` | Starts the role that the container receives |
| `docker/php/iron-table.ini` | PHP settings for production |
| `.env.example` | All settings, with comments. Copy it to `.env`. |
| `scripts/setup.sh` | Makes `.env` with random secrets (first start) |
| `scripts/bootstrap-laravel.sh` | One-time script that made the Laravel skeleton |
| `docs/` | Design specification and guides |

## 3. Bootstrap the Laravel skeleton (one time only)

Do this procedure one time for the project. Do not do it again after the skeleton is in Git.

The skeleton was made on 13 November 2026 with the GitHub workflow **Bootstrap Laravel skeleton**. Do not do this procedure again.

1. Clone the repository.
2. Go to the repository folder.
3. Run `scripts/bootstrap-laravel.sh`.
4. Read the summary. Add the settings that the script lists as missing to `.env.example`.
5. Run `git status` and examine the new files.
6. Commit the new files.

The script makes Laravel 13 with Livewire 4 and Laravel Reverb. It never replaces a file that is already in the repository.

## 4. First start

1. Run `./scripts/setup.sh`. It makes `.env` with new random secrets. It does not change an `.env` that exists.
2. Optional: change other settings in `.env`, for example `APP_URL`.
3. Start the services: `docker compose up -d`. The first start builds the image.
4. Wait until all services show `healthy`: `docker compose ps`.
5. Open `APP_URL` in a browser. The default is http://localhost:8080.

## 5. Daily commands

| Task | Command |
| --- | --- |
| Start | `docker compose up -d` |
| Stop | `docker compose down` |
| Show logs | `docker compose logs -f` |
| Run an Artisan command | `docker compose exec app php artisan <command>` |
| Start with Redis | `docker compose --profile redis up -d` |
| Start with local AI | `docker compose --profile ai up -d` |
| Rebuild after a code change | `docker compose build && docker compose up -d` |

## 6. Data

- The database is in the volume `pgdata`.
- Uploaded files are in the volume `storage`.
- `docker compose down` keeps the volumes. `docker compose down -v` deletes them and all data.

## 7. Security defaults

- The ports listen on `127.0.0.1` only. Set `APP_BIND` to change this.
- The containers run as a non-root user (UID 10001) with `no-new-privileges`.
- `APP_DEBUG` is `false`. Do not set it to `true` on a server that other people can reach.
- The containers send no telemetry.

## 8. Known items

| ID | Item | Plan |
| --- | --- | --- |
| K1 | The browser settings for Reverb (`VITE_REVERB_*`) go into the front-end assets when the image is built. `.env` is not in the build. One image cannot serve different host names. | Give these values to the browser at run time (a meta tag), not at build time. |
| K2 | TLS is not in the image yet. | Use a reverse proxy now. Add a FrankenPHP Caddyfile with automatic TLS later. |
| K3 | The root file system is not read-only yet (spec SEC-10). | Make it read-only after the first full test, with tmpfs for temporary files. |
| K4 | The backup and restore commands (spec section 11.3) do not exist yet. | Add them with the first migrations. |
| K5 | CI builds and starts the stack on GitHub runners only (amd64). Nobody has run it on a server or on arm64 yet. | Do the first server start with section 4. Add an arm64 build to CI. |
