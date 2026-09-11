# 3A TrackPro — Windows 11 Demo Laptop Setup Guide

This guide prepares a Windows 11 laptop to run the 3A TrackPro classroom demo.
It is written for beginners and uses Windows Command Prompt (CMD) for most
commands.

There are two different jobs:

1. **One-time preparation several days before the presentation** — install the
   software, clone TrackPro, import the private demo database, and test it.
2. **Presentation-day startup** — start MySQL, run TrackPro, open the browser,
   and sign in.

> **Important:** A Git clone contains the application source code, but it does
> not contain the current demo catalog, users, stocks, or transaction history.
> Those records must be transferred separately in a **private MySQL dump**.

## 1. What You Need

Prepare the following:

- A Windows 11 laptop with enough free disk space.
- Internet access during the **first setup**.
- [Git for Windows](https://git-scm.com/download/win).
- [Laravel Herd Basic for Windows](https://herd.laravel.com/docs/windows/getting-started/installation).
- A PHP version satisfying the project's Composer requirement: PHP `^8.3`
  (PHP 8.3 or a compatible later 8.x version).
- Composer for the locked Laravel/PHP dependencies.
- Node.js satisfying `package.json`: `^20.19.0 || >=22.12.0`.
- npm, supplied with Node.js.
- A separate [MySQL Server Community](https://dev.mysql.com/downloads/)
  installation.
- A modern browser such as Chrome, Edge, or Firefox.
- The private `trackpro-demo.sql` file exported from the original Linux laptop.
- An optional USB drive containing a private backup.

Herd Basic is recommended because its Windows installer conveniently provides
PHP, Composer, Node.js, and Laravel tooling. **Do not assume Herd Basic supplies
MySQL Server.** Use a separate MySQL Community Server installation for this
free/basic setup.

After installing Herd, check its Node version. Use it if it satisfies
`^20.19.0 || >=22.12.0`. If it does not, install a compatible
[Node.js 22 LTS](https://nodejs.org/en/download) version separately and verify
which `node` command Windows uses.

## 2. Do This Before Presentation Day

> **Never attempt the first setup at school immediately before presenting.**

Recommended preparation sequence:

1. Borrow the classmate's laptop several days early.
2. Install Git, Herd Basic, a compatible Node.js/npm, and MySQL Server.
3. Clone TrackPro and install its locked dependencies.
4. Create the private presentation database and application `.env`.
5. Transfer, verify, and import the private demo SQL dump.
6. Test both the assigned Admin and Staff accounts.
7. Perform a mostly read-only smoke test of the important pages.
8. Restart Windows.
9. Start and test TrackPro again after the restart.
10. Prepare a private USB backup and pack the laptop charger.

Leave enough time to solve installation or PATH problems and repeat the smoke
test after any change.

## 3. Open CMD

1. Open the Windows **Start** menu.
2. Type `CMD`.
3. Select **Command Prompt**.

Most commands in this guide should run in a normal CMD window. Use
**Run as administrator** only when an installer, Windows UAC prompt, PATH
change, or Windows service operation requires elevation. Close the
Administrator window after that task; routine TrackPro startup should not need
administrator rights.

Commands marked **CMD** are Windows CMD commands. Do not paste Linux Bash
commands into CMD. Commands marked **MYSQL** are entered only after the
`mysql>` prompt appears. Commands marked **LINUX** run only on the original
Linux development laptop.

## 4. Check WinGet

**CMD**

```cmd
winget --version
```

If Windows says `winget` is not recognized, install or update **App Installer**
through Microsoft Store/Windows Update, following the
[official WinGet guidance](https://learn.microsoft.com/windows/package-manager/winget/).
If WinGet still cannot be used, download each tool from its official installer
page instead.

## 5. Install Git

Use the exact Git for Windows package ID:

**CMD**

```cmd
winget install --id Git.Git -e --source winget
```

Accept only the expected trusted installer and UAC prompt. Close CMD, open a new
CMD window so PATH changes take effect, and verify:

**CMD**

```cmd
git --version
```

## 6. Install Laravel Herd Basic

Use the official [Laravel Herd Windows installer](https://herd.laravel.com/docs/windows/getting-started/installation).
This guide does not hard-code a Herd WinGet package ID.

1. Download the Windows installer from the official Herd site.
2. Run it and approve the expected Windows UAC prompt. Herd requires
   administrator privileges during setup for its helper service.
3. Complete the first-launch/onboarding screens.
4. Close CMD and open a new normal CMD window.
5. Verify the supplied tools:

**CMD**

```cmd
php --version
composer --version
node --version
npm --version
```

PHP must satisfy `^8.3`. Node must satisfy `^20.19.0 || >=22.12.0`.

If Herd's Node version does not satisfy that expression, install a compatible
Node.js 22 LTS release from the official Node.js site. Close and reopen CMD,
then run `node --version` and `npm --version` again. Do not continue while an
unsupported version is still first on PATH.

## 7. Install MySQL Server

For this setup, install MySQL Server Community separately. Herd Basic must not
be treated as the database server.

To discover the package currently offered to that laptop, search first:

**CMD**

```cmd
winget search MySQL
```

Review the names, publishers, sources, and IDs shown. Install the exact official
MySQL Server result shown on that machine; do not guess a package ID.

Alternatively, download the official MySQL MSI from
[MySQL Downloads](https://dev.mysql.com/downloads/) and use MySQL Configurator.
Oracle's current Windows documentation recommends the MSI plus Configurator for
the simplest installation.

During setup:

- Install MySQL Server, not only a graphical client.
- Keep TCP port `3306` unless it is already occupied.
- Create a strong private MySQL root/administrator password.
- Never place that password in Git, this guide, screenshots, or chat.
- Configure MySQL to run as a Windows service so it can start with Windows.
- Record the actual Windows service name privately; do not assume its name.

## 8. Verify Required Tools

Open a new CMD window and run:

**CMD**

```cmd
git --version
php --version
composer --version
node --version
npm --version
mysql --version
```

Required project checks:

- PHP satisfies `^8.3`.
- PHP includes BCMath.
- PHP includes PDO MySQL.
- Composer can install the locked Laravel dependencies.
- Node satisfies `^20.19.0 || >=22.12.0`.
- npm is available for the Vite/Tailwind frontend build.
- MySQL Server and its command-line client are available.

Check the two PHP extensions:

**CMD**

```cmd
php -m | findstr /I "bcmath"
php -m | findstr /I "pdo_mysql"
```

Each command should print the matching extension name. No output means that the
extension is not enabled in the PHP used by this CMD window.

If `mysql` is not recognized, locate the installed MySQL Server **bin** folder.
Use Windows Search, MySQL Configurator, or the installation details; do not
assume an exact version folder. Either add that bin folder to PATH and reopen
CMD, or use the quoted full path to `mysql.exe`, for example:

**CMD**

```cmd
"C:\path\to\MySQL\bin\mysql.exe" --version
```

Replace the example path with the path actually found on the laptop.

## 9. Clone TrackPro

Use the recommended Documents location:

**CMD**

```cmd
cd %USERPROFILE%\Documents
git clone https://github.com/wariza818189/3a-trackpro-system.git
cd 3a-trackpro-system
git checkout main
git pull origin main
git rev-parse HEAD
```

Expected checkpoint when this guide was written:

`d78f15cdf1f09a745afc9c69108789d40428e1ff`

A later approved checkpoint may supersede this value. If the team supplies a
new expected commit, verify that exact approved commit before presentation.
Do not reset, rebase, or force the repository merely to make the value match.

## 10. Install TrackPro Dependencies

From the repository folder:

**CMD**

```cmd
composer install
npm ci
npm run build
```

`composer install` uses `composer.lock`, while `npm ci` uses
`package-lock.json`. They reproduce the project's reviewed dependency versions.

Do **not** substitute `composer update` or `npm update`; those commands can
select newer dependency versions and create an untested presentation setup.

## 11. Create `.env`

Only create `.env` if this new clone does not already have one:

**CMD**

```cmd
copy .env.example .env
php artisan key:generate
notepad .env
```

In Notepad, set the following presentation values. This is configuration text,
not a CMD command:

**CMD — enter these lines in `.env`; do not execute them**

```cmd
APP_URL=http://127.0.0.1:8015

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trackpro_demo
DB_USERNAME=trackpro_demo
DB_PASSWORD=<PRIVATE_LOCAL_PASSWORD>
```

Replace `<PRIVATE_LOCAL_PASSWORD>` privately. Use the same password when the
MySQL account is created in the next section. Never put the actual password in
this guide, Git, screenshots, or shared messages. `.env` must remain untracked.

## 12. Create the Windows Presentation Database

Start the MySQL client. It will prompt for the private root password:

**CMD**

```cmd
mysql -u root -p
```

After the `mysql>` prompt appears, enter:

**MYSQL**

```sql
CREATE DATABASE trackpro_demo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'trackpro_demo'@'localhost'
  IDENTIFIED BY '<PRIVATE_LOCAL_PASSWORD>';

GRANT ALL PRIVILEGES
  ON trackpro_demo.*
  TO 'trackpro_demo'@'localhost';

FLUSH PRIVILEGES;

EXIT;
```

Replace the placeholder privately with the same password placed in `.env`.

If MySQL reports that the database or user already exists, **stop and inspect
the existing setup**. Do not drop, overwrite, or delete it automatically.

## 13. Why Git Clone Alone Is Not Enough

The two required parts are different:

- **Git repository:** Laravel application code, views, migrations, and locked
  dependencies.
- **Private MySQL dump:** the current presentation users, demo catalog, Opening
  Inventory, current stocks, Stock Movements, Stock In history, Sales, and
  receipts.

`database/seeders/DatabaseSeeder.php` currently performs no provisioning; its
`run()` method only states that data provisioning is deferred. Therefore a
fresh clone or seeder does not reproduce the legitimate `trackpro_local` demo
state. The private SQL transfer is necessary.

## 14. Export Demo Database From the Original Linux Laptop

These are future instructions only. **Do not export during this documentation
task.** On the original Linux laptop, first use the approved local database
account and confirm the intended source database is `trackpro_local`.

Run:

**LINUX**

```bash
mysqldump \
  --single-transaction \
  --quick \
  --no-tablespaces \
  -u <LOCAL_DB_USER> \
  -p \
  trackpro_local > trackpro-demo.sql
```

Important safeguards:

- Replace `<LOCAL_DB_USER>` with the approved local username.
- Never put the database password after `-p`; the program prompts privately.
- A consistent dump is read-only and must not mutate `trackpro_local`.
- The SQL contains private application data and password hashes.
- Never commit, email publicly, or upload the dump to public/shared storage.
- Transfer it using a controlled private USB drive or approved private storage.

Create a checksum beside the dump:

**LINUX**

```bash
sha256sum trackpro-demo.sql
```

Save the displayed SHA-256 value in a separate private text file for comparison
on Windows.

## 15. Verify Dump Checksum on Windows

Before import, use the real dump path:

**CMD**

```cmd
certutil -hashfile C:\path\to\trackpro-demo.sql SHA256
```

Compare the Windows SHA-256 character-for-character with the Linux value. Stop
if they differ; recopy the file and verify again. Do not import a damaged or
unverified dump.

## 16. Import Demo Database

Import into the empty `trackpro_demo` database. The client prompts for the
private presentation-database password:

**CMD**

```cmd
mysql -u trackpro_demo -p trackpro_demo < C:\path\to\trackpro-demo.sql
```

Never put the password on the command line. This import restores the current
demo state into the Windows presentation database; it does not recreate that
state from the seeder.

After a successful import, return to the repository folder and inspect the
application state:

**CMD**

```cmd
cd %USERPROFILE%\Documents\3a-trackpro-system
php artisan optimize:clear
php artisan migrate:status
php artisan about
```

> **Do not automatically run migrations** if the imported dump already contains
> the current schema and `migrate:status` shows no legitimate pending migration.
> If any migration is pending unexpectedly, stop and ask the project maintainer
> to reconcile the application checkpoint and dump.

Never run these against the presentation database:

**CMD — forbidden commands; shown only so they can be recognized**

```cmd
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

## 17. Start TrackPro

From a normal CMD window:

**CMD**

```cmd
cd %USERPROFILE%\Documents\3a-trackpro-system
php artisan serve --host=127.0.0.1 --port=8015
```

Keep that CMD window open. Open `http://127.0.0.1:8015` in the browser.

Press `Ctrl+C` in the server CMD window to stop Laravel after the session.

## 18. Login

Never place real login details in this guide.

- **Admin:** use the privately assigned Admin credentials.
- **Staff:** use the privately assigned Staff credentials.

Passwords are case-sensitive. Do not save them in a public browser profile,
shared text file, screenshot, or repository.

## 19. First Windows Smoke Test

Prefer a read-only first smoke test. Check each item:

- [ ] Login page loads.
- [ ] Admin login works.
- [ ] Staff login works.
- [ ] Dashboard loads.
- [ ] Categories load.
- [ ] Products load.
- [ ] Product Variants load.
- [ ] Whole stock shows `8` instead of `8.000`.
- [ ] Fractional stock keeps values such as `7.500 kg`.
- [ ] Opening Inventory pages load for Admin as appropriate.
- [ ] Stock In loads.
- [ ] Stock Correction permissions behave correctly.
- [ ] POS loads.
- [ ] Sales History loads.
- [ ] Reports load for Admin and remain unavailable to Staff.
- [ ] Logout works.

Do not create test Stock In, POS, Opening Inventory, or Stock Correction records
just to complete this first check. Mutating rehearsal steps require a separately
approved demo plan.

## 20. MySQL Service on Windows

Do not assume the service is named `MySQL`, `MySQL80`, or anything else. Search
for the actual installed service:

**CMD**

```cmd
sc query type= service | findstr /I mysql
```

After identifying the exact service name, an Administrator CMD can start it:

**CMD**

```cmd
net start <MYSQL_SERVICE_NAME>
```

Replace the placeholder with the displayed service name. As a GUI fallback,
press `Windows+R`, enter `services.msc`, find the MySQL service, and select
**Start**. MySQL's official Windows documentation recommends running the server
as a Windows service.

## 21. Presentation-Day Quick Start

Keep presentation-day startup simple:

1. Power on the laptop.
2. Confirm the MySQL service is running.
3. Open a normal CMD window.
4. Go to the TrackPro folder and start Laravel.
5. Open the saved browser bookmark.
6. Sign in with the private assigned account.
7. Present.

**CMD**

```cmd
cd %USERPROFILE%\Documents\3a-trackpro-system
php artisan serve --host=127.0.0.1 --port=8015
```

Open `http://127.0.0.1:8015`.

> **Do not run `git pull`, `composer update`, `npm update`, or reinstall
> packages on presentation day** unless it is absolutely necessary and there is
> enough time to repeat the entire smoke test.

## 22. Common Windows Problems

### `php` is not recognized

Close and reopen CMD after installing Herd. Launch Herd once and complete its
onboarding. Use `where php` to see which executable Windows finds. If none is
found, repair Herd/PATH through its official installer rather than copying a
random PHP executable.

**CMD**

```cmd
where php
php --version
```

### `composer` is not recognized

Reopen CMD and verify Herd completed setup. Use `where composer`. If another
Composer installation is selected first, correct PATH deliberately and reopen
CMD.

**CMD**

```cmd
where composer
composer --version
```

### `git` is not recognized

Close and reopen CMD. If it remains missing, rerun the trusted Git for Windows
installer and ensure its command-line PATH option is enabled.

**CMD**

```cmd
where git
git --version
```

### `node` or `npm` is not recognized

Reopen CMD after installing Herd or Node.js. Check both executable locations
and versions:

**CMD**

```cmd
where node
where npm
node --version
npm --version
```

### `mysql` is not recognized

Confirm MySQL Server is installed, find its real bin directory, and add that
directory to PATH or use the full quoted path to `mysql.exe`. Do not assume the
versioned installation folder.

### BCMath is missing

Check which PHP is active with `where php`, then use Herd to select/repair a PHP
installation that includes BCMath. Reopen CMD and confirm:

**CMD**

```cmd
php -m | findstr /I "bcmath"
```

Do not continue if the command prints nothing.

### PDO MySQL is missing

Check the active PHP and enable/use its `pdo_mysql` extension through the
trusted PHP/Herd configuration. Reopen CMD and confirm:

**CMD**

```cmd
php -m | findstr /I "pdo_mysql"
```

### Unsupported PHP version

Run `php --version` and `where php`. Select a Herd PHP version satisfying
`^8.3`, then reopen CMD. Do not bypass Composer's platform check.

### Unsupported Node version

Run `node --version` and `where node`. Install/select Node 22 LTS if necessary.
The project requires `^20.19.0 || >=22.12.0`; reopen CMD after changing PATH.

### Composer install error

Confirm internet access, PHP version, and required extensions. Then run:

**CMD**

```cmd
composer check-platform-reqs
composer install
```

Read the first real error. Do not use `composer update` as a repair shortcut.

### `npm ci` or frontend build error

Confirm the Node version and that commands are running inside the repository:

**CMD**

```cmd
node --version
npm --version
cd %USERPROFILE%\Documents\3a-trackpro-system
npm ci
npm run build
```

Do not replace `npm ci` with `npm update`.

### MySQL connection refused

The MySQL service may be stopped or port `3306` may not match `.env`. Identify
and start the actual service as described in Section 20, then retry. Do not
disable the firewall globally.

### MySQL access denied

Check `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE` privately. Confirm the
database account was created and granted access. Never paste the password into
a screenshot, command history, or support message.

### `APP_KEY` is missing

For this new Windows clone only, confirm `.env` exists and run:

**CMD**

```cmd
php artisan key:generate
```

Do not regenerate the key repeatedly or overwrite another configured system's
`.env`.

### Vite manifest is missing

Build the locked frontend dependencies:

**CMD**

```cmd
cd %USERPROFILE%\Documents\3a-trackpro-system
npm ci
npm run build
```

### Port 8015 is already occupied

Find the listener:

**CMD**

```cmd
netstat -ano | findstr :8015
```

Do not terminate an unknown process. Close the known application using that
port, or choose another unused port, update `APP_URL` to match, and repeat the
smoke test before presentation.

### Laravel shows a 500 error

Check that `.env` exists, `APP_KEY` is set, MySQL is running, the private demo
database was imported, and dependencies were installed. Clear cached local
configuration:

**CMD**

```cmd
php artisan optimize:clear
php artisan about
```

Application logs may contain private details. Do not publish or screenshot them
without reviewing and redacting sensitive information.

### Login fails because the demo database was never imported

A Git clone and empty seeder create no demo users. Stop attempting credentials,
verify the private dump checksum, and follow Sections 12 through 16. Do not
create browser-based replacement accounts or import a preserved test database.

### Windows Firewall asks about the local PHP server

The guide binds Laravel to `127.0.0.1`, which is local to the laptop. Never
disable Windows Defender or the firewall globally. If Windows requests access,
allow only the minimum trusted private-network access needed by the approved
setup; do not allow public-network access merely to dismiss the prompt.

## 23. Emergency USB Backup

Keep private backup copies of:

- `trackpro-demo.sql`.
- A text file containing its SHA-256 checksum.
- This Windows setup guide.
- Optionally, a repository ZIP or archive of the approved checkpoint.

Do **not** place these on the USB drive:

- Plaintext database passwords.
- Plaintext application passwords.
- `.env`.

Treat the SQL dump as private even though the passwords inside it are hashed.
Keep credentials in a separate private location. Physically control the USB
drive and delete temporary copies from shared computers after the presentation
only when an approved backup still exists.

## 24. Never Do This on the Presentation Database

Never run:

**CMD — forbidden commands; shown only so they can be recognized**

```cmd
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

Also never:

- Delete Sales to reset the demo.
- Delete Stock Movements or Restocks.
- Directly rewrite current stock or immutable history.
- Import `trackpro_test`.
- Use FT15 or FT17 fixtures.
- Expose or commit `.env`.
- Commit the SQL dump.
- Commit or share credentials.
- Force-push or replace the approved application checkpoint.

## 25. Pre-Presentation Sign-Off

- [ ] Classmate's Windows 11 laptop is fully prepared.
- [ ] Git, PHP, Composer, Node, npm, and MySQL are verified.
- [ ] PHP BCMath and PDO MySQL are enabled.
- [ ] Correct TrackPro checkpoint is installed.
- [ ] Private demo database is imported.
- [ ] Dump checksum matched before import.
- [ ] Admin login is tested.
- [ ] Staff login is tested.
- [ ] Whole/fractional stock display is verified.
- [ ] Dashboard is tested.
- [ ] POS page is tested without an unauthorized transaction.
- [ ] Sales History is tested.
- [ ] Reports are tested with Admin.
- [ ] Windows has been restarted.
- [ ] TrackPro has been retested after restart.
- [ ] Laptop charger is packed.
- [ ] Private USB backup is packed.
- [ ] Browser bookmark points to `http://127.0.0.1:8015`.
- [ ] Credentials are available privately and separately.
