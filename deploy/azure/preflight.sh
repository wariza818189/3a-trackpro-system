#!/usr/bin/env bash
# Run from a disposable copy: never load the developer's .env or cached config.
set +x
set -euo pipefail
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
[[ $(git -C "$root" branch --show-current) == deploy/azure-trackpro-demo ]] || {
    echo 'Refusing preflight outside deploy/azure-trackpro-demo.' >&2; exit 1;
}
for tool in php composer node npm tar python3; do command -v "$tool" >/dev/null; done
[[ -d "$root/vendor" && -d "$root/node_modules" ]] || {
    echo 'Install locked development dependencies locally before preflight.' >&2; exit 1;
}
python3 -B "$root/tests/deployment/test_azure_startup.py"
python3 -B "$root/tests/deployment/test_release_package.py"
work=$(mktemp -d /tmp/trackpro-preflight.XXXXXX)
mkdir -p "$work/app" "$work/home"
tar -C "$root" --exclude='.env*' --exclude='bootstrap/cache' \
    --exclude='public/build' --exclude='public/hot' --exclude='public/storage' \
    -cf "$work/source.tar" app bootstrap config database public resources routes \
    scripts tests docs/test-cases.md artisan composer.json composer.lock \
    package.json package-lock.json phpunit.xml vite.config.js .gitignore
tar -C "$work/app" -xf "$work/source.tar"
cp -a "$root/vendor" "$work/app/vendor"
# Vite writes .vite-temp here; never write through a link into the source tree.
cp -a "$root/node_modules" "$work/app/node_modules"
mkdir -p "$work/app/bootstrap/cache" "$work/app/storage/framework/"{cache/data,sessions,views} \
    "$work/app/storage/logs" "$work/app/storage/app/"{private,public}
cd "$work/app"
# phpdotenv otherwise emits a missing-file warning during each PHPUnit boot.
# This empty placeholder contains no settings and is never copied from the repo.
: > .env
export PATH
run_clean() { env -i PATH="$PATH" HOME="$work/home" APP_BASE_PATH="$work/app" "$@"; }
run_clean composer validate --strict
run_clean composer check-platform-reqs
run_clean npm run build
# Ephemeral test key, held only in process memory; no real APP_KEY is read.
test_key=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
run_clean env APP_ENV=testing APP_KEY="$test_key" DB_CONNECTION=sqlite DB_DATABASE=:memory: \
    DB_URL= CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test
unset test_key
git -C "$root" diff --check
git -C "$root" status --short --branch
echo "Preflight copy retained at $work (no local environment or database copied)."
