# TrackPro controlled initial demo release

Preparation only, from `deploy/azure-trackpro-demo`, baseline `e9f00d7`.
No commit, push, Azure operation, database operation or public unlock is authorized
by this document. All existing rehearsal files remain in the working tree.

The owner reports MySQL 8.4 rehearsal migrations, workflow constraints, lock-only
concurrency, TLS and isolation accepted. Production `trackpro_demo` is empty;
`trackpro_runtime` has four DML privileges and `trackpro_migrator` nine approved
schema privileges. Live backup/restore is explicitly deferred for this initial
academic demo. Full service-level concurrency races remain unverified. This
record supersedes older pending rehearsal/backup requirements for this release.
Do not start another backup audit as part of deployment preparation.

## Release contents and reproducibility

Run `bash deploy/azure/preflight.sh`, then `bash deploy/azure/build.sh` locally.
Both use disposable `/tmp` copies and sanitized environments. Preflight tests
only SQLite `:memory:`; it does not run `tests/MySql`. The build installs locked
production Composer dependencies without scripts/plugins, performs package
discovery without real settings, and builds locked Vite assets. Registry network
access is required for a fresh build; no database connection is needed.

Only `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`,
`deploy/azure`, production `vendor`, `artisan`, `composer.json` and `composer.lock`
enter the ZIP. Migration/seed/factory PHP definitions are source, not executed
fixtures; no database files or test records are shipped. Inert test connection
names in config remain for compatibility, with no test credentials or test DBs.
`scripts/deployment`, all tests, docs, agent instructions, Git metadata,
node_modules, `.env*`, cached configuration, runtime storage, public/storage,
credential files/TSVs, encrypted backups, keys, SQL dumps and database files do
not enter the package. The builder rejects symlinks and forbidden artifact paths.

The ZIP writer sorts paths and fixes timestamps and permissions. Identical
payload bytes and executable bits produce identical archives under the same
Python/zlib toolchain. This is not a promise that different dependency/build
platform versions generate identical payload bytes. Pin the recorded artifact
SHA-256, source revision and tool versions; do not substitute an older `/tmp` ZIP.
The actual archive is CRC-tested, checked against locked production dependencies,
required files and compiled asset references, and hashed after validation.
Builds contain the current reviewed working tree, so require commit/source
correspondence before deployment. Keep the ZIP and checksum in approved release
storage outside the repository; `/tmp` is temporary, not durable retention.

## Next checkpoint: commit and controlled private deployment

Proceed only after explicit authorization for each mutation below.

1. Review the release diff and confirm the baseline, branch and empty index.
   The required pending application change is `config/database.php` (explicit
   certificate verification). Release fixes are `deploy/azure/build.sh`,
   `deploy/azure/startup.sh`, `deploy/azure/preflight.sh`, the startup and ZIP
   regression tests, and these deployment docs. After approval, stage literally:

   ```bash
   git add -- config/database.php deploy/azure/build.sh deploy/azure/startup.sh \
     deploy/azure/preflight.sh tests/Unit/DatabaseConfigurationTest.php \
     tests/deployment/test_azure_startup.py tests/deployment/test_release_package.py \
     docs/deployment/azure-trackpro.md docs/deployment/trackpro-release.md
   git diff --cached --check
   git diff --cached --stat
   git commit -m "Prepare controlled TrackPro Azure demo release"
   git push origin deploy/azure-trackpro-demo
   ```

   Leave every rehearsal script/test untracked and intact. Do not use `git add .`.
   Rebuild after the reviewed commit; compare packaged source files against that
   commit and record its full hash and final ZIP checksum. These commands have
   not been run by this preparation.

2. In a separately authorized Azure session, verify subscription and exact
   resource IDs for app `3a-trackpro-demo-2026`, resource group
   `ces-portal-demo-2026_group`. Keep main-site default ingress deny; confirm an
   existing authorized private/operator path to both app and SCM. If none exists,
   stop and request only a TrackPro-specific access plan. Do not open public
   traffic to bypass SCM restrictions. Never mutate the shared plan or CES app.

3. Configure only TrackPro with Linux PHP 8.4 and the settings below. Verify CLI
   and FPM required extensions (`composer check-platform-reqs --no-dev` plus
   pdo_mysql), real `www-data` UID/GID 33, port 8080, FPM 127.0.0.1:9000, existing
   approved wrapper, persistent `/home`, and sufficient capacity. The actual
   wrapper must end in exactly the approved startup command with no fallback FPM
   execution. FPM must receive the same settings as CLI. Disable PHP display_errors.

   | Settings | Exact value / private requirement |
   | --- | --- |
   | APP_ENV / APP_DEBUG | production / false |
   | APP_NAME / APP_URL | 3A TrackPro / verified TrackPro HTTPS hostname |
   | APP_KEY | private unique valid base64 AES-256 key (32 decoded bytes), recovered/escrowed separately; preserve across releases |
   | TRACKPRO_EXPECTED_SITE_NAME | 3a-trackpro-demo-2026 |
   | DB_CONNECTION / DB_HOST / DB_PORT | mysql / ces-mysql-demo-2026.mysql.database.azure.com / 3306 |
   | DB_DATABASE / DB_USERNAME | trackpro_demo / trackpro_runtime |
   | DB_PASSWORD | private runtime secret, never supplied as a CLI literal or in this ZIP |
   | MYSQL_ATTR_SSL_CA | verified readable public CA bundle outside public/, e.g. /etc/ssl/certs/ca-certificates.crt if verified in this image |
   | DB_CHARSET / DB_COLLATION | utf8mb4 / utf8mb4_unicode_ci |
   | SESSION_DRIVER / SESSION_COOKIE / SESSION_LIFETIME | file / trackpro_demo_session / 120 |
   | SESSION_SECURE_COOKIE / SESSION_HTTP_ONLY / SESSION_SAME_SITE | true / true / lax |
   | CACHE_STORE / FILESYSTEM_DISK / QUEUE_CONNECTION | file / local / sync |
   | LOG_CHANNEL / LOG_LEVEL | stderr / warning, bounded platform retention |
   | APP_MAINTENANCE_DRIVER / MAIL_MAILER | file / log (no outbound mail credentials) |
   | SCM_DO_BUILD_DURING_DEPLOYMENT | false |

   Leave SESSION_DOMAIN, DB_URL, DB_SOCKET, WEBSITE_RUN_FROM_PACKAGE, alternate
   Laravel storage/base/config/cache paths unset (full override list in the
   existing Azure guide). Do not enable config caching during initial release.
   Startup creates/probes only known writable directories, persists storage at
   `/home/trackpro-storage`, and never installs dependencies, seeds, migrates,
   generates keys, or flushes application caches.

   Approved startup command:

   ```text
   bash /home/site/wwwroot/deploy/azure/startup.sh && exec php-fpm
   ```

4. Verify the recorded local SHA-256, then deploy only the reviewed ZIP using the
   existing authorized SCM path and Microsoft Entra authentication. Future
   command template (replace the absolute path with the recorded release):

   ```bash
   az webapp deploy --resource-group ces-portal-demo-2026_group \
     --name 3a-trackpro-demo-2026 --type zip --src-path /absolute/release/trackpro.zip
   ```

   ZIP extraction is intended; do not set run-from-package. Inspect deployment
   logs, generated wrapper and effective Nginx config; require real `nginx -t`,
   public/ root, startup success and FPM settings. Keep traffic restricted even
   if `/up` responds. No Azure command is executed by the local builder.

5. Separately authorize the first production migrations with application writes
   held off. Keep runtime App Settings on `trackpro_runtime`; use a private
   one-off CLI process with migrator credentials injected securely into that
   process only (no literal password argv/history, no config cache, no saved .env,
   no connection dumps/logs). Before any migration, that context must verify
   DATABASE() = trackpro_demo, CURRENT_USER() = trackpro_migrator@%, exact server
   FQDN, TLS verification/cipher, MySQL 8.4, FK enforcement, strict session mode,
   and the expected empty production schema. Review `php artisan migrate:status` privately (a missing migration repository
   is expected on this empty schema), confirm the 19 reviewed migration files,
   then authorize one:

   ```bash
   php artisan migrate --force
   ```

   Never add `--seed`, `migrate:fresh`, rollback/down, or automatic retries.
   MySQL DDL can partially commit: stop on error, retain private evidence and
   inspect partial state before any repair. Verify all 19 ledger entries, 16
   InnoDB tables, 30 enforced CHECKs, 29 FKs and zero business rows. Close the
   migrator process and remove its temporary secret from that context. Confirm
   runtime identity from FPM. Then separately authorize initial administrator
   provisioning with the existing interactive `php artisan trackpro:create-admin`
   under runtime credentials; no default/demo seed data or hardcoded password.

6. Privately smoke-test HTTPS `/up` (boot only), login/logout, admin/staff
   authorization, assets, CSRF, Secure/HttpOnly/Lax host-only cookies, redirects
   and HTTPS/Host forwarding behavior. Confirm database readiness separately.
   Test denial of `/.env`, `/composer.json`, `/vendor/autoload.php`,
   `/storage/logs/laravel.log`, `/other.php`, `/index.php/path` and dotfiles.
   Verify persistence of sessions/storage through a TrackPro-only restart and
   confirm the same APP_KEY. Use separately approved synthetic business writes
   only; do not invent a deletion route for immutable transaction history.
   Check bounded logs and no displayed errors/secrets. No CES queries are needed.

7. Record acceptance and explicit owner acceptance of deferred backup/restore
   and unverified service-level races for this initial academic demo. Only then
   separately authorize TrackPro public ingress, retain appropriate SCM/operator
   restrictions, verify HTTPS-only and repeat smoke checks from outside.

## Failure and shortest path

Keep ingress restricted on any failure. Code rollback uses a previous compatible,
validated TrackPro ZIP and unchanged APP_KEY/storage; never delete storage or
roll back schema automatically. No previous successful application release exists
for the initial deployment, so retain the gate and diagnose rather than promise
an application rollback. Do not rewind the shared MySQL server or modify CES.

The shortest safe path is: approved release commit → build/hash → configure and
ZIP deploy privately → one controlled migration → interactive initial admin →
private smoke/persistence checks → separately approved public access. Backup
rehearsal is deferred, not silently treated as tested; reconsider recovery before
storing valuable data or performing later schema upgrades.

References: [Azure ZIP deployment](https://learn.microsoft.com/en-us/azure/app-service/deploy-zip)
and [Azure PHP startup](https://learn.microsoft.com/en-us/azure/app-service/configure-language-php).

## Local execution evidence

- Branch and HEAD matched `deploy/azure-trackpro-demo` / `e9f00d7`; index empty.
- Isolated Laravel Unit + Feature suites: **832 passed, 7,056 assertions**;
  SQLite `:memory:` only, including the rehearsal tools' offline tests.
- Startup regressions: **20 passed**; ZIP writer regression: **1 passed**.
- PHP syntax: **140 files passed**; shell syntax and full Pint check passed.
- Composer strict validation/platform checks and locked Vite production build passed.
- Final ZIP: `/tmp/trackpro-build.cPTApq/trackpro.zip`, **8,443,769 bytes**,
  **7,610 entries**, **76 production Composer packages**, **19 migrations**.
- SHA-256: `6dcb6a157c41268b6154cc530bb1a297e96108e836e41efb48e63068b54d526c`.
- Archive CRC, required application files, locked package metadata, compiled assets,
  forbidden artifact checks and current packaged source comparison passed.
- Rebuilding the ZIP from the identical staged payload after changing file mtimes
  produced the identical SHA-256. Different toolchains are not certified identical.
- `git diff --check` and new-file whitespace checks passed.
- Initial sandbox build failed at registry DNS; the authorized network-enabled
  build succeeded. No dependency lockfiles were changed or development vendor reused.
- Logs: `/tmp/trackpro-release-preflight.log`, `/tmp/trackpro-release-pint.log`,
  `/tmp/trackpro-release-build-final.log`; archive evidence:
  `/tmp/trackpro-build.cPTApq/release-verification.json`.
- No Azure/database connection, credential file access, migration, backup/restore,
  CES change, commit, push or public unlock occurred. Runtime cloud acceptance
  remains the next supervised checkpoint, not an offline PASS claim.
