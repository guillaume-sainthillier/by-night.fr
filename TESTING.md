# Testing Setup

This document describes the testing setup for the By Night application.

## Requirements

No special requirements needed for the current test suite. All tests run without database dependencies.

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
- `tests/Utils/` - Utility function tests:
  - `ComparatorTest` - Place comparison and matching (47 tests)
  - `CleanerTest` - DTO cleaning for events, places, cities (11 tests)
  - `StringManipulatorTest` - String manipulation utilities (32 tests)
  - `UtilTest` - General utility functions (32 tests)
  - `FirewallTest` - Firewall utilities (1 test)
- `src/Factory/` - Foundry factories for entities
- `src/DataFixtures/` - Doctrine fixtures using Foundry

## Test Coverage

The test suite currently includes:

- **123 passing unit tests** for utility classes
- Tests for string manipulation, cleaning, comparison, and validation
- All tests run without database dependencies
- Factories are ready for future integration tests

## Notes

- The test suite uses Foundry's `Factories` trait for factory support
- For unit tests that don't need database persistence, use `withoutPersisting()` on factories
- All current tests run without database dependencies
- Factories are available for creating test data in future integration tests
