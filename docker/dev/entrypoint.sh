#!/bin/bash
set -e

chown -R www-data var

if [ $# -eq 0 ]; then
    supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    exec "$@"
fi