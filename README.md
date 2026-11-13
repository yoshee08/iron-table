# IRon Table

A self-hosted app for incident response tabletop exercises.

## You need

[Docker](https://docs.docker.com/get-docker/) (with Docker Compose).

## Start

```sh
git clone https://github.com/yoshee08/iron-table.git
cd iron-table
./scripts/setup.sh
docker compose up -d
```

Open **http://localhost:8080**

The first start takes a few minutes. Later starts take seconds.

`setup.sh` shows the first administrator email and password. Save them.

## Stop

```sh
docker compose down
```

Your data is kept.

## Other commands

| To do this | Run this |
| --- | --- |
| Start again | `docker compose up -d` |
| See if it is running | `docker compose ps` |
| See the logs | `docker compose logs -f` |
| Update to the latest version | `git pull && docker compose up -d --build` |
| Back up all data | `./scripts/backup.sh` |
| Restore a backup | `./scripts/restore.sh backups/<file>.tar` |
| Delete everything, including all data | `docker compose down -v` |

## More

- [Development guide](docs/development.md): settings, optional services, security
- [Design specification](docs/design-specification.html)

## License

Code: [AGPL-3.0](LICENSE). Scenarios: [CC BY 4.0](LICENSE-CONTENT).
