FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libavif-dev \
        libzip-dev \
        libonig-dev \
        gettext-base \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql zip exif mbstring \
    && (a2dismod -f mpm_event mpm_worker || true) \
    && a2enmod mpm_prefork \
    && a2enmod rewrite headers expires deflate \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
COPY docker/apache-ports.conf /etc/apache2/ports.conf
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/railway-entrypoint

RUN chmod +x /usr/local/bin/railway-entrypoint \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

ENTRYPOINT ["railway-entrypoint"]
CMD ["apache2-foreground"]
