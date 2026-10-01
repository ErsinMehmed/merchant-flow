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

## Formatting

From the repository root:

```sh
docker compose run --rm format
docker compose run --rm format --check
```

The first command fixes formatting; the second checks it without changing source files. The tool image builds automatically on first use. Run `docker compose build format` after changing its dependencies or script.

PHP uses Laravel Pint with the Laravel preset. Go uses `gofmt`. Prettier formats JavaScript/TypeScript, CSS, HTML, JSON, YAML, and Markdown. Versions are locked in `tools/format/` and the Dockerfile. Dockerfiles, shell scripts, and Nginx/INI files follow `.editorconfig` and are reviewed manually.

GitHub Actions runs the same check for pushes and pull requests.

## Tests

Test commands are listed in the [README](README.md#tests). Changes to charge rules belong in `contracts/charge-operations.json` first, so the PHP and Go suites both cover them. When an API behavior visible to reviewers changes, update `scripts/smoke-test.sh` as well.

CI runs the portal and Go unit checks on every push. The PHP tests, Go integration tests and the full-stack smoke test need the `NOVA_USERNAME` and `NOVA_LICENSE_KEY` repository secrets and are skipped without them.

## Dependencies and credentials

Commit dependency lock files once generated. Keep local settings in `.env` and Nova package credentials in `.secrets/composer-auth.json`; both are ignored. Commit only the example files with empty credential fields. Never add access tokens or license keys to source files.

See [reviewer setup](docs/reviewer-setup.md) for Nova access and configuring a fresh checkout.
