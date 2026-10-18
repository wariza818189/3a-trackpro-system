#!/usr/bin/env bash
# Build only. No Azure commands, database commands, or source dependency changes.
set +x
set -euo pipefail
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
[[ $(git -C "$root" branch --show-current) == deploy/azure-trackpro-demo ]] || {
    echo 'Refusing build outside deploy/azure-trackpro-demo.' >&2; exit 1;
}
for tool in php composer node npm tar zip unzip cmp sha256sum; do command -v "$tool" >/dev/null; done
work=$(mktemp -d /tmp/trackpro-build.XXXXXX)
trap 'result=$?; if (( result != 0 )); then echo "Build failed (exit $result); diagnostics retained at $work. No validated release." >&2; fi' EXIT
echo "Isolated build directory: $work"
mkdir -p "$work/app" "$work/home"
tar -C "$root" --exclude='.env*' --exclude='bootstrap/cache' \
    --exclude='public/build' --exclude='public/hot' --exclude='public/storage' \
    --exclude='.npmrc' --exclude='auth.json' --exclude='*.key' --exclude='*.pem' \
    --exclude='*.p12' --exclude='*.pfx' --exclude='*.sql' --exclude='*.sql.gz' \
    --exclude='*.dump' --exclude='*.bak' --exclude='*.backup' --exclude='*.log' \
    --exclude='.git' --exclude='.aws' --exclude='.azure' --exclude='.ssh' \
    --exclude='credentials' --exclude='credentials.json' --exclude='id_rsa*' --exclude='id_ed25519*' \
    --exclude='*.sqlite*' --exclude='*.db' \
    -cf "$work/source.tar" app bootstrap config database public resources routes \
    deploy/azure artisan composer.json composer.lock package.json package-lock.json vite.config.js .gitignore
tar -C "$work/app" -xf "$work/source.tar"
cd "$work/app"
mkdir -p bootstrap/cache
run_clean() { env -i PATH="$PATH" HOME="$work/home" APP_BASE_PATH="$work/app" "$@"; }
run_clean composer validate --strict
run_clean composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --no-plugins
run_clean composer check-platform-reqs --no-dev
# Discovery only; never cache build-machine settings or run post-update hooks.
run_clean env APP_ENV=production APP_DEBUG=false DB_CONNECTION=sqlite DB_DATABASE=:memory: \
    CACHE_STORE=file QUEUE_CONNECTION=sync php artisan package:discover --ansi
run_clean npm ci
run_clean npm run build
[[ -s public/build/manifest.json && ! -e public/hot && ! -e storage ]]
# Explicit payload: no node_modules, tests, local files, config cache, or runtime storage.
payload=(app bootstrap config database public resources routes deploy vendor artisan composer.json composer.lock)
# ZIP normally follows symlinks. Reject them before it can read an external target.
run_clean php -r '
    function checkPath(string $path): void {
        if (is_link($path)
            || preg_match("~[\x00-\x1f\x7f\\\\]~", $path)
            || preg_match("~(^|/)(?:\\.env[^/]*|\\.git|\\.aws|\\.azure|\\.ssh|\\.npmrc|\\.pypirc|auth\\.json|credentials(?:\\.json)?|id_(?:rsa|ed25519)[^/]*|node_modules)(/|$)~i", $path)
            || preg_match("~\\.(?:key|pem|p12|pfx|sql(?:\\.gz)?|dump|bak|backup|log|sqlite(?:3)?|db)$~i", $path)) {
            fwrite(STDERR, "Prohibited path or symlink in release payload.\n"); exit(1);
        }
    }
    foreach (array_slice($argv, 1) as $path) {
        checkPath($path);
        if (!is_dir($path)) { continue; }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            checkPath($file->getPathname());
        }
    }
' -- "${payload[@]}"
zip -qr "$work/trackpro.zip" "${payload[@]}"
# Validate the actual archive, not just the staging directory or zip exit code.
unzip -t "$work/trackpro.zip" > "$work/archive-integrity.log"
unzip -Z1 "$work/trackpro.zip" > "$work/archive-paths.txt"
unzip -p "$work/trackpro.zip" composer.lock > "$work/archive-composer.lock"
unzip -p "$work/trackpro.zip" vendor/composer/installed.json > "$work/archive-installed.json"
unzip -p "$work/trackpro.zip" public/build/manifest.json > "$work/archive-vite-manifest.json"
cmp "$root/composer.lock" "$work/archive-composer.lock"
run_clean php /dev/stdin "$work/archive-paths.txt" "$work/archive-composer.lock" \
    "$work/archive-installed.json" "$work/archive-vite-manifest.json" <<'PHP'
<?php
declare(strict_types=1);

function reject(string $reason): never
{
    fwrite(STDERR, "Release validation failed: $reason\n");
    exit(1);
}

// No archive content is evaluated as PHP. Compare metadata parsed as JSON only.
// argv[0] is /dev/stdin; the first supplied metadata path is argv[1].
$entries = file($argv[1], FILE_IGNORE_NEW_LINES);
if ($entries === false || $entries === []) { reject('empty archive listing'); }
$paths = [];
$roots = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'deploy', 'vendor'];
foreach ($entries as $path) {
    if ($path === '' || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)
        || str_starts_with($path, '/') || preg_match('~(^|/)\.{1,2}(/|$)~', $path)
        || isset($paths[$path])) { reject('unsafe or duplicate archive path'); }
    $paths[$path] = true;
    $parts = explode('/', rtrim($path, '/'));
    if (!in_array($parts[0], $roots, true)
        && !in_array($path, ['artisan', 'composer.json', 'composer.lock'], true)) {
        reject('unexpected top-level payload');
    }
    foreach ($parts as $part) {
        if (preg_match('/^(?:\.env.*|\.git|\.aws|\.azure|\.ssh|\.npmrc|\.pypirc|auth\.json|credentials(?:\.json)?|id_(?:rsa|ed25519).*)$/i', $part)
            || strcasecmp($part, 'node_modules') === 0
            || preg_match('/\.(?:key|pem|p12|pfx|sql(?:\.gz)?|dump|bak|backup|log|sqlite(?:3)?|db)$/i', $part)) {
            reject('prohibited environment, credential, dependency, backup, or runtime path');
        }
    }
    if ($path === 'public/hot' || str_starts_with($path, 'public/storage/') || $path === 'public/storage'
        || (str_starts_with($path, 'bootstrap/cache/')
            && !in_array($path, ['bootstrap/cache/', 'bootstrap/cache/packages.php', 'bootstrap/cache/services.php'], true))) {
        reject('runtime storage or cached configuration in archive');
    }
}
foreach (['artisan', 'composer.json', 'composer.lock', 'public/index.php',
    'vendor/autoload.php', 'vendor/composer/installed.json', 'vendor/composer/installed.php',
    'vendor/composer/autoload_real.php', 'vendor/composer/autoload_psr4.php',
    'public/build/manifest.json', 'bootstrap/app.php', 'bootstrap/providers.php',
    'config/app.php', 'config/database.php', 'config/session.php', 'config/filesystems.php',
    'deploy/azure/startup.sh', 'deploy/azure/nginx.conf'] as $required) {
    if (!isset($paths[$required])) { reject("missing required file: $required"); }
}
$lock = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$installed = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
if (($installed['dev'] ?? null) !== false) { reject('Composer metadata is not a no-dev installation'); }
$expected = [];
foreach ($lock['packages'] as $package) { $expected[$package['name']] = $package; }
$actual = [];
foreach ($installed['packages'] as $package) {
    $name = $package['name'];
    if (isset($actual[$name]) || !isset($expected[$name])) { reject('unexpected or duplicate Composer package'); }
    $actual[$name] = $package;
    foreach (['version', 'source', 'dist'] as $field) {
        if (($package[$field] ?? null) !== ($expected[$name][$field] ?? null)) {
            reject("locked Composer metadata mismatch: $name");
        }
    }
    if (!isset($paths["vendor/$name/composer.json"])) { reject("missing package files: $name"); }
}
if (count($actual) !== count($expected)) { reject('missing locked production packages'); }
// Reject undeclared package directories too, even if installed.json omits them.
foreach ($entries as $path) {
    if (!str_starts_with($path, 'vendor/') || $path === 'vendor/') { continue; }
    if ($path === 'vendor/autoload.php' || preg_match('~^vendor/(?:composer|bin)(?:/|$)~', $path)) { continue; }
    $owned = false;
    foreach (array_keys($expected) as $name) {
        $prefix = "vendor/$name/";
        if (str_starts_with($path, $prefix) || (str_ends_with($path, '/') && str_starts_with($prefix, $path))) {
            $owned = true; break;
        }
    }
    if (!$owned) { reject('unlocked vendor content'); }
}
$manifest = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($manifest) || $manifest === []) { reject('empty Vite manifest'); }
foreach ($manifest as $asset) {
    if (!isset($asset['file'], $paths['public/build/'.$asset['file']])) { reject('missing compiled Vite asset'); }
    foreach (array_merge($asset['css'] ?? [], $asset['assets'] ?? []) as $file) {
        if (!isset($paths['public/build/'.$file])) { reject('missing referenced Vite asset'); }
    }
}
echo 'Archive validation PASS: required files, path policy, locked production packages, and Vite assets.'.PHP_EOL;
PHP
php -r 'echo hash_file("sha256", $argv[1])."  trackpro.zip\n";' "$work/trackpro.zip" > "$work/trackpro.zip.sha256"
(
    cd "$work"
    sha256sum --check trackpro.zip.sha256
)
echo "Validated package retained at $work; Azure runtime verification is still required."
