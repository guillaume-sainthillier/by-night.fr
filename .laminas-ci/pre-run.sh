#!/bin/bash

set -ex

# This script runs before PHPUnit tests
# It sets up the test database schema

# Ensure var directory exists and is writable (required for SQLite)
mkdir -p var
chmod 777 var

# Remove any existing test database to ensure clean state with proper permissions
rm -f var/data_test.db

# Create database schema
bin/console doctrine:schema:create --env=test --no-interaction

# Ensure the database file is writable
chmod 666 var/data_test.db 2>/dev/null || true
