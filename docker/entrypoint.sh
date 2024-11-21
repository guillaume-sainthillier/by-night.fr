#!/bin/sh
set -e

if [ "$1" = "worker" ]; then
    supervisord -c /etc/supervisor/conf.d/supervisord-worker.conf
else
    exec docker-php-entrypoint "$@"
fi
