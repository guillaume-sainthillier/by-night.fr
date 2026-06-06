# By Night

![Build And Deploy](https://github.com/guillaume-sainthillier/by-night.fr/workflows/Build%20And%20Deploy%20Release/badge.svg)
[![SymfonyInsight](https://insight.symfony.com/projects/a11fedf7-0560-449b-bbfa-d38fe90a99ee/mini.svg)](https://insight.symfony.com/projects/a11fedf7-0560-449b-bbfa-d38fe90a99ee)

Plateforme de gestion d'événements en France.

## DEMO

https://by-night.fr

## Stack technique

- PHP 8.4
- Symfony 7.4
- MySQL 8.0
- Elastic Search 7
- Varnish 6
- Redis 5
- Sass
- Webpack
- Docker
- Amazon S3 / Cloudfront

## Docker

The application image is built `FROM` a pre-built runtime base
(`guystlr/by-night-base`) that bakes in the PHP extensions and the MJML rendering
extension (`ext-mjml`), so the app build never recompiles them. The base uses
immutable version tags (`php85-v1`, `php85-v2`, …). The single source of truth is
**`docker/base/VERSION`**, and the app's `ARG BASE_IMAGE_TAG` has **no default** — it
must be passed explicitly on every build.

`.github/workflows/build-base-image.yml` builds the base on PRs that touch
`docker/base/` (validation only) and publishes it on `main` / manual dispatch; the
release workflow passes `BASE_IMAGE_TAG` from `docker/base/VERSION` automatically.

For local builds, export the version first, then build/pull:

```bash
export BASE_IMAGE_TAG=$(cat docker/base/VERSION)
docker compose build
# pull (or build) the base it references:
docker pull guystlr/by-night-base:$BASE_IMAGE_TAG
docker build -t guystlr/by-night-base:$BASE_IMAGE_TAG docker/base
```

To change the base (e.g. add an extension): edit `docker/base/Dockerfile` **and**
bump `docker/base/VERSION` (`php85-v1` → `php85-v2`) in the same PR.

## Setup

```bash
bin/console rabbitmq:setup-fabric
bin/console messenger:setup-transports
```

## Add events

```bash
bin/console app:events:import toulouse.opendata -vv
bin/console rabbitmq:batch:consumer add_event -vv
```
