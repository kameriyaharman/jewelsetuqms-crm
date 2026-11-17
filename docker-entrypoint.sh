#!/bin/sh
set -e
PORT="${PORT:-80}"
# Railway sometimes ends up with two MPMs enabled — keep only prefork (needed by mod_php)
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p /var/www/html/uploads /var/www/html/storage
chown -R www-data:www-data /var/www/html/uploads /var/www/html/storage
exec apache2-foreground
