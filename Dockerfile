# syntax=docker/dockerfile:1

# ═══════════════════════════════════════════════════════════════════════════════
# Multi-stage Dockerfile for LaunchPilot (Marko PHP + React/Vite)
# Targets:
#   app   → PHP-FPM production image
#   nginx → nginx reverse-proxy image (copies built public assets)
# ═══════════════════════════════════════════════════════════════════════════════

# ─── Stage 1: Build frontend assets ───────────────────────────────────────────
FROM node:24-alpine AS node-builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ─── Stage 2: Install PHP dependencies ────────────────────────────────────────
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-progress

# ─── Stage 3: Base application files ──────────────────────────────────────────
FROM alpine:3.21 AS app-base
WORKDIR /var/www
COPY --chown=1000:1000 . .
RUN rm -rf public/build
COPY --from=node-builder --chown=1000:1000 /app/public/build ./public/build
COPY --from=composer-builder --chown=1000:1000 /app/vendor ./vendor

# ─── Target: app ── PHP-FPM production image ─────────────────────────────────
FROM php:8.5-fpm-alpine AS app

RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql pgsql

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www

# Copy fully-built application from base stage
COPY --from=app-base /var/www /var/www

# Ensure storage is writable
RUN mkdir -p /var/www/storage/sessions /var/www/storage/logs \
    && chown -R www-data:www-data /var/www/storage

USER www-data
EXPOSE 9000
CMD ["php-fpm"]

# ─── Target: nginx ── nginx reverse-proxy image ──────────────────────────────
FROM nginx:stable-alpine AS nginx

# Copy nginx site config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy public assets (static files + built Vite output + index.php)
COPY --from=app-base /var/www/public /var/www/public

EXPOSE 80
