# MerchantFlow

A payments platform built around a PHP administration portal, a React merchant portal, and a Go API sharing a MySQL database.

## Project status

Initial repository setup. Application code, Docker packaging, migrations, and tests are not implemented yet. There is currently no runnable application.

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
- [ ] Build a Docker Compose environment with automatic initialization.
- [ ] Implement PHP migrations, relationships, permissions, and repeatable demo seeders.
- [ ] Implement Go authentication, tenant scoping, and payment operations with tests.
- [ ] Implement Nova administration and the React merchant portal.
- [ ] Verify clean startup, restart behavior, and complete user flows.
- [ ] Document demo accounts, test commands, design decisions, AI use, and limitations.

## Running and testing

The intended startup command is `docker compose up`. It is not available yet; working commands and demo credentials will be added after verification.

Laravel Nova requires authorized package access. Credentials must never be committed or embedded in Docker image layers. The final setup will document how this dependency is supplied.

## Engineering approach

Apply SOLID where it clarifies responsibilities and dependency boundaries. Apply KISS by using framework conventions and avoiding abstractions without a concrete purpose. Test authorization, tenant isolation, financial invariants, and database concurrency against meaningful scenarios.

See [CONTRIBUTING.md](CONTRIBUTING.md) for development and review expectations and [SECURITY.md](SECURITY.md) for handling vulnerabilities and secrets.

## Attribution and AI assistance

The implementation is based on a technical assignment from Payarc; this repository is not an official Payarc product. AI has assisted with environment inspection and repository documentation. Its use will be recorded as implementation progresses. Submitted code must be reviewed, understood, and verified by the developer.
