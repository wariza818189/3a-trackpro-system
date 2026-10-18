"""Local hook regression tests. Services/identity are mocked; no Azure or DB access."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

REPO = Path(__file__).resolve().parents[2]


class StartupTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='trackpro-startup-test-')
        self.addCleanup(self.temp.cleanup)
        self.base = Path(self.temp.name)
        self.root = self.base / 'wwwroot'
        self.data = self.base / 'runtime'
        self.site = self.base / 'default'
        self.enabled = self.base / 'enabled'
        self.wrapper = self.base / 'wrapper.sh'
        self.log = self.base / 'commands.log'
        for name in ['public/build', 'vendor', 'bootstrap/cache', 'deploy/azure']:
            (self.root / name).mkdir(parents=True, exist_ok=True)
        for name in ['public/build/manifest.json', 'vendor/autoload.php']:
            (self.root / name).write_text('synthetic fixture')
        self.config = (REPO / 'deploy/azure/nginx.conf').read_text()
        (self.root / 'deploy/azure/nginx.conf').write_text(self.config)
        self.site.write_text('previous config\n')
        self.enabled.symlink_to(self.site)
        self.approved_command = f'bash {self.root}/deploy/azure/startup.sh && exec php-fpm'
        # Simulate the observed ordering; all services and FPM are local stubs.
        self.wrapper.write_text('#!/bin/sh\nservice nginx start\n' + self.approved_command + '\n')
        self.ca = self.base / 'ca-fixture'
        self.ca.write_text('synthetic readable file; not a certificate')
        script = (REPO / 'deploy/azure/startup.sh').read_text()
        # Rewrite a disposable copy only. Production hook has no test path overrides.
        for old, new in [('/home/site/wwwroot', self.root),
                         ('/home/trackpro-storage', self.data),
                         ('/etc/nginx/sites-available/default', self.site),
                         ('/etc/nginx/sites-enabled/default', self.enabled),
                         ('/opt/startup/startup.sh', self.wrapper)]:
            script = script.replace(old, str(new))
        self.hook = self.root / 'deploy/azure/startup.sh'
        self.hook.write_text(script)
        self.bin = self.base / 'bin'
        self.bin.mkdir()
        mocks = {
            'id': 'if [ "$#" = 1 ]; then echo 0; else echo 33; fi',
            'php-fpm': 'echo fpm-launch >> "$COMMAND_LOG"',
            'chown': 'exit "${MODE_FAIL:-0}"',
            'chmod': 'exit "${MODE_FAIL:-0}"',
            'nginx': 'echo "nginx $*" >> "$COMMAND_LOG"; [ "${TEST_FAIL:-}" != syntax ]',
            'service': 'echo "service $*" >> "$COMMAND_LOG"; '
                       'if [ "$2" = reload ] && [ "${TEST_FAIL:-}" = reload ]; then exit 1; fi',
            # Execute real PHP filesystem probe, but do not pretend local UID is 33.
            'runuser': 'echo worker-probe >> "$COMMAND_LOG"; '
                       '[ "${TEST_FAIL:-}" != worker ] || exit 1; '
                       'shift 5; exec "$@"',
        }
        for name, body in mocks.items():
            path = self.bin / name
            path.write_text('#!/bin/sh\n' + body + '\n')
            path.chmod(0o755)
        self.env = dict(PATH=f'{self.bin}:{os.environ["PATH"]}',
                        COMMAND_LOG=str(self.log),
                        TRACKPRO_EXPECTED_SITE_NAME='3a-trackpro-demo-2026',
                        WEBSITE_SITE_NAME='3a-trackpro-demo-2026', PHP_ORIGIN='php-fpm',
                        APP_ENV='production', APP_DEBUG='false', APP_KEY='synthetic-placeholder',
                        DB_CONNECTION='mysql', DB_DATABASE='trackpro_demo',
                        DB_HOST='ces-mysql-demo-2026.mysql.database.azure.com',
                        DB_PORT='3306', DB_USERNAME='trackpro_runtime',
                        SESSION_SECURE_COOKIE='true', SESSION_HTTP_ONLY='true',
                        SESSION_SAME_SITE='lax',
                        MYSQL_ATTR_SSL_CA=str(self.ca), SESSION_DRIVER='file', CACHE_STORE='file',
                        FILESYSTEM_DISK='local', QUEUE_CONNECTION='sync', LOG_CHANNEL='stderr')

    def run_hook(self, ok=True):
        result = subprocess.run(['bash', str(self.hook)], env=self.env,
                                text=True, capture_output=True)
        self.assertEqual(result.returncode == 0, ok, result.stderr)
        return result

    def run_wrapper(self, ok=True):
        result = subprocess.run(['sh', str(self.wrapper)], env=self.env,
                                text=True, capture_output=True)
        self.assertEqual(result.returncode == 0, ok, result.stderr)
        return result

    def test_wrapper_success_executes_fpm_exactly_once(self):
        self.run_wrapper()
        commands = self.commands().splitlines()
        self.assertEqual(commands[0], 'service nginx start')
        self.assertEqual(commands[-1], 'fpm-launch')
        self.assertEqual(commands.count('fpm-launch'), 1)
        self.assertLess(commands.index('service nginx reload'), commands.index('fpm-launch'))

    def test_wrapper_hook_failure_never_executes_fpm(self):
        self.env['APP_DEBUG'] = 'true'
        self.run_wrapper(False)
        self.assertNotIn('fpm-launch', self.commands())
        self.assertIn('service nginx stop', self.commands())
        self.assertFalse(self.data.exists())

    def test_wrapper_worker_and_nginx_failures_never_execute_fpm(self):
        for failure in ['worker', 'syntax', 'reload']:
            with self.subTest(failure=failure):
                self.log.unlink(missing_ok=True)
                self.env['TEST_FAIL'] = failure
                self.run_wrapper(False)
                self.assertNotIn('fpm-launch', self.commands())
                self.assertIn('service nginx stop', self.commands())
                self.assertEqual(self.site.read_text(), 'previous config\n')

    def test_wrapper_expected_identity_mismatch_stops_trackpro(self):
        self.env['TRACKPRO_EXPECTED_SITE_NAME'] = 'other-app'
        self.run_wrapper(False)
        self.assertNotIn('fpm-launch', self.commands())
        self.assertIn('service nginx stop', self.commands())
        self.assertFalse(self.data.exists())
        self.assertEqual(self.site.read_text(), 'previous config\n')

    def test_wrapper_actual_identity_mismatch_never_touches_other_service(self):
        for site in ['ces-portal-demo-2026', 'CES-PORTAL-DEMO-2026', 'other-app', '']:
            with self.subTest(site=site):
                self.log.unlink(missing_ok=True)
                self.env['WEBSITE_SITE_NAME'] = site
                self.run_wrapper(False)
                # Only the simulated platform starts Nginx; the hook takes no
                # service action on an unknown/protected identity, and no FPM runs.
                self.assertEqual(self.commands(), 'service nginx start\n')
                self.assertFalse(self.data.exists())
                self.assertEqual(self.site.read_text(), 'previous config\n')

    def test_incorrect_wrapper_commands_rejected(self):
        hook = f'bash {self.root}/deploy/azure/startup.sh'
        for command in [f'{hook}; php-fpm;', f'{hook} && php-fpm',
                        f'{hook} && exec php-fpm;', f'{hook} || exec php-fpm',
                        self.approved_command + '; php-fpm;',
                        'php-fpm\n' + self.approved_command,
                        self.approved_command + '\nphp-fpm',
                        self.approved_command + '\n' + self.approved_command]:
            with self.subTest(command=command):
                self.wrapper.write_text(command + '\n')
                self.run_hook(False)
                self.assertFalse(self.data.exists())
                self.assertNotIn('fpm-launch', self.commands())
                self.assertNotIn('service nginx reload', self.commands())
        self.assertIn('service nginx stop', self.commands())

    def commands(self):
        return self.log.read_text() if self.log.exists() else ''

    def test_success_and_repeat(self):
        self.run_hook()
        (self.data / 'app/private/keep').write_text('preserve')
        self.run_hook()
        self.assertEqual((self.data / 'app/private/keep').read_text(), 'preserve')
        self.assertEqual((self.root / 'storage').resolve(), self.data)
        self.assertEqual(self.site.read_text(), self.config)
        self.assertFalse(list(self.data.rglob('.trackpro-probe-*')))
        self.assertEqual(self.commands().count('service nginx reload'), 2)

    def test_cifs_mode_failures_are_not_write_failures(self):
        self.env['MODE_FAIL'] = '1'
        self.run_hook()

    def test_site_guards_have_no_side_effects(self):
        for site in ['ces-portal-demo-2026', 'CES-PORTAL-DEMO-2026', 'other-app', '']:
            with self.subTest(site=site):
                self.env['WEBSITE_SITE_NAME'] = site
                self.env['TRACKPRO_EXPECTED_SITE_NAME'] = site
                self.run_hook(False)
                self.assertFalse(self.data.exists())
                self.assertEqual(self.commands(), '')

    def test_settings_fail_closed(self):
        for key, value in [('APP_ENV', 'local'), ('APP_DEBUG', 'true'), ('APP_KEY', ''),
                           ('DB_CONNECTION', 'sqlite'), ('DB_DATABASE', 'ces_portal_demo'),
                           ('DB_HOST', 'other.mysql.database.azure.com'),
                           ('DB_PORT', '3307'), ('DB_USERNAME', 'trackpro_migrator'),
                           ('SESSION_SECURE_COOKIE', 'false'), ('SESSION_HTTP_ONLY', 'false'),
                           ('SESSION_SAME_SITE', 'none'), ('SESSION_DOMAIN', '.example.com'),
                           ('DB_URL', 'forbidden'), ('MYSQL_ATTR_SSL_CA', ''),
                           ('LARAVEL_STORAGE_PATH', '/tmp/alternate'),
                           ('APP_CONFIG_CACHE', '/tmp/cache'), ('VIEW_COMPILED_PATH', '/tmp/views'),
                           ('SESSION_DRIVER', 'database'), ('CACHE_STORE', 'database'),
                           ('FILESYSTEM_DISK', 'public'), ('LOG_CHANNEL', 'daily')]:
            with self.subTest(key=key):
                original = self.env.get(key)
                self.env[key] = value
                self.run_hook(False)
                self.assertNotIn('service nginx reload', self.commands())
                if original is None:
                    self.env.pop(key)
                else:
                    self.env[key] = original
        self.assertIn('service nginx stop', self.commands())

    def test_unverified_wrapper_is_blocked(self):
        self.wrapper.write_text('php-fpm;\n')
        self.run_hook(False)
        self.assertFalse(self.data.exists())

    def test_existing_storage_directory_preserved(self):
        (self.root / 'storage').mkdir()
        self.run_hook(False)
        self.assertTrue((self.root / 'storage').is_dir())

    def test_wrong_storage_link_preserved(self):
        (self.root / 'storage').symlink_to(self.base / 'wrong')
        self.run_hook(False)
        self.assertEqual(os.readlink(self.root / 'storage'), str(self.base / 'wrong'))

    def test_public_storage_link_rejected(self):
        (self.root / 'public/storage').symlink_to(self.base / 'absent')
        self.run_hook(False)
        self.assertFalse(self.data.exists())

    def test_symlinked_runtime_path_rejected_before_creation(self):
        self.data.mkdir()
        external = self.base / 'external'
        external.mkdir()
        (self.data / 'framework').symlink_to(external)
        self.run_hook(False)
        self.assertEqual(list(external.iterdir()), [])

    def test_worker_failure_stops_before_replacement(self):
        self.env['TEST_FAIL'] = 'worker'
        self.run_hook(False)
        self.assertEqual(self.site.read_text(), 'previous config\n')
        self.assertIn('service nginx stop', self.commands())

    def test_syntax_failure_restores_disk_config_and_stops(self):
        self.env['TEST_FAIL'] = 'syntax'
        self.run_hook(False)
        self.assertEqual(self.site.read_text(), 'previous config\n')
        self.assertNotIn('service nginx reload', self.commands())
        self.assertIn('service nginx stop', self.commands())

    def test_reload_failure_restores_disk_config_and_stops(self):
        self.env['TEST_FAIL'] = 'reload'
        self.run_hook(False)
        self.assertEqual(self.site.read_text(), 'previous config\n')
        self.assertIn('service nginx reload', self.commands())
        self.assertIn('service nginx stop', self.commands())

    def test_wrong_include_target_rejected(self):
        self.enabled.unlink()
        self.enabled.write_text('not a symlink')
        self.run_hook(False)
        self.assertEqual(self.site.read_text(), 'previous config\n')

    def test_nginx_static_contract(self):
        self.assertIn('root /home/site/wwwroot/public;', self.config)
        self.assertIn('location = /index.php', self.config)
        self.assertIn('fastcgi_pass 127.0.0.1:9000;', self.config)
        self.assertIn('try_files $uri $uri/ /index.php?$query_string;', self.config)
        self.assertIn('disable_symlinks on;', self.config)
        self.assertNotIn('listen [::]', self.config)
        self.assertEqual(self.config.count('fastcgi_param HTTPS '), 1)
        self.assertIn('fastcgi_param HTTP_PROXY "";', self.config)


if __name__ == '__main__':
    unittest.main()
