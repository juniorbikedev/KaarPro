#!/bin/sh
# Démarrage du conteneur API : mise en cache de la configuration, migrations, compte administrateur.
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan db:seed --class=AdministrateurSeeder --force

exec "$@"
