#syntax=docker/dockerfile:1.27-labs

# By Night runtime base image.
#
# Bakes the PHP extensions AND the MJML rendering extension (ext-mjml, Rust mrml engine)
# into a single published image so the application build never recompiles them. This is the
# slowest part of a cold build, and it changes rarely — only when PHP, the extension list,
# or the mjml version changes.
#
# The version is the single source of truth in docker/base/VERSION (e.g. php85-v1);
# bump it together with any docker/base/ change. Published tags are immutable — the
# publishing workflows (build-base-image.yml, and the base job of
# build-and-deploy-release.yml as a fallback) skip tags that already exist.
#
# Build & publish manually:
#   docker build -t guystlr/by-night-base:$(cat docker/base/VERSION) docker/base
#   docker push  guystlr/by-night-base:$(cat docker/base/VERSION)

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
        ffi \
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
        curl \
        patch
# No prebuilt .so exists for php8.5 + musl + ZTS, so it is compiled from source (what `pie install kcs/mjml`
# does), with two patches until upstream fixes them (2026-10-08, still in ext-php-rs 0.16.1):
# - ext-php-rs: a second MINIT in the same process panicked ("Class entry has already been set") and aborted
#   FrankenPHP, which runs one on POST /frankenphp/workers/restart and on opcache_reset();
# - mjml-php: a render error panicked instead of throwing (its exception class entry was never stored), and
#   the class was \RenderException, not the Mjml\Exception\RenderException the stubs and mjml-bundle catch.
COPY patches/ /usr/src/patches/
RUN curl -fsSL https://static.crates.io/crates/ext-php-rs/ext-php-rs-0.15.7.crate -o /tmp/ext-php-rs.crate \
    && echo "83b57e00633bee412187a6c91888906c34a4dc11901b7c978fdcf572591c59b1  /tmp/ext-php-rs.crate" | sha256sum -c - \
    && mkdir /usr/src/ext-php-rs \
    && tar xzf /tmp/ext-php-rs.crate -C /usr/src/ext-php-rs --strip-components=1 \
    && patch -d /usr/src/ext-php-rs -p1 < /usr/src/patches/ext-php-rs-0.15.7-set-ce.patch \
    && curl -fsSL https://github.com/alekitto/mjml-php/archive/refs/tags/v1.2.1.tar.gz -o /tmp/mjml.tar.gz \
    && echo "869f030275281f830632d8fd114ee2fe53c086e4bcbf38f0f30da9fe58699ae6  /tmp/mjml.tar.gz" | sha256sum -c - \
    && mkdir /usr/src/mjml \
    && tar xzf /tmp/mjml.tar.gz -C /usr/src/mjml --strip-components=1 \
    && patch -d /usr/src/mjml -p1 < /usr/src/patches/mjml-php-1.2.1.patch
RUN --mount=type=cache,target=/root/.cargo/registry \
    cd /usr/src/mjml \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install

# ── Runtime base ──
FROM php_ext

# Runtime system packages (curl is required by the application image HEALTHCHECK).
# vips + vips-heif (AVIF): Picasso's Glide "vips" driver in production (config/packages/picasso.yaml),
# ~1.7x faster than GD on a thumbnail srcset. PHP calls libvips through FFI, which the web SAPI
# only allows with ffi.enable=true (the default "preload" limits it to the CLI and preloading).
RUN apk add --no-cache \
        bash \
        ca-certificates \
        curl \
        supervisor \
        tzdata \
        vips \
        vips-heif \
    && echo "Europe/Paris" > /etc/timezone \
    && echo "ffi.enable=true" >> "$PHP_INI_DIR/conf.d/docker-php-ext-ffi.ini"

# Add mjml.so to the (identical) extensions dir — a safe superset, see header note.
COPY --from=mjml_builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
RUN echo "extension=mjml" > "$PHP_INI_DIR/conf.d/docker-php-ext-mjml.ini"
