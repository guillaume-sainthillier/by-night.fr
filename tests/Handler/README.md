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
   php bin/console doctrine:database:create --env=test
   php bin/console doctrine:schema:create --env=test
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

CI is configured to:
1. Install PHP SQLite extension via `.laminas-ci/pre-install.sh`
2. Create test database and schema via `.laminas-ci.json` pre_test hook
3. Run all tests including integration tests
4. Use SQLite from `.env.test` for fast, reliable testing

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
