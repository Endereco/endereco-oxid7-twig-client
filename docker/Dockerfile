ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

ARG OXID_COMPOSER_VERSION=dev-b-7.3-ce

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libzip-dev \
    libxml2-dev libicu-dev libonig-dev libcurl4-openssl-dev \
    zip unzip curl git default-mysql-client \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) \
    pdo_mysql mysqli gd intl zip bcmath soap mbstring xml opcache \
    curl exif gettext

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Install OXID 7 shop.
RUN composer create-project --no-interaction --ignore-platform-reqs \
    oxid-esales/oxideshop-project /var/www/html "$OXID_COMPOSER_VERSION"

# Register the module's PSR-4 namespace and merge its runtime dependencies
# into the shop's composer.json, then resolve only the new packages so the
# existing lock file stays intact for everything else.
# The module source is not yet mounted here, so we copy only what the script
# needs (the module's composer.json) from the build context.
COPY docker/patch-composer.php /tmp/patch-composer.php
COPY composer.json /var/www/html/source/modules/endereco/endereco-oxid7-twig-client/composer.json
RUN cd /var/www/html \
 && php /tmp/patch-composer.php \
 && rm /tmp/patch-composer.php \
 && PKGS=$(cat /tmp/module-packages.txt) && rm /tmp/module-packages.txt \
 && composer update --no-interaction --no-dev --no-scripts $PKGS \
 && composer dump-autoload --no-interaction

# AdminNeo — single-file database manager
RUN mkdir -p /var/www/adminneo \
 && curl -fsSL -o /var/www/adminneo/index.php \
    "https://www.adminneo.org/files/latest/mysql_en_default/adminneo-latest.php"

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers

COPY docker/php.ini /usr/local/etc/php/conf.d/99-oxid.ini

# oe:setup:shop needs config.inc.php with <placeholder> strings to fill in.
RUN cp /var/www/html/source/config.inc.php.dist /var/www/html/source/config.inc.php

RUN mkdir -p /var/www/html/source/tmp \
             /var/www/html/var/log \
             /var/www/html/var/cache \
             /var/www/html/var/configuration/shops \
 && chown -R www-data:www-data /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
