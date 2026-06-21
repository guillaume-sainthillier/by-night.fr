#syntax=docker/dockerfile:1.25-labs

# By Night runtime base image.
#
# Bakes the PHP extensions AND the MJML rendering extension (ext-mjml, Rust mrml engine)
# into a single published image so the application build never recompiles them. This is the
# slowest part of a cold build, and it changes rarely — only when PHP, the extension list,
# or the mjml version changes.
#
# Build & publish (see .github/workflows/build-base-image.yml):
#   docker build -t guystlr/by-night-base:php8.5 docker/base
#   docker push  guystlr/by-night-base:php8.5

# ── PHP extensions (installed once, shared by the builder and the runtime base) ──
FROM dunglas/frankenphp:1.12-php8.5-alpine AS php_ext

# intl transitively installs icu-data-full (required for French date/number formatting).
RUN IPE_GD_WITHOUTAVIF=1 \
    install-php-extensions \
        @composer \
        amqp \
        apcu \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        sockets \
        zip

# ── MJML extension builder (Rust toolchain stays in this throwaway stage) ──
FROM php_ext AS mjml_builder

RUN apk add --no-cache --virtual .mjml-build-deps \
        $PHPIZE_DEPS \
        cargo \
        clang-dev \
        libtool \
        openssl-dev \
        curl
RUN curl -fsSL https://github.com/php/pie/releases/download/1.4.5/pie.phar -o /usr/local/bin/pie \
    && chmod +x /usr/local/bin/pie
# No prebuilt .so exists for php8.5 + musl + ZTS, so PIE compiles it from source.
RUN --mount=type=cache,target=/root/.cargo/registry \
    pie install kcs/mjml:^1.2

# ── Runtime base ──
FROM php_ext

# Runtime system packages (curl is required by the application image HEALTHCHECK).
RUN apk add --no-cache \
        bash \
        ca-certificates \
        curl \
        supervisor \
        tzdata \
    && echo "Europe/Paris" > /etc/timezone

# Add mjml.so to the (identical) extensions dir — a safe superset, see header note.
COPY --from=mjml_builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
RUN echo "extension=mjml" > "$PHP_INI_DIR/conf.d/docker-php-ext-mjml.ini"
