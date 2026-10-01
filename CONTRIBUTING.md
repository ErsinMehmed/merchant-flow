# Contributing

## Scope and design

Keep changes focused on one behavior or concern. Explain the problem, the resulting behavior, and material tradeoffs. Follow the existing architecture and language conventions.

- Keep HTTP handling, authorization, payment rules, and persistence responsibilities clear.
- Introduce small interfaces when a real dependency boundary needs them.
- Avoid generic infrastructure for hypothetical future requirements.
- Keep all database migrations in PHP.
- Enforce permissions and tenant scope on the server for every operation.
- Use only fictional demo data and integer minor units for payment amounts.

## Workflow

1. Create a short branch such as `feat/charge-refunds`, `fix/tenant-scope`, or `docs/setup`.
2. Implement a focused change and meaningful tests for changed behavior.
3. Run the applicable checks and inspect the diff for secrets and unintended files.
4. Update documentation when setup, contracts, or behavior changes.
5. Open a pull request using the repository template.

Use descriptive commits such as `feat: enforce merchant charge scope` or `docs: document verified startup`. Do not mark a check as passing unless it was run. If a check is unavailable, state why.

Language-specific formatting and executable test commands will be added with the corresponding toolchains. Until then, do not add CI steps that imply application coverage exists.

## Dependencies and credentials

Commit dependency lock files once generated. Never commit `.env` files, Composer `auth.json`, access tokens, or Nova credentials. Document required environment variable names using `.env.example` when configuration is implemented.
