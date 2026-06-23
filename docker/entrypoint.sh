#!/bin/bash
set -euo pipefail

SHOP_DIR=/var/www/html
MODULE_ID="endereco-oxid7-client"
MODULE_PATH="${SHOP_DIR}/source/modules/endereco/endereco-oxid7-twig-client"
SETUP_FLAG="${SHOP_DIR}/.setup-done"

# Refresh the autoloader on every start so the volume-mounted module's classes
# are always resolvable, even after a container restart without rebuild.
cd "$SHOP_DIR"
composer dump-autoload --no-interaction --quiet 2>/dev/null || true

# Clear compiled Twig templates and template chain cache on every start.
# Without this, switching git branches while the container is running leaves
# stale compiled templates that no longer match the current source — causing
# silent failures like enderecoLoadAMSConfig rendering wrong or not at all.
rm -rf "${SHOP_DIR}/source/tmp/twig_component/" \
       "${SHOP_DIR}/source/tmp/template_cache/" 2>/dev/null || true

if [ ! -f "$SETUP_FLAG" ]; then
    # Run setup in the background so Apache can start immediately.
    # The shop will return 500 until setup is complete — that's expected and documented.
    (
        echo "[setup] Waiting for MySQL..."
        until mysql --ssl=FALSE -h"${MYSQL_HOST}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" \
              "${MYSQL_DATABASE}" -e "SELECT 1" > /dev/null 2>&1; do
            echo -n "."
            sleep 2
        done
        echo ""
        echo "[setup] MySQL ready."

        cd "$SHOP_DIR"

        mkdir -p source/tmp source/export var/log var/cache var/configuration/shops
        chown -R www-data:www-data source/tmp source/export var/

        echo "[setup] Setting up OXID shop (this takes a few minutes)..."
        vendor/bin/oe-console oe:setup:shop \
            --db-host="${MYSQL_HOST}" \
            --db-port=3306 \
            --db-name="${MYSQL_DATABASE}" \
            --db-user="${MYSQL_USER}" \
            --db-password="${MYSQL_PASSWORD}" \
            --shop-url="${SHOP_URL}" \
            --shop-directory="${SHOP_DIR}/source" \
            --compile-directory="${SHOP_DIR}/source/tmp" \
            --no-interaction

        echo "[setup] Installing demo data..."
        vendor/bin/oe-console oe:setup:demodata --no-interaction

        echo "[setup] Creating admin user..."
        vendor/bin/oe-console oe:admin:create-user \
            --admin-email="admin@example.com" \
            --admin-password="admin" \
            --no-interaction

        echo "[setup] Activating APEX theme..."
        vendor/bin/oe-console oe:theme:activate apex --no-interaction 2>/dev/null || true

        echo "[setup] Disabling maintenance mode..."
        mysql --ssl=FALSE -h"${MYSQL_HOST}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" \
            -e "DELETE FROM oxconfig WHERE OXVARNAME='blShopStopped' AND OXSHOPID='1';" \
            2>/dev/null || true

        if [ -f "${MODULE_PATH}/metadata.php" ]; then
            echo "[setup] Installing and activating ${MODULE_ID}..."
            vendor/bin/oe-console oe:module:install \
                "source/modules/endereco/endereco-oxid7-twig-client" \
                --no-interaction 2>/dev/null || true

            vendor/bin/oe-console oe:module:activate "$MODULE_ID" \
                --no-interaction 2>/dev/null \
                || echo "[setup] Module activation failed — activate manually in the admin panel."

            echo "[setup] Running module migrations..."
            # Migrations add required DB columns (e.g. MOJOISO31662 on oxstates).
            # oe:module:activate does not trigger onActivate (events[] is empty in metadata.php),
            # so migrations are the only path to schema changes.
            vendor/bin/oe-eshop-doctrine_migration migrations:migrate --no-interaction 2>/dev/null || true

            echo "[setup] Regenerating database views..."
            # Views must be rebuilt after migrations add new columns, otherwise OXID
            # queries against the old view schema and throws "Unknown column" errors.
            php << 'PHPEOF' || true
<?php
define('OXID_PHP_UNIT', false);
require '/var/www/html/source/bootstrap.php';
OxidEsales\Eshop\Core\Registry::get(OxidEsales\Eshop\Core\DbMetaDataHandler::class)->updateViews();
echo "Views regenerated.\n";
PHPEOF
        else
            echo "[setup] Module not found at ${MODULE_PATH}; skipping activation."
        fi

        echo "[setup] Setting config.inc.php to read-only..."
        chmod 444 source/config.inc.php 2>/dev/null || true

        rm -rf source/tmp/*
        chown -R www-data:www-data source/tmp var/

        touch "$SETUP_FLAG"
        echo "[setup] Setup complete — shop is ready."
    ) &
fi

# Remove the setup wizard so OXID never redirects to it.
# oe:setup:shop handles everything via CLI in the background.
rm -rf "${SHOP_DIR}/source/Setup"

exec "$@"
