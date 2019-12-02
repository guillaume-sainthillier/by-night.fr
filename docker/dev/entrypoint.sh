#!/bin/bash
set -e

if [ $# -eq 0 ]; then
    supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    if [ "$1" = "worker" ]; then
        supervisord -c /etc/supervisor/conf.d/supervisord-worker.conf
    else
        exec "$@"
    fi
fi

