# Local OpenCart 4 stand for developing the Parcora extension.
FROM php:8.3-apache

ARG OPENCART_VERSION=4.1.0.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev unzip default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd zip mysqli \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL "https://github.com/opencart/opencart/releases/download/${OPENCART_VERSION}/opencart-${OPENCART_VERSION}.zip" -o /tmp/opencart.zip \
    && unzip -q /tmp/opencart.zip -d /tmp/opencart \
    && cp -a /tmp/opencart/upload/. /var/www/html/ \
    && mv /var/www/html/config-dist.php /var/www/html/config.php \
    && mv /var/www/html/admin/config-dist.php /var/www/html/admin/config.php \
    && rm -rf /tmp/opencart /tmp/opencart.zip \
    && chown -R www-data:www-data /var/www/html

COPY entrypoint.sh /usr/local/bin/opencart-entrypoint
RUN chmod +x /usr/local/bin/opencart-entrypoint

ENTRYPOINT ["opencart-entrypoint"]
CMD ["apache2-foreground"]
