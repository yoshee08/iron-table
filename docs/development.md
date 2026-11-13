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
| `docker/Caddyfile` | Web server: the PHP app and the live-update path `/app/*` on one port |
| `docker/php/iron-table.ini` | PHP settings for production |
| `.env.example` | All settings, with comments. Copy it to `.env`. |
| `scripts/setup.sh` | Makes `.env` with random secrets (first start) |
| `scripts/backup.sh`, `scripts/restore.sh` | Back up and restore the database and uploaded files |
| `scripts/bootstrap-laravel.sh` | One-time script that made the Laravel skeleton |
| `docs/` | Design specification and guides |

## 3. Bootstrap the Laravel skeleton (one time only)

Do this procedure one time for the project. Do not do it again after the skeleton is in Git.

The skeleton was made on 13 November 2026 with a one-time GitHub workflow (now removed). Do not do this procedure again.

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
- `./scripts/backup.sh` writes one file to `backups/` with the database and the uploaded files.
- `./scripts/restore.sh backups/<file>.tar` replaces all data with the backup. It asks first.
- A backup does not contain `.env`. Keep a safe copy of `APP_KEY`. The restore needs the same `APP_KEY`.

## 7. First administrator

- `scripts/setup.sh` writes `INITIAL_ADMIN_EMAIL` and a random `INITIAL_ADMIN_PASSWORD` to `.env`.
- On the first start, the app container makes this account. It does this only when no user exists.
- After the first login, remove `INITIAL_ADMIN_PASSWORD` from `.env`.
- Note: the login screen does not exist yet. Roles do not exist yet (spec section 10).

## 8. Security defaults

- The ports listen on `127.0.0.1` only. Set `APP_BIND` to change this.
- The containers run as a non-root user (UID 10001) with `no-new-privileges`.
- `APP_DEBUG` is `false`. Do not set it to `true` on a server that other people can reach.
- The containers send no telemetry.

## 9. Live updates

- Browsers use one port only: `APP_PORT` (default 8080).
- `docker/Caddyfile` forwards the path `/app/*` to the `reverb` service. Do not use the URL prefix `/app` for application routes.
- The page gives the browser the public Reverb key at run time. One image works for any host name.

## 10. Known items

| ID | Item | Plan |
| --- | --- | --- |
| K2 | TLS is not in the image yet. | Use a reverse proxy in front of port 8080 now. Add automatic TLS to `docker/Caddyfile` later. |
| K3 | The root file system is not read-only yet (spec SEC-10). | Make it read-only after the first full test, with tmpfs for temporary files. |
| K5 | CI starts the full stack on amd64 only. The arm64 image is built and checked (PHP and extensions) under emulation, but the stack is not started on arm64. Nobody has run it on a real server yet. | Do the first server start with section 4. |
