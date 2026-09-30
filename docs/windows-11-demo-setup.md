# 3A TrackPro — Windows 11 Presentation Laptop Handoff Guide

## 1. Quick Overview

Prepare the laptop several days before presentation day. A working presentation
copy needs the TrackPro repository, PHP/Laravel dependencies, Node/npm and built
frontend assets, MySQL, a private presentation database backup, private demo
credentials, a local browser, the presentation PPTX, and fallback screenshots.

**GitHub contains project files. It does not contain the prepared database or
private credentials.** Receive those separately through a private team-controlled
transfer. A clone, ZIP download, or empty database does not recreate the demo.

This guide uses **PowerShell** for Windows commands. Open Start, search for
PowerShell, and open a normal window. Blocks labelled **MySQL client** are entered
only at the `mysql>` prompt. Blocks labelled **.env file** are configuration text,
not commands. Replace every `<PLACEHOLDER>` before using an example.

Use **Run as administrator** only for installation, approved PATH changes, or
starting a Windows service. Close that elevated window afterward. Routine
TrackPro startup does not require administrator privileges. Do not paste Linux
shell syntax into PowerShell.

Follow the sections in order. Stop at any unexpected error; do not guess a
password, replace a database, or update dependencies to bypass a failure.

## 2. Presentation Baseline

| Item | Approved presentation value |
| --- | --- |
| Repository | <https://github.com/wariza818189/3a-trackpro-system> |
| Branch | `main` |
| Documentation baseline inspected for this guide | `350dfc64b63c5b3f3eded02c85b25a01aa82e1f1` |
| Presentation database | `trackpro_demo` |
| Server / browser URL | `http://127.0.0.1:8016` |
| Runtime values | `DB_DATABASE=trackpro_demo`, `SESSION_COOKIE=trackpro_demo_session`, `APP_URL=http://127.0.0.1:8016`, `APP_DEBUG=false` |
| Presentation deck | `docs/presentation/TrackPro_Final_Presentation.pptx` |
| Screenshot fallback | `docs/images/user-guide/` |

Confirm the **exact approved final Git commit with the team before downloading
or copying the laptop**. This guide's documentation commit, and later approved
documentation commits, may follow the inspected baseline above. Do not force a
checkout, reset, rebase, or force-push to make a revision match. An unexpected
revision must be resolved before setup continues.

The application is feature-frozen. Laptop setup and a read-only smoke check do
not establish that full group rehearsal or final presentation has occurred.

## 3. PRIVATE — DO NOT COMMIT TO GITHUB

Receive these privately before choosing the presentation restore path:

1. A **full schema + migrations + data backup of `trackpro_demo`**, its SHA-256,
   and the application revision with which it was prepared.
2. The dedicated local application MySQL username and password, or an approved
   private credential pair to create for this new laptop.
3. The `demo_admin` application login password.
4. The `demo_staff` application login password.

Use controlled USB storage, direct local transfer, or another private
team-controlled channel. MySQL administrator credentials are also private and
are used only for laptop provisioning. MySQL account credentials and TrackPro
login credentials are different; do not interchange them.

Keep the SQL backup **outside the project folder**, for example in a privately
controlled `C:\TrackProPrivate` folder. It contains password hashes and business
history and remains private even though its login passwords are hashed. Keep
credentials separately in an approved private location; do not place plaintext
passwords or `.env` beside the SQL backup on a shared USB drive.

An emergency private USB copy may contain the SQL backup, its checksum, an
approved repository ZIP, this guide, the PPTX, and screenshots. Control access to
that drive. Remove temporary copies from shared machines only after confirming
an approved backup still exists.

**STOP if the full backup or demo login credentials are unavailable.** Path B
can demonstrate an empty installation, but it cannot substitute for the accepted
prepared presentation dataset. This guide does not require creating a new
backup or accessing the original machine's protected databases.

## 4. Download the Project

### Method A — Git Clone (Recommended)

1. If Git is missing, install [Git for Windows](https://git-scm.com/download/win)
   from its official site. Alternatively use the established WinGet command
   below. Accept only the expected installer/publisher and reopen PowerShell
   after installation.

   **PowerShell — any working folder**

   ```powershell
   winget --version
   winget install --id Git.Git -e --source winget
   git --version
   ```

   If WinGet is unavailable, use the official installer or repair App Installer
   through Microsoft Store using [Microsoft's WinGet guidance](https://learn.microsoft.com/windows/package-manager/winget/).

2. Choose a normal writable folder. The example uses your Documents folder:

   **PowerShell**

   ```powershell
   Set-Location ([Environment]::GetFolderPath('MyDocuments'))
   git clone https://github.com/wariza818189/3a-trackpro-system.git
   Set-Location .\3a-trackpro-system
   git status
   git branch --show-current
   git log -1 --oneline
   git rev-parse HEAD
   ```

3. Expected: branch `main`, a clean worktree, and the team's approved commit.
   Record the full commit in the rehearsal record. If the folder already exists,
   stop and inspect it; do not clone over an existing setup or discard changes.
   Arrange an approved update several days early if the revision differs.

### Method B — GitHub Download ZIP

1. Open the repository URL in §2, select branch **main**, then **Code → Download ZIP**.
2. Right-click the downloaded ZIP and select **Extract All**. Extract into a
   normal writable folder, preferably outside a shared or cloud-synced folder.
3. Open the extracted folder containing `artisan`, `composer.json`, and
   `package.json`. Do not run from the compressed ZIP view in Downloads.
4. Open PowerShell there, or use `Set-Location "C:\path\to\extracted-project"`.
5. Have the team record the main-branch commit associated with that download and
   confirm it matches the approved handoff revision.

ZIP downloads do not contain normal Git metadata. Git installation and Git
checkpoint commands are unnecessary for this method. A ZIP's folder name alone
is not proof of its revision; retain the team's download/checkpoint record.

## 5. Required Software and Installation

### Requirements Established by This Repository

| Component | Requirement / check |
| --- | --- |
| PHP | Use PHP **8.4.1 or newer in a compatible 8.x release**. `composer.json` allows `^8.3`, but current `composer.lock` includes Symfony 8.1 packages requiring `>=8.4.1`. PHP 8.3 is insufficient for this lockfile. Development preflight used PHP 8.4.26. |
| PHP extensions | Project requires BCMath; application uses PDO MySQL. Locked production packages require ctype, DOM, fileinfo, filter, hash, iconv, JSON, libxml, mbstring, OpenSSL, PCRE, session, and tokenizer; PDO is needed for PDO MySQL. Some are built into PHP. Composer checks the installed platform. |
| Composer | Composer 2.x; locked Laravel requires Composer runtime API `^2.2`. Use a current Composer 2 release. |
| Node.js / npm | `package.json` and locked Vite packages require `^20.19.0 || >=22.12.0`. Use Node **24 LTS** as preferred in README, with its bundled npm. Verify the selected executable rather than relying on the installer label. |
| MySQL | MySQL **8.x** with InnoDB. Repository verification used MySQL 8.0.46. Use a team-approved 8.x server installation and confirm backup compatibility; do not silently substitute MariaDB or a newer major version. |
| Git | Required only for Method A. |
| Browser | A modern local browser such as Edge, Chrome, or Firefox. |
| PPTX application | Desktop PowerPoint or an approved locally installed PowerPoint-compatible application; actual fonts and slide rendering must be checked. |

PDO SQLite is for isolated tests, not the normal MySQL presentation setup.
PHP ZIP/cURL or a trusted archive extractor can help Composer downloads; they
are useful installation tooling, not substitutes for required extensions.

### Install PHP / Composer / Node

Preserve the existing recommended Windows route: install
[Laravel Herd Basic for Windows](https://herd.laravel.com/docs/windows/getting-started/installation).
Its official installer provides PHP, Composer, Node.js, and Laravel tooling.
Herd requires administrator privileges during installation. Complete onboarding,
select a compatible PHP version in Herd's PHP controls, then reopen a normal
PowerShell window and verify the actual CLI versions.

**Do not assume Herd Basic provides MySQL Server.** Install it separately.
If Herd's Node version is incompatible, install Node 24 LTS from the
[official Node.js download page](https://nodejs.org/en/download). If Composer is
missing, use the [official Composer Windows installer](https://getcomposer.org/download/)
and select the intended PHP executable. Avoid duplicate conflicting PATH entries.

### Install MySQL Server

1. Use [official MySQL Community downloads](https://dev.mysql.com/downloads/).
   Select a team-approved **8.x** Windows server release, not simply the latest
   major offered. Install the server and command-line client, not only Workbench.
   Use the provided installer/configuration tool for that release.
2. Select a local development configuration, InnoDB, and TCP port `3306`, unless
   a known existing service requires an intentional alternative.
3. Set a strong private administrator password. Configure a Windows service
   and record its actual name; do not assume `MySQL80` or `MySQL`.
4. For a WinGet route, run `winget search MySQL`, inspect the official publisher
   and version, and install only the exact suitable package ID shown. Do not
   guess a server package ID or install a different major unintentionally.

### Verify Tools and Extensions

**PowerShell — any working folder**

```powershell
php --version
composer --version
node --version
npm --version
mysql --version
git --version
php -m
php --ini
```

Skip `git --version` for ZIP-only setup. Expected: compatible versions above and
BCMath / `pdo_mysql` in `php -m`. `php --ini` identifies the CLI configuration if
an extension is missing. Use the trusted PHP/Herd configuration to enable it;
reopen PowerShell after PATH changes. Do not bypass Composer platform checks.

If `mysql` is not recognized, find the installed MySQL **bin** directory, add
that exact directory to PATH, and reopen PowerShell. Until PATH is fixed, use
PowerShell's call operator with its real full path:

```powershell
& "C:\path\to\MySQL\bin\mysql.exe" --version
```

Do not assume an exact versioned installation directory. The same call operator
can invoke later MySQL commands with that full executable path.

## 6. Configure the Local Environment File

All following application commands run from the **project directory** containing
`artisan`. Confirm it before continuing:

**PowerShell — project directory**

```powershell
Get-Location
Test-Path .\artisan
Test-Path .\.env.example
Test-Path .\.env
```

Expected: `artisan` and `.env.example` are present. On a fresh copy, `.env` is
absent. Only then create it:

```powershell
Copy-Item .\.env.example .\.env
notepad .\.env
```

**Never overwrite an existing `.env` or application key.** Stop and inspect any
existing configuration privately. `.env.example` defaults to `trackpro_local`
and debug enabled: change those values before running Artisan or starting the
application. Edit the existing keys rather than creating duplicate entries.

**.env file — configuration text, do not execute in PowerShell**

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8016

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trackpro_demo
DB_USERNAME=<PRIVATE_APP_DB_USER>
DB_PASSWORD="<PRIVATE_APP_DB_PASSWORD>"
DB_URL=
DB_SOCKET=

SESSION_DRIVER=file
SESSION_COOKIE=trackpro_demo_session
SESSION_DOMAIN=null
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Replace the username/password privately with the account provisioned in §8.
Use proper dotenv quoting for the actual password; ask the maintainer privately
if it contains characters needing escaping. Leave `APP_KEY` initially blank;
generate it once after dependencies are installed in §7. Keep the remaining
`.env.example` values unless the approved local setup requires a change.

Remove any stale database URL/socket override and any inherited shell database
settings from another project. To list only inherited variable names without
printing their secret values, then remove database overrides from this window:

**PowerShell — before application commands**

```powershell
Get-ChildItem Env:DB_* | Select-Object Name
Remove-Item -Path Env:DB_CONNECTION,Env:DB_HOST,Env:DB_PORT,Env:DB_DATABASE,Env:DB_USERNAME,Env:DB_PASSWORD,Env:DB_URL,Env:DB_SOCKET -ErrorAction SilentlyContinue
```

This changes only this shell's environment, not `.env` or MySQL. Later sections
set the intended runtime database explicitly. `DB_URL` can override individual
connection fields. Do not copy another machine's `.env`. `.env` stays local and untracked;
never show it on the projector or paste it into slides, screenshots, or chat.

## 7. Install Locked Project Dependencies

After §6 is configured, run these one at a time. Stop if any command fails.

**PowerShell — project directory**

```powershell
composer check-platform-reqs --lock
composer install --no-interaction --prefer-dist
composer check-platform-reqs
npm ci
npm run build
php artisan config:clear
php artisan key:generate
```

Expected: Composer reports platform requirements satisfied, installs the reviewed
`composer.lock` packages into `vendor`, and completes Laravel package discovery.
`npm ci` installs `package-lock.json` versions into `node_modules`; the build
creates `public/build/manifest.json` and bundled CSS/JavaScript. Config clearing
removes any cached configuration; key generation fills the new local `APP_KEY`.
Do not regenerate a key that is already configured.

An optional smaller presentation installation can use
`composer install --no-dev --no-interaction --prefer-dist` instead, paired with
`composer check-platform-reqs --lock --no-dev` before installation and
`composer check-platform-reqs --no-dev` afterward. Development/test tools are
then omitted; the locked runtime still requires PHP 8.4.1 or newer. No automated
test run is part of this laptop handoff.

Do not use `composer update`, `npm update`, or `--ignore-platform-reqs` as repair
shortcuts. The presentation uses built assets; a running Vite development server
is unnecessary. Confirm the expected files:

```powershell
Test-Path .\vendor\autoload.php
Test-Path .\public\build\manifest.json
```

Both should return `True`. If PowerShell blocks `npm.ps1`, use `npm.cmd ci` and
`npm.cmd run build`; do not weaken the machine's execution policy globally.

## 8. Database Setup — Choose Exactly One Path

### Start / Identify MySQL Safely

**PowerShell — any working folder**

```powershell
Get-Service | Where-Object { $_.Name -like '*mysql*' -or $_.DisplayName -like '*mysql*' }
```

Identify the intended local server. If stopped, start that exact service using
an elevated PowerShell window, replacing the placeholder:

```powershell
Start-Service -Name "<MYSQL_SERVICE_NAME>"
```

GUI alternative: Windows+R → `services.msc` → the identified MySQL service →
**Start**. Return to normal PowerShell afterward. Do not interfere with other
services or disable Windows Defender/firewall globally.

### Path A — Restore the Prepared Presentation Database (Recommended)

This path reproduces the stable prepared dataset. It requires the private full
`trackpro_demo` backup from §3. Perform initial provisioning off-projector on the
new laptop, several days early.

1. **Verify the transfer.** Store the trusted backup outside the repository in
   a private folder. Use a simple filename/path without spaces for the MySQL
   `SOURCE` example below; replace its filename with the one actually supplied.

   **PowerShell — any working folder**

   ```powershell
   Get-FileHash -Algorithm SHA256 -LiteralPath "C:\TrackProPrivate\PRIVATE_DEMO_BACKUP.sql"
   ```

   Compare the hash character-for-character with the separately supplied private
   checksum. **STOP on a mismatch**; recopy and verify. Confirm with the backup
   owner that this is a complete, single-database `trackpro_demo` backup matching
   the approved application revision. It must not switch to protected databases,
   create/drop databases, create users/grants, or modify global server settings.
   Have the maintainer review unexpected directives privately before import.

2. **Open an administrator MySQL client.** This is a private provisioning login,
   not the runtime application account. `-p` prompts for the password; never
   append a password to it.

   **PowerShell — any working folder**

   ```powershell
   mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u "<MYSQL_ADMIN_USER>" -p
   ```

3. **Create / inspect the target.** Enter at `mysql>`:

   **MySQL client**

   ```sql
   CREATE DATABASE IF NOT EXISTS trackpro_demo
     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE trackpro_demo;
   SELECT DATABASE();
   SHOW TABLES;
   ```

   Expected: database `trackpro_demo` and **no tables** before this first import.
   If tables already exist, **STOP**: investigate whether the laptop is already
   prepared. Do not import over, drop, or clear an existing database. Do not
   repeatedly import after a partial failure.

4. **Create a dedicated local runtime user.** Use a new, privately agreed
   username and password matching §6. SQL password text is entered only in this
   private provisioning session. If that account already exists, stop and inspect
   its privileges instead of recreating or resetting it.

   **MySQL client**

   ```sql
   CREATE USER '<PRIVATE_APP_DB_USER>'@'127.0.0.1'
     IDENTIFIED BY '<PRIVATE_APP_DB_PASSWORD>';
   SELECT @@GLOBAL.partial_revokes;
   ```

   Give runtime DML privileges only on the presentation schema. Because `_` can
   be a wildcard in database-level grants, choose the statement matching the
   returned setting. With `partial_revokes = 0`, use this escaped form:

   ```sql
   GRANT SELECT, INSERT, UPDATE, DELETE ON `trackpro\_demo`.*
     TO '<PRIVATE_APP_DB_USER>'@'127.0.0.1';
   ```

   With `partial_revokes = 1`, use the literal database name instead:

   ```sql
   GRANT SELECT, INSERT, UPDATE, DELETE ON `trackpro_demo`.*
     TO '<PRIVATE_APP_DB_USER>'@'127.0.0.1';
   ```

   Do not change `partial_revokes`; this is only a read-only settings check.
   Do not grant global privileges, `GRANT OPTION`, or runtime schema-reset rights.
   Check the new account's grants and exit:

   ```sql
   SHOW GRANTS FOR '<PRIVATE_APP_DB_USER>'@'127.0.0.1';
   EXIT;
   ```

   These forms follow [MySQL's database grant rules](https://dev.mysql.com/doc/refman/8.4/en/grant.html).
   Account creation/grants take effect without `FLUSH PRIVILEGES`.

5. **Import once into the confirmed empty target.** Reopen the administrator
   client for the reviewed schema/data import; the restricted runtime user does
   not need schema-creation privileges.

   **PowerShell — any working folder**

   ```powershell
   mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u "<MYSQL_ADMIN_USER>" -p --default-character-set=utf8mb4 trackpro_demo
   ```

   **MySQL client**

   ```sql
   SELECT DATABASE();
   SHOW TABLES;
   ```

   Confirm `trackpro_demo` and still no tables before running the next command.
   A full dump may include table replacement directives, which is why it must
   be reviewed and used only for this initial import into an empty new target.

   ```sql
   SOURCE C:/TrackProPrivate/PRIVATE_DEMO_BACKUP.sql;
   SHOW TABLES;
   SELECT COUNT(*) AS applied_migrations FROM migrations;
   EXIT;
   ```

   `SOURCE` is entered **inside the MySQL client**, not in PowerShell. It avoids
   PowerShell's unsupported CMD-style input redirection. Use forward slashes and
   the actual private filename. This method is documented by
   [MySQL's SQL-file import instructions](https://dev.mysql.com/doc/refman/8.4/en/mysql-batch-commands.html).
   Expected: no import errors, application tables present, and 19 migration rows
   for the current frozen baseline. If any statement fails, stop and preserve
   the error privately; do not use `--force` or reset and retry blindly.

6. **Verify the runtime account without writes.** Exit the administrator session
   before continuing:

   **PowerShell — any working folder**

   ```powershell
   mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u "<PRIVATE_APP_DB_USER>" -p trackpro_demo
   ```

   **MySQL client**

   ```sql
   SELECT DATABASE();
   SELECT COUNT(*) AS applied_migrations FROM migrations;
   EXIT;
   ```

7. Continue to §10. A full restored backup already contains schema, migrations,
   and data. **Do not run fresh migrations or create replacement demo users.**
   Unexpected pending migrations mean stop and compare the Git revision and
   backup before any database change.

### Path B — Fresh Empty Install (Fallback Only)

**THIS PATH DOES NOT RECREATE THE PREPARED PRESENTATION DATASET.** It will not
contain the prepared Plywood/G.I. Pipe states, PO #1/#2 history, damage evidence,
TRX-000001/TRX-000002, or representative reports. The empty seeder does not supply
those records. This path is not the preferred final-presentation path.

Use a separate new database named `trackproempty`; never migrate or provision
an empty-install fallback over the prepared `trackpro_demo` database.

1. Open the administrator client as above. Only if this database and account are
   genuinely new, create the schema and a separate local user:

   **MySQL client**

   ```sql
   CREATE DATABASE trackproempty
     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER '<PRIVATE_FRESH_DB_USER>'@'127.0.0.1'
     IDENTIFIED BY '<PRIVATE_FRESH_DB_PASSWORD>';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
     ON trackproempty.* TO '<PRIVATE_FRESH_DB_USER>'@'127.0.0.1';
   EXIT;
   ```

   Stop if either already exists. The additional DDL privileges are for initial
   migrations on this new schema only, not for presentation recovery.

2. Configure the local `.env` from §6 with `DB_DATABASE=trackproempty` and this
   separate account. In a new normal PowerShell window, explicitly select it:

   **PowerShell — project directory**

   ```powershell
   $env:DB_CONNECTION = "mysql"
   $env:DB_DATABASE = "trackproempty"
   $env:DB_URL = ""
   $env:DB_SOCKET = ""
   php artisan config:clear
   php artisan tinker --execute="dump(config('database.default'), config('database.connections.mysql.database'));"
   ```

   Expected: `mysql`, `trackproempty`. **STOP if any other database is shown.**
   Only for this confirmed new empty installation:

   ```powershell
   php artisan migrate
   php artisan trackpro:create-admin
   ```

   Migrations create the initial schema. The approved bootstrap command displays
   the environment/database and asks for confirmation before creating an active
   Admin. Confirm `trackproempty`, enter a name and username, then a private
   password of at least 12 characters twice. It accepts no password argument.
   Do not use this to replace the imported `demo_admin` or `demo_staff` accounts.

3. Using the administrator client, remove initial migration privileges afterward:

   **MySQL client**

   ```sql
   REVOKE CREATE, ALTER, INDEX, REFERENCES ON trackproempty.*
     FROM '<PRIVATE_FRESH_DB_USER>'@'127.0.0.1';
   EXIT;
   ```

4. An empty-install login uses the Admin just created, not the absent demo
   accounts. Record that the prepared presentation is unavailable. Do not claim
   Path A smoke checks passed. Before a future prepared-demo handoff, return to
   the approved Path A configuration in a **new PowerShell window** and verify
   the database target again; never let a `trackproempty` shell override a demo
   `.env` unnoticed.

## 9. Database Safety — STOP Before Guessing

Protected/preserved project databases include `trackpro_local`, `trackpro_demo`,
`trackpro_test`, `trackpro_ft17_test`, and `trackpro_24e_test`.

**Never use these reset commands against presentation/protected databases:**

```text
FORBIDDEN — DO NOT RUN:
php artisan migrate:fresh
php artisan migrate:refresh
php artisan migrate:reset
php artisan db:wipe
```

Never use uncontrolled `DROP DATABASE` or `TRUNCATE`. Do not import a demo backup
into `trackpro_local`, import test fixtures, delete prepared records, rewrite
stock/history, or restore over a populated demo. Confirm `DB_DATABASE` and the
actual effective connection before every database command. Initial Path A import
and Path B provisioning are separate, intentional setup operations on the new
laptop; neither is a presentation-day reset procedure.

## 10. Verify the Presentation Database Before Starting

For **Path A only**, verify `.env` contains the presentation values and run:

**PowerShell — project directory**

```powershell
$env:DB_CONNECTION = "mysql"
$env:DB_DATABASE = "trackpro_demo"
$env:DB_URL = ""
$env:DB_SOCKET = ""
php artisan config:clear
php artisan tinker --execute="dump(config('database.default'), config('database.connections.mysql.database'));"
php artisan migrate:status
```

Expected: `mysql`, `trackpro_demo`, and all **19 current migrations applied**, no
pending rows, based on the documented frozen preflight. `migrate:status` is
read-only; it does not apply migrations. Any pending/extra mismatch means **STOP**
and reconcile the approved revision and backup. Do not run `migrate` on this
restored copy merely to silence a mismatch.

After login in §12, verify these read-only:

- [ ] Plywood = **2 / 5 sheets**, low and uncovered by an open PO.
- [ ] G.I. Pipe = **4.500 / 5.000 m**, low but covered by open procurement.
- [ ] Parent PO #1 shows partial receiving and damage evidence.
- [ ] Follow-up PO #2 = **7.000 m outstanding** with source lineage.
- [ ] TRX-000001 = **Completed**, **₱500.00**.
- [ ] TRX-000002 = **Voided** with preserved details.
- [ ] Register = **Closed**.
- [ ] Movement History contains Opening Inventory, Stock In, Sale,
      Stock Correction, and Sale Void evidence.
- [ ] Reports contain representative data; choose date filters that include
      the prepared records if a default range excludes them.

Do not edit data to make the checklist match. Record actual results in
[Final Testing & Rehearsal](final-testing-rehearsal.md).

## 11. Start the Presentation Server

With the approved `.env` already configured, these valid PowerShell overrides
make the intended presentation target explicit:

**PowerShell — project directory, normal window**

```powershell
$env:DB_CONNECTION = "mysql"
$env:DB_DATABASE = "trackpro_demo"
$env:DB_URL = ""
$env:DB_SOCKET = ""
$env:SESSION_DRIVER = "file"
$env:CACHE_STORE = "file"
$env:SESSION_COOKIE = "trackpro_demo_session"
$env:APP_URL = "http://127.0.0.1:8016"
$env:APP_DEBUG = "false"
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8016 --tries=1
```

Expected: Laravel reports the server at **http://127.0.0.1:8016**. Open that URL
in the browser. The overrides last for this PowerShell window and child processes;
closing the window removes them. Do not paste Bash `DB_DATABASE=... php ...` or
Bash continuation backslashes into PowerShell.

Keep this server window running. Press **Ctrl+C in that window** to stop that
server when finished. Do not terminate unknown PHP/MySQL processes. During the
presentation, minimize/hide the terminal rather than closing the server window.

## 12. Login and Read-Only Smoke Check

1. Open `http://127.0.0.1:8016`.
2. Privately sign in as **demo_admin** using the separately supplied password.
   Do not save it in a public browser profile.
3. Confirm these Admin pages:

   - [ ] Dashboard loads and shows the Admin role/navigation.
   - [ ] Products/Variants load; whole/fractional stock displays correctly.
   - [ ] Purchase Orders load; prepared parent/follow-up details are visible.
   - [ ] POS loads while register remains Closed; do not submit opening cash.
   - [ ] Sales History and prepared completed/voided details load.
   - [ ] Reports load and display representative records.
   - [ ] Optional User Management/Audit Logs load only if selected for the demo.

4. Sign out normally. Privately sign in as **demo_staff**.
5. Confirm these Staff pages:

   - [ ] Staff Dashboard loads with the appropriate role/navigation.
   - [ ] Stock In page loads without submitting a receipt.
   - [ ] Movement History loads with the prepared movement types.
   - [ ] Admin-only navigation is absent; Reports remain Admin-only.
   - [ ] Sign out works.

6. Complete §10's data checklist, check for missing assets/layout problems, and
   record any issues privately. Restart Windows several days early, repeat
   MySQL/server startup, and repeat the smoke check to prove the setup survives
   a reboot. Retain enough time to repair and recheck installation problems.

Do **not** create sales, voids, POs, receiving records, Stock In, corrections,
account edits, or register changes during this initial check. Navigation is
sufficient. Do not run broad regression suites just to prepare the laptop;
release evidence and rerun decisions are in the rehearsal record.

## 13. Presentation Files Stored Locally

**PowerShell — project directory**

```powershell
Test-Path .\docs\presentation\TrackPro_Final_Presentation.pptx
Test-Path .\docs\images\user-guide
Get-FileHash -Algorithm SHA256 .\docs\presentation\TrackPro_Final_Presentation.pptx
Invoke-Item .\docs\presentation\TrackPro_Final_Presentation.pptx
Invoke-Item .\docs\images\user-guide
```

Both paths should exist. The accepted deck has **14 slides** and SHA-256:
`e7df9d94f052ea4ef53d6f450076c252c005f35caf2518c14aba865c34915a65`.
The screenshot folder contains **18 approved PNGs** for read-only/offline fallback.
If the team's deck changes later, obtain the newly approved artifact/checksum.

Open the deck before presentation day in the actual local PPTX application.
Windows 11 normally provides Edge for local browser checks. If no local app is
associated with `.pptx`, arrange desktop [PowerPoint](https://www.microsoft.com/microsoft-365/powerpoint)
under the team's existing license, or a team-approved compatible application
such as [LibreOffice Impress](https://www.libreoffice.org/download/download-libreoffice/)
from its official vendor. Install it before rehearsal and repeat slide checks.
Check fonts, text wrapping, images, slide rendering, and slideshow controls.
A PowerPoint-compatible application's rendering may differ. Open representative
screenshots locally and ensure the operator can find the needed evidence.
Do not rely on online Office, web links, or internet-hosted files during delivery.
Any exported/local alternate deck must be separately approved; do not assume one
already exists. Laptop verification is a human result, not a claim made by this
guide.

## 14. Normal Startup on Presentation Day

1. Connect the charger and check power/display.
2. Start the identified MySQL service if it is not running.
3. Open a normal PowerShell window in the prepared project folder.
4. Privately confirm the approved revision, local `.env`, and `trackpro_demo`
   target; use §10's effective-target check if uncertain.
5. Start Laravel on **port 8016** using §11.
6. Open **http://127.0.0.1:8016**.
7. Privately check Admin login and required pages.
8. Sign out, privately check Staff login, then return to the opening demo role
   before the audience flow begins.
9. Confirm prepared data, especially Closed register, PO #2, and the two Sales.
10. Open the PPTX locally and confirm slideshow readiness.
11. Open the screenshot fallback folder for quick access.
12. Hide/minimize terminals and close secret files/Developer Tools from audience
    view; leave the server running. Keep credentials private.
13. Begin the approved presentation sequence in the demo run sheet.

Do not perform first setup at school immediately before presenting. Do not run
`git pull`, `composer update`, `npm update`, or reinstall packages on presentation
day unless an intentional approved repair leaves enough time for the full smoke
check afterward.

## 15. Offline Readiness — HUMAN CHECK

Once software, Composer/npm dependencies, built assets, database, PPTX, and
screenshots are present locally, the normal prepared demo is intended to operate
through local PHP/MySQL/browser services. Assets use system fonts. Initial
installation/downloads require internet; ordinary prepared startup should not.

The **actual final laptop must verify this during rehearsal**:

- [ ] Temporarily disconnect/disable internet after startup and preparation.
- [ ] Confirm the local URL and required read-only pages still work.
- [ ] Confirm CSS/JavaScript assets load without the network.
- [ ] Confirm the local PPTX opens and slides render.
- [ ] Confirm local screenshots open.
- [ ] Re-enable networking afterward if desired.

Keep MySQL and Laravel running; internet loss does not require stopping local
services or changing databases. Record actual results in the rehearsal record.

## 16. Common Windows Problems — Symptom → Check → Safe Fix

| Symptom | Check | Safe fix / stop rule |
| --- | --- | --- |
| `php` not recognized | Reopen PowerShell; use `where.exe php` and `php --version` | Complete Herd onboarding; repair/select the trusted PHP installation and PATH. Do not copy random PHP binaries. |
| `composer` not recognized | `where.exe composer`; `composer --version` | Reopen terminal, repair Herd or the official Composer installer, select the intended PHP. |
| `git` not recognized | `where.exe git` | Reopen terminal or repair Git for Windows command-line PATH. ZIP method does not need Git. |
| `node` / `npm` not recognized | `where.exe node`; `where.exe npm`; check versions | Reopen terminal after installing/selecting compatible Node; remove conflicting PATH entries deliberately. |
| `npm.ps1` execution-policy error | Confirm `npm.cmd --version` | Use `npm.cmd ci` / `npm.cmd run build`; do not globally relax execution policy. |
| `mysql` not recognized | Find actual MySQL Server bin folder | Fix PATH or use `& "C:\actual\path\mysql.exe"` as in §5. |
| BCMath/PDO MySQL or other PHP extension missing | `php --ini`; `php -m`; Composer platform errors | Enable it through the active trusted PHP/Herd configuration; reopen terminal. Stop until platform checks pass. |
| Unsupported PHP / Node | Check actual CLI versions and `where.exe` paths | PHP must satisfy the lockfile's 8.4.1 minimum; select compatible Node. Never bypass platform checks or update locks. |
| Composer installation error | Correct project folder, internet, PHP/extensions; first real error | Repeat §7 after fixing the cause; do not use `composer update`. |
| npm/build error | Correct project folder, Node version, internet during install | Use locked `npm ci` then `npm run build`; do not use `npm update`. |
| MySQL connection refused | Intended service running? `DB_HOST`/`DB_PORT` correct? | Start the identified service and retry. Do not disable firewall globally. |
| Access denied for database user | Privately compare local `.env` and account host/grants | Correct the intended local credentials/grants off-projector; do not publish passwords or grant global access. |
| Unknown database `trackpro_demo` | Path A database/import completed? | Stop and provision the new laptop via Path A; do not redirect to `trackpro_local`. |
| Import errors / unexpected migration status | Backup checksum, empty-target guard, Git/backup revision | Stop; preserve the error privately and reconcile with maintainer. Do not reset, reimport blindly, or apply pending migrations casually. |
| APP_KEY missing | `.env` exists, new key still blank? | For this new local copy only, run `php artisan key:generate` once. Never overwrite an existing key as routine recovery. |
| Vite manifest/assets missing | `vendor` and build manifest exist? npm build succeeded? | Run §7's frontend install/build off-projector, then repeat smoke checks. Do not require a Vite dev server. |
| 500 error | Private logs, local key, dependencies, demo DB connection, built assets | Keep `APP_DEBUG=false`; inspect logs privately. Use screenshots during presentation; do not debug live. |
| Session/login problem | Correct role, demo import, private password, `SESSION_COOKIE`? | Sign out normally, verify `trackpro_demo_session`, clear only this local site's cookies if needed. Do not clear all browser data or change roles/passwords as a shortcut. |
| PPTX layout differs | Actual local PPTX application, installed fonts, slide rendering | Correct the viewer/font setup before presentation; use an approved local alternate only if available. Recheck all slides. |

### Port 8016 Already in Use

**PowerShell — any working folder**

```powershell
Get-NetTCPConnection -LocalPort 8016 -State Listen
Get-Process -Id <OWNING_PROCESS_ID>
```

Use the `OwningProcess` value from the first command in the second. No listener
can produce a no-matching-object message. If a listener exists, identify it
before acting. Stop a **known task-owned TrackPro server using Ctrl+C in its
own window**. Do not randomly terminate services. An intentional port change
requires agreement, a matching APP_URL/bookmark, and repeated smoke checks;
8016 remains the approved default.

### Private 500-Error Inspection

After confirming the target settings, `php artisan config:clear` safely removes
stale cached configuration. To inspect the local log off-projector:

**PowerShell — project directory**

```powershell
Get-Content .\storage\logs\laravel.log -Tail 50
```

Logs can contain private details; do not share or screenshot them without review
and redaction. Do not enable debug mode in front of the audience. Windows Firewall
prompts must be handled deliberately for this local loopback setup; never disable
Defender/firewall globally or allow public-network access merely to dismiss a prompt.

## 17. Safe Recovery During Presentation

| Situation | Safe response |
| --- | --- |
| Laravel server stopped | Restart §11 in the prepared folder; reopen the local URL. |
| MySQL stopped | Start only the identified MySQL service, then retry local pages. |
| Browser closed | Reopen the local bookmark and sign in privately if needed. |
| Session expired | Return to Login, sign in privately, continue from the planned step. |
| Wrong role logged in | Sign out normally and log in as the intended role; never change account roles. |
| Page unavailable | Continue with approved local screenshot/read-only evidence and note the issue. |
| Internet unavailable | Continue with local services/deck/screenshots after rehearsed offline checks. |
| Prepared record unexpectedly changed | Preserve current state, use approved screenshots, and record the issue for later investigation. No reset, reimport, history rewrite, or compensating transaction. |

**Do not debug or reset data live in front of the audience.** Recover simply
where safe; otherwise use the screenshot fallback. Never describe a failed
live action as successful. Backup restoration, if genuinely needed later, is a
separately authorized off-presentation operation, not this emergency procedure.

## 18. Copy / Handoff Checklist — Wariza and Receiving Teammate

All checks below are initially uncompleted. Record who prepared/checked the
actual laptop and the results in `docs/final-testing-rehearsal.md`.

### Public / Repository

- [ ] Repository downloaded via clone or ZIP.
- [ ] Correct main branch / exact approved handoff baseline confirmed.
- [ ] Presentation PPTX present.
- [ ] Approved screenshots present.

### Private

- [ ] Full `trackpro_demo` SQL backup received privately.
- [ ] Backup checksum and matching source revision supplied and verified.
- [ ] Application DB credentials received/agreed privately.
- [ ] Demo Admin credential received privately.
- [ ] Demo Staff credential received privately.
- [ ] SQL backup kept outside repository; passwords kept separately.

### Software

- [ ] PHP compatible with lockfile; required extensions available.
- [ ] Composer 2 available.
- [ ] Node/npm compatible with package/lock requirements.
- [ ] MySQL 8.x server/client available; actual service identified.
- [ ] Local browser available.
- [ ] Local PowerPoint-compatible application available.
- [ ] Git available if using clone method.

### Application

- [ ] Locked Composer dependencies installed and platform checks passed.
- [ ] Locked npm dependencies installed.
- [ ] Assets built and manifest present.
- [ ] Local `.env` configured with `trackpro_demo` and debug false.
- [ ] New local APP_KEY configured once.
- [ ] Reviewed full demo DB imported into the initially empty new target.
- [ ] Runtime DB access and read-only migration status verified.
- [ ] Admin login/pages checked privately.
- [ ] Staff login/pages checked privately.
- [ ] Prepared data checked without business mutation.
- [ ] Port 8016/local URL works.
- [ ] Windows restarted and startup/smoke checks repeated afterward.

### Presentation

- [ ] All 14 slides open/render correctly on this actual laptop.
- [ ] Screenshot fallback accessible locally.
- [ ] Local/offline test completed and recorded.
- [ ] Charger/power ready.
- [ ] Credentials kept private.
- [ ] Terminals/secret files hidden from audience; server left running.
- [ ] Laptop operator and private backup/fallback arrangements confirmed.
- [ ] Group rehearsal results recorded separately; readiness not assumed.

## 19. DO NOT DO THIS

> **DO NOT** commit `.env` or SQL backups, publish credentials, restore into
> `trackpro_local`, use `migrate:fresh` or `db:wipe`, delete prepared data, or
> perform test sales/voids/receiving against the final prepared database unless
> intentionally authorized. Never expose passwords on the projector, rely only
> on internet-hosted presentation files, or debug live when screenshot fallback
> is available. Do not use FT15/FT17 fixtures or protected test databases to
> manufacture presentation data, and do not force-push or replace approved Git
> history during laptop preparation.

## 20. Source of Truth and Responsibility

| Document | Responsibility |
| --- | --- |
| [Windows 11 Demo Setup](windows-11-demo-setup.md) | Laptop installation, private initial restore, startup, and safe setup troubleshooting. |
| [Demo Preparation](demo-preparation.md) | Approved demo flow, presenters, teacher-scope map, read-only-first policy, and recovery strategy. |
| [Final Testing & Rehearsal](final-testing-rehearsal.md) | Actual final-machine smoke results, group rehearsal, timing, handoffs, Q&A/fallback practice, and readiness decision. |
| [System User Guide](system-user-guide.md) | Functional user instructions and supported application behavior. |
| [Project Tracker](project-tracker.md) | Authoritative current phase status. |
| [README](../README.md), [Composer requirements](../composer.json), [Composer lockfile](../composer.lock), [frontend requirements](../package.json), [frontend lockfile](../package-lock.json), [.env example](../.env.example) | Repository setup/dependency evidence used by this guide. |

Use this guide to prepare the laptop; use the approved run sheet for the actual
demo sequence. Completing installation does not close Final Testing & Rehearsal
or Final Presentation. Record only checks actually performed on the final machine.
