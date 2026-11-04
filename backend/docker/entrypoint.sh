#!/bin/sh
# Démarrage du conteneur API : mise en cache de la configuration, migrations, comptes.
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force

# En local (SEED_DEMO=true) : comptes et véhicules de démonstration ; en production : l'administrateur seul
if [ "$SEED_DEMO" = "true" ]; then
    php artisan db:seed --force
else
    php artisan db:seed --class=AdministrateurSeeder --force
fi

exec "$@"
