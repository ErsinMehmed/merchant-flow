# MerchantFlow

A small payments platform built as a take-home assignment: a Laravel/Apiato/Nova admin portal, a React merchant portal backed by a Go API, and one shared MySQL database. The PHP application owns the schema and the Spatie roles and permissions; the Go API reads the same tables on every request, so a permission change made in Nova applies to the merchant portal immediately.

All data is fictional. Payments only record rows in `charges`; nothing is sent to a real processor and no card data exists anywhere.

## Requirements

- Docker Desktop (or Docker Engine) with Compose v2. Nothing else is needed on the host: PHP, Go and Node.js builds all run inside Docker.
- Credentials for an authorized [Laravel Nova](https://nova.laravel.com) license. Nova is a private Composer package, so the PHP image cannot be built without them.
- Free port `8080` on `127.0.0.1` (configurable, see [Configuration](#configuration)).

## Setup

1. Put the Nova credentials in an ignored local file. The file is passed to the image build as a BuildKit secret and never ends up in an image layer or in Git.

   macOS / Linux:

   ```sh
   mkdir -p .secrets
   cp docker/secrets/composer-auth.json.example .secrets/composer-auth.json
   ```

   Windows PowerShell:

   ```powershell
   New-Item -ItemType Directory -Path .secrets -Force
   Copy-Item docker/secrets/composer-auth.json.example .secrets/composer-auth.json
   ```

   Then edit `.secrets/composer-auth.json`: `username` is the Nova account email, `password` is the license key.

2. Start everything:

   ```sh
   docker compose up --build --detach --wait
   ```

   `docker compose up` on its own works too; `--detach --wait` returns once every service is healthy. The first build takes a few minutes (Composer, Go modules and npm packages are downloaded).

That is the whole setup. There is no manual migration, seeding or asset step.

## What happens on startup

| Step | Service  | What it does                                                                                                                                 |
| ---- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| 1    | `mysql`  | MySQL 8.4 with a named volume (`merchant-flow_mysql-data`).                                                                                  |
| 2    | `init`   | One-off job: `php artisan migrate --force`, then `php artisan db:seed --force`, then copies Nova's public assets for Nginx. Exits when done. |
| 3    | `php`    | PHP-FPM serving Nova. Starts only after `init` succeeded.                                                                                    |
| 4    | `go-api` | The merchant API. Starts only after `init` succeeded. It never creates or alters tables.                                                     |
| 5    | `web`    | Nginx: the built React portal, `/admin` to PHP, `/api` to Go. The only service with a published port.                                        |

Seeding is safe to repeat. Roles and permissions are created if missing, but existing role permissions are never reset, so changes made in Nova survive restarts. Demo users, merchants, agents and about 940 charges are created only on an empty database.

## URLs

| What                    | URL                                                                        |
| ----------------------- | -------------------------------------------------------------------------- |
| Merchant portal (React) | http://localhost:8080/                                                     |
| Admin portal (Nova)     | http://localhost:8080/admin                                                |
| Go API                  | http://localhost:8080/api/v1/… (health: `/api/health`)                     |
| Health checks           | `/health` (Nginx), `/health/php` (PHP + MySQL), `/api/health` (Go + MySQL) |

## Demo accounts

All demo users share the password **`MerchantFlowDemo!2026`**. It exists only for the fictional local data.

| Email                             | Role     | Admin portal     | Merchant portal                                                               |
| --------------------------------- | -------- | ---------------- | ----------------------------------------------------------------------------- |
| `admin@merchant-flow.test`        | admin    | Yes, full access | Every merchant, every action                                                  |
| `merchant@merchant-flow.test`     | merchant | No               | Northwind Coffee Roasters and Fabrikam Fitness; create, capture, refund, void |
| `merchant-two@merchant-flow.test` | merchant | No               | Contoso Books and Tailspin Toys (suspended: no new charges)                   |
| `agent@merchant-flow.test`        | agent    | No               | Read-only: the merchants Harbor Payments serves (Northwind, Contoso)          |
| `subagent@merchant-flow.test`     | agent    | No               | Read-only: Fabrikam Fitness (Summit Referral, a sub-agent of Harbor)          |

**Admin portal login:** open http://localhost:8080/admin and sign in with `admin@merchant-flow.test` / `MerchantFlowDemo!2026`. Agents and merchants get the same "credentials do not match" answer as a wrong password; Nova never starts a session for them.

The merchant portal's sign-in page has a "Demo accounts" list that fills the form for you.

## Things worth trying

- Sign in to the portal as `merchant@merchant-flow.test`, create a charge, refund part of it, then try to refund more than is left.
- Create a charge with "Capture now" unchecked, then capture or void it.
- In Nova, open **Roles → merchant** and detach `charges.refund`. In the portal, the Refund button disappears after the next reload, and the API answers `403` straight away.
- Unlink a user from a merchant in Nova (**Users → Merchant accounts**). That merchant's charges become `404` for the user on the next request.
- In Nova, **Users → Portal sessions** lists merchant portal sessions; deleting one signs that browser out.
- Ask the API for another merchant's charge: `404`, exactly like a charge that does not exist.

## Migrations and seeders

The PHP application owns the schema. Migrations live with the Apiato container they belong to:

| Path                                                           | Tables                                                                                             |
| -------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `php/app/Containers/AppSection/User/Data/Migrations`           | `users`, `sessions`, `password_reset_tokens`                                                       |
| `php/app/Containers/AppSection/Authorization/Data/Migrations`  | Spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |
| `php/app/Containers/AppSection/Agent/Data/Migrations`          | `agents`, `agent_user`                                                                             |
| `php/app/Containers/AppSection/Account/Data/Migrations`        | `accounts`, `account_user`, `agent_account`                                                        |
| `php/app/Containers/AppSection/Charge/Data/Migrations`         | `charges` (with CHECK constraints on amounts and status)                                           |
| `php/app/Containers/AppSection/Authentication/Data/Migrations` | `api_tokens` (merchant portal sessions issued by Go)                                               |
| `php/app/Ship/Migrations`                                      | `cache`, `idempotency_keys`                                                                        |

Seeders: `Authorization/Data/Seeders` (roles and permissions, every start) and `Demo/Data/Seeders` (demo data, empty database only).

Useful commands while the stack is running:

```sh
docker compose exec php php artisan migrate:status
docker compose exec php php artisan migrate --force   # apply new migrations
docker compose exec php php artisan db:seed --force   # re-run the (idempotent) seeders
```

To start again from a clean database, drop the volumes and start as usual. **This deletes all local data**, including anything changed in Nova:

```sh
docker compose down --volumes
docker compose up --build --detach --wait
```

## Tests

Each suite runs in its own container. The PHP and Go suites use a separate throwaway MySQL (`mysql-test`, on tmpfs), never the demo database.

```sh
docker compose --profile test run --rm php-test      # PHPUnit: domain rules, policies, Nova, seeders
docker compose --profile test run --rm go-test       # go vet + unit and MySQL integration tests
docker compose --profile test run --rm portal-test   # TypeScript, ESLint, Vitest
docker compose --profile test down                   # remove the test database afterwards
```

`go-test` first runs the PHP migrations and seeders against its own test database, so the Go tests always run against the real schema and the real role definitions.

`contracts/charge-operations.json` lists the charge state transitions (capture, refund, void, create) with expected amounts or error codes. Both the PHP and the Go suites run every case, which keeps the two implementations of the charge rules identical.

To check a running stack end to end through Nginx (health, both portals, sign-in, tenant scope, permissions, charge operations, idempotency), run the smoke test. It needs `curl` and `jq` and creates one small charge:

```sh
sh scripts/smoke-test.sh                                # http://localhost:8080
BASE_URL=http://localhost:8090 sh scripts/smoke-test.sh # another port
```

### Continuous integration

`.github/workflows/tests.yml` runs on every push and pull request:

| Job                                | What it runs                                                            | Needs Nova secrets |
| ---------------------------------- | ----------------------------------------------------------------------- | ------------------ |
| Merchant portal                    | `portal-test`: TypeScript, ESLint, Vitest                               | no                 |
| Go API                             | gofmt, `go vet`, unit tests                                             | no                 |
| PHP tests and Go integration tests | `php-test` and `go-test` against a test MySQL                           | yes                |
| Full stack                         | `docker compose up`, the smoke test, a restart and the smoke test again | yes                |

The PHP image cannot be built without Nova, so the last two jobs run only when the repository has the `NOVA_USERNAME` (Nova account email) and `NOVA_LICENSE_KEY` secrets; otherwise they are skipped with a notice. To add them:

```sh
gh secret set NOVA_USERNAME
gh secret set NOVA_LICENSE_KEY
```

Formatting (Prettier, Pint, gofmt) runs without Nova credentials:

```sh
docker compose run --rm format --check
```

## Roles and permissions

| Permission                                          | admin | merchant | agent            |
| --------------------------------------------------- | ----- | -------- | ---------------- |
| Admin portal (Nova)                                 | yes   | no       | no               |
| `charges.view`                                      | all   | own      | of own merchants |
| `charges.create`                                    | yes   | own      | no               |
| `charges.capture`                                   | yes   | own      | no               |
| `charges.refund`                                    | yes   | own      | no               |
| `charges.void`                                      | yes   | own      | no               |
| `merchants.manage`, `agents.manage`, `users.manage` | yes   | no       | no               |

These are the seeded defaults; administrators can change role permissions or grant direct permissions to a user in Nova. The user type flags (`is_admin`, `is_agent`, `is_merchant`) are edited in Nova and keep the matching Spatie roles in sync; authorization in both PHP and Go reads only the Spatie tables.

Scope: an admin sees every account; anyone else sees the accounts linked through `account_user` plus those served by their agents (`agent_user` → `agent_account`). A parent agent does not inherit its sub-agents' merchants.

## Merchant API

All endpoints are under `/api/v1` and exchange JSON. Amounts are integer minor units (cents).

| Method | Path                                                                                                   | Permission                                                                    |
| ------ | ------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------- |
| `POST` | `/auth/login`                                                                                          | — (`{email, password}`)                                                       |
| `POST` | `/auth/logout`                                                                                         | —                                                                             |
| `GET`  | `/me`                                                                                                  | signed in                                                                     |
| `GET`  | `/accounts`                                                                                            | `charges.view` or `charges.create`                                            |
| `GET`  | `/charges?page=&page_size=&sort=&status=&account_id=&currency=&q=&created_from=&created_to=&disputed=` | `charges.view`                                                                |
| `GET`  | `/charges/{id}`                                                                                        | `charges.view`                                                                |
| `POST` | `/charges`                                                                                             | `charges.create` (`{account_id, amount, currency, customer_email, capture?}`) |
| `POST` | `/charges/{id}/capture`                                                                                | `charges.capture`                                                             |
| `POST` | `/charges/{id}/refund`                                                                                 | `charges.refund` (`{amount?}`; omitted refunds the rest)                      |
| `POST` | `/charges/{id}/void`                                                                                   | `charges.void` (`{reason?}`)                                                  |

- **Authentication.** Go checks the email and password against `users` (Laravel bcrypt hashes) and issues a random token. Only its SHA-256 hash is stored, in `api_tokens`. The browser receives it as an `HttpOnly`, `SameSite=Strict` cookie; API clients can send `Authorization: Bearer <token>` with the token from the login response. Sessions expire after 8 hours. Login is rate limited.
- **Errors** always look like `{"error": {"code": "...", "message": "...", "fields": {...}}}`: `401` not signed in, `403` missing permission, `404` not found or outside your scope, `409` the charge's state does not allow the operation, `422` invalid input.
- **Idempotency.** Every `POST` that changes a charge accepts an `Idempotency-Key` header. A retry with the same key returns the stored response (`Idempotent-Replayed: true`) instead of running again; the key is recorded in the same transaction as the change. The portal sends one key per dialog.

Example with curl:

```sh
TOKEN=$(curl -s -X POST localhost:8080/api/v1/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"merchant@merchant-flow.test","password":"MerchantFlowDemo!2026"}' | jq -r .data.token)

curl -s "localhost:8080/api/v1/charges?status=captured&page_size=5" -H "Authorization: Bearer $TOKEN" | jq
```

## Configuration

Compose works without any `.env`. To change defaults, copy `.env.example` to `.env`:

| Variable                                                        | Default           | Purpose                       |
| --------------------------------------------------------------- | ----------------- | ----------------------------- |
| `APP_PORT`                                                      | `8080`            | Port published on `127.0.0.1` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` | local demo values | MySQL credentials             |

The Laravel application key is generated on first start and kept in the `merchant-flow_app-key` volume.

## Stopping

```sh
docker compose down             # stop; keeps the database
docker compose down --volumes   # stop and delete all local data
```

## Troubleshooting

- **`Missing Nova credentials`** or a `secret ... not found` error: `.secrets/composer-auth.json` is missing or empty; see [Setup](#setup).
- **Port 8080 is already in use:** set `APP_PORT=8081` in `.env`.
- **Errors after pulling a newer version over an old database:** an earlier development schema may still be in the volume. Reset it with `docker compose down --volumes`.
- **Logs:** `docker compose logs init` (migrations and seeding), `docker compose logs go-api`, `docker compose logs php`.

## Repository layout

```
php/              Laravel 12 + Apiato 13 + Nova 5: schema, admin portal, seeders, PHP tests
go-api/           Go API: cmd/api (entry point), internal/{auth,access,charge,idempotency,httpapi}
merchant-portal/  React 19 + AG Grid Community (Vite)
contracts/        Charge rule cases shared by the PHP and Go tests
docker/           Dockerfiles, Nginx config, test database init
```

See [docs/notes.md](docs/notes.md) for the main decisions, trade-offs and what I would do with more time. Code conventions are in [CONTRIBUTING.md](CONTRIBUTING.md); secret handling in [SECURITY.md](SECURITY.md).
