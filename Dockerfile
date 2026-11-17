# Railway / any Docker host. On cPanel shared hosting, just upload the folder instead.
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install pdo_mysql zip gd \
 && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
RUN { echo 'upload_max_filesize=32M'; echo 'post_max_size=40M'; echo 'memory_limit=256M'; echo 'max_execution_time=120'; echo 'date.timezone=Asia/Kolkata'; } > /usr/local/etc/php/conf.d/qtrack.ini \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
COPY . /var/www/html/
RUN rm -f /var/www/html/router.php && mkdir -p /var/www/html/uploads /var/www/html/storage && chown -R www-data:www-data /var/www/html/uploads /var/www/html/storage
COPY docker-entrypoint.sh /usr/local/bin/qtrack-entry
RUN chmod +x /usr/local/bin/qtrack-entry
CMD ["qtrack-entry"]
