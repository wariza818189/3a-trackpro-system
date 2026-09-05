# 3A TrackPro

Hardware Store Sales and Inventory Management System for a single-location Philippine hardware store. School project; final presentation: October 22, 2026.

## Stage 0 scope

Fresh Laravel 13 application with Blade, Tailwind CSS 4, Vite, and PHPUnit. The landing page works without a database. Business features and authentication are not implemented. Supplier management is out of scope for Version 1; later restocking may have an optional supplier/invoice/reference field.

## Requirements

- PHP 8.4 with Laravel-required extensions and PDO MySQL; PDO SQLite for isolated tests.
- Composer 2.
- Node compatible with the locked Vite dependencies. This setup uses installed Node 20.20.2; Node 24 LTS is preferred when safe version management is available. Node 20 is end-of-life.
- MySQL is reserved for Stage 1. Do not provision databases or run migrations during Stage 0.

## Local setup

```bash
composer install
cp .env.example .env
chmod 600 .env
php artisan key:generate
npm ci
npm run build
```

Copy `.env.example` only when `.env` does not already exist. Never overwrite an existing application key. The example database names are placeholders, not provisioned resources. No database connection is needed for the landing page: sessions/cache use files and queued work is configured synchronously.

Run these in separate terminals:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

```bash
npm run dev -- --host 127.0.0.1
```

Open http://127.0.0.1:8000. `composer dev` starts only the local PHP server; run Vite separately. Assets use system fonts, with no remote font download.

## Verification

```bash
composer validate --strict
composer check-platform-reqs
composer audit
npm audit
npm run build
php artisan test
vendor/bin/pint --test
git diff --check
git status --short --branch
```

PHPUnit forces an in-memory SQLite test connection. Stage 0 tests do not migrate or seed anything. MySQL integration tests and dedicated development/test databases require Stage 1 approval. Default framework migrations and the User model are retained from the skeleton but have not been executed or extended into authentication features. The default seeder is intentionally empty.

## Configuration and security

The business timezone is `Asia/Manila` in `config/app.php`; application dates and reports must use that same convention. Stage 1 must align database/session timestamp behavior with it.

`.env`, other secret environment files, dependencies, generated assets, and local `.agents/` and `.codex/` directories are ignored. Only `.env.example` is versionable. Do not put credentials or client data in source code, logs, screenshots, or seeders. `APP_KEY` is generated locally, never shared in documentation. Keep debug mode restricted to local development.

Future deployment will use Nginx, PHP-FPM, and MySQL. Serve only `public/`, disable debug mode, use separate deployment credentials and persistent storage, and rehearse backups/restores. Docker deployment is deferred.

See [Stage 0 notes](docs/stage-0.md) for implementation boundaries and follow-up decisions.
