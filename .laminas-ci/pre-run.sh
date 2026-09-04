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

    # CI never runs "yarn build": stub the Encore manifests so tests that render a full page
    # (asset() / encore_entry_*_tags()) do not fail on a missing file. The manifest can be an
    # empty array, but EntrypointLookup refuses a file without an "entrypoints" key.
    mkdir -p public/build
    [ -f public/build/manifest.json ] || echo '[]' > public/build/manifest.json
    [ -f public/build/entrypoints.json ] || echo '{"entrypoints":{}}' > public/build/entrypoints.json
fi

# Simplify permissions - set entire var directory accessible
chmod -R 777 var
