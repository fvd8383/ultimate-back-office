#!/usr/bin/env bash
set -euo pipefail

echo "Deploying Ultimate Back Office staging..."

cd /var/www/ubo-repo

git checkout main
git pull --ff-only origin main

echo "Running PHP lint..."
find private public shared -name "*.php" -print0 | xargs -0 -n1 php -l

echo "PHP lint passed. Reloading apache2..."
systemctl reload apache2

echo "Staging deployment complete: main is current, PHP lint passed, and apache2 was reloaded."
