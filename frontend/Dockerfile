FROM php:8.2-apache-bookworm

COPY ./index.php /var/www/html/
COPY ./nyckml.css /var/www/html/
COPY ./nyckml.js /var/www/html/

# Use production config file as recommended
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
