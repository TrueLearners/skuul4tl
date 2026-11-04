#!/bin/sh
set -eu
cd /var/www/html
PORT="${PORT:-10000}"
export PORT

envsubst '${PORT}' < /etc/apache2/ports.conf.template > /etc/apache2/ports.conf
sed -i "s/:10000>/:\${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/framework/cache storage/framework/sessions \
  storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Apply database migrations before starting the web server.
php artisan migrate --force

# Fill in missing country states without truncating existing countries or states.
php artisan world:import-missing-states

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/skuul.conf
