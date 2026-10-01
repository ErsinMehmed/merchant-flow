# Notes

## Key decisions

- **One permission model.** Spatie's tables are the only source of truth. Both apps use a single guard (`web`) and a stable morph alias (`user`), so the Go API reads exactly the rows Nova writes. Go loads roles and permissions on every request and caches nothing, which is what makes a change in Nova apply on the next API call. The `is_admin`/`is_agent`/`is_merchant` flags are what an administrator edits; an observer keeps the matching roles in sync, and authorization never reads the flags.
- **Scope before data.** Every charge and account query in Go goes through one scope filter (admin: all; otherwise `account_user` plus `agent_user` → `agent_account`). Permission checks run first and do not depend on the resource, so `403` reveals nothing; anything outside the scope is `404`, identical to a missing row.
- **Authentication in Go, schema in PHP.** Go verifies the Laravel bcrypt hash and issues an opaque token; only its SHA-256 hash is stored in `api_tokens`, a table PHP migrates. The browser gets an `HttpOnly`, `SameSite=Strict` cookie (no token in JavaScript), and cross-site writes are refused using `Sec-Fetch-Site`/`Origin`. I chose this over JWTs because sessions must be revocable from Nova immediately, and over Passport because the portal may only talk to Go.
- **Charge rules in two languages, one contract.** The rules live in the PHP `Charge` model (used by Nova actions) and in Go's `charge` package. `contracts/charge-operations.json` holds 32 cases that both test suites run, and MySQL CHECK constraints guard the amount invariants whatever writes the row. Changes lock the charge row (`SELECT … FOR UPDATE`), and a test fires ten concurrent refunds to prove the total never exceeds the captured amount.
- **Idempotency.** Payment writes accept an `Idempotency-Key`. The stored response is inserted in the same transaction as the change, so a retry either replays it or runs for the first time; concurrent duplicates collapse to one.
- **Statuses.** A partial refund keeps the charge `captured` with `amount_refunded > 0`; it becomes `refunded` (and `refunded = true`) once nothing is left. Void only applies to `authorized` charges; disputed charges cannot be refunded; suspended or disabled merchants cannot take new charges but can still settle existing ones. I added `capture` (with a `charges.capture` permission) so that authorize-only charges are useful.
- **Apiato kept lean.** I removed the starter's Passport, API endpoints and documentation generator, since nothing used them and they would have been a second, untested way in.

## Assumptions

- A parent agent does not see the merchants of its sub-agents; only directly served merchants count.
- An admin can also use the merchant portal and sees every merchant there.
- All supported currencies have two decimals (USD, EUR, GBP, CAD).
- Nova is reviewed with the reviewer's own license; it is not redistributed.

## With more time

- Refunds as their own ledger rows (one per refund) instead of a running total, for reporting and audit.
- Keyset pagination for very large charge lists, and a shared store for the login rate limiter if the API runs on more than one instance.
- Browser end-to-end tests in CI (I verified the flows with a headless browser locally) and PHPStan in CI.
- A charge timeline in the portal, built on Nova's action log and API events.

## AI use

I used AI coding assistants (OpenAI Codex, then Anthropic's Claude Code) for scaffolding, implementation, tests and documentation, with me directing the design, reviewing every change, and running and checking the results. I can explain and defend each part of the code.

Time spent: _fill in_.
