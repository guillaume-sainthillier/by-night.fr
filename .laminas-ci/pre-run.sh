#!/bin/bash

set -ex

# This script runs before PHPUnit tests
# It sets up the test database and schema

# Create test database (if not exists)
bin/console doctrine:database:create --env=test --if-not-exists

# Create/update database schema
bin/console doctrine:schema:create --env=test || bin/console doctrine:schema:update --env=test --force
