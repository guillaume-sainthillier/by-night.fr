#!/bin/bash

set -ex

JOB=$3

# This script runs before CI jobs
# Database setup only runs for PHPUnit jobs

# Create var/tools directory for tools like Twig-CS-Fixer, PHPStan, etc.
mkdir -p var/tools

# Only create database for PHPUnit jobs
if [[ "${JOB}" =~ "phpunit" ]] || [[ "${JOB}" =~ "PHPUnit" ]]; then
    # Create database schema
    bin/console doctrine:schema:create --env=test --no-interaction
fi

# Simplify permissions - set entire var directory accessible
chmod -R 777 var
