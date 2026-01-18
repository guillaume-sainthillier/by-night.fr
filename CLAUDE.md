# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

By Night is an event management platform for France (https://by-night.fr). It aggregates events from multiple external sources (OpenAgenda, DataTourisme, Awin partners, etc.) and provides a searchable, location-based event discovery interface.

## Tech Stack

-   **Backend**: PHP 8.4, Symfony 7.4
-   **Database**: MySQL 8.0 with Doctrine ORM
-   **Search**: Elasticsearch 7 with FOSElasticaBundle
-   **Caching**: Redis, Varnish 6
-   **Message Queue**: RabbitMQ (php-amqplib/rabbitmq-bundle)
-   **File Storage**: AWS S3 / CloudFront, Flysystem
-   **Frontend**: Webpack Encore, Bootstrap 5, jQuery, Sass

## Common Commands

### Development

```bash
# Install dependencies
composer install
npm install

# Build frontend assets
npm run dev          # Development build
npm run watch        # Watch mode
npm run build        # Production build

# Start local services (requires Docker)
docker-compose up -d
```

### Testing & Quality

```bash
# Run tests
vendor/bin/phpunit                           # All tests
vendor/bin/phpunit tests/Utils/FirewallTest.php  # Single test file

# Static analysis
vendor/bin/phpstan analyse                   # PHPStan (level 6)

# Code formatting
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
vendor/bin/twig-cs-fixer lint --fix --config=.twig-cs-fixer.php
npm run lint                                 # ESLint for JS
```

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

-   `EventDto`, `PlaceDto`, `CityDto`, `CountryDto`

Entity factories (`src/EntityFactory/`) convert DTOs to Doctrine entities.

Entity providers (`src/EntityProvider/`) find existing entities matching DTOs.

### Key Services

-   **Firewall** (`src/Utils/Firewall.php`): Validates and filters event data
-   **DoctrineEventHandler**: Main orchestrator for event persistence
-   **ImageHelper** (`src/Image/Helper/ImageHelper.php`): Image processing with Glide

### Routing

Routes are location-prefixed (e.g., `/toulouse/agenda`). The `{location}` parameter is resolved by `LocationConverter` to a `Location` value object containing City/Country context.

### Caching

-   Varnish reverse proxy with tag-based invalidation (`TagsInvalidator`)
-   `#[ReverseProxy]` annotation for cache control on controllers
-   Redis for application caching

### Search

Elasticsearch indexes defined in `config/packages/fos_elastica.yaml`:

-   `event` index with French language analyzers
-   Async document persistence via Symfony Messenger

## Key Directories

-   `src/Parser/` - Event data parsers for external sources
-   `src/Handler/` - Business logic handlers (DoctrineEventHandler, EventHandler)
-   `src/Consumer/` - RabbitMQ message consumers
-   `src/Dto/` - Data transfer objects for import pipeline
-   `src/EntityFactory/` - DTO to Entity conversion
-   `src/EntityProvider/` - Entity lookup services
-   `src/Comparator/` - Entity matching logic
-   `src/Controller/Location/` - Location-scoped controllers (agenda, events)
-   `config/packages/` - Bundle configuration
