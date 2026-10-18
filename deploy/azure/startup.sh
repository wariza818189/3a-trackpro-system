#!/usr/bin/env bash
# Future TrackPro-only App Service startup command: bash /home/site/wwwroot/deploy/azure/startup.sh
# Not a local script. No migrations, seeds, installs, key generation, or cache flushes.
set +x
set -euo pipefail
root=/home/site/wwwroot
data=/home/trackpro-storage
expected_site=${TRACKPRO_EXPECTED_SITE_NAME:-}
actual_site=${WEBSITE_SITE_NAME:-}
[[ -n $expected_site && -n $actual_site \
    && ${expected_site,,} != ces-portal-demo-2026 \
    && ${actual_site,,} != ces-portal-demo-2026 \
    && $actual_site == "$expected_site" ]] || {
    echo 'The approved TrackPro site identity must match; CES Portal is forbidden.' >&2; exit 1;
}
[[ ${APP_ENV:-} == production && ${APP_DEBUG:-} == false && -n ${APP_KEY:-} ]] || {
    echo 'Production environment, disabled debug, and a privately configured key are required.' >&2; exit 1;
}
[[ ${DB_CONNECTION:-} == mysql && ${DB_DATABASE:-} == trackpro_demo ]] || {
    echo 'Refusing startup without the separate TrackPro MySQL database setting.' >&2; exit 1;
}
[[ -n ${MYSQL_ATTR_SSL_CA:-} && -r ${MYSQL_ATTR_SSL_CA:-} ]] || {
    echo 'A readable MySQL CA bundle is required.' >&2; exit 1;
}
[[ -z ${DB_URL:-} && -z ${DB_SOCKET:-} ]] || {
    echo 'DB_URL and DB_SOCKET overrides must be unset.' >&2; exit 1;
}
[[ -s "$root/public/build/manifest.json" && -s "$root/vendor/autoload.php" && ! -e "$root/public/hot" ]]
[[ ! -e "$root/.env" && ! -e "$root/bootstrap/cache/config.php" ]] || {
    echo 'Environment files and prebuilt configuration caches must not be deployed.' >&2; exit 1;
}
# Provision this directory with the verified PHP-FPM identity before first launch.
# Keep it outside the ZIP deployment directory so deployments cannot delete it.
mkdir -p "$data/framework/"{cache/data,sessions,views} "$data/logs" "$data/app/"{private,public}
if [[ -L "$root/storage" ]]; then
    [[ $(readlink "$root/storage") == "$data" ]] || exit 1
elif [[ -e "$root/storage" ]]; then
    echo 'Existing storage directory needs manual review; refusing to replace it.' >&2; exit 1
else
    ln -s "$data" "$root/storage"
fi
[[ -w "$root/bootstrap/cache" && -w "$data/framework/sessions" ]]
# Microsoft documents reapplying site configuration on every container startup.
# Verify the include path, FPM endpoint, permissions, and startup failure behavior first.
cp "$root/deploy/azure/nginx.conf" /etc/nginx/sites-available/default
nginx -t
service nginx reload
