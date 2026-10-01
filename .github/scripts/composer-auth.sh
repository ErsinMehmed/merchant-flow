#!/bin/sh
# Writes the Nova Composer credential for CI from NOVA_USERNAME and
# NOVA_LICENSE_KEY (repository secrets). The file is ignored by Git and only
# reaches the image build as a BuildKit secret.
set -eu

mkdir -p .secrets
umask 077
jq -n --arg u "$NOVA_USERNAME" --arg p "$NOVA_LICENSE_KEY" \
    '{"http-basic": {"nova.laravel.com": {"username": $u, "password": $p}}}' >.secrets/composer-auth.json
