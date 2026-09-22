# Event Handler Tests

## Overview

This directory contains integration tests for the event insertion and merging process in the By Night application.

## Test Coverage

The `DoctrineEventHandlerTest.php` file includes comprehensive tests for:

### 1. Event Insertion (`testInsertNewEvent`)

- **Purpose**: Verifies that a new event is successfully inserted into the database
- **What it tests**:
    - Event creation with all required fields
    - Database persistence
    - External ID tracking
    - Place association during insertion

### 2. Event Merging (`testMergeWithExistingEvent`)

- **Purpose**: Tests that events with the same external ID are merged rather than duplicated
- **What it tests**:
    - Existing event lookup by external ID and origin
    - Update of existing event data
    - Preservation of entity ID
    - Data field updates (name, description, etc.)

### 3. Duplicate Detection (`testNoDuplicatesByExternalId`)

- **Purpose**: Ensures no duplicate events are created for the same external ID
- **What it tests**:
    - Multiple insertion attempts with same external ID
    - Proper merging behavior
    - Single entity constraint enforcement

### 4. Multiple Events (`testMultipleEventsWithDifferentExternalIds`)

- **Purpose**: Validates batch insertion of multiple distinct events
- **What it tests**:
    - Handling of multiple events in one operation
    - Unique event separation
    - Batch processing performance

### 5. Validation/Filtering (`testInvalidEventIsFiltered`)

- **Purpose**: Tests the firewall filtering of invalid events
- **What it tests**:
    - Minimum name length validation (3 characters for non-affiliates)
    - Minimum description length validation (10 characters)
    - Prevention of invalid event insertion

### 6. Place Association (`testPlaceAssociationDuringInsertion`)

- **Purpose**: Verifies proper place lookup and association
- **What it tests**:
    - Existing place resolution by external ID
    - Place reuse instead of duplication
    - Proper foreign key relationships

### 7. Batch Performance (`testBatchInsertionPerformance`)

- **Purpose**: Tests batch insertion efficiency
- **What it tests**:
    - Handling of multiple events in a single batch
    - Performance within acceptable limits
    - Memory management

### 8. Contact Information (`testEventWithAllContactInformation`)

- **Purpose**: Validates storage of contact details
- **What it tests**:
    - Phone contacts array persistence
    - Email contacts array persistence
    - Website contacts array persistence

### 9. Timestamps (`testEventTimestampsAreSet`)

- **Purpose**: Ensures proper timestamp management
- **What it tests**:
    - Creation timestamp is set
    - Update timestamp is set
    - Timestamps are accurate

### 10. Failed batch rollback (`testFailedBatchIsRolledBackAndCanBeRetried`)

- **Purpose**: A batch runs in one transaction; a failure mid-merge must leave no trace
- **What it tests**:
    - No `parser_data` row (and thus no content hash) survives the failed batch
    - No message (image download) leaves before the commit
    - Retrying the same DTO (what `EventBatchHandler` does) imports the event
    - Uses `FailOnceEventEntityFactory`, a test double swapped into the container

### 11. Batched collection loading on merge (`testMergingExistingEventsBatchLoadsTimesheetsAndThemes`)

- **Purpose**: Guards against the N+1 on the update path
- **What it tests**:
    - Timesheets and themes of existing events come from one query each, not one per event
    - Relies on `doctrine.debug_data_holder` (query logging in the test environment)

### 12. Concurrent workers (`tests/MessageHandler/EventBatchHandlerTest.php`)

- **Purpose**: Two workers importing the same new venue must not create a duplicate
- **What it tests**:
    - The second insert hits the unique key on `place_metadata`, the batch is rolled back
    - `EventBatchHandler` re-runs the batch once at warning level and it resolves the other worker's venue
    - Uses `StaleLookupPlaceEntityProvider`, a test double whose lookups miss on purpose once

### 13. Event families (`testSiblingsWithDistinctExternalIdsAreGroupedIntoAFamily`)

- **Purpose**: The same event published under distinct external ids (an OpenAgenda organizer creating one event per session) must show up once
- **What it tests**:
    - Both rows get the same identity hash (`EventContentHasher::identity()`) and form a family: the oldest is the canonical, the other one redirects to it and is not indexable
    - The canonical carries the sibling's date as an inherited timesheet (`source_event_id`) and its range spans both sessions
    - Re-importing the sibling with a moved date replaces the inherited row; re-importing the canonical re-syncs its own rows and keeps the inherited one
    - The resolution itself is covered in `tests/Import/EventFamilyResolverTest.php`, the catch-up command in `tests/Command/EventsResolveFamiliesCommandTest.php`

### 14. Batch history sources (`testTheBatchHistoryRecordsTheSourceOfItsEvents`, `testTheBatchHistoryKeepsEverySourceOfAMixedBatch`, `testABatchWithoutAnySourceRecordsNone`)

- **Purpose**: The `parser_history` row written per batch (the "Historiques" admin page) must say which parsers the events came from
- **What it tests**:
    - `fromData` (a `json` list) holds the parser display names carried by `EventDto::$fromData` (the same value as `Event::$fromData`), once each in first-seen order, no longer a `?` placeholder
    - A DTO without a source (built by hand) adds nothing, and a batch without any stores an empty list
    - The counters of the batch (new events) are recorded alongside
    - The admin page rendering and the search on that column are covered in `tests/Controller/Admin/ParserHistoryCrudControllerTest.php`

### 15. Failed batch then one-by-one retry (`testAFailedBatchLeavesTheHistoryReadyForTheOneByOneRetry`)

- **Purpose**: `EventBatchHandler` retries a failed batch one message at a time through `handleOne()`; the history counters reset by the failure must not break that path
- **What it tests**:
    - The failed batch writes no `parser_history` row
    - `handleOne()` right after the failure imports the event (a regression: `reset()` used to `unset()` a typed property, and the next `isStarted()` threw)
    - Uses `FailOnceEventEntityFactory`, like test 10

## Running the Tests

**Important**: These tests use **DAMA Doctrine Test Bundle** for automatic transaction rollback, providing test isolation without manual cleanup.

### Prerequisites

1. **Database Setup**: These are integration tests that require a working database connection.

    Configure your test database in `.env.test`:

    ```bash
    DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_test.db"
    ```

    Or use MySQL/PostgreSQL:

    ```bash
    DATABASE_URL="mysql://user:password@127.0.0.1:3306/test_db?serverVersion=8.0"
    ```

2. **PHP Extensions Required**:
    - For SQLite: `php-sqlite3` or `php-pdo-sqlite`
    - For MySQL: `php-mysql` or `php-pdo-mysql`
    - For PostgreSQL: `php-pgsql` or `php-pdo-pgsql`

3. **Database Schema**: Create the test database schema:

    ```bash
    # For SQLite (default in .env.test), database file is created automatically
    php bin/console doctrine:schema:create --env=test

    # For MySQL/PostgreSQL, you may need to create the database first:
    # php bin/console doctrine:database:create --env=test
    # php bin/console doctrine:schema:create --env=test
    ```

### Running the Tests

Run all tests (integration tests will run automatically):

```bash
vendor/bin/phpunit
```

Run only the DoctrineEventHandlerTest:

```bash
vendor/bin/phpunit tests/Handler/DoctrineEventHandlerTest.php
```

Run a specific test method:

```bash
vendor/bin/phpunit --filter testInsertNewEvent tests/Handler/DoctrineEventHandlerTest.php
```

### Test Isolation with DAMA Bundle

These tests use **DAMA Doctrine Test Bundle** which:

- Automatically wraps each test in a database transaction
- Rolls back the transaction after each test completes
- Ensures tests don't interfere with each other
- No manual database cleanup required

This is configured in `phpunit.xml.dist` and works automatically.

### CI/CD Integration

CI is configured via Laminas CI:

1. **`.laminas-ci.json`**: Declares `pdo_sqlite` extension requirement
2. **`.laminas-ci/pre-run.sh`**: Creates database schema before tests (SQLite database file is created automatically)
3. **`.env.test`**: Configures SQLite database for fast, reliable testing
4. **DAMA Bundle**: Provides automatic transaction rollback for test isolation
5. **Messenger**: Uses in-memory transport (no RabbitMQ/AMQP needed in tests)

All tests run automatically in CI with proper database support.

## Test Implementation Details

### Factories Used

The tests leverage **Zenstruck Foundry** factories for test data generation:

- `EventFactory`: Creates test Event entities
- `PlaceFactory`: Creates test Place entities
- `CityFactory`: Creates test City entities
- `CountryFactory`: Creates test Country entities
- `UserFactory`: Creates test User entities

### Key Classes Tested

1. **DoctrineEventHandler** (`src/Handler/DoctrineEventHandler.php`)
    - Main orchestrator for event insertion and merging
    - Handles validation through Firewall
    - Manages batch processing and chunking

2. **EventRepository** (`src/Repository/EventRepository.php`)
    - Provides `findAllByDtos()` for existing event lookup
    - Enables merge detection by external ID

3. **EventEntityFactory** (`src/EntityFactory/EventEntityFactory.php`)
    - Creates new Event entities from DTOs
    - Updates existing entities with new data

4. **Firewall** (`src/Utils/Firewall.php`)
    - Validates event data before insertion
    - Filters invalid events

## Troubleshooting

### Database Connection Errors

If you see "could not find driver" errors:

```
Doctrine\DBAL\Exception\DriverException: An exception occurred in the driver: could not find driver
```

**Solution**: Install the required PHP database extension:

```bash
# For SQLite
sudo apt-get install php-sqlite3

# For MySQL
sudo apt-get install php-mysql

# For PostgreSQL
sudo apt-get install php-pgsql
```

### Read-Only Database Errors

If you see "attempt to write a readonly database" errors:

```
Doctrine\DBAL\Exception\ReadOnlyException: attempt to write a readonly database
```

**Solution**: Ensure the var directory has write permissions:

```bash
mkdir -p var
chmod -R 777 var
```

This is automatically handled by `.laminas-ci/pre-run.sh` in CI.

### DAMA Bundle with Read-Only Entities

The project has several entities marked as `readOnly: true`:

- `City`, `ZipCity`, `ParserHistory`, `AdminZone*`

To prevent conflicts with DAMA bundle's transaction handling:

- `enable_static_connection: false` is set in `dama_doctrine_test_bundle.yaml`
- This allows read-only entities to work correctly with transaction rollback
- Test isolation still works properly through DAMA's transaction wrapping

### Schema Not Found Errors

If you see table not found errors:

```
Doctrine\DBAL\Exception\TableNotFoundException
```

**Solution**: Create the database schema:

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test
```

### Slow Test Execution

If tests are running slowly:

- Consider using SQLite in-memory database for faster tests: `sqlite:///:memory:`
- Check the `testBatchInsertionPerformance` test for performance benchmarks
- Ensure database indexes are properly created

## Future Enhancements

Potential areas for additional test coverage:

- Image download and processing during event insertion
- User association and authentication flow
- Parser version tracking
- External update timestamps
- Event status transitions
- Error handling and rollback scenarios
- Concurrent insertion race conditions
