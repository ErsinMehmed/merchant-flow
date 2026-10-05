# Notes

## Key decisions

- **Shared schema and permissions.** PHP owns migrations and seeders; both services use the same MySQL and Spatie tables (`web` guard, `user` morph alias). Nova's user flags sync to roles. Go reloads permissions on every request, so changes apply on the next API call.
- **Tenant boundaries.** Admins see all accounts; others see direct `account_user` or `agent_user` → `agent_account` links, without inheriting sub-agent accounts. Missing permission returns `403` before lookup; out-of-scope records return `404`.
- **Revocable sessions.** Go verifies Laravel bcrypt passwords and issues opaque tokens; only SHA-256 hashes are stored. The portal uses an `HttpOnly`, `SameSite=Strict` cookie and `Origin`/`Sec-Fetch-Site` checks for writes, allowing revocation without a PHP API.
- **Charge correctness.** PHP and Go implement capture, refund and void; 32 shared contract cases and MySQL amount constraints check the behavior. Writes lock rows and store idempotency results transactionally; ten concurrent refunds are tested. Partial refunds stay `captured`; disputed charges cannot be refunded.
- **Delivery.** With authorized Nova credentials outside Git ([reviewer setup](reviewer-setup.md)), `docker compose up` builds, migrates and seeds the stack. CI checks formatting and frontend tests; Nova-dependent jobs run PHPStan, integration tests and Playwright when credentials are available. Photos are stored in MySQL for PHP/Go access.

## Assumptions

- Admins may use the merchant portal. Supported currencies (USD, EUR, GBP, CAD) have two decimal places.

## With more time

- Record each refund in a ledger for audit and reporting; add keyset pagination for large charge lists, a shared login rate limiter for multiple API instances, and a charge timeline.

## AI use

I used AI coding assistants (OpenAI Codex, then Anthropic's Claude Code) for scaffolding, implementation, tests and documentation. I directed the design, reviewed the changes and verified the results. I can explain the submitted code and its trade-offs.

## Time spent

Approximately 25 hours of focused work across six days:

- Planning and architecture: 2 hours.
- Project, Docker and database setup: 3 hours.
- Implementation and integration of the PHP, Go and React components: 11 hours.
- Automated tests, end-to-end checks and fixes: 5 hours.
- Optimizations and improvements: 4 hours.
