#!/bin/bash

set -ex

# Replace Redis cache with filesystem for CI
sed -i 's/app: cache.adapter.redis/app: cache.adapter.filesystem/g' config/packages/cache.yaml
