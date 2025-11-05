#!/bin/bash

set -ex

# This script runs before PHPUnit tests
# It sets up the test database schema
# Note: For SQLite, the database file is created automatically

# Create database schema
bin/console doctrine:schema:create --env=test --no-interaction
