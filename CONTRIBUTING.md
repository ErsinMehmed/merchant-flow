# Contributing

## Scope and design

Keep each pull request focused on one change. Explain what changed and how you checked it.

- Keep all database migrations in PHP.
- Enforce permissions and tenant scope on the server for every operation.
- Use only fictional demo data and integer minor units for payment amounts.
- Keep payment rules separate from HTTP handling. Add interfaces when needed, rather than wrapping every dependency.

## Workflow

1. Create a short branch such as `feat/charge-refunds`, `fix/tenant-scope`, or `docs/setup`.
2. Implement the change and test the affected behavior.
3. Run the relevant checks and review the diff.
4. Update documentation when setup, contracts, or behavior changes.
5. Open a pull request using the repository template.

Use descriptive commits such as `feat: enforce merchant charge scope` or `docs: document startup`. In the pull request, list checks you ran and any you could not run.

Formatting and test commands will be documented when the application toolchains are added.

## Dependencies and credentials

Commit dependency lock files once generated. Keep local settings in `.env` and Nova package credentials in `.secrets/composer-auth.json`; both are ignored. Commit only the example files with empty credential fields. Never add access tokens or license keys to source files.
