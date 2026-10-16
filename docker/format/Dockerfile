FROM node:24-alpine@sha256:ebfe2f90462722a7a4de65e91990e97fe0d401c70e0e762c5b53302f905ec1c1 AS prettier
WORKDIR /opt/format/node
COPY tools/format/package.json tools/format/package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

FROM golang:1.27-alpine@sha256:f92b6ef800e499660581efdabdf25d9d817a9d124eaf900924f0504e7e27e12d AS go
FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.5-fpm-alpine@sha256:fa01fb1645cd0fc566a5f146b099adace33b906571f972f71f2182a7c12d1cd7
RUN apk add --no-cache libstdc++
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=prettier /usr/local/bin/node /usr/local/bin/node
COPY --from=prettier /opt/format/node /opt/format/node
COPY --from=go /usr/local/go/bin/gofmt /usr/local/bin/gofmt

WORKDIR /opt/format/php
COPY tools/format/composer.json tools/format/composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --no-plugins

COPY docker/format/format.sh /usr/local/bin/format
WORKDIR /workspace
ENTRYPOINT ["sh", "/usr/local/bin/format"]
