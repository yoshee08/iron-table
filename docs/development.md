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
| `scripts/setup.sh`, `scripts/setup.ps1` | Makes `.env` with random secrets (first start). `setup.ps1` is for Windows (PowerShell). |
| `scripts/backup.sh`, `scripts/restore.sh` | Back up and restore the database and uploaded files |
| `.github/` | CI workflows, CI check scripts (`.github/ci/`), Dependabot, security policy |
| `docs/` | Design specification and guides |

## 3. First start

1. Run `./scripts/setup.sh`. On Windows, run `powershell -ExecutionPolicy Bypass -File scripts\setup.ps1`. It makes `.env` with new random secrets. It does not change an `.env` that exists.
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

### IR plan

- Moderators and administrators upload the IR plan in **IR plan** > **Upload a plan** (PDF with selectable text, DOCX or Markdown; 20 MB maximum).
- IRon Table splits the plan into numbered sections. Check the sections and correct the ones that are wrong.
- Every logged-in user can read and search the plan. Each upload is a new version.
- A scanned PDF (images only, no text) cannot be read. Export the plan from the original document instead.

### Scenarios

- Only moderators and administrators see scenarios. Participants must not see the injects before the session.
- A scenario is one YAML file. The format is in the design specification, section 6. The example is `src/resources/scenarios/phish-to-mailbox-rule.yaml` (also in **Scenarios** > **Import a scenario**).
- Before you check a scenario, prepare the current IR plan:
  1. Open **IR plan**, then **Organization profile**. Type the values that scenarios use, for example the name of the CISO.
  2. On each section that a scenario expects, select **Correct this section** and type the plan concept, for example `severity_table`.
- A new plan version keeps the profile and the plan concepts of the version before it.
- The scenario page shows the consistency check. Correct all errors with **Edit the YAML**. To keep the old version, change `meta.version` and import the file again.

### Exercises

1. Open **Exercises** > **Prepare an exercise**. Select a scenario that passes the consistency check. The exercise keeps a copy of the scenario and of the current IR plan version.
2. Add the participants. Give each one an exercise role (participant, scribe or observer) and a plan role, for example CISO.
3. Open **Control**. Select **Start the session**.
4. Release the injects. The timeline marks the suggested next inject. You can release any inject, for example a branch.
5. Participants open **Live screen**. They see the injects and the artifacts, and send responses that cite plan sections.
6. Select **STOP** if a real event needs attention. Select **Resume** to continue, or **End the session**.

During the session:

- **Write a new inject** releases an inject that you write for an unexpected response.
- **Plan coverage** shows the expected plan concepts of the current inject that the responses cite (used), the ones that they do not cite (missed), and the responses without plan coverage.
- **Notes and logs**: write moderator notes (only moderators see them) and parking-lot topics for the debrief. Scribes write the communication, action and validation logs on their live screen. Each entry gets the real time and the simulated time.

Screens update immediately through the live connection. If the connection drops, they update every 30 seconds.

### AI assistance (optional)

- The AI writes scenario drafts in small steps: the outline and the facts, then one inject text, one artifact or one rubric at a time. Moderators check each step.
- The application works without AI. AI is off by default.
- The AI never sees the IR plan or the values of the organization profile.

To use the local AI (nothing leaves the server):

1. In `.env`, set `AI_ENABLED=true` and `AI_PROVIDER=ollama`.
2. Start with the AI service: `docker compose --profile ai up -d`.
3. Download the model one time: `docker compose exec ollama ollama pull qwen2.5:1.5b`.
4. Open **Scenarios** > **Draft with AI**.

To use Claude or DeepSeek, set `AI_PROVIDER`, `AI_BASE_URL`, `AI_API_KEY` and `AI_MODEL` in `.env` (examples are in `.env.example`). Then run `docker compose up -d`. The scenario text goes to that company.

Do not use DeepSeek if your organization handles Controlled Unclassified Information (CUI) or export-controlled data. DeepSeek processes data in China.

### AI pictures (optional)

Each picture artifact has an AI prompt, for example "an office door propped open with a chair at night". The image service makes the picture. The moderator chooses it, or uploads a picture instead.

To use the local image model (nothing leaves the server):

1. In `.env`, set `AI_IMAGE_PROVIDER=local`.
2. Start: `docker compose --profile images up -d`. The first start builds the image server for this CPU (about 20 minutes; `AI_IMAGE_BUILD_JOBS` compile jobs) and downloads the model files (about 5.2 GB). The download checks the SHA-256 of each file.
3. The model needs about 8 GB of memory. A picture takes about 5 to 10 minutes on a CPU. The queue worker makes it, so you can continue to work.

To use openference.com, set `AI_IMAGE_PROVIDER=openference`, `AI_IMAGE_API_KEY` and `AI_IMAGE_MODEL` (an image model from openference.com/models). The picture prompt goes to openference.com. Its website does not say who runs the service or how long it keeps data.

Claude and DeepSeek do not make pictures. When one of them is the text AI, **Add a picture prompt with AI** writes the prompt from the scenario facts.

A scenario with pictures downloads as a ZIP file: `scenario.yaml` and the folder `images/`. Import the ZIP file to restore it.

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
