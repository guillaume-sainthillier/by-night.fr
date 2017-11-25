FROM debian:jessie

ENV FILEBEAT_VERSION 6.0.0

RUN apt-get update && apt-get install -y \
    curl \
    nginx

ADD nginx.conf /etc/nginx/
ADD symfony.conf /etc/nginx/sites-available/

RUN ln -s /etc/nginx/sites-available/symfony.conf /etc/nginx/sites-enabled/symfony
RUN ln -s /etc/nginx/sites-available/assets.conf /etc/nginx/sites-enabled/assets
RUN rm /etc/nginx/sites-enabled/default

RUN echo "upstream php-upstream { server php:9000; }" > /etc/nginx/conf.d/upstream.conf
RUN usermod -u 1000 www-data

RUN curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-${FILEBEAT_VERSION}-amd64.deb \
 && dpkg -i filebeat-${FILEBEAT_VERSION}-amd64.deb \
 && rm filebeat-${FILEBEAT_VERSION}-amd64.deb

# remove log symlinks
RUN rm /var/log/nginx/access.log /var/log/nginx/error.log

### configure Filebeat

# config file
ADD filebeat.yml /etc/filebeat/filebeat.yml
RUN chmod 644 /etc/filebeat/filebeat.yml

# CA cert
RUN mkdir -p /etc/pki/tls/certs
ADD logstash-beats.crt /etc/pki/tls/certs/logstash-beats.crt

ADD start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD [ "/usr/local/bin/start.sh" ]

EXPOSE 80
EXPOSE 443
