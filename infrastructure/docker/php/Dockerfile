# Local development image for the RowBuddy Laravel app (apps/web) and its
# worker processes (Horizon, Reverb) — they all run this same image with a
# different command, since they are one Laravel application, not separate
# services (see docs/decisions/001-modular-monolith.md).
#
# Build context is the REPOSITORY ROOT (not apps/web), so this image can
# mirror the monorepo's own relative layout: apps/web/composer.json points
# at packages/shared-kernel via a Composer path repository using
# "../../packages/shared-kernel" — copying both into the image preserving
# that same two-level nesting means the path repository resolves with zero
# path rewriting inside the container.
#
# This targets local development (dev dependencies included, no
# multi-stage --no-dev hardening yet). Production hardening is a Phase 9
# (Hardening & Launch Readiness) concern, not Phase 0.

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        git \
        unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        bcmath \
        gd \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY apps/web/ /var/www/apps/web/
COPY packages/shared-kernel/ /var/www/packages/shared-kernel/

WORKDIR /var/www/apps/web

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 9000

CMD ["php-fpm"]
