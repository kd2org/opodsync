FROM alpine:edge
LABEL Maintainer="BohwaZ <https://bohwaz.net/>" \
      Description="oPodSync"

RUN apk --no-cache add php83 php83-ctype php83-opcache php83-session php83-sqlite3

# Setup document root
RUN mkdir -p /var/www
RUN mkdir -p /var/www/server
RUN mkdir -p /var/www/server/data

# Add application
WORKDIR /var/www/
COPY server /var/www/server/

EXPOSE 8080

VOLUME ["/var/www/server/data"]

ENV PHP_CLI_SERVER_WORKERS=2
CMD ["php", "-S", "0.0.0.0:8080", "-t", "server", "server/index.php"]
