FROM node:26-alpine@sha256:0b36e8c136b94cd4fcf02188228e76c31ad5872eef3fec8cbd2eee500cfd9e80 AS prettier
WORKDIR /opt/format/node
COPY tools/format/package.json tools/format/package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

FROM golang:1.26-alpine@sha256:8ac98ca534ac3f51e1f420a1dd2c15e74c75cfa0f23f3ad27eb5d7236c349a0c AS go
FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.4-fpm-alpine@sha256:78cd8de9970a9cd6ff4d98860a94eb5bf37dd2d5776b4785562dbfad01c29d5a
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
