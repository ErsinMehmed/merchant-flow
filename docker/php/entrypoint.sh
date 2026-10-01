#!/bin/sh
set -eu

# Laravel needs a stable APP_KEY for sessions. Generate one on first start and
# keep it in a volume shared by the init and php containers, so it survives
# restarts without being committed anywhere.
if [ -z "${APP_KEY:-}" ]; then
    key_dir=/run/merchant-flow
    key_file="$key_dir/app-key"
    mkdir -p "$key_dir"
    if [ ! -s "$key_file" ]; then
        umask 077
        printf 'base64:%s' "$(php -r 'echo base64_encode(random_bytes(32));')" > "$key_file.tmp"
        mv "$key_file.tmp" "$key_file"
    fi
    APP_KEY=$(cat "$key_file")
    export APP_KEY
fi

exec docker-php-entrypoint "$@"
