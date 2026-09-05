<?php

namespace Tests\MySql;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\TestCase;

abstract class MySqlTestCase extends TestCase
{
    protected const DATABASE = 'trackpro_test';

    protected const ACCOUNT = 'trackpro_test_user@localhost';

    protected const SOCKET = '/var/run/mysqld/mysqld.sock';

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardMySqlTestConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = DB::connection('mysql_testing');

        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    protected function guardMySqlTestConnection(): Connection
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('MySQL verification requires the testing environment.');
        }

        if (config('database.default') !== 'mysql_testing') {
            throw new RuntimeException('MySQL verification requires the dedicated mysql_testing connection.');
        }

        $configuration = config('database.connections.mysql_testing');

        if (! is_array($configuration)
            || $configuration['database'] !== self::DATABASE
            || $configuration['username'] !== 'trackpro_test_user'
            || $configuration['unix_socket'] !== self::SOCKET
            || ! in_array($configuration['host'], ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('MySQL verification connection configuration is not isolated and local.');
        }

        $connection = DB::connection('mysql_testing');
        $identity = $connection->selectOne(
            'SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name, '
            .'@@socket AS socket_path, @@default_storage_engine AS default_engine, VERSION() AS version'
        );

        if ($identity === null
            || $identity->database_name !== self::DATABASE
            || $identity->account_name !== self::ACCOUNT
            || $identity->socket_path !== self::SOCKET
            || strcasecmp($identity->default_engine, 'InnoDB') !== 0
            || ! str_starts_with($identity->version, '8.0.46')) {
            throw new RuntimeException('Live MySQL identity guard rejected the connected server.');
        }

        $connectionStatus = (string) $connection->getPdo()->getAttribute(PDO::ATTR_CONNECTION_STATUS);

        if (! str_contains(strtolower($connectionStatus), 'unix socket')) {
            throw new RuntimeException('MySQL verification must use the local Unix socket.');
        }

        return $connection;
    }

    /** @return list<string> */
    protected function tableInventory(): array
    {
        return array_map(
            static fn (object $row): string => $row->table_name,
            DB::connection('mysql_testing')->select(
                'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES '
                .'WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
                [self::DATABASE, 'BASE TABLE']
            )
        );
    }
}
