#!/bin/bash

set -ex

# Install SQLite PDO extension for database tests
sudo apt-get update && sudo apt-get install -y php${PHP_VERSION}-sqlite3 || echo "SQLite already installed"

# Replace Redis cache with filesystem for CI
sed -i 's/app: cache.adapter.redis/app: cache.adapter.filesystem/g' config/packages/cache.yaml
