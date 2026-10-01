# Reviewer setup and Nova access

## Local development

Clone the repository and install Docker Desktop with Linux containers. The current runtime and formatting checks work without Nova credentials. The portals and payment features are still pending.

Copy `.env.example` to `.env` if you need different local settings. For Nova package installation, use authorized package access supplied for the assignment or your own Nova credentials.

Create the local credential file using the checked-in template:

```sh
mkdir -p .secrets
cp docker/secrets/composer-auth.json.example .secrets/composer-auth.json
```

On Windows PowerShell:

```powershell
New-Item -ItemType Directory -Path .secrets -Force
Copy-Item docker/secrets/composer-auth.json.example .secrets/composer-auth.json
```

Open the local file in your editor. Set `username` to the Nova account email and `password` to the license key. Composer receives it as a read-only secret. The file stays local and is not included in Git or submission archives.

Then run:

```sh
docker compose up
docker compose run --rm format --check
```

## Submission

Share the Git repository or a code archive with the README and empty configuration templates. If reviewers need package credentials, arrange access with the assignment contact through an agreed private channel; do not attach credentials to the repository or archive. If they already have the Nova access supplied for this assignment, they can create the local file from the template.

The final handoff must provide verified one-command startup and demo accounts. Nova package installation may require reviewer-provided build access or a prebuilt application image. Credentials used to download dependencies should not be included in the runtime image. The authenticated build and final packaging are not implemented yet.

## CI

Formatting runs without secrets. If authenticated builds are added to GitHub Actions, store `NOVA_USERNAME` and `NOVA_LICENSE_KEY` separately in [Actions secrets](https://docs.github.com/en/actions/concepts/security/secrets), then supply temporary Composer authentication through a [BuildKit secret mount](https://docs.docker.com/build/building/secrets/). Actions secrets serve workflow jobs, not reviewer credential distribution.
