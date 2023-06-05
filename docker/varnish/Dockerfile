FROM varnish:7.3-alpine

USER root

COPY --chown=varnish  . /etc/varnish

RUN chown -R varnish:varnish /etc/varnish

USER varnish
