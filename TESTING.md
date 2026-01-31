# Testing Setup

This document describes the testing setup for the By Night application.

## Requirements

### For Utility Tests (No Database Required)
- No special requirements needed for utility tests
- All utility tests run without database dependencies

### For Integration Tests (Database Required)
- Database connection (SQLite, MySQL, or PostgreSQL)
- Appropriate PHP PDO extension installed
- Database schema created

## Test Configuration

The test environment is configured to use SQLite database (for future integration tests):

- Database URL: `sqlite:///%kernel.project_dir%/var/data_test.db`
- Configuration file: `.env.test`

### Installing PHP SQLite Extension (Optional)

If you want to add integration tests in the future:

#### Ubuntu/Debian
```bash
sudo apt-get install php-sqlite3
```

#### macOS (Homebrew)
```bash
brew install php
# SQLite extension is usually included
```

#### Windows
Uncomment the following line in your `php.ini`:
```ini
extension=pdo_sqlite
```

## Foundry Bundle & Fixtures

The project uses [Zenstruck Foundry](https://github.com/zenstruck/foundry) for creating test data:

### Available Factories

- `CountryFactory` - Creates Country entities (with `france()` preset)
- `CityFactory` - Creates City entities (with `toulouse()` preset)
- `ZipCityFactory` - Creates ZipCity entities (with `toulouse31000()` and `toulouse31500()` presets)
- `PlaceFactory` - Creates Place entities
- `UserFactory` - Creates User entities (with `admin()` and `enabled()` methods)
- `EventFactory` - Creates Event entities (with `upcoming()`, `past()`, and `withDates()` methods)

### Using Factories in Tests

```php
use App\Factory\CountryFactory;
use App\Factory\CityFactory;

// Create and persist entities
$france = CountryFactory::france()->create();
$toulouse = CityFactory::toulouse()->create();

// Create without persisting (for unit tests)
$country = CountryFactory::new()->withoutPersisting()->create();

// Create many entities
$cities = CityFactory::createMany(10);
```

### Doctrine Fixtures

Run fixtures to populate the database:

```bash
php bin/console doctrine:fixtures:load --env=test
```

## Running Tests

Run all tests:
```bash
vendor/bin/phpunit
```

Run specific test file:
```bash
vendor/bin/phpunit tests/Utils/ComparatorTest.php
```

Run with test documentation output:
```bash
vendor/bin/phpunit --testdox
```

## Test Structure

- `tests/AppKernelTestCase.php` - Base test case with Foundry support
- `tests/Utils/` - Utility function tests (no database required):
  - `ChunkUtilsTest` - Object chunking and grouping by class (10 tests)
  - `CityManipulatorTest` - City name alternatives and sanitization (17 tests)
  - `CleanerTest` - DTO cleaning for events, places, cities (11 tests)
  - `ComparatorTest` - Place comparison and matching (47 tests)
  - `FirewallTest` - Firewall utilities (1 test)
  - `MemoryUtilsTest` - Memory formatting and usage tracking (13 tests)
  - `SluggerUtilsTest` - URL-safe slug generation (23 tests)
  - `StringManipulatorTest` - String manipulation utilities (32 tests)
  - `UtilTest` - General utility functions (32 tests)
- `tests/Handler/` - Integration tests (database required):
  - `DoctrineEventHandlerTest` - Event insertion and merging (9 tests)
    - Tests event insertion into database
    - Tests merging with existing events by external ID
    - Tests duplicate prevention
    - Tests validation/filtering
    - Tests place association
    - Tests batch insertion
    - Tests contact information persistence
    - Tests timestamp management
  - See `tests/Handler/README.md` for detailed documentation
- `src/Factory/` - Foundry factories for entities
- `src/DataFixtures/` - Doctrine fixtures using Foundry

## Test Coverage

The test suite currently includes:

### Phase 1: Utility Tests (No Database Required)
- **186 passing unit tests** for utility classes
- Tests for string manipulation, cleaning, comparison, validation, chunking, memory, and slugs
- Comprehensive edge case coverage (empty strings, Unicode, special characters)
- Fast execution (<1 second)

### Phase 2: Integration Tests (Database Required)
- **9 comprehensive integration tests** for event handling
- Tests for event insertion into database
- Tests for event merging and duplicate detection
- Tests for validation and filtering
- Tests for place association and dependency resolution
- Tests for batch processing and performance
- See `tests/Handler/README.md` for detailed documentation

## Running Integration Tests

Integration tests require a database connection and are **excluded by default** to ensure CI passes without database setup.

### Running Database Tests Locally

Before running database tests:

1. **Set up test database schema**:
   ```bash
   # For SQLite (default in .env.test), database file is created automatically
   php bin/console doctrine:schema:create --env=test

   # For MySQL/PostgreSQL, you may need to create the database first:
   # php bin/console doctrine:database:create --env=test
   # php bin/console doctrine:schema:create --env=test
   ```

2. **Run all tests** (integration tests included):
   ```bash
   vendor/bin/phpunit
   ```

   Or run specific test file:
   ```bash
   vendor/bin/phpunit tests/Handler/DoctrineEventHandlerTest.php
   ```

### How It Works in CI

CI automatically:
- Installs the `pdo_sqlite` extension (via `.laminas-ci.json`)
- Creates the database schema (via `.laminas-ci/pre-run.sh`)
- Runs all tests including integration tests with full database isolation (via DAMA bundle)

## Notes

- The test suite uses Foundry's `Factories` trait for factory support
- For unit tests that don't need database persistence, use `withoutPersisting()` on factories
- Utility tests run without database dependencies for fast feedback
- Integration tests require a working database connection
- See `tests/Handler/README.md` for detailed integration test documentation
