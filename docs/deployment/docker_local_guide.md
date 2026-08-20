# Local Docker Deployment

SaQshi can run locally with one application container and one MySQL container.
Both UI and API remain under the same local origin, preserving the existing
session and CSRF behaviour.

## Local URLs

| Area | URL |
| --- | --- |
| SaQshi login | `http://localhost:8082/ui/login.html` |
| SaQshi dashboard | `http://localhost:8082/ui/dashboard.html` |
| CSRF API check | `http://localhost:8082/api/auth/v1/csrf.php` |
| Local MySQL | `127.0.0.1:3307` |

## Services

| Service | Container role |
| --- | --- |
| `app` | Apache, PHP 8.2, UI, API and GitBook files. |
| `db` | MySQL 8.0, initialized with `api/sql/schema/001_base_schema.sql`. |

The local database, uploads and API storage use named Docker volumes. They
remain available after the containers stop.

## Start

Run from the `open_source` directory:

```text
docker compose up --build
```

The first run downloads the PHP and MySQL images, builds the application image,
creates the database volume and imports the sanitized base schema.

For background execution:

```text
docker compose up --build -d
```

## Verify

```text
docker compose ps
docker compose logs app
docker compose logs db
```

The `db` service becomes healthy before the application container starts.

## Stop and Reset

```text
docker compose down
```

The named volumes remain intact after this command.

For a completely fresh local database and empty local storage:

```text
docker compose down -v
```

This removes only the `saqshi-local` Docker volumes created by this Compose
project. It does not alter the repository files or any external database.

## Local Development Values

The Compose configuration injects local-only database credentials and an
encryption key directly into the containers. They are for local development
only and are not production credentials. Production deployments use external
secrets instead.
