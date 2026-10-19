#!/usr/bin/env python3
"""Explicit opt-in Docker rehearsal. Creates unique resources, never existing DBs.
Run from repository root after building trackpro-render-demo:20261019.
No resource is deleted; created containers are stopped in finally.
"""
import base64
import json
from pathlib import Path
import re
import secrets
import subprocess
import tempfile
import time

IMAGE = "trackpro-render-demo:20261019"
ROOT = Path(__file__).resolve().parents[2]
NAME = "trackpro-render-check-" + secrets.token_hex(4)
TMP = Path(tempfile.mkdtemp(prefix=NAME + "-"))
DB = NAME + "-mysql"
WEB = NAME + "-web"
VOLUME = NAME + "-storage"
DBVOLUME = NAME + "-db"
started = []


def run(args, *, data=None, check=True):
    result = subprocess.run(args, input=data, text=True, capture_output=True)
    if check and result.returncode:
        # Commands may contain interactive input/credentials: never echo them.
        raise RuntimeError("Command failed: " + args[0] + "\n" + result.stdout + result.stderr)
    return result


def docker(*args, **kwargs):
    return run(["docker", *args], **kwargs)


def php(code):
    return docker("exec", "--user", "www-data", WEB, "php", "-r", code).stdout.strip()


BOOT = 'require "/app/vendor/autoload.php"; $app=require "/app/bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '


def wait_web():
    for _ in range(90):
        result = docker("exec", WEB, "curl", "-fsS", "-o", "/dev/null", "http://127.0.0.1:18080/up", check=False)
        if result.returncode == 0:
            return
        time.sleep(1)
    raise RuntimeError("Web startup failed; inspect logs for " + WEB)


def wait_mysql():
    # The image's temporary initialization server accepts socket pings before
    # the final TCP server is ready. Require an authenticated TLS TCP query.
    for _ in range(120):
        result = docker("exec", DB, "sh", "-c",
                        'MYSQL_PWD="$MYSQL_PASSWORD" mysql --protocol=TCP --host="$1" --user="$MYSQL_USER" --ssl-mode=VERIFY_IDENTITY --ssl-ca=/etc/secrets/mysql-ca.pem --batch --skip-column-names --execute="SELECT 1"',
                        "sh", DB, check=False)
        if result.returncode == 0 and result.stdout.strip() == "1":
            return
        time.sleep(1)
    raise RuntimeError("MySQL authenticated TLS TCP readiness timeout")


def request(path, *, post=None):
    args = ["exec", WEB, "curl", "-sS", "-H", "X-Forwarded-Proto: https",
            "-H", "Host: localhost", "-b", "/tmp/trackpro-cookies", "-c", "/tmp/trackpro-cookies",
            "-D", "/tmp/trackpro-headers", "-w", "\n%{http_code}"]
    if post:
        for key, value in post.items():
            args += ["--data-urlencode", key + "=" + value]
    result = docker(*args, "http://localhost:18080" + path).stdout
    body, status = result.rsplit("\n", 1)
    return body, int(status)


try:
    run(["bash", str(ROOT / "deploy/render/make-mysql-tls.sh"), DB, str(TMP / "tls")])
    tls = TMP / "tls"
    # Secret files are separate bind mounts, as on Render; no host directory access.
    for name in ("mysql-ca.pem", "mysql-server.pem", "mysql-server.key"):
        (tls / name).chmod(0o644)
    password = secrets.token_urlsafe(32)
    root_password = secrets.token_urlsafe(32)
    env = TMP / "mysql.env"
    env.write_text("MYSQL_DATABASE=trackpro_render_demo\nMYSQL_USER=trackpro_render\nMYSQL_PASSWORD=" + password + "\nMYSQL_ROOT_PASSWORD=" + root_password + "\n")
    env.chmod(0o600)
    docker("network", "create", NAME)
    docker("volume", "create", VOLUME)
    docker("volume", "create", DBVOLUME)
    mounts = []
    for name in ("mysql-ca.pem", "mysql-server.pem", "mysql-server.key"):
        mounts += ["--mount", f"type=bind,src={tls / name},dst=/etc/secrets/{name},readonly"]
    docker("run", "-d", "--name", DB, "--network", NAME, "--network-alias", "wrong-hostname",
           "--memory", "2g", "--env-file", str(env), "--mount", f"type=volume,src={DBVOLUME},dst=/var/lib/mysql",
           *mounts, "mysql:8.4", "mysqld", "--require-secure-transport=ON",
           "--ssl-ca=/etc/secrets/mysql-ca.pem", "--ssl-cert=/etc/secrets/mysql-server.pem",
           "--ssl-key=/etc/secrets/mysql-server.key", "--innodb-buffer-pool-size=128M",
           "--max-connections=30", "--performance-schema=OFF", "--mysqlx=0")
    started.append(DB)
    wait_mysql()
    print("PASS isolated MySQL 8.4 startup with TLS required", flush=True)
    for _ in range(900):
        if docker("image", "inspect", IMAGE, check=False).returncode == 0:
            break
        time.sleep(1)
    else:
        raise RuntimeError("Build the runtime image before rehearsal")
    values = dict(APP_KEY="base64:" + base64.b64encode(secrets.token_bytes(32)).decode(),
                  APP_URL="https://localhost", DB_CONNECTION="mysql", DB_HOST=DB, DB_PORT="3306",
                  DB_DATABASE="trackpro_render_demo", DB_USERNAME="trackpro_render", DB_PASSWORD=password,
                  MYSQL_ATTR_SSL_CA="/etc/secrets/mysql-ca.pem", SESSION_SECURE_COOKIE="true",
                  SESSION_HTTP_ONLY="true", SESSION_SAME_SITE="lax", PORT="18080")
    webenv = TMP / "web.env"
    webenv.write_text("".join(k + "=" + v + "\n" for k, v in values.items()))
    webenv.chmod(0o600)
    docker("run", "-d", "--name", WEB, "--network", NAME, "--memory", "512m",
           "--env-file", str(webenv), "--mount", f"type=volume,src={VOLUME},dst=/app/storage",
           "--mount", f"type=bind,src={tls / 'mysql-ca.pem'},dst=/etc/secrets/mysql-ca.pem,readonly", IMAGE)
    started.append(WEB)
    wait_web()
    assert php(BOOT + 'echo Illuminate\\Support\\Facades\\Schema::hasTable("migrations") ? "unexpected" : "empty";') == "empty"
    print("PASS startup /up on custom PORT; no automatic migrations", flush=True)
    assert php(BOOT + '$rows=Illuminate\\Support\\Facades\\DB::select("SHOW SESSION STATUS WHERE Variable_name = CHAR(83,115,108,95,99,105,112,104,101,114)"); echo $rows[0]->Value;')
    for host, ca in (("wrong-hostname", "/etc/secrets/mysql-ca.pem"), (DB, "/etc/ssl/certs/ca-certificates.crt")):
        code = BOOT + 'config(["database.connections.mysql.host"=>' + json.dumps(host) + ', "database.connections.mysql.options"=>[Pdo\\Mysql::ATTR_SSL_CA=>' + json.dumps(ca) + ',Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT=>true]]); Illuminate\\Support\\Facades\\DB::purge("mysql"); try { Illuminate\\Support\\Facades\\DB::connection()->getPdo(); echo "UNSAFE"; } catch (Throwable $e) {echo "rejected";}'
        assert php(code) == "rejected"
    print("PASS MySQL TLS cipher, wrong hostname rejection, wrong CA rejection", flush=True)
    docker("exec", "--user", "www-data", WEB, "php", "artisan", "migrate", "--force")
    admin_password = secrets.token_urlsafe(24)
    result = docker("exec", "-i", "--user", "www-data", WEB, "php", "artisan", "trackpro:create-admin",
                    data="yes\nRender Test Admin\nrender_test_admin\n" + admin_password + "\n" + admin_password + "\n")
    assert "Active administrator created" in result.stdout
    print("PASS supervised migrations and existing interactive admin command", flush=True)
    body, status = request("/login")
    assert status == 200 and 'https://localhost/login' in body
    headers = docker("exec", WEB, "cat", "/tmp/trackpro-headers").stdout.lower()
    assert "secure" in headers and "httponly" in headers and "samesite=lax" in headers
    token = re.search(r'name="_token" value="([^"]+)"', body).group(1)
    _, status = request("/login", post={"_token": token, "username": "render_test_admin", "password": admin_password})
    assert status == 302
    for path in ("/", "/pos", "/products", "/purchase-orders", "/reports", "/users"):
        assert request(path)[1] == 200, path
    for path in ("/.env", "/composer.json", "/deploy/render/start.sh", "/storage/test", "/other.php"):
        assert request(path)[1] in (403, 404), path
    manifest = json.loads(docker("exec", WEB, "cat", "/app/public/build/manifest.json").stdout)
    asset = next(iter(manifest.values()))["file"]
    assert request("/build/" + asset)[1] == 200
    php(BOOT + 'file_put_contents(storage_path("app/private/render-rehearsal"), "persisted"); cache()->forever("render-rehearsal", "persisted");')
    cookies = docker("exec", WEB, "cat", "/tmp/trackpro-cookies").stdout
    docker("stop", "--time", "10", WEB)
    assert docker("inspect", "--format", "{{.State.ExitCode}}", WEB).stdout.strip() in ("0", "143")
    WEB = NAME + "-redeploy"
    docker("run", "-d", "--name", WEB, "--network", NAME, "--memory", "512m",
           "--env-file", str(webenv), "--mount", f"type=volume,src={VOLUME},dst=/app/storage",
           "--mount", f"type=bind,src={tls / 'mysql-ca.pem'},dst=/etc/secrets/mysql-ca.pem,readonly", IMAGE)
    started.append(WEB)
    wait_web()
    docker("exec", "-i", WEB, "sh", "-c", "umask 077; cat > /tmp/trackpro-cookies", data=cookies)
    assert request("/")[1] == 200
    assert php(BOOT + 'echo file_get_contents(storage_path("app/private/render-rehearsal")).cache()->get("render-rehearsal");') == "persistedpersisted"
    docker("restart", DB)
    wait_mysql()
    assert request("/")[1] == 200
    print("PASS HTTPS URLs/cookies, admin pages, assets, private paths, session/cache/private file persistence after container replacement and DB persistence after restart", flush=True)
    unsafe = docker("run", "--rm", "--network", "none", "-e", "APP_DEBUG=true", IMAGE, check=False)
    assert unsafe.returncode != 0 and "startup refused" in unsafe.stderr
    print("PASS invalid production configuration fails closed", flush=True)
    print("Local resources retained, stopped: " + NAME, flush=True)
    print("Private test credentials/certificates retained outside repo: " + str(TMP), flush=True)
finally:
    for container in reversed(started):
        docker("stop", "--time", "10", container, check=False)
