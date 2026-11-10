FROM php:8.2-apache

# 1. Installer les librairies système (AJOUT DE libwebp-dev)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Configurer GD avec le support JPEG, FreeType ET WEBP
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp

# 3. Installer les extensions PHP
RUN docker-php-ext-install pdo_pgsql curl gd

# 4. Limites d'upload
RUN echo "upload_max_filesize = 20M\npost_max_size = 20M\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

# 5. Script de démarrage
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 6. Activer mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]