#!/usr/bin/env sh
# Installs WordPress and activates the plugin in the docker dev environment.
# Runs inside the `cli` container (`make setup`); safe to re-run.
set -e

cd /var/www/html

echo "Waiting for wp-config.php..."
i=0
until [ -f wp-config.php ]; do
    i=$((i + 1))
    if [ $i -gt 60 ]; then
        echo "wp-config.php never appeared; is the wordpress container running?" >&2
        exit 1
    fi
    sleep 1
done

if ! wp core is-installed 2>/dev/null; then
    wp core install \
        --url="http://localhost:${WP_PORT:-8080}" \
        --title="Cablecast Dev" \
        --admin_user=admin \
        --admin_password=admin \
        --admin_email=admin@example.com \
        --skip-email
fi

# The wordpress image ships a rewrite-ready .htaccess, so no --hard needed.
wp rewrite structure '/%postname%/'
wp plugin activate wp-cablecast

if [ -n "$CABLECAST_SERVER" ]; then
    wp option patch update cablecast_options server "$CABLECAST_SERVER" 2>/dev/null \
        || wp option patch insert cablecast_options server "$CABLECAST_SERVER"
fi

echo
echo "Site:  http://localhost:${WP_PORT:-8080}"
echo "Admin: http://localhost:${WP_PORT:-8080}/wp-admin  (admin / admin)"
echo "Cablecast server: $(wp option pluck cablecast_options server)"
