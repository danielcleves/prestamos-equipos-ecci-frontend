# syntax=docker/dockerfile:1
#
# Imagen de produccion del frontend (Render). No hay docker-compose de
# desarrollo para el frontend: localmente se sigue usando "php artisan serve".

# --- 1. Assets (Vite + Tailwind) -------------------------------------------------
FROM node:22-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- 2. Aplicacion PHP -----------------------------------------------------------
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid 1000 www \
    && useradd --uid 1000 --gid 1000 --create-home --shell /bin/bash www \
    && mkdir -p /var/www && chown www:www /var/www
WORKDIR /var/www

COPY --chown=www:www composer.json composer.lock ./
USER www
RUN composer install \
        --no-interaction \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY --chown=www:www . .
COPY --chown=www:www --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev

USER root
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
USER www

# Valores por defecto de produccion. Render puede sobrescribir cualquiera.
# El frontend no usa base de datos: sesion y cache van a archivos.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    PHP_CLI_SERVER_WORKERS=4 \
    PORT=8000

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["sh", "-c", "exec php artisan serve --host=0.0.0.0 --port=${PORT} --no-reload"]
