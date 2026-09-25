# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

By Night is an event management platform for France (https://by-night.fr). It aggregates events from multiple external sources (OpenAgenda, DataTourisme, Awin partners, etc.) and provides a searchable, location-based event discovery interface.

## Tech Stack

- **Backend**: PHP 8.4, Symfony 7.4
- **Database**: MySQL 8.0 with Doctrine ORM
- **Search**: Elasticsearch 7 with FOSElasticaBundle
- **Caching**: Redis (application cache), HTTP cache headers + Cloudflare CDN
- **Message Queue**: RabbitMQ (php-amqplib/rabbitmq-bundle)
- **File Storage**: S3-compatible bucket via Flysystem, exposed as `data.by-night.fr`
- **Frontend**: Webpack Encore, Bootstrap 5, jQuery, Sass, Preact (for reactive components)
- **Error Tracking**: Sentry

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

```bash
# Install dependencies
composer install
yarn install

# Build frontend assets
yarn run dev          # Development build
yarn run watch        # Watch mode
yarn run build        # Production build

# Start local services (requires Docker)
docker-compose up -d
```

### Testing & Quality

```bash
# PHP Tests
vendor/bin/phpunit                                 # All tests
vendor/bin/phpunit tests/Import/FirewallTest.php   # Single test file

# Static Analysis
vendor/bin/phpstan analyse                         # PHPStan (level 6)

# Code Formatting
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php  # PHP (Symfony ruleset)
vendor/bin/twig-cs-fixer lint --fix --config=.twig-cs-fixer.php  # Twig templates
npx prettier --write "assets/**/*.{scss,md,yaml,yml}"  # Prettier for styles/config
npx eslint --fix "assets/**/*.{js,jsx}"            # ESLint for JavaScript

# Pre-commit Hook
# Husky runs lint-staged on commit, which auto-formats changed files:
# - PHP: php-cs-fixer
# - Twig: twig-cs-fixer
# - JS/JSX: eslint --fix
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
bin/console rabbitmq:setup-fabric
bin/console messenger:setup-transports

# Import events from a parser
bin/console app:events:import <parser-name> -vv          # changes since the parser's last run
bin/console app:events:import <parser-name> --full -vv   # whole catalogue

# Process queued events
bin/console rabbitmq:batch:consumer add_event -vv
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
    - Parsers create `EventDto` objects and publish them via `EventProducer`
    - `parse(?DateTimeImmutable $since)`: `app:events:import` passes the start of the parser's previous successful run (stored in `parser_state`, see `ParserStateRepository`) so incremental sources fetch only what changed since then; `null` (first run, or `--full`) means a full import
    - `DataTourismeParser` reads the DATAtourisme API (`DATATOURISME_API_KEY`) through the `datatourisme.client` scoped HTTP client, throttled to the API quotas by the `datatourisme_api` rate limiter (`config/packages/rate_limiter.yaml`)

2. **Message Queue**: Events are queued in RabbitMQ for async processing

3. **Consumers** (`src/MessageHandler/EventBatchHandler.php`): Process batches of events
    - `DoctrineEventHandler` orchestrates entity resolution and persistence

4. **Entity Resolution** (`src/Handler/`):
    - `EntityProviderHandler`: Resolves DTOs to existing entities (Country, City, Place)
    - `EntityFactoryHandler`: Creates new entities when not found
    - `ComparatorHandler`: Matches DTOs to entities using configurable comparators

### DTO/Entity Pattern

DTOs (`src/Dto/`) represent imported data before persistence. Key DTOs:

- `EventDto`, `PlaceDto`, `CityDto`, `CountryDto`

Entity factories (`src/EntityFactory/`) convert DTOs to Doctrine entities.

Entity providers (`src/EntityProvider/`) find existing entities matching DTOs.

### Key Services

- **Firewall** (`src/Import/Firewall.php`): Validates and filters event data (with the rest of the import-pipeline services — `Cleaner`, `EventContentHasher`, `EventChangeDetector`, `EventPublicationGuard` — in the `App\Import` namespace)
- **DoctrineEventHandler**: Main orchestrator for event persistence
- **Images**: Handled by the `silarhi/picasso-bundle` (`config/packages/picasso.yaml` — Glide transformer, blur placeholders, thumbnails cached in `thumbs.storage`). Render images with the `<twig:Picasso:Image>` component; per-entity picture services live in `src/Picture/` (e.g. `EventProfilePicture`, `UserProfilePicture`)

### Routing

Routes are location-prefixed (e.g., `/toulouse/agenda`). `AppContextSubscriber` (`src/EventSubscriber/AppContextSubscriber.php`, on `kernel.request`) resolves the `{location}` parameter to a `Location` value object containing City/Country context and stores it in `App\App\AppContext`. Routes without a `{location}` parameter fall back to the `app_city` cookie.

### Caching

- HTTP cache headers via the `Symfony\Component\HttpKernel\Attribute\Cache` attribute on controllers (e.g. `src/Controller/Location/EventController.php`)
- Built assets ship inside the Docker image (`public/build`, `public/bundles`). Production mounts the external volume `by-nightfr_assets` on `/app/public/build` of `app` and `app-images`; `deploy.sh` fills it from the new image right after the pull (see README), so every release's hashed files accumulate next to the current ones and old tabs or sent emails keep resolving; nothing prunes that volume. Caddy (`docker/Caddyfile`) serves `/build/*` and `/bundles/*` same-origin with one-year `immutable` headers, cached by Cloudflare. There is no separate asset host: `asset()` yields paths, so anything that needs an absolute URL (og:image, JSON-LD, emails) wraps it in `absolute_url()` / `UrlHelper`
- Redis for application caching (`$memoryCache` in `config/services.yaml`, bound to the `redis.app_cache_pool` pool)
- CDN purge (`src/Cdn/CloudflareCdnPurger.php`) and thumbnail cleanup on image changes, via the `PurgeCdnCacheUrl` / `RemoveImageThumbnails` messages dispatched by `src/EventSubscriber/ImageSubscriber.php`

### Search

Elasticsearch indexes defined in `config/packages/fos_elastica.yaml`:

- `event` index with French language analyzers
- Async document persistence via Symfony Messenger

## Frontend Architecture

### JavaScript Application Structure

The frontend uses a modular listener-based architecture with dependency injection:

**Main App** (`assets/js/app.js`):

- Bootstraps the application with configuration from Twig templates
- Initializes Sentry error tracking
- Manages a dependency injection container (`Container.js`)
- Dispatches two types of listeners:
    - **Global listeners**: Execute once on app initialization (autocomplete, lazyload, scroll-to-top)
    - **Page listeners**: Execute on page load and after AJAX updates (forms, modals, tooltips, etc.)
- Provides `window.App.dispatchPageLoadedEvent(container)` to re-initialize listeners on dynamic content

**Listener Types**:

1. **Global Listeners** (`assets/js/global-listeners/`): Run once at app startup
    - `lazyload.js` - Lazy image loading with lazysizes
    - `autocomplete.js` - Algolia autocomplete search
    - `scroll-to-top.js` - Scroll behavior

2. **Page Listeners** (`assets/js/listeners/`): Run on page load and after AJAX updates
    - `form-collection.js` - Dynamic form field addition/removal
    - `form-errors.js` - Client-side form validation
    - `like.js` - Event favoriting
    - `popup.js` - Modal interactions
    - etc.

3. **UI Services** (`assets/js/services/ui/`): Heavy third-party widgets wrapped as services exporting a `create()` function, imported only by the page entry points that need them (statically, or via dynamic `import()` as in `assets/js/modules/image-previews.js`)
    - `DatepickerService.js` - Date range picker (moment.js, daterangepicker)
    - `SelectService.js` / `TagsService.js` - Enhanced select boxes and tag inputs (tom-select)
    - `WysiwygService.js` - Rich text editor (summernote)
    - `AutocompleteService.js` - Autocomplete inputs (@tarekraafat/autocomplete.js)
    - `FancyboxService.js` - Image lightbox (fancybox)

**Page-Specific Scripts** (`assets/js/pages/`):

- Separate entry points for each major page (agenda, event_details, search, etc.)
- Loaded only on specific routes to reduce bundle size
- Use `window.App.dispatchPageLoadedEvent()` to reinitialize listeners after AJAX loads

**Dependency Injection** (`assets/js/services/Container.js`):

- Simple DI container with lazy instantiation
- Services registered in `assets/js/services.js`
- Access via `di.get('serviceName')` or `window.App.get('serviceName')`

**Key Services**:

- `modalManager` - Bootstrap modal wrapper
- `toastManager` - Toast notifications
- `formManager` - Form field visibility/disabled/required state management
- `collectionManager` - Dynamic form collections (add/remove form fields)

**Webpack Configuration**:

- Uses Symfony Webpack Encore
- Split entry points for each page (code splitting)
- Babel transforms JSX to Preact (`h` pragma)
- ESLint runs on build in dev mode with auto-fix
- PurgeCSS in production removes unused Bootstrap classes

**Code Style**:

- ESLint with `@eslint/js` recommended rules
- Prettier for formatting (120 char width, single quotes, 4 space tabs)
- No semicolons (enforced by ESLint)
- Flat config format (`eslint.config.mjs`)

## Key Directories

### Backend

- `src/Parser/` - Event data parsers for external sources
- `src/Handler/` - Business logic handlers (DoctrineEventHandler, EventHandler)
- `src/Import/` - Import-pipeline domain services (Firewall, Cleaner, content hashing, dedup)
- `src/MessageHandler/` - RabbitMQ message handlers (EventBatchHandler consumes the import queue)
- `src/Dto/` - Data transfer objects for import pipeline
- `src/EntityFactory/` - DTO to Entity conversion
- `src/EntityProvider/` - Entity lookup services
- `src/Comparator/` - Entity matching logic
- `src/Controller/Location/` - Location-scoped controllers (agenda, events)
- `config/packages/` - Bundle configuration

### Frontend

- `assets/js/app.js` - Main application entry point
- `assets/js/pages/` - Page-specific entry points (agenda, search, etc.)
- `assets/js/global-listeners/` - One-time initialization listeners
- `assets/js/listeners/` - Re-runnable page listeners
- `assets/js/services/` - DI container and service classes
- `assets/js/services/ui/` - Heavy third-party widgets (datepicker, selects, wysiwyg, ...) loaded only where needed
- `assets/js/components/` - Reusable UI components (Widgets, CommentApp, etc.)
- `assets/js/utils/` - Utility functions (DOM helpers, CSS helpers, etc.)
- `assets/scss/` - Sass stylesheets
