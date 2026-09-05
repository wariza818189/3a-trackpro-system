# 3A TrackPro

Hardware Store Sales and Inventory Management System for a single-location Philippine hardware store. School project; final presentation: October 22, 2026.

## Current scope: Stage 2 authentication foundation

Laravel 13 application with Blade, Tailwind CSS 4, Vite, and PHPUnit. The schema and model foundation is implemented and verified on isolated local and test databases running MySQL 8.0.46. Username/password authentication, active/disabled account enforcement, and Admin/Staff authorization are implemented with Laravel's native session guard. Business workflows are not implemented. Supplier management is out of scope for Version 1; later restocking may have an optional supplier/invoice/reference field.

## Requirements

- PHP 8.4 with Laravel-required extensions and PDO MySQL; PDO SQLite for isolated tests.
- Composer 2.
- Node compatible with the locked Vite dependencies. This setup uses installed Node 20.20.2; Node 24 LTS is preferred when safe version management is available. Node 20 is end-of-life.
- MySQL 8.0.46 with InnoDB for the application schema and guarded integration tests.

## Local setup

```bash
composer install
cp .env.example .env
chmod 600 .env
php artisan key:generate
npm ci
npm run build
```

Copy `.env.example` only when `.env` does not already exist. Never overwrite an existing application key. Supply the local database password privately; `.env.example` intentionally contains none. Sessions/cache use files and queued work is configured synchronously.

Run these in separate terminals:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

```bash
npm run dev -- --host 127.0.0.1
```

Open http://127.0.0.1:8000. `composer dev` starts only the local PHP server; run Vite separately. Assets use system fonts, with no remote font download.

There is no public account registration or password-reset workflow. To create the first operational administrator, run `php artisan trackpro:create-admin` and confirm the displayed environment and database before entering the account details. The command prompts for the password privately and never accepts it as a command-line argument.

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

Ordinary PHPUnit runs force an in-memory SQLite connection. Foundation tests do not migrate or seed anything and block PDO access. Authentication tests load only the existing users migration into that in-memory database; they do not execute the MySQL-specific business migrations. The users migration uses username/role/status; unused cache/jobs migrations were removed. The ten application migrations have been applied to the local development database, and the default seeder remains empty.

Live schema tests use the named `mysql_testing` connection and credentials from the ignored `.env.mysql` file. `scripts/verify-mysql-schema` rejects any database, account, socket, engine, or environment other than the dedicated test configuration before it migrates or rolls back. Its default entry state is an empty schema; set `MYSQL_TEST_INITIAL_STATE=migrated` for a guarded rerun from the verified migrated state. The runner is destructive to that isolated test database and must never be configured with the normal local database.

## Configuration and security

The business timezone is `Asia/Manila` in `config/app.php`; standard Laravel timestamp behavior was verified against MySQL while application dates remain in that business timezone.

`.env`, other secret environment files, dependencies, generated assets, and local `.agents/` and `.codex/` directories are ignored. Only `.env.example` is versionable. Do not put credentials or client data in source code, logs, screenshots, or seeders. `APP_KEY` is generated locally, never shared in documentation. Keep debug mode restricted to local development.

Future deployment will use Nginx, PHP-FPM, and MySQL. Serve only `public/`, disable debug mode, use separate deployment credentials and persistent storage, and rehearse backups/restores. Docker deployment is deferred.

See [Stage 0 notes](docs/stage-0.md) for implementation boundaries and follow-up decisions.

## Database foundation

See [database-design.md](docs/database-design.md) for the implemented fields, relationships, checks, and deferred workflow controls. Quantity fields use DECIMAL(14,3); supported units are piece, sheet, roll, m, kg, with no conversion system. Costs are reference/purchase values only, with no COGS or profit accounting. Standard Laravel timestamps are retained and application timezone remains Asia/Manila.

The explicit CHECK statements target MySQL and were verified on MySQL 8.0.46; do not execute these migrations on SQLite. Schema/constraint integration tests require the isolated, guarded MySQL test connection. Model decimal casts do not replace input validation or transactional stock services.
