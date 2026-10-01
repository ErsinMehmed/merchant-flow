# syntax=docker/dockerfile:1

# The React portal is built inside the image, so no Node.js is needed on the
# host. The same stage runs the portal checks (compose service portal-test).
FROM node:24-alpine@sha256:ebfe2f90462722a7a4de65e91990e97fe0d401c70e0e762c5b53302f905ec1c1 AS portal-build
WORKDIR /app
COPY merchant-portal/package.json merchant-portal/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY merchant-portal/ ./
RUN npm run build

FROM nginx:stable-alpine@sha256:0985e772fb9f729e6fa0980da05fca5d9c468e870eed43071545afa9d2e27d94
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=portal-build /app/dist /usr/share/nginx/html
