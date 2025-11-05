#!/bin/bash

set -ex

# This script runs before PHPUnit tests
# It sets up the test database schema
# Note: For SQLite, the database file is created automatically, no need for doctrine:database:create

# Ensure var directory exists and is writable
mkdir -p var
chmod -R 777 var

# Remove any existing test database to ensure clean state
rm -f var/data_test.db

# Create database schema (database file is created automatically on first access for SQLite)
bin/console doctrine:schema:create --env=test --no-interaction
