<?php

declare(strict_types=1);

namespace Tests\Unit;

use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class DatabaseConfigurationTest extends TestCase
{
    #[DataProvider('caProvider')]
    public function test_mysql_explicitly_verifies_server_certificates_and_preserves_ca_support(?string $ca): void
    {
        $this->assertTrue(extension_loaded('pdo_mysql'), 'This TLS regression test requires pdo_mysql.');

        $configuration = $this->configuration($ca === null ? [] : ['MYSQL_ATTR_SSL_CA' => $ca]);
        $options = $configuration['connections']['mysql']['options'];

        $this->assertTrue($options[Mysql::ATTR_SSL_VERIFY_SERVER_CERT] ?? null);

        if ($ca === null || $ca === '') {
            $this->assertArrayNotHasKey(Mysql::ATTR_SSL_CA, $options);
        } else {
            $this->assertSame($ca, $options[Mysql::ATTR_SSL_CA]);
        }
    }

    public static function caProvider(): array
    {
        return [
            'unset CA' => [null],
            'empty CA' => [''],
            'configured CA' => ['/synthetic/mysql-ca.pem'],
        ];
    }

    public function test_database_defaults_and_other_connection_definitions_are_preserved(): void
    {
        $configuration = $this->configuration();
        $connections = $configuration['connections'];

        $this->assertSame('sqlite', $configuration['default']);
        $this->assertSame(
            ['sqlite', 'mysql', 'mysql_testing', 'mysql_ft17_testing', 'mariadb', 'pgsql', 'sqlsrv'],
            array_keys($connections),
        );
        $this->assertSame('/synthetic/database/database.sqlite', $connections['sqlite']['database']);
        $this->assertTrue($connections['sqlite']['foreign_key_constraints']);
        $this->assertSame('DEFERRED', $connections['sqlite']['transaction_mode']);

        foreach (['mysql', 'mysql_testing', 'mysql_ft17_testing', 'mariadb'] as $name) {
            $this->assertSame('utf8mb4', $connections[$name]['charset']);
            $this->assertSame('utf8mb4_unicode_ci', $connections[$name]['collation']);
            $this->assertTrue($connections[$name]['strict']);
            $this->assertTrue($connections[$name]['prefix_indexes']);
        }

        $this->assertSame('127.0.0.1', $connections['mysql']['host']);
        $this->assertSame('3306', $connections['mysql']['port']);
        $this->assertSame('laravel', $connections['mysql']['database']);
        $this->assertSame('root', $connections['mysql']['username']);
        $this->assertSame('', $connections['mysql']['password']);
        $this->assertSame('', $connections['mysql']['unix_socket']);
        $this->assertNull($connections['mysql']['engine']);

        foreach (['mysql_testing', 'mysql_ft17_testing'] as $name) {
            foreach (['host', 'port', 'database', 'username', 'password', 'unix_socket'] as $key) {
                $this->assertNull($connections[$name][$key]);
            }
            $this->assertSame('InnoDB', $connections[$name]['engine']);
            $this->assertSame([], $connections[$name]['options']);
        }

        $this->assertSame('mariadb', $connections['mariadb']['driver']);
        $this->assertSame([], $connections['mariadb']['options']);
        $this->assertSame('pgsql', $connections['pgsql']['driver']);
        $this->assertSame('prefer', $connections['pgsql']['sslmode']);
        $this->assertSame('sqlsrv', $connections['sqlsrv']['driver']);
    }

    private function configuration(array $variables = []): array
    {
        // Load only the config array with synthetic environment values. No app boot,
        // environment files, PDO connection, migration or seeder is involved.
        $property = new ReflectionProperty(Env::class, 'repository');
        $previousRepository = $property->getValue();
        $previousContainer = Container::getInstance();
        $repository = RepositoryBuilder::createWithNoAdapters()
            ->addAdapter(ArrayAdapter::class)->make();
        foreach ($variables as $name => $value) {
            $repository->set($name, $value);
        }
        $application = $this->createMock(Application::class);
        $application->method('databasePath')->with('database.sqlite')
            ->willReturn('/synthetic/database/database.sqlite');

        try {
            $property->setValue(null, $repository);
            Container::setInstance($application);

            return require dirname(__DIR__, 2).'/config/database.php';
        } finally {
            $property->setValue(null, $previousRepository);
            Container::setInstance($previousContainer);
        }
    }
}
