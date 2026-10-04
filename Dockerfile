FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .

RUN mkdir -p /var/www/html/runtime/cache \
    && chown -R www-data:www-data /var/www/html/runtime \
    && chmod -R 775 /var/www/html/runtime

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

RUN sed -ri 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' \
    /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["sh", "-c", "\
echo 'Checking Render Aiven CA secret...' && \
if [ ! -f /etc/secrets/ca.pem ]; then \
    echo 'ERROR: /etc/secrets/ca.pem does not exist'; \
    echo 'Files inside /etc/secrets:'; \
    ls -la /etc/secrets || true; \
    exit 1; \
fi && \
echo 'Aiven CA secret found.' && \
cp /etc/secrets/ca.pem /var/www/html/runtime/aiven-ca.pem && \
chown www-data:www-data /var/www/html/runtime/aiven-ca.pem && \
chmod 600 /var/www/html/runtime/aiven-ca.pem && \
export DB_SSL_CA=/var/www/html/runtime/aiven-ca.pem && \
echo 'Starting Apache...' && \
exec apache2-foreground"]