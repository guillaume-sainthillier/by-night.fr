#!/bin/bash
set -e

rm -rf var/cache/*
bin/console cache:clear --env=prod --no-warmup
bin/console cache:warmup --env=prod
chown -R www-data:www-data var

if [ $# -eq 0 ]; then
    supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    exec "$@"
fi