FROM php:8.2-apache

# 1. Installer les extensions PostgreSQL, cURL ET GD (pour les images)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql curl gd \
    && rm -rf /var/lib/apt/lists/*

# 2. Augmenter les limites d'upload PHP (20 Mo)
RUN echo "upload_max_filesize = 20M\npost_max_size = 20M\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

# 3. Script de démarrage pour adapter le port
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 4. Activer mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]