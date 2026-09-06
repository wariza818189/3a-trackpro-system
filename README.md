# 3A TrackPro

Hardware Store Sales and Inventory Management System for a single-location Philippine hardware store. School project; final presentation: October 22, 2026.

## Current scope: Stage 3B opening inventory

Laravel 13 application with Blade, Tailwind CSS 4, Vite, and PHPUnit. The schema and model foundation is implemented and verified on isolated local and test databases running MySQL 8.0.46. Username/password authentication, active/disabled account enforcement, and Admin/Staff authorization use Laravel's native session guard. Stage 3A provides server-rendered Category, Product, and Product Variant management. Stage 3B adds the first stock mutation: an Admin-only, exactly-once opening physical count recorded as an immutable `INITIAL_STOCK` movement. Sales, restocking, corrections, and supplier workflows are not implemented.

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

Ordinary PHPUnit runs force an in-memory SQLite connection. Foundation tests do not migrate or seed anything and block PDO access. Authentication tests load only the existing users migration. Catalog feature tests load the portable users/categories/products migrations and construct a clearly labeled test-only Product Variant/history-marker schema for HTTP behavior; they never execute the MySQL-specific Product Variant migration. Production CHECK and collation behavior remains the responsibility of the guarded MySQL suite. The default seeder remains empty.

Live schema tests use the named `mysql_testing` connection and credentials from the ignored `.env.mysql` file. `scripts/verify-mysql-schema` rejects any database, account, socket, engine, or environment other than the dedicated test configuration before it migrates or rolls back. Its default entry state is an empty schema; set `MYSQL_TEST_INITIAL_STATE=migrated` for a guarded rerun from the verified migrated state. The runner is destructive to that isolated test database and must never be configured with the normal local database.

## Configuration and security

The business timezone is `Asia/Manila` in `config/app.php`; standard Laravel timestamp behavior was verified against MySQL while application dates remain in that business timezone.

`.env`, other secret environment files, dependencies, generated assets, and local `.agents/` and `.codex/` directories are ignored. Only `.env.example` is versionable. Do not put credentials or client data in source code, logs, screenshots, or seeders. `APP_KEY` is generated locally, never shared in documentation. Keep debug mode restricted to local development.

Future deployment will use Nginx, PHP-FPM, and MySQL. Serve only `public/`, disable debug mode, use separate deployment credentials and persistent storage, and rehearse backups/restores. Docker deployment is deferred.

See [Stage 0 notes](docs/stage-0.md) for implementation boundaries and follow-up decisions.

## Database foundation

See [database-design.md](docs/database-design.md) for the implemented fields, relationships, checks, and deferred workflow controls. Quantity fields use DECIMAL(14,3); supported units are piece, sheet, roll, m, kg, with no conversion system. Costs are reference/purchase values only, with no COGS or profit accounting. Standard Laravel timestamps are retained and application timezone remains Asia/Manila.

The explicit CHECK statements target MySQL and were verified on MySQL 8.0.46; do not execute these migrations on SQLite. Schema/constraint integration tests require the isolated, guarded MySQL test connection. Model decimal casts do not replace input validation or transactional stock services.

## Catalog behavior

Active Staff may browse only active Categories, active Products under active Categories, and active Variants in that hierarchy. Cost prices and catalog mutation controls are Admin-only. Admins can create, edit, archive, and reactivate catalog records subject to active-parent, child-state, history, and stock-safety rules.

Catalog forms never accept `current_stock`; new variants begin at `0.000`. Variant identity is frozen after stock or transaction activity, cost price becomes restock-owned after the first restock item, and a variant with positive stock cannot be archived. Catalog management provides no hard-delete routes.

## Opening inventory behavior

An active Admin can record one opening physical count for a Variant in an active Category → Product → Variant hierarchy. Zero is a valid opening count and still creates the `INITIAL_STOCK` evidence that permanently marks the Variant initialized. Quantity input is handled as a canonical three-decimal string and validated against the Variant's whole/fractional mode; the required reason is trimmed and whitespace-normalized.

The operation locks Category → Product → Product Variant, rechecks the hierarchy and status, and uses locking reads for movement, sale, and restock history before updating stock and appending the movement in one transaction. Opening inventory is rejected after any stock/transaction history or when a zero-stock invariant is not satisfied. It does not implement restocking, corrections, sales, or other later inventory workflows.
