# MerchantFlow

A payments platform built around a PHP administration portal, a React merchant portal, and a Go API sharing a MySQL database.

## Project status

The Docker development environment is available: MySQL, PHP-FPM, a Go health endpoint, and Nginx. The homepage is a temporary setup page. Laravel/Apiato/Nova, the React portal, payment operations, migrations, and application tests are not implemented yet.

This project originates from a Payarc take-home assignment and is being developed as a portfolio project. Payments will use fictional demo data and record simulated transactions; no real card processing is planned.

## Planned architecture

| Component | Stack | Responsibility |
| --- | --- | --- |
| Administration | Laravel / Apiato / Nova | Own the schema, manage users, merchants, agents, and permissions |
| Merchant portal | React / AG Grid | Display transactions and initiate permitted payment actions through Go |
| API | Go | Authenticate requests, enforce permissions and tenant scope, execute payment operations |
| Database | MySQL / Spatie tables | Shared application data and a single source of permission assignments |

PHP owns all migrations. Go accesses the shared schema without creating or migrating tables. Authorization must read current Spatie assignments and restrict every resource operation to the caller's accessible accounts. Requests for another tenant's resources return `404`.

Payment amounts use integer minor units. Refund and void operations must preserve valid amounts and statuses under concurrent requests.

## Development milestones

- [ ] Confirm compatible dependency versions and Laravel Nova access.
- [x] Set up the Docker runtime and database connectivity checks.
- [ ] Add automatic PHP migrations and demo seeding.
- [ ] Implement PHP migrations, relationships, permissions, and repeatable demo seeders.
- [ ] Implement Go authentication, tenant scoping, and payment operations with tests.
- [ ] Implement Nova administration and the React merchant portal.
- [ ] Verify clean startup, restart behavior, and complete user flows.
- [ ] Document demo accounts, test commands, design decisions, AI use, and limitations.

## Local setup

Install Docker Desktop and use Linux containers. From the repository root:

```sh
docker compose up
```

Open [localhost:8080](http://localhost:8080). The first run downloads images and builds PHP extensions and the Go binary. No local PHP, Go, Node.js, or Composer installation is needed.

To start in the background and wait for readiness:

```sh
docker compose up --build --detach --wait
```

Only Nginx is published, on `127.0.0.1:8080`. MySQL, PHP-FPM, and Go are reachable inside the Compose network. MySQL creates the empty demo database on first start and stores it in `merchant-flow_mysql-data`; there are no application tables or seeded accounts yet. PHP migrations and seeders will be added with the monolith.

| URL | Current behavior |
| --- | --- |
| `/` | Temporary setup page |
| `/health` | Nginx health, `200` |
| `/health/php` | PHP database connectivity, `200` or `503` |
| `/api/health` | Go database connectivity, `200` or `503` |
| `/admin` | `503` until Nova is installed |

Services wait for their dependencies' health checks before startup, using [Compose startup conditions](https://docs.docker.com/compose/how-tos/startup-order/).

### Configuration

Compose uses local demo defaults, so copying `.env.example` is optional. For overrides, copy it to `.env` and set `APP_PORT` or the database settings. The defaults are not production credentials. Changing database credentials in `.env` does not change users already stored in an existing MySQL volume.

Base images are pinned by digest. PHP uses 8.4, Go uses 1.26, MySQL uses 8.4, and the Node tool uses 24. Package-level compatibility and Nova installation will be verified when the monolith is added.

### Useful commands

```sh
docker compose ps
docker compose logs --tail=100
docker compose down
```

`docker compose down` preserves the database volume. After source changes, use `docker compose up --build --detach --wait` to rebuild the runtime images.

Composer and Node are optional tool services; they run only when requested:

```sh
docker compose run --rm composer --version
docker compose run --rm node --version
docker compose run --rm --entrypoint npm node --version
```

The tools mount `php/` and `merchant-portal/` respectively, so generated project files are saved in the repository. Runtime services use files copied into their images.

Laravel Nova requires authorized package access. Credentials must never be committed or embedded in Docker image layers. The final setup will document how this dependency is supplied.

## Engineering approach

Keep HTTP handlers small and separate permission checks from payment operations. Use framework conventions and add abstractions only when they solve a specific problem. Tests will focus on tenant isolation, permission changes, and concurrent refunds and voids.

See [CONTRIBUTING.md](CONTRIBUTING.md) for development and review expectations and [SECURITY.md](SECURITY.md) for handling vulnerabilities and secrets.

## Attribution and AI assistance

Based on a Payarc take-home assignment. MerchantFlow is an independent portfolio project. AI assisted with environment checks, documentation, and the Docker setup. The final project note will describe its use during implementation.
