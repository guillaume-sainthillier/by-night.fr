#!/bin/bash
set -e

uid=$(stat -c %u /app)
gid=$(stat -c %g /app)

if [ $# -eq 0 ]; then
    supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    exec "$@"
fi

sed -i "s/user = www-data/user = bynight/g" /usr/local/etc/php-fpm.d/www.conf
sed -i "s/group = www-data/group = bynight/g" /usr/local/etc/php-fpm.d/www.conf
sed -i -r "s/bynight:x:[0-9]+:[0-9]+:/bynight:x:$uid:$gid:/g" /etc/passwd
sed -i -r "s/bynight:x:[0-9]+:/bynight:x:$gid:/g" /etc/group

chown $uid:$gid /home/bynight

if [ $# -eq 0 ]; then
    supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    exec gosu bynight "$@"
fi