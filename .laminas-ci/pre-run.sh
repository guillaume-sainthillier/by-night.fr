#!/bin/bash

set -ex

# This script runs before PHPUnit tests
# It sets up the test database schema
# Note: For SQLite, the database file is created automatically, no need for doctrine:database:create

# Create database schema (database file is created automatically on first access for SQLite)
bin/console doctrine:schema:create --env=test --no-interaction || bin/console doctrine:schema:update --env=test --force --no-interaction
