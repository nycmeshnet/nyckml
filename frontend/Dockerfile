FROM php:8.5-apache-trixie@sha256:08af36df144462d84c7f16190557eec06cb9f656882685610b712a15eec54d46

# src/ holds the PHP that pages include; it sits outside the web root so it can't be requested directly.
COPY ./src/ /var/www/src/
COPY ./public/ /var/www/html/

# Use production config file as recommended
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
