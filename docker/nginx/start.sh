#!/bin/bash

curl -XDELETE http://elk:9200/_template/*
filebeat export template > /etc/filebeat/filebeat.template.json
curl -XPUT -H 'Content-Type: application/json' 'http://elk:9200/_template/filebeat?pretty' -d@/etc/filebeat/filebeat.template.json
/etc/init.d/filebeat start
nginx
tail -f /var/log/nginx/symfony_access.log -f /var/log/nginx/error.log
