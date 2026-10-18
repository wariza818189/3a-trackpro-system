#!/usr/bin/env bash
# Approved TrackPro-only App Service startup command:
# bash /home/site/wwwroot/deploy/azure/startup.sh && exec php-fpm
# Not a local script. No migrations, seeds, installs, key generation, or cache flushes.
set +x
set -Eeuo pipefail
root=/home/site/wwwroot
data=/home/trackpro-storage
expected_site=${TRACKPRO_EXPECTED_SITE_NAME:-}
actual_site=${WEBSITE_SITE_NAME:-}
site_config=/etc/nginx/sites-available/default
backup=
fail_closed() {
    trap - ERR
    set +e
    echo 'TrackPro startup failed; stopping Nginx. Keep ingress restricted.' >&2
    if [[ -n $backup && -f $backup ]]; then
        if cp "$backup" "$site_config"; then
            rm -f "$backup"
        else
            echo "Nginx config restoration failed; backup retained at $backup." >&2
        fi
    fi
    # Never issue service commands on CES or an unapproved/unknown app identity.
    # A wrong expected-name setting on the actual TrackPro app still stops Nginx.
    if [[ $actual_site == 3a-trackpro-demo-2026 ]]; then
        service nginx stop || nginx -s stop || echo 'BLOCKED: Nginx could not be stopped.' >&2
    else
        echo 'Nginx stop skipped: actual site is not the approved TrackPro app.' >&2
    fi
    exit 1
}
trap fail_closed ERR
require() { if ! "$@"; then fail_closed; fi; }
[[ $expected_site == 3a-trackpro-demo-2026 && $actual_site == 3a-trackpro-demo-2026 \
    && ${expected_site,,} != ces-portal-demo-2026 \
    && ${actual_site,,} != ces-portal-demo-2026 \
    && $actual_site == "$expected_site" ]] || {
    echo 'The approved TrackPro site identity must match; CES Portal is forbidden.' >&2; fail_closed;
}
[[ $(id -u) == 0 ]] || { echo 'Root startup identity required.' >&2; fail_closed; }
# Oryx starts default Nginx before this hook. Restricted TrackPro ingress is
# mandatory before deploying application files; reload timing cannot close that gap.
# The hook never launches FPM: only a successful return permits the wrapper exec.
approved_startup='bash /home/site/wwwroot/deploy/azure/startup.sh && exec php-fpm'
# Accept one exact terminal command and reject additional bare FPM commands.
# This is a narrow check of the trusted generated wrapper, not a shell sandbox.
require awk -v approved="$approved_startup" '
    /^[[:space:]]*#/ { next }
    NF { last = $0 }
    /(^|[[:space:];&|])php-fpm([[:space:];&|]|$)/ {
        if ($0 != approved) { invalid = 1 }
        count++
    }
    END { exit (invalid || count != 1 || last != approved) }
' /opt/startup/startup.sh
require test "${PHP_ORIGIN:-}" = php-fpm
require test "$(id -u www-data)" = 33
require test "$(id -g www-data)" = 33
[[ ${APP_ENV:-} == production && ${APP_DEBUG:-} == false && -n ${APP_KEY:-} ]] || {
    echo 'Production environment, disabled debug, and a privately configured key are required.' >&2; fail_closed;
}
[[ ${DB_CONNECTION:-} == mysql && ${DB_DATABASE:-} == trackpro_demo ]] || {
    echo 'Refusing startup without the separate TrackPro MySQL database setting.' >&2; fail_closed;
}
[[ -n ${MYSQL_ATTR_SSL_CA:-} && -r ${MYSQL_ATTR_SSL_CA:-} ]] || {
    echo 'A readable MySQL CA bundle is required.' >&2; fail_closed;
}
[[ -z ${DB_URL:-} && -z ${DB_SOCKET:-} ]] || {
    echo 'DB_URL and DB_SOCKET overrides must be unset.' >&2; fail_closed;
}
[[ -s "$root/public/build/manifest.json" && -s "$root/vendor/autoload.php" && ! -e "$root/public/hot" ]]
[[ ! -e "$root/.env" && ! -L "$root/.env" && ! -e "$root/bootstrap/cache/config.php" && ! -L "$root/bootstrap/cache/config.php" ]] || {
    echo 'Environment files and prebuilt configuration caches must not be deployed.' >&2; fail_closed;
}
# Reject alternate Laravel paths/drivers rather than probing unused directories.
for setting in APP_BASE_PATH DB_URL DB_SOCKET LARAVEL_STORAGE_PATH APP_CONFIG_CACHE APP_SERVICES_CACHE APP_PACKAGES_CACHE APP_ROUTES_CACHE APP_EVENTS_CACHE VIEW_COMPILED_PATH SESSION_FILES; do
    [[ -z ${!setting:-} ]] || { echo "Unsupported override: $setting" >&2; fail_closed; }
done
require test "${SESSION_DRIVER:-}" = file
require test "${CACHE_STORE:-}" = file
require test "${FILESYSTEM_DISK:-}" = local
require test "${QUEUE_CONNECTION:-}" = sync
require test "${LOG_CHANNEL:-}" = stderr
require test ! -e "$root/public/storage"
require test ! -L "$root/public/storage"
# No SSL verification-disable option is supplied; config/database.php retains
# PDO MySQL's verification default and requires the independently verified CA.
# Provision only known paths. On CIFS chown/chmod may fail or be ineffective;
# the worker's actual file operations below, not stat modes, decide write access.
# Keep it outside the ZIP deployment directory so deployments cannot delete it.
paths=("$data" "$data/framework" "$data/framework/cache" "$data/framework/cache/data"
    "$data/framework/sessions" "$data/framework/views" "$data/logs"
    "$data/app" "$data/app/private" "$data/app/public" "$root/bootstrap/cache")
for directory in "${paths[@]}"; do
    # Canonical-path equality also rejects symlinked ancestors and broken links.
    require test ! -L "$directory"
    require test "$(readlink -m "$directory")" = "$directory"
    mkdir -p "$directory"
    require test "$(readlink -f "$directory")" = "$directory"
    if ! chown www-data:www-data "$directory" || ! chmod 0770 "$directory"; then
        echo 'Storage ownership/mode hint unavailable; checking worker writes.' >&2
    fi
done
if [[ -L "$root/storage" ]]; then
    require test "$(readlink "$root/storage")" = "$data"
elif [[ -e "$root/storage" ]]; then
    echo 'Existing storage directory needs manual review; refusing to replace it.' >&2
    fail_closed
else
    ln -s "$data" "$root/storage"
fi
# No Laravel boot or DB access. Drop root and strip settings from the probe.
# Exercise exclusive creation, locking, read, rename and deletion in every path.
runuser --user www-data --group www-data -- env -i PATH="$PATH" php -r '
        foreach (array_slice($argv, 1) as $dir) {
            $path = $dir."/.trackpro-probe-".bin2hex(random_bytes(12));
            $renamed = $path.".renamed";
            try {
                $file = fopen($path, "x+");
                if ($file === false || !flock($file, LOCK_EX)
                    || fwrite($file, "probe") !== 5 || !fflush($file)) { throw new RuntimeException("Storage write probe failed."); }
                fclose($file);
                if (!rename($path, $renamed) || file_get_contents($renamed) !== "probe"
                    || !unlink($renamed)) { throw new RuntimeException("Storage write probe failed."); }
            } finally {
                if (is_file($path)) { unlink($path); }
                if (is_file($renamed)) { unlink($renamed); }
            }
        }
' -- "${paths[@]}"
# Only the verified include target is supported. No alternate platform paths.
require test "$(readlink -f /etc/nginx/sites-enabled/default)" = "$site_config"
candidate_backup=$(mktemp /tmp/trackpro-nginx-backup.XXXXXX)
cp "$site_config" "$candidate_backup"
backup=$candidate_backup
cp "$root/deploy/azure/nginx.conf" "$site_config"
nginx -t
service nginx reload
rm -f "$backup"
backup=
trap - ERR
# Return success to the approved fail-closed wrapper. Never invoke/start/background PHP-FPM here.
