# Local development image for the RowBuddy Laravel app (apps/web) and its
# worker processes (Horizon, Reverb) — they all run this same image with a
# different command, since they are one Laravel application, not separate
# services (see docs/decisions/001-modular-monolith.md).
#
# Build context is the REPOSITORY ROOT (not apps/web), so this image can
# mirror the monorepo's own relative layout: apps/web/composer.json points
# at each bounded-context package (packages/shared-kernel, packages/Queues,
# ...) via Composer path repositories — copying every package into the
# image preserving that same two-level nesting means each path repository
# resolves with zero path rewriting inside the container. Add a COPY line
# here whenever a new packages/* module is wired into apps/web.
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
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Phase 9 Sprint 4 (ADR-027 Decision 6): overrides Alpine's stock
# pm.max_children=5 pool default, empirically found to be a real
# synchronous bottleneck under load-testing — see
# infrastructure/docker/php/www.conf's own comment for the evidence.
COPY infrastructure/docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

COPY apps/web/ /var/www/apps/web/
COPY packages/shared-kernel/ /var/www/packages/shared-kernel/
COPY packages/Queues/ /var/www/packages/Queues/
COPY packages/QueuePresence/ /var/www/packages/QueuePresence/
COPY packages/Auctions/ /var/www/packages/Auctions/
COPY packages/Bids/ /var/www/packages/Bids/
COPY packages/Payments/ /var/www/packages/Payments/
COPY packages/Transfers/ /var/www/packages/Transfers/
COPY packages/Disputes/ /var/www/packages/Disputes/
COPY packages/Ratings/ /var/www/packages/Ratings/
COPY packages/Notifications/ /var/www/packages/Notifications/
COPY packages/Administration/ /var/www/packages/Administration/

WORKDIR /var/www/apps/web

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 9000

CMD ["php-fpm"]
