# syntax=docker/dockerfile:1
FROM mcr.microsoft.com/playwright:v1.63.0-noble

WORKDIR /app
COPY merchant-portal/package.json merchant-portal/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY merchant-portal/ ./

CMD ["npm", "run", "test:e2e"]
