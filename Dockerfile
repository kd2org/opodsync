FROM php:8.2-apache
COPY ./server/ /var/www/html/

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN sed -ri -e 's/80/8100/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 8100
