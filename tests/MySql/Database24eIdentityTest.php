<?php

namespace Tests\MySql;

final class Database24eIdentityTest extends MySql24eTestCase
{
    public function test_runtime_connection_configuration_is_exact_and_isolated(): void
    {
        $configuration = config('database.connections.'.self::CONNECTION);

        $this->assertSame(self::CONNECTION, config('database.default'));
        $this->assertIsArray($configuration);
        $this->assertSame('mysql', $configuration['driver'] ?? null);
        $this->assertSame(self::DATABASE, $configuration['database'] ?? null);
        $this->assertSame(self::USERNAME, $configuration['username'] ?? null);
        $this->assertSame(self::HOST, $configuration['host'] ?? null);
        $this->assertSame(self::SOCKET, $configuration['unix_socket'] ?? null);
        $this->assertNotSame('', $configuration['password'] ?? '');
    }

    public function test_live_server_identity_and_transport_are_exact(): void
    {
        $identity = $this->verifiedIdentity();

        $this->assertSame(self::DATABASE, $identity->database_name);
        $this->assertSame(self::ACCOUNT, $identity->account_name);
        $this->assertMatchesRegularExpression('/\A8\.\d+(?:\.\d+)?/', $identity->version);
        $this->assertSame('InnoDB', $identity->default_engine);
        $this->assertSame(self::SOCKET, $identity->socket_path);
        $this->assertStringContainsString('unix socket', strtolower($this->verifiedConnectionStatus()));
    }

    public function test_account_privileges_are_limited_to_the_dedicated_database(): void
    {
        $this->assertSame(array_map(
            static fn (string $privilege): array => [self::DATABASE, $privilege, 'NO'],
            ['ALTER', 'CREATE', 'DELETE', 'DROP', 'INDEX', 'INSERT', 'REFERENCES', 'SELECT', 'UPDATE'],
        ), $this->verifiedSchemaPrivileges());
        $this->assertSame([['USAGE', 'NO']], $this->verifiedGlobalPrivileges());
        $this->assertSame(0, $this->verifiedRoutinePrivilegeCount());
        $this->assertSame(['global_usage', 'schema_privileges'], $this->verifiedGrantForms());
    }
}
