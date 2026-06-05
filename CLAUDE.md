# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

By Night is an event management platform for France (https://by-night.fr). It aggregates events from multiple external sources (OpenAgenda, DataTourisme, Awin partners, etc.) and provides a searchable, location-based event discovery interface.

## Tech Stack

- **Backend**: PHP 8.4, Symfony 7.4
- **Database**: MySQL 8.0 with Doctrine ORM
- **Search**: Elasticsearch 7 with FOSElasticaBundle
- **Caching**: Redis, Varnish 6
- **Message Queue**: RabbitMQ (php-amqplib/rabbitmq-bundle)
- **File Storage**: AWS S3 / CloudFront, Flysystem
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
vendor/bin/phpunit tests/Utils/FirewallTest.php    # Single test file

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

Tests build their fixtures with **Zenstruck Foundry** factories (`src/Factory/`), **never by hand through the `EntityManager`**. Factories extend `PersistentProxyObjectFactory`, so each created object is a proxy exposing an Active-Record-like API.

```php
use App\Factory\EventFactory;
use function Zenstruck\Foundry\Persistence\flush_after;

$event = EventFactory::createOne(['name' => 'Test']);    // persisted proxy
$event->_delete();                                        // remove + flush
$event->_refresh();                                       // reload from the DB

EventFactory::find(['externalId' => 'x']);                // single result (throws if missing)
EventFactory::findBy(['email' => 'a@b.c']);               // list
EventFactory::count(['duplicateOf' => null]);             // fresh COUNT(*), ignores the unit of work
```

Conventions:

- **Add a factory** in `src/Factory/` for any entity a test needs — don't `new` + `persist()`/`flush()` in the test, and don't pull the `EntityManager` to create, query, or delete fixtures.
- **Deleting several entities in one flush** (e.g. to exercise an `onFlush`/`postFlush` listener's de-duplication): wrap the `_delete()` calls in `flush_after(fn () => …)` so they share a single flush instead of one flush each.
- **Asserting "nothing was persisted"** after code that leaves unflushed in-memory changes (e.g. a `--dry-run` command): use `Factory::count(...)`, which runs a fresh `SELECT COUNT(*)` and ignores the dirty unit of work. Don't use `find()` here — the proxy's auto-refresh throws `ObjectHasUnsavedChanges` on dirty entities.
- Use the `ResetDatabase` + `Factories` traits (see `tests/AppKernelTestCase.php`).
- Fetch up-to-date Foundry usage from context7 (`/zenstruck/foundry`) rather than guessing the 2.x API.

### Email Tests (MJML)

Transactional emails are MJML templates (`templates/email/*.mjml.twig`) rendered in-process by the `mjml_php` renderer (`config/packages/mjml.yaml`), which requires the **`ext-mjml`** PHP extension (Rust `alekitto/mjml-php`). The extension is compiled into the Docker image but is **not present in a local Homebrew PHP**, so any test that asserts a sent email (`assertEmailCount`, `assertEmailHtmlBodyContains`, …) fails locally with `Class "Mjml\Mjml" not found` and an HTTP 500. Run the email-sending tests in Docker/CI, or install the extension locally.

### Event Import Pipeline

```bash
# Setup message queues
bin/console rabbitmq:setup-fabric
bin/console messenger:setup-transports

# Import events from a parser
bin/console app:events:import <parser-name> -vv

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

2. **Message Queue**: Events are queued in RabbitMQ for async processing

3. **Consumers** (`src/Consumer/AddEventConsumer.php`): Process batches of events
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

- **Firewall** (`src/Utils/Firewall.php`): Validates and filters event data
- **DoctrineEventHandler**: Main orchestrator for event persistence
- **ImageHelper** (`src/Image/Helper/ImageHelper.php`): Image processing with Glide

### Routing

Routes are location-prefixed (e.g., `/toulouse/agenda`). The `{location}` parameter is resolved by `LocationConverter` to a `Location` value object containing City/Country context.

### Caching

- Varnish reverse proxy with tag-based invalidation (`TagsInvalidator`)
- `#[ReverseProxy]` annotation for cache control on controllers
- Redis for application caching

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

3. **Lazy Listeners** (`assets/js/lazy-listeners/`): Heavy dependencies loaded on-demand
    - `dates.js` - Date range picker (moment.js, daterangepicker)
    - `selects.js` - Enhanced select boxes (select2)
    - `wysiwyg.js` - Rich text editor (summernote)

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
- `src/Consumer/` - RabbitMQ message consumers
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
- `assets/js/lazy-listeners/` - Heavy dependencies loaded on-demand
- `assets/js/services/` - DI container and service classes
- `assets/js/components/` - Reusable UI components (Widgets, CommentApp, etc.)
- `assets/js/utils/` - Utility functions (DOM helpers, CSS helpers, etc.)
- `assets/scss/` - Sass stylesheets
