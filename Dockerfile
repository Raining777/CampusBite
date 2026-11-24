FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . .
