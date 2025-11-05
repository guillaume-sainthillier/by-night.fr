#!/bin/bash

set -ex

# This script runs before PHPUnit tests
# It sets up the test database schema

# Ensure var directory exists and is writable (required for SQLite)
mkdir -p var
chmod 777 var

# Create database schema
bin/console doctrine:schema:create --env=test --no-interaction
