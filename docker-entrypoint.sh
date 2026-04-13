#!/bin/bash
set -e

# Ensure the data directories exist and are writable by www-data
# (Docker volumes are mounted as root, so we fix permissions at runtime)
mkdir -p "${DATA_DIR}/logs" "${DATA_DIR}/uploads"
chown -R www-data:www-data "${DATA_DIR}"
chmod -R 755 "${DATA_DIR}"

# Also ensure game servers and steamcmd dirs are writable
chown -R www-data:www-data /opt/servers /opt/steamcmd 2>/dev/null || true

exec "$@"
