#!/usr/bin/env bash
# No database access, migrations, seeds, admin creation or key generation.
set -Eeuo pipefail
umask 0027
fail() { echo "TrackPro startup refused: $*" >&2; exit 1; }
[[ ${APP_ENV:-} == production && ${APP_DEBUG:-} == false && -n ${APP_KEY:-} ]] || fail 'production settings and APP_KEY required'
[[ ${APP_URL:-} == https://* ]] || fail 'HTTPS APP_URL required'
[[ ${PORT:-} =~ ^[0-9]+$ && ${#PORT} -le 5 ]] || fail 'invalid PORT'
(( 10#$PORT > 1024 && 10#$PORT < 65536 )) || fail 'PORT must be 1025..65535'
[[ ${DB_CONNECTION:-} == mysql && ${DB_DATABASE:-} == trackpro_render_demo && ${DB_PORT:-} == 3306 ]] || fail 'separate Render demo database required'
[[ -n ${DB_HOST:-} && -n ${DB_USERNAME:-} && ${DB_USERNAME} != root && -n ${DB_PASSWORD:-} ]] || fail 'non-root database credentials required'
[[ $DB_HOST != *azure* && $DB_HOST != *ces* ]] || fail 'Azure/CES hosts forbidden'
[[ -n ${MYSQL_ATTR_SSL_CA:-} && -r $MYSQL_ATTR_SSL_CA ]] || fail 'readable verified MySQL CA required'
[[ ${SESSION_SECURE_COOKIE:-} == true && ${SESSION_HTTP_ONLY:-} == true && ${SESSION_SAME_SITE:-} == lax && -z ${SESSION_DOMAIN:-} ]] || fail 'secure host-only cookies required'
[[ ${SESSION_DRIVER:-} == file && ${CACHE_STORE:-} == file && ${QUEUE_CONNECTION:-} == sync && ${FILESYSTEM_DISK:-} == local ]] || fail 'file storage and synchronous queue required'
for setting in DB_URL DB_SOCKET APP_BASE_PATH LARAVEL_STORAGE_PATH APP_CONFIG_CACHE APP_SERVICES_CACHE APP_PACKAGES_CACHE APP_ROUTES_CACHE APP_EVENTS_CACHE VIEW_COMPILED_PATH; do
    [[ -z ${!setting:-} ]] || fail "unsupported override: $setting"
done
[[ ! -e /app/.env && ! -e /app/public/hot && ! -e /app/public/storage ]] || fail 'unexpected environment or public artifact'
# Render disk must be mounted here. Initialize empty disks without replacing data.
for directory in app/private app/public framework/cache/data framework/sessions framework/views logs; do
    mkdir -p "/app/storage/$directory"
done
chown www-data:www-data /app/storage /app/storage/app /app/storage/framework /app/storage/framework/cache     /app/storage/app/private /app/storage/app/public /app/storage/framework/cache/data     /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs /app/bootstrap/cache
runuser -u www-data -- php -r '
foreach (["app/private", "app/public", "framework/cache/data", "framework/sessions", "framework/views", "logs"] as $dir) {
    $path = "/app/storage/".$dir."/.probe-".bin2hex(random_bytes(8));
    $f = fopen($path, "x+");
    if (!$f || !flock($f, LOCK_EX) || fwrite($f, "ok") !== 2) { exit(1); }
    fclose($f); unlink($path);
}'
# Rebuild only ephemeral configuration/views with current runtime settings.
runuser -u www-data -- php artisan config:cache
runuser -u www-data -- php artisan view:cache
envsubst '${PORT}' < /etc/nginx/templates/trackpro.conf.template > /etc/nginx/conf.d/trackpro.conf
nginx -t
php-fpm -t
php-fpm --nodaemonize &
fpm_pid=$!
nginx -g 'daemon off;' &
nginx_pid=$!
stop() {
    trap - TERM INT
    kill -QUIT "$nginx_pid" "$fpm_pid" 2>/dev/null || true
    wait "$nginx_pid" "$fpm_pid" 2>/dev/null || true
}
trap stop TERM INT
set +e
wait -n "$fpm_pid" "$nginx_pid"
status=$?
stop
exit "$status"
