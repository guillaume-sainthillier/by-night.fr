#!/bin/sh
set -e

if [ "$1" = "worker" ]; then
    # exec, so supervisord is PID 1 and gets the SIGTERM of `docker stop`: a shell PID 1
    # ignores it, Docker then SIGKILLs every worker and the parser's unacked batch is
    # redelivered, rejected and sent to the failure transport.
    exec supervisord -c /etc/supervisor/conf.d/supervisord-worker.conf
else
    exec docker-php-entrypoint "$@"
fi
