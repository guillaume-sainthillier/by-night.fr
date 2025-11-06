#!/bin/bash

set -ex

# This script runs before PHPUnit tests
# It sets up the test database schema

# Ensure var directory exists and is writable, remove old database
mkdir -p var && chmod 777 var && rm -f var/data_test.db

# Create database schema and set permissions
bin/console doctrine:schema:create --env=test --no-interaction && chmod 666 var/data_test.db
