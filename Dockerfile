FROM php:8.2-apache

# 1. Installer les extensions PostgreSQL ET cURL
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_pgsql curl \
    && rm -rf /var/lib/apt/lists/*

# 2. Augmenter les limites d'upload PHP (20 Mo)
RUN echo "upload_max_filesize = 20M\npost_max_size = 20M\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

# 3. Script de démarrage pour adapter le port
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 4. Activer mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

# 5. Copier TOUS les dossiers (admin, actions, assets, config...) dans le serveur
COPY . /var/www/html/

EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]