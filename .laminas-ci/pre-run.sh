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

    # CI never runs "yarn build": stub the Reprise manifests so tests that render a full page
    # (asset() / reprise_entry_*_tags()) do not fail on a missing file. The manifest can be an
    # empty array, but Reprise validates entrypoints.json and throws unless "isProd",
    # "publicPath" and "entryPoints" are present with the right types.
    mkdir -p public/build
    [ -f public/build/manifest.json ] || echo '[]' > public/build/manifest.json
    [ -f public/build/entrypoints.json ] || echo '{"isProd":true,"devServer":null,"publicPath":"/build/","entryPoints":{}}' > public/build/entrypoints.json
fi

# Simplify permissions - set entire var directory accessible
chmod -R 777 var
