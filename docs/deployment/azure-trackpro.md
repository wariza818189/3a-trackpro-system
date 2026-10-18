# Azure TrackPro demo — Phase 1 preparation

Current release ordering: [controlled initial demo release](trackpro-release.md).
That checkpoint accepts the completed database rehearsals and explicitly defers
live backup/restore; older pending-check lists below are historical.

Prepared 2026-10-18 on `deploy/azure-trackpro-demo`. Current hardening baseline:
`b7af0cd4f64a9440fd7d81218df8c5e263c0df89`; original preparation baseline `fb40de4`.
This is a deployment design and local preparation, not deployment authorization.
No Azure resource or persistent project database was accessed by this hardening task. CES Portal must remain untouched.
The current record below supersedes older candidate/unselected runtime statements.
Phase 1 / 1B validation records at the end are historical.


## Fail-closed startup correction — 2026-10-18

**PASS locally; BLOCKED for Azure acceptance.** Existing uncommitted Checkpoint 21
changes were preserved on `deploy/azure-trackpro-demo`, committed HEAD
`b7af0cd4f64a9440fd7d81218df8c5e263c0df89`. This task changes only startup.sh,
the deployment regression tests and this runbook. Checkpoint 21 nginx.conf,
preflight.sh and build.sh were hash-verified unchanged; all worker, storage,
database and Nginx provisioning below the wrapper check was compared byte-for-byte
and preserved. No reset, stage, commit, push, Azure mutation or deployment occurred.

The semicolon contract from Checkpoint 21 is rejected. The exact approved command
is `bash /home/site/wwwroot/deploy/azure/startup.sh && exec php-fpm`. Cleanup is
installed before identity validation and stops Nginx where possible only on the
exact actual TrackPro site. CES/other/unknown identities fail without hook service
actions. The hook never launches FPM. A separate TrackPro-only Azure ingress
restriction is mandatory before any application-file deployment because default
Nginx starts before the hook; reload timing cannot fix that exposure.

| Verification | Observed result |
| --- | --- |
| Shell syntax, all three deployment shell scripts | PASS, exit 0 |
| Python startup regressions | PASS, 20 tests including the original 14; final preflight rerun 6.329 seconds |
| Wrapper lifecycle regressions | PASS: one stub FPM launch on success; zero on settings, identity, worker, syntax/reload failure; incorrect and duplicate FPM commands rejected |
| Isolated Laravel preflight | PASS, exit 0: Composer strict validation/platform requirements, Vite build, 558 tests / 6112 assertions / 67.74 seconds, no reported warnings |
| Git whitespace, including untracked test | PASS |
| Real production services / Azure / persistent databases | NOT ACCESSED; wrapper tests use stub Nginx/FPM/identity commands, PHP file probes use temporary local paths, Laravel tests use in-memory SQLite |
| Git after correction | HEAD unchanged; index empty; four tracked modifications and one untracked test file including preserved Checkpoint 21 work |

Preflight log: `/tmp/trackpro-failclosed-preflight.log`; disposable tree:
`/tmp/trackpro-preflight.sLZ5xv`. No builder rerun or migration command was issued.
Remaining Azure checks: generate/inspect the corrected wrapper and actual image
init path, prove no unconditional/additional FPM command is appended, verify
single foreground FPM startup and failure propagation, and separately authorize
and verify the TrackPro-only ingress gate before any artifact upload. Real Nginx
stop/reload, failed-start probes and Checkpoint 21 CIFS/HTTP/TLS acceptance remain
outstanding. An approved command in documentation is not evidence of a changed
Azure setting. Stop for review; deployment and commit remain unauthorized.

## Startup compatibility hardening — 2026-10-18

**Verdict: BLOCKED for cloud acceptance.** Local changes are reviewable preparation,
not deployment permission. Branch and clean index/worktree were verified at exact
`b7af0cd4f64a9440fd7d81218df8c5e263c0df89` before edits. No Azure calls, database
connections, migrations, credentials, commits, pushes or merges are authorized.

### Owner-supplied verified Azure observations

These observations were supplied with this task on 2026-10-18; they were not
independently rechecked from this workspace. No Laravel code is deployed.

| Item | Observation |
| --- | --- |
| Approved / protected apps | `3a-trackpro-demo-2026` / `ces-portal-demo-2026` |
| Plan / runtime | Existing Linux Free F1; PHP 8.4.26 |
| Nginx | Port 8080; `/etc/nginx/sites-available/default`; active include `/etc/nginx/sites-enabled/*` |
| PHP-FPM | `127.0.0.1:9000`; worker `www-data:www-data`, UID/GID 33 |
| Startup shell | root |
| Persistent `/home` | CIFS: `uid=0,forceuid,gid=0,forcegid,file_mode=0777,dir_mode=0777,mfsymlinks` |
| Quota | 1 GiB; includes release, dependencies, runtime files and platform usage |
| Health | TrackPro HTTPS 200; unauthenticated SCM 401; FPM ready; warm-up succeeded; CES separately 200 |

The supplied default generated script changes into `/home/site/wwwroot`, exports
`NGINX_PORT=8080`, starts Nginx for `PHP_ORIGIN=php-fpm`, then runs `php-fpm;`.
This proves the default sequence only; HTTP 200 does not prove Laravel readiness.

### Startup semantics and evidence boundary

Inspected 2026-10-18: Microsoft's [Oryx PHP generator source](https://github.com/microsoft/Oryx/blob/main/src/startupscriptgenerator/src/php/scriptgenerator.go),
`GenerateEntrypointScript` / `getStartupCommand`. It emits Nginx start before the
supplied command. Its default FPM command is used only when that command is empty.
The [official PHP guide](https://learn.microsoft.com/en-us/azure/app-service/configure-language-php?pivots=platform-linux)
describes a custom startup script and site replacement/reload. Neither establishes
the current PHP 8.4 image's command concatenation. The public generator's `main`
branch is not a pinned identification of the live image.

Owner-supplied follow-up observation on 2026-10-18: on PHP 8.4.26, an
`oryx create-script` probe with the semicolon command generated a wrapper that
starts Nginx first, then runs `bash .../startup.sh; php-fpm;`. The previous
string check accepted it. This confirms the dangerous generator output for that
probe: `;` runs FPM even when the hook fails. It does not establish that the
corrected command has been configured or generated by the live app.

The **only approved App Service Startup Command** is now:

```sh
bash /home/site/wwwroot/deploy/azure/startup.sh && exec php-fpm
```

The hook validates `/opt/startup/startup.sh` for this exact terminal command,
rejecting semicolons, missing exec, and additional bare FPM commands. On success,
it returns zero and the wrapper replaces its shell with one foreground FPM
process. On failure, `&&` skips exec and the wrapper returns failure. The hook
never starts, backgrounds or retries FPM. Do not add another FPM invocation.
These are shell semantics tested locally with stubs; actual corrected Azure
wrapper/init behavior remains **BLOCKED** pending separately authorized review.
If the image appends another FPM command or changes the wrapper, reject that
candidate and review the platform contract rather than weakening validation.
An unapproved semicolon wrapper is unsafe even if our hook rejects it.

**Mandatory deployment prerequisite:** Oryx starts default Nginx before our hook.
Application files must **NOT** be deployed while public ingress is unrestricted.
A separately authorized, verified **TrackPro-only Azure ingress restriction** must
be in place before uploading any application artifact. Nginx reload timing,
`&&`, hook error handling and best-effort stop cannot eliminate pre-hook exposure.
Keep the restriction throughout startup/security/failure rehearsals, and lift it
only after a separately approved release passes acceptance. Never apply the gate
to CES or alter the shared plan as a substitute. This task makes no Azure changes.

### Storage and CIFS security contract

Runtime storage stays at `/home/trackpro-storage`, outside the served public root
and ZIP replacement. `wwwroot/storage` must be absent or already the exact
approved symlink; an existing directory/wrong link is preserved and startup fails.
No `public/storage` directory/link is allowed, even a broken link. Runtime-path
symlinks and symlinked ancestors are rejected before creating subdirectories.

The hook creates/checks the root, framework, cache/data, sessions, views, logs,
app/private, app/public and bootstrap/cache directories. It applies best-effort
owner/mode hints (www-data, 0770, nonrecursive), then runs a clean-environment PHP
probe via `runuser --user www-data --group www-data`. The account must be UID/GID
33. The probe creates an exclusive unpredictable temporary file in every path,
locks, writes, renames, reads and removes it without booting Laravel or opening a
DB. An unwritable path fails startup. Existing user files are never recursively
chowned, chmodded, deleted, moved or flushed. Repeated successful starts preserve
sessions, cache and private data. Abrupt termination can leave a tiny probe file;
review it rather than applying a broad cleanup.

CIFS forceuid/forcegid and mode 0777 can ignore or simulate chown/chmod. Successful
mode calls do not establish privacy. This mount cannot guarantee isolation
between Unix users/processes inside the app container; App Service isolation,
restricted operator/SCM access, application authorization, and public-only Nginx
serving provide the boundaries. No chmod 777 command or permission claim is used.
Do not put production secrets, CA private keys, datasets or backups in artifacts.
A CA bundle is public trust material and stays outside public/; App Settings hold
runtime secrets separately. If per-user filesystem confidentiality is required,
this CIFS design is **BLOCKED** pending an independently approved storage design.

Only `SESSION_DRIVER=file`, `CACHE_STORE=file`, `FILESYSTEM_DISK=local`,
`QUEUE_CONNECTION=sync`, `LOG_CHANNEL=stderr` are accepted. Alternate Laravel
base/cache/storage/view/session paths are rejected. Both configured local disk roots
are probed; the public disk is not web-linked. Framework-signed file delivery
is also blocked at `/storage`; no upload/disk feature is introduced. Session,
view/cache persistence and lock semantics under real CIFS/FPM remain **BLOCKED**
until tested through requests, restart and redeployment. Stderr logging avoids
unbounded file logs in normal operation; fallback logs, file sessions/cache,
release payloads and retained builds still need quota/retention monitoring.
Do not store build directories on the 1 GiB share. Persistence is not backup.

### Nginx validation, failure recovery and rollback

The site is an http-context include containing maps and one IPv4 port-8080 server.
IPv6 is not assumed. Only public/ is served, with query-preserving Laravel
front-controller routing and explicit FastCGI parameters to `127.0.0.1:9000`.
Only exact `/index.php` executes; other PHP paths, dotfiles, runtime/implementation
paths and sensitive file extensions are denied. Symlink serving is disabled.
Exact frontend HTTPS normalization is retained; no broad proxy trust is added.
Verify frontend header replacement, Host, secure cookies and proxy-IP throttling.
TLS CA enforcement and PDO's default verification remain unchanged; no
verification-disable option is introduced. Actual MySQL CA/hostname validation
and negative TLS tests are still required under separate DB authorization.

The failure handler is installed before site/root validation. If the actual
`WEBSITE_SITE_NAME` is exactly `3a-trackpro-demo-2026`, any failure (including an
incorrect expected-name setting) attempts to stop Nginx. For CES, another app or
an unknown actual identity, the hook exits nonzero without service/config/storage
actions. That protects CES and prevents FPM under the approved `&&` wrapper;
it cannot safely stop an unapproved app's existing server. The external ingress
prerequisite remains mandatory. The active `sites-enabled/default` must resolve to the verified site
path. Before replacement, a successful backup copy is retained in `/tmp`. The
new site is installed, `nginx -t` runs, and only then reload runs. On syntax or
reload failure, restore the previous file on disk and stop Nginx; do not reload
an old configuration that might serve the repository root. If stop fails, the
script reports BLOCKED; outer restricted ingress is the necessary backstop.
A reload exit code does not prove workers adopted the config: inspect effective
config and HTTP behavior. Nginx is already started by the wrapper before the
hook; this script cannot eliminate that initial exposure window. Never deploy
Laravel while public ingress is unrestricted. A separate TrackPro-only Azure
ingress restriction must precede any artifact upload; reload timing is insufficient.

For an authorized recovery: keep TrackPro ingress restricted, inspect restored
config and startup logs privately, select a prior validated artifact/checksum
with compatible settings and repeat smoke/security/persistence checks before
reopening ingress. A cold restart recreates ephemeral /etc; repair the reviewed
startup candidate rather than relying on manual /etc edits. Preserve the runtime
directory and APP_KEY. Never restore a repository-root default and reopen
traffic. Do not use CES restart, shared-plan changes, database rollback or DNS
changes as recovery. No rollback operation is performed by this task.

### Local validation and remaining acceptance

Run `python3 -B tests/deployment/test_azure_startup.py`, shell syntax checks,
`git diff --check`, then isolated preflight/builder when available. The focused
suite rewrites a temporary hook copy and mocks identities/services; it executes
the actual PHP file-operation probe as the local user. It tests mode-call failure,
idempotence/data preservation, identity/settings guards, symlinks, worker failure,
wrong include target, syntax/reload recovery and static Nginx contracts. Mocked
`nginx -t` is a recovery test, not Nginx syntax validation or UID/CIFS proof.

Cloud acceptance remains **BLOCKED** pending the corrected actual custom-wrapper/init inspection,
FPM settings propagation/extensions, http-level include/effective configuration,
real `nginx -t`/reload/stop tests, failed-start/initial-exposure probes, UID-33 CIFS
and bootstrap writes, locks/persistence/redeploy, HTTPS/Host/sensitive-path tests,
quota/retention, and separately approved MySQL 8.4/TLS/grants/backup rehearsal.
Local results are recorded below after execution; no deployment is authorized.

### Checkpoint 21 hardening execution record — historical

| Check | Observed result on 2026-10-18 |
| --- | --- |
| Initial Git | Clean required branch, exact `b7af0cd4f64a9440fd7d81218df8c5e263c0df89` |
| `bash -n deploy/azure/startup.sh deploy/azure/preflight.sh deploy/azure/build.sh` | PASS, exit 0 |
| Focused startup regressions | PASS, 14 tests; final candidate rerun by preflight, 3.363 seconds |
| Initial preflight | Exit 1 at Vite: EROFS writing `.vite-temp` through source `node_modules` symlink; no application tests reached |
| Preflight isolation correction | Copy `node_modules` into /tmp rather than symlink it; source dependencies remain untouched |
| Corrected isolated preflight | PASS, exit 0; Composer strict validation/platform requirements, Vite 8.2.2 build in 891 ms, 558 tests / 6112 assertions / 56.94 seconds, no reported warnings |
| Release builder | BLOCKED, exit 100: Composer curl error 6 resolving `api.github.com` and `repo.packagist.org`; outer 150-second bound was not reached |
| Archive/checksum acceptance | NOT RUN; production installation failed before ZIP/checksum creation; no validated release |
| Nginx syntax/runtime | NOT RUN: nginx unavailable locally; test service commands are mocked |
| Git whitespace / reviewed-file whitespace | PASS, including the untracked Python test; no local Markdown link destinations in this runbook to validate; external links not exhaustively checked |
| Final Git scope | Four existing files modified, one new deployment-test file; index empty, HEAD unchanged; `build.sh` reviewed and unchanged |

Logs: `/tmp/trackpro-hardening-preflight.log` (initial failure),
`/tmp/trackpro-hardening-preflight-final.log` (pass),
`/tmp/trackpro-hardening-build.log` (dependency failure).
Retained trees: `/tmp/trackpro-preflight.toLNau` (initial),
`/tmp/trackpro-preflight.PvKbhb` (pass), `/tmp/trackpro-build.rgMGeD` (failed).
The failed build tree is not a release or rollback candidate. No alternate
registry, source/developer-vendor substitution, network bypass or retry was used.
Tests used an isolated in-memory SQLite database, never persistent project or
Azure databases. No application feature/business logic, schema, lockfile or
`main` edit; no deployment, Azure mutation, production credential creation,
commit, push or merge. Stop for owner review before any commit.

## Target and architecture

| Component | Intended use |
| --- | --- |
| Resource group | Existing `ces-portal-demo-2026_group` |
| Protected app | Existing `ces-portal-demo-2026`: no changes or restarts |
| Plan | Existing `ASP-cesportaldemo2026group-8251`; owner verifies Linux F1; shared capacity remains to review |
| TrackPro app | Existing `3a-trackpro-demo-2026`; owner-observed PHP 8.4.26; no Laravel deployed |
| MySQL server | Existing `ces-mysql-demo-2026`, reported MySQL 8.4 |
| Protected schema | `ces_portal_demo`: no access, grants, migrations, or restoration changes |
| TrackPro schema | Proposed `trackpro_demo`, pending separate approval |

Browser HTTPS → Azure TLS frontend → built-in Nginx on port 8080 →
`/home/site/wwwroot/public/index.php` → built-in PHP-FPM → MySQL over verified TLS.
Deploy a ZIP containing locked production PHP dependencies and precompiled Vite
assets. Keep Laravel/Blade and synchronous jobs. No Docker, Node server, worker,
Redis, permission package, or changed application logic is needed.

Microsoft documents Nginx configuration replacement at startup, frontend TLS
termination, and persistence under `/home` for the built-in PHP image.
The supplied site config is a candidate for the actual image; its FPM endpoint
and inclusion context require verification. See [PHP configuration](https://learn.microsoft.com/en-us/azure/app-service/configure-language-php).

F1 is a demo constraint: CPU/storage quotas, idle cold starts, and lack of
production guarantees affect reliability. Do not plan around Always On,
deployment slots, automatic App Service backups, or VNet integration on F1.
Confirm current SKU features and regional PHP availability before approval.
See [Linux pricing](https://azure.microsoft.com/en-us/pricing/details/app-service/linux/)
and [App Service limits](https://learn.microsoft.com/en-us/azure/azure-resource-manager/management/azure-subscription-service-limits#app-service-limits).
If the existing plan is Windows, full, or otherwise incompatible, stop and
propose a separately approved plan; do not resize or reconfigure CES resources.
Sharing a plan/server still shares quotas and database capacity; require an
owner capacity review to protect CES even though the apps and schemas differ.

## Repository audit

| Inspected scope | Finding |
| --- | --- |
| `composer.json`, `composer.lock` | PHP `^8.3`; Laravel `^13.17`, locked `v13.30.1`; BCMath explicitly required. Use Composer 2 and the lockfile, never `composer update` for release. |
| Locked PHP requirements | BCMath, ctype, DOM, fileinfo, filter, hash, iconv, JSON, libxml, mbstring, OpenSSL, PCRE, session, tokenizer. Some are core or Composer-polyfilled. Also require PDO + `pdo_mysql` for production; Composer alone does not check the chosen database driver. Verify CLI and FPM, not CLI alone. OPcache recommended; no Redis/GD/Intl requirement found. |
| Development test platform | PHPUnit 12 additionally needs XML/XMLWriter; SQLite/PDO SQLite for ordinary tests. These are build/test dependencies, not a production database choice. |
| `package.json`, lock | Vite 8.2.2, Laravel Vite plugin 3.2.0, Tailwind and its Vite plugin 4.3.3. Node `^20.19.0 || >=22.12.0`; `npm ci` must include devDependencies. Native platform packages must match the build host. No Node runtime after compilation. |
| `.env.example` | Local defaults deliberately stay unchanged; never deploy it as `.env`. File sessions/cache and sync queue already exist. |
| `config/database.php` | MySQL strict mode, utf8mb4 / utf8mb4_unicode_ci, optional `MYSQL_ATTR_SSL_CA`; PHP 8.4 `Pdo\\Mysql` constants. `DB_URL` can override settings; prohibit it for this release. No TLS-disable change needed. |
| `config/session.php` | File sessions; host-only domain by default; HttpOnly true, SameSite lax; explicitly enable secure cookie in Azure settings. |
| `config/filesystems.php` | Private `storage/app/private`, optional public `storage/app/public`. No current application upload use found. Do not expose private storage or create a public storage link without a feature requiring it. |
| `bootstrap/app.php` | `/up` health route; no explicitly trusted Azure proxies. `APP_URL` alone does not make incoming requests secure. |
| `public/index.php` | Conventional Laravel entry point; maintenance pre-render path is relative to `storage/`. A storage symlink preserves this behavior as well as normal Laravel paths. |
| All 19 migration files | MySQL CHECK statements, enums, exact DECIMALs, restrictive FKs, unique keys, nullable history links, JSON. No migration or schema edits made. |
| Existing guidance | README deployment/security notes and Windows demo setup describe public-only serving and historical MySQL 8.0.46 evidence; no existing Azure deployment configuration found. |

`storage/framework/{sessions,views,cache/data}`, `storage/logs`, private/public
disk roots, and `bootstrap/cache` need writable paths. Runtime storage lives at
`/home/trackpro-storage`, outside ZIP replacement, with a `wwwroot/storage`
symlink. Do not package runtime files. Startup now probes every required directory as UID/GID 33. CIFS cannot enforce
Unix owner/mode isolation; see the current security contract above.
`bootstrap/cache` must also pass the FPM-identity write probe.
Confirm file sessions and cache survive an app-only restart and redeployment.
Monitor storage usage and restrict/archive logs; persistence is not backup.

## Preparation files and safe local use

Run from the deployment branch:

```bash
bash deploy/azure/preflight.sh
bash deploy/azure/build.sh
```

`preflight.sh` requires existing locked development `vendor/` and `node_modules/`.
It first runs the focused synthetic startup regression suite. It copies source and PHP dependencies into a new `/tmp/trackpro-preflight.*`
directory, excludes environment files and existing caches, copies Node
dependencies so Vite temporary files never write back through a source link, and runs Composer checks, Vite build, and `php artisan test`.
Its process environment is cleared; a new empty `.env` placeholder avoids
phpdotenv missing-file warnings without importing any local settings. Tests
receive an ephemeral key and forced
in-memory SQLite configuration. It runs only phpunit.xml's Unit/Feature suites,
never `tests/MySql`, `tests/Ft17`, or database verification/fixture scripts.
The Unit fixture-plan tests read helper code and documentation but do not open
a database. The copy is retained for diagnosis; no cleanup command is executed.

`build.sh` uses a separate fresh `/tmp/trackpro-build.*` tree and isolated home.
It installs production Composer dependencies without plugins/scripts, then runs
only Laravel package discovery. It performs `npm ci` with build dependencies
and `npm run build`, and archives an explicit payload to `trackpro.zip` with a
SHA-256 file. Network/package-registry access is required. It does not deploy.
No `.env`, developer credentials, APP_KEY, config cache, runtime storage,
node_modules, or test tree belongs in the ZIP. Composer's production autoload
contains no test namespace; do not use application factories in production.
Build artifacts contain the working source, so inspect branch/diff and approve
the precise source checkpoint separately before deployment. No commit is made
by either script. Generated directories are retained outside the repository.

### Phase 1B packaging repair and release validation

The original ZIP path list omitted `vendor/` even though Composer installed it
in the isolated build tree. This was a confirmed packaging defect: Laravel's
`vendor/autoload.php` and startup's dependency check could never succeed from
that archive. Phase 1's network failure prevented production ZIP creation, so
no incomplete release was deployed. The repaired explicit payload is:

```text
app/ bootstrap/ config/ database/ public/ resources/ routes/ deploy/ vendor/
artisan composer.json composer.lock
```

`vendor/` comes exclusively from the fresh build's
`composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --no-plugins`.
The developer's installed vendor tree is never substituted for this release
installation. Existing developer dependencies are used only in test preflight.
Build source selection excludes environment/credential/backup filenames;
before ZIP creation a path scan rejects symlinks and prohibited filenames to
avoid following external credential targets. This filename policy cannot
detect a secret embedded in ordinary application source: review source before
approval and keep credentials out of source.

After ZIP creation the builder must pass all of these checks, or exit nonzero:

1. `unzip -t` verifies archive integrity, with output retained in
   `archive-integrity.log` alongside the build directory.
2. Required entries include `vendor/autoload.php`, Composer metadata,
   `public/index.php`, `public/build/manifest.json`, Laravel bootstrap/config
   files, and `deploy/azure/startup.sh` / `deploy/azure/nginx.conf`.
3. The archive listing rejects unexpected root paths (including application
   tests and runtime storage), environment/credential/backup files,
   node_modules, unsafe/duplicate paths, public/hot, public/storage, and
   bootstrap caches other than freshly generated package/service discovery.
   Files shipped inside locked production packages remain package contents;
   no development Composer package is permitted.
4. The archived lockfile must byte-match the repository lockfile. Archived
   `vendor/composer/installed.json` must declare `dev=false`; its exact package
   set, versions, source and dist metadata must match `composer.lock`'s
   production `packages`, with every package directory present and no extra
   vendor package tree. This verifies lock consistency, not a substitute for
   registry provenance or an independent security audit.
5. Each compiled file referenced by the Vite manifest must exist in the ZIP.
6. Only after those checks pass is `trackpro.zip.sha256` written; a separate
   `sha256sum --check trackpro.zip.sha256` must pass before the builder prints
   its validated-package message. Package validation is not Azure readiness.

Failed and successful build directories are retained. An archive found after
a failed run is unvalidated and must not be deployed. No checksum exists if
dependency installation or archive validation never reaches checksum creation.
Before later transfer/deployment or rollback, verify the retained checksum
again from its directory and compare it to the independently recorded release
checksum; do not regenerate a checksum to approve a modified archive.

## Proposed TrackPro app settings

Configure only the future TrackPro app through a private operator session.
This table is a specification, not an executable settings import.

| Setting | Value / requirement |
| --- | --- |
| `APP_NAME` | `3A TrackPro` |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_URL` | Exact HTTPS URL copied from the new app's Overview; do not invent its default hostname |
| `APP_KEY` | Independently generated AES-256 key, stored privately; never copy CES/local key |
| `APP_MAINTENANCE_DRIVER` | `file` |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | `en`, `en` |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | `file`, `120` |
| `SESSION_COOKIE` | `trackpro_demo_session` |
| `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE` | `true`, `true`, `lax` |
| `SESSION_DOMAIN` | Omit, keeping a host-only cookie |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK` | `file`, `sync`, `local` |
| `BROADCAST_CONNECTION`, `MAIL_MAILER` | `log`, `log`; review mail logs for sensitive content before enabling email features |
| `LOG_CHANNEL`, `LOG_LEVEL` | `stderr`, `warning`; arrange bounded platform log retention |
| `DB_CONNECTION`, `DB_DATABASE`, `DB_PORT` | `mysql`, exactly `trackpro_demo`, `3306` |
| `DB_HOST` | Verified server FQDN, expected `ces-mysql-demo-2026.mysql.database.azure.com` |
| `DB_USERNAME`, `DB_PASSWORD` | Dedicated runtime account and private secret; no server admin or CES credentials |
| `DB_CHARSET`, `DB_COLLATION` | `utf8mb4`, `utf8mb4_unicode_ci` |
| `MYSQL_ATTR_SSL_CA` | Verified readable CA bundle outside public/, e.g. `/home/trackpro-certs/mysql-ca.pem` |
| `SCM_DO_BUILD_DURING_DEPLOYMENT` | `false` for the prebuilt ZIP strategy |
| `WEBSITE_RUN_FROM_PACKAGE` | Unset: this design needs a writable extracted wwwroot for the storage link and bootstrap cache |
| `DB_URL`, `DB_SOCKET`, `APP_BASE_PATH`, `LARAVEL_STORAGE_PATH`, `APP_CONFIG_CACHE`, `APP_SERVICES_CACHE`, `APP_PACKAGES_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE`, `VIEW_COMPILED_PATH`, `SESSION_FILES` | Unset; avoid alternate database/storage/cache overrides |
| Startup command | `bash /home/site/wwwroot/deploy/azure/startup.sh && exec php-fpm` |
| `TRACKPRO_EXPECTED_SITE_NAME` | Exactly `3a-trackpro-demo-2026`; must match `WEBSITE_SITE_NAME`. Both names must differ from CES Portal, including case variants. Required by the Phase 1B guard. |

Generate the production key through a trusted secret-management process that
writes directly to private storage; never use a command that prints the key in
logs, chat, shell history, or build output. Use restricted App Settings access,
or an existing Key Vault reference only if its identity/permissions are already
approved. Do not introduce a vault or identity silently. Preserve the same key
across releases and keep an encrypted recovery copy separate from artifacts.

## HTTPS, Nginx, startup, and restarts

`nginx.conf` serves only public/, denies dotfiles and every PHP path except
the exact front controller, and forwards missing routes with their query
string. It uses a complete explicit FastCGI parameter list to avoid duplicate
HTTPS/port parameters. Only an exact frontend `X-Forwarded-Proto: https` sets
PHP HTTPS and port 443. Laravel then recognizes a secure request without
changing middleware. Other forwarded headers are not trusted. Client IP
reporting/rate limits may therefore see the proxy address; validate login
throttling under the demo's expected usage before acceptance. If true client
IP trust is required, propose a separately scoped trusted-proxy change.

Require HTTPS-only at the Azure frontend. Verify Azure replaces, rather than
accepts client spoofing of, the consumed protocol header and preserves the
approved Host. Test redirects, form actions, Vite URLs, and Secure/HttpOnly/Lax
cookies. Test forged forwarded host/protocol headers. Do not add wildcard
Laravel proxy trust as a workaround. Do not enable HSTS until the HTTPS host
and redirect behavior have been verified.

Startup checks non-secret prerequisites, creates missing storage subdirectories,
preserves the storage link, installs the site config, validates it, and reloads
Nginx. It never invokes FPM; the reviewed wrapper contract must supply its single FPM
invocation, with live custom-command verification still BLOCKED.
Every container restart must reapply ephemeral `/etc` configuration. No Composer,
npm, key generation, migration, seeding, or application cache flush runs on boot.
Config/route/view caching is deferred; do not ship a developer config cache.

Before exposing Laravel, verify port 8080, FPM listener
`127.0.0.1:9000`, nginx http-level site inclusion, default-site replacement,
startup ordering, exact fail-closed wrapper with one FPM exec, reload permissions,
and propagation of app settings into FPM.
Do not assume a failing custom startup command prevents Azure from continuing
with its default server. Keep public ingress blocked until a failed-start test
and sensitive-path probes prove the repository root cannot be served. If the
SKU cannot provide that gate, stop for a revised deployment plan. Do not create
public phpinfo, diagnostic environment endpoints, or database-error pages.

Use extracted ZIP deployment, not a read-only package mount or an unverified
Oryx temporary document root. Deployment and settings updates can restart only
TrackPro; verify exact resource identity in each future operator action.

### October 2026 Nginx platform change and remaining runtime gates

Microsoft's [October 9, 2026 announcement](https://azure.github.io/AppService/2026/10/09/php-nginx-update.html)
says PHP images are moving Nginx packages from `deb.sury.org` to `nginx.org`.
Standard-channel rollout was scheduled to start October 14 UTC and take several
days across regions. It specifically calls out custom startup commands and
hardcoded paths for review. This is relevant to our replacement/reload script;
the calendar does not establish which image East Asia is actually running.
No release channel, CES configuration, or Azure resource was queried or changed.
Do not use a channel change, alternate config path, custom FPM listener, or
package installation as an unverified workaround. F1 does not provide the
deployment-slot rehearsal suggested by the announcement; use a separately
authorized isolated app/environment instead.

| Reviewed item | Result / live acceptance gate |
| --- | --- |
| Expected site identity | Phase 1B replaces the earlier non-CES-only guard with an exact match against required `TRACKPRO_EXPECTED_SITE_NAME`. The exact approved name is now hardcoded as `3a-trackpro-demo-2026` as well as requiring both settings to match; no broad non-CES default is accepted. This application guard is not Azure authorization. |
| PHP-FPM upstream | Owner supplied `127.0.0.1:9000` and worker UID/GID 33; actual application FPM behavior still requires runtime acceptance. |
| `map` inclusion context | Nginx permits `map` in `http`, not `server` or `location`. Verify the actual include chain; see [Nginx map documentation](https://nginx.org/en/docs/http/ngx_http_map_module.html). Do not paste this file into a server block. |
| Replacement and reload | Microsoft's PHP guide documents replacing `/etc/nginx/sites-available/default` and `service nginx reload`. Confirm that this path is still active and the service command works in the deployed image; validate the effective config and syntax before admitting traffic. No alternate-path fallback is implemented. |
| Startup failure | The guarded error handler attempts to stop Nginx and restores a backed-up site on disk after config failure; the approved `&& exec` wrapper skips FPM on hook failure, but an initial default-server window remains. Restricted ingress and a failed-start probe remain mandatory. |
| Writable storage/session persistence | Verify startup/FPM ownership and write access to persistent storage and bootstrap/cache. Confirm the storage symlink survives both app-only restart and ZIP redeployment, with existing sessions and file-cache behavior checked. Best-effort 0770/www-data hints and actual worker file-operation probes are now added; modes are not an isolation guarantee on CIFS. |
| Sensitive paths | Static review finds public-only root, dotfile denial, and exact front-controller PHP execution. Actual default-site precedence, traversal/encoded requests, extra PHP paths, and sensitive-file probes still need HTTP verification. |
| Trusted HTTPS forwarding | Only exact `X-Forwarded-Proto: https` is normalized to HTTPS/443. Require frontend HTTPS-only and prove protocol-header replacement/spoof resistance; verify cookies, redirects, host handling, and proxy-IP login throttling. No wildcard Laravel proxy trust was introduced. |

No PHP 8.4 runtime compatibility is claimed. Record actual runtime/channel,
Nginx version, active include paths, FPM listener, effective public root and
restart observations in a future authorized verification record.

## MySQL 8.4 and TLS risks

1. Existing production-schema evidence is MySQL 8.0.46. Ordinary SQLite tests
   use partial behavior fixtures and cannot certify 8.4 migrations, CHECKs,
   collation, DECIMAL enforcement, deadlocks, or concurrent stock updates.
   Rehearse the unchanged migrations and guarded concurrency tests against a
   separately authorized, disposable MySQL 8.4 database before live migration.
   No protected local test database is authorized by Phase 1.
2. `mysql_native_password` is disabled by default in 8.4. Use an account with
   modern `caching_sha2_password` and verify the image's PDO/mysqlnd supports it;
   do not change shared server authentication settings to support a legacy
   account. See [MySQL authentication](https://dev.mysql.com/doc/refman/8.4/en/native-pluggable-authentication.html).
3. 8.4 restricts foreign keys referencing nonunique/partial parent keys. The
   inspected migrations reference parent `id` primary keys, so no obvious
   violation was found; full migration execution remains required. Keep
   default restrictive behavior. See [MySQL foreign keys](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html).
4. Preserve strict SQL mode, enforced CHECKs, InnoDB, exact DECIMAL arithmetic,
   and utf8mb4_unicode_ci. Check composite key sizes, nullable unique semantics,
   FK creation order, datetime/session timezone and SQL modes, and schema-wide
   constraint-name uniqueness on the actual version. Do not switch collation
   or bypass checks to make a migration pass. Review timezone handling against
   the existing Asia/Manila business configuration.
5. MySQL DDL can implicitly commit. A failed migration can leave partial tables
   even when Laravel did not record success. Stop and preserve diagnostics;
   neither automatic retries nor `migrate:fresh` are a recovery strategy.

Use the verified server FQDN and `MYSQL_ATTR_SSL_CA`; never disable peer/name
verification. Microsoft currently calls for both DigiCert Global Root G2 and
Microsoft RSA Root CA 2017 in the client trust bundle; verify the current chain
and certificate-rotation guidance at deployment. See [Azure MySQL TLS](https://learn.microsoft.com/en-us/azure/mysql/security/security-tls-how-to-connect).
Confirm TLS 1.2+ and the negotiated cipher from the application connection in a
future authorized rehearsal. A wrong CA and wrong hostname must both fail;
having a CA path alone is not evidence of verification or absence of fallback.
Require secure transport at server/account level without changing CES server
settings. If the existing shared server is insecure, report it for owner action.

Use database-scoped grants on exactly `trackpro_demo`. With SQL grant patterns,
an underscore can be a wildcard: the operator must account for the server's
`partial_revokes` behavior and verify effective grants. Runtime credentials
should have only required DML, normally SELECT/INSERT/UPDATE; review actual
delete requirements before granting DELETE. Use a separate temporary migration
identity with only needed schema-scoped DDL/REFERENCES permissions; no global
privileges, grant option, or CES access. Do not leave migration credentials in
App Settings. Private-only MySQL networking may be incompatible with F1; if
public access is already approved, scope firewall access to verified outbound
addresses. Never enable blanket Azure-wide access as a fallback.

## Future manual deployment ordering — separate authorization required

1. Review this diff and local validation. Verify Azure subscription/resource
   IDs read-only, exact SKU/OS/location, PHP 8.4 availability and extensions,
   capacity, network path, startup behavior, backup retention, and secret access.
   Do not modify the existing app, plan, server parameters, or CES database.
2. Obtain explicit approval for the named TrackPro web app, `trackpro_demo`,
   database identities/grants, narrowly scoped network changes if necessary,
   and a disposable 8.4 rehearsal. None is created by this repository work.
3. Rehearse migrations, constraints, transactions/concurrency, TLS negative
   tests, backup/restore, and runtime DML grants in that isolated environment.
4. Build/review the release ZIP and hash, record source revision, settings
   names, runtime version, storage permissions, and backup checkpoint. Store
   secrets separately. Before any artifact upload, establish and verify the separately
   authorized TrackPro-only Azure ingress restriction; unrestricted ingress blocks deployment.
5. Configure only TrackPro's settings/startup/storage, deploy the artifact,
   and verify Nginx before public traffic. Verify CLI/FPM platform requirements.
   Take a TrackPro-only database backup before any subsequent schema upgrade.
6. With application writes disabled, independently verify `DATABASE()` and
   `CURRENT_USER()` match the approved schema/identity and review pending
   migrations. Only then run a single controlled `php artisan migrate --force`
   using the temporary migration account in a future authorized session.
   Never run migrations on startup, concurrent deploys, or CES. No automatic
   seeds; initial admin creation uses the existing interactive
   `trackpro:create-admin` command only after separate data-write approval.
7. Restore runtime credentials, restart only TrackPro if required, and verify
   `/up`, login, authorization, Vite assets, secure cookies, persistence, and a
   separately approved small transaction. `/up` proves boot, not DB readiness.
   Probe `/.env`, `/composer.json`, `/vendor/autoload.php`, `/storage/logs/laravel.log`,
   `/other.php`, `/index.php/path`, and dotfiles: none may disclose source/data.
   Remove the ingress restriction only after checks pass. Record results.

## Backup and rollback

Retain previous reviewed ZIPs with checksums, Nginx/startup config, runtime
version, non-secret settings inventory, privately escrowed secrets/key, and
encrypted backups of TrackPro private files. Exclude disposable cache/views
from restoration; decide whether sessions should survive or be invalidated.
Arrange a protected TrackPro-only logical backup and prove restoration into a
separately approved isolated schema. Inspect dump metadata to ensure it cannot
select or recreate another database, user, or global/server configuration.

Do not rely on F1 slots or App Service automatic backups. MySQL server point-in-
time recovery creates/restores at server scope; it is not permission to rewind
the shared server or CES. Verify its availability/retention separately. Any
server restore/new recovery resource needs new approval and an isolation plan.

For code-only failure, gate TrackPro traffic, redeploy the previous verified ZIP
and its compatible startup/settings, preserve persistent storage and the key,
restart only TrackPro, then repeat smoke/security checks. ZIP replacement is
not atomic; accept a planned demo maintenance interval rather than claim zero
downtime. If schema changed incompatibly, keep writes stopped and prefer a
reviewed forward fix or a TrackPro-only restore into an isolated target with
explicit approval. Never automatically run migration rollback/down, drop
history, or restore the shared MySQL server in place. First-deployment failure
has no previous app release: keep TrackPro gated and diagnose without CES changes.

Phase 1B adds archive acceptance to rollback: select a prior release that passed
the corrected integrity/content/production-lock validation and recheck its
recorded SHA-256 before use. Do not treat a Phase 1 archive or any failed build
as a rollback candidate. Verify the prior artifact's startup guard/settings
contract (including `TRACKPRO_EXPECTED_SITE_NAME` when applicable) before
restarting only TrackPro. A ZIP checksum does not restore or validate database
schema compatibility. MySQL 8.4 migration, constraint, concurrency, TLS, least-
privilege and isolated restore rehearsals remain prerequisites; Phase 1B does
not authorize any database connection, migration, seeding or restore.

## Production security acceptance checklist

- [ ] New TrackPro identity and target schema verified; CES has no changes.
- [ ] HTTPS-only, approved host, frontend header handling, cookies and login throttling verified.
- [ ] Only public/ served; arbitrary PHP, dotfiles and sensitive paths denied; failed startup cannot expose source.
- [ ] APP_ENV production, debug and PHP error display off; dependencies/extensions checked in FPM.
- [ ] Independent secret key/passwords protected; no environment/config cache/credentials in artifact or logs.
- [ ] CA/name verification and TLS negative tests pass; effective grants exclude CES/global privileges.
- [ ] UID-33 writes and restart/redeploy persistence checked; CIFS lacks per-user permission isolation, and application/web/operator isolation is accepted.
- [ ] MySQL 8.4 rehearsal, controlled migration, least-privilege runtime smoke test, backup/restore rehearsal complete.
- [ ] F1 capacity/cold-start/storage limits accepted; rollback artifact and maintenance procedure ready.

## Phase 1 validation record

Local environment: PHP 8.4.26, Composer 2.10.3, Node 24.21.0, npm 11.19.0.

| Check | Actual result |
| --- | --- |
| Initial branch / baseline / index / worktree | Expected branch, exact `fb40de4f5369aea0274d1059db617c0c87d03dfe`, clean |
| `composer validate --strict` | PASS, `./composer.json is valid` |
| `composer check-platform-reqs` | PASS for installed development dependencies on PHP 8.4.26; not proof of Azure FPM extensions |
| `npm run build` | PASS, Vite 8.2.2, 3 modules; manifest, CSS 36.83 kB, JS 8.58 kB |
| Initial isolated `php artisan test` | Exit 1: 1 failed, 496 warnings, 61 passed, 6107 assertions, 56.86 seconds |
| Warning diagnosis | Fresh copy lacked `.env`; phpdotenv's suppressed missing-file warning was captured by PHPUnit. Preflight now creates an empty placeholder, without copying secrets or changing tests. |
| Initial assertion failure | `ProductSalesReportTest::test_complete_historical_identity_groups_exact_decimal_values`, line 128: whole rendered HTML unexpectedly contained `kg`. Cause not established; no application/test edits made. Retain this result even if a rerun passes. |
| Diagnostic key setup | Two single-test diagnostic attempts omitted a key visible to the PHPUnit child process and failed with MissingAppKeyException. The original/full preflight supplies an ephemeral key correctly; a corrected single-test diagnostic isolated the missing-file warning. These are harness diagnostics, not application defects. |
| Corrected full isolated `php artisan test` | PASS, exit 0: 558 passed, 6112 assertions, no warnings, 57.31 seconds. Initial `kg` assertion failure did not recur; its cause remains unconfirmed. |
| `bash -n` for all three scripts | PASS |
| `git diff --check` and separate new-file whitespace check | PASS; ordinary Git diff excludes these untracked new files, so they were checked separately |
| Production ZIP build | INCOMPLETE: Composer downloads failed with curl error 6, DNS resolution of `api.github.com` blocked. Stopped the build (exit 130); no ZIP produced. No privilege escalation, registry changes, or fallback attempted. |
| `nginx -t`, actual Azure startup/restart | NOT RUN: nginx unavailable locally; requires the verified Azure image |
| Final `git status --short --branch` | Expected deployment branch; only untracked `deploy/` and `docs/deployment/`; index clean, HEAD remains `fb40de4` |

Logs retained outside Git: `/tmp/trackpro-phase1-preflight-final.log`,
`/tmp/trackpro-phase1-build.log`, and
`/tmp/trackpro-phase1-warning-detail2.log`. The first full test result above
preserves its reported evidence; it was not converted into a pass.

Azure image, TLS, network, SKU, restore, and MySQL 8.4 checks remain pending;
none is implied by successful local tests. The next checkpoint is review of
this preparation, completion of the production ZIP build in an approved
network-enabled build environment, and explicit authorization for read-only
Azure verification before provisioning.

Phase 1 verdict: **NEEDS FIX before deployment**. Repository preparation and
safe local checks are complete. Resolve the incomplete production dependency
build, assess the initial transient assertion result, and satisfy the live
verification gates before deployment clearance. No source, schema, tests,
existing workflows, tracker history, or dependency lockfiles were changed.
Nothing was staged, committed, pushed, or opened as a PR.

## Phase 1B validation record — package repair

Verdict: **BLOCKED** on production dependency downloads. The missing-vendor
packaging defect is repaired in source, but no release ZIP is validated or
available for deployment. Phase 1 test evidence above, including the transient
whole-page `kg` assertion failure, remains historical and unchanged.

Changed in Phase 1B: `deploy/azure/build.sh`, `deploy/azure/startup.sh`, and this
runbook. All five original files remain present; `nginx.conf` and `preflight.sh`
were reviewed and preserved without edits. No application source, migration,
existing test, dependency lockfile, or workflow was modified.

| Check | Actual Phase 1B result |
| --- | --- |
| Repository guard | Expected deployment branch and exact baseline `fb40de4f5369aea0274d1059db617c0c87d03dfe`; only the original five untracked deployment files; index clean |
| `bash -n` all deployment shell scripts | PASS |
| Embedded PHP verifier / path-guard syntax | PASS via `php -l` on copies extracted under /tmp |
| Pre-ZIP path policy smoke checks | PASS: ordinary public/index.php path accepted; environment and SQL-backup filenames rejected. No archive or developer secret used. |
| `composer validate --strict` | PASS, through isolated preflight; also passed at start of production build |
| `composer check-platform-reqs` | PASS on local PHP 8.4.26 through isolated preflight, not an Azure FPM check |
| `npm run build` | PASS through preflight: Vite 8.2.2, 3 modules, 735 ms |
| Isolated `php artisan test` | PASS: 558 tests, 6112 assertions, no warnings, 56.44 seconds; preflight exit 0 |
| Production build attempt | BLOCKED: curl error 6 resolving `api.github.com` and `repo.packagist.org`; Composer/build exit 100. A 90-second outer timeout was configured but did not cause the exit. No sandbox bypass, dependency substitution, source fallback, or retry was used. |
| Archive integrity/content/production-lock checks | NOT RUN: installation failed before ZIP creation. New verifier has syntax checks only; its end-to-end behavior remains unverified. |
| Production ZIP / SHA-256 | Neither exists. SHA-256 creation/verification NOT RUN. |
| Git whitespace / untracked-file whitespace | PASS; separate checks cover all five untracked files |
| Azure PHP 8.4 / Nginx runtime checks | NOT RUN; October platform change, upstream/include/reload behavior, identity, failed-start security, permissions/persistence, and forwarded HTTPS remain live blockers |
| MySQL 8.4 checks | NOT RUN; isolated migration/constraint/concurrency, authentication/TLS negative tests, effective grants, and backup/restore rehearsal remain required |

Diagnostics: `/tmp/trackpro-phase1b-build.log`; failed build tree
`/tmp/trackpro-build.RbolEY`; preflight output
`/tmp/trackpro-phase1b-preflight.log`; isolated preflight tree
`/tmp/trackpro-preflight.noLLg9`. No production archive/checksum path can be
reported. The partial build tree must not be deployed or used as rollback input.

Exact next checkpoint: review this repair, then run
`bash deploy/azure/build.sh` on this branch in an approved network-enabled build
environment with PHP/Composer/Node/npm and unzip/zip/cmp/sha256sum. Require
successful production install, archive integrity/content/lock checks, and
checksum verification before accepting its reported ZIP path. Any verifier
failure must be diagnosed, not bypassed. Then obtain separate authorization
for read-only Azure image/SKU/network verification and an isolated MySQL 8.4
rehearsal before provisioning, connecting to live databases, or deployment.
No commit, push, PR, Azure action, migration, or seeding was performed.
