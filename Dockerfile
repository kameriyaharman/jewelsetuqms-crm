# Railway / any Docker host. On Linux shared hosting just upload the contents of www/ to public_html.
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install pdo_mysql zip gd \
 && (a2dismod -f mpm_event mpm_worker || true) && a2enmod mpm_prefork rewrite headers && rm -rf /var/lib/apt/lists/*
RUN { echo 'upload_max_filesize=32M'; echo 'post_max_size=40M'; echo 'memory_limit=256M'; echo 'max_execution_time=120'; echo 'date.timezone=Asia/Kolkata'; } > /usr/local/etc/php/conf.d/qtrack.ini \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf
# www/ already contains the built React app (frontend/ → npm run build) + api/ + server/
COPY www/ /var/www/html/
RUN mkdir -p /var/www/html/server/uploads /var/www/html/server/storage && chown -R www-data:www-data /var/www/html/server/uploads /var/www/html/server/storage
COPY docker-entrypoint.sh /usr/local/bin/qtrack-entry
RUN chmod +x /usr/local/bin/qtrack-entry
CMD ["qtrack-entry"]
