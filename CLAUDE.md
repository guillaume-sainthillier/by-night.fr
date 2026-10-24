# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

By Night is an event management platform for France (https://by-night.fr). It aggregates events from multiple external sources (OpenAgenda, DataTourisme, Awin partners, etc.) and provides a searchable, location-based event discovery interface.

## Tech Stack

- **Backend**: PHP 8.5, Symfony 8.1
- **Database**: MySQL 8.0 with Doctrine ORM
- **Search**: Elasticsearch 9 with FOSElasticaBundle
- **Caching**: Redis (application cache), HTTP cache headers + Cloudflare CDN
- **Message Queue**: RabbitMQ via Symfony Messenger (`symfony/amqp-messenger`, transports in `config/packages/messenger.yaml`)
- **File Storage**: S3-compatible bucket via Flysystem, exposed as `data.by-night.fr`
- **Frontend**: TypeScript (strict), Vite with `@symfony/reprise`, Bootstrap 5 + Tabler, jQuery, Sass, Preact (for reactive components), Stimulus
- **Real-time**: Mercure hub (protocol 1.0, own `mercure` container, `symfony/mercure-bundle`) on the site's origin under `/.well-known/mercure` (setup in the README). Members' updates are private, published with the resource's IRI as canonical topic and the member's channel as alternate (`App\Mercure\UserChannel::update()`); a page subscribes to the channel with `{{ user_channel_hub(app.user) }}` (the hub's URL; sets the subscriber cookie once per request, as the Twig `mercure()` bridge would, which throws when asked twice). Every personal-space page listens for its jobs and toasts when one is done (`assets/js/listeners/transfer-toasts.ts`)
- **Error Tracking**: Sentry
- **Audience**: self-hosted Umami (cookieless, no consent), tracker same-origin at `/a/script.js` + `/a/api/send`, routed by Traefik straight to the Umami container (not through the app); GA4 (Tag Manager, after consent) stays for the site-wide analysis. Event authors see their pages' visitors and ticketing clicks (`/espace-perso/{id}/statistiques`, `App\Analytics\EventAudienceReport`: a period of shortcuts looking back, `AudiencePeriod`, or dates picked, `AudienceQuery`; a line chart drawn server side as SVG, `AudienceChart`), copied hourly from Umami's API into `event_visit_stat` by `app:events:sync-visits` (`App\Analytics\EventVisitSync`; setup in the README). A click worth counting carries `data-analytics-event="<name>"` and its properties as `data-analytics-<prop>` (the ticketing links: `ticket-click` and `seller`, the event and property the sync reads), sent by `assets/js/listeners/analytics.ts`, the only file that knows Umami (`window.umami`): never `data-umami-*` in a template. The e-mails worth measuring (weekend selection, outing alerts, publishers' news) get their UTM parameters from `MailerManager` as the `utm` context (`App\Utils\EmailUtm::campaign(<kind>, <week, change or edition>)`), and their templates tag each link with `|utm('<which link>')` (`App\Twig\UtmExtension`), a no-op in the other e-mails, so the layouts tag theirs; never on a signed URL (unsubscribe), whose signature covers the query

## Backend Development Workflow

**Before modifying PHP files**, always check the current state of the codebase:

```bash
# Run all quality checks on the files you plan to modify
vendor/bin/phpstan analyse src/Path/To/File.php   # Check specific file(s)
vendor/bin/phpunit tests/Path/To/FileTest.php     # Run related tests
vendor/bin/php-cs-fixer fix --dry-run --diff src/Path/To/File.php  # Check formatting
```

**After modifying PHP files**, verify your changes don't introduce issues:

```bash
# 1. Fix code style (required - pre-commit hook will run this anyway)
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php src/Path/To/File.php

# 2. Run static analysis on modified files
vendor/bin/phpstan analyse src/Path/To/File.php

# 3. Run related tests
vendor/bin/phpunit tests/Path/To/FileTest.php

# 4. For broad changes, run the full suite
vendor/bin/phpstan analyse && vendor/bin/phpunit
```

This workflow catches type errors, regressions, and style issues before they reach the pre-commit hook or CI.

## Common Commands

### Development

pnpm is the package manager: its version is pinned by `packageManager` in `package.json`, and `pnpm-workspace.yaml` lists the dependencies allowed to run install scripts (`allowBuilds`; pnpm fails the install on a new unlisted one).

```bash
# Install dependencies
composer install
pnpm install

# Build frontend assets
pnpm dev              # Development build
pnpm watch            # Watch mode
pnpm build            # Production build

# Start local services (requires Docker; compose refuses to start without BASE_IMAGE_TAG)
export BASE_IMAGE_TAG=$(cat docker/base/VERSION)
docker compose up -d
```

### Testing & Quality

```bash
# PHP Tests
vendor/bin/phpunit                                 # All tests
vendor/bin/phpunit tests/Import/FirewallTest.php   # Single test file

# JavaScript Tests (Vitest, vitest.config.ts): assets/**/*.test.ts next to the code they cover
pnpm test                                          # All tests
pnpm test assets/js/utils/plural.test.ts           # Single test file

# Static Analysis
vendor/bin/phpstan analyse                         # PHPStan (level 6)

# Code Formatting
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php  # PHP (Symfony ruleset)
vendor/bin/twig-cs-fixer lint --fix --config=.twig-cs-fixer.php  # Twig templates
pnpm typecheck                                     # TypeScript (tsc, strict, no emit)
pnpm lint                                          # Biome (lint + format) for TypeScript
pnpm prettier                                      # Prettier for SCSS/Markdown

# Pre-commit Hook
# Husky runs lint-staged on commit, which auto-formats changed files:
# - PHP: php-cs-fixer
# - Twig: twig-cs-fixer
# - JS/JSX/TS/JSON: biome check --write
# - SCSS/MD/YAML: prettier --write
```

### Test Fixtures — Foundry

Tests build, change, query and delete their fixtures with **Zenstruck Foundry** (`src/Factory/`), **never through the `EntityManager`**: no `persist()`, `flush()`, `remove()`, `clear()` or `refresh()` in a test. Passing the `EntityManager` to a service the test builds by hand (or stubbing it to build a `QueryBuilder`) is fine: that is wiring, not fixtures.

Factories extend `PersistentObjectFactory` with `enable_auto_refresh_with_lazy_objects` (PHP 8.4 lazy objects): created objects are plain entities, and the persistence helpers are functions, not proxy methods (`_save()`, `_refresh()`, `_delete()` do not exist here).

```php
use App\Factory\EventFactory;
use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\flush_after;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

$event = EventFactory::createOne(['name' => 'Test']);    // persisted + flushed
$event->setName('Renamed');
save($event);                                             // persist + flush a change
refresh($event);                                          // reload from the DB
delete($event);                                           // remove + flush

EventFactory::find(['externalId' => 'x']);                // single result, read from the DB (throws if missing)
EventFactory::findBy(['email' => 'a@b.c']);               // list
EventFactory::count(['duplicateOf' => null]);             // fresh COUNT(*), ignores the unit of work
```

Instead of the `EntityManager`:

| Instead of                                                                                                                | Use                                                                                                                                                    |
| ------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `new Entity()` + `persist()` + `flush()`                                                                                  | `XFactory::createOne([...])` (add the factory in `src/Factory/` if missing)                                                                            |
| changing an entity then `$em->flush()`                                                                                    | `save($entity)`                                                                                                                                        |
| `$em->remove()` + `flush()`                                                                                               | `delete($entity)`; several in one flush: `flush_after(fn () => …)`                                                                                     |
| `$em->refresh()`, or `clear()` to read back what was written                                                              | `refresh($entity)`, or `XFactory::find([...])`, which already returns the DB state                                                                     |
| `$em->getRepository(X::class)->count()/findBy()`                                                                          | `XFactory::count()/findBy()/all()`                                                                                                                     |
| `$em->clear()` to start from an empty identity map (a query-count test, code that runs after the import loops' `clear()`) | `self::bootKernel()`: a fresh kernel has a fresh entity manager. DAMA keeps the same connection and transaction, and Foundry follows the new container |

Conventions:

- **Deleting several entities in one flush** (e.g. to exercise an `onFlush`/`postFlush` listener's de-duplication): wrap the `delete()` calls in `flush_after(fn () => …)` so they share a single flush instead of one flush each.
- **Asserting "nothing was persisted"** after code that leaves unflushed in-memory changes (e.g. a `--dry-run` command): use `Factory::count(...)`, which runs a fresh `SELECT COUNT(*)` and ignores the dirty unit of work. Don't use `find()` here: Foundry's auto-refresh throws `ObjectHasUnsavedChanges` on dirty entities.
- **Testing detached entities**: a persisting factory swaps any detached object it is given for its managed instance, which hides "A new entity was found through the relationship" errors. Build the object with `XFactory::new()->withoutPersisting()->create([...])`, then `save()` it: `save()` persists it as is (see `CountryImporterTest`).
- No trait to add: the `FoundryExtension` and DAMA `PHPUnitExtension` (`phpunit.xml.dist`) boot Foundry and wrap each test in a rolled-back transaction; `AppKernelTestCase` only boots the kernel.
- Functional tests extend `App\Tests\AppWebTestCase` (or `AppApiTestCase` for API Platform), never Symfony's `WebTestCase` directly: their client browses `https://by-night.test` (`APP_URL` in `.env.test`), the only host `SYMFONY_TRUSTED_HOSTS` accepts in `.env.test`, so absolute URLs in assertions are `https://by-night.test/…`, and those of the image bucket `https://data.by-night.test/…` (`S3_PUBLIC_URL`). No `localhost` in the tests.
- Fetch up-to-date Foundry usage from context7 (`/zenstruck/foundry`) rather than guessing the 2.x API.

### Email Tests (MJML)

Transactional emails are MJML templates (`templates/email/*.mjml.twig`) rendered in-process by the `mjml_php` renderer (`config/packages/mjml.yaml`), which requires the **`ext-mjml`** PHP extension (Rust `alekitto/mjml-php`). It is baked into the Docker image (`docker/base/`) but is **not present in a stock Homebrew PHP**.

Tests that send an email (which renders MJML) are therefore guarded with `#[RequiresPhpExtension('mjml')]` (`ContentRemovalRequestTest`, `FeedbackTest`, `ContentRemovalEventDeletionListenerTest`): they **run** wherever `ext-mjml` is installed and are **skipped** (not failed) where it is absent — so CI (laminas-ci, no `ext-mjml`) stays green. To run them locally, install the extension via PIE:

```bash
pie install kcs/mjml          # builds mjml.dylib; on macOS copy it to the extension dir as mjml.so
echo "extension=mjml" > "$(php -r 'echo dirname(php_ini_loaded_file());')/conf.d/ext-mjml.ini" 2>/dev/null || true
php -m | grep mjml            # verify
```

### Event Import Pipeline

```bash
# Setup message queues
bin/console messenger:setup-transports

# Import events from a parser
bin/console app:events:import <parser-name> -vv          # changes since the parser's last run
bin/console app:events:import <parser-name> --full -vv   # whole catalogue
bin/console app:events:import <parser-name> --whole -vv  # backfill: whole catalogue, past events included

# Process queued events (the `parser` transport; workers in docker/supervisord-worker.conf)
bin/console messenger:consume parser -vv
```

### Elasticsearch

```bash
bin/console fos:elastica:populate           # Reindex all data
```

## Architecture

### Event Import Pipeline

The system imports events through a multi-stage pipeline:

1. **Parsers** (`src/Parser/`): Fetch and normalize events from external APIs
    - Extend `AbstractParser`, implement `ParserInterface`
    - Each parser has a command name (e.g., `openagenda`, `toulouse.opendata`)
    - Parsers create `EventDto` objects and dispatch them on the Messenger bus (`AbstractParser`), routed to the `parser` transport
    - `parse(?DateTimeImmutable $since)`: `app:events:import` passes the start of the parser's previous successful run (stored in `parser_state`, see `ParserStateRepository`) so incremental sources fetch only what changed since then; `null` (first run, or `--full`) means a full import. `parse($since, $includePast)`: `--whole` (implies `--full`) also asks for the events already over, to backfill; only DataTourisme and OpenAgenda filter them out by default (SowProg always sends them, the affiliate and Toulouse feeds have no history)
    - Removals: a source that says which of its records are gone (OpenAgenda `removed=1`, per agenda; DATAtourisme `isObsolete`) has its parser yield a `RemovedEventDto`; `AbstractParser` dispatches them as `RemoveSourceEvents` (`parser` transport), and `RemoveSourceEventsHandler` hands them to `App\Import\SourceEventRemover`, which flags the events `EventStatus::Removed` + `draft`: out of the listings, search and counts, while `EventVoter` keeps the page open. Listed again, the event comes back (`EventEntityFactory`). SowProg and the affiliate feeds send no tombstone (SowProg lists published events only), so their removals go unnoticed
    - `DataTourismeParser` reads the DATAtourisme API (`DATATOURISME_API_KEY`) through the `datatourisme.client` scoped HTTP client, throttled to the API quotas by the `datatourisme_api` rate limiter (`config/packages/rate_limiter.yaml`)
    - `CDiscountAwinParser` resells Ticketmaster France: its `merchant_product_id` is the Ticketmaster show id (`idmanif`). Each run reads Ticketmaster's France catalogue (`App\Parser\Ticketmaster\TicketmasterCatalogue`, Discovery Feed CSV, `TICKETMASTER_API_KEY` = the app's consumer key) and completes the shows it knows with all their dates, a 2048px picture and the venue's coordinates; without the key or the feed, CDiscount is imported as is. Ticketmaster is no source of its own (no affiliate link): never import it as a parser, its shows would duplicate CDiscount's

2. **Message Queue**: Events are queued in RabbitMQ (Messenger `parser` transport) for async processing

3. **Consumers** (`src/MessageHandler/EventBatchHandler.php`): Process batches of events
    - `DoctrineEventHandler` orchestrates entity resolution and persistence

4. **Entity Resolution** (`src/Handler/`):
    - `EntityProviderHandler`: Resolves DTOs to existing entities (Country, City, Place)
    - `EntityFactoryHandler`: Creates new entities when not found
    - `ComparatorHandler`: Matches DTOs to entities using configurable comparators

5. **Families** (`App\Import\EventFamilyResolver`, after every batch): rows proven to be the same event share one page. Proof is a shared identity hash (`EventContentHasher::identity()`, one source: OpenAgenda's one-record-per-session) or a `CrossSourceLink` (several sources selling the same show). One member is the canonical page, the others redirect to it (`duplicateOf`, with a `DuplicateReason`; a link without a reason was made by hand and is left alone) and lend it their dates. Its page offers every ticketing site of the family, cheapest first (`App\Ticketing\EventTicketOffers`)
    - Cross-source links are written by `bin/console app:events:link-cross-source --apply` (nightly host cron, after the imports; previews without `--apply`): same resolved place, a session day in common, titles naming the same show (`App\Import\CrossSource\EventTitleComparator`), and every two events of a show agreeing, or the whole show stays unlinked. Links of events already over are kept; a link whose events no longer share a venue is taken back (the venues of linked events are scanned too). `--keep-apart=<event id>` parts a false match for good, with the other records of the event (its identity hash): the links stay, flagged `keptApart`, and no record of either side is linked to the other again. A duplicate's change purges its canonical's page, which lists its offer (`EventPageCachePurgeListener`)

### Publishers

A member's event belongs to a **publisher** (`App\Entity\Publisher`: a venue, a collective, a member in their own name), not to the member: `Event::$user` is only its author, `Event::$publisher` its owner. The members who manage a publisher (`Publisher::isManagedBy()`, for now its single `owner`) and the administrators edit and delete its events (`EventVoter`). A member may run several publishers: every organizer page of the personal space is a publisher's, under `/espace-perso/organisateurs/{publisher}/…` (events, imports, exports, its page), as immobilus scopes its profiles by URL, never by session. `PublisherContextSubscriber` (kernel.request, after the firewall) resolves `{publisher}` (404 unknown, 403 not managed, `PublisherVoter::EDIT`) into `App\App\PublisherContext` and sets it as a router context parameter, so `path('app_event_list')` stays on the same publisher without naming it; a link from outside those pages names it. On those pages the navbar shows the publisher next to the member's menu, as immobilus shows its active profile (`fragments/_header-publisher-dropdown.html.twig`): its pages, then the member's other publishers on the same page (`publisher_switch_url()`, `App\Twig\PublisherExtension`) and the creation of another one; nowhere else. The former slugless URLs (`/espace-perso/mes-soirees`, `/nouvelle-soiree`, `/imports`, `/exports`, `/page-organisateur`, `/{id}`) are the entries the site links to (`app_personal_space_*`): they lead to the member's first publisher, or to `/espace-perso/nouvelle-page-organisateur` (named after the member) when they have none, which also creates another one. Imports and exports are a publisher's (`EventImport/EventExport::$publisher`, one job at a time per member still); `Version20261022162404` gave a publisher to every member who had published, named after them. Imported events have none. Deleting the account deletes its publishers. A member deletes a publisher from its page (`app_publisher_delete`, `App\Manager\PublisherRemover`, its name typed again; not from the back office): its events move to another publisher of its owner, which takes its URL (`PublisherLegacySlug`, 301; the URLs that led to it follow), or are deleted with it; a moved event whose reference the other already uses loses it. Its imports and exports go with their files (once the transaction is committed), never while one runs. A member's only publisher can only be deleted once it has no events. Its public page is `/organisateurs/{slug}` (`PublisherController`, the slug is kept on rename), listed in the `publishers` sitemap section once it has an event to come; event cards, the event page and the JSON-LD `organizer` (with `sameAs`) show the publisher, else the author, and a member's page links to the publishers they run.

- **Its page's content** is edited by its owner at `/espace-perso/page-organisateur` (`App\Controller\PersonalSpace\PublisherController`, `PublisherType`, `PublisherVoter::EDIT`; creates the member's publisher if they have none): presentation, website, social links (`App\Enum\PublisherNetwork`, stored as `Publisher::$socialLinks` JSON by network, `PublisherSocialLinksType` refuses a link to another host), logo and cover (`publisher_image` Vich mapping, `publishers.storage`, Picasso loader of the same name). The icons of the links show next to "Organisé par" on its events (`<twig:Publisher:Links>`); `<twig:Publisher:Avatar>` shows the logo, else the initials
- **Incentives** (`App\Stats\PublisherProfileProvider`, over its events online: published, not folded into another): key figures (events, to come, "J'y vais" and favourites, cities), its venues and categories (the member stats queries of `EventRepository` take any base query), distinctions (`App\Enum\PublisherDistinction`, thresholds sized on the dev data for a few dozen publishers each), and for its owner only a completion card (`App\Stats\PublisherCompletion`, `App\Enum\PublisherPageStep`: logo, presentation, links, cover, an event to come, equal weights) In tests, `EventFactory`, `EventImportFactory` and `EventExportFactory` give what has a `user` that member's publisher (`PublisherFactory::forTheMember()`, pass `'publisher'` to choose), and `AppWebTestCase::organizerPath($member, 'evenements')` builds the URL of an organizer page of their first one
- **News** (`bin/console app:publishers:send-news <edition>…`, by hand or by the daily host cron, which lists the editions in order under one `--limit`): e-mails to the owners of a publisher telling what changed on the site. An edition is a case of `App\Enum\PublisherNewsEdition` (its subject and audience) and its two templates `templates/email/publisher-news/<value>.{mjml,txt}.twig` (they extend the `layout` of that directory and include `_feature.mjml.twig`). Its audience (`PublisherNewsAudience`) is the members who logged in within 6 months (the monthly news) or those who did not (a single "what changed", `2026-10-retour`), never both, among the enabled ones who did not opt out (`User::$publisherNews`, a switch on the profile shown to publishers only). Each delivery is recorded (`PublisherNewsDelivery`, one per member and edition) as soon as the e-mail left, so a run again, after a crash or with `--limit` to stay under the mail provider's daily cap, sends to the members still without it. Without `--send` it only counts; `--to=<address> --as=<member's e-mail>` previews it, recording nothing. The e-mails carry `List-Unsubscribe` + `List-Unsubscribe-Post` (RFC 8058) to a signed URL that needs no login (`PublisherNewsController`): a GET only asks (mailboxes' link checkers open links), a POST unsubscribes

### Members' Event Import / Export

`/espace-perso/organisateurs/{publisher}/imports` and `…/exports` (`App\Controller\PersonalSpace\Transfer\ImportController` / `ExportController`, a page each with its history fragment; `ImportVoter`/`ExportVoter` keep each job to its member, and a job is only reached on its publisher's pages; templates under `personal-space/transfers/{import,export}/`; the upload is the shared `.dropzone`, which takes what its field's `accept` allows) let a member import their events from a spreadsheet and export them; both run on a worker (`transfers` transport, `messenger-transfers` program) and their pages follow them live through the member's Mercure channel (`TransferNotifier`), reading their server-rendered fragment again on each update (`assets/js/pages/event_transfers.ts`).

- **Columns** (`App\Transfer\Sheet\EventColumn`): one list for the template, the exports and the imports, found by header (case, accents). Rows sharing an ID, or without one a "Référence externe" (`Event::$externalReference`, unique per publisher), are one event, a row per session; a row with neither is refused (it would be created twice next time). CSV (`;`, UTF-8 BOM; reads `,`/tab and Windows-1252) and XLSX through OpenSpout, 1000 rows at most
- **Check** (`EventImportChecker`, `CheckEventImport`): nothing written. Each event is read by `EventRowMapper` into what the event form would send (a new `MemberEventPublisher::createDto()`, or `EventDtoFactory` over the member's event) and judged as the form is (validator + `DoctrineEventHandler::judge()`); possible duplicates are warnings. It stores two files next to the upload in the private `transfers.storage`: the rows it kept and the rows it refused with an "Erreurs" column
- **Import** (`EventImportRunner`, `RunEventImport`): reads the check's kept rows (what was checked is what is imported), maps them again, saves them in batches with `MemberEventPublisher::saveImported()` (new events are the member's publisher's and drafts unless "Brouillon" says "non", their author goes). An unchanged event is skipped; a run again (resumed after a crash) finds its events by ID/reference. A column the file lacks is left alone; an empty "Image (URL)" keeps the picture, one of our data host's (an export's) too
- **Export** (`EventExporter`, `RunEventExport`): the member's events of a scope, as the import's columns (CSV/XLSX, so export → edit → import updates them) or as a calendar (ICS, `EventCalendarWriter`)
- A member runs one job at a time; starting an import is a conditional UPDATE (`EventImportRepository::transition()`). `app:transfers:cleanup` (daily host cron) fails the jobs stuck for 2 h and deletes those older than 30 days with their files

### Outing Alerts

The members going to an event or keeping it as a favourite (`UserEvent`) are e-mailed when its dates, venue or status change (`App\Outing`). `OutingChangeListener` only flags the event (`Event::$flaggedForAlertAt`, moved to each change's time, written by the same UPDATE; hiding it or publishing it again flags it too); `app:outing-alerts:send` (host cron, four times a day, see the README) runs `OutingChangeNotifier::sendFlagged()`: the events flagged before the run started (strictly: the flag keeps whole seconds), 500 at a time, are compared to what each follower knows (`UserEvent::$knownState`, `OutingChanges`), then unflagged, unless a change came meanwhile, a follower already heard of the event today (one alert a day: told by the next day's first run) or an e-mail could not leave (tried again by the next run). Each alert records `knownState` and `alertedAt` as soon as it left, so a run again sends nothing twice; a lock keeps two runs apart. A DQL bulk update flags nothing

### DTO/Entity Pattern

DTOs (`src/Dto/`) represent imported data before persistence. Key DTOs:

- `EventDto`, `PlaceDto`, `CityDto`, `CountryDto`

Entity factories (`src/EntityFactory/`) convert DTOs to Doctrine entities.

Entity providers (`src/EntityProvider/`) find existing entities matching DTOs.

### Key Services

- **Firewall** (`src/Import/Firewall.php`): Validates and filters event data (with the rest of the import-pipeline services — `Cleaner`, `EventContentHasher`, `EventChangeDetector`, `EventPublicationGuard` — in the `App\Import` namespace)
- **DoctrineEventHandler**: Main orchestrator for event persistence
- **Login return** (`src/Security/LoginTargetPath.php`): a "log in to…" link passes `?_target_path=` (a local path, optionally with a fragment) to the login, sign-up or social login, which keep it in the firewall's target-path session entry and lead back to it. `#participer` makes the event page record the "J'y vais" clicked before the login (`assets/js/listeners/like.ts`)
- **Images**: Handled by the `silarhi/picasso-bundle` (`config/packages/picasso.yaml` — Glide transformer, blur placeholders, thumbnails cached in `thumbs.storage`). Render images with the `<twig:Picasso:Image>` component; per-entity picture services live in `src/Picture/` (e.g. `EventProfilePicture`, `UserProfilePicture`)

### Routing

Routes are location-prefixed (e.g., `/toulouse`, the location page and its agenda, then `/toulouse/2`; `/toulouse/agenda/sortir/concert`; `/toulouse/` and the former `/toulouse/agenda` 301 to `/toulouse`). `AppContextSubscriber` (`src/EventSubscriber/AppContextSubscriber.php`, on `kernel.request`) resolves the `{location}` parameter to a `Location` value object containing City/Country context and stores it in `App\App\AppContext`. Countries and cities share that segment (`/france`, `/toulouse`): a country's slug comes first (`App\App\CountrySlugs`, read once per request), no city takes one (`CitySlugHandler`, Gedmo), and the former `/c--france/…` URLs 301 in one hop (`LegacyCountryUrlSubscriber`, before the router). The cities of a country flagged `Country::$prefixesCities` (Switzerland, Belgium, Monaco) live under it: their slug holds the country's (`suisse/geneve`, `{location}` accepts one prefix, see `App\Routing\LocationRequirement`), and their former slugs (`city_legacy_slug`) redirect in one hop (`CityMovedException`, `MovedCitySubscriber`). Routes without a `{location}` parameter take the city a logged-in member chose on their profile (`User::$city`, looked up by `AppContext` only when a page reads the location), else have none.

### Caching

- HTTP cache headers via the `Symfony\Component\HttpKernel\Attribute\Cache` attribute on controllers (e.g. `src/Controller/Location/EventController.php`): public pages use `#[Cache(maxage: 0, smaxage: …, public: true)]`, shared by Cloudflare, never kept by the browser (it would outlive a login). Reading the security token (`app.user`, `getUser()`, `is_granted()`) marks the session as used, which makes Symfony turn the response private; `SharedCacheSubscriber` undoes that for visitors without a session or `REMEMBERME` cookie, so members always get private pages. Cloudflare only caches HTML through a cache rule that must bypass requests carrying either cookie. Never read the session (e.g. `app.flashes`) without checking `app.request.hasPreviousSession` first
- Event pages (`src/Cdn/EventPageCache.php`) stay in Cloudflare a day (a week once ended, never past the midnight an upcoming event ends) and carry `Cache-Tag: event,event-<id>,place-<id>,publisher-<id>`. `EventPageCachePurgeListener` purges an event's tag when a flush changes the event, its sessions, comments or participants, its place's address, or its publisher's name, logo, website or social links (one `PurgeCdnCacheTags` message of up to 100 tags per flush; `PurgeCdnCacheTagsHandler` packs consecutive messages into requests of 100 distinct tags); DQL bulk updates purge nothing. An imported event whose new picture is still to download is purged once, by the download's flush (`EventImageDownloadScheduler::defersPagePurge()`, `DownloadEventImages::$pageEventIds`), even when the picture does not change `bin/console app:cdn:purge-events` drops every event page in one API call (after a release that changes the event template)
- Built assets ship inside the Docker image (`public/build`, `public/bundles`). Production mounts the external volume `by-nightfr_assets` on `/app/public/build` of `app` and `app-images`; `deploy.sh` fills it from the new image right after the pull (see README), so every release's hashed files accumulate next to the current ones and old tabs or sent emails keep resolving; nothing prunes that volume. Caddy (`docker/Caddyfile`) serves `/build/*` and `/bundles/*` same-origin with one-year `immutable` headers, cached by Cloudflare. There is no separate asset host: `asset()` yields paths, so anything that needs an absolute URL (og:image, JSON-LD, emails) wraps it in `absolute_url()` / `UrlHelper`
- Redis for application caching (`$memoryCache` in `config/services.yaml`, bound to the `redis.app_cache_pool` pool)
- CDN purge (`src/Cdn/CloudflareCdnPurger.php`: files, tags or prefixes, 100 per call, throttled per Cloudflare quota: `cloudflare_purge` 5 tag/prefix requests a minute, `cloudflare_purge_files` 8 URL requests a second; the `PurgeCdnCache*` messages ride their own `cdn` transport and worker, since the Cloudflare throttle holds it up to 12 s per request) and thumbnail cleanup when an image is deleted or replaced, via the `PurgeCdnCacheUrl` (the original on the data host) / `RemoveImageThumbnails` messages dispatched by `src/EventSubscriber/ImageSubscriber.php`. `RemoveImageThumbnailsHandler` deletes the thumbnails from `thumbs.storage`, then queues a `PurgeCdnCachePrefix` for `by-night.fr/p/image/glide/vich/<path>/`, since Cloudflare keeps thumbnails a year. `tests/EventSubscriber/ImageRemovalTest.php` runs the whole chain

### Search

Elasticsearch indexes defined in `config/packages/fos_elastica.yaml`:

- `event` index with French language analyzers
- Async document persistence via Symfony Messenger
- Keywords (`/recherche`, the agenda's `?term=`, the header search): `EventElasticaRepository::createKeywordsQuery()`, every word required but the stop words (`french_stop`, search side only), found as typed across the fields naming the event first, then inflected, in a theme, in the description, and with a typo in the name last (`Fuzzy`: never on the first letter). Ties go by the next date; the header search also matches the beginnings of words (`name.autocomplete`). `/recherche` and the header search take the page's `?city=` slug, whose events and those around score up to ×3. The nightly agenda types keep their own query (`createTypeKeywordsQuery()`)
- A change of the mapping or the analyzers needs `bin/console fos:elastica:populate --index=event` (the index is recreated: about 15 min of partial results, and the queries naming a new analyzer fail until it starts)

## Frontend Architecture

### JavaScript Application Structure

The frontend uses a modular listener-based architecture with dependency injection, on top of `@silarhi/app-js` (`App`, its service container, and the `defineListener()`, `defineModule()`, `definePage()` helpers; also its DOM helpers `findOne`, `findAll`, `on`, `trigger`, `appendHTML`).

**Main App** (`assets/js/app.ts`, exposed as `window.App`):

- Boots from `window.appParameters`, which `templates/base.html.twig` sets in a classic script before the entry: initializes Sentry, runs every module once (`app.start()`), then mounts the whole document. Without them (the error pages) the app does not start
- Errors caught in a module, a listener or a page go to its `onError` handler (`reportError`)
- `mount(container)` connects every listener to its matching elements inside `container`; call it after inserting HTML by AJAX. Bookkeeping is per element, so an element already connected is skipped
- `unmount(container)` runs the cleanups of every connected element the container covers (and of those already removed from the DOM)
- Stimulus controllers (`assets/controllers/`) are loaded too, through `assets/stimulus_bootstrap.ts`

**Building Blocks**:

1. **Modules** (`assets/js/modules/`): Functions run once at `start()`, declared with `defineModule(function name() {…})` (a named function: its name tags the errors sent to Sentry), bound to `document`/`window`, never to elements inside `body`
    - `autocomplete.ts` - Algolia autocomplete search
    - `scroll-to-top.ts` - Scroll behavior

2. **Listeners** (`assets/js/listeners/`): `{selector, connect}` objects declared with `defineListener<E>({…})` and registered in `app.ts`, `E` being the element its selector matches; `connect(element, {app})` runs once per matching element and may return a cleanup. The selector is only evaluated when a container mounts, so it must not match on state JS toggles later
    - `form-confirm.ts` - Confirmation before a form is sent
    - `form-errors.ts` - Client-side form validation
    - `like.ts` - Event favoriting
    - `popup.ts` - Modal interactions
    - etc.

3. **UI Services** (`assets/js/services/ui/`): Heavy third-party widgets wrapped as services exporting a `create()` function, imported only by the page entry points that need them (statically, or via dynamic `import()` as in `assets/js/listeners/image-previews.ts`)
    - `DatepickerService.ts` - Date and date range picker (vanilla-calendar-pro)
    - `SliderService.ts` - Range sliders (nouislider)
    - `TagsService.ts` - Tag inputs (tom-select)
    - `WysiwygService.ts` - Rich text editor (summernote)
    - `AutocompleteService.ts` - Autocomplete inputs (@tarekraafat/autocomplete.js)
    - `FancyboxService.ts` - Image lightbox (fancybox)

**Page-Specific Scripts** (`assets/js/pages/`):

- Separate entry points for each major page (agenda, event_details, profile, etc.), included only on their routes to reduce bundle size
- Each default-exports its initializer (`export default definePage(…)`), imported by the `pages` loaders of `app.ts`: the entry tag of its template only preloads that chunk (same file, loaded once). A second page of the same file is a named export its loader picks (`agenda_map`)
- A template runs it with the `load_page()` Twig function (`src/Twig/PageExtension.php`): `<div {{ load_page('agenda', {…}) }}>` emits a `data-page` marker, and the app's built-in `data-page` listener calls the initializer (after every other listener) with `{app, container, ...params}` once its chunk has loaded; the initializer may return a cleanup

**Dependency Injection** (the `@silarhi/app-js` container):

- Lazy: each service is built by its factory on first `get()`, then shared
- Services registered in `assets/js/services.ts` (`defineServices()`), typed by declaration merging into the package's `ServiceMap` (and the parameters into `ParameterMap`)
- Access via `app.get('serviceName')` or `window.App.get('serviceName')`, which returns the registered type; parameters via `app.getParameter('locale')`

**Key Services**:

- `modalManager` - Bootstrap modal wrapper
- `toastManager` - Toast notifications
- `collectionManager` - Symfony form collections: `@silarhi/app-js/form`'s `CollectionManager` (a service like the others; `collectionListener('collectionManager')` in `app.ts` hands it the add/remove buttons; `collection.add`/`added`/`remove`/`removed` events, bubbling, typed in `assets/types/collection-events.d.ts`)

**Vite Configuration** (`vite.config.ts`):

- `@symfony/reprise` plugin: writes `public/build/entrypoints.json` + `manifest.json`, read by the `reprise_entry_link_tags()` / `reprise_entry_script_tags()` Twig functions; registers the Stimulus controllers (`assets/controllers.json`) and copies `assets/images` to `public/build/images`
- One entry point per page (`app`, `admin`, and each `assets/js/pages/*.ts` listed in `pages`)
- JSX compiled for Preact with the automatic runtime (`importSource: 'preact'`), no `h` import needed
- `@` aliases `assets/`
- `pnpm dev` / `pnpm watch` build with sourcemaps and without minification; no PurgeCSS
- Sass uses the module system: every stylesheet starts with `@use '<path>/core' as *;` (`assets/scss/_core.scss` configures Tabler once with `@forward … with (…)` and forwards the site's tokens from `_variables.scss`); `ui/_tabler.scss` is the curated list of Tabler partials. Custom properties are written bare (`var(--primary)`, as in Tabler's sources): a PostCSS step in `vite.config.ts` prefixes those of `assets/scss` with `--bs-`
- `build.cssTarget` is Tabler's browser baseline (Chrome 123, Firefox 128, Safari 17.5): below it Lightning CSS would polyfill `light-dark()` and break the `data-bs-theme="dark"` islands

**Code Style**:

- TypeScript (`tsconfig.json`): `strict`, no `any`, no `@ts-ignore`; untyped third-party code gets a narrow shim in `assets/types/` (`declare module`, jQuery plugins on `JQuery` in `jquery-plugins.d.ts`). Tests pass their plain-object doubles through `fake<T>()` (`assets/js/test/fake.ts`) and connect a listener with `connectListener()` (`assets/js/test/listener.ts`; `connectListenerWithoutCleanup()` where it leaves nothing to clean up), never with its own `connect()`
- Icons are the SVG files of `assets/icons/`, rendered by `<twig:Icon>` in Twig and, in TypeScript, from the dump `silarhi/ux-icons-js-bundle` writes into `assets/js/icons/index.js` with the `ux_icons` attributes, on every cache rebuild or with `bin/console ux-icons-js:dump` (`lucide:trash-2` is `LucideTrash2Icon`): `renderIcon()` of `@silarhi/ux-icons-js` for HTML strings, `UXIcon` of `@silarhi/ux-icons-js/preact` in the Preact components, never drawn by hand. The dump is committed, so the frontend builds without PHP: commit it again after adding an SVG or changing `ux_icons` (prod dumps to `var/icons` instead, see `config/packages/silarhi_ux_icons_js.yaml`). A `class` replaces the configured `icon` class, as in Twig
- Biome (`biome.json`) lints and formats TypeScript: 120 char width, single quotes, 4 space indent, no semicolons
- Prettier only for non-JS files (SCSS, Markdown, YAML)

## Key Directories

### Backend

- `src/Parser/` - Event data parsers for external sources
- `src/Handler/` - Business logic handlers (DoctrineEventHandler, EventHandler)
- `src/Import/` - Import-pipeline domain services (Firewall, Cleaner, content hashing, dedup)
- `src/MessageHandler/` - Messenger handlers (EventBatchHandler consumes the `parser` transport in batches)
- `src/Dto/` - Data transfer objects for import pipeline
- `src/EntityFactory/` - DTO to Entity conversion
- `src/EntityProvider/` - Entity lookup services
- `src/Comparator/` - Entity matching logic
- `src/Controller/Location/` - Location-scoped controllers (agenda, events)
- `config/packages/` - Bundle configuration

### Frontend

- `assets/js/app.ts` - Main application entry point
- `assets/types/` - Type shims for untyped third-party code
- `assets/js/pages/` - Page-specific entry points (agenda, event_details, etc.)
- `assets/js/modules/` - One-time modules run at boot
- `assets/js/listeners/` - Per-element listeners, connected on every mount
- `assets/controllers/` - Stimulus controllers
- `assets/js/services/` - Service classes
- `assets/js/services/ui/` - Heavy third-party widgets (datepicker, selects, wysiwyg, ...) loaded only where needed
- `assets/js/components/` - Reusable UI components (Widgets, CommentApp, etc.)
- `assets/js/utils/` - Utility functions (DOM helpers, CSS helpers, etc.)
- `assets/scss/` - Sass stylesheets
