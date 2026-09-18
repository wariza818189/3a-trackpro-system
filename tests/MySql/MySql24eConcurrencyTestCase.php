<?php

namespace Tests\MySql;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

abstract class MySql24eConcurrencyTestCase extends MySql24eTestCase
{
    protected const CHILD_EXIT_SUCCESS = 0;

    protected const CHILD_EXIT_UNEXPECTED = 1;

    protected const CHILD_EXIT_TIMEOUT = 2;

    protected const CHILD_EXIT_EXCEPTION = 3;

    protected const CHILD_ALARM_SECONDS = 25;

    private const CHILD_WAIT_SECONDS = 30;

    private const CHILD_TERMINATION_GRACE_SECONDS = 2;

    private const CHILD_KILL_REAP_SECONDS = 2;

    private const CHILD_WAIT_POLL_MICROSECONDS = 50000;

    protected const IPC_READ_TIMEOUT_SECONDS = 10;

    protected const BLOCKING_OBSERVATION_MICROSECONDS = 500000;

    /** @var list<string> */
    private const EXPECTED_TABLES = [
        'audit_logs',
        'cash_register_sessions',
        'categories',
        'migrations',
        'product_variants',
        'products',
        'restock_items',
        'restocks',
        'sale_items',
        'sales',
        'stock_movements',
        'users',
    ];

    /** @var list<string> */
    private const EXPECTED_MIGRATIONS = [
        '0001_01_01_000000_create_users_table',
        '2026_09_05_000001_create_categories_table',
        '2026_09_05_000002_create_products_table',
        '2026_09_05_000003_create_product_variants_table',
        '2026_09_05_000004_create_sales_table',
        '2026_09_05_000005_create_sale_items_table',
        '2026_09_05_000006_create_restocks_table',
        '2026_09_05_000007_create_restock_items_table',
        '2026_09_05_000008_create_stock_movements_table',
        '2026_09_05_000009_create_audit_logs_table',
        '2026_09_17_000010_create_cash_register_sessions_table',
        '2026_09_17_000011_add_cash_register_session_id_to_sales_table',
    ];

    /** @var list<int> */
    private array $ownedChildPids = [];

    protected function tearDown(): void
    {
        try {
            $this->reapOwnedChildren();
        } finally {
            parent::tearDown();
        }
    }

    protected function guardedConcurrencyConnection(): Connection
    {
        try {
            return $this->guardConcurrencyConnection();
        } catch (MySql24eGuardException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MySql24eGuardException('The dedicated #24E concurrency guard failed safely.');
        }
    }

    private function guardConcurrencyConnection(): Connection
    {
        $connection = $this->guardedMySql24eConnection();
        $tables = array_map(
            static fn (object $row): string => (string) $row->table_name,
            $connection->select(
                'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES '
                .'WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
                [self::DATABASE, 'BASE TABLE'],
            ),
        );
        if ($tables !== self::EXPECTED_TABLES) {
            throw new MySql24eGuardException('The dedicated #24E concurrency schema inventory is not exact.');
        }

        $migrations = $connection->table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(static fn (mixed $migration): string => (string) $migration)
            ->all();
        if ($migrations !== self::EXPECTED_MIGRATIONS) {
            throw new MySql24eGuardException('The dedicated #24E concurrency migration ledger is not exact.');
        }

        $isolation = strtoupper(str_replace('_', '-', trim(
            (string) $connection->scalar('SELECT @@transaction_isolation'),
        )));
        if ($isolation !== 'REPEATABLE-READ') {
            throw new MySql24eGuardException('The dedicated #24E concurrency isolation level is not exact.');
        }

        if ((int) $connection->scalar('SELECT @@autocommit') !== 1) {
            throw new MySql24eGuardException('The dedicated #24E concurrency connection must begin with autocommit enabled.');
        }

        return $connection;
    }

    protected function prepareDedicatedConnectionForFork(): void
    {
        try {
            $cached = $this->cachedDedicatedConnection();
            if ($cached !== null) {
                $this->guardNoActiveTransaction($cached);
            }

            $connection = $this->guardedConcurrencyConnection();
            $this->guardNoActiveTransaction($connection);
        } catch (MySql24eGuardException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MySql24eGuardException('The dedicated #24E pre-fork connection check failed safely.');
        }

        DB::disconnect(self::CONNECTION);
        DB::purge(self::CONNECTION);
    }

    protected function reconnectParentAfterFork(): Connection
    {
        try {
            $cached = $this->cachedDedicatedConnection();
            if ($cached !== null) {
                $this->guardNoActiveTransaction($cached);
            }
        } catch (MySql24eGuardException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MySql24eGuardException('The dedicated #24E parent reconnection check failed safely.');
        }

        DB::disconnect(self::CONNECTION);
        DB::purge(self::CONNECTION);

        return $this->guardedConcurrencyConnection();
    }

    protected function reconnectChildAfterFork(): Connection
    {
        if (config('database.default') !== self::CONNECTION) {
            throw new MySql24eGuardException('The #24E child default connection is not exact.');
        }

        try {
            $cached = $this->cachedDedicatedConnection();
            if ($cached !== null) {
                $this->guardNoActiveTransaction($cached);
            }
        } catch (MySql24eGuardException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MySql24eGuardException('The dedicated #24E child reconnection check failed safely.');
        }

        DB::disconnect(self::CONNECTION);
        DB::purge(self::CONNECTION);

        return $this->guardedConcurrencyConnection();
    }

    protected function cleanupChildConnection(?Connection $connection): void
    {
        try {
            if ($connection !== null) {
                if ($connection->getName() !== self::CONNECTION
                    || $connection->getDatabaseName() !== self::DATABASE) {
                    throw new MySql24eGuardException('The #24E child cleanup connection is not exact.');
                }

                $pdo = $connection->getPdo();
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        } finally {
            DB::disconnect(self::CONNECTION);
            DB::purge(self::CONNECTION);
        }
    }

    protected function requireConcurrencySupport(): void
    {
        foreach ([
            'pcntl_fork',
            'pcntl_waitpid',
            'pcntl_alarm',
            'pcntl_async_signals',
            'pcntl_signal',
            'pcntl_wifexited',
            'pcntl_wexitstatus',
            'pcntl_get_last_error',
            'posix_kill',
            'stream_socket_pair',
            'stream_select',
        ] as $function) {
            if (! function_exists($function)) {
                throw new RuntimeException("{$function} is required for the dedicated #24E MySQL concurrency proof.");
            }
        }
    }

    /**
     * @param  callable(resource, Connection): int  $childWork
     * @return array{resource, int}
     */
    protected function forkGuardedWorker(callable $childWork): array
    {
        $this->requireConcurrencySupport();
        $this->prepareDedicatedConnectionForFork();
        [$parentSocket, $childSocket] = $this->createSocketPair();

        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->closeSocket($parentSocket);
            $this->closeSocket($childSocket);

            throw new RuntimeException('Unable to fork the dedicated #24E concurrency worker.');
        }

        if ($pid === 0) {
            $this->closeSocket($parentSocket);
            $this->runGuardedChild($childSocket, $childWork);
        }

        $this->closeSocket($childSocket);
        $this->ownedChildPids[] = $pid;

        return [$parentSocket, $pid];
    }

    /** @return array{resource, resource} */
    protected function createSocketPair(): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Unable to create the dedicated #24E concurrency IPC channel.');
        }

        return $sockets;
    }

    /** @param resource $socket */
    protected function writeStatus($socket, string $status): void
    {
        $this->guardStatus($status);
        $this->writeFrame($socket, $status);
    }

    /** @param resource $socket */
    protected function readStatus($socket, int $timeoutSeconds = self::IPC_READ_TIMEOUT_SECONDS): string
    {
        $status = $this->readFrame($socket, $timeoutSeconds);
        $this->guardStatus($status);

        return $status;
    }

    /** @param resource $socket @param array<string, mixed> $data */
    protected function writeResult($socket, string $status, array $data = []): void
    {
        $this->guardStatus($status);
        $this->guardJsonValue($data);
        $this->writeFrame($socket, json_encode([
            'status' => $status,
            'data' => $data,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param resource $socket @return array{status: string, data: array<string, mixed>} */
    protected function readResult($socket, int $timeoutSeconds = self::IPC_READ_TIMEOUT_SECONDS): array
    {
        $decoded = json_decode(
            $this->readFrame($socket, $timeoutSeconds),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        if (! is_array($decoded)
            || array_keys($decoded) !== ['status', 'data']
            || ! is_string($decoded['status'])
            || ! is_array($decoded['data'])) {
            throw new RuntimeException('The dedicated #24E worker returned an invalid result envelope.');
        }
        $this->guardStatus($decoded['status']);
        $this->guardJsonValue($decoded['data']);

        return $decoded;
    }

    /** @param resource $socket */
    protected function assertNoWorkerResult(
        $socket,
        string $message,
        int $microseconds = self::BLOCKING_OBSERVATION_MICROSECONDS,
    ): void {
        $read = [$socket];
        $write = null;
        $except = null;

        $this->assertSame(0, stream_select($read, $write, $except, 0, $microseconds), $message);
    }

    protected function waitForSuccessfulChild(int $pid): void
    {
        $this->waitForChild($pid, self::CHILD_EXIT_SUCCESS);
    }

    protected function waitForChild(int $pid, int $expectedExitCode): void
    {
        $outcome = $this->waitForOwnedChildBounded($pid);
        $status = $outcome['status'];

        $this->assertFalse(
            $outcome['forced'],
            'The dedicated #24E worker required forced termination.',
        );
        $this->assertTrue(pcntl_wifexited($status), 'The dedicated #24E worker did not exit normally.');
        $this->assertSame($expectedExitCode, pcntl_wexitstatus($status));
    }

    /** @param resource $socket */
    protected function closeSocket($socket): void
    {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    /** @param resource $socket @param callable(resource, Connection): int $childWork */
    private function runGuardedChild($socket, callable $childWork): never
    {
        $connection = null;
        $this->configureChildAlarm();

        try {
            $connection = $this->reconnectChildAfterFork();
            $exitCode = $childWork($socket, $connection);
            if (! in_array($exitCode, [self::CHILD_EXIT_SUCCESS, self::CHILD_EXIT_UNEXPECTED], true)) {
                throw new RuntimeException('The dedicated #24E worker returned an invalid exit code.');
            }

            $this->finishChild($socket, $connection, $exitCode);
        } catch (Throwable $exception) {
            try {
                $this->writeResult($socket, 'exception', ['class' => $exception::class]);
            } catch (Throwable) {
                // The parent will also detect the nonzero child exit.
            }

            $this->finishChild($socket, $connection, self::CHILD_EXIT_EXCEPTION);
        }
    }

    private function configureChildAlarm(): void
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function (): never {
            exit(self::CHILD_EXIT_TIMEOUT);
        });
        pcntl_alarm(self::CHILD_ALARM_SECONDS);
    }

    /** @param resource $socket */
    private function finishChild($socket, ?Connection $connection, int $exitCode): never
    {
        pcntl_alarm(0);

        try {
            $this->cleanupChildConnection($connection);
        } catch (Throwable) {
            $exitCode = self::CHILD_EXIT_EXCEPTION;
        }

        $this->closeSocket($socket);
        exit($exitCode);
    }

    private function cachedDedicatedConnection(): ?Connection
    {
        $connections = DB::getConnections();
        $connection = $connections[self::CONNECTION] ?? null;

        return $connection instanceof Connection ? $connection : null;
    }

    private function guardNoActiveTransaction(Connection $connection): void
    {
        if ($connection->getName() !== self::CONNECTION
            || $connection->getDatabaseName() !== self::DATABASE) {
            throw new MySql24eGuardException('The dedicated #24E fork connection is not exact.');
        }

        if ($connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
            throw new MySql24eGuardException('A dedicated #24E transaction is active at the fork boundary.');
        }
    }

    /** @param resource $socket */
    private function writeFrame($socket, string $payload): void
    {
        $frame = $payload."\n";
        $written = 0;
        $length = strlen($frame);

        while ($written < $length) {
            $bytes = fwrite($socket, substr($frame, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('The dedicated #24E worker IPC write failed.');
            }
            $written += $bytes;
        }
        fflush($socket);
    }

    /** @param resource $socket */
    private function readFrame($socket, int $timeoutSeconds): string
    {
        if ($timeoutSeconds < 1) {
            throw new RuntimeException('The dedicated #24E worker IPC timeout is invalid.');
        }

        $read = [$socket];
        $write = null;
        $except = null;
        if (stream_select($read, $write, $except, $timeoutSeconds) !== 1) {
            throw new RuntimeException('Timed out waiting for the dedicated #24E worker.');
        }

        $line = fgets($socket);
        if ($line === false) {
            throw new RuntimeException('The dedicated #24E worker IPC channel closed unexpectedly.');
        }

        return rtrim($line, "\r\n");
    }

    private function guardStatus(string $status): void
    {
        if (preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $status) !== 1) {
            throw new RuntimeException('The dedicated #24E worker status is invalid.');
        }
    }

    private function guardJsonValue(mixed $value): void
    {
        if (is_object($value) || is_resource($value)) {
            throw new RuntimeException('The dedicated #24E worker result contains an unsafe value.');
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->guardJsonValue($item);
            }
        }
    }

    private function forgetOwnedChild(int $pid): void
    {
        $this->ownedChildPids = array_values(array_filter(
            $this->ownedChildPids,
            static fn (int $ownedPid): bool => $ownedPid !== $pid,
        ));
    }

    /** @return array{status: int, forced: bool} */
    private function waitForOwnedChildBounded(int $pid): array
    {
        $this->guardOwnedChildPid($pid);

        $status = $this->pollOwnedChildUntil(
            $pid,
            $this->monotonicDeadlineAfterSeconds(self::CHILD_WAIT_SECONDS),
        );
        if ($status !== null) {
            return ['status' => $status, 'forced' => false];
        }

        $this->signalOwnedChild($pid, SIGTERM);
        $status = $this->pollOwnedChildUntil(
            $pid,
            $this->monotonicDeadlineAfterSeconds(self::CHILD_TERMINATION_GRACE_SECONDS),
        );
        if ($status !== null) {
            return ['status' => $status, 'forced' => true];
        }

        $this->signalOwnedChild($pid, SIGKILL);
        $status = $this->pollOwnedChildUntil(
            $pid,
            $this->monotonicDeadlineAfterSeconds(self::CHILD_KILL_REAP_SECONDS),
        );
        if ($status !== null) {
            return ['status' => $status, 'forced' => true];
        }

        throw new RuntimeException('The dedicated #24E worker could not be reaped within bounded recovery.');
    }

    private function guardOwnedChildPid(int $pid): void
    {
        if ($pid <= 0 || ! in_array($pid, $this->ownedChildPids, true)) {
            throw new RuntimeException('The requested PID is not owned by this #24E concurrency test.');
        }
    }

    private function signalOwnedChild(int $pid, int $signal): void
    {
        $this->guardOwnedChildPid($pid);

        // A false result may mean the child exited between the last poll and signal.
        // The following bounded reap decides whether ownership was actually resolved.
        posix_kill($pid, $signal);
    }

    private function pollOwnedChildUntil(int $pid, int $deadline): ?int
    {
        $this->guardOwnedChildPid($pid);

        do {
            $status = 0;
            $waitedPid = pcntl_waitpid($pid, $status, WNOHANG);

            if ($waitedPid === $pid) {
                $this->forgetOwnedChild($pid);

                return $status;
            }

            if ($waitedPid === -1) {
                if (pcntl_get_last_error() === PCNTL_EINTR) {
                    if (hrtime(true) >= $deadline) {
                        return null;
                    }

                    usleep(self::CHILD_WAIT_POLL_MICROSECONDS);

                    continue;
                }

                throw new RuntimeException('The dedicated #24E worker wait failed safely.');
            }

            if ($waitedPid !== 0) {
                throw new RuntimeException('The dedicated #24E worker wait returned an unexpected PID.');
            }

            if (hrtime(true) >= $deadline) {
                return null;
            }

            usleep(self::CHILD_WAIT_POLL_MICROSECONDS);
        } while (true);
    }

    private function monotonicDeadlineAfterSeconds(int $seconds): int
    {
        return hrtime(true) + ($seconds * 1_000_000_000);
    }

    private function reapOwnedChildren(): void
    {
        $recoveryFailed = false;

        foreach (array_values($this->ownedChildPids) as $pid) {
            try {
                $outcome = $this->waitForOwnedChildBounded($pid);
                $recoveryFailed = $outcome['forced'] || $recoveryFailed;
            } catch (Throwable) {
                $recoveryFailed = true;
            }
        }

        if ($recoveryFailed) {
            throw new RuntimeException('One or more dedicated #24E workers required failed or forced bounded recovery.');
        }
    }
}
