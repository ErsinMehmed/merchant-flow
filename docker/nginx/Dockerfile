# syntax=docker/dockerfile:1

# The React portal is built inside the image, so no Node.js is needed on the
# host. The same stage runs the portal checks (compose service portal-test).
FROM node:26-alpine@sha256:0b36e8c136b94cd4fcf02188228e76c31ad5872eef3fec8cbd2eee500cfd9e80 AS portal-build
WORKDIR /app
COPY merchant-portal/package.json merchant-portal/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY merchant-portal/ ./
RUN npm run build

FROM nginx:stable-alpine@sha256:0985e772fb9f729e6fa0980da05fca5d9c468e870eed43071545afa9d2e27d94
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=portal-build /app/dist /usr/share/nginx/html
