# Testing Setup

This document describes the testing setup for the By Night application.

## Requirements

### PHP Extensions

The test suite is configured to use SQLite database. You need the following PHP extensions:

- `pdo_sqlite` - PDO driver for SQLite databases

### Installing PHP SQLite Extension

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

## Test Configuration

The test environment is configured to use SQLite in-memory database:

- Database URL: `sqlite:///%kernel.project_dir%/var/data_test.db`
- Configuration file: `.env.test`

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
- `tests/Utils/` - Utility function tests (Comparator, Cleaner, StringManipulator, Util, Firewall)
- `tests/Repository/` - Repository integration tests (require database)
- `tests/Entity/` - Entity behavior and validation tests (require database)
- `src/Factory/` - Foundry factories for entities
- `src/DataFixtures/` - Doctrine fixtures using Foundry

## Test Coverage

The test suite includes:

- **123 passing tests** for utility classes (no database required)
- Unit tests for string manipulation, cleaning, comparison, and validation
- Repository tests (require SQLite extension)
- Entity tests (require SQLite extension)

## Running Tests Without Database

Many tests don't require a database connection and can run without SQLite installed:

```bash
# Run only utility tests (no database required)
vendor/bin/phpunit tests/Utils/
```

Repository and entity tests require the `pdo_sqlite` PHP extension and will be skipped if not available.

## Notes

- The test suite uses Foundry's `Factories` trait for factory support
- For unit tests that don't need database persistence, use `withoutPersisting()` on factories
- Repository and entity tests use the `ResetDatabase` trait for database isolation
- Most utility tests don't require a database and will run in any environment
