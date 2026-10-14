# syntax=docker/dockerfile:1
# API Laravel de SIGA (PHP-FPM). Caddy (imagen del front) le pasa /api por FastCGI.
FROM php:8.4-fpm-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN { \
      echo 'expose_php=Off'; \
      echo 'memory_limit=256M'; \
      echo 'upload_max_filesize=20M'; \
      echo 'post_max_size=25M'; \
      echo 'date.timezone=America/Mexico_City'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/zz-siga.ini

WORKDIR /var/www/html

# 1 = instala también require-dev (Faker), necesario solo para sembrar el DemoSeeder
ARG COMPOSER_DEV=0

# Dependencias primero: si no cambia composer.lock, Docker reusa esta capa
COPY composer.json composer.lock ./
RUN composer install $( [ "$COMPOSER_DEV" = "1" ] || echo --no-dev ) \
      --no-scripts --no-autoloader --no-interaction --prefer-dist

# --chown: el código llega de Windows con permisos 700 y php-fpm corre como www-data
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize $( [ "$COMPOSER_DEV" = "1" ] || echo --no-dev ) \
 && php artisan package:discover --ansi \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
