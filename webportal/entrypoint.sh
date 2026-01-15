#!/bin/sh
set -e

TARGET_DIR=/var/www/html
SOURCE_DIR=/opt/laravel

if [ ! -f "${TARGET_DIR}/artisan" ]; then
  echo "Populating Laravel application in ${TARGET_DIR}..."
  mkdir -p "${TARGET_DIR}"
  cp -a ${SOURCE_DIR}/. "${TARGET_DIR}/"
fi

cd "${TARGET_DIR}"

if [ ! -f "${TARGET_DIR}/vendor/autoload.php" ]; then
  echo "Installing composer dependencies..."
  composer install --no-dev --prefer-dist --no-interaction
  chown -R www-data:www-data "${TARGET_DIR}/vendor"
fi

echo "Ensuring writable storage and cache directories..."
mkdir -p "${TARGET_DIR}/storage" "${TARGET_DIR}/bootstrap/cache"
chown -R www-data:www-data "${TARGET_DIR}/storage" "${TARGET_DIR}/bootstrap/cache"
chmod -R ug+rwX "${TARGET_DIR}/storage" "${TARGET_DIR}/bootstrap/cache"

exec "$@"
