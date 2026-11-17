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
| `src/` | The Laravel application (PHP code, views, tests, Composer and npm files) |
| `compose.yaml` | The Docker Compose deployment: services, volumes and profiles |
| `docker/Dockerfile` | The one image for the three roles: `app`, `reverb` and `worker` |
| `docker/entrypoint.sh` | Starts the role that the container receives |
| `docker/Caddyfile` | Web server: the PHP app and the live-update path `/app/*` on one port |
| `docker/php/iron-table.ini` | PHP settings for production |
| `.env.example` | All settings, with comments. Copy it to `.env`. |
| `scripts/setup.sh` | Makes `.env` with random secrets (first start) |
| `scripts/backup.sh`, `scripts/restore.sh` | Back up and restore the database and uploaded files |
| `.github/` | CI workflows, CI check scripts (`.github/ci/`), Dependabot, security policy |
| `docs/` | Design specification and guides |

## 3. First start

1. Run `./scripts/setup.sh`. It makes `.env` with new random secrets. It does not change an `.env` that exists.
2. Optional: change other settings in `.env`, for example `APP_URL`.
3. Start the services: `docker compose up -d`. The first start builds the image.
4. Wait until all services show `healthy`: `docker compose ps`.
5. Open `APP_URL` in a browser. The default is http://localhost:8080.

## 4. Daily commands

| Task | Command |
| --- | --- |
| Start | `docker compose up -d` |
| Stop | `docker compose down` |
| Show logs | `docker compose logs -f` |
| Run an Artisan command | `docker compose exec app php artisan <command>` |
| Start with Redis | `docker compose --profile redis up -d` |
| Start with local AI | `docker compose --profile ai up -d` |
| Rebuild after a code change | `docker compose build && docker compose up -d` |

## 5. Change dependencies

1. Open GitHub > Actions > **Update dependencies** > Run workflow.
2. Select `composer` or `npm`. Type the arguments, for example `require vendor/package:^1.2`.
3. Type a new branch name. The workflow pushes the changed lock files to it.
4. Open a pull request from that branch. CI tests it.

## 6. Data

- The database is in the volume `pgdata`.
- Uploaded files are in the volume `storage`.
- `docker compose down` keeps the volumes. `docker compose down -v` deletes them and all data.
- `./scripts/backup.sh` writes one file to `backups/` with the database and the uploaded files.
- `./scripts/restore.sh backups/<file>.tar` replaces all data with the backup. It asks first.
- A backup does not contain `.env`. Keep a safe copy of `APP_KEY`. The restore needs the same `APP_KEY`.

## 7. First administrator and users

- `scripts/setup.sh` writes `INITIAL_ADMIN_EMAIL` and a random `INITIAL_ADMIN_PASSWORD` to `.env`.
- On the first start, the app container makes this account. It does this only when no user exists.
- After the first login, remove `INITIAL_ADMIN_PASSWORD` from `.env`.
- The account has the Administrator role. Log in at `APP_URL/login`, then turn on two-factor authentication in **Account**.
- Administrators and moderators must turn on two-factor authentication. Until they do, the application shows only the **Account** page.
- To add a user, open **Users**, type the name, email and role, and select **Add the user**. Give the setup link to the user. The link lets the user choose a password and works for 60 minutes. To make a new link, select **New setup link**.

## 8. Security defaults

- The ports listen on `127.0.0.1` only. Set `APP_BIND` to change this.
- The containers run as a non-root user (UID 10001) with `no-new-privileges`.
- `APP_DEBUG` is `false`. Do not set it to `true` on a server that other people can reach.
- The containers send no telemetry.
- The web server sends security headers: a Content Security Policy (scripts, styles and data from this server only, no frames), `X-Content-Type-Options`, `X-Frame-Options` and `Referrer-Policy`. CI checks them.

## 9. Live updates

- Browsers use one port only: `APP_PORT` (default 8080).
- `docker/Caddyfile` forwards the path `/app/*` to the `reverb` service. Do not use the URL prefix `/app` for application routes.
- The page gives the browser the public Reverb key at run time. One image works for any host name.

## 10. Known limitations

The design specification lists the open limitations and their plans: `docs/design-specification.html`, section 15.4.
