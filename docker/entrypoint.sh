#!/bin/sh
set -e
cd /opt/www

[ ! -d vendor ] && composer install --no-interaction --prefer-dist
composer dump-autoload -o
rm -rf runtime/container

# Subir o Hyperf SEM anexar nenhum argumento extra
exec php bin/hyperf.php start
