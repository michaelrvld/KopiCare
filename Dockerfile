FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_pgsql

WORKDIR /app
COPY Caddyfile /etc/caddy/Caddyfile
COPY . /app

ENV PORT=80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
