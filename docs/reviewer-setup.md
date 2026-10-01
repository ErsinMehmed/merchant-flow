# Reviewer setup and Nova access

The full setup is in the [README](../README.md#setup). This page only covers the Nova credential.

Nova is a licensed, private Composer package, so building the PHP image needs an authorized Nova account. Create the ignored local file from the template and fill in the account email (`username`) and license key (`password`):

```sh
mkdir -p .secrets
cp docker/secrets/composer-auth.json.example .secrets/composer-auth.json
```

The file stays on the reviewer's machine. Docker passes it to the `composer install` build step as a BuildKit secret; it is not copied into any image layer, environment variable or log. Without it the build stops with `Missing Nova credentials`.

If you need credentials for this review, ask through the assignment contact; they are shared privately. Never put license keys in the repository, an issue or a submitted archive.
