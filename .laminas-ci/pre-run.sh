#!/bin/bash

set -ex

# This script runs before CI jobs
# Database setup only runs for PHPUnit jobs

# Only create database for PHPUnit jobs
if [[ "${JOB}" =~ "phpunit" ]] || [[ "${JOB}" =~ "PHPUnit" ]]; then
    # Ensure var directory exists and is writable, remove old database
    mkdir -p var && chmod 777 var && rm -f var/data_test.db

    # Create database schema and set permissions
    bin/console doctrine:schema:create --env=test --no-interaction && chmod 666 var/data_test.db
fi
