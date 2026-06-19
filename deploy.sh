#!/bin/bash
set -e

# Execute cote serveur (Infomaniak) par .github/workflows/deploy.yml.
# APP_DIR = racine du depot clone sur le serveur ; l'app Symfony vit dans site/.
# Le document root du domaine doit pointer vers site/public.

source ~/.bashrc

git fetch --tags
# shellcheck disable=SC2046
git checkout $(git tag --sort=-version:refname | head -1)

cd site

composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
# Telecharge les paquets JS distants (importmap.php) dans assets/vendor, qui est
# gitignore : indispensable avant asset-map:compile pour que GSAP & co soient servis.
php bin/console importmap:install
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear --env=prod

echo "Deploy termine."
