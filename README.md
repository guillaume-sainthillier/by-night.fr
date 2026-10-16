# By Night

![Build And Deploy](https://github.com/guillaume-sainthillier/by-night.fr/workflows/Build%20And%20Deploy%20Release/badge.svg)

Plateforme de gestion d'événements en France.

## DEMO

https://by-night.fr

## Stack technique

- PHP 8.5 (FrankenPHP)
- Symfony 8.1
- MySQL 8.0
- Elasticsearch 9
- Cloudflare (CDN, cache HTTP)
- Redis
- RabbitMQ
- Vite
- Sass
- Bootstrap 5 / Tabler
- Preact
- Docker
- OVH Object Storage (S3)

## Docker

The application image is built `FROM` a pre-built runtime base
(`guystlr/by-night-base`) that bakes in the PHP extensions and the MJML rendering
extension (`ext-mjml`), so the app build never recompiles them. The base uses
immutable version tags (`php85-v1`, `php85-v2`, …). The single source of truth is
**`docker/base/VERSION`**, and the app's `ARG BASE_IMAGE_TAG` has **no default** — it
must be passed explicitly on every build.

`.github/workflows/build-base-image.yml` publishes the base on `main` / manual
dispatch — since published tags are immutable, it is a no-op when the
`docker/base/VERSION` tag already exists. The release workflow passes
`BASE_IMAGE_TAG` from `docker/base/VERSION` automatically and republishes a missing
base tag itself before building the app, so releases are self-healing. Every PR builds
the images (base when `docker/base/` changes, then app, no push) in the "Build images
(no push)" job of `continuous-integration.yml`, which "CI passed" requires.

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

### Hashed assets in production

Production (`/apps/by-night.fr` on the server) mounts the external volume `by-nightfr_assets`
on `public/build` of the `app` and `app-images` services, so each release adds its hashed
files next to the previous ones and never deletes any: pages opened before a deploy and
emails sent months ago keep resolving. Docker only fills a named volume once, when it is
created, so `deploy.sh` copies the new image's build into it right after the pull, before
the rollout:

```bash
docker run --rm -v by-nightfr_assets:/mnt guystlr/by-night:stable cp -a /app/public/build/. /mnt/
```

```yaml
services:
    app:
        volumes:
            - assets:/app/public/build

volumes:
    assets:
        external: true
        name: by-nightfr_assets
```

The volume was created by hand and seeded from the last Encore release, so the URLs of
that era still resolve. The same one-liner with any image tag adds that release's files.

### Mercure hub

The live progress of the members' imports and exports goes through a [Mercure](https://mercure.rocks) hub
(protocol 1.0, `dunglas/mercure` image) running in its own container. Browsers reach it on the site's own origin,
under `/.well-known/mercure`: the subscriber cookie the Twig `mercure()` function sets
(`__Secure-mercure_access_token`, path `/.well-known/mercure`) is only sent there. The app publishes to the
hub's internal URL (`MERCURE_URL`); both URLs end up in the publisher token's audience.

Locally, `docker compose up -d mercure` starts it on port 3000, and the Apache vhost of `by-night.test` proxies
the path to it (no compression, events flushed as they come):

```apache
<Location /.well-known/mercure>
    ProxyPass http://127.0.0.1:3000/.well-known/mercure flushpackets=on
    ProxyPassReverse http://127.0.0.1:3000/.well-known/mercure
    RequestHeader set X-Forwarded-Proto https
    SetEnv no-gzip 1
</Location>
```

In production, the hub is a service of `/apps/by-night.fr/docker-compose.yaml`, routed by Traefik on the same
host. Its alias must be unique on the shared `web` network, where several stacks run a service of the same name:

```yaml
services:
    mercure:
        image: dunglas/mercure:v1.1.2
        restart: unless-stopped
        environment:
            SERVER_NAME: ':80'
            GLOBAL_OPTIONS: |-
                servers {
                    trusted_proxies static private_ranges
                }
            MERCURE_TRUSTED_ISSUERS: 'https://by-night.fr'
            MERCURE_PUBLISHER_JWT_KEY: '${MERCURE_JWT_SECRET}'
            MERCURE_SUBSCRIBER_JWT_KEY: '${MERCURE_JWT_SECRET}'
        volumes:
            - mercure_data:/data
            - mercure_config:/config
        networks:
            web:
                aliases: [by-night-mercure]
        labels:
            traefik.enable: 'true'
            traefik.http.routers.by-night-mercure.rule: 'Host(`by-night.fr`) && PathPrefix(`/.well-known/mercure`)'
            traefik.http.services.by-night-mercure.loadbalancer.server.port: '80'
```

`app.env` then sets `MERCURE_URL=http://by-night-mercure/.well-known/mercure`,
`MERCURE_PUBLIC_URL=https://by-night.fr/.well-known/mercure`, a random `MERCURE_JWT_SECRET` (the same as the hub's,
at least 32 bytes) and `MERCURE_JWT_ISSUER=https://by-night.fr`.

### Members' imports and exports

The members' event imports and exports run on their own Messenger transport, `transfers`
(`MESSENGER_TRANSFERS_TRANSPORT_DSN`, set up by `deploy.sh`'s `messenger:setup-transports`), consumed by the
`messenger-transfers` program of the worker (`docker/supervisord-worker.conf`). Their files live in
`transfers.storage`, private objects under `private/transfers/` of the bucket, which `data.by-night.fr` never serves.
After the first deploy, check that one is out of reach:

```bash
curl -sI https://data.by-night.fr/private/transfers/ | head -1
```

A daily host cron fails the jobs a worker left halfway and deletes those older than 30 days with their files:

```bash
docker compose exec -T worker /app/bin/console app:transfers:cleanup -vv
```

## Setup

```bash
bin/console messenger:setup-transports
```

## Add events

```bash
bin/console app:events:import toulouse.opendata -vv
bin/console messenger:consume parser -vv
```
