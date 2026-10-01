#!/bin/sh
set -eu

case "${1:-}" in
    "")
        node /opt/format/node/node_modules/prettier/bin/prettier.cjs . --write --ignore-unknown
        php /opt/format/php/vendor/bin/pint --config=pint.json php
        find go-api -type d \( -name vendor -o -name bin \) -prune -o -type f -name '*.go' -print0 | xargs -0 -r gofmt -w
        ;;
    --check)
        node /opt/format/node/node_modules/prettier/bin/prettier.cjs . --check --ignore-unknown
        php /opt/format/php/vendor/bin/pint --config=pint.json --test php
        unformatted=$(find go-api -type d \( -name vendor -o -name bin \) -prune -o -type f -name '*.go' -print0 | xargs -0 -r gofmt -l)
        if [ -n "$unformatted" ]; then
            printf 'Go files need formatting:\n%s\n' "$unformatted"
            exit 1
        fi
        ;;
    *)
        printf 'Usage: docker compose run --rm format [--check]\n' >&2
        exit 2
        ;;
esac
