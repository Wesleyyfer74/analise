#!/bin/sh
set -eu

export PORT="${PORT:-8080}"

if [ -n "${RAILWAY_VOLUME_MOUNT_PATH:-}" ]; then
  mkdir -p "$RAILWAY_VOLUME_MOUNT_PATH/reports" "$RAILWAY_VOLUME_MOUNT_PATH/cache" "$RAILWAY_VOLUME_MOUNT_PATH/logs"
  chown -R www-data:www-data "$RAILWAY_VOLUME_MOUNT_PATH" || true
fi

envsubst '${PORT}' < /etc/apache2/ports.conf > /tmp/ports.conf
cat /tmp/ports.conf > /etc/apache2/ports.conf
envsubst '${PORT}' < /etc/apache2/sites-available/000-default.conf > /tmp/000-default.conf
cat /tmp/000-default.conf > /etc/apache2/sites-available/000-default.conf

exec "$@"
