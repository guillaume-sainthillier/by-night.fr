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

- `CountryFactory` - Creates Country entities
- `CityFactory` - Creates City entities  
- `ZipCityFactory` - Creates ZipCity entities
- `PlaceFactory` - Creates Place entities

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
- `tests/Utils/` - Utility function tests
- `src/Factory/` - Foundry factories for entities
- `src/DataFixtures/` - Doctrine fixtures using Foundry

## Notes

- The test suite uses Foundry's `Factories` trait for factory support
- For unit tests that don't need database persistence, use `withoutPersisting()` on factories
- The `ResetDatabase` trait is not used to allow tests to run without requiring a database connection for simple unit tests
