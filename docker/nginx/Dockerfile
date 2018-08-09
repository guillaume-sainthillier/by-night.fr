FROM debian:stretch

ENV FILEBEAT_VERSION 6.1.1

RUN apt-get update && apt-get install -y \
    curl \
    nginx

# Install Filebeat
RUN curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-${FILEBEAT_VERSION}-amd64.deb \
 && dpkg -i filebeat-${FILEBEAT_VERSION}-amd64.deb \
 && rm filebeat-${FILEBEAT_VERSION}-amd64.deb

# Config Filebeat
ADD filebeat.yml /etc/filebeat/filebeat.yml
RUN chmod 644 /etc/filebeat/filebeat.yml

# CA cert
RUN mkdir -p /etc/pki/tls/certs
ADD logstash-beats.crt /etc/pki/tls/certs/logstash-beats.crt

# Config nginx
ADD nginx.conf /etc/nginx/f
ADD symfony.conf /etc/nginx/sites-available/

RUN ln -s /etc/nginx/sites-available/symfony.conf /etc/nginx/sites-enabled/symfony
RUN rm /etc/nginx/sites-enabled/default

RUN echo "upstream php-upstream { server php:9000; }" > /etc/nginx/conf.d/upstream.conf
RUN usermod -u 1000 www-data

# remove log symlinks
RUN rm /var/log/nginx/access.log /var/log/nginx/error.log

ADD start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD [ "/usr/local/bin/start.sh" ]

EXPOSE 80
EXPOSE 443
