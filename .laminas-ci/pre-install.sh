#!/bin/bash

sed -i 's/app: cache.adapter.redis/app: cache.adapter.filesystem/g' config/packages/cache.yaml
