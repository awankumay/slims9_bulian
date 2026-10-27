# Base image is pinned to a patched PHP release (PHP2Shell: CVE-2026-109618/109619,
# fixed in 8.3.36 / 8.4.27). Use the -bookworm variant: the plain tag is Debian trixie.
# To test PHP 8.4: --build-arg PHP_IMAGE=php:8.4.27-apache-bookworm@sha256:e653ff90d7f6e2e6f81eddb07c864f1eae224ccbf5143f82a44f25e2f110e320
ARG PHP_IMAGE=php:8.3.36-apache-bookworm@sha256:cbd3b3d9011668443d54ab40ec31db56c73d91b4c8198935d639aa80d2d815c1
ARG COMPOSER_IMAGE=composer:2

FROM ${COMPOSER_IMAGE} AS composer

FROM ${PHP_IMAGE}

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libxml2-dev libzip-dev libpng-dev libonig-dev libjpeg62-turbo libjpeg62-turbo-dev libfreetype6-dev unzip \
    && docker-php-ext-install intl xml xmlwriter gettext mbstring zip mysqli pdo_mysql \
    && docker-php-ext-enable intl xml xmlwriter gettext mbstring zip mysqli pdo_mysql \
    && docker-php-ext-install -j$(nproc) iconv \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-enable gd
RUN rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

COPY docker/php/zz-slims.ini /usr/local/etc/php/conf.d/zz-slims.ini
COPY docker/apache/slims-security.conf /etc/apache2/conf-available/slims-security.conf
RUN a2enconf slims-security

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN chown -R www-data:www-data /var/www/html/files
RUN chown -R www-data:www-data /var/www/html/images
RUN chown -R www-data:www-data /var/www/html/repository
RUN rm -rf /var/www/html/install /var/www/html/docker /var/www/html/entrypoint.sh

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/index.php || exit 1

ENTRYPOINT ["/entrypoint.sh"]

CMD ["apache2-foreground"]
