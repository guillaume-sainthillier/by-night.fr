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

In production, the hub is a service of `/apps/by-night.fr/docker-compose.yaml`: Traefik routes
`by-night.fr/.well-known/mercure` to it (above the app's router), and the app and the worker publish to it over the
stack's own network, under an alias no other stack uses (the worker is not on `web`). Its JWT key is
`MERCURE_JWT_SECRET` of the stack's `.env`, also copied into `app.env`: rotate both together.

```yaml
services:
    mercure:
        image: dunglas/mercure:v1.1.2
        container_name: '${APP_NAME}-mercure'
        environment:
            SERVER_NAME: ':80'
            # Traefik is a trusted proxy: the hub takes its public URL (the tokens' audience) from X-Forwarded-*
            GLOBAL_OPTIONS: |-
                servers {
                    trusted_proxies static private_ranges
                }
            MERCURE_TRUSTED_ISSUERS: 'https://by-night.fr'
            MERCURE_PUBLISHER_JWT_KEY: '${MERCURE_JWT_SECRET}'
            MERCURE_SUBSCRIBER_JWT_KEY: '${MERCURE_JWT_SECRET}'
            # Restarts close the event streams within 5 s (pages reconnect) instead of write_timeout's 10 min
            MERCURE_EXTRA_DIRECTIVES: 'drain_timeout 5s'
        volumes:
            - mercure_data:/data
            - mercure_config:/config
        labels:
            - 'traefik.enable=true'
            - 'traefik.http.routers.${APP_NAME}-mercure.rule=(Host(`by-night.fr`) || Host(`www.by-night.fr`)) && PathPrefix(`/.well-known/mercure`)'
            - 'traefik.http.routers.${APP_NAME}-mercure.priority=1000'
            - 'traefik.http.routers.${APP_NAME}-mercure.entrypoints=websecure'
            - 'traefik.http.routers.${APP_NAME}-mercure.tls=true'
            - 'traefik.http.routers.${APP_NAME}-mercure.middlewares=sts,strip-www'
            - 'traefik.http.routers.${APP_NAME}-mercure.service=${APP_NAME}-mercure'
            - 'traefik.http.services.${APP_NAME}-mercure.loadbalancer.server.port=80'
        stop_grace_period: 10s
        restart: unless-stopped
        mem_limit: 256m
        networks:
            default:
                aliases: [by-night-mercure]
            web:
```

`app.env` then sets `MERCURE_URL=http://by-night-mercure/.well-known/mercure`,
`MERCURE_PUBLIC_URL=https://by-night.fr/.well-known/mercure`, `MERCURE_JWT_SECRET` (the hub's, at least 32 bytes) and
`MERCURE_JWT_ISSUER=https://by-night.fr`. Cloudflare streams the events (no cache: the hub answers `private,
no-store`); its idle cut (~100 s) is above the hub's 40 s heartbeat.

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

### Outing alerts

The members going to an event, or keeping it as a favourite, hear by e-mail when its dates, venue or status change. A
change only flags the event (`event.flagged_for_alert_at`); a host cron tells the changes four times a day, at 8:00,
12:00, 16:00 and 20:00 (`0 8,12,16,20 * * *`):

```bash
docker compose exec -T worker /app/bin/console app:outing-alerts:send -vv
```

A run takes every event flagged before it started; a change made while it runs is left for the next one. Run again, by
hand or after a crash, it sends nothing twice, and two runs never overlap (a lock in Redis). The e-mails that could not leave are
listed in its output and tried again by the next run. A member hears of an event once a day at most: a change after
the day's alert waits for the first run of the next day.

### Event audience (Umami)

The event pages' visitors and ticketing clicks are shown to their authors (`/espace-perso/{id}/statistiques`). They
come from the self-hosted Umami (`analytics.silarhi.fr`, container `analytics` on the shared `web` network): no
cookie, so no consent and every visitor counted.

- **Tracker**: `base.html.twig` loads `/a/script.js` when `UMAMI_WEBSITE_ID` is set; the script posts its hits next to
  itself, to `/a/api/send`. Traefik sends those two exact paths (never the dashboard or the API) straight to the Umami
  container, without going through the app: the `analytics-tracker` router and its `/a` strip middleware, labels of
  the Umami service in `/apps/analytics.silarhi.fr/docker-compose.yaml`, priority 2000 (above by-night's routers).
  The sites it answers for are `TRACKER_HOSTS` in that directory's `.env`, a Traefik rule (``Host(`by-night.fr`)``):
  another site behind this Traefik joins by adding its host there, then `docker compose up -d app`. Umami reads the
  visitor's IP from `CF-Connecting-IP`, which Traefik passes on. The ticketing links carry
  `data-umami-event="ticket-click"`.
- **API**: `UMAMI_API_URL=https://analytics.silarhi.fr/` (the worker is not on the `web` network, so not
  `http://analytics:3000`), read as `by-night-api`, a view-only user of the "BY NIGHT" team, which holds the by-night
  website only (`UMAMI_USERNAME`, `UMAMI_PASSWORD`, `UMAMI_WEBSITE_ID` in `app.env`).
- **Sync**: an hourly host cron copies today and yesterday into `event_visit_stat` (a day read again is replaced):

```bash
docker compose exec -T worker /app/bin/console app:events:sync-visits -vv
```

The 90 days before Umami (2026-07-25 to 2026-10-22) were filled once from Google Analytics, flagged
`from_google_analytics`: GA4 only counted the visitors who accepted its cookies, and no ticketing clicks; the statistics
page says so. The one-shot command that did it is gone.

Umami's event-data values stop at the top 100, so the sync pages through `metrics?type=path` (the visitors of each
`/soiree/` page) and `events?event=ticket-click` (one row per click), and finds the event by the `--{id}` ending
the path.

## Setup

```bash
bin/console messenger:setup-transports
```

## Add events

```bash
bin/console app:events:import toulouse.opendata -vv
bin/console messenger:consume parser -vv
```
