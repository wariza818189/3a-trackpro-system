# Stage 0 implementation notes

Date: September 5, 2026.

## Foundation

Source: official `laravel/laravel` skeleton v13.10.1, downloaded with `--no-install --no-scripts` into `${STAGE0_TEMP_DIR}/app`. The manifest was inspected before copying. Existing `.git/`, `.agents/`, and `.codex/` were protected against copying and destination collisions were checked first. The historical repository was not accessed.

Upstream `AGENTS.md` and `CLAUDE.md` bootstrap instructions were omitted because they require extra agent tooling outside approved scope. No Boost package was added. The scaffold's default User model, user factory, and framework migrations remain unchanged; no authentication feature or business-domain object was added. No migration or seeding command was run. The default example-user seeder was made empty.

## Deliberate adjustments

- Application identity and `Asia/Manila` business timezone.
- File sessions/cache, synchronous queue configuration, log mail, and blank database credentials.
- Removed Composer setup/create hooks that automatically migrate or create SQLite files.
- Composer development command starts only Artisan's local server.
- Removed unused frontend process orchestration dependencies and remote font fetching.
- Blade landing page, shared layout, explicit Tailwind source scanning, and system fonts.
- Landing-page and timezone tests; forced isolated PHPUnit database environment.
- Ignored local agent directories and all secret environment variants.

No configured nvm, fnm, Volta, or asdf was found through command lookup and conventional installation paths. Operating-system Node remains 20.20.2. No `.nvmrc` was added because there is no configured manager and no Node 24 runtime was validated. Dependency engine requirements and the build are checked against the existing runtime.

## Deferred work

Stage 1 requires explicit approval for dedicated MySQL databases/users and database connectivity checks. No existing MySQL resources were accessed or changed during implementation. Match MySQL timestamp handling to Manila business time before creating domain schemas.

Products, variants, sales, sale items, restocks, stock movements, audit logs, authentication, and roles are deferred. Supplier management is excluded from Version 1. There are no public setup/migration/reset routes.

Review Node 24 LTS version management, MySQL readiness, store requirements, remote Git state, and deployment needs before the next stage. Keep the October 22 presentation deadline in view; reserve time for verification, documentation, and demonstration rehearsal.

Dependency locks record exact resolved versions. No commit or push is part of Stage 0.

## Verification results

- PHP 8.4.25; Composer 2.9.7; Node 20.20.2; npm 10.8.2.
- Laravel framework 13.30.1; PHPUnit 12.5.34; Pint 1.30.5.
- Vite 8.2.2; laravel-vite-plugin 3.2.0; Tailwind CSS and its Vite plugin 4.3.3.
- Installed 109 Composer packages and 35 npm packages. Exact transitive versions are in the lockfiles.
- Composer strict validation and platform requirements passed.
- Composer audit and npm audit reported zero vulnerabilities.
- PHPUnit: 3 tests, 7 assertions, all passed. Pint passed.
- Vite production build passed with installed Node. Vite and Laravel's plugin declare `^20.19.0 || >=22.12.0`.
- Application-kernel smoke check returned HTTP 200 with real compiled asset links, without mocking Vite.
- Git diff check and separate untracked-text whitespace check passed. `.env` permissions are 600; secret environment files and agent directories are ignored, while `.env.example` is not ignored.
- Route review: landing page, standard `/up` health route, and framework local-storage routes. No setup, migration, or reset endpoint.
- All project source files remain untracked on unborn `main`. Upstream tracking still reports `origin/main [gone]`; no remote synchronization was attempted.

## Commands executed

Temporary paths below use `${STAGE0_TEMP_DIR}` as a generalized placeholder for the scaffold directory.

Read-only inspection used `pwd`, `ls`, `rg`, `git status`, `command -v` for nvm/fnm/volta/asdf, conventional version-manager path checks, and PHP/Composer/Node/npm version commands. Scaffold files were inspected with `rg --files --hidden` and `cat`.

```bash
STAGE0_TEMP_DIR="$(mktemp -d)"
composer create-project --prefer-dist --no-install --no-scripts --no-interaction laravel/laravel ${STAGE0_TEMP_DIR}/app '^13.0'
COMPOSER_CACHE_DIR=${STAGE0_TEMP_DIR}/composer-cache composer create-project --prefer-dist --no-install --no-scripts --no-interaction laravel/laravel ${STAGE0_TEMP_DIR}/app '^13.0'
COMPOSER_CACHE_DIR=${STAGE0_TEMP_DIR}/composer-cache composer install --prefer-dist --no-interaction
npm install --cache ${STAGE0_TEMP_DIR}/npm-cache
php artisan key:generate
composer validate --strict
composer check-platform-reqs
COMPOSER_CACHE_DIR=${STAGE0_TEMP_DIR}/composer-cache composer audit
php artisan test
vendor/bin/pint --test
npm run build
npm audit --cache ${STAGE0_TEMP_DIR}/npm-cache
npm ls --depth=0
php artisan route:list --except-vendor
php artisan route:list
git diff --check
git status --short --branch
git check-ignore .env .env.testing .agents/ .codex/ vendor/ node_modules/ public/build/
git check-ignore .env.example
stat -c '%a %n' .env
```

The initial Composer scaffold request failed on sandbox DNS/cache permissions; it was retried with approved network access and a temporary cache. The initial npm install hit sandbox DNS errors and was interrupted before retrying with approved network access. An interim `npm ls` reported missing dependencies while installation was still pending; the final run passed. Diagnostic log reads used `ls`, `rg`, and `tail`; a process lookup used `ps` and `rg`.

Python scripts performed collision-checked copying, project file edits, local `.env` creation with restrictive permissions, a whitespace check for untracked source files, and the source inventory below. Shell heredocs wrote project documentation. A Node command read dependency engines from installed package manifests. A PHP heredoc exercised the landing page through the application kernel. Composer ran its package-discovery and asset-publication hooks; no publishable assets were found. No migration/create-database hook ran.

Temporary scaffold/download caches remain under `${STAGE0_TEMP_DIR}`; no cleanup of external directories was performed.

## Complete versionable file inventory

All are newly created in this repository.

```text
.editorconfig
.env.example
.gitattributes
.gitignore
.npmrc
README.md
app/Http/Controllers/Controller.php
app/Models/User.php
app/Providers/AppServiceProvider.php
artisan
bootstrap/app.php
bootstrap/cache/.gitignore
bootstrap/providers.php
composer.json
composer.lock
config/app.php
config/auth.php
config/cache.php
config/database.php
config/filesystems.php
config/logging.php
config/mail.php
config/queue.php
config/services.php
config/session.php
database/.gitignore
database/factories/UserFactory.php
database/migrations/0001_01_01_000000_create_users_table.php
database/migrations/0001_01_01_000001_create_cache_table.php
database/migrations/0001_01_01_000002_create_jobs_table.php
database/seeders/DatabaseSeeder.php
docs/stage-0.md
package-lock.json
package.json
phpunit.xml
public/.htaccess
public/favicon.ico
public/index.php
public/robots.txt
resources/css/app.css
resources/js/app.js
resources/views/layouts/app.blade.php
resources/views/welcome.blade.php
routes/console.php
routes/web.php
storage/app/.gitignore
storage/app/private/.gitignore
storage/app/public/.gitignore
storage/framework/.gitignore
storage/framework/cache/.gitignore
storage/framework/cache/data/.gitignore
storage/framework/sessions/.gitignore
storage/framework/testing/.gitignore
storage/framework/views/.gitignore
storage/logs/.gitignore
tests/Feature/ExampleTest.php
tests/TestCase.php
tests/Unit/ExampleTest.php
vite.config.js
```

Files customized relative to the official scaffold:

```text
.env.example
.gitignore
README.md
composer.json
config/app.php
database/seeders/DatabaseSeeder.php
package.json
phpunit.xml
resources/css/app.css
resources/views/welcome.blade.php
tests/Feature/ExampleTest.php
vite.config.js
```
