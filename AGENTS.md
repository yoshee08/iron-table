<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Rules for AI coding assistants — IRon Table

These rules apply to every AI assistant that works in this repository. They replace the default Laravel skeleton instructions.

## 1. Do not install software

- Do not install PHP, Composer, Node.js, Laravel Boost or other tools on the host computer.
- Do not run `curl ... | bash` or similar install commands.
- All build, test and run steps use Docker (`compose.yaml`, `docker/Dockerfile`) or GitHub Actions (`.github/workflows/`).
- If a task needs a tool that is not available, stop and tell the user.

## 2. Check work before each commit

1. Check shell scripts with `bash -n` or `sh -n`.
2. Check `compose.yaml` with `docker compose config` and a test `.env` outside the repository.
3. Check YAML and JSON files with a parser.
4. Scan the change for secrets, real names and real contact data.
5. Write in the commit message what you checked and what you did not check.
6. After the push, read the CI result. A failed CI run is not complete work.

## 3. Commits

- Make one commit for each logical change. Push it.
- Never commit `.env`, keys, database dumps, `vendor/` or `node_modules/`.
- Never commit real organization evidence (for example IR plans, screenshots, contact lists). Sample data must be fictional (spec SEC-14).

## 4. Branches and merges

- Do not push to `main`. Make a branch and open a pull request.
- Merge only when all CI jobs pass. GitHub cannot enforce this on a free private repository, so obey this rule.

## 5. Source of truth

- The design specification (`docs/design-specification.html`) controls the design.
- The development guide (`docs/development.md`) controls the build and start procedures.
- If code and specification do not agree, tell the user. Do not change the specification without approval.
- The specification is edited in Claude Docs. After each change, export it to `docs/design-specification.html` in the same pull request.

## 6. Code and documents

- Keep the codebase small and easy to review. Every file, package and function must be used by the application, a test, CI or the documentation.
- Do not add example code, commented-out code or unused settings. When the last use of something goes, remove it in the same pull request.
- Keep workflow files short. Put more than a few lines of shell in `.github/ci/`, so shellcheck checks it.
- PHP code in `src/` must pass Laravel Pint (`vendor/bin/pint --test`). CI checks this.

- Our own source files start with `SPDX-License-Identifier: AGPL-3.0-or-later`. Scenario and mapping files use `CC-BY-4.0`.
- Write documentation in ASD-STE100 Simplified Technical English: short sentences, active voice, one instruction per sentence.
- Use the versions in `src/composer.lock`, `src/package-lock.json` and the `FROM` lines of `docker/Dockerfile`. Do not change a version without a reason in the commit message.

## 7. Keep it simple (KISS)

- Use the simplest solution that meets the specification.
- Prefer fewer files, steps, services, settings and options.
- Do not add features, abstractions or options that nobody asked for.
- Make one pull request for each change. Do not stack pull requests.
- If the specification asks for something complex, tell the user and propose a simpler option.

## 8. Repository layout

- `src/` holds the Laravel application. Laravel files and folders go there, not in the repository root.
- The root holds only what users or tools need there: README, licenses, `compose.yaml`, `.env.example`, ignore files, `AGENTS.md` and `CLAUDE.md`.
