# TrackPro Render demo — October 22, 2026

Prepared from `6d9f6576306e143e3518d37b8b7320d1e08bc49c` on branch
`deploy/render-trackpro-demo`, worktree `/home/whis/Projects/3a-trackpro-render-demo`.
Only Render deployment files are added. No paid resources have been created.

## Dashboard deployment (supervised, after separate authorization)

1. Review these uncommitted files, then obtain authorization to commit/push this
   branch. Render cannot build local unpushed files. Connect the source repository
   in the Render dashboard. Use one workspace and **Singapore** for both services;
   if unavailable, choose the same available region for both.
2. **New → Private Service → Existing Image**: image `docker.io/library/mysql:8.4`,
   name `trackpro-render-mysql`. Choose **1c-2g** (legacy Standard), one instance.
   Add a **5 GB persistent disk at `/var/lib/mysql` before first deploy**.
   Environment: `MYSQL_DATABASE=trackpro_render_demo`,
   `MYSQL_USER=trackpro_render`, privately generated distinct `MYSQL_PASSWORD`
   and `MYSQL_ROOT_PASSWORD`. Do not set `MYSQL_ROOT_HOST`, allow-empty-password,
   or random-root-password. No public web service, public port, or port forwarding.
   Docker Command initially:
   `mysqld --require-secure-transport=ON --innodb-buffer-pool-size=128M --max-connections=30 --performance-schema=OFF --mysqlx=0`.
   Leave pre-deploy blank. The official entrypoint initializes only this new DB/user
   on an empty disk; it never executes Laravel migrations or seeds. Changing these
   environment passwords later does not rotate existing MySQL accounts.
3. Copy MySQL's **Connect → Internal** hostname (without `:3306`). From this worktree:
   `bash deploy/render/make-mysql-tls.sh EXACT_INTERNAL_HOST /tmp/trackpro-render-tls-NEW`.
   Store the CA private key securely offline. In MySQL **Environment → Secret Files**,
   add `mysql-ca.pem`, `mysql-server.pem`, `mysql-server.key` using generated contents.
   Update Docker Command (single line) to:
   `mysqld --require-secure-transport=ON --ssl-ca=/etc/secrets/mysql-ca.pem --ssl-cert=/etc/secrets/mysql-server.pem --ssl-key=/etc/secrets/mysql-server.key --innodb-buffer-pool-size=128M --max-connections=30 --performance-schema=OFF --mysqlx=0`.
   Redeploy and require successful MySQL readiness. Renew this 90-day certificate
   before expiry; regenerate if hostname changes. Never upload the CA private key.
4. **New → Web Service → repository**: branch `deploy/render-trackpro-demo`,
   runtime **Docker**, name `trackpro-render-demo`, region as above,
   **0.5c-512mb** (legacy Starter), one instance. Root Directory blank,
   Docker Build Context Directory `.`, Dockerfile Path `deploy/render/Dockerfile`.
   Docker Command blank (use image CMD). Pre-deploy Command blank.
   **Auto-Deploy Off**, health check path `/up`.
   Attach **1 GB persistent disk at `/app/storage` before first deploy**.
5. Enter all settings from `deploy/render/environment.example` in Environment.
   Set `DB_HOST` to the exact internal hostname, `DB_PASSWORD` to MySQL user's
   password, `APP_URL` to the web service's assigned HTTPS URL. Add secret file
   `mysql-ca.pem` (CA only). Generate APP_KEY privately once, for example in a
   private local terminal: `php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'`.
   Save it directly to Render APP_KEY and a secure backup; never commit it or
   regenerate it on restart. Do not add shared Azure environment groups.
6. Create/deploy the web service. `/up` must return 200. This checks Laravel boot,
   **not** database readiness or schema. Until the supervised setup below completes,
   authentication may fail because tables do not yet exist. Do not share the URL.
7. In the **running web service Shell**, verify the configured target and TLS,
   then run exactly one supervised migration and interactive admin creation:

   ```sh
   cd /app
   php artisan tinker --execute='dump(app()->environment(), config("database.connections.mysql.host"), config("database.connections.mysql.database")); dump(DB::select("SHOW SESSION STATUS LIKE \"Ssl_cipher\""));'
   php artisan migrate:status
   php artisan migrate --force
   php artisan migrate:status
   php artisan trackpro:create-admin
   ```

   The first status command can report a missing migrations table on a fresh DB.
   Proceed only after confirming `production`, the new Render host,
   `trackpro_render_demo`, and a **nonempty Ssl_cipher**. Admin creation prints the
   target, asks confirmation, and hides the password (minimum 12 characters).
   Do not seed, use migrate:fresh, or run a worker/scheduler. Never point these
   commands at an Azure or existing development database. No SQL is run by this guide
   until an operator performs these steps. Use the running service Shell, not a
   separate job that lacks the mounted disk. Commands run as root in the Shell may
   create root-owned view/session files: run Artisan as `runuser -u www-data -- php artisan ...`
   when supported, especially if performing commands that write storage.
8. Verify HTTPS login as Admin; create Staff through the existing Admin UI and
   verify Staff permissions. Exercise POS, inventory, purchase orders, reports
   using clearly marked new demo data. Redeploy once and verify the same login
   session, uploaded private data and DB records survive. Keep auto-deploy disabled
   through the presentation. Record a logical DB backup before further changes.

## Storage, security and compatibility

PHP 8.4 FPM, BCMath and PDO MySQL are built together with mbstring, intl, zip and
OPcache. Production Composer dependencies and Node 22/Vite assets are built in
separate stages. The final image has no Composer, Node, dev dependencies, test
suite, `.env`, Git history or Azure deployment files. The context uses an allowlist;
Render-injected root secret files are excluded too. Never use build args for secrets.
Nginx exposes only `/app/public`, executes only `index.php`, blocks hidden/private
paths and uses Render's HTTPS marker for secure URL generation. FPM listens only
on loopback and executes requests as www-data. Startup uses root for disk ownership
and process startup; Nginx workers and PHP requests run without root.

`/app/storage` persists file sessions, cache and private files. Startup creates missing
subdirectories without clearing existing data. Keep APP_KEY unchanged. No public
storage symlink is created. Configuration cache lives in ephemeral bootstrap/cache
and is rebuilt with runtime settings. Compiled views are regenerated each start.
One instance only: disk-backed services cannot scale horizontally and redeploys
briefly interrupt service. Do not put vendor, code or build assets on the disk.

Render private networking avoids public internet transit; it does not replace
certificate verification. MySQL requires TLS; Laravel retains its existing
`ATTR_SSL_VERIFY_SERVER_CERT=true`, with a supplied CA and a certificate SAN matching
DB_HOST. No verification-disable flag is added. Do not use MySQL's default generated
self-signed certificate as the final verified-hostname configuration.

MySQL 8.4 uses caching_sha2_password, supported by PHP 8.4 mysqlnd. Preserve strict
mode and exact DECIMAL arithmetic; no business logic/configuration changes are made.
The official image is self-managed: maintain logical `mysqldump` backups (including
routines/triggers where applicable) outside the service. Disk snapshots alone are
not a safe MySQL recovery method. Monitor disk usage, memory, and certificate expiry.

## Estimated cost / minimum resources

As checked October 19, 2026: web 0.5 CPU / 512 MB is about **$7/month**;
recommended MySQL 1 CPU / 2 GB is about **$25/month**; disks 1+5 GB at
**$0.25/GB-month** add **$1.50/month**: approximately **$33.50/month** total.
A tuned 512 MB MySQL service could reduce the total to about **$15.50/month**, but
2 GB is the safer presentation choice; do not assume 512 MB reliability from a
short local smoke test. Workspace subscriptions, taxes, bandwidth/build overages
are additional if applicable. Compute is prorated; disks remain billed while
retained. Confirm the dashboard quote before provisioning. No spending is authorized
by this preparation task.

Sources: [pricing](https://render.com/pricing),
[compute plan IDs](https://render.com/docs/compute-plans),
[regions](https://render.com/docs/regions),
[private networking](https://render.com/docs/private-network),
[MySQL](https://render.com/docs/deploy-mysql),
[disks and database backup cautions](https://render.com/docs/disks),
[Docker secrets](https://render.com/docs/docker-secrets),
[MySQL TLS](https://dev.mysql.com/doc/refman/8.4/en/using-encrypted-connections.html).

## Local verification

Build: `docker build -f deploy/render/Dockerfile -t trackpro-render-demo:20261019 .`.
Test image: `docker build --target test -f deploy/render/Dockerfile -t trackpro-render-test:20261019 .`.
Run authentication/configuration tests using only in-memory SQLite:
`docker run --rm --network none trackpro-render-test:20261019`.
The test image creates an ephemeral test-only APP_KEY at runtime.
Run the isolated MySQL/runtime rehearsal with `python3 tests/deployment/test_render_runtime.py`.
It retains its uniquely named resources, stops its containers and never deletes existing resources.
See `render-verification.md` for actual local results and remaining gates.
