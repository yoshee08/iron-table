# IRon Table

IRon Table is an open-source, self-hosted application for incident response (IR) tabletop exercises.

- A live moderator controls each exercise.
- Participants use the IR plan of their own organization to make decisions.
- AI assistance is optional. The application works fully without it.

## Status

Build setup phase. The Docker build files are ready. The Laravel skeleton is not generated yet.

| Document | Contents |
| --- | --- |
| [`docs/design-specification.html`](docs/design-specification.html) | Approved design specification |
| [`docs/development.md`](docs/development.md) | How to bootstrap, build and start the application |

## Technology

PHP 8.4, Laravel 13, Livewire 4, Laravel Reverb, PostgreSQL 16 with pgvector, FrankenPHP, Docker Compose.

## License

- Code: [AGPL-3.0-or-later](LICENSE)
- Scenarios and framework mappings: [CC BY 4.0](LICENSE-CONTENT)
