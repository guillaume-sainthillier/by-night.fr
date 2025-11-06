#!/bin/bash

set -ex

# Install SQLite PDO extension if not already available
# Check which PHP version is being used and install the corresponding package
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
if ! php -m | grep -q pdo_sqlite; then
    echo "Installing php${PHP_VERSION}-sqlite3..."
    sudo apt-get update -qq
    sudo apt-get install -y -qq php${PHP_VERSION}-sqlite3 || sudo apt-get install -y -qq php-sqlite3
fi

# Replace Redis cache with filesystem for CI
sed -i 's/app: cache.adapter.redis/app: cache.adapter.filesystem/g' config/packages/cache.yaml
