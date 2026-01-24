FROM varnish:8.0-alpine

USER root

COPY --chown=varnish  . /etc/varnish

RUN chown -R varnish:varnish /etc/varnish && \
  mkdir -p -m 1777 /var/lib/varnish/varnishd

USER varnish
