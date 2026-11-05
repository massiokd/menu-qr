FROM php:8.2-apache

# Installer l'extension PostgreSQL
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Copier le script de démarrage
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Activer mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]