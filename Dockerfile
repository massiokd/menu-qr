FROM php:8.2-apache

# 1. Installer les extensions PostgreSQL ET cURL
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_pgsql curl \
    && rm -rf /var/lib/apt/lists/*

# 2. Script de démarrage pour adapter le port
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 3. Activer mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]